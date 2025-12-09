<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ShippingAddressRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Models\CategoryDiscountRule;
use App\Models\EasyOrder;
use App\Models\EasyOrdersGovernorateMapping;
use App\Models\Governorate;
use App\Models\Order;
use App\Traits\CalculatorTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EasyOrdersService
{
    use CalculatorTrait;

    public function __construct(
        private readonly CustomerRepositoryInterface          $customerRepo,
        private readonly OrderRepositoryInterface             $orderRepo,
        private readonly OrderDetailRepositoryInterface       $orderDetailRepo,
        private readonly ProductRepositoryInterface           $productRepo,
        private readonly ShippingAddressRepositoryInterface   $shippingAddressRepo,
        private readonly ShippingAddressService               $shippingAddressService,
        private readonly OrderDetailsService                  $orderDetailsService,
    ) {
    }

    /**
     * Parse SKU string like "313DMT(5)+XTJGAI(5)" into [ ['code'=>'313DMT','quantity'=>5], ... ].
     */
    public function parseSkuString(?string $sku): array
    {
        $result = [];
        if (!$sku) {
            return $result;
        }

        $parts = preg_split('/\+/', $sku);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_-]+)\((\d+)\)$/u', $part, $matches)) {
                $result[] = [
                    'code' => $matches[1],
                    'quantity' => (int)$matches[2],
                ];
            }
        }

        return $result;
    }

    /**
     * Find products by SKU code.
     *
     * @param array $skuItems [['code' => string, 'quantity' => int], ...]
     * @return array
     */
    public function findProductsBySku(array $skuItems): array
    {
        $codes = array_unique(array_column($skuItems, 'code'));
        if (empty($codes)) {
            return [];
        }

        $products = \App\Models\Product::whereIn('code', $codes)->get()->keyBy('code');

        $items = [];
        foreach ($skuItems as $item) {
            $code = $item['code'];
            $qty = (int)$item['quantity'];
            if ($qty <= 0) {
                continue;
            }
            $product = $products->get($code);
            if (!$product) {
                continue;
            }
            $items[] = [
                'product' => $product,
                'quantity' => $qty,
            ];
        }

        return $items;
    }

    public function mapGovernorate(?string $easyOrdersGovName): ?Governorate
    {
        if (!$easyOrdersGovName) {
            return null;
        }

        $easyOrdersGovName = trim($easyOrdersGovName);

        $mapping = EasyOrdersGovernorateMapping::where('easyorders_name', $easyOrdersGovName)->first();
        if ($mapping) {
            return $mapping->governorate;
        }

        // Fallback: try exact match on name_ar
        return Governorate::where('name_ar', $easyOrdersGovName)->first();
    }

    public function findSellerForGovernorate(?Governorate $governorate): ?int
    {
        if (!$governorate) {
            return null;
        }

        $seller = $governorate->sellers()->first();
        return $seller?->id;
    }

    /**
     * Build cart items and calculate category-based extra discount (same as POS).
     *
     * @param array $products [['product'=>Product,'quantity'=>int], ...]
     * @return array ['cartItems' => array, 'extraDiscount' => float]
     */
    public function buildCartAndCalculateDiscount(array $products): array
    {
        $cartItems = [];
        $totals = [
            'subtotal' => 0.0,
            'productDiscount' => 0.0,
            'totalTax' => 0.0,
        ];

        foreach ($products as $entry) {
            $product = $entry['product'];
            $qty = (int)$entry['quantity'];
            if ($qty <= 0) {
                continue;
            }

            $unitPrice = (float)$product['unit_price'];
            $unitDiscount = 0.0;
            $discountType = $product['discount_type'] ?? 'flat';
            $discountValue = (float)($product['discount'] ?? 0);
            if ($discountValue > 0) {
                if ($discountType === 'percent') {
                    $unitDiscount = ($unitPrice * $discountValue) / 100.0;
                } else {
                    $unitDiscount = $discountValue;
                }
            }

            $tax = (float)$product['tax'];
            $taxAmountPerUnit = $this->getTaxAmount($unitPrice, $tax);

            $item = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $unitPrice,
                'quantity' => $qty,
                'image' => $product['thumbnail'],
                'productType' => $product['product_type'],
                'unit' => $product['unit'],
                'tax' => $tax,
                'tax_type' => $product['tax_type'],
                'tax_model' => $product['tax_model'],
                'discount' => $unitDiscount,
                'discount_type' => 'flat',
                'variant' => '',
                'variations' => [],
                'category_id' => $product['category_id'] ?? 0,
                'is_gift' => false,
                'productSubtotal' => ($unitPrice - $unitDiscount) * $qty,
            ];

            $cartItems[] = $item;

            $itemSubtotal = $unitPrice * $qty;
            $itemDiscount = $unitDiscount * $qty;
            $totals['subtotal'] += $itemSubtotal;
            $totals['productDiscount'] += $itemDiscount;
            $totals['totalTax'] += $taxAmountPerUnit * $qty;
        }

        // Category rules discount (exclude gifts) – same logic as POS
        $categoryCounts = [];
        foreach ($cartItems as $ci) {
            if (!empty($ci['is_gift'])) {
                continue;
            }
            $catId = (int)Arr::get($ci, 'category_id', 0);
            if ($catId <= 0) {
                continue;
            }
            $categoryCounts[$catId] = ($categoryCounts[$catId] ?? 0) + (int)$ci['quantity'];
        }

        $categoryDiscount = 0.0;
        if (!empty($categoryCounts)) {
            $rules = CategoryDiscountRule::whereIn('category_id', array_keys($categoryCounts))
                ->where('is_active', true)
                ->orderBy('quantity', 'desc')
                ->get()
                ->groupBy('category_id');

            foreach ($categoryCounts as $catId => $count) {
                $remaining = $count;
                $catRules = ($rules[$catId] ?? collect())->sortByDesc('quantity');
                foreach ($catRules as $rule) {
                    if ($remaining < (int)$rule->quantity) {
                        continue;
                    }
                    $times = intdiv($remaining, (int)$rule->quantity);
                    if ($times <= 0) {
                        continue;
                    }
                    $categoryDiscount += ((float)$rule->discount_amount) * $times;
                    $remaining = $remaining % (int)$rule->quantity;
                }
            }
        }

        return [
            'cartItems' => $cartItems,
            'extraDiscount' => (float)$categoryDiscount,
            'totals' => $totals,
        ];
    }

    /**
     * Import a staged EasyOrder into the main orders system.
     */
    public function importOrder(EasyOrder $easyOrder): Order
    {
        if ($easyOrder->status === 'imported' && $easyOrder->imported_order_id) {
            /** @var Order $existing */
            $existing = $this->orderRepo->getFirstWhere(params: ['id' => $easyOrder->imported_order_id]);
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($easyOrder) {
            // 1) Resolve products from SKU
            $skuItems = $this->parseSkuString($easyOrder->sku_string ?? Arr::get($easyOrder->raw_payload, 'cart_items.0.product.sku'));
            $products = $this->findProductsBySku($skuItems);
            if (empty($products)) {
                throw new \RuntimeException('No matching products were found for the provided SKU.');
            }

            // 2) Governorate & seller mapping
            $governorate = $this->mapGovernorate($easyOrder->government);
            $sellerId = $this->findSellerForGovernorate($governorate);
            $cityId = $governorate?->id;

            // 3) Customer resolution (by phone) or creation
            $customer = null;
            $phone = $easyOrder->phone;
            if ($phone) {
                $customer = $this->customerRepo->getFirstWhere(['phone' => $phone]);
            }
            if (!$customer) {
                $nameParts = preg_split('/\s+/', trim((string)$easyOrder->full_name));
                $fName = $nameParts[0] ?? '';
                $lName = implode(' ', array_slice($nameParts, 1));

                $customer = $this->customerRepo->add([
                    'f_name' => $fName,
                    'l_name' => $lName,
                    'email' => null,
                    'phone' => $phone,
                    'password' => bcrypt('123456'),
                    'is_active' => 1,
                ]);
            }
            $userId = (int)$customer->id;

            // 4) Shipping address snapshot
            $addressData = $this->shippingAddressService->getAddAddressData(
                [
                    'city_id' => $cityId,
                    'city' => $easyOrder->government,
                    'f_name' => $customer->f_name,
                    'l_name' => $customer->l_name,
                    'address' => $easyOrder->address,
                    'zip_code' => null,
                    'country' => null,
                    'phone' => $customer->phone,
                ],
                $userId,
                'home'
            );

            $existingAddress = $this->shippingAddressRepo->getFirstWhere([
                'customer_id' => $userId,
                'address_type' => 'home',
            ]);

            if ($existingAddress) {
                $this->shippingAddressRepo->update($existingAddress->id, $addressData);
            } else {
                $this->shippingAddressRepo->add($addressData);
            }

            // 5) Build cart and calculate totals & extra discount
            $cartData = $this->buildCartAndCalculateDiscount($products);
            $cartItems = $cartData['cartItems'];
            $totals = $cartData['totals'];
            $extraDiscount = $cartData['extraDiscount'];

            $shippingCost = (float)$easyOrder->shipping_cost;
            $couponDiscount = 0.0;

            $serverAmount = $totals['subtotal']
                - $totals['productDiscount']
                + $totals['totalTax']
                + $shippingCost
                - $couponDiscount
                - $extraDiscount;

            if ($serverAmount < 0) {
                $serverAmount = 0;
            }

            $orderId = (int)(Order::max('id') ?? 99999) + 1;

            // 6) Create order details
            foreach ($cartItems as $item) {
                $product = $this->productRepo->getFirstWhere(params: ['id' => $item['id']]);
                if (!$product) {
                    continue;
                }

                $tax = $this->getTaxAmount($item['price'], (float)$product['tax']);
                $price = $product['tax_model'] == 'include' ? ($item['price'] - $tax) : $item['price'];

                $orderDetail = $this->orderDetailsService->getPOSOrderDetailsData(
                    orderId: $orderId,
                    item: $item,
                    product: $product,
                    price: $price,
                    tax: $tax
                );

                if ($product['product_type'] === 'physical') {
                    $currentStock = $product['current_stock'] - $item['quantity'];
                    $this->productRepo->update(id: $product['id'], data: ['current_stock' => $currentStock]);
                }

                $this->orderDetailRepo->add($orderDetail);
            }

            // 7) Create order
            $orderData = [
                'id' => $orderId,
                'customer_id' => $userId,
                'customer_type' => 'customer',
                'payment_status' => 'unpaid',
                'order_status' => 'pending',
                'seller_id' => $sellerId,
                'seller_is' => $sellerId ? 'seller' : 'admin',
                'payment_method' => 'cod',
                'order_type' => 'EasyOrders',
                'checked' => 1,
                'extra_discount' => $extraDiscount,
                'extra_discount_type' => $extraDiscount > 0 ? 'amount' : null,
                'order_amount' => currencyConverter(amount: $serverAmount),
                'paid_amount' => 0,
                'discount_amount' => $couponDiscount,
                'coupon_code' => null,
                'discount_type' => null,
                'coupon_discount_bearer' => 'inhouse',
                'city_id' => $cityId,
                'shipping_cost' => currencyConverter(amount: $shippingCost),
                'order_note' => Arr::get($easyOrder->raw_payload, 'metadata.note') ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->orderRepo->add($orderData);

            /** @var Order $order */
            $order = $this->orderRepo->getFirstWhere(params: ['id' => $orderId]);

            $this->orderRepo->update((string)$orderId, [
                'shipping_address_data' => json_encode($addressData),
            ]);

            // 8) Update EasyOrder staging row
            $easyOrder->status = 'imported';
            $easyOrder->imported_order_id = $orderId;
            $easyOrder->import_error = null;
            $easyOrder->imported_at = now();
            $easyOrder->save();

            return $order;
        });
    }
}



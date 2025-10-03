<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Contracts\Repositories\{OrderRepositoryInterface, OrderDetailRepositoryInterface, ProductRepositoryInterface, DigitalProductVariationRepositoryInterface, StorageRepositoryInterface, ShippingAddressRepositoryInterface, CustomerRepositoryInterface};
use App\Services\{OrderDetailsService, OrderService, ShippingAddressService};
use App\Traits\CalculatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CategoryDiscountRule;

class OrderUpdateAction extends Controller
{
    use CalculatorTrait;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly OrderDetailRepositoryInterface $orderDetailRepo,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly DigitalProductVariationRepositoryInterface $digitalProductVariationRepo,
        private readonly StorageRepositoryInterface $storageRepo,
        private readonly ShippingAddressRepositoryInterface $shippingAddressRepo,
        private readonly CustomerRepositoryInterface $customerRepo,
        private readonly OrderDetailsService $orderDetailsService,
        private readonly OrderService $orderService,
        private readonly ShippingAddressService $shippingAddressService,
    ) {}

    public function __invoke(Request $request, int|string $id)
    {
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['details']);
        if (!$order) {
            return response()->json(['error' => translate('order_not_found')], 404);
        }

        $clientCart = json_decode($request->input('cart_data', '{}'), true) ?: [];
        $cartItems = (array)($clientCart['items'] ?? []);
        if (empty($cartItems)) {
            return response()->json(['error' => translate('cart_empty_warning')], 422);
        }

        return DB::transaction(function () use ($request, $order, $clientCart, $cartItems) {
            // Resolve customer and order meta (city, seller) from request/client cart
            $userId = (int)($order->customer_id ?? 0);
            $cityId = (int)($request->get('city_id', data_get($clientCart, 'customer.city_id', $order->city_id)));
            $sellerId = (int)($request->get('seller_id', data_get($clientCart, 'customer.seller_id', $order->seller_id)));
            $shippingAddressForOrder = null;

            if ($request->has('customer_data')) {
                $cd = json_decode($request->input('customer_data', '{}'), true) ?: [];
                if (!empty($cd)) {
                    // Find current customer or resolve by phone
                    $customer = null;
                    if ($userId > 0) {
                        $customer = $this->customerRepo->getFirstWhere(['id' => $userId]);
                    }
                    if (!$customer && !empty($cd['phone'])) {
                        $customer = $this->customerRepo->getFirstWhere(['phone' => $cd['phone']]);
                    }

                    if ($customer) {
                        // Update existing customer data
                        $this->customerRepo->update($customer->id, [
                            'f_name' => $cd['f_name'] ?? ($customer->f_name ?? ''),
                            'l_name' => $cd['l_name'] ?? ($customer->l_name ?? ''),
                            'phone' => $cd['phone'] ?? ($customer->phone ?? null),
                            'alternative_phone' => $cd['alternative_phone'] ?? ($customer->alternative_phone ?? null),
                        ]);
                        $userId = (int)$customer->id;
                    } else {
                        // Create new customer if none found
                        $customer = $this->customerRepo->add([
                            'f_name' => $cd['f_name'] ?? '',
                            'l_name' => $cd['l_name'] ?? '',
                            'email' => null,
                            'phone' => $cd['phone'] ?? null,
                            'alternative_phone' => $cd['alternative_phone'] ?? null,
                            'password' => bcrypt('123456'),
                            'is_active' => 1,
                        ]);
                        $userId = (int)$customer->id;
                    }

                    // Update/create a simple HOME shipping address for the customer and embed snapshot on order
                    $existingAddress = $this->shippingAddressRepo->getFirstWhere([
                        'customer_id' => $userId,
                        'address_type' => 'home',
                    ]);
                    $addressData = $this->shippingAddressService->getAddAddressData(
                        array_merge($cd, ['l_name' => $cd['l_name'] ?? '']),
                        $userId,
                        'home'
                    );
                    // Persist address to shipping_addresses table (no alternative_phone column there)
                    if ($existingAddress) {
                        $this->shippingAddressRepo->update($existingAddress->id, $addressData);
                    } else {
                        $this->shippingAddressRepo->add($addressData);
                    }
                    // Prepare snapshot for order with alternative_phone included
                    $shippingAddressForOrder = $addressData;
                    if (!empty($cd['alternative_phone'])) {
                        $shippingAddressForOrder['alternative_phone'] = $cd['alternative_phone'];
                    }
                }
            }

            // Revert stock for old items
            foreach ($order->details as $od) {
                $product = $this->productRepo->getFirstWhere(params: ['id' => $od->product_id]);
                if ($product && ($product['product_type'] === 'physical')) {
                    $this->productRepo->update(id: $product['id'], data: ['current_stock' => $product['current_stock'] + $od->qty]);
                }
            }

            // Remove old details
            $this->orderDetailRepo->delete(params: ['order_id' => $order->id]);

            // Re-add new details and subtract stock
            $subtotal = 0.0; $productDiscount = 0.0; $totalTax = 0.0;
            foreach ($cartItems as $item) {
                $product = $this->productRepo->getFirstWhere(params: ['id' => $item['id']]);
                if (!$product) { continue; }
                $tax = $this->getTaxAmount($item['price'], $product['tax']);
                $price = $product['tax_model'] == 'include' ? ($item['price'] - $tax) : $item['price'];

                $subtotal += (float)$item['price'] * (int)$item['quantity'];
                $productDiscount += (float)$item['discount'] * (int)$item['quantity'];
                $totalTax += $tax * (int)$item['quantity'];

                if ($product['product_type'] === 'physical') {
                    $this->productRepo->update(id: $product['id'], data: [
                        'current_stock' => max(0, $product['current_stock'] - $item['quantity'])
                    ]);
                }

                $detail = $this->orderDetailsService->getPOSOrderDetailsData(
                    orderId: $order->id, item: $item, product: $product, price: $price, tax: $tax
                );
                $this->orderDetailRepo->add($detail);
            }

            $shippingCost = (float)($request->input('shipping_cost', session('selected_shipping_cost', 0)));
            $couponDiscount = (float)($clientCart['couponDiscount'] ?? 0);

            // Recompute extra discount server-side: category rules + manual extra (from request)
            // Category rules discount (exclude gifts)
            $categoryCounts = [];
            foreach ($cartItems as $ci) {
                if (!empty($ci['isGift']) || !empty($ci['is_gift'])) { continue; }
                $catId = (int)($ci['categoryId'] ?? ($ci['category_id'] ?? 0));
                if ($catId <= 0) { continue; }
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
                        if ($remaining < (int)$rule->quantity) { continue; }
                        $times = intdiv($remaining, (int)$rule->quantity);
                        if ($times <= 0) { continue; }
                        $categoryDiscount += ((float)$rule->discount_amount) * $times;
                        $remaining = $remaining % (int)$rule->quantity;
                    }
                }
            }

            // Manual extra from request (only if explicitly set)
            $manualExtra = 0.0;
            $manualExtraSet = (int)$request->get('manual_extra_set', 0) === 1;
            $extType = $manualExtraSet ? $request->get('ext_discount_type') : null;
            $extVal = $manualExtraSet ? (float)$request->get('ext_discount', 0) : 0.0;
            if ($manualExtraSet) {
                if ($extType === 'percent') {
                    $base = max(0.0, $subtotal - $productDiscount);
                    $manualExtra = ($base * $extVal) / 100.0;
                } elseif ($extVal > 0) {
                    $manualExtra = $extVal;
                }
            }

            $totalExtraDiscount = (float)$categoryDiscount + (float)$manualExtra;
            $serverAmount = max(0, $subtotal - $productDiscount + $totalTax + $shippingCost - $couponDiscount - $totalExtraDiscount);
            $paidAmount = $serverAmount; // Avoid change amount in POS edit flow

            $updateData = [
                'order_amount' => currencyConverter(amount: $serverAmount),
                'discount_amount' => $couponDiscount,
                'coupon_code' => $clientCart['coupon_code'] ?? null,
                'discount_type' => (!empty($clientCart['coupon_code']) ? 'coupon_discount' : null),
                'coupon_discount_bearer' => $clientCart['coupon_bearer'] ?? 'inhouse',
                'extra_discount' => $totalExtraDiscount,
                'extra_discount_type' => $totalExtraDiscount > 0 ? 'amount' : null,
                'shipping_cost' => currencyConverter(amount: $shippingCost),
                'order_note' => $request->input('order_note'),
                'paid_amount' => currencyConverter(amount: $paidAmount),
                'is_printed' => 0,
                'city_id' => $cityId,
                'seller_id' => $sellerId,
                'customer_id' => $userId,
            ];
            if (!empty($shippingAddressForOrder)) {
                $updateData['shipping_address_data'] = json_encode($shippingAddressForOrder);
            }
            $this->orderRepo->update(id: $order->id, data: $updateData);

            \Devrabiul\ToastMagic\Facades\ToastMagic::success(translate('order_updated_successfully'));
            return response()->json(['order_id' => $order->id]);
        });
    }
}



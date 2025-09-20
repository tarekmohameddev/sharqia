<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Contracts\Repositories\{OrderRepositoryInterface, OrderDetailRepositoryInterface, ProductRepositoryInterface, DigitalProductVariationRepositoryInterface, StorageRepositoryInterface, ShippingAddressRepositoryInterface, CustomerRepositoryInterface};
use App\Services\{OrderDetailsService, OrderService, ShippingAddressService};
use App\Traits\CalculatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            // Revert stock for old items
            foreach ($order->details as $od) {
                $product = $this->productRepo->getFirstWhere(params: ['id' => $od->product_id]);
                if ($product && ($product['product_type'] === 'physical')) {
                    $this->productRepo->update(id: $product['id'], data: ['current_stock' => $product['current_stock'] + $od->qty]);
                }
            }

            // Remove old details
            $this->orderDetailRepo->deleteWhere(params: ['order_id' => $order->id]);

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
            $couponDiscount = (float)($clientCart['coupon_discount'] ?? 0);
            $extraDiscount = (float)($clientCart['ext_discount'] ?? 0);
            $serverAmount = max(0, $subtotal - $productDiscount + $totalTax + $shippingCost - $couponDiscount - $extraDiscount);

            $this->orderRepo->update(id: $order->id, data: [
                'order_amount' => currencyConverter(amount: $serverAmount),
                'discount_amount' => $couponDiscount,
                'coupon_code' => $clientCart['coupon_code'] ?? null,
                'discount_type' => (!empty($clientCart['coupon_code']) ? 'coupon_discount' : null),
                'coupon_discount_bearer' => $clientCart['coupon_bearer'] ?? 'inhouse',
                'extra_discount' => $extraDiscount,
                'extra_discount_type' => $extraDiscount > 0 ? 'amount' : null,
                'shipping_cost' => currencyConverter(amount: $shippingCost),
                'order_note' => $request->input('order_note'),
                'is_printed' => 0,
            ]);

            \Devrabiul\ToastMagic\Facades\ToastMagic::success(translate('order_updated_successfully'));
            return response()->json(['order_id' => $order->id]);
        });
    }
}



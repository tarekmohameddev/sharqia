<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Governorate;
use App\Models\Order;
use App\Services\OrderDetailsService;
use App\Services\OrderService;
use App\Services\POSService;
use App\Traits\CalculatorTrait;
use App\Traits\CommonTrait;
use App\Utils\CartManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SimpleOrderController extends Controller
{
    use CalculatorTrait;
    use CommonTrait;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderDetailsService $orderDetailsService,
        private readonly POSService $posService,
    ) {}

    public function placeOrder(Request $request): JsonResponse
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone'         => 'required|string|max:20',
            'address'       => 'required|string|max:1000',
            'city_id'       => 'required|integer',
            'order_note'    => 'nullable|string|max:1000',
            'guest_id'      => 'nullable|string',
        ]);

        $apiUser   = auth('api')->user();
        $isGuest   = $apiUser === null ? 1 : 0;
        $customerId = $isGuest ? $request->input('guest_id') : $apiUser->id;

        if ($isGuest && !$request->filled('guest_id')) {
            return response()->json(['message' => translate('guest_id_required_for_guest_checkout')], 422);
        }

        $cartGroupIds = CartManager::get_cart_group_ids(request: $request, type: 'checked');

        if (empty($cartGroupIds)) {
            return response()->json(['message' => translate('cart_is_empty')], 403);
        }

        $carts = Cart::whereHas('product', fn($q) => $q->active())
            ->with('product')
            ->whereIn('cart_group_id', $cartGroupIds)
            ->where('is_checked', 1)
            ->get();

        if ($carts->isEmpty()) {
            return response()->json(['message' => translate('cart_is_empty')], 403);
        }

        if (!CartManager::product_stock_check($carts)) {
            return response()->json(['message' => translate('out_of_stock')], 403);
        }

        $governorate = Governorate::with(['sellers', 'shippingCost'])->find($request->city_id);

        if (!$governorate) {
            return response()->json(['message' => translate('governorate_not_found')], 404);
        }

        $seller = $governorate->sellers->first();

        if (!$seller) {
            return response()->json(['message' => translate('no_seller_assigned_to_this_governorate')], 404);
        }

        $sellerId     = $seller->id;
        $shippingCost = (float)($governorate->shippingCost?->cost ?? 0);

        $orderId        = (int)(Order::max('id') ?? 99999) + 1;
        $subtotal       = 0.0;
        $productDiscount = 0.0;
        $taxTotal       = 0.0;

        DB::beginTransaction();

        try {
            foreach ($carts as $cart) {
                $product = $cart->product;

                if (!$product) {
                    continue;
                }

                $taxRate   = (float)($product->tax ?? 0);
                $taxAmount = $this->getTaxAmount($cart->price, $taxRate);
                $price     = $product->tax_model === 'include'
                    ? $cart->price - $taxAmount
                    : $cart->price;

                $item = [
                    'id'         => $cart->product_id,
                    'quantity'   => $cart->quantity,
                    'price'      => $cart->price,
                    'discount'   => $cart->discount,
                    'variant'    => $cart->variant,
                    'variations' => json_decode($cart->variations ?? '[]', true) ?? [],
                ];

                $detail = $this->orderDetailsService->getPOSOrderDetailsData(
                    orderId: $orderId,
                    item: $item,
                    product: $product,
                    price: $price,
                    tax: $taxAmount,
                );

                $detail['payment_status']  = 'unpaid';
                $detail['product_details'] = json_encode($product);

                DB::table('order_details')->insert($detail);

                if ($cart->variant) {
                    $variantData = $this->posService->getVariantData(
                        type: $cart->variant,
                        variation: json_decode($product->variation ?? '[]', true) ?? [],
                        quantity: $cart->quantity,
                    );
                    $product->update(['variation' => json_encode($variantData)]);
                }

                if ($product->product_type === 'physical') {
                    $newStock = max(0, $product->current_stock - $cart->quantity);
                    $product->update(['current_stock' => $newStock]);
                }

                $subtotal        += $cart->price * $cart->quantity;
                $productDiscount += $cart->discount * $cart->quantity;
                $taxTotal        += $taxAmount * $cart->quantity;
            }

            $orderAmount = max(0.0, $subtotal - $productDiscount + $taxTotal + $shippingCost);

            $shippingAddressData = json_encode([
                'contact_person_name' => $request->customer_name,
                'phone'               => $request->phone,
                'address'             => $request->address,
                'city_id'             => $request->city_id,
            ]);

            $orderData = $this->orderService->getSimpleOrderData(
                orderId: $orderId,
                amount: $orderAmount,
                userId: $customerId,
                isGuest: $isGuest,
                sellerId: $sellerId,
                cityId: $request->city_id,
                shippingCost: $shippingCost,
                orderNote: $request->order_note,
            );

            DB::table('orders')->insert(array_merge($orderData, [
                'shipping_address_data' => $shippingAddressData,
            ]));

            self::add_order_status_history($orderId, $customerId, 'pending', 'customer');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => translate('something_went_wrong')], 500);
        }

        CartManager::cartCleanByCartGroupIds(cartGroupIDs: $cartGroupIds);

        return response()->json([
            'message'  => translate('order_placed_successfully'),
            'order_id' => $orderId,
        ]);
    }
}

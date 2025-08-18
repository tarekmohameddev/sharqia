<?php

namespace App\Services;

use App\Enums\SessionKey;
use App\Traits\CalculatorTrait;
use Brian2694\Toastr\Facades\Toastr;

class POSService
{
    use CalculatorTrait;

    public function getTotalHoldOrders(array $carts): int
    {
        $totalHoldOrders = 0;
        foreach ($carts as $cart) {
            if (is_array($cart) && count($cart) > 1) {
                if (isset($cart[0]) && is_array($cart[0]) && ($cart[0]['customerOnHold'] ?? false)) {
                    $totalHoldOrders++;
                }
            }
        }
        return $totalHoldOrders;
    }

    public function getCartNames(array $carts): array
    {
        $cartNames = [];
        foreach ($carts as $name => $cart) {
            if (is_array($cart) && count($cart) > 1) {
                $cartNames[] = $name;
            }
        }
        return $cartNames;
    }

    public function checkConditions(float $amount, array $cart, ?float $paidAmount = null): bool
    {
        $condition = false;
        if (count($cart) < 1) {
            Toastr::error(translate('cart_empty_warning'));
            $condition = true;
        }
        if ($amount <= 0) {
            Toastr::error(translate('amount_cannot_be_lees_then_0'));
            $condition = true;
        }
        if (!is_null($paidAmount) && $paidAmount < $amount) {
            Toastr::error(translate('paid_amount_is_less_than_total_amount'));
            $condition = true;
        }
        return $condition;
    }

    public function getCouponCalculation(object $coupon, float $totalProductPrice, float $productDiscount, float $productTax, array $cart = []): array
    {
        $extraDiscount = 0;
        if ($coupon['discount_type'] === 'percentage') {
            $discount = min((($totalProductPrice / 100) * $coupon['discount']), $coupon['max_discount']);
        } else {
            $discount = $coupon['discount'];
        }
        if (isset($cart['ext_discount_type'])) {
            $extraDiscount = $this->getDiscountAmount(price: $totalProductPrice, discount: $cart['ext_discount'], discountType: $cart['ext_discount_type']);
        }
        $total = $totalProductPrice - $productDiscount + $productTax - $discount - $extraDiscount;
        return [
            'total' => $total,
            'discount' => $discount,
        ];
    }

    public function applyCouponToCart(array $cart, $discount, $couponTitle, $couponBearer, $couponCode): array
    {
        $cart['coupon_code'] = $couponCode;
        $cart['coupon_discount'] = $discount;
        $cart['coupon_title'] = $couponTitle;
        $cart['coupon_bearer'] = $couponBearer;
        return $cart;
    }

    public function getVariantData(string $type, array $variation, int $quantity): array
    {
        $variationData = [];
        foreach ($variation as $variant) {
            if ($type == $variant['type']) {
                $variant['qty'] -= $quantity;
            }
            $variationData[] = $variant;
        }
        return $variationData;
    }

    public function getSummaryData(array $carts, string $currentUser): array
    {
        return [
            'cartName' => array_keys($carts),
            'currentUser' => $currentUser,
            'totalHoldOrders' => $this->getTotalHoldOrders($carts),
            'cartNames' => $this->getCartNames($carts),
        ];
    }
}

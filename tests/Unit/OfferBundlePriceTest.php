<?php

use App\Utils\CartManager;
use App\Utils\OrderManager;

test('order summary uses bundle price', function () {
    $cart = collect([
        (object) [
            'price' => 100,
            'bundle_price' => 80,
            'offer_id' => 1,
            'quantity' => 2,
            'discount' => 5,
            'seller_id' => 1,
        ],
    ]);

    $summary = OrderManager::getOrderSummaryBeforePlaceOrder($cart, 0);

    expect($summary['order_total'])->toBe((80 * 2) - (5 * 2));
});

test('cart totals use bundle price', function () {
    $cart = [
        [
            'price' => 100,
            'bundle_price' => 50,
            'offer_id' => 1,
            'quantity' => 3,
            'tax' => 10,
            'discount' => 0,
            'tax_model' => 'exclude',
        ],
    ];

    expect(CartManager::cart_total($cart))->toBe(150);
    expect(CartManager::cart_total_with_tax($cart))->toBe(180);
});

<?php

namespace Tests\Feature;

use Tests\TestCase;

class OrderSubmissionTest extends TestCase
{
    public function testSubmittingFullCart()
    {
        $payload = [
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson('/api/v1/order/submit', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'order_submitted',
                'items' => $payload['items'],
            ]);
    }
}

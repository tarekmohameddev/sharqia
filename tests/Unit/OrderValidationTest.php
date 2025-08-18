<?php

namespace Tests\Unit;

use Tests\TestCase;

class OrderValidationTest extends TestCase
{
    public function testSubmittingCartRequiresItems()
    {
        $response = $this->postJson('/api/v1/order/submit', []);
        $response->assertStatus(422);
    }
}

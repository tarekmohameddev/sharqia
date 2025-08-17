<?php

namespace Tests\Unit;

use App\Models\StockClearanceProduct;
use Tests\TestCase;

class StockClearanceOfferTest extends TestCase
{
    public function test_scope_blocks_non_pos_requests(): void
    {
        $this->app['request']->server->set('REQUEST_URI', '/not-pos');
        $sql = StockClearanceProduct::query()->active()->toSql();
        $this->assertStringContainsString('0 = 1', $sql);
    }

    public function test_scope_checks_product_stock_and_allows_pos_requests(): void
    {
        $this->app['request']->server->set('REQUEST_URI', 'admin/pos/test');
        $sql = StockClearanceProduct::query()->active()->toSql();
        $this->assertStringNotContainsString('0 = 1', $sql);
        $this->assertStringContainsString('current_stock', $sql);
    }
}

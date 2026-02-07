<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Optimized order statistics service using SQL aggregations instead of loading all data into PHP.
 * This dramatically improves performance for dashboards and reports with large datasets.
 */
class OrderStatsService
{
    private const CACHE_TTL = 60; // Cache for 60 seconds

    /**
     * Get order component totals using SQL aggregations with SUBQUERIES and CACHING
     *
     * @param array $filters Order filters
     * @param bool $includeProducts Include products in total
     * @param bool $includeShipping Include shipping costs
     * @param bool $includeDiscounts Subtract discounts from total
     * @param bool $includeDelivery Include delivery fees
     * @return array Components breakdown and custom total
     */
    public function getOrderComponentTotals(
        array $filters,
        bool $includeProducts = true,
        bool $includeShipping = true,
        bool $includeDiscounts = true,
        bool $includeDelivery = true
    ): array {
        // Create cache key based on filters
        $cacheKey = 'order_component_totals_' . md5(json_encode($filters));
        
        // Try to get from cache first
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== null) {
            // Recalculate custom total based on checkbox selections
            $products = $includeProducts ? (float) $cachedData['raw_products'] : 0;
            $shipping = $includeShipping ? (float) $cachedData['raw_shipping'] : 0;
            $discounts = $includeDiscounts ? (float) $cachedData['raw_discounts'] : 0;
            $delivery = $includeDelivery ? (float) $cachedData['raw_delivery'] : 0;
            $customTotal = $products + $shipping - $discounts + $delivery;
            
            return [
                'products' => $products,
                'shipping' => $shipping,
                'discounts' => $discounts,
                'delivery' => $delivery,
                'custom_total' => $customTotal,
                'product_sales_total' => (float) $cachedData['raw_products'],
                'breakdown' => [
                    'include_products' => $includeProducts,
                    'include_shipping' => $includeShipping,
                    'include_discounts' => $includeDiscounts,
                    'include_delivery' => $includeDelivery,
                ],
            ];
        }

        // Build the base query with filters - use as SUBQUERY instead of fetching IDs
        $orderSubquery = $this->buildFilteredOrderQuery($filters)->select('id');

        // Use SQL SUM with subquery - no need to fetch IDs into PHP
        $orderTotals = Order::whereIn('id', $orderSubquery)
            ->selectRaw('
                COUNT(*) as order_count,
                COALESCE(SUM(shipping_cost), 0) as total_shipping,
                COALESCE(SUM(discount_amount), 0) + COALESCE(SUM(extra_discount), 0) as total_discounts,
                COALESCE(SUM(deliveryman_charge), 0) as total_delivery
            ')
            ->first();

        if (($orderTotals->order_count ?? 0) == 0) {
            return [
                'products' => 0,
                'shipping' => 0,
                'discounts' => 0,
                'delivery' => 0,
                'custom_total' => 0,
                'product_sales_total' => 0,
                'breakdown' => [
                    'include_products' => $includeProducts,
                    'include_shipping' => $includeShipping,
                    'include_discounts' => $includeDiscounts,
                    'include_delivery' => $includeDelivery,
                ],
            ];
        }

        // Use subquery for product totals
        $productTotal = OrderDetail::whereIn('order_id', $orderSubquery)
            ->selectRaw('COALESCE(SUM(qty * price), 0) as total_products')
            ->value('total_products') ?? 0;

        // Store raw values in cache (before applying checkbox selections)
        $rawData = [
            'raw_products' => (float) $productTotal,
            'raw_shipping' => (float) ($orderTotals->total_shipping ?? 0),
            'raw_discounts' => (float) ($orderTotals->total_discounts ?? 0),
            'raw_delivery' => (float) ($orderTotals->total_delivery ?? 0),
        ];
        Cache::put($cacheKey, $rawData, self::CACHE_TTL);

        $products = $includeProducts ? (float) $productTotal : 0;
        $shipping = $includeShipping ? (float) ($orderTotals->total_shipping ?? 0) : 0;
        $discounts = $includeDiscounts ? (float) ($orderTotals->total_discounts ?? 0) : 0;
        $delivery = $includeDelivery ? (float) ($orderTotals->total_delivery ?? 0) : 0;

        // Calculate custom total: Products + Shipping - Discounts + Delivery
        $customTotal = $products + $shipping - $discounts + $delivery;

        return [
            'products' => $products,
            'shipping' => $shipping,
            'discounts' => $discounts,
            'delivery' => $delivery,
            'custom_total' => $customTotal,
            'product_sales_total' => (float) $productTotal,
            'breakdown' => [
                'include_products' => $includeProducts,
                'include_shipping' => $includeShipping,
                'include_discounts' => $includeDiscounts,
                'include_delivery' => $includeDelivery,
            ],
        ];
    }

    /**
     * Get product sales total using SQL aggregation with SUBQUERY
     */
    public function getProductSalesTotal(array $filters): float
    {
        // Build the base query with filters - use as SUBQUERY
        $orderSubquery = $this->buildFilteredOrderQuery($filters)->select('id');

        return (float) OrderDetail::whereIn('order_id', $orderSubquery)
            ->selectRaw('COALESCE(SUM(qty * price), 0) as total')
            ->value('total') ?? 0;
    }

    /**
     * Build a filtered order query based on filters (without fetching data)
     */
    private function buildFilteredOrderQuery(array $filters)
    {
        return Order::query()
            ->when(isset($filters['seller_is']) && $filters['seller_is'] != 'all', function ($query) use ($filters) {
                return $query->where('seller_is', $filters['seller_is']);
            })
            ->when(isset($filters['seller_id']) && $filters['seller_id'] != 'all', function ($query) use ($filters) {
                return $query->where('seller_id', $filters['seller_id']);
            })
            ->when(isset($filters['order_type']) && $filters['order_type'] != 'all', function ($query) use ($filters) {
                return $query->where('order_type', $filters['order_type']);
            })
            ->when(isset($filters['order_status']) && $filters['order_status'] != 'all', function ($query) use ($filters) {
                return $query->where('order_status', $filters['order_status']);
            })
            ->when(isset($filters['customer_id']) && $filters['customer_id'] != 'all', function ($query) use ($filters) {
                return $query->where('customer_id', $filters['customer_id']);
            })
            ->when(isset($filters['city_id']) && $filters['city_id'] != 'all', function ($query) use ($filters) {
                return $query->where('city_id', $filters['city_id']);
            })
            ->when(isset($filters['is_printed']) && $filters['is_printed'] !== 'all', function ($query) use ($filters) {
                return $query->where('is_printed', (bool)$filters['is_printed']);
            })
            ->when(isset($filters['delivery_man_id']), function ($query) use ($filters) {
                return $query->where('delivery_man_id', $filters['delivery_man_id']);
            })
            ->when(isset($filters['date_type']) && $filters['date_type'] == "this_year", function ($query) use ($filters) {
                $dateField = $filters['date_field'] ?? 'created_at';
                return $query->whereYear($dateField, date('Y'));
            })
            ->when(isset($filters['date_type']) && $filters['date_type'] == "this_month", function ($query) use ($filters) {
                $dateField = $filters['date_field'] ?? 'created_at';
                return $query->whereMonth($dateField, date('m'))->whereYear($dateField, date('Y'));
            })
            ->when(isset($filters['date_type']) && $filters['date_type'] == "this_week", function ($query) use ($filters) {
                $dateField = $filters['date_field'] ?? 'created_at';
                return $query->whereBetween($dateField, [now()->startOfWeek(), now()->endOfWeek()]);
            })
            ->when(isset($filters['date_type']) && $filters['date_type'] == "custom_date" && isset($filters['from']) && isset($filters['to']), function ($query) use ($filters) {
                $dateField = $filters['date_field'] ?? 'created_at';
                return $query->whereDate($dateField, '>=', $filters['from'])
                    ->whereDate($dateField, '<=', $filters['to']);
            })
            ->when(isset($filters['filter']), function ($query) use ($filters) {
                // Only add order_type from filter when order_source has not already set it (avoids conflicting WHEREs)
                $query->when($filters['filter'] == 'POS' && !isset($filters['order_type']), function ($query) {
                    return $query->where('order_type', 'POS');
                })
                    ->when($filters['filter'] == 'default_type' && !isset($filters['order_type']), function ($query) {
                        return $query->where('order_type', 'default_type');
                    });
            });
    }

    /**
     * Get cached dashboard statistics with SQL counts
     *
     * @param array $dateFilter Optional date filter ['from' => Carbon, 'to' => Carbon]
     * @return array Dashboard statistics
     */
    public function getDashboardOrderStats(array $dateFilter = []): array
    {
        $cacheKey = 'dashboard_order_stats_' . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dateFilter) {
            $baseQuery = Order::query();

            if (!empty($dateFilter['from']) && !empty($dateFilter['to'])) {
                $baseQuery->whereBetween('created_at', [$dateFilter['from'], $dateFilter['to']]);
            }

            // Single query to get all status counts
            $statusCounts = Order::query()
                ->when(!empty($dateFilter['from']) && !empty($dateFilter['to']), function ($query) use ($dateFilter) {
                    return $query->whereBetween('created_at', [$dateFilter['from'], $dateFilter['to']]);
                })
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN order_status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery,
                    SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN order_status = 'returned' THEN 1 ELSE 0 END) as returned,
                    SUM(CASE WHEN order_status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN order_status = 'canceled' THEN 1 ELSE 0 END) as canceled
                ")
                ->first();

            return [
                'order' => (int) ($statusCounts->total ?? 0),
                'pending' => (int) ($statusCounts->pending ?? 0),
                'confirmed' => (int) ($statusCounts->confirmed ?? 0),
                'processing' => (int) ($statusCounts->processing ?? 0),
                'out_for_delivery' => (int) ($statusCounts->out_for_delivery ?? 0),
                'delivered' => (int) ($statusCounts->delivered ?? 0),
                'returned' => (int) ($statusCounts->returned ?? 0),
                'failed' => (int) ($statusCounts->failed ?? 0),
                'canceled' => (int) ($statusCounts->canceled ?? 0),
            ];
        });
    }
}

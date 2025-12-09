<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\AdminWalletRepositoryInterface;
use App\Contracts\Repositories\BrandRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\OrderTransactionRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\RestockProductRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Repositories\VendorWalletRepositoryInterface;
use App\Http\Controllers\BaseController;
use App\Services\DashboardService;
use App\Services\OrderStatsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseController
{
    private const CACHE_TTL = 60; // Cache dashboard stats for 60 seconds

    public function __construct(
        private readonly AdminWalletRepositoryInterface      $adminWalletRepo,
        private readonly CustomerRepositoryInterface         $customerRepo,
        private readonly OrderTransactionRepositoryInterface $orderTransactionRepo,
        private readonly ProductRepositoryInterface          $productRepo,
        private readonly DeliveryManRepositoryInterface      $deliveryManRepo,
        private readonly OrderRepositoryInterface            $orderRepo,
        private readonly BrandRepositoryInterface            $brandRepo,
        private readonly VendorRepositoryInterface           $vendorRepo,
        private readonly VendorWalletRepositoryInterface     $vendorWalletRepo,
        private readonly RestockProductRepositoryInterface   $restockProductRepo,
        private readonly DashboardService                    $dashboardService,
        private readonly OrderStatsService                   $orderStatsService,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     * Index function is the starting point of a controller 
     */
    public function index(Request|null $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        // Use cache for expensive queries - these are dashboard stats that don't need real-time updates
        $dashboardData = Cache::remember('dashboard_main_data', self::CACHE_TTL, function () {
            // Use SQL LIMIT instead of fetching all and taking in PHP
            $mostRatedProducts = $this->productRepo->getTopRatedList(dataLimit: DASHBOARD_DATA_LIMIT);
            $topSellProduct = $this->productRepo->getTopSellList(relations: ['orderDetails'], dataLimit: DASHBOARD_TOP_SELL_DATA_LIMIT);
            $topCustomer = $this->orderRepo->getTopCustomerList(relations: ['customer'], dataLimit: DASHBOARD_DATA_LIMIT);
            $topRatedDeliveryMan = $this->deliveryManRepo->getTopRatedList(filters: ['seller_id' => 0], relations: ['deliveredOrders'], dataLimit: DASHBOARD_DATA_LIMIT);
            $topVendorByEarning = $this->vendorWalletRepo->getListWhere(orderBy: ['total_earning' => 'desc'], filters: [['column' => 'total_earning', 'operator' => '>', 'value' => 0]], relations: ['seller.shop'], dataLimit: DASHBOARD_DATA_LIMIT);
            $topVendorByOrderReceived = $this->vendorRepo->getTopVendorListByWishlist(relations: ['shop'], dataLimit: DASHBOARD_DATA_LIMIT);

            return [
                'mostRatedProducts' => $mostRatedProducts,
                'topSellProduct' => $topSellProduct,
                'topCustomer' => $topCustomer,
                'topRatedDeliveryMan' => $topRatedDeliveryMan,
                'topVendorByEarning' => $topVendorByEarning,
                'topVendorByOrderReceived' => $topVendorByOrderReceived,
            ];
        });

        // Get order status counts using optimized SQL (cached)
        $data = self::getOrderStatusData();
        $admin_wallet = $this->adminWalletRepo->getFirstWhere(params: ['admin_id' => 1]);

        $from = now()->startOfYear()->format('Y-m-d'); 
        $to = now()->endOfYear()->format('Y-m-d');
        $range = range(1, 12);
        $label = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        
        // Cache earning statistics
        $earningStats = Cache::remember('dashboard_earning_stats', self::CACHE_TTL, function () use ($from, $to, $range) {
            return [
                'inHouseOrderEarning' => $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: 'month', userType: 'admin'),
                'vendorOrderEarning' => $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: 'month', userType: 'seller'),
                'inHouseEarning' => $this->getEarning(from: $from, to: $to, range: $range, type: 'month', userType: 'admin'),
                'vendorEarning' => $this->getEarning(from: $from, to: $to, range: $range, type: 'month', userType: 'seller'),
                'commissionEarn' => $this->getAdminCommission(from: $from, to: $to, range: $range, type: 'month'),
            ];
        });

        $inHouseOrderEarningArray = $earningStats['inHouseOrderEarning'];
        $vendorOrderEarningArray = $earningStats['vendorOrderEarning'];
        $inHouseEarning = $earningStats['inHouseEarning'];
        $vendorEarning = $earningStats['vendorEarning'];
        $commissionEarn = $earningStats['commissionEarn'];
        $dateType = 'yearEarn';

        // Use optimized SQL COUNT instead of loading all records
        $entityCounts = Cache::remember('dashboard_entity_counts', self::CACHE_TTL, function () {
            return [
                'order' => $this->orderRepo->getCountWhere(),
                'brand' => $this->brandRepo->getCountWhere(),
                'customer' => $this->customerRepo->getCountWhere(filters: ['avoid_walking_customer' => 1]),
                'vendor' => $this->vendorRepo->getCountWhere(),
                'deliveryMan' => $this->deliveryManRepo->getCountWhere(filters: ['seller_id' => 0]),
            ];
        });

        $data += [
            'order' => $entityCounts['order'],
            'brand' => $entityCounts['brand'],
            'topSellProduct' => $dashboardData['topSellProduct'],
            'mostRatedProducts' => $dashboardData['mostRatedProducts'],
            'topVendorByEarning' => $dashboardData['topVendorByEarning'],
            'top_customer' => $dashboardData['topCustomer'],
            'top_store_by_order_received' => $dashboardData['topVendorByOrderReceived'],
            'topRatedDeliveryMan' => $dashboardData['topRatedDeliveryMan'],
            'inhouse_earning' => $admin_wallet['inhouse_earning'] ?? 0,
            'commission_earned' => $admin_wallet['commission_earned'] ?? 0,
            'delivery_charge_earned' => $admin_wallet['delivery_charge_earned'] ?? 0,
            'pending_amount' => $admin_wallet['pending_amount'] ?? 0,
            'total_tax_collected' => $admin_wallet['total_tax_collected'] ?? 0,
            'getTotalCustomerCount' => $entityCounts['customer'],
            'getTotalVendorCount' => $entityCounts['vendor'],
            'getTotalDeliveryManCount' => $entityCounts['deliveryMan'],
        ];
        return view('admin-views.system.dashboard', compact('data', 'inHouseEarning', 'vendorEarning', 'commissionEarn', 'inHouseOrderEarningArray', 'vendorOrderEarningArray', 'label', 'dateType'));
    }

    public function getOrderStatus(Request $request)
    {
        session()->put('statistics_type', $request['statistics_type']);
        $data = self::getOrderStatusData();
        return response()->json(['view' => view('admin-views.partials._dashboard-order-status', compact('data'))->render()], 200);
    }

    /**
     * Get order status data using optimized SQL counts with caching
     * This replaces the old approach that loaded all records into PHP
     */
    public function getOrderStatusData(): array
    {
        // Build date filter based on session statistics_type
        $dateFilter = $this->getDateFilterForStatistics();
        $cacheKey = 'dashboard_order_status_' . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dateFilter) {
            // Get all order status counts in a single optimized query
            $orderStats = $this->orderStatsService->getDashboardOrderStats($dateFilter);

            // Get entity counts with date filter using SQL COUNT
            $storeCount = $this->vendorRepo->getCountWhere($dateFilter);
            $productCount = $this->productRepo->getCountWhere($dateFilter);
            $customerCount = $this->customerRepo->getCountWhere(array_merge(['avoid_walking_customer' => 1], $dateFilter));

            return [
                'order' => $orderStats['order'],
                'store' => $storeCount,
                'failed' => $orderStats['failed'],
                'pending' => $orderStats['pending'],
                'product' => $productCount,
                'customer' => $customerCount,
                'returned' => $orderStats['returned'],
                'canceled' => $orderStats['canceled'],
                'confirmed' => $orderStats['confirmed'],
                'delivered' => $orderStats['delivered'],
                'processing' => $orderStats['processing'],
                'out_for_delivery' => $orderStats['out_for_delivery'],
            ];
        });
    }

    /**
     * Get date filter array based on session statistics_type
     */
    private function getDateFilterForStatistics(): array
    {
        $statisticsType = session('statistics_type');

        if ($statisticsType === 'today') {
            return [
                'created_at_from' => now()->startOfDay(),
                'created_at_to' => now()->endOfDay(),
            ];
        }

        if ($statisticsType === 'this_month') {
            return [
                'created_at_from' => now()->startOfMonth(),
                'created_at_to' => now()->endOfMonth(),
            ];
        }

        return []; // No date filter (all time)
    }

    public function getOrderStatistics(Request $request): JsonResponse
    {
        $dateType = $request['type'];
        $dateTypeArray = $this->dashboardService->getDateTypeData(dateType: $dateType);
        $from = $dateTypeArray['from'];
        $to = $dateTypeArray['to'];
        $type = $dateTypeArray['type'];
        $range = $dateTypeArray['range'];
        $inHouseOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: $type, userType: 'admin');
        $vendorOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: $type, userType: 'seller');
        $label = $dateTypeArray['keyRange'] ?? [];
        $inHouseOrderEarningArray = array_values($inHouseOrderEarningArray);
        $vendorOrderEarningArray = array_values($vendorOrderEarningArray);
        return response()->json([
            'view' => view('admin-views.system.partials.order-statistics', compact('inHouseOrderEarningArray', 'vendorOrderEarningArray', 'label', 'dateType'))->render(),
        ]);
    }

    public function getEarningStatistics(Request $request): JsonResponse
    {
        $dateType = $request['type'];
        $dateTypeArray = $this->dashboardService->getDateTypeData(dateType: $dateType);
        $from = $dateTypeArray['from'];
        $to = $dateTypeArray['to'];
        $type = $dateTypeArray['type'];
        $range = $dateTypeArray['range'];
        $inHouseEarning = $this->getEarning(from: $from, to: $to, range: $range, type: $type, userType: 'admin');
        $vendorEarning = $this->getEarning(from: $from, to: $to, range: $range, type: $type, userType: 'seller');
        $commissionEarn = $this->getAdminCommission(from: $from, to: $to, range: $range, type: $type);
        $label = $dateTypeArray['keyRange'] ?? [];
        $inHouseEarning = array_values($inHouseEarning);
        $vendorEarning = array_values($vendorEarning);
        $commissionEarn = array_values($commissionEarn);
        return response()->json([
            'view' => view('admin-views.system.partials.earning-statistics', compact('inHouseEarning', 'vendorEarning', 'commissionEarn', 'label', 'dateType'))->render(),
        ]);
    }

    protected function getOrderStatisticsData($from, $to, $range, $type, $userType): array
    {
        $orderEarnings = $this->orderRepo->getListWhereBetween(
            filters: [
                'seller_is' => $userType,
                'payment_status' => 'paid'
            ],
            selectColumn: 'order_amount',
            whereBetween: 'created_at',
            whereBetweenFilters: [$from, $to],
        );
        $orderEarningArray = [];
        foreach ($range as $value) {
            $matchingEarnings = $orderEarnings->where($type, $value);
            if ($matchingEarnings->count() > 0) {
                $orderEarningArray[$value] = usdToDefaultCurrency($matchingEarnings->sum('sums'));
            } else {
                $orderEarningArray[$value] = 0;
            }
        }
        return $orderEarningArray;
    }

    protected function getEarning(string|Carbon $from, string|Carbon $to, array $range, string $type, $userType): array
    {
        $earning = $this->orderTransactionRepo->getListWhereBetween(
            filters: [
                'seller_is' => $userType,
                'status' => 'disburse',
            ],
            selectColumn: 'seller_amount',
            whereBetween: 'created_at',
            groupBy: $type,
            whereBetweenFilters: [$from, $to],
        );
        return $this->dashboardService->getDateWiseAmount(range: $range, type: $type, amountArray: $earning);
    }

    /**
     * @param string|Carbon $from
     * @param string|Carbon $to
     * @param array $range
     * @param string $type
     * @return array
     */
    protected function getAdminCommission(string|Carbon $from, string|Carbon $to, array $range, string $type): array
    {
        $commissionGiven = $this->orderTransactionRepo->getListWhereBetween(
            filters: [
                'seller_is' => 'seller',
                'status' => 'disburse',
            ],
            selectColumn: 'admin_commission',
            whereBetween: 'created_at',
            groupBy: $type,
            whereBetweenFilters: [$from, $to],
        );
        return $this->dashboardService->getDateWiseAmount(range: $range, type: $type, amountArray: $commissionGiven);
    }

    public function getRealTimeActivities(): JsonResponse
    {
        // Use SQL COUNT for new orders instead of loading all records
        $newOrder = $this->orderRepo->getCountWhere(filters: ['checked' => 0]);
        
        // Cache restock product data briefly since it's polled frequently
        $restockData = Cache::remember('dashboard_restock_data', 30, function () {
            $restockProductList = $this->restockProductRepo->getListWhere(filters: ['added_by' => 'in_house'], dataLimit: 'all')->groupBy('product_id');
            $restockProduct = [];
            
            if (count($restockProductList) == 1) {
                $products = $this->restockProductRepo->getListWhere(orderBy: ['updated_at' => 'desc'], filters: ['added_by' => 'in_house'], relations: ['product'], dataLimit: 1);
                $firstProduct = $products->first();
                $count = $this->restockProductRepo->getListWhere(filters: ['added_by' => 'in_house'], dataLimit: 'all')->sum('restock_product_customers_count') ?? 0;
                $restockProduct = [
                    'title' => $firstProduct?->product?->name ?? '',
                    'body' => $count < 100 ? translate('This_product_has') . ' ' . $count . ' ' . translate('restock_request') : translate('This_product_has') . ' 99+ ' . translate('restock_request'),
                    'image' => getStorageImages(path: $firstProduct?->product?->thumbnail_full_url ?? '', type: 'product'),
                    'route' => route('admin.products.request-restock-list')
                ];
            } elseif (count($restockProductList) > 1) {
                $restockProduct = [
                    'title' => translate('Restock_Request'),
                    'body' => count($restockProductList) < 100 ? (count($restockProductList) . ' ' . translate('products_have_restock_request')) : ('99 +' . ' ' . translate('more_products_have_restock_request')),
                    'image' => dynamicAsset(path: 'public/assets/back-end/img/icons/restock-request-icon.svg'),
                    'route' => route('admin.products.request-restock-list')
                ];
            }

            return [
                'restockProductCount' => $restockProductList->count(),
                'restockProduct' => $restockProduct,
            ];
        });

        return response()->json([
            'success' => 1,
            'new_order_count' => $newOrder,
            'restockProductCount' => $restockData['restockProductCount'],
            'restockProduct' => $restockData['restockProduct']
        ]);
    }
}

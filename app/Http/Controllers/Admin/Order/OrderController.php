<?php

namespace App\Http\Controllers\Admin\Order;

use Carbon\Carbon;
use App\Models\Governorate;
use App\Models\ShippingAddress;
use App\Enums\WebConfigKey;
use App\Utils\OrderManager;
use App\Exports\OrderExport;
use App\Traits\PdfGenerator;
use Illuminate\Http\Request;
use App\Enums\GlobalConstant;
use App\Traits\CustomerTrait;
use App\Services\OrderService;
use App\Events\OrderStatusEvent;
use App\Models\ReferralCustomer;
use App\Traits\FileManagerTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use App\Enums\ViewPaths\Admin\Order;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\BaseController;
use App\Services\DeliveryManWalletService;
use App\Repositories\DeliveryManRepository;
use App\Services\OrderStatusHistoryService;
use App\Services\DeliveryCountryCodeService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Database\Eloquent\Collection;
use App\Services\DeliveryManTransactionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\View as PdfView;
use App\Repositories\OrderTransactionRepository;
use App\Repositories\WalletTransactionRepository;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Http\Requests\UploadDigitalFileAfterSellRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Contracts\Repositories\DeliveryZipCodeRepositoryInterface;
use App\Contracts\Repositories\ShippingAddressRepositoryInterface;
use App\Contracts\Repositories\DeliveryManWalletRepositoryInterface;
use App\Contracts\Repositories\OrderStatusHistoryRepositoryInterface;
use App\Contracts\Repositories\DeliveryCountryCodeRepositoryInterface;
use App\Contracts\Repositories\DeliveryManTransactionRepositoryInterface;
use App\Contracts\Repositories\LoyaltyPointTransactionRepositoryInterface;
use App\Contracts\Repositories\OrderExpectedDeliveryHistoryRepositoryInterface;
use App\Services\OrderStatsService;
use App\Models\RefundRequest;
use App\Events\RefundEvent;
use App\Models\OrderRefund;

class OrderController extends BaseController
{
    use CustomerTrait;
    use PdfGenerator;
    use FileManagerTrait {
        delete as deleteFile;
        update as updateFile;
    }

    public function __construct(
        private readonly OrderRepositoryInterface                        $orderRepo,
        private readonly CustomerRepositoryInterface                     $customerRepo,
        private readonly VendorRepositoryInterface                       $vendorRepo,
        private readonly BusinessSettingRepositoryInterface              $businessSettingRepo,
        private readonly DeliveryCountryCodeRepositoryInterface          $deliveryCountryCodeRepo,
        private readonly DeliveryZipCodeRepositoryInterface              $deliveryZipCodeRepo,
        private readonly DeliveryManRepository                           $deliveryManRepo,
        private readonly ShippingAddressRepositoryInterface              $shippingAddressRepo,
        private readonly OrderExpectedDeliveryHistoryRepositoryInterface $orderExpectedDeliveryHistoryRepo,
        private readonly OrderDetailRepositoryInterface                  $orderDetailRepo,
        private readonly WalletTransactionRepository                     $walletTransactionRepo,
        private readonly DeliveryManWalletRepositoryInterface            $deliveryManWalletRepo,
        private readonly DeliveryManTransactionRepositoryInterface       $deliveryManTransactionRepo,
        private readonly OrderStatusHistoryRepositoryInterface           $orderStatusHistoryRepo,
        private readonly OrderTransactionRepository                      $orderTransactionRepo,
        private readonly LoyaltyPointTransactionRepositoryInterface      $loyaltyPointTransactionRepo,
        private readonly OrderStatsService                               $orderStatsService,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|JsonResponse|null Index function is the starting point of a controller
     * Index function is the starting point of a controller
     */
    public function index(Request|null $request, $type = 'all'): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $status = $type;
        $searchValue = $request['searchValue'];

        $filter = $request['filter'];
        $from = $request['from'];
        $to = $request['to'];

        // Only run UPDATE if there are unchecked orders (avoids slow query when nothing to update)
        $uncheckedCount = $this->orderRepo->getCountWhere(filters: ['checked' => 0]);
        if ($uncheckedCount > 0) {
            $this->orderRepo->updateWhere(params: ['checked' => 0], data: ['checked' => 1]);
        }

        $vendorId = $request['seller_id'] == '0' ? 1 : $request['seller_id'];
        if ($request['seller_id'] == null) {
            $vendorIs = 'all';
        } elseif ($request['seller_id'] == 'all') {
            $vendorIs = $request['seller_id'];
        } elseif ($request['seller_id'] == '0') {
            $vendorIs = 'admin';
        } else {
            $vendorIs = 'seller';
        }
        $dateType = $request['date_type'];
        $allowedPerPage = [10, 25, 50, 100];
        $defaultLimit = (int) getWebConfig(name: WebConfigKey::PAGINATION_LIMIT) ?: 10;
        $perPage = (int) ($request->get('per_page') ?? $defaultLimit);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = in_array($defaultLimit, $allowedPerPage, true) ? $defaultLimit : 10;
        }

        $orderSource = $request->get('order_source', 'all');

        $filters = [
            'order_status' => $status,
            'filter' => $request['filter'] ?? 'all',
            'date_type' => $dateType,
            'date_field' => $request['date_field'] ?? 'created_at',
            'from' => $request['from'],
            'to' => $request['to'],
            'delivery_man_id' => $request['delivery_man_id'],
            'customer_id' => $request['customer_id'],
            'city_id' => $request['city_id'],
            'seller_id' => $vendorId,
            'seller_is' => $vendorIs,
            'is_printed' => $request['is_printed'] ?? 'all',
            'per_page' => $perPage,
        ];
        if ($orderSource !== 'all' && in_array($orderSource, ['EasyOrders', 'POS'], true)) {
            $filters['order_type'] = $orderSource;
        }

        $orders = $this->orderRepo->getListWhere(orderBy: ['id' => 'desc'], searchValue: $request['searchValue'], filters: $filters, relations: ['customer', 'seller.shop'], dataLimit: $perPage);
        $sellers = $this->vendorRepo->getByStatusExcept(status: 'pending', relations: ['shop']);

        $customer = "all";
        if (isset($request['customer_id']) && $request['customer_id'] != 'all' && !is_null($request->customer_id) && $request->has('customer_id')) {
            $customer = $this->customerRepo->getFirstWhere(params: ['id' => $request['customer_id']]);
        }

        $vendorId = $request['seller_id'];
        $customerId = $request['customer_id'];

        // Get selected seller for display
        $selectedSeller = null;
        if ($vendorId && $vendorId != 'all' && $vendorId != '0') {
            $selectedSeller = $this->vendorRepo->getFirstWhere(params: ['id' => $vendorId], relations: ['shop']);
        }

        if (request()->ajax()) {
            return response()->json([
                'orders' => $orders
            ]);
        }

        // Stats section
        $countBaseFilters = [
            'seller_id' => $vendorId,
            'seller_is' => $vendorIs,
        ];
        if ($orderSource !== 'all' && in_array($orderSource, ['EasyOrders', 'POS'], true)) {
            $countBaseFilters['order_type'] = $orderSource;
        }
        $startOfMonth = Carbon::now()->startOfMonth()->startOfDay();
        $endOfMonth = Carbon::now()->endOfMonth()->endOfDay();
        $startOfDay = Carbon::now()->startOfDay();
        $endOfDay = Carbon::now()->endOfDay();

        // Get checkbox selections for custom total calculation (default: all selected)
        $includeProducts = $request->get('include_products', '1') === '1';
        $includeShipping = $request->get('include_shipping', '1') === '1';
        $includeDiscounts = $request->get('include_discounts', '1') === '1';
        $includeDelivery = $request->get('include_delivery', '1') === '1';

        // Calculate order components using optimized SQL aggregations with caching
        $orderComponents = $this->orderStatsService->getOrderComponentTotals(
            $filters,
            $includeProducts,
            $includeShipping,
            $includeDiscounts,
            $includeDelivery
        );
        
        // Product sales total is included in orderComponents to avoid duplicate query
        $productSalesTotal = $orderComponents['product_sales_total'] ?? $orderComponents['products'];

        // Use single optimized query with caching instead of 5 separate count queries
        $orderStats = $this->orderRepo->getOrderStatsOptimized(
            $countBaseFilters,
            $startOfMonth,
            $endOfMonth,
            $startOfDay,
            $endOfDay
        );
        $stats = [
            'total' => $orderStats['total'],
            'this_month' => $orderStats['this_month'],
            'today' => $orderStats['today'],
            'printed' => $orderStats['printed'],
            'unprinted' => $orderStats['unprinted'],
            'custom_total' => $orderComponents['custom_total'],
            'product_sales_total' => $productSalesTotal,
            'components' => $orderComponents,
        ];

        $governorates = Governorate::orderBy('name_ar')->get(['id','name_ar']);

        return view('admin-views.order.list', compact(
            'orders',
            'searchValue',
            'from',
            'to',
            'status',
            'filter',
            'orderSource',
            'sellers',
            'customer',
            'vendorId',
            'customerId',
            'dateType',
            'stats',
            'governorates',
            'selectedSeller',
        ))->with([
            'dateField' => $request['date_field'] ?? 'created_at',
            'includeProducts' => $includeProducts,
            'includeShipping' => $includeShipping,
            'includeDiscounts' => $includeDiscounts,
            'includeDelivery' => $includeDelivery,
        ]);
    }

    public function edit(int|string $id): RedirectResponse
    {
        if (!\App\Utils\Helpers::module_permission_check('order_edit')) {
            \Devrabiul\ToastMagic\Facades\ToastMagic::error(translate('access_denied'));
            return back();
        }
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id]);
        if (!$order) {
            \Devrabiul\ToastMagic\Facades\ToastMagic::error(translate('order_not_found'));
            return back();
        }
        return redirect()->route('admin.pos.index', ['edit_order_id' => $id]);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        if (!\App\Utils\Helpers::module_permission_check('order_edit')) {
            return response()->json(['error' => translate('access_denied')], 403);
        }
        // Delegate to action class for clarity and isolation
        return app(\App\Http\Controllers\Admin\Order\OrderUpdateAction::class)($request, $id);
    }

    public function createRefundRequest(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $orderId], relations: ['details']);
        if (!$order) {
            return response()->json(['error' => translate('Order_not_found')], 404);
        }

        // Check if a refund request already exists for this order
        $existingRefund = OrderRefund::where('order_id', $orderId)->first();
        if ($existingRefund) {
            return response()->json(['error' => translate('already_applied_for_refund_request!!')], 422);
        }

        // Calculate total refundable amount for the order
        $totalRefundableAmount = 0;
        foreach ($order->details as $orderDetail) {
            $totalRefundableAmount += OrderManager::getRefundDetailsForSingleOrderDetails(orderDetailsId: $orderDetail['id'])['total_refundable_amount'];
        }

        // Create a single refund request for the entire order
        $orderRefund = new OrderRefund();
        $orderRefund->order_id = $order->id;
        $orderRefund->customer_id = $order->customer_id ?? 0;
        $orderRefund->status = 'pending';
        $orderRefund->amount = $totalRefundableAmount;
        $orderRefund->admin_note = $request->input('reason', 'Created by admin');
        $orderRefund->save();

        // Optionally, update the order details to indicate a refund has been requested
        foreach ($order->details as $orderDetail) {
            $this->orderDetailRepo->update(id: $orderDetail['id'], data: ['refund_request' => 1]);
        }

        // Optionally, dispatch an event
        // event(new RefundEvent(status: 'refund_request', order: $order, refund: $orderRefund));

        return response()->json(['message' => translate('refund_requested_successful!!')]);
    }

    public function approveRefund(Request $request, int $refundId): JsonResponse
    {
        $orderRefund = OrderRefund::find($refundId);
        if (!$orderRefund) {
            return response()->json(['error' => translate('Refund_request_not_found')], 404);
        }

        $orderRefund->status = 'approved';
        $orderRefund->save();

        return response()->json(['message' => translate('Refund_request_approved_successfully')]);
    }

    public function rejectRefund(Request $request, int $refundId): JsonResponse
    {
        $orderRefund = OrderRefund::find($refundId);
        if (!$orderRefund) {
            return response()->json(['error' => translate('Refund_request_not_found')], 404);
        }

        $orderRefund->status = 'rejected';
        $orderRefund->admin_note = $request->input('reason', $orderRefund->admin_note);
        $orderRefund->save();

        return response()->json(['message' => translate('Refund_request_rejected_successfully')]);
    }

    public function refundOrder(Request $request, int $refundId): JsonResponse
    {
        $orderRefund = OrderRefund::find($refundId);
        if (!$orderRefund) {
            return response()->json(['error' => translate('Refund_request_not_found')], 404);
        }

        if ($orderRefund->status != 'approved') {
            return response()->json(['error' => translate('Refund_request_must_be_approved_first')], 422);
        }

        $orderRefund->status = 'refunded';
        $orderRefund->save();

        return response()->json(['message' => translate('Order_refunded_successfully')]);
    }


    public function exportList(Request $request, $status): BinaryFileResponse|RedirectResponse
    {
        $vendorId = $request['seller_id'] == '0' ? 1 : $request['seller_id'];
        if ($request['seller_id'] == null) {
            $vendorIs = 'all';
        } elseif ($request['seller_id'] == 'all') {
            $vendorIs = $request['seller_id'];
        } elseif ($request['seller_id'] == '0') {
            $vendorIs = 'admin';
        } else {
            $vendorIs = 'seller';
        }

        $filters = [
            'order_status' => $status,
            'filter' => $request['filter'] ?? 'all',
            'date_type' => $request['date_type'],
            'date_field' => $request['date_field'] ?? 'created_at',
            'from' => $request['from'],
            'to' => $request['to'],
            'delivery_man_id' => $request['delivery_man_id'],
            'customer_id' => $request['customer_id'],
            'city_id' => $request['city_id'],
            'seller_id' => $vendorId,
            'seller_is' => $vendorIs,
            'is_printed' => $request['is_printed'] ?? 'all',
        ];
        $orderSourceExport = $request->get('order_source', 'all');
        if ($orderSourceExport !== 'all' && in_array($orderSourceExport, ['EasyOrders', 'POS'], true)) {
            $filters['order_type'] = $orderSourceExport;
        }

        $orders = $this->orderRepo->getListWhere(orderBy: ['id' => 'desc'], searchValue: $request['searchValue'], filters: $filters, relations: ['customer', 'seller.shop'], dataLimit: 'all');

        /** order status count  */
        $status_array = [
            'pending' => 0,
            'confirmed' => 0,
            'processing' => 0,
            'out_for_delivery' => 0,
            'delivered' => 0,
            'returned' => 0,
            'failed' => 0,
            'canceled' => 0,
        ];
        $orders?->map(function ($order) use (&$status_array) { // Pass by reference using &
            if (isset($status_array[$order->order_status])) {
                $status_array[$order->order_status]++;
            }
            $order?->orderDetails?->map(function ($details) use ($order) {
                $order['total_qty'] += $details->qty;
                $order['total_price'] += $details->qty * $details->price + ($details->tax_model == 'include' ? $details->qty * $details->tax : 0);
                $order['total_discount'] += $details->discount;
                $order['total_tax'] += $details->tax_model == 'exclude' ? $details->tax : 0;
            });

        });
        /** order status count  */

        /** date */
        $date_type = $request->date_type ?? '';
        $from = match ($date_type) {
            'this_year' => date('Y-01-01'),
            'this_month' => date('Y-m-01'),
            'this_week' => Carbon::now()->subDays(7)->startOfWeek()->format('Y-m-d'),
            default => $request['from'] ?? '',
        };
        $to = match ($date_type) {
            'this_year' => date('Y-12-31'),
            'this_month' => date('Y-m-t'),
            'this_week' => Carbon::now()->startOfWeek()->format('Y-m-d'),
            default => $request['to'] ?? '',
        };
        /** end  */
        $seller = [];
        if ($request['seller_id'] != 'all' && $request->has('seller_id') && $request->seller_id != 0) {
            $seller = $this->vendorRepo->getFirstWhere(['id' => $request['seller_id']]);
        }
        $customer = [];
        if ($request['customer_id'] != 'all' && $request->has('customer_id')) {
            $customer = $this->customerRepo->getFirstWhere(['id' => $request['customer_id']]);
        }

        $data = [
            'data-from' => 'admin',
            'orders' => $orders,
            'order_status' => $status,
            'seller' => $seller,
            'customer' => $customer,
            'status_array' => $status_array,
            'searchValue' => $request['searchValue'],
            'order_type' => $request['filter'] ?? 'all',
            'from' => $from,
            'to' => $to,
            'date_type' => $date_type,
            'defaultCurrencyCode' => getCurrencyCode(),
        ];
        return Excel::download(new OrderExport($data), 'Orders.xlsx');
    }

    public function getView(string|int $id, DeliveryCountryCodeService $service, OrderService $orderService): View|RedirectResponse
    {

        $countryRestrictStatus = getWebConfig(name: 'delivery_country_restriction');
        $zipRestrictStatus = getWebConfig(name: 'delivery_zip_code_area_restriction');
        $deliveryCountry = $this->deliveryCountryCodeRepo->getList(dataLimit: 'all');
        $countries = $countryRestrictStatus ? $service->getDeliveryCountryArray(deliveryCountryCodes: $deliveryCountry) : GlobalConstant::COUNTRIES;
        $zipCodes = $zipRestrictStatus ? $this->deliveryZipCodeRepo->getList(dataLimit: 'all') : 0;
        $companyName = getWebConfig(name: 'company_name');
        $companyWebLogo = getWebConfig(name: 'company_web_logo');
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['details.productAllStatus', 'verificationImages', 'shipping', 'seller.shop', 'offlinePayments', 'deliveryMan']);

        if ($order) {
            $physicalProduct = false;
            if (isset($order->details)) {
                foreach ($order->details as $orderDetail) {
                    $orderDetailProduct = json_decode($orderDetail?->product_details, true);
                    if (isset($orderDetail?->product?->product_type) && $orderDetail?->product?->product_type == 'physical') {
                        $physicalProduct = true;
                    } else if ($orderDetailProduct && isset($orderDetailProduct['product_type']) && $orderDetailProduct['product_type'] == 'physical') {
                        $physicalProduct = true;
                    }
                }
            }

            $whereNotIn = [
                'order_group_id' => ['def-order-group'],
                'id' => [$order['id']],
            ];
            $linkedOrders = $this->orderRepo->getListWhereNotIn(filters: ['order_group_id' => $order['order_group_id']], whereNotIn: $whereNotIn, dataLimit: 'all');
            $totalDelivered = $this->orderRepo->getListWhere(filters: ['seller_id' => $order['seller_id'], 'order_status' => 'delivered', 'order_type' => 'default_type'], dataLimit: 'all')->count();
            $shippingMethod = getWebConfig('shipping_method');

            $sellerId = 0;
            if ($order['seller_is'] == 'seller' && $shippingMethod == 'sellerwise_shipping') {
                $sellerId = $order['seller_id'];
            }
            $filters = [
                'is_active' => 1,
                'seller_id' => $sellerId,
            ];
            $deliveryMen = $this->deliveryManRepo->getListWhere(filters: $filters, dataLimit: 'all');
            $isOrderOnlyDigital = $orderService->getCheckIsOrderOnlyDigital(order: $order);
            if ($order['order_type'] == 'default_type') {
                $orderCount = $this->orderRepo->getListWhereCount(filters: ['customer_id' => $order['customer_id']]);
                return view(Order::VIEW[VIEW], compact('order', 'linkedOrders',
                    'deliveryMen', 'totalDelivered', 'companyName', 'companyWebLogo', 'physicalProduct',
                    'countryRestrictStatus', 'zipRestrictStatus', 'countries', 'zipCodes', 'orderCount', 'isOrderOnlyDigital'));
            } else {
                $orderCount = $this->orderRepo->getListWhereCount(filters: ['customer_id' => $order['customer_id'], 'order_type' => 'POS']);
                return view(Order::VIEW_POS[VIEW], compact('order', 'companyName', 'companyWebLogo', 'orderCount'));
            }
        } else {
            ToastMagic::error(translate('Order_not_found'));
            return back();
        }
    }

    public function generateInvoice(string|int $id)
    {
        $companyPhone = getWebConfig(name: 'company_phone');
        $companyEmail = getWebConfig(name: 'company_email');
        $companyName = getWebConfig(name: 'company_name');
        $companyWebLogo = getWebConfig(name: 'company_web_logo');
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['seller', 'shipping', 'details', 'customer']);
        // Use order's shipping_address_data directly (this is what gets updated from quick edit)
        $shippingAddress = $order['shipping_address_data'] ?? null;

        // Resolve governorate name by city_id stored on order
        $governorateName = null;
        if (!empty($order['city_id'])) {
            $governorateName = Governorate::find($order['city_id'])?->name_ar;
        }
        $vendor = $this->vendorRepo->getFirstWhere(params: ['id' => $order['details']->first()->seller_id]);
        $invoiceSettings = getWebConfig(name: 'invoice_settings');
        $mpdfView = PdfView::make('admin-views.order.invoice',
            compact('order', 'vendor', 'companyPhone', 'companyEmail', 'companyName', 'companyWebLogo', 'invoiceSettings', 'shippingAddress', 'governorateName')
        );
        $this->generatePdf(view: $mpdfView, filePrefix: 'order_invoice_', filePostfix: $order['id'], pdfType: 'invoice');
        // mark as printed and move status to out_for_delivery when printing
        $this->orderRepo->update(id: $order['id'], data: ['is_printed' => 1, 'order_status' => 'out_for_delivery']);
    }

    public function updateStatus(
        Request                       $request,
        DeliveryManTransactionService $deliveryManTransactionService,
        DeliveryManWalletService      $deliveryManWalletService,
        OrderStatusHistoryService     $orderStatusHistoryService,
    ): JsonResponse
    {
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $request['id']], relations: ['customer', 'seller.shop', 'deliveryMan']);

        if (!$order['is_guest'] && !isset($order['customer'])) {
            return response()->json(['customer_status' => 0], 200);
        }
        $this->orderRepo->updateStockOnOrderStatusChange($request['id'], $request['order_status']);
        $this->orderRepo->update(id: $request['id'], data: ['order_status' => $request['order_status']]);
        if ($request['order_status'] == 'delivered') {
            $this->orderRepo->update(id: $request['id'], data: ['payment_status' => 'paid', 'is_pause' => 0]);
            $this->orderDetailRepo->updateWhere(params: ['order_id' => $order['id']], data: ['delivery_status' => $request['order_status'], 'payment_status' => 'paid']);
        }
        event(new OrderStatusEvent(key: $request['order_status'], type: 'customer', order: $order));
        if ($request['order_status'] == 'canceled') {
            event(new OrderStatusEvent(key: 'canceled', type: 'delivery_man', order: $order));
        }
        if ($order['seller_is'] == 'seller') {
            if ($request['order_status'] == 'canceled') {
                event(new OrderStatusEvent(key: 'canceled', type: 'seller', order: $order));
            } elseif ($request['order_status'] == 'delivered') {
                event(new OrderStatusEvent(key: 'delivered', type: 'seller', order: $order));
            }
        }

        $loyaltyPointStatus = getWebConfig(name: 'loyalty_point_status');
        $loyaltyPointEachOrder = getWebConfig(name: 'loyalty_point_for_each_order');
        $loyaltyPointEachOrder = !is_null($loyaltyPointEachOrder) ? $loyaltyPointEachOrder : $loyaltyPointStatus;

        if ($loyaltyPointStatus == 1 && $loyaltyPointEachOrder == 1 && !$order['is_guest'] && $request['order_status'] == 'delivered') {
            $this->loyaltyPointTransactionRepo->addLoyaltyPointTransaction(userId: $order['customer_id'], reference: $order['id'], amount: usdToDefaultCurrency(amount: $order['order_amount'] - $order['shipping_cost']), transactionType: 'order_place');
        }

        OrderManager::generateReferBonusForFirstOrder(orderId: $order['id']);

        if ($order['delivery_man_id'] && $request->order_status == 'delivered') {
            $deliverymanWallet = $this->deliveryManWalletRepo->getFirstWhere(params: ['delivery_man_id' => $order['delivery_man_id']]);
            $cashInHand = $order['payment_method'] == 'cash_on_delivery' ? $order['order_amount'] : 0;

            if (empty($deliverymanWallet)) {
                $deliverymanWalletData = $deliveryManWalletService->getDeliveryManData(id: $order['delivery_man_id'], deliverymanCharge: $order['deliveryman_charge'], cashInHand: $cashInHand);
                $this->deliveryManWalletRepo->add(data: $deliverymanWalletData);
            } else {
                $deliverymanWalletData = [
                    'current_balance' => $deliverymanWallet['current_balance'] + $order['deliveryman_charge'] ?? 0,
                    'cash_in_hand' => $deliverymanWallet['cash_in_hand'] + $cashInHand ?? 0,
                ];
                $this->deliveryManWalletRepo->updateWhere(params: ['delivery_man_id' => $order['delivery_man_id']], data: $deliverymanWalletData);
            }
            if ($order['deliveryman_charge'] && $request['order_status'] == 'delivered') {
                $deliveryManTransactionData = $deliveryManTransactionService->getDeliveryManTransactionData(amount: $order['deliveryman_charge'], addedBy: 'admin', id: $order['delivery_man_id'], transactionType: 'deliveryman_charge');
                $this->deliveryManTransactionRepo->add($deliveryManTransactionData);
            }
        }

        $orderStatusHistoryData = $orderStatusHistoryService->getOrderHistoryData(orderId: $request['id'], userId: 0, userType: 'admin', status: $request['order_status']);
        $this->orderStatusHistoryRepo->add($orderStatusHistoryData);

        $transaction = $this->orderTransactionRepo->getFirstWhere(params: ['order_id' => $order['id']]);
        if (isset($transaction) && $transaction['status'] == 'disburse') {
            return response()->json($request['order_status']);
        }
        if ($request['order_status'] == 'delivered' && $order['seller_id'] != null) {
            $this->orderRepo->manageWalletOnOrderStatusChange(order: $order, receivedBy: 'admin');
        }
        if ($request['order_status'] == 'delivered') {
            $referredUser = ReferralCustomer::where('user_id', $order?->customer?->id)->first();
            if ($referredUser?->delivered_notify != 1) {
                event(new OrderStatusEvent(key: 'your_referred_customer_order_has_been_delivered', type: 'promoter', order: $order));
                ReferralCustomer::where('user_id', $order?->customer?->id)->update(['delivered_notify' => 1]);
            }
        }
        return response()->json($request['order_status']);
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $request['order_id']], relations: ['seller.shop', 'deliveryMan']);
        $shippingAddressData = json_decode(json_encode($order['shipping_address_data']), true);
        $billingAddressData = json_decode(json_encode($order['billing_address_data']), true);
        $commonAddressData = [
            'contact_person_name' => $request['name'],
            'phone' => $request['phone_number'],
            'country' => $request['country'],
            'city' => $request['city'],
            'zip' => $request['zip'],
            'address' => $request['address'],
            'latitude' => $request['latitude'],
            'longitude' => $request['longitude'],
            'updated_at' => now(),
        ];

        if ($request['address_type'] == 'shipping') {
            $shippingAddressData = array_merge($shippingAddressData, $commonAddressData);
        } elseif ($request['address_type'] == 'billing') {
            $billingAddressData = array_merge($billingAddressData, $commonAddressData);
        }

        $updateData = [];
        if ($request['address_type'] == 'shipping') {
            $updateData['shipping_address_data'] = json_encode($shippingAddressData);
        } elseif ($request['address_type'] == 'billing') {
            $updateData['billing_address_data'] = json_encode($billingAddressData);
        }

        if (!empty($updateData)) {
            $this->orderRepo->update(id: $request['order_id'], data: $updateData);
        }

        if ($order->seller_is == 'seller') {
            OrderStatusEvent::dispatch('order_edit_message', 'seller', $order);
        }

        if ($order->delivery_type == 'self_delivery' && $order->delivery_man_id) {
            OrderStatusEvent::dispatch('order_edit_message', 'delivery_man', $order);
        }

        ToastMagic::success(translate('successfully_updated'));
        return back();
    }

    public function updateDeliverInfo(Request $request): RedirectResponse
    {
        $updateData = [
            'delivery_type' => 'third_party_delivery',
            'delivery_service_name' => $request['delivery_service_name'],
            'third_party_delivery_tracking_id' => $request['third_party_delivery_tracking_id'],
            'delivery_man_id' => null,
            'deliveryman_charge' => 0,
            'expected_delivery_date' => null,
        ];
        $this->orderRepo->update(id: $request['order_id'], data: $updateData);

        ToastMagic::success(translate('updated_successfully'));
        return back();
    }

    public function addDeliveryMan(string|int $order_id, string|int $delivery_man_id): JsonResponse
    {
        if ($delivery_man_id == 0) {
            return response()->json([], 401);
        }

        $orderData = $this->orderRepo->getFirstWhere(params: ['id' => $order_id]);
        $order = [
            'seller_is' => $orderData->seller_is,
            'delivery_man_id' => $delivery_man_id,
            'delivery_type' => 'self_delivery',
            'delivery_service_name' => null,
            'third_party_delivery_tracking_id' => null,
        ];
        $this->orderRepo->update(id: $order_id, data: $order);

        $order = $this->orderRepo->getFirstWhere(params: ['id' => $order_id], relations: ['seller.shop', 'deliveryMan']);

        event(new OrderStatusEvent(key: 'new_order_assigned_message', type: 'delivery_man', order: $order));

        /** For Seller Product Send Notification */
        if ($order['seller_is'] == 'seller') {
            event(new OrderStatusEvent(key: 'delivery_man_assign_by_admin_message', type: 'seller', order: $order));
        }
        /** end */

        return response()->json(['status' => true], 200);
    }

    public function updateAmountDate(Request $request): JsonResponse
    {
        $userId = 0;
        $status = $this->orderRepo->updateAmountDate(request: $request, userId: $userId, userType: 'admin');
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $request['order_id']], relations: ['customer', 'deliveryMan']);

        $fieldName = $request['field_name'];
        $message = '';
        if ($fieldName == 'expected_delivery_date') {
            OrderStatusEvent::dispatch('expected_delivery_date', 'delivery_man', $order);
            $message = translate("expected_delivery_date_added_successfully");

        } elseif ($fieldName == 'deliveryman_charge') {
            OrderStatusEvent::dispatch('delivery_man_charge', 'delivery_man', $order);
            $message = translate("deliveryman_charge_added_successfully");
        }

        return response()->json(['status' => $status, 'message' => $message], $status ? 200 : 403);
    }

    public function getCustomers(Request $request): JsonResponse
    {
        $allCustomer = ['id' => 'all', 'text' => 'All customer'];
        $customers = $this->customerRepo->getCustomerNameList(request: $request)->toArray();
        array_unshift($customers, $allCustomer);

        return response()->json($customers);
    }

    public function updatePaymentStatus(Request $request): JsonResponse
    {
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $request['id']]);

        if ($order['is_guest'] == '0' && !isset($order['customer'])) {
            return response()->json(['customer_status' => 0], 200);
        }
        $this->orderRepo->update(id: $request['id'], data: ['payment_status' => $request['payment_status']]);
        return response()->json($request['payment_status']);
    }

    public function filterInHouseOrder(): RedirectResponse
    {
        if (session()->has('show_inhouse_orders') && session('show_inhouse_orders') == 1) {
            session()->put('show_inhouse_orders', 0);
        } else {
            session()->put('show_inhouse_orders', 1);
        }
        return back();
    }

    public function uploadDigitalFileAfterSell(UploadDigitalFileAfterSellRequest $request): RedirectResponse
    {
        $orderDetails = $this->orderDetailRepo->getFirstWhere(['id' => $request['order_id']]);
        $digitalFileAfterSell = $this->updateFile(dir: 'product/digital-product/', oldImage: $orderDetails['digital_file_after_sell'], format: $request['digital_file_after_sell']->getClientOriginalExtension(), image: $request->file('digital_file_after_sell'), fileType: 'file');

        if ($this->orderDetailRepo->update(id: $orderDetails['id'], data: ['digital_file_after_sell' => $digitalFileAfterSell])) {
            ToastMagic::success(translate('digital_file_upload_successfully'));
        } else {
            ToastMagic::error(translate('digital_file_upload_failed'));
        }
        return back();
    }

    public function bulkUpdateStatus(
        Request                       $request,
        DeliveryManTransactionService $deliveryManTransactionService,
        DeliveryManWalletService      $deliveryManWalletService,
        OrderStatusHistoryService     $orderStatusHistoryService,
    ): JsonResponse {
        $targetStatus = $request->get('status');
        $ids = (array)$request->get('ids', []);

        if (!$targetStatus || empty($ids)) {
            return response()->json(['error' => translate('invalid_request')], 422);
        }

        $allowedTransitions = [
            'pending' => ['confirmed', 'canceled'],
            'confirmed' => ['processing', 'canceled'],
            'processing' => ['out_for_delivery', 'failed', 'returned', 'canceled'],
            'out_for_delivery' => ['delivered', 'failed', 'returned'],
            'delivered' => [],
            'returned' => [],
            'failed' => [],
            'canceled' => [],
        ];

        $updated = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['customer', 'seller.shop', 'deliveryMan']);
            if (!$order) {
                $skipped[] = ['id' => $id, 'reason' => 'not_found'];
                continue;
            }

            $current = (string)$order['order_status'];
            if ($current === $targetStatus) {
                $skipped[] = ['id' => $id, 'reason' => 'no_change'];
                continue;
            }

            $allowed = $allowedTransitions[$current] ?? [];
            if (!in_array($targetStatus, $allowed)) {
                $skipped[] = ['id' => $id, 'reason' => 'transition_not_allowed'];
                continue;
            }

            if (!$order['is_guest'] && !isset($order['customer'])) {
                $skipped[] = ['id' => $id, 'reason' => 'customer_deleted'];
                continue;
            }

            $this->orderRepo->updateStockOnOrderStatusChange($id, $targetStatus);
            $this->orderRepo->update(id: $id, data: ['order_status' => $targetStatus]);

            if ($targetStatus == 'delivered') {
                $this->orderRepo->update(id: $id, data: ['payment_status' => 'paid', 'is_pause' => 0]);
                $this->orderDetailRepo->updateWhere(params: ['order_id' => $order['id']], data: ['delivery_status' => $targetStatus, 'payment_status' => 'paid']);
            }

            event(new OrderStatusEvent(key: $targetStatus, type: 'customer', order: $order));
            if ($targetStatus == 'canceled') {
                event(new OrderStatusEvent(key: 'canceled', type: 'delivery_man', order: $order));
            }
            if ($order['seller_is'] == 'seller') {
                if ($targetStatus == 'canceled') {
                    event(new OrderStatusEvent(key: 'canceled', type: 'seller', order: $order));
                } elseif ($targetStatus == 'delivered') {
                    event(new OrderStatusEvent(key: 'delivered', type: 'seller', order: $order));
                }
            }

            $loyaltyPointStatus = getWebConfig(name: 'loyalty_point_status');
            $loyaltyPointEachOrder = getWebConfig(name: 'loyalty_point_for_each_order');
            $loyaltyPointEachOrder = !is_null($loyaltyPointEachOrder) ? $loyaltyPointEachOrder : $loyaltyPointStatus;
            if ($loyaltyPointStatus == 1 && $loyaltyPointEachOrder == 1 && !$order['is_guest'] && $targetStatus == 'delivered') {
                $this->loyaltyPointTransactionRepo->addLoyaltyPointTransaction(userId: $order['customer_id'], reference: $order['id'], amount: usdToDefaultCurrency(amount: $order['order_amount'] - $order['shipping_cost']), transactionType: 'order_place');
            }

            OrderManager::generateReferBonusForFirstOrder(orderId: $order['id']);

            if ($order['delivery_man_id'] && $targetStatus == 'delivered') {
                $deliverymanWallet = $this->deliveryManWalletRepo->getFirstWhere(params: ['delivery_man_id' => $order['delivery_man_id']]);
                $cashInHand = $order['payment_method'] == 'cash_on_delivery' ? $order['order_amount'] : 0;

                if (empty($deliverymanWallet)) {
                    $deliverymanWalletData = $deliveryManWalletService->getDeliveryManData(id: $order['delivery_man_id'], deliverymanCharge: $order['deliveryman_charge'], cashInHand: $cashInHand);
                    $this->deliveryManWalletRepo->add(data: $deliverymanWalletData);
                } else {
                    $deliverymanWalletData = [
                        'current_balance' => $deliverymanWallet['current_balance'] + $order['deliveryman_charge'] ?? 0,
                        'cash_in_hand' => $deliverymanWallet['cash_in_hand'] + $cashInHand ?? 0,
                    ];
                    $this->deliveryManWalletRepo->updateWhere(params: ['delivery_man_id' => $order['delivery_man_id']], data: $deliverymanWalletData);
                }
                if ($order['deliveryman_charge'] && $targetStatus == 'delivered') {
                    $deliveryManTransactionData = $deliveryManTransactionService->getDeliveryManTransactionData(amount: $order['deliveryman_charge'], addedBy: 'admin', id: $order['delivery_man_id'], transactionType: 'deliveryman_charge');
                    $this->deliveryManTransactionRepo->add($deliveryManTransactionData);
                }
            }

            $orderStatusHistoryData = $orderStatusHistoryService->getOrderHistoryData(orderId: $id, userId: 0, userType: 'admin', status: $targetStatus);
            $this->orderStatusHistoryRepo->add($orderStatusHistoryData);

            $transaction = $this->orderTransactionRepo->getFirstWhere(params: ['order_id' => $order['id']]);
            if (isset($transaction) && $transaction['status'] == 'disburse') {
                // skip wallet changes
            }
            if ($targetStatus == 'delivered' && $order['seller_id'] != null) {
                $this->orderRepo->manageWalletOnOrderStatusChange(order: $order, receivedBy: 'admin');
            }
            if ($targetStatus == 'delivered') {
                $referredUser = ReferralCustomer::where('user_id', $order?->customer?->id)->first();
                if ($referredUser?->delivered_notify != 1) {
                    event(new OrderStatusEvent(key: 'your_referred_customer_order_has_been_delivered', type: 'promoter', order: $order));
                    ReferralCustomer::where('user_id', $order?->customer?->id)->update(['delivered_notify' => 1]);
                }
            }

            $updated++;
        }

        return response()->json(['updated' => $updated, 'skipped' => $skipped]);
    }

    public function listIds(Request $request): JsonResponse
    {
        $status = $request->get('status', 'all');

        $vendorId = $request['seller_id'] == '0' ? 1 : $request['seller_id'];
        if ($request['seller_id'] == null) {
            $vendorIs = 'all';
        } elseif ($request['seller_id'] == 'all') {
            $vendorIs = $request['seller_id'];
        } elseif ($request['seller_id'] == '0') {
            $vendorIs = 'admin';
        } else {
            $vendorIs = 'seller';
        }

        $filters = [
            'order_status' => $status,
            'filter' => $request['filter'] ?? 'all',
            'date_type' => $request['date_type'],
            'from' => $request['from'],
            'to' => $request['to'],
            'delivery_man_id' => $request['delivery_man_id'],
            'customer_id' => $request['customer_id'],
            'city_id' => $request['city_id'],
            'seller_id' => $vendorId,
            'seller_is' => $vendorIs,
            'is_printed' => $request['is_printed'] ?? 'all',
        ];
        $orders = $this->orderRepo->getListWhere(orderBy: ['id' => 'desc'], searchValue: $request['searchValue'], filters: $filters, relations: [], dataLimit: 'all');
        return response()->json(['ids' => $orders->pluck('id')->toArray()]);
    }

    public function bulkInvoices(Request $request)
    {
        $ids = (array)$request->get('ids', []);
        $applyTo = $request->get('apply_to');
        $status = $request->get('status', 'all');
        $printLimit = 500; // Maximum orders to print at once
        $totalUnprinted = 0;

        if ($applyTo === 'all') {
            $vendorId = $request['seller_id'] == '0' ? 1 : $request['seller_id'];
            if ($request['seller_id'] == null) {
                $vendorIs = 'all';
            } elseif ($request['seller_id'] == 'all') {
                $vendorIs = $request['seller_id'];
            } elseif ($request['seller_id'] == '0') {
                $vendorIs = 'admin';
            } else {
                $vendorIs = 'seller';
            }

            $filters = [
                'order_status' => $status,
                'filter' => $request['filter'] ?? 'all',
                'date_type' => $request['date_type'],
                'from' => $request['from'],
                'to' => $request['to'],
                'delivery_man_id' => $request['delivery_man_id'],
                'customer_id' => $request['customer_id'],
                'city_id' => $request['city_id'],
                'seller_id' => $vendorId,
                'seller_is' => $vendorIs,
                'is_printed' => $request['is_printed'] ?? 'all',
            ];
            $ordersAll = $this->orderRepo->getListWhere(orderBy: ['id' => 'desc'], searchValue: $request['searchValue'], filters: $filters, relations: [], dataLimit: 'all');
            $allIds = $ordersAll->pluck('id')->toArray();
            $totalUnprinted = count($allIds);
            // Limit to first 500 orders
            $ids = array_slice($allIds, 0, $printLimit);
        }

        if (empty($ids)) {
            ToastMagic::warning(translate('no_order_found'));
            return back();
        }

        // Batch load: one query for orders with relations (avoids 500+ per-order queries)
        $ordersCollection = \App\Models\Order::with(['seller.shop', 'shipping', 'details', 'customer'])
            ->whereIn('id', $ids)
            ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $ids)) . ')')
            ->get();
        $ordersById = $ordersCollection->keyBy('id');

        // Batch load vendors (sellers) used by these orders (keep 0 for inhouse)
        $sellerIds = $ordersCollection->pluck('details')->flatten()->pluck('seller_id')->unique()->filter(fn ($id) => $id !== null)->values()->toArray();
        $vendorsById = $sellerIds ? \App\Models\Seller::whereIn('id', $sellerIds)->get()->keyBy('id') : collect();

        // Batch load governorates for city names
        $cityIds = $ordersCollection->pluck('city_id')->unique()->filter()->values()->toArray();
        $governoratesById = $cityIds ? Governorate::whereIn('id', $cityIds)->get()->keyBy('id') : collect();

        // Config once (avoids 5 × 500 getWebConfig calls)
        $companyPhone = getWebConfig(name: 'company_phone');
        $companyEmail = getWebConfig(name: 'company_email');
        $companyName = getWebConfig(name: 'company_name');
        $companyWebLogo = getWebConfig(name: 'company_web_logo');
        $invoiceSettings = getWebConfig(name: 'invoice_settings');

        $mpdf = new \Mpdf\Mpdf(['default_font' => 'FreeSerif', 'mode' => 'utf-8', 'format' => [190, 250], 'autoLangToFont' => true]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $footerHtml = self::footerHtml('admin');
        $mpdf->SetHTMLFooter($footerHtml);

        $isFirst = true;
        foreach ($ids as $oid) {
            $order = $ordersById->get($oid);
            if (!$order) continue;
            $firstDetail = $order->details->first();
            // Admin invoice uses $order['seller'] for shop name; $vendor is optional (fallback to order's seller or null for inhouse)
            $vendor = $firstDetail ? ($vendorsById->get($firstDetail->seller_id) ?? $order->seller) : $order->seller;
            $shippingAddress = $order->shipping_address_data ?? null;
            $governorateName = $order->city_id ? ($governoratesById->get($order->city_id)?->name_ar) : null;

            $view = PdfView::make('admin-views.order.invoice', compact('order', 'vendor', 'companyPhone', 'companyEmail', 'companyName', 'companyWebLogo', 'invoiceSettings', 'shippingAddress', 'governorateName'));
            $html = $view->render();
            if (!$isFirst) {
                $mpdf->AddPage();
            }
            $mpdf->WriteHTML($html);
            $isFirst = false;
        }

        $fileName = 'orders_invoices_' . date('Ymd_His') . '.pdf';

        // Bulk update: one query instead of 500
        \App\Models\Order::whereIn('id', $ids)->update(['is_printed' => 1, 'order_status' => 'out_for_delivery']);
        
        // Calculate remaining orders for flash message
        $printedCount = count($ids);
        $remainingCount = $totalUnprinted - $printedCount;
        if ($remainingCount > 0) {
            session()->flash('print_remaining', $remainingCount);
        }
        
        $mpdf->Output($fileName, 'D');
        return null;
    }

    public function bulkChangeSeller(Request $request): JsonResponse
    {
        if (!\App\Utils\Helpers::module_permission_check('order_edit')) {
            return response()->json(['error' => translate('access_denied')], 403);
        }

        $targetSellerId = $request->integer('seller_id');
        $applyTo = $request->get('apply_to'); // 'selected' or 'all'
        $ids = (array)$request->get('ids', []);

        if (is_null($targetSellerId)) {
            return response()->json(['error' => translate('invalid_request')], 422);
        }

        // Build ids from filtered results when apply_to=all
        if ($applyTo === 'all') {
            $status = $request->get('status', 'all');
            $vendorId = $request['seller_id'] == '0' ? 1 : $request['seller_id'];
            if ($request['seller_id'] == null) {
                $vendorIs = 'all';
            } elseif ($request['seller_id'] == 'all') {
                $vendorIs = $request['seller_id'];
            } elseif ($request['seller_id'] == '0') {
                $vendorIs = 'admin';
            } else {
                $vendorIs = 'seller';
            }
            $filters = [
                'order_status' => $status,
                'filter' => $request['filter'] ?? 'all',
                'date_type' => $request['date_type'],
                'from' => $request['from'],
                'to' => $request['to'],
                'delivery_man_id' => $request['delivery_man_id'],
                'customer_id' => $request['customer_id'],
                'seller_id' => $vendorId,
                'seller_is' => 'seller',
                'is_printed' => $request['is_printed'] ?? 'all',
            ];
            $ordersAll = $this->orderRepo->getListWhere(orderBy: ['id' => 'desc'], searchValue: $request['searchValue'], filters: $filters, relations: [], dataLimit: 'all');
            $ids = $ordersAll->pluck('id')->toArray();
        }

        if (empty($ids)) {
            return response()->json(['error' => translate('no_order_found')], 422);
        }

        $updated = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['details']);
            if (!$order) {
                $skipped[] = ['id' => $id, 'reason' => 'not_found'];
                continue;
            }

            // Determine seller_is for the target: 0 or 1 maps to admin/seller semantics in this system where 0 is inhouse
            $newSellerIs = $targetSellerId == 0 ? 'admin' : 'seller';

            // If order already has same seller, skip
            if ((int)$order['seller_id'] === (int)$targetSellerId && (string)$order['seller_is'] === (string)$newSellerIs) {
                $skipped[] = ['id' => $id, 'reason' => 'no_change'];
                continue;
            }

            // Constraints: if delivered or returned/failed/canceled, skip changing seller
            if (in_array($order['order_status'], ['delivered', 'returned', 'failed', 'canceled'])) {
                $skipped[] = ['id' => $id, 'reason' => 'immutable_status'];
                continue;
            }

            // Update order: new seller, reset to unprinted and confirmed so the new seller can print it again
            $this->orderRepo->update(id: $id, data: [
                'seller_id' => $targetSellerId,
                'seller_is' => $newSellerIs,
                'is_printed' => 0,
                'order_status' => 'confirmed',
            ]);
            // Update all order details rows to new seller to keep consistency
            foreach ($order->details as $detail) {
                $this->orderDetailRepo->update(id: $detail['id'], data: ['seller_id' => $targetSellerId]);
            }

            $updated++;
        }

        return response()->json(['updated' => $updated, 'skipped' => $skipped]);
    }

    public function updateCustomerInfoQuick(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $customerName = $request->input('customer_name');
        $customerPhone = $request->input('customer_phone');
        $customerAddress = $request->input('customer_address');

        // Validation - only phone is required
        if (!$orderId || !$customerPhone) {
            return response()->json(['error' => translate('invalid_request')], 422);
        }

        $order = $this->orderRepo->getFirstWhere(params: ['id' => $orderId]);
        if (!$order) {
            return response()->json(['error' => translate('order_not_found')], 404);
        }

        // Get existing shipping address data or initialize empty array
        $shippingAddressData = json_decode(json_encode($order['shipping_address_data'] ?? []), true);

        // Update the shipping address data fields
        if (!empty($customerName)) {
            $shippingAddressData['contact_person_name'] = $customerName;
        }
        $shippingAddressData['phone'] = $customerPhone;
        if (!empty($customerAddress)) {
            $shippingAddressData['address'] = $customerAddress;
        }
        $shippingAddressData['updated_at'] = now();

        // Update the order
        $this->orderRepo->update(id: $orderId, data: [
            'shipping_address_data' => json_encode($shippingAddressData)
        ]);

        // Also update customer record if customer exists and name is provided
        if ($order->customer && !empty($customerName)) {
            // Split customer name into first and last name
            $nameParts = explode(' ', trim($customerName), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            $this->customerRepo->update(id: $order->customer_id, data: [
                'f_name' => $firstName,
                'l_name' => $lastName,
                'phone' => $customerPhone,
            ]);
        } elseif ($order->customer) {
            // Only update phone if name is not provided
            $this->customerRepo->update(id: $order->customer_id, data: [
                'phone' => $customerPhone,
            ]);
        }

        return response()->json(['message' => translate('customer_info_updated_successfully')]);
    }

    public function updateCityQuick(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $cityId = $request->input('city_id');
        $sellerId = $request->input('seller_id');

        // Validation
        if (!$orderId || !$cityId || !$sellerId) {
            return response()->json(['error' => translate('invalid_request')], 422);
        }

        $order = $this->orderRepo->getFirstWhere(params: ['id' => $orderId], relations: ['details']);
        if (!$order) {
            return response()->json(['error' => translate('order_not_found')], 404);
        }

        // Determine seller_is based on seller_id (0 = admin/inhouse, others = seller)
        $sellerIs = $sellerId == 0 ? 'admin' : 'seller';

        // Update the order city and seller
        $this->orderRepo->update(id: $orderId, data: [
            'city_id' => $cityId,
            'seller_id' => $sellerId,
            'seller_is' => $sellerIs
        ]);

        // Also update all order details to match the new seller
        foreach ($order->details as $detail) {
            $this->orderDetailRepo->update(id: $detail['id'], data: [
                'seller_id' => $sellerId
            ]);
        }

        return response()->json(['message' => translate('city_and_seller_updated_successfully')]);
    }

}

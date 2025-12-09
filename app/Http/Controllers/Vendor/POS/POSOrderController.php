<?php

namespace App\Http\Controllers\Vendor\POS;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\DigitalProductVariationRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StorageRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Enums\SessionKey;
use App\Enums\ViewPaths\Vendor\POSOrder;
use App\Events\DigitalProductDownloadEvent;
use App\Http\Controllers\BaseController;
use App\Services\CartService;
use App\Services\OrderDetailsService;
use App\Services\OrderService;
use App\Services\POSService;
use App\Traits\CalculatorTrait;
use App\Traits\CustomerTrait;
use App\Models\Order;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use App\Models\CategoryDiscountRule;

class POSOrderController extends BaseController
{
    use CustomerTrait;
    use CalculatorTrait;


    /**
     * @param ProductRepositoryInterface $productRepo
     * @param CustomerRepositoryInterface $customerRepo
     * @param OrderRepositoryInterface $orderRepo
     * @param OrderDetailRepositoryInterface $orderDetailRepo
     * @param VendorRepositoryInterface $vendorRepo
     * @param DigitalProductVariationRepositoryInterface $digitalProductVariationRepo
     * @param StorageRepositoryInterface $storageRepo
     * @param POSService $POSService
     * @param CartService $cartService
     * @param OrderDetailsService $orderDetailsService
     * @param OrderService $orderService
     */
    public function __construct(
        private readonly ProductRepositoryInterface                 $productRepo,
        private readonly CustomerRepositoryInterface                $customerRepo,
        private readonly OrderRepositoryInterface                   $orderRepo,
        private readonly OrderDetailRepositoryInterface             $orderDetailRepo,
        private readonly VendorRepositoryInterface                  $vendorRepo,
        private readonly DigitalProductVariationRepositoryInterface $digitalProductVariationRepo,
        private readonly StorageRepositoryInterface                 $storageRepo,
        private readonly POSService                                 $POSService,
        private readonly CartService                                $cartService,
        private readonly OrderDetailsService                        $orderDetailsService,
        private readonly OrderService                               $orderService,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        return $this->getOrderDetails(id: $type);
    }

    /**
     * @param string $id
     * @return View|RedirectResponse
     */
    public function getOrderDetails(string $id): View|RedirectResponse
    {
        $vendorId = auth('seller')->id();
        $vendor = $this->vendorRepo->getFirstWhere(params: ['id' => $vendorId]);
        $getPOSStatus = getWebConfig('seller_pos');
        if ($vendor['pos_status'] == 0 || $getPOSStatus == 0) {
            ToastMagic::warning(translate('access_denied!!'));
            return redirect()->back();
        }
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['details', 'shipping', 'seller']);
        return view(POSOrder::ORDER_DETAILS[VIEW], compact('order'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $amount = $request['amount'];
        $paidAmount = $request['type'] == 'cash' ? ($request['paid_amount'] ?? 0) : $amount;
        $orderNote = $request['order_note'] ?? null;
        $cityId = $request['city_id'] ?? null;

        // Handle client cart data if present
        if ($request->has('cart_data')) {
            $clientCart = json_decode($request['cart_data'], true);
            $cart = ['items' => []];
            
            // Convert client cart items to server cart format
            foreach ($clientCart['items'] as $item) {
                $cart['items'][] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'image' => $item['image'],
                    'productType' => $item['productType'],
                    'unit' => $item['unit'],
                    'tax' => $item['tax'],
                    'tax_type' => $item['taxType'],
                    'tax_model' => $item['taxModel'],
                    'discount' => $item['discount'],
                    'discount_type' => $item['discountType'],
                    'variant' => $item['variant'],
                    'variations' => $item['variations'],
                    'category_id' => $item['categoryId'] ?? 0,
                    'is_gift' => (bool)($item['isGift'] ?? false),
                    'productSubtotal' => ($item['price'] - $item['discount']) * $item['quantity']
                ];
            }
            
            // Handle extra discount - prefer separate fields over cart data
            $cart['ext_discount'] = $request->get('ext_discount', $clientCart['extraDiscount'] ?? 0);
            $cart['ext_discount_type'] = $request->get('ext_discount_type', ($cart['ext_discount'] > 0 ? 'amount' : null));
            $cart['coupon_discount'] = $clientCart['couponDiscount'] ?? 0;
            
            $cartItems = $cart['items'];
        } else {
            // Handle traditional server-side cart
            $cart = $request->input('cart', []);
            $cartItems = $cart['items'] ?? [];
        }
        
        if (empty($cartItems)) {
            ToastMagic::error(translate('cart_empty_warning'));
            return response()->json();
        }
        if ($amount <= 0) {
            ToastMagic::error(translate('amount_cannot_be_lees_then_0'));
            return response()->json();
        }

        // Early duplicate check BEFORE mutating any customer data
        try {
            $preUserId = 0;
            if ($request->has('customer_id')) {
                $preUserId = (int)$request['customer_id'];
            } else {
                $ciTmp = $request->input('customer', []);
                $preUserId = (int)($ciTmp['id'] ?? 0);
                if ($preUserId === 0 && !empty($ciTmp['phone'])) {
                    $existing = $this->customerRepo->getFirstWhere(['phone' => $ciTmp['phone']]);
                    $preUserId = $existing?->id ?? 0;
                }
            }

            if ($preUserId > 0) {
                $unprintedCountEarly = $this->orderRepo->getCountWhere(filters: [
                    'customer_id' => $preUserId,
                    'order_type' => 'POS',
                    'is_printed' => 0,
                    'created_at_from' => now()->subDay(),
                    'created_at_to' => now(),
                ]);
                if ($unprintedCountEarly > 0) {
                    ToastMagic::warning(translate('This_customer_has_an_unprinted_order_within_the_last_24_hours'));
                    return response()->json([
                        'duplicate_unprinted' => true,
                        'message' => translate('This_customer_has_an_unprinted_order_within_the_last_24_hours')
                    ], 409);
                }
            }
        } catch (\Throwable $e) {
            // fail-open: if check errors out, we proceed to avoid blocking POS
        }

        $customerInfo = $request->input('customer', []);
        $userId = $customerInfo['id'] ?? 0;
        if ($userId == 0 && isset($customerInfo['phone'])) {
            $customer = $this->customerRepo->updateOrCreate(
                ['phone' => $customerInfo['phone']],
                [
                    'f_name' => $customerInfo['f_name'] ?? '',
                    'l_name' => $customerInfo['l_name'] ?? '',
                    'email' => $customerInfo['email'] ?? null,
                    'phone' => $customerInfo['phone'],
                    'password' => bcrypt('123456'),
                ]
            );
            $userId = $customer['id'];
        }

        if ($request['type'] == 'wallet' && $userId != 0) {
            $customerBalance = $this->customerRepo->getFirstWhere(params: ['id' => $userId]) ?? 0;
            if ($customerBalance['wallet_balance'] >= currencyConverter(amount: $amount)) {
                $this->createWalletTransaction(user_id: $userId, amount: floatval($amount), transaction_type: 'order_place', reference: 'order_place_in_pos');
            } else {
                ToastMagic::error(translate('need_Sufficient_Amount_Balance'));
                return response()->json();
            }
        }

        // Definitive duplicate check AFTER resolving customer id (do not create order)
        if (!empty($userId)) {
            $unprintedCount = $this->orderRepo->getCountWhere(filters: [
                'customer_id' => $userId,
                'order_type' => 'POS',
                'is_printed' => 0,
                'created_at_from' => now()->subDay(),
                'created_at_to' => now(),
            ]);
            if ($unprintedCount > 0) {
                ToastMagic::warning(translate('This_customer_has_an_unprinted_order_within_the_last_24_hours'));
                return response()->json([
                    'duplicate_unprinted' => true,
                    'message' => translate('This_customer_has_an_unprinted_order_within_the_last_24_hours')
                ], 409);
            }
        }

        $checkProductTypeDigital = false;
        foreach ($cartItems as $ci) {
            $productTypeCheck = $this->productRepo->getFirstWhere(params: ['id' => $ci['id']]);
            if ($productTypeCheck && $productTypeCheck['product_type'] == 'digital') {
                $checkProductTypeDigital = true;
            }
        }
        if ($userId == 0 && $checkProductTypeDigital) {
            return response()->json(['checkProductTypeForWalkingCustomer' => true, 'message' => translate('To_order_digital_product') . ',' . translate('_kindly_fill_up_the_“Add_New_Customer”_form') . '.']);
        }

        $orderId = (int)(Order::max('id') ?? 99999) + 1;
        foreach ($cartItems as $item) {
            $product = $this->productRepo->getFirstWhere(params: ['id' => $item['id']], relations: ['clearanceSale' => function ($query) {
                return $query->active();
            }]);
            if (!$product) {
                continue;
            }
            $tax = $this->getTaxAmount($item['price'], $product['tax']);
            $price = $product['tax_model'] == 'include' ? $item['price'] - $tax : $item['price'];

            $digitalProductVariation = $this->digitalProductVariationRepo->getFirstWhere(params: ['product_id' => $item['id'], 'variant_key' => $item['variant']], relations: ['storage']);
            if ($product['product_type'] == 'digital' && $digitalProductVariation) {
                $price = $product['tax_model'] == 'include' ? $digitalProductVariation['price'] - $tax : $digitalProductVariation['price'];

                if ($product['digital_product_type'] == 'ready_product') {
                    $getStoragePath = $this->storageRepo->getFirstWhere(params: [
                        'data_id' => $digitalProductVariation['id'],
                        "data_type" => "App\Models\DigitalProductVariation",
                    ]);
                    $product['digital_file_ready'] = $digitalProductVariation['file'];
                    $product['storage_path'] = $getStoragePath ? $getStoragePath['value'] : 'public';
                }
            } elseif ($product['digital_product_type'] == 'ready_product' && !empty($product['digital_file_ready'])) {
                $product['storage_path'] = $product['digital_file_ready_storage_type'] ?? 'public';
            }

            $orderDetail = $this->orderDetailsService->getPOSOrderDetailsData(
                orderId: $orderId, item: $item,
                product: $product, price: $price, tax: $tax
            );
            if ($item['variant'] != null) {
                $variantData = $this->POSService->getVariantData(
                    type: $item['variant'],
                    variation: json_decode($product['variation'], true),
                    quantity: $item['quantity']
                );
                $this->productRepo->update(id: $product['id'], data: ['variation' => json_encode($variantData)]);
            }

            if ($product['product_type'] == 'physical') {
                $currentStock = $product['current_stock'] - $item['quantity'];
                $this->productRepo->update(id: $product['id'], data: ['current_stock' => $currentStock]);
            }
            $this->orderDetailRepo->add(data: $orderDetail);
        }

        // Server-side extra discount calculation (category rules + manual extra)
        $couponDiscount = (float)($cart['coupon_discount'] ?? 0);
        $totals = [
            'subtotal' => 0.0,
            'productDiscount' => 0.0,
            'totalTax' => 0.0,
        ];
        foreach ($cartItems as $ci) {
            $itemSubtotal = (float)$ci['price'] * (int)$ci['quantity'];
            $itemDiscount = (float)$ci['discount'] * (int)$ci['quantity'];
            $totals['subtotal'] += $itemSubtotal;
            $totals['productDiscount'] += $itemDiscount;
            // tax calculation
            $taxAmount = 0.0;
            $tax = (float)($ci['tax'] ?? 0);
            $taxType = $ci['tax_type'] ?? 'percent';
            $taxModel = $ci['tax_model'] ?? 'exclude';
            if ($tax > 0) {
                if ($taxType === 'percent') {
                    if ($taxModel === 'include') {
                        $taxAmount = ($itemSubtotal * $tax) / (100 + $tax);
                    } else {
                        $taxAmount = ($itemSubtotal * $tax) / 100;
                    }
                } else {
                    $taxAmount = $tax * (int)$ci['quantity'];
                }
            }
            $totals['totalTax'] += $taxAmount;
        }

        // Category rules discount (exclude gifts)
        $categoryCounts = [];
        foreach ($cartItems as $ci) {
            if (!empty($ci['is_gift'])) { continue; }
            $catId = (int)($ci['category_id'] ?? 0);
            if ($catId <= 0) { continue; }
            $categoryCounts[$catId] = ($categoryCounts[$catId] ?? 0) + (int)$ci['quantity'];
        }
        $categoryDiscount = 0.0;
        if (!empty($categoryCounts)) {
            $rules = CategoryDiscountRule::whereIn('category_id', array_keys($categoryCounts))
                ->where('is_active', true)
                ->orderBy('quantity', 'desc')
                ->get()
                ->groupBy('category_id');
            foreach ($categoryCounts as $catId => $count) {
                $remaining = $count;
                $catRules = ($rules[$catId] ?? collect())->sortByDesc('quantity');
                foreach ($catRules as $rule) {
                    if ($remaining < (int)$rule->quantity) { continue; }
                    $times = intdiv($remaining, (int)$rule->quantity);
                    if ($times <= 0) { continue; }
                    $categoryDiscount += ((float)$rule->discount_amount) * $times;
                    $remaining = $remaining % (int)$rule->quantity;
                }
            }
        }

        // Manual extra discount (only if cashier explicitly set it)
        $manualExtra = 0.0;
        $manualExtraSet = (int)$request->get('manual_extra_set', 0) === 1;
        $extType = $manualExtraSet ? ($cart['ext_discount_type'] ?? $request->get('ext_discount_type')) : null;
        $extVal = $manualExtraSet ? (float)($cart['ext_discount'] ?? $request->get('ext_discount', 0)) : 0.0;
        if ($manualExtraSet) {
            if ($extType === 'percent') {
                $base = max(0.0, $totals['subtotal'] - $totals['productDiscount']);
                $manualExtra = ($base * $extVal) / 100.0;
            } elseif ($extVal > 0) {
                $manualExtra = $extVal;
            }
        }

        $totalExtraDiscount = (float)$categoryDiscount + (float)$manualExtra;
        $cart['ext_discount'] = $totalExtraDiscount;
        $cart['ext_discount_type'] = $totalExtraDiscount > 0 ? 'amount' : null;

        // Recompute order amount server-side to ensure consistency
        $serverAmount = $totals['subtotal'] - $totals['productDiscount'] + $totals['totalTax'] - (float)$couponDiscount - (float)$totalExtraDiscount;
        if ($serverAmount < 0) { $serverAmount = 0; }
        $amount = $serverAmount;
        // Force paid amount to match total to avoid change amount in POS flow
        if ($request['type'] == 'cash') {
            $paidAmount = $amount;
        }

        $order = $this->orderService->getPOSOrderData(
            orderId: $orderId,
            cart: $cart,
            amount: $amount,
            paidAmount: $paidAmount,
            paymentType: $request['type'],
            addedBy: 'seller',
            userId: $userId,
            sellerId: auth('seller')->id(),
            cityId: $cityId,
            orderNote: $orderNote,
        );
        $this->orderRepo->add(data: $order);
        if ($checkProductTypeDigital) {
            $order = $this->orderRepo->getFirstWhere(params: ['id' => $orderId], relations: ['details.productAllStatus']);
            $data = [
                'userName' => $order->customer->f_name,
                'userType' => 'customer',
                'templateName' => 'digital-product-download',
                'order' => $order,
                'subject' => translate('download_Digital_Product'),
                'title' => translate('Congratulations') . '!',
                'emailId' => $order->customer['email'],
            ];
            event(new DigitalProductDownloadEvent(email: $order->customer['email'], data: $data));
        }

        ToastMagic::success(translate('order_placed_successfully'));
        return response()->json(['order_id' => $orderId]);
    }

    public function cancelOrder(Request $request): JsonResponse
    {
        session()->remove($request['cart_id']);
        $totalHoldOrders = $this->POSService->getTotalHoldOrders();
        $cartNames = $this->POSService->getCartNames();
        $cartItems = $this->getHoldOrderCalculationData(cartNames: $cartNames);
        return response()->json([
            'message' => $request['cart_id'] . ' ' . translate('order_is_cancel'),
            'status' => 'success',
            'view' => view(POSOrder::CANCEL_ORDER[VIEW], compact('cartItems', 'totalHoldOrders'))->render(),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllHoldOrdersView(Request $request): JsonResponse
    {
        $cartNames = $this->POSService->getCartNames();
        $cartItems = $this->getHoldOrderCalculationData(cartNames: $cartNames);
        $totalHoldOrders = $this->POSService->getTotalHoldOrders();
        if (!empty($request['customer'])) {
            $searchValue = strtolower($request['customer']);
            $filteredItems = collect($cartItems)->filter(function ($item) use ($searchValue) {
                return str_contains(strtolower($item['customerName']), $searchValue) !== false;
            });
            $cartItems = $filteredItems->all();
        }
        return response()->json([
            'flag' => 'inactive',
            'totalHoldOrders' => $totalHoldOrders,
            'view' => view(POSOrder::HOLD_ORDERS[VIEW], compact('totalHoldOrders', 'cartItems'))->render(),
        ]);
    }

    /**
     * @param array $cartNames
     * @return array
     */
    protected function getHoldOrderCalculationData(array $cartNames): array
    {
        $CustomerCartData = [];
        foreach ($cartNames as $cartId) {
            $CustomerData = $this->getCustomerCartData(cartName: $cartId);
            $CartItemData = $this->calculateCartItemsData(cartName: $cartId, customerCartData: $CustomerData);
            $CustomerCartData[$cartId] = array_merge($CustomerData[$cartId], $CartItemData);
        }
        return $CustomerCartData;
    }

    /**
     * @param string $cartName
     * @return array
     */
    protected function getCustomerCartData(string $cartName): array
    {
        $customerCartData = [];
        if (Str::contains($cartName, 'walk-in-customer')) {
            $currentCustomerInfo = [
                'customerName' => 'Walk-In Customer',
                'customerPhone' => "",
            ];
            $customerId = 0;
        } else {
            $customerId = explode('-', $cartName)[2];
            $currentCustomerData = $this->customerRepo->getFirstWhere(params: ['id' => $customerId]);
            $currentCustomerInfo = $this->cartService->getCustomerInfo(currentCustomerData: $currentCustomerData, customerId: $customerId);
        }
        $customerCartData[$cartName] = [
            'customerName' => $currentCustomerInfo['customerName'],
            'customerPhone' => $currentCustomerInfo['customerPhone'],
            'customerId' => $customerId,
        ];
        return $customerCartData;
    }

    protected function calculateCartItemsData(string $cartName, array $customerCartData): array
    {
        $cartItemValue = [];
        $subTotalCalculation = [
            'countItem' => 0,
            'totalQuantity' => 0,
            'taxCalculate' => 0,
            'totalTaxShow' => 0,
            'totalTax' => 0,
            'totalIncludeTax' => 0,
            'subtotal' => 0,
            'discountOnProduct' => 0,
            'productSubtotal' => 0,
        ];
        if (session()->get($cartName)) {
            foreach (session()->get($cartName) as $cartItem) {
                if (is_array($cartItem)) {
                    $product = $this->productRepo->getFirstWhere(params: ['id' => $cartItem['id']], relations: ['clearanceSale' => function ($query) {
                        return $query->active();
                    }]);
                    $cartSubTotalCalculation = $this->cartService->getCartSubtotalCalculation(
                        product: $product,
                        cartItem: $cartItem,
                        calculation: $subTotalCalculation
                    );
                    if ($cartItem['customerId'] == $customerCartData[$cartName]['customerId']) {
                        $cartItem['productSubtotal'] = $cartSubTotalCalculation['productSubtotal'];
                        $subTotalCalculation['customerOnHold'] = $cartItem['customerOnHold'];
                        $cartItemValue[] = $cartItem;

                        $subTotalCalculation['countItem'] += $cartSubTotalCalculation['countItem'];
                        $subTotalCalculation['totalQuantity'] += $cartSubTotalCalculation['totalQuantity'];
                        $subTotalCalculation['taxCalculate'] += $cartSubTotalCalculation['taxCalculate'];
                        $subTotalCalculation['totalTaxShow'] += $cartSubTotalCalculation['totalTaxShow'];
                        $subTotalCalculation['totalTax'] += $cartSubTotalCalculation['totalTax'];
                        $subTotalCalculation['totalIncludeTax'] += $cartSubTotalCalculation['totalIncludeTax'];
                        $subTotalCalculation['productSubtotal'] += $cartSubTotalCalculation['productSubtotal'];
                        $subTotalCalculation['subtotal'] += $cartSubTotalCalculation['subtotal'];
                        $subTotalCalculation['discountOnProduct'] += $cartSubTotalCalculation['discountOnProduct'];
                    }
                }
            }
        }
        $totalCalculation = $this->cartService->getTotalCalculation(
            subTotalCalculation: $subTotalCalculation, cartName: $cartName
        );
        return [
            'countItem' => $subTotalCalculation['countItem'],
            'total' => $totalCalculation['total'],
            'subtotal' => $subTotalCalculation['subtotal'],
            'taxCalculate' => $subTotalCalculation['taxCalculate'],
            'totalTaxShow' => $subTotalCalculation['totalTaxShow'],
            'totalTax' => $subTotalCalculation['totalTax'],
            'discountOnProduct' => $subTotalCalculation['discountOnProduct'],
            'productSubtotal' => $subTotalCalculation['productSubtotal'],
            'cartItemValue' => $cartItemValue,
            'couponDiscount' => $totalCalculation['couponDiscount'],
            'extraDiscount' => $totalCalculation['extraDiscount'],
            'customerOnHold' => $subTotalCalculation['customerOnHold'] ?? false,
        ];
    }

}

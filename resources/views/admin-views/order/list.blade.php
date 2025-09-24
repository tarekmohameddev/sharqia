@extends('layouts.admin.app')
@section('title', translate('order_List'))
@section('content')
    <div class="content container-fluid">
        <div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <h2 class="h1 mb-0">
                    <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/all-orders.png') }}" class="mb-1 mr-1"
                        alt="">
                    <span class="page-header-title">
                        @if ($status == 'processing')
                            {{ translate('packaging_Orders') }}
                        @elseif($status == 'failed')
                            {{ translate('failed_to_Deliver_Orders') }}
                        @elseif($status == 'all')
                            {{ translate('all_Orders') }}
                        @else
                            {{ translate(str_replace('_', ' ', $status)) }} {{ translate('Orders') }}
                        @endif
                    </span>
                </h2>
                <span class="badge text-dark bg-body-secondary fw-semibold rounded-45">{{ $orders->total() }}</span>
            </div>
            <div class="card">
                <div class="card-body">
                    @isset($stats)
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="d-flex flex-column p-3 rounded bg-light h-100">
                                    <span class="text-muted">{{ translate('total_orders') }}</span>
                                    <strong class="h4 mb-0">{{ $stats['total'] }}</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="d-flex flex-column p-3 rounded bg-light h-100">
                                    <span class="text-muted">{{ translate('total_orders_this_month') }}</span>
                                    <strong class="h4 mb-0">{{ $stats['this_month'] }}</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="d-flex flex-column p-3 rounded bg-light h-100">
                                    <span class="text-muted">{{ translate('total_orders_today') }}</span>
                                    <strong class="h4 mb-0">{{ $stats['today'] }}</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="d-flex flex-column p-3 rounded bg-light h-100">
                                    <span class="text-muted">{{ translate('printed_orders') }}</span>
                                    <strong class="h4 mb-0">{{ $stats['printed'] }}</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="d-flex flex-column p-3 rounded bg-light h-100">
                                    <span class="text-muted">{{ translate('unprinted_orders') }}</span>
                                    <strong class="h4 mb-0">{{ $stats['unprinted'] }}</strong>
                                </div>
                            </div>
                        </div>
                    @endisset
                    <form action="{{ route('admin.orders.list', ['status' => request('status')]) }}" id="form-data"
                        method="GET">
                        <div class="row g-3">
                            <div class="col-12">
                                <h3 class="mb-3 text-capitalize">{{ translate('filter_order') }}</h3>
                            </div>
                            @if (request('delivery_man_id'))
                                <input type="hidden" name="delivery_man_id" value="{{ request('delivery_man_id') }}">
                            @endif

                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="form-group">
                                    <label class="form-label" for="filter">{{ translate('order_type') }}</label>
                                    <div class="select-wrapper">
                                        <select name="filter" id="filter" class="form-select">
                                            <option value="all" {{ $filter == 'all' ? 'selected' : '' }}>
                                                {{ translate('all') }}</option>
                                            <option value="admin" {{ $filter == 'admin' ? 'selected' : '' }}>
                                                {{ translate('in_House_Order') }}</option>
                                            <option value="seller" {{ $filter == 'seller' ? 'selected' : '' }}>
                                                {{ translate('vendor_Order') }}</option>
                                            @if (($status == 'all' || $status == 'delivered') && !request()->has('delivery_man_id'))
                                                <option value="POS" {{ $filter == 'POS' ? 'selected' : '' }}>
                                                    {{ translate('POS_Order') }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl-3" id="seller_id_area"
                                style="{{ $filter && $filter == 'admin' ? 'display:none' : '' }}">
                                <div class="form-group">
                                    <label class="form-label" for="store">{{ translate('store') }}</label>
                                    <div class="select-wrapper">
                                        <select name="seller_id" id="seller_id" class="form-select">
                                            <option value="all">{{ translate('all_shop') }}</option>
                                           <option value="0" id="seller_id_inhouse"{{ request()->has('seller_id') ? (request('seller_id') == 0 ? 'selected' : '') : '' }}>{{ translate('inhouse') }}</option>
                                            @foreach ($sellers as $seller)
                                                @isset($seller->shop)
                                                    <option
                                                        value="{{ $seller->id }}"{{ request('seller_id') == $seller->id ? 'selected' : '' }}>
                                                        {{ $seller->shop->name }}
                                                    </option>
                                                @endisset
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="form-group">
                                    <label class="form-label" for="customer">{{ translate('customer') }}</label>

                                    <input type="hidden" id='customer_id' name="customer_id"
                                        value="{{ request('customer_id') ? request('customer_id') : 'all' }}">
                                    <select id="customer_id_value"
                                        data-placeholder="@if ($customer == 'all') {{ translate('all_customer') }}
                                                    @else
                                                        {{ $customer->name ?? $customer->f_name . ' ' . $customer->l_name . ' ' . '(' . $customer->phone . ')' }} @endif"
                                        class="js-data-example-ajax form-control form-ellipsis">
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="form-group">
                                    <label class="form-label" for="date_type">{{ translate('date_type') }}</label>
                                    <div class="select-wrapper">
                                        <select class="form-select" name="date_type" id="date_type">
                                            <option value="" selected disabled>{{ translate('select_Date_Type') }}
                                            </option>
                                            <option value="this_year" {{ $dateType == 'this_year' ? 'selected' : '' }}>
                                                {{ translate('this_Year') }}</option>
                                            <option value="this_month" {{ $dateType == 'this_month' ? 'selected' : '' }}>
                                                {{ translate('this_Month') }}</option>
                                            <option value="this_week" {{ $dateType == 'this_week' ? 'selected' : '' }}>
                                                {{ translate('this_Week') }}</option>
                                            <option value="custom_date" {{ $dateType == 'custom_date' ? 'selected' : '' }}>
                                                {{ translate('custom_Date') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="form-group">
                                    <label class="form-label" for="is_printed">{{ translate('printed_status') }}</label>
                                    <div class="select-wrapper">
                                        <select class="form-select" name="is_printed" id="is_printed">
                                            <option value="all" {{ request('is_printed','all') == 'all' ? 'selected' : '' }}>{{ translate('all') }}</option>
                                            <option value="1" {{ request('is_printed') === '1' ? 'selected' : '' }}>{{ translate('printed_only') }}</option>
                                            <option value="0" {{ request('is_printed') === '0' ? 'selected' : '' }}>{{ translate('unprinted_only') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl-3" id="from_div">
                                <div class="form-group">
                                    <label class="form-label" for="customer">{{ translate('start_date') }}</label>
                                    <input type="date" name="from" value="{{ $from }}" id="from_date"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl-3" id="to_div">
                                <div class="form-group">
                                    <label class="form-label" for="customer">{{ translate('end_date') }}</label>
                                    <input type="date" value="{{ $to }}" name="to" id="to_date"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-3 justify-content-end">
                                    <a href="{{ route('admin.orders.list', ['status' => request('status'), 'delivery_man_id' => request('delivery_man_id')]) }}"
                                        class="btn btn-secondary min-w-120">
                                        {{ translate('reset') }}
                                    </a>
                                    <button type="submit" class="btn btn-primary min-w-120" id="formUrlChange"
                                        data-action="{{ url()->current() }}">
                                        {{ translate('show_data') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body d-flex flex-column gap-20">

                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
                        <h3 class="mb-0">
                            {{ translate('order_list') }}
                            <span class="badge badge-info text-bg-info">{{ $orders->total() }}</span>
                        </h3>

                        <div class="d-flex gap-3 align-items-center flex-wrap">
                            <form action="{{ url()->current() }}" method="GET">
                                <div class="form-group">
                                    <div class="input-group">
                                        <input id="datatableSearch_" type="search" name="searchValue"
                                            class="form-control" placeholder="{{ translate('search_by_Order_ID') }}"
                                            aria-label="Search by customer phone or order id" value="{{ $searchValue }}">
                                        <div class="input-group-append search-submit">
                                            <button type="submit">
                                                <i class="fi fi-rr-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <div class="d-flex align-items-center gap-2">
                                <div class="select-wrapper">
                                    <select id="bulk-action-select" class="form-select">
                                        <option value="">{{ translate('bulk_actions') }}</option>
                                        <optgroup label="{{ translate('change_status') }}">
                                            @foreach(\App\Enums\OrderStatus::LIST as $st)
                                                <option value="status:{{ $st }}">{{ translate(str_replace('_',' ',$st)) }}</option>
                                            @endforeach
                                        </optgroup>
                                        <option value="print:selected">{{ translate('print_selected_invoices') }}</option>
                                        <option value="print:all">{{ translate('print_all_in_filtered_results') }}</option>
                                    </select>
                                </div>
                                <button id="apply-bulk-action" type="button" class="btn btn-primary">
                                    {{ translate('apply') }}
                                </button>
                                <button id="print-unprinted" type="button" class="btn btn-outline-secondary text-nowrap">
                                    {{ translate('print_unprinted') }}
                                </button>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="select-wrapper">
                                        <select id="bulk-seller-select" class="form-select">
                                            <option value="">{{ translate('select_store') }}</option>
                                            <option value="0">{{ translate('inhouse') }}</option>
                                            @foreach ($sellers as $seller)
                                                @isset($seller->shop)
                                                    <option value="{{ $seller->id }}">{{ $seller->shop->name }}</option>
                                                @endisset
                                            @endforeach
                                        </select>
                                    </div>
                                    <button id="apply-bulk-seller" type="button" class="btn btn-outline-primary">
                                        {{ translate('change_store_for_selected') }}
                                    </button>
                                    <button id="apply-bulk-seller-filtered" type="button" class="btn btn-outline-primary">
                                        {{ translate('change_store_for_filtered') }}
                                    </button>
                                </div>
                            </div>

                            <a type="button" class="btn btn-outline-primary text-nowrap"
                                href="{{ route('admin.orders.export-excel', ['delivery_man_id' => request('delivery_man_id'), 'status' => $status, 'from' => $from, 'to' => $to, 'filter' => $filter, 'searchValue' => $searchValue, 'seller_id' => $vendorId, 'customer_id' => $customerId, 'date_type' => $dateType]) }}">
                                <img width="14"
                                    src="{{ dynamicAsset(path: 'public/assets/back-end/img/excel.png') }}" alt=""
                                    class="excel">
                                <span class="ps-2">{{ translate('export') }}</span>
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless">
                            <thead class="text-capitalize">
                                <tr>
                                    <th>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="checkbox" id="select-all-orders">
                                            <label class="mb-0" for="select-all-orders">{{ translate('all') }}</label>
                                        </div>
                                    </th>
                                    <th>{{ translate('SL') }}</th>
                                    <th>{{ translate('order_ID') }}</th>
                                    <th class="text-capitalize">{{ translate('order_date') }}</th>
                                    <th class="text-capitalize">{{ translate('customer_info') }}</th>
                                    <th>{{ translate('store') }}</th>
                                    <th class="text-capitalize">{{ translate('total_amount') }}</th>
                                    <th class="text-capitalize">{{ translate('printed') }}</th>
                                    @if ($status == 'all')
                                        <th class="text-center">{{ translate('order_status') }} </th>
                                    @else
                                        <th class="text-capitalize">{{ translate('payment_method') }} </th>
                                    @endif
                                    <th class="text-center">{{ translate('action') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($orders as $key => $order)

                                    <tr class="status-{{ $order['order_status'] }} class-all">
                                        <td class="">
                                            <input type="checkbox" class="order-select" value="{{ $order['id'] }}">
                                        </td>
                                        <td class="">
                                            {{ $orders->firstItem() + $key }}
                                        </td>
                                        <td>
                                            <a class="text-dark"
                                                href="{{ route('admin.orders.details', ['id' => $order['id']]) }}">{{ $order['id'] }}
                                                {!! $order->order_type == 'POS' ? '<span class="text--primary">(POS)</span>' : '' !!}</a>
                                        </td>
                                        <td>
                                            <div>{{ date('d M Y', strtotime($order['created_at'])) }},</div>
                                            <div>{{ date('h:i A', strtotime($order['created_at'])) }}</div>
                                        </td>
                                        <td>
                                            @if ($order->is_guest)
                                                <strong class="text-dark">{{ translate('guest_customer') }}</strong>
                                            @elseif($order->customer_id == 0)
                                                <strong class="text-dark">{{ translate('Walk-In-Customer') }}</strong>
                                            @else
                                                @if ($order->customer)
                                                    <a class="text-body text-capitalize"
                                                        href="{{ route('admin.customer.view', ['user_id' => $order->customer['id']]) }}">
                                                        <strong
                                                            class="title-name">{{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}</strong>
                                                    </a>
                                                    @if ($order->customer['phone'])
                                                        <a class="d-block text-dark"
                                                            href="tel:{{ $order->customer['phone'] }}">{{ $order->customer['phone'] }}</a>
                                                    @else
                                                        <a class="d-block text-dark"
                                                            href="mailto:{{ $order->customer['email'] }}">{{ $order->customer['email'] }}</a>
                                                    @endif
                                                    @php($altPhone = data_get($order, 'shipping_address_data.alternative_phone') ?: ($order->customer->alternative_phone ?? null))
                                                    @if (!empty($altPhone))
                                                        <small class="d-block text-muted">Alt: <a class="text-muted" href="tel:{{ $altPhone }}">{{ $altPhone }}</a></small>
                                                    @endif
                                                @else
                                                    <label class="badge badge-danger text-bg-danger">
                                                        {{ translate('customer_not_found') }}
                                                    </label>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if (isset($order->seller_id) && isset($order->seller_is))
                                                <a href="{{ $order->seller_is == 'seller' && $order->seller?->shop ? route('admin.vendors.view', ['id' => $order->seller->shop->id]) : 'javascript:' }}"
                                                    class="store-name fw-medium text-dark">
                                                    @if ($order->seller_is == 'seller')
                                                        {{ isset($order->seller?->shop) ? $order->seller?->shop?->name : translate('Store_not_found') }}
                                                    @elseif($order->seller_is == 'admin')
                                                        {{ translate('in_House') }}
                                                    @endif
                                                </a>
                                            @else
                                                {{ translate('Store_not_found') }}
                                            @endif
                                        </td>
                                        <td>
                                            <div>
                                                @php($orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order))
                                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['totalAmount']), currencyCode: getCurrencyCode()) }}
                                            </div>

                                            @if ($order->payment_status == 'paid')
                                                <span
                                                    class="badge badge-success text-bg-success">{{ translate('paid') }}</span>
                                            @else
                                                <span
                                                    class="badge badge-danger text-bg-danger">{{ translate('unpaid') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($order->is_printed)
                                                <span class="badge badge-success text-bg-success">{{ translate('yes') }}</span>
                                            @else
                                                <span class="badge badge-secondary bg-secondary">{{ translate('no') }}</span>
                                            @endif
                                        </td>
                                        @if ($status == 'all')
                                            <td class="text-center text-capitalize">
                                                @if ($order['order_status'] == 'pending')
                                                    <span class="badge badge-info text-bg-info">
                                                        {{ translate($order['order_status']) }}
                                                    </span>
                                                @elseif($order['order_status'] == 'processing' || $order['order_status'] == 'out_for_delivery')
                                                    <span class="badge badge-warning text-bg-warning">
                                                        {{ str_replace('_', ' ', $order['order_status'] == 'processing' ? translate('packaging') : translate($order['order_status'])) }}
                                                    </span>
                                                @elseif($order['order_status'] == 'confirmed')
                                                    <span class="badge badge-success text-bg-success">
                                                        {{ translate($order['order_status']) }}
                                                    </span>
                                                @elseif($order['order_status'] == 'failed')
                                                    <span class="badge badge-danger text-bg-danger">
                                                        {{ translate('failed_to_deliver') }}
                                                    </span>
                                                @elseif($order['order_status'] == 'delivered')
                                                    <span class="badge badge-success text-bg-success">
                                                        {{ translate($order['order_status']) }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-danger text-bg-danger">
                                                        {{ translate($order['order_status']) }}
                                                    </span>
                                                @endif
                                            </td>
                                        @else
                                            <td class="text-capitalize">
                                                {{ str_replace('_', ' ', $order['payment_method']) }}
                                            </td>
                                        @endif
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a class="btn btn-outline-info btn-outline-info-dark icon-btn"
                                                    title="{{ translate('view') }}"
                                                    href="{{ route('admin.orders.details', ['id' => $order['id']]) }}">
                                                    <i class="fi fi-sr-eye"></i>
                                                </a>
                                                <a class="btn btn-outline-success btn-outline-success-dark icon-btn"
                                                    target="_blank" title="{{ translate('invoice') }}"
                                                    href="{{ route('admin.orders.generate-invoice', [$order['id']]) }}">
                                                    <i class="fi fi-sr-down-to-line"></i>
                                                </a>
                                                @if(\App\Utils\Helpers::module_permission_check('order_edit'))
                                                <a class="btn btn-outline-primary btn-outline-primary-dark icon-btn"
                                                   title="{{ translate('edit') }}"
                                                   href="{{ route('admin.orders.edit', ['id' => $order['id']]) }}">
                                                    <i class="fi fi-rr-edit"></i>
                                                </a>
                                                @endif
                                                <button type="button" class="btn btn-outline-danger btn-outline-danger-dark icon-btn js-create-refund"
                                                    data-order-id="{{ $order['id'] }}" title="{{ translate('refund_request') }}">
                                                    <i class="fi fi-rr-undo"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning btn-outline-warning-dark icon-btn js-flag-late"
                                                    data-order-id="{{ $order['id'] }}" title="{{ translate('flag_as_late') }}">
                                                    <i class="fi fi-rr-time-forward"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <div class="d-flex justify-content-lg-end">
                            {!! $orders->links() !!}
                        </div>
                    </div>
                    @if (count($orders) == 0)
                        @include(
                            'layouts.admin.partials._empty-state',
                            ['text' => 'no_order_found'],
                            ['image' => 'default']
                        )
                    @endif
                </div>
            </div>
            <div class="js-nav-scroller hs-nav-scroller-horizontal d-none">
                <span class="hs-nav-scroller-arrow-prev d-none">
                    <a class="hs-nav-scroller-arrow-link" href="javascript:">
                        <i class="fi fi-rr-angle-left"></i>
                    </a>
                </span>

                <span class="hs-nav-scroller-arrow-next d-none">
                    <a class="hs-nav-scroller-arrow-link" href="javascript:">
                        <i class="fi fi-rr-angle-right"></i>
                    </a>
                </span>
                <ul class="nav nav-tabs page-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">{{ translate('order_list') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <span id="message-date-range-text" data-text="{{ translate('invalid_date_range') }}"></span>
    <span id="js-data-example-ajax-url" data-url="{{ route('admin.orders.customers') }}"></span>
    <span id="bulk-status-url" data-url="{{ route('admin.orders.bulk-status') }}"></span>
    <span id="bulk-invoices-url" data-url="{{ route('admin.orders.bulk-invoices') }}"></span>
    <span id="bulk-change-seller-url" data-url="{{ route('admin.orders.bulk-change-seller') }}"></span>
    <span id="current-order-status" data-status="{{ $status }}"></span>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/order.js') }}"></script>
    <script>
        (function () {
            const selectAll = document.getElementById('select-all-orders');
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.order-select').forEach(cb => {
                        cb.checked = selectAll.checked;
                    });
                });
            }

            function getSelectedIds() {
                const ids = [];
                document.querySelectorAll('.order-select:checked').forEach(cb => ids.push(cb.value));
                return ids;
            }

            const applyBtn = document.getElementById('apply-bulk-action');
            const selectEl = document.getElementById('bulk-action-select');
            const bulkSellerSelect = document.getElementById('bulk-seller-select');
            const bulkSellerSelectedBtn = document.getElementById('apply-bulk-seller');
            const bulkSellerFilteredBtn = document.getElementById('apply-bulk-seller-filtered');
            if (applyBtn && selectEl) {
                applyBtn.addEventListener('click', function () {
                    const action = selectEl.value;
                    if (!action) return;
                    const [type, param] = action.split(':');
                    if (type === 'status') {
                        const ids = getSelectedIds();
                        if (ids.length === 0) {
                            toastMagic.warning('{{ translate('please_select_at_least_one_order') }}');
                            return;
                        }
                        Swal.fire({
                            title: '{{ translate('are_you_sure') }}',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#377dff',
                            cancelButtonColor: '#dd3333',
                            confirmButtonText: '{{ translate('yes_change') }}'
                        }).then((result) => {
                            if (!result.value) return;
                            $.ajaxSetup({
                                headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') }
                            });
                            $.post($('#bulk-status-url').data('url'), { ids: ids, status: param }, function (res) {
                                toastMagic.success('{{ translate('status_updated_successfully') }}');
                                location.reload();
                            }).fail(function () {
                                toastMagic.error('{{ translate('something_went_wrong') }}');
                            });
                        });
                    } else if (type === 'print') {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = $('#bulk-invoices-url').data('url') + window.location.search;
                        const csrf = document.querySelector('meta[name="_token"]').getAttribute('content');
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = csrf;
                        form.appendChild(csrfInput);

                        const statusInput = document.createElement('input');
                        statusInput.type = 'hidden';
                        statusInput.name = 'status';
                        statusInput.value = document.getElementById('current-order-status').dataset.status || 'all';
                        form.appendChild(statusInput);

                        if (param === 'selected') {
                            const ids = getSelectedIds();
                            if (ids.length === 0) {
                                toastMagic.warning('{{ translate('please_select_at_least_one_order') }}');
                                return;
                            }
                            ids.forEach(function (id) {
                                const i = document.createElement('input');
                                i.type = 'hidden';
                                i.name = 'ids[]';
                                i.value = id;
                                form.appendChild(i);
                            });
                        } else if (param === 'all') {
                            const applyTo = document.createElement('input');
                            applyTo.type = 'hidden';
                            applyTo.name = 'apply_to';
                            applyTo.value = 'all';
                            form.appendChild(applyTo);
                        }

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            function postBulkChangeSeller(applyTo) {
                const targetSellerId = bulkSellerSelect?.value;
                if (!targetSellerId) {
                    toastMagic.warning('{{ translate('please_select_a_store') }}');
                    return;
                }
                const payload = { seller_id: targetSellerId, apply_to: applyTo };
                if (applyTo === 'selected') {
                    const ids = getSelectedIds();
                    if (ids.length === 0) {
                        toastMagic.warning('{{ translate('please_select_at_least_one_order') }}');
                        return;
                    }
                    payload.ids = ids;
                } else if (applyTo === 'all') {
                    payload.status = document.getElementById('current-order-status').dataset.status || 'all';
                }

                Swal.fire({
                    title: '{{ translate('are_you_sure') }}',
                    text: '{{ translate('this_will_change_the_store_for_the_selected_orders') }}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#377dff',
                    cancelButtonColor: '#dd3333',
                    confirmButtonText: '{{ translate('yes_change') }}'
                }).then((result) => {
                    if (!result.value) return;
                    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') } });
                    $.post($('#bulk-change-seller-url').data('url') + (applyTo === 'all' ? window.location.search : ''), payload, function (res) {
                        toastMagic.success('{{ translate('updated_successfully') }}');
                        location.reload();
                    }).fail(function (xhr) {
                        const msg = xhr.responseJSON?.error ?? '{{ translate('something_went_wrong') }}';
                        toastMagic.error(msg);
                    });
                });
            }

            if (bulkSellerSelectedBtn) {
                bulkSellerSelectedBtn.addEventListener('click', function () { postBulkChangeSeller('selected'); });
            }
            if (bulkSellerFilteredBtn) {
                bulkSellerFilteredBtn.addEventListener('click', function () { postBulkChangeSeller('all'); });
            }

            const unprintedBtn = document.getElementById('print-unprinted');
            if (unprintedBtn) {
                unprintedBtn.addEventListener('click', function () {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = $('#bulk-invoices-url').data('url') + window.location.search;
                    const csrf = document.querySelector('meta[name="_token"]').getAttribute('content');
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrf;
                    form.appendChild(csrfInput);

                    const statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'status';
                    statusInput.value = document.getElementById('current-order-status').dataset.status || 'all';
                    form.appendChild(statusInput);

                    const applyTo = document.createElement('input');
                    applyTo.type = 'hidden';
                    applyTo.name = 'apply_to';
                    applyTo.value = 'all';
                    form.appendChild(applyTo);

                    const isPrinted = document.createElement('input');
                    isPrinted.type = 'hidden';
                    isPrinted.name = 'is_printed';
                    isPrinted.value = '0';
                    form.appendChild(isPrinted);

                    document.body.appendChild(form);
                    form.submit();
                });
            }
        })();
    </script>
    <script>
        $(document).on('click', '.js-flag-late', function () {
            const orderId = $(this).data('order-id');
            $.post({
                url: '{{ url('admin/late-delivery/flag') }}/' + orderId,
                data: {
                    _token: '{{ csrf_token() }}'
                }
            }).done(function (res) {
                toastMagic.success(res.message ?? '{{ translate('late_delivery_request_created') }}');
            }).fail(function (xhr) {
                const msg = xhr.responseJSON?.error ?? '{{ translate('something_went_wrong') }}';
                toastMagic.error(msg);
            });
        });
        $(document).on('click', '.js-create-refund', function () {
            const orderId = $(this).data('order-id');
            Swal.fire({
                title: '{{ translate('are_you_sure') }}',
                text: '{{ translate('refund_request') }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#377dff',
                cancelButtonColor: '#dd3333',
                confirmButtonText: '{{ translate('yes') }}'
            }).then((result) => {
                if (!result.value) return;
                $.post({
                    url: '{{ url('admin/orders/create-refund') }}/' + orderId,
                    data: {
                        _token: '{{ csrf_token() }}'
                    }
                }).done(function (res) {
                    toastMagic.success(res.message ?? '{{ translate('refund_requested_successful!!') }}');
                }).fail(function (xhr) {
                    const msg = xhr.responseJSON?.error ?? '{{ translate('something_went_wrong') }}';
                    toastMagic.error(msg);
                });
            });
        });
    </script>
@endpush

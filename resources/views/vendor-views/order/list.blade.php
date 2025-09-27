@extends('layouts.vendor.app')
@section('title', translate('order_List'))

@push('css_or_js')
    <link href="{{dynamicAsset(path: 'public/assets/back-end/vendor/datatables/dataTables.bootstrap4.min.css')}}" rel="stylesheet">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <h2 class="h1 mb-0">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/all-orders.png') }}" class="mb-1 mr-1" alt="">
                <span class="page-header-title">
                    @if($status =='processing')
                        {{translate('packaging')}}
                    @elseif($status =='failed')
                        {{translate('failed_to_Deliver')}}
                    @elseif($status == 'all')
                        {{translate('all')}}
                    @else
                        {{translate(str_replace('_',' ',$status))}}
                    @endif
                </span>
                {{translate('orders')}}
            </h2>
            <span class="badge badge-soft-dark radius-50 fz-14">{{$orders->total()}}</span>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                @isset($stats)
                    <div class="row gx-2 gy-3 mb-3">
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
                <form action="{{route('vendor.orders.list',['status'=>request('status')])}}" id="form-data"
                      method="GET">
                    <div class="row gx-2">
                        <div class="col-12">
                            <h4 class="mb-3 text-capitalize">{{translate('filter_order')}}</h4>
                        </div>
                        @if(request('delivery_man_id'))
                            <input type="hidden" name="delivery_man_id" value="{{ request('delivery_man_id') }}">
                        @endif

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

                        <div class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="form-group">
                                <label class="title-color" for="city_id">{{ translate('city') }}</label>
                                <div class="select-wrapper">
                                    <select class="form-select" name="city_id" id="city_id">
                                        <option value="all">{{ translate('all') }}</option>
                                        @foreach($governorates as $gov)
                                            <option value="{{ $gov->id }}" {{ request('city_id') == $gov->id ? 'selected' : '' }}>{{ $gov->name_ar }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        @if (request('status')=='all' || request('status')=='delivered')
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="form-group">
                                    <label class="title-color" for="filter">{{translate('order_Type')}}</label>
                                    <select name="filter" id="filter" class="form-control select2-selection__arrow">
                                        <option
                                            value="all" {{ $filter == 'all' ? 'selected' : '' }}>{{translate('all')}}</option>
                                        <option
                                            value="default_type" {{ $filter == 'default_type' ? 'selected' : '' }}>{{translate('website_Order')}}</option>
                                        @if(($status == 'all' || $status == 'delivered') && $sellerPos == 1 && !request()->has('deliveryManId'))
                                            <option
                                                value="POS" {{ $filter == 'POS' ? 'selected' : '' }}>{{translate('POS_Order')}}</option>
                                        @endif
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="form-group">
                                <label class="title-color" for="customer">{{translate('customer')}}</label>

                                <input type="hidden" id='customer_id' name="customer_id"
                                       value="{{request('customer_id') ? request('customer_id') : 'all'}}">
                                <select
                                        id="customer_id_value"
                                        data-placeholder="
                                        @if($customer == 'all')
                                            {{translate('all_customer')}}
                                        @else
                                            {{$customer['name'] ?? $customer['f_name'].' '.$customer['l_name'].' '.'('.$customer['phone'].')'}}
                                        @endif"
                                        class="js-data-example-ajax form-control form-ellipsis"
                                >
                                    <option value="all">{{translate('all_customer')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4 col-xl-3">
                            <label class="title-color" for="date_type">{{translate('date_type')}}</label>
                            <div class="form-group">
                                <select class="form-control __form-control" name="date_type" id="date_type">
                                    <option
                                        value="this_year" {{ $dateType == 'this_year'? 'selected' : '' }}>{{translate('this_Year')}}</option>
                                    <option
                                        value="this_month" {{ $dateType == 'this_month'? 'selected' : '' }}>{{translate('this_Month')}}</option>
                                    <option
                                        value="this_week" {{ $dateType == 'this_week'? 'selected' : '' }}>{{translate('this_Week')}}</option>
                                    <option
                                        value="custom_date" {{ $dateType == 'custom_date'? 'selected' : '' }}>{{translate('custom_Date')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4 col-xl-3" id="from_div">
                            <label class="title-color" for="customer">{{translate('start_date')}}</label>
                            <div class="form-group">
                                <input type="date" name="from" value="{{$from}}" id="from_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4 col-xl-3" id="to_div">
                            <label class="title-color" for="customer">{{translate('end_date')}}</label>
                            <div class="form-group">
                                <input type="date" value="{{$to}}" name="to" id="to_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-3 justify-content-end">
                                <a href="{{route('vendor.orders.list',['status'=>request('status')])}}"
                                   class="btn btn-secondary px-5">
                                    {{translate('reset')}}
                                </a>
                                <button type="submit" class="btn btn--primary px-5" id="formUrlChange" data-action="{{ url()->current() }}">
                                    {{translate('show_data')}}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="px-3 py-4 light-bg">
                    <div class="row g-2 align-items-center flex-grow-1">
                        <div class="col-md-4">
                            <h5 class="text-capitalize d-flex gap-1">
                                {{translate('order_list')}}
                                <span class="badge badge-soft-dark radius-50 fs-12">{{$orders->total()}}</span>
                            </h5>
                        </div>
                        <div class="col-md-8 d-flex gap-3 flex-wrap flex-sm-nowrap justify-content-md-end">
                            <form action="{{ url()->current() }}" method="GET">
                                <div class="input-group input-group-merge input-group-custom">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <i class="tio-search"></i>
                                        </div>
                                    </div>
                                    <input id="datatableSearch_" type="search" name="searchValue" class="form-control"
                                           placeholder="{{translate('search_orders')}}" aria-label="Search orders"
                                           value="{{ $searchValue }}" required>
                                    <button type="submit" class="btn btn--primary">{{translate('search')}}</button>
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
                                <button id="apply-bulk-action" type="button" class="btn btn--primary">
                                    {{ translate('apply') }}
                                </button>
                                <button id="print-unprinted" type="button" class="btn btn-outline--primary">
                                    {{ translate('print_unprinted') }}
                                </button>
                                <button id="print-unprinted-by-city" type="button" class="btn btn-outline--primary">
                                    {{ translate('print_unprinted_by_city_distribution') }}
                                </button>
                                <a type="button" class="btn btn-outline--primary text-nowrap" href="{{ route('vendor.orders.export-excel', ['delivery_man_id' => request('delivery_man_id'), 'status' => $status, 'from' => $from, 'to' => $to, 'filter' => $filter, 'searchValue' => $searchValue,'seller_id'=>$vendorId,'customer_id'=>$customerId, 'date_type'=>$dateType, 'city_id' => request('city_id')]) }}">
                                    <img width="14" src="{{dynamicAsset(path: 'public/assets/back-end/img/excel.png')}}" class="excel" alt="">
                                    <span class="ps-2">{{ translate('export') }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="datatable"
                           class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light thead-50 text-capitalize">
                        <tr>
                            <th>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="checkbox" id="select-all-orders">
                                    <label class="mb-0" for="select-all-orders">{{ translate('all') }}</label>
                                </div>
                            </th>
                            <th class="text-capitalize">{{translate('SL')}}</th>
                            <th class="text-capitalize">{{translate('order_ID')}}</th>
                            <th class="text-capitalize">{{translate('order_Date')}}</th>
                            <th class="text-capitalize">{{translate('customer_info')}}</th>
                            <th class="text-capitalize">{{ translate('city') }}</th>
                            <th class="text-capitalize">{{translate('total_amount')}}</th>
                            <th class="text-capitalize">{{ translate('printed') }}</th>
                            @if($status == 'all')
                                <th class="text-capitalize">{{translate('order_Status')}} </th>
                            @else
                                <th class="text-capitalize">{{translate('payment_method')}} </th>
                            @endif
                            <th class="text-center">{{translate('action')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($orders as $key=>$order)
                            <tr>
                                <td>
                                    <input type="checkbox" class="order-select" value="{{ $order['id'] }}">
                                </td>
                                <td>
                                    {{ $orders->firstItem() + $key}}
                                </td>
                                <td>
                                    <a class="title-color hover-c1"
                                       href="{{route('vendor.orders.details',$order['id'])}}">{{$order['id']}} {!! $order->order_type == 'POS' ? '<span class="text--primary">(POS)</span>' : '' !!}</a>
                                </td>
                                <td>
                                    <div>{{date('d M Y',strtotime($order['created_at']))}}</div>
                                    <div>{{date('H:i A',strtotime($order['created_at']))}}</div>
                                </td>
                                <td>
                                    @if($order->is_guest)
                                        <strong class="title-name">{{translate('guest_customer')}}</strong>
                                    @elseif($order->customer_id == 0)
                                        <strong class="title-name">
                                            {{ translate('Walk-In-Customer') }}
                                        </strong>
                                    @else
                                        @if($order->customer)
                                            <span class="text-body text-capitalize" >
                                                <strong class="title-name">
                                                    {{ $order->customer['f_name'].' '.$order->customer['l_name'] }}
                                                </strong>
                                            </span>
                                            @if($order->customer['phone'])
                                                <a class="d-block title-color" href="tel:{{ $order->customer['phone'] }}">{{ $order->customer['phone'] }}</a>
                                            @else
                                                <a class="d-block title-color" href="mailto:{{ $order->customer['email'] }}">{{ $order->customer['email'] }}</a>
                                            @endif
                                        @else
                                            <label class="badge badge-danger fs-12">{{translate('invalid_customer_data')}}</label>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    @php($__city = $order['city_id'] ? $governorates->firstWhere('id', $order['city_id']) : null)
                                    {{ $__city->name_ar ?? '-' }}
                                </td>
                                <td>
                                    <div>
                                        @php($orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order))
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['totalAmount']), currencyCode: getCurrencyCode()) }}
                                    </div>

                                    @if($order->payment_status=='paid')
                                        <span class="badge badge-soft-success">{{translate('paid')}}</span>
                                    @else
                                        <span class="badge badge-soft-danger">{{translate('unpaid')}}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($order->is_printed)
                                        <span class="badge badge-success text-bg-success">{{ translate('yes') }}</span>
                                    @else
                                        <span class="badge badge-secondary bg-secondary">{{ translate('no') }}</span>
                                    @endif
                                </td>
                                @if($status == 'all')
                                    <td class="text-capitalize">
                                        @if($order->order_status=='pending')
                                            <label
                                                class="badge badge-soft-primary">{{$order['order_status']}}</label>
                                        @elseif($order->order_status=='processing' || $order->order_status=='out_for_delivery')
                                            <label
                                                class="badge badge-soft-warning">{{str_replace('_',' ',$order['order_status'] == 'processing' ? 'packaging' : $order['order_status'])}}</label>
                                        @elseif($order->order_status=='delivered' || $order->order_status=='confirmed')
                                            <label
                                                class="badge badge-soft-success">{{$order['order_status']}}</label>
                                        @elseif($order->order_status=='returned')
                                            <label
                                                class="badge badge-soft-danger">{{$order['order_status']}}</label>
                                        @elseif($order['order_status']=='failed')
                                            <span class="badge badge-danger fs-12">
                                                    {{translate('failed_to_deliver')}}
                                            </span>
                                        @else
                                            <label
                                                class="badge badge-soft-danger">{{$order['order_status']}}</label>
                                        @endif
                                    </td>
                                @else
                                    <td class="text-capitalize">
                                        {{str_replace('_',' ',$order['payment_method'])}}
                                    </td>
                                @endif
                                <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a class="btn btn-outline--primary btn-sm square-btn"
                                           title="{{translate('view')}}"
                                           href="{{route('vendor.orders.details',[$order['id']])}}">
                                            <i class="tio-invisible"></i>

                                        </a>
                                        <a class="btn btn-outline-info btn-sm square-btn" target="_blank"
                                           title="{{translate('invoice')}}"
                                           href="{{route('vendor.orders.generate-invoice',[$order['id']])}}">
                                            <i class="tio-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive mt-4">
                    <div class="d-flex justify-content-lg-end">
                        {{$orders->links()}}
                    </div>
                </div>

                @if(count($orders)==0)
                    @include('layouts.vendor.partials._empty-state',['text'=>'no_order_found'],['image'=>'default'])
                @endif
            </div>
        </div>
    </div>

    <span id="message-date-range-text" data-text="{{ translate("invalid_date_range") }}"></span>
    <span id="js-data-example-ajax-url" data-url="{{ route('vendor.orders.customers') }}"></span>
    <span id="bulk-status-url" data-url="{{ route('vendor.orders.bulk-status') }}"></span>
    <span id="bulk-invoices-url" data-url="{{ route('vendor.orders.bulk-invoices') }}"></span>
    <span id="current-order-status" data-status="{{ $status }}"></span>
    
    <!-- Print By City Modal (Vendor) -->
    <div class="modal fade" id="print-by-city-modal" tabindex="-1" aria-labelledby="printByCityLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printByCityLabel">{{ translate('select_city_to_print_unprinted') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="print-city-select">{{ translate('city') }}</label>
                        <div class="select-wrapper">
                            <select id="print-city-select" class="form-select">
                                @foreach(($coverageGovernorates->count() ? $coverageGovernorates : $governorates) as $gov)
                                    <option value="{{ $gov->id }}">{{ $gov->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <small class="text-muted d-block mt-2">{{ translate('only_cities_you_cover_are_listed') }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('close') }}</button>
                    <button type="button" id="confirm-print-by-city" class="btn btn--primary">{{ translate('print_unprinted') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{dynamicAsset(path: 'public/assets/back-end/vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{dynamicAsset(path: 'public/assets/back-end/vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
    <script src="{{dynamicAsset(path: 'public/assets/back-end/js/vendor/order.js')}}"></script>
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
                            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') } });
                            $.post($('#bulk-status-url').data('url'), { ids: ids, status: param }, function () {
                                toastMagic.success('{{ translate('status_updated_successfully') }}');
                                location.reload();
                            }).fail(function () { toastMagic.error('{{ translate('something_went_wrong') }}'); });
                        });
                    } else if (type === 'print') {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = $('#bulk-invoices-url').data('url') + window.location.search;
                        const csrf = document.querySelector('meta[name="_token"]').getAttribute('content');
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden'; csrfInput.name = '_token'; csrfInput.value = csrf; form.appendChild(csrfInput);
                        const statusInput = document.createElement('input');
                        statusInput.type = 'hidden'; statusInput.name = 'status'; statusInput.value = document.getElementById('current-order-status').dataset.status || 'all';
                        form.appendChild(statusInput);
                        if (param === 'selected') {
                            const ids = getSelectedIds();
                            if (ids.length === 0) { toastMagic.warning('{{ translate('please_select_at_least_one_order') }}'); return; }
                            ids.forEach(function (id) { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; form.appendChild(i); });
                        } else if (param === 'all') {
                            const applyTo = document.createElement('input'); applyTo.type = 'hidden'; applyTo.name = 'apply_to'; applyTo.value = 'all'; form.appendChild(applyTo);
                        }
                        document.body.appendChild(form); form.submit();
                    }
                });
            }

            const unprintedBtn = document.getElementById('print-unprinted');
            if (unprintedBtn) {
                unprintedBtn.addEventListener('click', function () {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = $('#bulk-invoices-url').data('url') + window.location.search;
                    const csrf = document.querySelector('meta[name="_token"]').getAttribute('content');
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden'; csrfInput.name = '_token'; csrfInput.value = csrf; form.appendChild(csrfInput);
                    const statusInput = document.createElement('input');
                    statusInput.type = 'hidden'; statusInput.name = 'status'; statusInput.value = document.getElementById('current-order-status').dataset.status || 'all';
                    form.appendChild(statusInput);
                    const applyTo = document.createElement('input'); applyTo.type = 'hidden'; applyTo.name = 'apply_to'; applyTo.value = 'all'; form.appendChild(applyTo);
                    const isPrinted = document.createElement('input'); isPrinted.type = 'hidden'; isPrinted.name = 'is_printed'; isPrinted.value = '0'; form.appendChild(isPrinted);
                    document.body.appendChild(form); form.submit();
                });
            }

            const unprintedByCityBtn = document.getElementById('print-unprinted-by-city');
            if (unprintedByCityBtn) {
                unprintedByCityBtn.addEventListener('click', function () {
                    const modalEl = document.getElementById('print-by-city-modal');
                    if (modalEl) {
                        const modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    }
                });
            }

            const confirmPrintByCityBtn = document.getElementById('confirm-print-by-city');
            if (confirmPrintByCityBtn) {
                confirmPrintByCityBtn.addEventListener('click', function () {
                    const selectedCityId = (document.getElementById('print-city-select') || {}).value;
                    if (!selectedCityId) { return; }
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = $('#bulk-invoices-url').data('url') + window.location.search;
                    const csrf = document.querySelector('meta[name="_token"]').getAttribute('content');
                    const csrfInput = document.createElement('input'); csrfInput.type = 'hidden'; csrfInput.name = '_token'; csrfInput.value = csrf; form.appendChild(csrfInput);
                    const statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status'; statusInput.value = document.getElementById('current-order-status').dataset.status || 'all'; form.appendChild(statusInput);
                    const applyTo = document.createElement('input'); applyTo.type = 'hidden'; applyTo.name = 'apply_to'; applyTo.value = 'all'; form.appendChild(applyTo);
                    const isPrinted = document.createElement('input'); isPrinted.type = 'hidden'; isPrinted.name = 'is_printed'; isPrinted.value = '0'; form.appendChild(isPrinted);
                    const cityId = document.createElement('input'); cityId.type = 'hidden'; cityId.name = 'city_id'; cityId.value = selectedCityId; form.appendChild(cityId);
                    document.body.appendChild(form); form.submit();
                });
            }
        })();
    </script>
@endpush

@php use Illuminate\Support\Str; @endphp
@extends('layouts.admin.app')
@section('title',translate('refund_requests'))

@section('content')
    <div class="content container-fluid">

        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/refund-request.png') }}" alt="">
                {{ translate($status.'_'.'refund_Requests') }}
                <span class="badge text-dark bg-body-secondary fw-semibold rounded-50">{{ $refundList->total() }}</span>
            </h2>
        </div>
        <div class="card">
            <div class="p-3">
                <div class="row justify-content-between align-items-center">
                    <div class="col-12 col-md-4">
                        <form action="{{ url()->current() }}" method="GET">
                            <div class="input-group flex-grow-1 max-w-280">
                                <input id="datatableSearch_" type="search" name="searchValue" class="form-control"
                                       placeholder="{{ translate('search_by_order_id_or_refund_id') }}"
                                       aria-label="Search orders" value="{{ request('searchValue') }}">
                                <div class="input-group-append search-submit">
                                    <button type="submit">
                                        <i class="fi fi-rr-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 mt-3 col-md-8">
                        <div class="d-flex gap-3 justify-content-md-end">
                            <div class="dropdown">
                                <a type="button" class="btn btn-outline-primary text-nowrap"
                                   href="{{route('admin.refund-section.refund.export',['status'=>request('status'),'searchValue'=>request('searchValue'), 'type'=>request('type')]) }}">
                                    <img width="14"
                                         src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/excel.png') }}"
                                         class="excel" alt="">
                                    <span class="ps-2">{{ translate('export') }}</span>
                                </a>
                            </div>
                            <div class="select-wrapper">
                                <select name="" id="" class="form-select"
                                        onchange="location.href='{{ url()->current()  }}?type='+this.value">
                                    <option
                                        value="all" {{ request('type') == 'all' ?'selected':''}}>{{ translate('all') }}</option>
                                    <option
                                        value="admin" {{ request('type')== 'admin' ? 'selected':''}}>{{ translate('inhouse_Requests') }}</option>
                                    <option
                                        value="seller" {{ request('type') == 'seller' ? 'selected':''}}>{{ translate('vendor_Requests') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless">
                    <thead class="text-capitalize">
                    <tr>
                        <th>{{ translate('SL') }}</th>
                        <th class="text-center">{{ translate('refund_ID') }}</th>
                        <th>{{ translate('order_id') }}</th>
                        <th>{{ translate('customer_info') }}</th>
                        <th class="text-end">{{ translate('total_amount') }}</th>
                        <th class="text-center">{{ translate('status') }}</th>
                        <th class="text-center">{{ translate('action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($refundList as $key => $refund)
                        <tr>
                            <td>{{ $refundList->firstItem() + $key }}</td>
                            <td class="text-center">
                                <span class="text-dark">
                                    {{ $refund->id }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.orders.details', ['id' => $refund->order_id]) }}"
                                   class="text-dark hover-primary">
                                    {{ $refund->order_id }}
                                </a>
                            </td>
                            <td>
                                @if ($refund->customer != null)
                                    <div class="d-flex flex-column gap-1">
                                        <a href="{{ route('admin.customer.view', [$refund->customer->id]) }}"
                                           class="text-dark fw-bold hover-primary">
                                            {{ $refund->customer->f_name . ' ' . $refund->customer->l_name }}
                                        </a>
                                        @if ($refund->customer->phone)
                                            <a href="tel:{{ $refund->customer->phone }}"
                                               class="text-dark hover-primary fs-12">
                                                {{ $refund->customer->phone }}
                                            </a>
                                        @else
                                            <a href="mailto:{{ $refund->customer['email'] }}"
                                               class="text-dark hover-primary fs-12">
                                                {{ $refund->customer['email'] }}
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <a href="javascript:" class="text-dark hover-primary">
                                        {{ translate('customer_not_found') }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1 text-end">
                                    <div>
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refund->amount), currencyCode: getCurrencyCode()) }}
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $refund->status == 'approved' ? 'badge-soft-success' : ($refund->status == 'rejected' ? 'badge-soft-danger' : 'badge-soft-warning') }}">
                                    {{ translate($refund->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                @if(\App\Utils\Helpers::module_permission_check('refund_actions'))
                                    @if ($refund->status == 'pending')
                                        <a class="btn btn-outline-success btn-outline-success-dark icon-btn js-approve-refund"
                                           title="{{ translate('approve') }}"
                                           data-id="{{ $refund->id }}"
                                           data-url="{{ route('admin.orders.approve-refund', ['refundId' => $refund->id]) }}">
                                            <i class="fi fi-rr-check"></i>
                                        </a>
                                        <a class="btn btn-outline-danger btn-outline-danger-dark icon-btn js-reject-refund"
                                           title="{{ translate('reject') }}"
                                           data-id="{{ $refund->id }}"
                                           data-url="{{ route('admin.orders.reject-refund', ['refundId' => $refund->id]) }}">
                                            <i class="fi fi-rr-cross"></i>
                                        </a>
                                    @elseif ($refund->status == 'approved')
                                        <a class="btn btn-outline-primary btn-outline-primary-dark icon-btn js-refund-order"
                                           title="{{ translate('refund') }}"
                                           data-id="{{ $refund->id }}"
                                           data-url="{{ route('admin.orders.refund-order', ['refundId' => $refund->id]) }}">
                                            <i class="fi fi-rr-money-bill-transfer"></i>
                                        </a>
                                    @endif
                                @endif
 
 									<!-- Print button for order details -->
 									<a class="btn btn-outline-secondary btn-outline-secondary-dark icon-btn"
 									   title="{{ translate('print_order') }}"
 									   href="{{ route('admin.orders.generate-invoice', ['id' => $refund->order_id]) }}"
 									   target="_blank">
 										<i class="fi fi-rr-print"></i>
 									</a>
 
 								</div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mt-4">
                <div class="px-4 d-flex justify-content-lg-end">
                    {!! $refundList->links() !!}
                </div>
            </div>

            @if(count($refundList) == 0)
                @include('layouts.admin.partials._empty-state',['text'=>'no_refund_request_found'],['image'=>'default'])
            @endif
        </div>
    </div>
@endsection

@push('script')
<script>
    $(document).ready(function() {
        // Handle approve refund
        $(document).on('click', '.js-approve-refund', function(e) {
            e.preventDefault();
            const button = $(this);
            const refundId = button.data('id');
            const url = button.data('url');
            
            Swal.fire({
                title: '{{ translate("are_you_sure") }}?',
                text: '{{ translate("you_want_to_approve_this_refund_request") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ translate("yes_approve_it") }}',
                cancelButtonText: '{{ translate("cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            toastMagic.success(response.message);
                            location.reload();
                        },
                        error: function(xhr) {
                            const response = JSON.parse(xhr.responseText);
                            toastMagic.error(response.error || '{{ translate("something_went_wrong") }}');
                        }
                    });
                }
            });
        });

        // Handle reject refund
        $(document).on('click', '.js-reject-refund', function(e) {
            e.preventDefault();
            const button = $(this);
            const refundId = button.data('id');
            const url = button.data('url');
            
            Swal.fire({
                title: '{{ translate("are_you_sure") }}?',
                text: '{{ translate("you_want_to_reject_this_refund_request") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '{{ translate("yes_reject_it") }}',
                cancelButtonText: '{{ translate("cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            toastMagic.success(response.message);
                            location.reload();
                        },
                        error: function(xhr) {
                            const response = JSON.parse(xhr.responseText);
                            toastMagic.error(response.error || '{{ translate("something_went_wrong") }}');
                        }
                    });
                }
            });
        });

        // Handle refund order
        $(document).on('click', '.js-refund-order', function(e) {
            e.preventDefault();
            const button = $(this);
            const refundId = button.data('id');
            const url = button.data('url');
            
            Swal.fire({
                title: '{{ translate("are_you_sure") }}?',
                text: '{{ translate("you_want_to_process_this_refund") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ translate("yes_refund_it") }}',
                cancelButtonText: '{{ translate("cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            toastMagic.success(response.message);
                            location.reload();
                        },
                        error: function(xhr) {
                            const response = JSON.parse(xhr.responseText);
                            toastMagic.error(response.error || '{{ translate("something_went_wrong") }}');
                        }
                    });
                }
            });
        });
    });
</script>
@endpush

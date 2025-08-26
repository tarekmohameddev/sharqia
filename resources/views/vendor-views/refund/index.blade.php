@php use Illuminate\Support\Str; @endphp
@extends('layouts.vendor.app')

@section('title', translate('refund_list'))

@section('content')
    <div class="content container-fluid">
        <div class="">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div class="">
                    <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                        <img width="20" src="{{dynamicAsset(path: 'public/assets/back-end/img/refund-request-list.png')}}" alt="">
                        {{translate('refund_request_list')}}
                        <span class="badge badge-soft-dark radius-50">{{$refundList->total()}}</span>
                    </h2>
                </div>
                <div>
                    <i class="tio-shopping-cart title-color fz-30"></i>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="p-3">
                <div class="row justify-content-between align-items-center">
                    <div class="col-12 col-md-4">
                        <form action="{{ url()->current() }}" method="GET">
                            <div class="input-group input-group-merge input-group-custom">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="tio-search"></i>
                                    </div>
                                </div>
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                       placeholder="{{translate('search_by_order_id_or_refund_id')}}"
                                       aria-label="Search orders" value="{{ request('searchValue') }}">
                                <button type="submit" class="btn btn--primary">{{translate('search')}}</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 mt-3 col-md-8">
                        <div class="d-flex gap-3 justify-content-md-end">
                            <div class="dropdown">
                                <a type="button" class="btn btn-outline--primary text-nowrap" href="{{route('vendor.refund.export',['status'=>request('status'),'search'=>request('search')])}}">
                                    <img width="14" src="{{dynamicAsset(path: 'public/assets/back-end/img/excel.png')}}" class="excel" alt="">
                                    <span class="ps-2">{{ translate('export') }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive datatable-custom">
                <table
                    class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table text-start">
                    <thead class="thead-light thead-50 text-capitalize">
                    <tr>
                        <th>{{translate('SL')}}</th>
                        <th class="text-center">{{translate('refund_id')}}</th>
                        <th>{{translate('order_ID')}} </th>
                        <th>{{translate('customer_Info')}}</th>
                        <th>{{translate('total_Amount')}}</th>
                        <th class="text-center">{{translate('status')}}</th>
                        <th class="text-center">{{translate('action')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($refundList as $key=>$refund)
                        <tr>
                            <td> {{$refundList->firstItem()+$key}}</td>
                            <td class="text-center">
                                <span class="title-color">
                                    {{$refund['id']}}
                                </span>
                            </td>
                            <td>
                                <a class="title-color hover-c1"
                                   href="{{route('vendor.orders.details',[$refund->order_id])}}">
                                    {{$refund->order_id}}
                                </a>
                            </td>
                            <td>
                                @if ($refund->customer != null)
                                    <div class="d-flex flex-column gap-1">
                                        <a href="javascript:" class="title-color font-weight-bold hover-c1">
                                            {{$refund->customer->f_name. ' '.$refund->customer->l_name}}
                                        </a>
                                        @if($refund->customer->phone)
                                            <a href="tel:{{$refund->customer->phone}}" class="title-color hover-c1 fs-12">{{$refund->customer->phone}}</a>
                                        @else
                                            <a href="mailto:{{$refund->customer['email']}}" class="title-color hover-c1 fs-12">{{$refund->customer['email']}}</a>
                                        @endif
                                    </div>
                                @else
                                    <a href="javascript:" class="title-color hover-c1">
                                        {{translate('customer_not_found')}}
                                    </a>
                                @endif
                            </td>
                            <td>
                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $refund->amount), currencyCode: getCurrencyCode()) }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $refund->status == 'approved' ? 'badge-soft-success' : ($refund->status == 'rejected' ? 'badge-soft-danger' : 'badge-soft-warning') }}">
                                    {{ translate($refund->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    @if ($refund->status == 'pending')
                                        <a class="btn btn-outline--success btn-sm js-approve-refund"
                                           title="{{ translate('approve') }}"
                                           data-id="{{ $refund->id }}"
                                           data-url="{{ route('vendor.orders.approve-refund', ['refundId' => $refund->id]) }}">
                                            <i class="tio-checkmark-circle"></i>
                                        </a>
                                        <a class="btn btn-outline--danger btn-sm js-reject-refund"
                                           title="{{ translate('reject') }}"
                                           data-id="{{ $refund->id }}"
                                           data-url="{{ route('vendor.orders.reject-refund', ['refundId' => $refund->id]) }}">
                                            <i class="tio-clear-circle"></i>
                                        </a>
                                    @elseif ($refund->status == 'approved')
                                        <a class="btn btn-outline--primary btn-sm js-refund-order"
                                           title="{{ translate('refund') }}"
                                           data-id="{{ $refund->id }}"
                                           data-url="{{ route('vendor.orders.refund-order', ['refundId' => $refund->id]) }}">
                                            <i class="tio-money"></i>
                                        </a>
                                    @endif
                                    
                                    <!-- Print button for order details -->
                                    <a class="btn btn-outline--secondary btn-sm"
                                       title="{{ translate('print_order') }}"
                                       href="{{ route('vendor.orders.generate-invoice', ['id' => $refund->order_id]) }}"
                                       target="_blank">
                                        <i class="tio-print"></i>
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
            @if(count($refundList)==0)
                @include('layouts.vendor.partials._empty-state',['text'=>'no_refund_request_found'],['image'=>'default'])
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

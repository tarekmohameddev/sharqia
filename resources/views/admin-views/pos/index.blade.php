@extends('layouts.admin.app')

@section('title', translate('POS'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}" />
@endpush
@section('content')
    <div class="content container-fluid">
        <div class="row mt-2">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <div class="card">
                    <h4 class="p-3 m-0 bg-light">
                        {{ translate('product_Section') }}
                    </h4>

                    <div class="px-3 py-30">
                        <div class="row gy-1">
                            <div class="col-sm-6">
                                <div class="input-group d-flex justify-content-end">
                                    <select name="category" id="category" class="custom-select w-100 action-category-filter" title="select category">
                                        <option value="">{{ translate('all_categories') }}</option>
                                        @foreach ($categories as $item)
                                            <option value="{{ $item->id}}" {{ $categoryId==$item->id?'selected':'' }}>
                                                {{ $item->defaultName }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <form action="" method="get">
                                    <div class="input-group flex-grow-1 position-relative">
                                        <input id="search" autocomplete="off" type="text"
                                               value="{{ $searchValue }}"
                                               name="searchValue" class="form-control search-bar-input"
                                               placeholder="{{ translate('search_by_name_or_sku') }}"
                                               aria-label="Search here">
                                        <div class="input-group-append search-submit">
                                            <button type="submit">
                                                <i class="fi fi-rr-search"></i>
                                            </button>
                                        </div>
                                        <diV class="card pos-search-card w-4 position-absolute z-1 w-100 top-40px">
                                            <div id="pos-search-box"
                                                 class="card-body search-result-box d-none"></div>
                                        </diV>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card-body pt-2 pb-80 overflow-hidden" id="items">
                        @if(count($products) > 0)
                            <div class="pos-item-wrap max-h-100vh-350px">
                                @foreach($products as $product)
                                    @include('admin-views.pos.partials._single-product',['product'=>$product])
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 bg-chat rounded text-center">
                                <div class="py-5">
                                    <img src="http://localhost/Backend-6Valley-eCommerce-CMS/public/assets/back-end/img/empty-product.png" width="64" alt="">
                                    <div class="mx-auto my-3 max-w-353px">
                                        {{ translate('Currently_no_product_available_by_this_name') }}
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                    <div class="table-responsive bottom-absolute-buttons">
                        <div class="px-4 d-flex justify-content-lg-end">
                            {!!$products->withQueryString()->links()!!}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card billing-section-wrap overflow-hidden">
                    <h5 class="p-3 m-0 bg-light">{{ translate('billing_Section') }}</h5>
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2 action-view-all-hold-orders"
                            data-bs-toggle="tooltip" data-bs-title="{{ translate('please_resume_the_order_from_here') }}">
                                {{ translate('view_All_Hold_Orders') }}
                                <span class="total_hold_orders badge text-bg-danger badge-danger rounded-circle fs-10 px-1">
                                    {{ $totalHoldOrder}}
                                </span>
                            </button>
                        </div>

                        <div class="form-group d-flex flex-lg-wrap flex-xl-nowrap gap-2">
                            <?php
                            $userId = 0;
                            if (Illuminate\Support\Str::contains(session('current_user'), 'saved-customer')) {
                                $userId = explode('-', session('current_user'))[2];
                            }
                            ?>
                            <select id='customer' name="customer_id" data-placeholder="Walk-In-Customer" class="js-example-matcher form-control form-ellipsis action-customer-change">
                                <option value="0" {{ $userId == 0 ? 'selected':'' }}>
                                    {{ translate('Walk-In-Customer') }}
                                </option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ $userId == $customer->id ? 'selected':'' }}>
                                        {{ $customer->f_name }} {{ $customer->l_name }}
                                        ({{ env('APP_MODE') != 'demo' ? $customer->phone : '+88017'.rand(111, 999).'XXXXX' }})
                                    </option>
                                @endforeach
                            </select>

                            <button class="btn btn-success rounded text-nowrap" id="add_new_customer" type="button" title="{{ translate('add_new_customer') }}">
                                {{ translate('add_New_Customer') }}
                            </button>

                            <button class="btn btn-primary rounded text-nowrap d-none" id="add_new_address" type="button" title="{{ translate('add_new_address') }}">
                                {{ translate('add_new_address') }}
                            </button>
                        </div>

                        <div id="add-customer-card" class="border rounded p-3 mt-3 d-none">
                            <form id="customer_form">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('first_name') }} <span class="input-label-secondary text-danger">*</span></label>
                                            <input type="text" name="f_name" class="form-control" placeholder="{{ translate('first_name') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('phone') }} <span class="input-label-secondary text-danger">*</span></label>
                                            <input class="form-control" type="tel" name="phone" placeholder="{{ translate('enter_phone_number') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('city') }} <span class="input-label-secondary text-danger">*</span></label>
                                            <select name="city_id" id="customer_city_id" class="custom-select" required>
                                                <option value="">{{ translate('select') }}</option>
                                                @foreach($governorates as $governorate)
                                                    <option value="{{ $governorate->id }}">{{ $governorate->name_ar }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('seller') }} <span class="input-label-secondary text-danger">*</span></label>
                                            <select name="seller_id" id="customer_seller_id" class="custom-select" required></select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('address') }}</label>
                                            <input type="text" name="address" class="form-control" placeholder="{{ translate('address') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="submit" id="submit_new_customer" class="btn btn-primary">{{ translate('submit') }}</button>
                                </div>
                            </form>
                        </div>

                        <div id="add-address-card" class="border rounded p-3 mt-3 d-none">
                            <form id="customer_address_form">
                                @csrf
                                <input type="hidden" name="customer_id" id="address_customer_id">
                                <div class="row g-3">
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('city') }} <span class="input-label-secondary text-danger">*</span></label>
                                            <select name="city_id" id="address_city_id" class="custom-select" required>
                                                <option value="">{{ translate('select') }}</option>
                                                @foreach($governorates as $governorate)
                                                    <option value="{{ $governorate->id }}">{{ $governorate->name_ar }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('seller') }} <span class="input-label-secondary text-danger">*</span></label>
                                            <select name="seller_id" id="address_seller_id" class="custom-select" required></select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('address') }}</label>
                                            <input type="text" name="address" class="form-control" placeholder="{{ translate('address') }}" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="submit" class="btn btn-primary">{{ translate('submit') }}</button>
                                </div>
                            </form>
                        </div>

                        <div id="cart-summary">
                            <table class="table table-align-middle m-0" id="cart-table">
                                <thead class="text-capitalize bg-light">
                                <tr>
                                    <th class="border-0">{{ translate('item') }}</th>
                                    <th class="border-0">{{ translate('qty') }}</th>
                                    <th class="border-0">{{ translate('price') }}</th>
                                    <th class="border-0 text-center">{{ translate('delete') }}</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>

                            <div class="pt-4">
                                <div class="d-flex justify-content-between">
                                    <span>{{ translate('sub_total') }} :</span>
                                    <span id="cart-subtotal">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>{{ translate('total') }} :</span>
                                    <span id="cart-total">0</span>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div>{{ translate('paid_By') }}:</div>
                                <div class="d-flex gap-3">
                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="type" value="cash" checked>
                                        <span class="form-check-label">{{ translate('cash') }}</span>
                                    </label>
                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="type" value="card">
                                        <span class="form-check-label">{{ translate('card') }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="d-flex gap-3 align-items-center pt-3">
                                <button class="btn btn-danger" id="cancel-order">{{ translate('cancel_Order') }}</button>
                                <button class="btn btn-primary" id="place-order">{{ translate('place_Order') }}</button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>


<div class="modal fade pt-5" id="quick-view" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" id="quick-view-modal"></div>
    </div>
</div>

<button class="d-none" id="hold-orders-modal-btn" type="button" data-bs-toggle="modal" data-bs-target="#hold-orders-modal">
</button>

@if($order)
@include('admin-views.pos.partials.modals._print-invoice')
@endif

@include('admin-views.pos.partials.modals._hold-orders-modal')
@include('admin-views.pos.partials.modals._add-coupon-discount')
@include('admin-views.pos.partials.modals._add-discount')
@include('admin-views.pos.partials.modals._short-cut-keys')

<span id="route-admin-products-search-product" data-url="{{ route('admin.pos.search-product') }}"></span>
<span id="route-admin-pos-place-order" data-url="{{ route('admin.pos.place-order') }}"></span>

@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/libs/printThis/printThis.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/pos-script.js') }}"></script>
    <script>
        "use strict";
        document.addEventListener('DOMContentLoaded', function () {
            @if($order)
            const modalElement = document.getElementById('print-invoice');
            if (modalElement) {
                const modalInstance = new bootstrap.Modal(modalElement);
                modalInstance.show();
            }
            @endif
        });
    </script>

    <script>
        let popupHideTimeout;
        let trackingInterval;

        $(document).on('mouseenter', '.table-items', function () {
            const $popup = $(this).find('.table-items-popup');
            const $item = $(this)[0];

            $('.table-items-popup').not($popup).removeClass('show');
            clearTimeout(popupHideTimeout);
            $popup.addClass('show');

            const updatePopupPosition = () => {
                const rect = $item.getBoundingClientRect();
                $popup.css({
                    top: rect.top + rect.height + 5 + 'px',
                    left: rect.left + (rect.width / 2) - ($popup.outerWidth() / 2) + 'px'
                });
            };

            updatePopupPosition();

            trackingInterval = setInterval(updatePopupPosition, 30);
        });

        $(document).on('mouseleave', '.table-items', function () {
            const $popup = $(this).find('.table-items-popup');
            popupHideTimeout = setTimeout(() => {
                $popup.removeClass('show');
                clearInterval(trackingInterval);
            }, 100);
        });

        $(document).on('mouseenter', '.table-items-popup', function () {
            clearTimeout(popupHideTimeout);
        });

        $(document).on('mouseleave', '.table-items-popup', function () {
            const $popup = $(this);
            popupHideTimeout = setTimeout(() => {
                $popup.removeClass('show');
                clearInterval(trackingInterval);
            }, 100);
        });
    </script>

@endpush

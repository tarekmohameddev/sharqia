@extends('layouts.admin.app')

@section('title', translate('POS'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <style>
        .pos-product-item {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .pos-product-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .pos-product-item_content {
            padding: 1rem;
        }
        
        .pos-product-item_title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            line-height: 1.3;
            min-height: 2.6rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .pos-product-item_price {
            font-weight: 700;
            color: #0d6efd;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .pos-product-item_stock {
            margin-bottom: 0.5rem;
            min-height: 1.2rem;
        }
        
        .pos-product-item_actions {
            margin-top: auto;
        }
        
        .pos-product-item_hover-content {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: inherit;
        }
        
        .pos-product-item:hover .pos-product-item_hover-content {
            opacity: 1;
        }
        
        .action-direct-add-to-cart {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            font-weight: 500;
        }
        
        .action-direct-add-to-cart:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .pos-product-item_thumb {
            position: relative;
            overflow: hidden;
        }
        
        .pos-product-item_content {
            display: flex;
            flex-direction: column;
            height: calc(100% - 200px); /* Adjust based on image height */
        }
        
        .client-cart-quantity {
            width: 70px !important;
            padding: 0.25rem 0.5rem;
            text-align: center;
        }
        
        /* Make product cards consistent height */
        .pos-product-item {
            height: 320px;
            display: flex;
            flex-direction: column;
        }
        
        .pos-product-item_thumb {
            height: 180px;
            flex-shrink: 0;
        }
        
        .pos-product-item_content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        /* Loading state for add to cart buttons */
        .action-direct-add-to-cart.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .action-direct-add-to-cart.loading::after {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid transparent;
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 5px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Horizontal Product Card Styles */
        .pos-item-wrap-horizontal {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-block-size: 61vh;
            overflow-y: auto;
            padding: 10px;
        }

        .pos-product-item-horizontal {
            cursor: pointer;
            overflow: visible;
            border-radius: 8px !important;
            position: relative;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e0e0e0;
            min-height: auto;
            width: 100%;
            display: flex;
            align-items: flex-start;
        }

        .pos-product-item-horizontal:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .pos-product-item_thumb-horizontal {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border-radius: 6px;
            overflow: hidden;
            margin-right: 15px;
        }

        .pos-product-item_thumb-horizontal img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pos-product-item_content-horizontal {
            padding: 0 15px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 6px;
            flex: 1;
            min-width: 0;
        }

        .pos-product-item_title-horizontal {
            font-weight: 600;
            font-size: 1rem;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.3;
        }

        .pos-product-item_price-horizontal {
            font-weight: 700;
            color: #0d6efd;
            font-size: 1.1rem;
        }

        /* Desktop Layout - Hide mobile add to cart */
        .add-to-cart-mobile {
            display: none;
        }

        .pos-product-item_actions-horizontal {
            display: flex;
            align-items: flex-start;
            min-width: 140px;
            max-width: 160px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .pos-product-item_actions-horizontal .btn {
            font-size: 0.75rem;
            padding: 0.5rem 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            min-height: 36px;
            width: 100%;
        }

        .pos-product-item_actions-horizontal .btn i {
            font-size: 0.7rem;
        }

        /* Offers Section Styles */
        .offers-section {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            width: 100%;
        }

        .offer-btn {
            font-size: 0.65rem !important;
            padding: 0.3rem 0.5rem !important;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 28px;
            flex-shrink: 0;
            max-width: fit-content;
        }

        .offer-btn i {
            font-size: 0.6rem;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .pos-product-item-horizontal {
                flex-direction: column;
                align-items: center;
                padding: 15px;
                min-height: auto;
            }

            .pos-product-item_thumb-horizontal {
                width: 80px;
                height: 80px;
                align-self: center;
                margin-right: 0;
                margin-bottom: 15px;
            }

            .pos-product-item_content-horizontal {
                padding: 0;
                text-align: center;
                margin-bottom: 0;
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            /* Hide desktop add to cart button, show mobile one */
            .pos-product-item_actions-horizontal {
                display: none;
            }

            .add-to-cart-mobile {
                display: flex;
                justify-content: center;
                margin-top: 15px;
                width: 100%;
            }

            .add-to-cart-mobile .btn {
                font-size: 0.8rem;
                padding: 0.5rem 1.5rem;
                min-height: 40px;
                width: auto;
                min-width: 200px;
                max-width: 280px;
            }

            .offers-section {
                justify-content: center;
                gap: 8px;
                margin-top: 12px;
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                max-width: 320px;
            }

            .offer-btn {
                font-size: 0.7rem !important;
                padding: 0.4rem 0.6rem !important;
                min-height: 32px;
                width: 100%;
                max-width: none;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .pos-product-item-horizontal {
                margin-bottom: 10px;
                padding: 12px;
            }

            .pos-product-item_thumb-horizontal {
                width: 70px;
                height: 70px;
                margin-bottom: 12px;
            }

            .pos-product-item_title-horizontal {
                font-size: 0.9rem;
                margin-bottom: 6px;
            }

            .pos-product-item_price-horizontal {
                font-size: 1rem;
                margin-bottom: 8px;
            }

            .add-to-cart-mobile .btn {
                font-size: 0.75rem;
                padding: 0.4rem 1.2rem;
                min-height: 36px;
                width: auto;
                min-width: 180px;
                max-width: 250px;
            }

            .offers-section {
                gap: 6px;
                margin-top: 10px;
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                max-width: 280px;
            }

            .offer-btn {
                font-size: 0.6rem !important;
                padding: 0.3rem 0.5rem !important;
                min-height: 28px;
                width: 100%;
                max-width: none;
                flex: none;
                text-align: center;
            }

            .offer-btn i {
                font-size: 0.55rem;
            }

            /* If there are 3 or more offers, use single column on very small screens */
            .offers-section:has(.offer-btn:nth-child(3)) {
                grid-template-columns: 1fr;
                max-width: 200px;
            }
        }

        /* Hide product images in Product section */
        .pos-product-item_thumb-horizontal,
        .pos-product-item_thumb,
        .pos-product-item_thumb-horizontal img,
        .pos-product-item_thumb img {
            display: none !important;
        }

        /* Adjust layout when images are hidden */
        .pos-product-item_content-horizontal {
            padding: 0;
        }
        .pos-product-item_content {
            height: auto;
        }
        .pos-product-item {
            height: auto;
        }
    </style>
@endpush
@section('content')
    <div class="content container-fluid">
        <div class="row mt-2">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <div class="card">
                    <h4 class="p-3 m-0 bg-light">
                        {{ translate('product_Section') }}
                    </h4>

                    <div class="px-3 py-30 d-none">
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
                            <div class="pos-item-wrap-horizontal max-h-100vh-350px">
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
                        <div class="d-flex justify-content-end mb-3 d-none">
                            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2 action-view-all-hold-orders"
                            data-bs-toggle="tooltip" data-bs-title="{{ translate('please_resume_the_order_from_here') }}">
                                {{ translate('view_All_Hold_Orders') }}
                                <span class="total_hold_orders badge text-bg-danger badge-danger rounded-circle fs-10 px-1">
                                    {{ $totalHoldOrder}}
                                </span>
                            </button>
                        </div>

                        <div class="form-group d-flex flex-lg-wrap flex-xl-nowrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="text-dark mb-0">{{ translate('customer_Information') }}</h5>
                                <small class="text-muted">({{ translate('required') }})</small>
                            </div>
                        </div>

                        <div id="customer-info-card" class="border rounded p-3 mb-3">
                            <form id="customer_info_form">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('first_name') }}</label>
                                            <input type="text" name="f_name" id="customer_f_name" class="form-control" placeholder="{{ translate('first_name') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('phone') }} <span class="input-label-secondary text-danger">*</span></label>
                                            <input class="form-control" type="tel" name="phone" id="customer_phone" placeholder="{{ translate('enter_phone_number') }}" required data-no-intl="true" maxlength="11" pattern="0(10|11|12|15)[0-9]{8}" title="{{ translate('egyptian_mobile_input_title') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('alternative_phone') }}</label>
                                            <input class="form-control" type="tel" name="alternative_phone" id="customer_alt_phone" placeholder="{{ translate('enter_phone_number') }}" data-no-intl="true" maxlength="11" pattern="0(10|11|12|15)[0-9]{8}" title="{{ translate('egyptian_mobile_input_title') }}">
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
                                            <textarea type="text" name="address" id="customer_address" rows="3" class="form-control" placeholder="{{ translate('address') }}"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label mb-1">{{ translate('order_note') }}</label>
                                            <textarea class="form-control" id="order_note" name="order_note" rows="1" placeholder="{{ translate('add_any_special_instructions_or_notes_for_this_order') }}"></textarea>
                                            
                                        </div>
                                    </div>

                                </div>
                            </form>
                        </div>

                        <!-- Keep existing customer selection for backward compatibility (hidden by default) -->
                        <div class="form-group d-none" id="legacy-customer-selection">
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
                        </div>

                        <!-- Keep the old add customer forms hidden for compatibility -->
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
                                            <select name="city_id" id="add_customer_city_id" class="custom-select" required>
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
                                            <select name="seller_id" id="add_customer_seller_id" class="custom-select" required></select>
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
                            @include('admin-views.pos.partials._cart-summary')
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

<span id="route-admin-pos-get-cart-ids" data-url="{{ route('admin.pos.get-cart-ids') }}"></span>
<span id="route-admin-pos-new-cart-id" data-url="{{ route('admin.pos.new-cart-id') }}"></span>
<span id="route-admin-pos-clear-cart-ids" data-url="{{ route('admin.pos.clear-cart-ids') }}"></span>
<span id="route-admin-pos-view-hold-orders" data-url="{{ route('admin.pos.view-hold-orders') }}"></span>
<span id="route-admin-products-search-product" data-url="{{ route('admin.pos.search-product') }}"></span>
<span id="route-admin-pos-change-customer" data-url="{{ route('admin.pos.change-customer') }}"></span>
<span id="route-admin-pos-update-discount" data-url="{{ route('admin.pos.update-discount') }}"></span>
<span id="route-admin-pos-coupon-discount" data-url="{{ route('admin.pos.coupon-discount') }}"></span>
<span id="route-admin-pos-cancel-order" data-url="{{ route('admin.pos.cancel-order') }}"></span>
<span id="route-admin-pos-quick-view" data-url="{{ route('admin.pos.quick-view') }}"></span>
<span id="route-admin-pos-add-to-cart" data-url="{{ route('admin.pos.add-to-cart') }}"></span>
<span id="route-admin-pos-remove-cart" data-url="{{ route('admin.pos.remove-cart') }}"></span>
<span id="route-admin-pos-empty-cart" data-url="{{ route('admin.pos.empty-cart') }}"></span>
<span id="route-admin-pos-update-quantity" data-url="{{ route('admin.pos.update-quantity') }}"></span>
<span id="route-admin-pos-get-variant-price" data-url="{{ route('admin.pos.get-variant-price') }}"></span>
<span id="route-admin-pos-change-cart-editable" data-url="{{ route('admin.pos.change-cart').'/?cart_id=:value' }}"></span>
<span id="route-admin-pos-get-sellers" data-url="{{ route('admin.pos.get-sellers') }}"></span>
<span id="discount-permission" data-permission="{{ \App\Utils\Helpers::module_permission_check('discount') ? 'true' : 'false' }}"></span>
<span id="route-admin-pos-set-shipping" data-url="{{ route('admin.pos.set-shipping') }}"></span>
<span id="route-admin-orders-list" data-url="{{ route('admin.orders.list', ['all']) }}"></span>
<span id="route-admin-customer-add" data-url="{{ route('admin.customer.add') }}"></span>
<span id="route-admin-customer-address-add" data-url="{{ route('admin.customer.address-add') }}"></span>

@if(isset($editOrder) && $editOrder)
    <span id="edit-order-id" data-id="{{ $editOrder->id }}"></span>
    <script id="edit-order-payload" type="application/json">{!! json_encode($editPayload, JSON_UNESCAPED_UNICODE) !!}</script>
    <span id="route-admin-orders-update" data-url="{{ route('admin.orders.update', ['id' => $editOrder->id]) }}"></span>
@endif

<span id="message-cart-word" data-text="{{ translate('cart') }}"></span>
<span id="message-stock-out" data-text="{{ translate('stock_out') }}"></span>
<span id="message-stock-id" data-text="{{ translate('in_stock') }}"></span>
<span id="message-add-to-cart" data-text="{{ translate('add_to_cart') }}"></span>
<span id="message-cart-updated" data-text="{{ translate('cart_updated') }}"></span>
<span id="message-update-to-cart" data-text="{{ translate('update_to_cart') }}"></span>
<span id="message-cart-is-empty" data-text="{{ translate('cart_is_empty') }}"></span>
<span id="message-enter-valid-amount" data-text="{{ translate('please_enter_a_valid_amount') }}"></span>
<span id="message-less-than-total-amount" data-text="{{ translate('paid_amount_is_less_than_total_amount') }}"></span>
<span id="message-coupon-is-invalid" data-text="{{ translate('coupon_is_invalid') }}"></span>
<span id="message-product-quantity-updated" data-text="{{ translate('product_quantity_updated') }}"></span>
<span id="message-coupon-added-successfully" data-text="{{ translate('coupon_added_successfully') }}"></span>
<span id="message-sorry-stock-limit-exceeded" data-text="{{ translate('sorry_stock_limit_exceeded') }}"></span>
<span id="message-please-choose-all-the-options" data-text="{{ translate('please_choose_all_the_options') }}"></span>
<span id="message-item-has-been-removed-from-cart" data-text="{{ translate('item_has_been_removed_from_cart') }}"></span>
<span id="message-you-want-to-remove-all-items-from-cart" data-text="{{ translate('you_want_to_remove_all_items_from_cart') }}"></span>
<span id="message-you-want-to-create-new-order" data-text="{{ translate('Want_to_create_new_order_for_another_customer') }}"></span>
<span id="message-product-quantity-is-not-enough" data-text="{{ translate('product_quantity_is_not_enough') }}"></span>
<span id="message-sorry-product-is-out-of-stock" data-text="{{ translate('sorry_product_is_out_of_stock') }}"></span>
<span id="message-item-has-been-added-in-your-cart" data-text="{{ translate('item_has_been_added_in_your_cart') }}"></span>
<span id="message-extra-discount-added-successfully" data-text="{{ translate('extra_discount_added_successfully') }}"></span>
<span id="message-amount-can-not-be-negative-or-zero" data-text="{{ translate('amount_can_not_be_negative_or_zero') }}"></span>
<span id="message-sorry-the-minimum-value-was-reached" data-text="{{ translate('sorry_the_minimum_value_was_reached') }}"></span>
<span id="message-this-discount-is-not-applied-for-this-amount" data-text="{{ translate('this_discount_is_not_applied_for_this_amount') }}"></span>
<span id="message-please-add-product-in-cart-before-applying-discount" data-text="{{ translate('please_add_product_to_cart_before_applying_discount') }}"></span>
<span id="message-please-add-product-in-cart-before-applying-coupon" data-text="{{ translate('please_add_product_to_cart_before_applying_coupon') }}"></span>
<span id="message-product-quantity-cannot-be-zero-in-cart" data-text="{{ translate('product_quantity_can_not_be_zero_or_less_than_zero_in_cart') }}"></span>

<!-- Translation elements for client-side cart -->
<span id="get-currency-symbol" data-symbol="{{ getCurrencySymbol() }}"></span>
<span id="get-currency-position" data-position="{{ getWebConfig('currency_symbol_position') }}"></span>
<span id="translate-item" data-text="{{ translate('item') }}"></span>
<span id="translate-qty" data-text="{{ translate('qty') }}"></span>
<span id="translate-price" data-text="{{ translate('price') }}"></span>
<span id="translate-delete" data-text="{{ translate('delete') }}"></span>
<span id="translate-subtotal" data-text="{{ translate('sub_total') }}"></span>
<span id="translate-product-discount" data-text="{{ translate('product_Discount') }}"></span>
<span id="translate-extra-discount" data-text="{{ translate('extra_Discount') }}"></span>
<span id="translate-coupon-discount" data-text="{{ translate('coupon_Discount') }}"></span>
<span id="translate-tax" data-text="{{ translate('tax') }}"></span>
<span id="translate-shipping-cost" data-text="{{ translate('shipping_cost') }}"></span>
<span id="translate-total" data-text="{{ translate('total') }}"></span>
<span id="session-shipping-cost" data-value="{{ session('selected_shipping_cost', 0) }}"></span>
<span id="translate-paid-by" data-text="{{ translate('paid_By') }}"></span>
<span id="translate-cash" data-text="{{ translate('cash') }}"></span>
<span id="translate-card" data-text="{{ translate('card') }}"></span>
<span id="translate-wallet" data-text="{{ translate('wallet') }}"></span>
<span id="translate-paid-amount" data-text="{{ translate('Paid_Amount') }}"></span>
<span id="translate-change-amount" data-text="{{ translate('Change_Amount') }}"></span>
<span id="translate-cancel-order" data-text="{{ translate('cancel_Order') }}"></span>
<span id="translate-place-order" data-text="{{ translate('place_Order') }}"></span>
<span id="translate-want-to-place-order" data-text="{{ translate('want_to_place_this_order').'?' }}"></span>
<span id="route-admin-pos-place-order-direct" data-url="{{ route('admin.pos.place-order') }}"></span>
<span id="message-order-created" data-text="{{ translate('order_placed_successfully') }}"></span>
<span id="message-order-updated" data-text="{{ translate('order_updated_successfully') }}"></span>

@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/libs/printThis/printThis.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/pos-script.js') }}"></script>
    <script>
        "use strict";
        window.CATEGORY_RULES_MAP = @json($categoryRulesMap ?? []);
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

    <!-- Translations for JS validation -->
    <span id="message-please-enter-customer-phone" data-text="{{ translate('please_enter_customer_phone_number') }}"></span>
    <span id="message-valid-egyptian-mobile" data-text="{{ translate('enter_valid_egyptian_mobile_number') }}"></span>
    <span id="message-valid-egyptian-alt-mobile" data-text="{{ translate('enter_valid_egyptian_alternative_mobile_number') }}"></span>

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

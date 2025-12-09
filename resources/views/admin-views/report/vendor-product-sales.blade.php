@extends('layouts.admin.app')
@section('title', translate('vendor_products_sales'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex gap-2 align-items-center">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/seller_sale.png')}}" alt="">
                {{translate('vendor_products_sales')}}
            </h2>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form action="" id="form-data" method="GET">
                    <h3 class="mb-3">{{translate('filter_Data')}}</h3>
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">{{ translate('select_Seller')}}</label>
                            <select class="custom-select" name="seller_id">
                                <option class="text-center" value="all" {{ ($sellerId ?? 'all') == 'all' ? 'selected' : '' }}>
                                    {{translate('all')}}
                                </option>
                                @foreach($sellers as $seller)
                                    <option value="{{$seller['id'] }}" {{($sellerId ?? 'all')==$seller['id']?'selected':''}}>
                                        {{$seller['f_name'] }} {{$seller['l_name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">{{ translate('select_Date')}}</label>
                            <div class="select-wrapper">
                                <select class="form-select" name="date_type" id="date_type">
                                    <option value="this_year" {{ ($dateType ?? 'this_year') == 'this_year'? 'selected' : '' }}>{{translate('this_Year')}}</option>
                                    <option value="this_month" {{ ($dateType ?? 'this_year') == 'this_month'? 'selected' : '' }}>{{translate('this_Month')}}</option>
                                    <option value="this_week" {{ ($dateType ?? 'this_year') == 'this_week'? 'selected' : '' }}>{{translate('this_Week')}}</option>
                                    <option value="today" {{ ($dateType ?? 'this_year') == 'today'? 'selected' : '' }}>{{translate('today')}}</option>
                                    <option value="custom_date" {{ ($dateType ?? 'this_year') == 'custom_date'? 'selected' : '' }}>{{translate('custom_Date')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3" style="background-color: #fff3cd; padding: 10px; border-radius: 5px;">
                            <label class="mb-2" for="date_field">
                                {{ translate('date_field_type') }}
                                <i class="fi fi-rr-info" data-bs-toggle="tooltip" data-bs-placement="top" 
                                   data-bs-title="{{ translate('Choose which order date to filter by: Last update date (updated_at - original/default) or Order date (created_at - new option)') }}" 
                                   style="font-size: 11px; cursor: help; color: #6c757d;"></i>
                            </label>
                            <div class="select-wrapper">
                                <select class="form-select" name="date_field" id="date_field">
                                    <option value="updated_at" {{ (request('date_field', 'updated_at') == 'updated_at') ? 'selected' : '' }}>
                                        {{ translate('last_update_date') }} (updated_at - {{ translate('original') }}/{{ translate('default') }})
                                    </option>
                                    <option value="created_at" {{ request('date_field') == 'created_at' ? 'selected' : '' }}>
                                        {{ translate('order_date') }} (created_at - {{ translate('new') }})
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3" id="from_div">
                            <div>
                                <label class="mb-2">{{ ucwords(translate('start_date'))}}</label>
                                <input type="date" name="from" value="{{$from}}" id="from_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3" id="to_div">
                            <div>
                                <label class="mb-2">{{ ucwords(translate('end_date'))}}</label>
                                <input type="date" value="{{$to}}" name="to" id="to_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">{{ translate('search') }}</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="{{translate('search_product_name')}}" value="{{$search}}">
                                <div class="input-group-append search-submit">
                                    <button type="submit">
                                        <i class="fi fi-rr-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 filter-btn">
                            <button type="submit" class="btn btn-primary">
                                {{translate('filter')}}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Component Selection Checkboxes --}}
        <div class="mb-3 p-3 rounded" style="background-color: #fff3cd;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <strong class="d-flex align-items-center gap-2">
                        {{ translate('customize_total') }}:
                        <i class="fi fi-rr-info" data-bs-toggle="tooltip" data-bs-placement="top" 
                           data-bs-title="{{ translate('Select which components to include in Custom Total calculation') }}" 
                           style="font-size: 12px; cursor: help; color: #6c757d;"></i>
                    </strong>
                </div>
                <div class="d-flex gap-3 flex-wrap">
                    <label class="d-flex align-items-center gap-1 mb-0" style="cursor: pointer;">
                        <input type="checkbox" name="include_products" value="1" class="component-checkbox" 
                               {{ $includeProducts ? 'checked' : '' }}>
                        <span>{{ translate('products') }}</span>
                    </label>
                    <label class="d-flex align-items-center gap-1 mb-0" style="cursor: pointer;">
                        <input type="checkbox" name="include_shipping" value="1" class="component-checkbox" 
                               {{ $includeShipping ? 'checked' : '' }}>
                        <span>{{ translate('shipping') }}</span>
                    </label>
                    <label class="d-flex align-items-center gap-1 mb-0" style="cursor: pointer;">
                        <input type="checkbox" name="include_discounts" value="1" class="component-checkbox" 
                               {{ $includeDiscounts ? 'checked' : '' }}>
                        <span>{{ translate('discounts') }} (-)</span>
                    </label>
                    <label class="d-flex align-items-center gap-1 mb-0" style="cursor: pointer;">
                        <input type="checkbox" name="include_delivery" value="1" class="component-checkbox" 
                               {{ $includeDelivery ? 'checked' : '' }}>
                        <span>{{ translate('delivery_fees') }}</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-3">
            <div class="d-flex flex-column gap-3 flex-grow-1">
                <div class="card card-body">
                    <div class="d-flex gap-3 align-items-center">
                        <img width="35" src="{{ dynamicAsset(path: 'public/assets/back-end/img/products.svg')}}" alt="">
                        <div class="info">
                            <h4 class="subtitle h1">{{ $totalProductSale }}</h4>
                            <h5 class="subtext">{{translate('total_Quantity_Sold')}}</h5>
                        </div>
                    </div>
                </div>
                <div class="card card-body">
                    <div class="d-flex gap-3 align-items-center">
                        <img width="35" src="{{ dynamicAsset(path: 'public/assets/back-end/img/stores.svg')}}'" alt="">
                        <div class="info">
                            <h4 class="subtitle h1">
                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $totalProductSaleAmount), currencyCode: getCurrencyCode()) }}
                            </h4>
                            <h5 class="subtext d-flex align-items-center gap-2">
                                {{translate('product_sales_total')}}
                                <i class="fi fi-rr-info" data-bs-toggle="tooltip" data-bs-placement="top" 
                                   data-bs-title="{{ translate('Product prices only (Quantity × Price). Excludes shipping, taxes, and order-level fees.') }}" 
                                   style="font-size: 14px; cursor: help; color: #6c757d;"></i>
                            </h5>
                        </div>
                    </div>
                </div>
                <div class="card card-body" style="background-color: #fff3cd;">
                    <div class="d-flex gap-3 align-items-center">
                        <img width="35" src="{{ dynamicAsset(path: 'public/assets/back-end/img/earn.svg')}}'" alt="">
                        <div class="info">
                            <h4 class="subtitle h1">
                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $customTotal ?? 0), currencyCode: getCurrencyCode()) }}
                            </h4>
                            <h5 class="subtext d-flex align-items-center gap-2">
                                {{translate('custom_total')}}
                                <i class="fi fi-rr-info" data-bs-toggle="tooltip" data-bs-placement="top" 
                                   data-bs-title="{{ translate('Customizable total based on selected components') }}" 
                                   style="font-size: 14px; cursor: help; color: #6c757d;"></i>
                            </h5>
                        </div>
                    </div>
                </div>
                <div class="card card-body">
                    <div class="d-flex gap-3 align-items-center">
                        <img width="35" src="{{ dynamicAsset(path: 'public/assets/back-end/img/stats.svg')}}" alt="">
                        <div class="info">
                            <h4 class="subtitle h1">
                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $totalDiscountGiven), currencyCode: getCurrencyCode()) }}
                            </h4>
                            <h5 class="subtext">{{translate('total_Discount_Given')}}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                    <h4 class="mb-0 mr-auto">
                        {{translate('products')}}
                        <span class="badge badge-info text-bg-info"> {{ $products->total() }}</span>
                    </h4>
                    <div class="d-flex gap-3 flex-wrap">
                        <form action="" method="GET">
                            <div class="form-group">
                                <div class="input-group">
                                    <input type="hidden" name="seller_id" value="{{ $sellerId }}">
                                    <input type="hidden" name="date_type" value="{{ $dateType }}">
                                    <input type="hidden" name="from" value="{{ $from }}">
                                    <input type="hidden" name="to" value="{{ $to }}">
                                    <input id="datatableSearch_" type="search" name="search" class="form-control min-w-300" placeholder="{{translate('search_product_name')}}" value="{{ $search }}">
                                    <div class="input-group-append search-submit">
                                        <button type="submit">
                                            <i class="fi fi-rr-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div class="dropdown">
                            <a type="button" class="btn btn-outline-primary text-nowrap" href="{{ route('admin.report.vendor-product-sales-export', ['seller_id' => request('seller_id'), 'search' => request('search'), 'date_type' => request('date_type'), 'date_field' => request('date_field', 'updated_at'), 'from' => request('from'), 'to' => request('to')]) }}">
                                <img width="14" src="{{ dynamicAsset(path: 'public/assets/back-end/img/excel.png')}}" class="excel" alt="">
                                <span class="ps-2">{{ translate('export') }}</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover __table table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100 {{Session::get('direction') === "rtl" ? 'text-right' : 'text-left'}}">
                        <thead class="thead-light thead-50 text-capitalize">
                        <tr>
                            <th>{{translate('SL')}}</th>
                            <th>{{translate('product_Name')}}</th>
                            <th>{{translate('product_Unit_Price')}}</th>
                            <th>
                                {{translate('total_Amount_Sold')}}
                                <i class="fi fi-rr-info" data-bs-toggle="tooltip" data-bs-placement="top" 
                                   data-bs-title="{{ translate('Product price × quantity sold') }}" 
                                   style="font-size: 12px; cursor: help; color: #6c757d; margin-left: 4px;"></i>
                            </th>
                            <th>{{translate('total_Quantity_Sold')}}</th>
                            <th>{{translate('average_Product_Value')}}</th>
                            <th>{{translate('current_Stock_Amount')}}</th>
                            <th>{{translate('average_Ratings')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($products as $key=>$product)
                            <tr>
                                <td>{{ $products->firstItem()+$key }}</td>
                                <td>
                                    <a href="{{route('admin.products.view',['addedBy'=>($product['added_by'] =='seller'?'vendor' : 'in-house'),'id'=>$product['id']])}}">
                                        <span class="media-body title-color hover-c1">{{\Illuminate\Support\Str::limit($product['name'], 20)}}</span>
                                    </a>
                                </td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->unit_price), currencyCode: getCurrencyCode()) }}</td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->total_sold_amount ?? 0), currencyCode: getCurrencyCode()) }}</td>
                                <td>{{ (int)($product->product_quantity ?? 0) }}</td>
                                <td>
                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: (($product->total_sold_amount ?? 0) / max(1, ($product->product_quantity ?? 1)))), currencyCode: getCurrencyCode()) }}
                                </td>
                                <td>{{ $product->product_type == 'digital' ? ($product->status==1 ? translate('available') : translate('not_available')) : $product->current_stock }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rating mr-1"><i class="tio-star"></i>
                                            {{count($product->rating)>0?number_format($product->rating[0]->average, 2, '.', ' '):0}}
                                        </div>
                                        <div>( {{$product->reviews->count()}} )</div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive mt-4">
                    <div class="px-4 d-flex justify-content-center justify-content-md-end">
                        {!! $products->links() !!}
                    </div>
                </div>
                @if(count($products)==0)
                    @include('layouts.admin.partials._empty-state',['text'=>'no_product_found'],['image'=>'default'])
                @endif
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/js/apexcharts.js')}}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/js/apexcharts-data-show.js')}}"></script>
    
    {{-- Component Checkbox Auto-Submit --}}
    <script>
        (function() {
            const checkboxes = document.querySelectorAll('.component-checkbox');
            
            if (checkboxes.length) {
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const url = new URL(window.location.href);
                        
                        // Update all checkbox parameters
                        checkboxes.forEach(cb => {
                            if (cb.checked) {
                                url.searchParams.set(cb.name, '1');
                            } else {
                                url.searchParams.set(cb.name, '0');
                            }
                        });
                        
                        // Navigate to updated URL
                        window.location.href = url.toString();
                    });
                });
            }
        })();
    </script>
@endpush

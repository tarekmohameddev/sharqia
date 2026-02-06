@extends('layouts.admin.app')

@section('title', translate('EasyOrders_SKU_Validation'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/bulk-import.png') }}" class="mb-1 mr-1" alt="">
                {{ translate('EasyOrders_SKU_Validation') }}
            </h2>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('EasyOrders_API_Key') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.business-settings.easyorders.sku-validation.save-api-key') }}" method="post">
                            @csrf
                            <div class="form-group">
                                <label for="api_key" class="form-label">{{ translate('API_Key') }}</label>
                                <input type="text" name="api_key" id="api_key" class="form-control" value="{{ old('api_key', $apiKey ?? '') }}" placeholder="{{ translate('Enter_EasyOrders_API_key') }}">
                                <small class="form-text text-muted">{{ translate('Required_for_fetching_products_from_EasyOrders') }}</small>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Run_SKU_Validation') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">{{ translate('Fetch_all_products_from_EasyOrders_and_compare_SKUs_against_your_system') }}</p>
                        <form action="{{ route('admin.business-settings.easyorders.sku-validation.run') }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-primary" @if(empty($apiKey)) disabled @endif>
                                <i class="fi fi-rr-refresh mr-1"></i>
                                {{ translate('Run_SKU_Validation') }}
                            </button>
                        </form>
                        @if(empty($apiKey))
                            <small class="form-text text-warning mt-2">{{ translate('Save_an_API_key_above_first') }}</small>
                        @endif
                    </div>
                </div>

                @if(!empty($results))
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ translate('Validation_Results') }}</h5>
                            <a href="{{ route('admin.business-settings.easyorders.sku-validation.download-report') }}" class="btn btn-success btn-sm">
                                <i class="fi fi-rr-download mr-1"></i>
                                {{ translate('Download_Excel_Report') }}
                            </a>
                        </div>
                        <div class="card-body">
                            @php $summary = $results['summary'] ?? []; @endphp
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="border rounded p-2 text-center">
                                        <div class="h5 mb-0">{{ $summary['total_easyorders_products'] ?? 0 }}</div>
                                        <small class="text-muted">{{ translate('EasyOrders_Products') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 text-center">
                                        <div class="h5 mb-0">{{ $summary['total_rows'] ?? 0 }}</div>
                                        <small class="text-muted">{{ translate('SKU_Rows') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 text-center text-success">
                                        <div class="h5 mb-0">{{ $summary['matched_count'] ?? 0 }}</div>
                                        <small class="text-muted">{{ translate('Matched') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 text-center text-danger">
                                        <div class="h5 mb-0">{{ $summary['unmatched_count'] ?? 0 }}</div>
                                        <small class="text-muted">{{ translate('Unmatched') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ translate('SKU_Code') }}</th>
                                            <th>{{ translate('EasyOrders_Product_Name') }}</th>
                                            <th>{{ translate('Compound_SKU') }}</th>
                                            <th>{{ translate('Status') }}</th>
                                            <th>{{ translate('Local_Product_Name') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($results['rows'] ?? [] as $row)
                                            <tr>
                                                <td>{{ $row['sku_code'] ?? '' }}</td>
                                                <td>{{ $row['easyorders_product_name'] ?? '' }}</td>
                                                <td><code>{{ $row['compound_sku'] ?? '' }}</code></td>
                                                <td>
                                                    @if(($row['status'] ?? '') === 'Matched')
                                                        <span class="badge badge-soft-success">{{ translate('Matched') }}</span>
                                                    @else
                                                        <span class="badge badge-soft-danger">{{ translate('Unmatched') }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $row['local_product_name'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('How_It_Works') }}</h5>
                    </div>
                    <div class="card-body">
                        <ol class="pl-3 mb-0">
                            <li class="mb-2">{{ translate('Save_your_EasyOrders_API_key_above') }}</li>
                            <li class="mb-2">{{ translate('Click_Run_SKU_Validation_to_fetch_all_products_from_EasyOrders') }}</li>
                            <li class="mb-2">{{ translate('Each_product_SKU_is_parsed_and_compared_to_your_local_product_codes') }}</li>
                            <li class="mb-2">{{ translate('Unmatched_SKUs_are_logged_and_listed_in_the_report') }}</li>
                            <li>{{ translate('Download_the_Excel_report_to_fix_missing_SKUs_and_prevent_order_failures') }}</li>
                        </ol>
                        <div class="alert alert-warning mt-3 mb-0">
                            <h6 class="mb-2"><i class="fi fi-rr-exclamation mr-1"></i>{{ translate('Note') }}</h6>
                            <p class="small mb-0">{{ translate('EasyOrders_API_rate_limit_is_40_requests_per_minute') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

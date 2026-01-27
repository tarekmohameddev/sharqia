@extends('layouts.admin.app')

@section('title', translate('EasyOrders_Excel_Import'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/bulk-import.png') }}" class="mb-1 mr-1" alt="">
                {{ translate('EasyOrders_Excel_Import') }}
            </h2>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Upload_Excel_File') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.orders.easy-orders.excel-import.process') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="form-group">
                                <label for="file" class="form-label">
                                    {{ translate('Select_Excel_or_CSV_File') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="file" class="custom-file-input" id="file" 
                                           accept=".xlsx,.xls,.csv" required>
                                    <label class="custom-file-label" for="file">{{ translate('Choose_file') }}</label>
                                </div>
                                <small class="form-text text-muted">
                                    {{ translate('Supported_formats') }}: .xlsx, .xls, .csv ({{ translate('Max_size') }}: 10MB)
                                </small>
                            </div>

                            <div class="alert alert-info mt-3">
                                <div class="d-flex align-items-start">
                                    <i class="fi fi-rr-info mr-2" style="font-size: 20px;"></i>
                                    <div>
                                        <h6 class="mb-2">{{ translate('How_It_Works') }}:</h6>
                                        <ol class="mb-0 pl-3">
                                            <li>{{ translate('Upload_your_Excel_CSV_file_with_EasyOrders_data') }}</li>
                                            <li>{{ translate('System_checks_each_order_ID_against_existing_records') }}</li>
                                            <li>{{ translate('New_orders_are_imported_duplicate_orders_are_skipped') }}</li>
                                            <li>{{ translate('Download_report_showing_imported_and_skipped_orders') }}</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <a href="{{ route('admin.orders.easy-orders.index') }}" class="btn btn-secondary mr-2">
                                    {{ translate('Cancel') }}
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fi fi-rr-upload mr-1"></i>
                                    {{ translate('Import_Orders') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Required_Excel_Format') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-primary">{{ translate('Required_Columns') }}:</h6>
                            <ul class="list-unstyled pl-0">
                                <li><span class="badge badge-soft-dark">Order ID</span> - {{ translate('EasyOrders_UUID') }}</li>
                                <li><span class="badge badge-soft-dark">FullName</span> - {{ translate('Customer_name') }}</li>
                                <li><span class="badge badge-soft-dark">Phone</span> - {{ translate('Phone_number') }}</li>
                                <li><span class="badge badge-soft-dark">City</span> - {{ translate('Governorate') }}</li>
                                <li><span class="badge badge-soft-dark">Address</span> - {{ translate('Full_address') }}</li>
                                <li><span class="badge badge-soft-dark">SKU</span> - {{ translate('Product_SKU') }}</li>
                                <li><span class="badge badge-soft-dark">Quantity</span> - {{ translate('Product_quantity') }}</li>
                                <li><span class="badge badge-soft-dark">Product Cost</span> - {{ translate('Product_cost') }}</li>
                                <li><span class="badge badge-soft-dark">Shipping Cost</span> - {{ translate('Shipping_cost') }}</li>
                                <li><span class="badge badge-soft-dark">Total Cost</span> - {{ translate('Total_cost') }}</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h6 class="mb-2"><i class="fi fi-rr-exclamation mr-1"></i>{{ translate('Important_Notes') }}:</h6>
                            <ul class="mb-0 pl-3 small">
                                <li>{{ translate('Order_ID_must_be_unique_EasyOrders_UUID') }}</li>
                                <li>{{ translate('Each_order_can_have_multiple_rows_for_multiple_products') }}</li>
                                <li>{{ translate('SKU_format') }}: 222233(5) {{ translate('or_just') }} 222233</li>
                                <li>{{ translate('Orders_already_in_system_will_be_skipped') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";
        // Update file input label with selected filename
        document.querySelector('.custom-file-input').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '{{ translate('Choose_file') }}';
            const label = e.target.nextElementSibling;
            label.textContent = fileName;
        });
    </script>
@endpush

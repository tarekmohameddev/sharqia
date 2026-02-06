@extends('layouts.admin.app')

@section('title', translate('EasyOrders_Order_Validation'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/bulk-import.png') }}" class="mb-1 mr-1" alt="">
                {{ translate('EasyOrders_Order_Validation') }}
            </h2>
        </div>

        <div class="row">
            {{-- Main content --}}
            <div class="col-lg-8">
                {{-- Overview card --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Staging_Orders_Overview') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <div class="h5 mb-0">{{ $totalStagingOrders ?? 0 }}</div>
                                    <small class="text-muted">{{ translate('Total') }}</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center text-success">
                                    <div class="h5 mb-0">{{ $importedCount ?? 0 }}</div>
                                    <small class="text-muted">{{ translate('Imported') }}</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center text-warning">
                                    <div class="h5 mb-0">{{ $pendingCount ?? 0 }}</div>
                                    <small class="text-muted">{{ translate('Pending') }}</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center text-danger">
                                    <div class="h5 mb-0">{{ $failedCount ?? 0 }}</div>
                                    <small class="text-muted">{{ translate('Failed') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Validation controls --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Run_Order_Validation') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            {{ translate('Fetch_each_order_from_EasyOrders_API_and_compare_against_your_imported_data') }}
                        </p>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_from" class="form-label">{{ translate('Date_From') }}</label>
                                    <input type="date" id="date_from" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_to" class="form-label">{{ translate('Date_To') }}</label>
                                    <input type="date" id="date_to" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="max_orders" class="form-label">{{ translate('Max_Orders') }}</label>
                                    <input type="number" id="max_orders" class="form-control" min="1" placeholder="{{ translate('All') }}">
                                    <small class="form-text text-muted">{{ translate('Leave_empty_for_all') }}</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="per_page" class="form-label">{{ translate('Batch_Size') }}</label>
                                    <select id="per_page" class="form-control">
                                        <option value="5">5</option>
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="30">30</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-2">
                            <button type="button" id="btn-start" class="btn btn-primary mr-2" @if(empty($apiKey)) disabled @endif>
                                <i class="fi fi-rr-play mr-1"></i>
                                {{ translate('Start_Validation') }}
                            </button>
                            <button type="button" id="btn-stop" class="btn btn-danger mr-2" style="display:none;">
                                <i class="fi fi-rr-stop mr-1"></i>
                                {{ translate('Stop') }}
                            </button>
                            <button type="button" id="btn-clear" class="btn btn-outline-secondary" style="display:none;">
                                <i class="fi fi-rr-trash mr-1"></i>
                                {{ translate('Clear_Results') }}
                            </button>
                        </div>

                        @if(empty($apiKey))
                            <small class="form-text text-warning mt-2">
                                {{ translate('EasyOrders_API_key_is_not_configured') }}.
                                <a href="{{ route('admin.business-settings.easyorders.sku-validation.index') }}">{{ translate('Set_it_here') }}</a>
                            </small>
                        @endif
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="card mb-3" id="progress-card" style="display:none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span id="progress-text" class="font-weight-bold">{{ translate('Processing') }}...</span>
                            <span id="progress-count" class="text-muted">0 / 0</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                                 style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                0%
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live summary --}}
                <div class="card mb-3" id="summary-card" style="display:none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Validation_Results') }}</h5>
                        <a href="{{ route('admin.business-settings.easyorders.order-validation.download-report') }}"
                           id="btn-download" class="btn btn-success btn-sm" style="display:none;">
                            <i class="fi fi-rr-download mr-1"></i>
                            {{ translate('Download_Excel_Report') }}
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col">
                                <div class="border rounded p-2 text-center">
                                    <div class="h5 mb-0" id="sum-total">0</div>
                                    <small class="text-muted">{{ translate('Processed') }}</small>
                                </div>
                            </div>
                            <div class="col">
                                <div class="border rounded p-2 text-center text-success">
                                    <div class="h5 mb-0" id="sum-matched">0</div>
                                    <small class="text-muted">{{ translate('Matched') }}</small>
                                </div>
                            </div>
                            <div class="col">
                                <div class="border rounded p-2 text-center text-danger">
                                    <div class="h5 mb-0" id="sum-mismatched">0</div>
                                    <small class="text-muted">{{ translate('Mismatched') }}</small>
                                </div>
                            </div>
                            <div class="col">
                                <div class="border rounded p-2 text-center text-warning">
                                    <div class="h5 mb-0" id="sum-not-imported">0</div>
                                    <small class="text-muted">{{ translate('Not_Imported') }}</small>
                                </div>
                            </div>
                            <div class="col">
                                <div class="border rounded p-2 text-center text-secondary">
                                    <div class="h5 mb-0" id="sum-errors">0</div>
                                    <small class="text-muted">{{ translate('Errors') }}</small>
                                </div>
                            </div>
                        </div>

                        {{-- Filter tabs --}}
                        <ul class="nav nav-tabs mb-3" id="resultTabs">
                            <li class="nav-item">
                                <a class="nav-link active" href="#" data-filter="all">{{ translate('All') }} (<span id="tab-all-count">0</span>)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-filter="matched">{{ translate('Matched') }} (<span id="tab-matched-count">0</span>)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-filter="mismatched">{{ translate('Mismatched') }} (<span id="tab-mismatched-count">0</span>)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-filter="not_imported">{{ translate('Not_Imported') }} (<span id="tab-not-imported-count">0</span>)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-filter="errors">{{ translate('Errors') }} (<span id="tab-errors-count">0</span>)</a>
                            </li>
                        </ul>

                        {{-- Results table --}}
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm" id="results-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:5%">#</th>
                                        <th style="width:20%">{{ translate('EasyOrders_ID') }}</th>
                                        <th>{{ translate('Customer') }}</th>
                                        <th style="width:10%">{{ translate('Staging_Status') }}</th>
                                        <th style="width:10%">{{ translate('Order_ID') }}</th>
                                        <th style="width:12%">{{ translate('Validation') }}</th>
                                        <th style="width:30%">{{ translate('Discrepancies') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="results-body">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('How_It_Works') }}</h5>
                    </div>
                    <div class="card-body">
                        <ol class="pl-3 mb-0">
                            <li class="mb-2">{{ translate('Set_a_date_range_to_filter_orders_by_when_they_were_received') }}</li>
                            <li class="mb-2">{{ translate('Optionally_set_a_max_number_of_orders_to_validate') }}</li>
                            <li class="mb-2">{{ translate('Click_Start_Validation_to_begin_processing_orders_in_batches') }}</li>
                            <li class="mb-2">{{ translate('Each_order_is_fetched_from_the_EasyOrders_API_and_compared') }}</li>
                            <li class="mb-2">{{ translate('Results_appear_in_real_time_as_batches_complete') }}</li>
                            <li class="mb-2">{{ translate('Use_the_Stop_button_to_pause_at_any_time') }}</li>
                            <li>{{ translate('Download_the_Excel_report_when_done') }}</li>
                        </ol>
                        <div class="alert alert-warning mt-3 mb-0">
                            <h6 class="mb-2"><i class="fi fi-rr-exclamation mr-1"></i>{{ translate('Note') }}</h6>
                            <p class="small mb-1">{{ translate('EasyOrders_API_rate_limit_is_40_requests_per_minute') }}</p>
                            <p class="small mb-0">{{ translate('Each_order_requires_one_API_call') }}</p>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            <h6 class="mb-2"><i class="fi fi-rr-info mr-1"></i>{{ translate('What_is_compared') }}</h6>
                            <ul class="small pl-3 mb-0">
                                <li>{{ translate('Product_cost_shipping_cost_total_cost') }}</li>
                                <li>{{ translate('Customer_name_phone_government') }}</li>
                                <li>{{ translate('Cart_items_count_and_details') }}</li>
                                <li>{{ translate('Product_quantities_and_prices') }}</li>
                                <li>{{ translate('Imported_order_line_items') }}</li>
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
(function() {
    'use strict';

    const CSRF_TOKEN = '{{ csrf_token() }}';
    const RUN_BATCH_URL = '{{ route("admin.business-settings.easyorders.order-validation.run-batch") }}';
    const CLEAR_URL = '{{ route("admin.business-settings.easyorders.order-validation.clear-results") }}';

    // State
    let isRunning = false;
    let currentPage = 1;
    let allResults = [];
    let summary = { total: 0, matched: 0, mismatched: 0, not_imported: 0, api_errors: 0, not_found_on_easyorders: 0 };

    // DOM elements
    const btnStart = document.getElementById('btn-start');
    const btnStop = document.getElementById('btn-stop');
    const btnClear = document.getElementById('btn-clear');
    const btnDownload = document.getElementById('btn-download');
    const progressCard = document.getElementById('progress-card');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const progressCount = document.getElementById('progress-count');
    const summaryCard = document.getElementById('summary-card');
    const resultsBody = document.getElementById('results-body');

    // Start validation
    btnStart.addEventListener('click', function() {
        if (isRunning) return;

        // Reset state
        allResults = [];
        currentPage = 1;
        summary = { total: 0, matched: 0, mismatched: 0, not_imported: 0, api_errors: 0, not_found_on_easyorders: 0 };
        resultsBody.innerHTML = '';
        updateSummaryUI();
        updateTabCounts();

        isRunning = true;
        btnStart.style.display = 'none';
        btnStop.style.display = 'inline-block';
        btnClear.style.display = 'none';
        btnDownload.style.display = 'none';
        progressCard.style.display = 'block';
        summaryCard.style.display = 'block';
        updateProgress(0, 0, 0);

        processBatch();
    });

    // Stop validation
    btnStop.addEventListener('click', function() {
        isRunning = false;
        btnStop.style.display = 'none';
        btnStart.style.display = 'inline-block';
        btnClear.style.display = 'inline-block';
        progressText.textContent = '{{ translate("Stopped") }}';
        progressBar.classList.remove('progress-bar-animated');

        if (allResults.length > 0) {
            btnDownload.style.display = 'inline-block';
        }
    });

    // Clear results
    btnClear.addEventListener('click', function() {
        fetch(CLEAR_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        });

        allResults = [];
        summary = { total: 0, matched: 0, mismatched: 0, not_imported: 0, api_errors: 0, not_found_on_easyorders: 0 };
        resultsBody.innerHTML = '';
        updateSummaryUI();
        updateTabCounts();
        progressCard.style.display = 'none';
        summaryCard.style.display = 'none';
        btnClear.style.display = 'none';
        btnDownload.style.display = 'none';
    });

    // Tab filtering
    document.querySelectorAll('#resultTabs .nav-link').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#resultTabs .nav-link').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const filter = this.getAttribute('data-filter');
            document.querySelectorAll('#results-body tr[data-status]').forEach(function(row) {
                if (filter === 'all') {
                    row.style.display = '';
                } else if (filter === 'errors') {
                    row.style.display = (row.dataset.status === 'api_error' || row.dataset.status === 'not_found_on_easyorders') ? '' : 'none';
                } else {
                    row.style.display = row.dataset.status === filter ? '' : 'none';
                }
            });
        });
    });

    // Process a batch
    function processBatch() {
        if (!isRunning) return;

        const params = {
            date_from: document.getElementById('date_from').value || null,
            date_to: document.getElementById('date_to').value || null,
            page: currentPage,
            per_page: parseInt(document.getElementById('per_page').value) || 10,
            max_orders: document.getElementById('max_orders').value ? parseInt(document.getElementById('max_orders').value) : null,
        };

        fetch(RUN_BATCH_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            },
            body: JSON.stringify(params),
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.message || 'Request failed'); });
            }
            return response.json();
        })
        .then(data => {
            if (!isRunning && currentPage > 1) return; // Stopped during request

            const results = data.results || [];
            const pagination = data.pagination || {};

            // Append results
            results.forEach(function(r) {
                allResults.push(r);
                appendResultRow(r, allResults.length);
                updateSummaryFromResult(r);
            });

            updateSummaryUI();
            updateTabCounts();

            // Update progress
            const processed = pagination.processed_so_far || allResults.length;
            const total = pagination.effective_total || pagination.total_orders || 0;
            updateProgress(processed, total, pagination.current_page || currentPage);

            if (data.completed || !pagination.has_more) {
                // Done
                isRunning = false;
                btnStop.style.display = 'none';
                btnStart.style.display = 'inline-block';
                btnClear.style.display = 'inline-block';
                progressText.textContent = '{{ translate("Completed") }}';
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');

                if (allResults.length > 0) {
                    btnDownload.style.display = 'inline-block';
                }
            } else {
                // Process next batch
                currentPage++;
                processBatch();
            }
        })
        .catch(function(err) {
            console.error('Validation batch error:', err);
            isRunning = false;
            btnStop.style.display = 'none';
            btnStart.style.display = 'inline-block';
            btnClear.style.display = 'inline-block';
            progressText.textContent = '{{ translate("Error") }}: ' + err.message;
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.add('bg-danger');

            if (allResults.length > 0) {
                btnDownload.style.display = 'inline-block';
            }
        });
    }

    // Append a single result row to the table
    function appendResultRow(r, index) {
        const tr = document.createElement('tr');
        tr.setAttribute('data-status', r.status);

        const statusBadge = getStatusBadge(r.status);
        const mismatches = (r.mismatches || []).map(function(m) {
            return '<div class="mb-1"><strong>' + escapeHtml(m.field) + '</strong>: ' +
                   '<span class="text-primary">EO=' + escapeHtml(String(m.easyorders_value)) + '</span> | ' +
                   '<span class="text-danger">Ours=' + escapeHtml(String(m.our_value)) + '</span></div>';
        }).join('');

        const easyordersIdShort = (r.easyorders_id || '').substring(0, 8) + '...';

        tr.innerHTML =
            '<td>' + index + '</td>' +
            '<td><small title="' + escapeHtml(r.easyorders_id || '') + '">' + escapeHtml(easyordersIdShort) + '</small></td>' +
            '<td><small>' + escapeHtml(r.full_name || '') + '<br>' + escapeHtml(r.phone || '') + '</small></td>' +
            '<td><span class="badge badge-soft-' + getStagingBadgeColor(r.our_staging_status) + '">' + escapeHtml(r.our_staging_status || '') + '</span></td>' +
            '<td>' + (r.imported_order_id || '-') + '</td>' +
            '<td>' + statusBadge + '</td>' +
            '<td><small>' + (mismatches || '<span class="text-success">{{ translate("All_fields_match") }}</span>') + '</small></td>';

        resultsBody.appendChild(tr);
    }

    function getStatusBadge(status) {
        const map = {
            'matched': '<span class="badge badge-soft-success">{{ translate("Matched") }}</span>',
            'mismatched': '<span class="badge badge-soft-danger">{{ translate("Mismatched") }}</span>',
            'not_imported': '<span class="badge badge-soft-warning">{{ translate("Not_Imported") }}</span>',
            'api_error': '<span class="badge badge-soft-secondary">{{ translate("API_Error") }}</span>',
            'not_found_on_easyorders': '<span class="badge badge-soft-secondary">{{ translate("Not_Found") }}</span>',
        };
        return map[status] || '<span class="badge badge-soft-info">' + escapeHtml(status) + '</span>';
    }

    function getStagingBadgeColor(status) {
        const map = { 'imported': 'success', 'pending': 'warning', 'failed': 'danger', 'rejected': 'secondary' };
        return map[status] || 'info';
    }

    function updateSummaryFromResult(r) {
        summary.total++;
        switch (r.status) {
            case 'matched': summary.matched++; break;
            case 'mismatched': summary.mismatched++; break;
            case 'not_imported': summary.not_imported++; break;
            case 'api_error': summary.api_errors++; break;
            case 'not_found_on_easyorders': summary.not_found_on_easyorders++; break;
        }
    }

    function updateSummaryUI() {
        document.getElementById('sum-total').textContent = summary.total;
        document.getElementById('sum-matched').textContent = summary.matched;
        document.getElementById('sum-mismatched').textContent = summary.mismatched;
        document.getElementById('sum-not-imported').textContent = summary.not_imported;
        document.getElementById('sum-errors').textContent = summary.api_errors + summary.not_found_on_easyorders;
    }

    function updateTabCounts() {
        document.getElementById('tab-all-count').textContent = summary.total;
        document.getElementById('tab-matched-count').textContent = summary.matched;
        document.getElementById('tab-mismatched-count').textContent = summary.mismatched;
        document.getElementById('tab-not-imported-count').textContent = summary.not_imported;
        document.getElementById('tab-errors-count').textContent = summary.api_errors + summary.not_found_on_easyorders;
    }

    function updateProgress(processed, total, page) {
        const pct = total > 0 ? Math.round((processed / total) * 100) : 0;
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
        progressBar.setAttribute('aria-valuenow', pct);
        progressCount.textContent = processed + ' / ' + total;
        progressText.textContent = '{{ translate("Processing_batch") }} ' + page + '...';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
})();
</script>
@endpush

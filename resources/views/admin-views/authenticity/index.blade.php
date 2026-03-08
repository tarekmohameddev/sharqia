@extends('layouts.admin.app')

@section('title', translate('Authenticity_Scratch_Cards'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('Authenticity_Scratch_Cards') }}</h2>
            <a href="{{ route('admin.authenticity.batches.create') }}" class="btn btn-primary">
                <i class="fi fi-sr-plus"></i> {{ translate('Generate_New_Batch') }}
            </a>
        </div>

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-soft-primary rounded p-3 fs-3"><i class="fi fi-sr-layers"></i></div>
                        <div>
                            <div class="text-muted small">{{ translate('Total_Batches') }}</div>
                            <div class="fs-4 fw-bold">{{ number_format($stats['total_batches']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-soft-success rounded p-3 fs-3"><i class="fi fi-sr-check-circle"></i></div>
                        <div>
                            <div class="text-muted small">{{ translate('Used_Codes') }}</div>
                            <div class="fs-4 fw-bold text-success">{{ number_format($stats['used_codes']) }}</div>
                            <div class="text-muted small">{{ translate('of') }} {{ number_format($stats['total_codes']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-soft-warning rounded p-3 fs-3"><i class="fi fi-sr-shield"></i></div>
                        <div>
                            <div class="text-muted small">{{ translate('Total_Scans') }}</div>
                            <div class="fs-4 fw-bold">{{ number_format($stats['total_scans']) }}</div>
                            <div class="text-muted small">{{ translate('valid') }}: {{ number_format($stats['valid_scans']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="bg-soft-danger rounded p-3 fs-3"><i class="fi fi-sr-flag"></i></div>
                        <div>
                            <div class="text-muted small">{{ translate('Pending_Reports') }}</div>
                            <div class="fs-4 fw-bold text-danger">{{ number_format($stats['pending_reports']) }}</div>
                            <div class="text-muted small">{{ translate('total') }}: {{ number_format($stats['total_reports']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            {{-- Recent Batches --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Recent_Batches') }}</h5>
                        <a href="{{ route('admin.authenticity.batches.index') }}" class="btn btn-sm btn-outline-primary">
                            {{ translate('View_All') }}
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ translate('batch_number') }}</th>
                                        <th>{{ translate('total') }}</th>
                                        <th>{{ translate('generated_at') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($recentBatches as $batch)
                                    <tr>
                                        <td><span class="badge badge-soft-primary">{{ $batch->batch_number }}</span></td>
                                        <td>{{ number_format($batch->total_count) }}</td>
                                        <td>{{ $batch->generated_at?->format('Y-m-d') ?? '-' }}</td>
                                        <td>
                                            <a href="{{ route('admin.authenticity.batches.show', $batch->id) }}"
                                               class="btn btn-sm btn-outline-info">
                                                {{ translate('view') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">{{ translate('no_batches_yet') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent Scans --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ translate('Recent_Scan_Activity') }}</h5>
                        <a href="{{ route('admin.authenticity.audit-logs') }}" class="btn btn-sm btn-outline-primary">
                            {{ translate('View_All') }}
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ translate('code') }}</th>
                                        <th>{{ translate('result') }}</th>
                                        <th>{{ translate('ip') }}</th>
                                        <th>{{ translate('time') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($recentScans as $log)
                                    <tr>
                                        <td><code>{{ $log->code_entered }}</code></td>
                                        <td>
                                            @php
                                                $resultClass = match($log->result) {
                                                    'valid_first' => 'success',
                                                    'valid_rescan' => 'info',
                                                    'invalid' => 'danger',
                                                    'rate_limited' => 'warning',
                                                    'blocked' => 'dark',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge badge-soft-{{ $resultClass }}">{{ $log->result }}</span>
                                        </td>
                                        <td>{{ $log->ip_address }}</td>
                                        <td>{{ $log->created_at?->diffForHumans() ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">{{ translate('no_scans_yet') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

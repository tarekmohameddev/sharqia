@extends('layouts.admin.app')

@section('title', translate('Batch') . ' ' . $batch->batch_number)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0">{{ $batch->batch_number }}</h2>
                <small class="text-muted">{{ translate('Generated') }}: {{ $batch->generated_at?->format('Y-m-d H:i') ?? '-' }}</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.authenticity.batches.export-pdf', $batch->id) }}"
                   class="btn btn-primary">
                    <i class="fi fi-sr-file-pdf"></i> {{ translate('Export_PDF_for_Printing') }}
                </a>
                <a href="{{ route('admin.authenticity.batches.export-csv', $batch->id) }}"
                   class="btn btn-outline-secondary">
                    <i class="fi fi-sr-file-spreadsheet"></i> {{ translate('Export_CSV') }}
                </a>
                <a href="{{ route('admin.authenticity.batches.index') }}" class="btn btn-outline-secondary">
                    {{ translate('Back') }}
                </a>
            </div>
        </div>

        {{-- Stats Row --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-1 fw-bold">{{ number_format($batch->total_count) }}</div>
                        <div class="text-muted">{{ translate('Total_Codes') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <div class="fs-1 fw-bold text-success">{{ number_format($usedCount) }}</div>
                        <div class="text-muted">{{ translate('Used') }}</div>
                        @if($batch->total_count > 0)
                            <div class="small text-muted">{{ round(($usedCount / $batch->total_count) * 100) }}%</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <div class="fs-1 fw-bold text-warning">{{ number_format($unusedCount) }}</div>
                        <div class="text-muted">{{ translate('Unused') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Codes Table --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Codes_in_this_Batch') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('code') }}</th>
                                <th>{{ translate('status') }}</th>
                                <th>{{ translate('first_scanned_at') }}</th>
                                <th>{{ translate('scanned_by') }}</th>
                                <th>{{ translate('scanned_from_ip') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($codes as $code)
                            <tr>
                                <td><code class="fs-6">{{ $code->code }}</code></td>
                                <td>
                                    <span class="badge badge-soft-{{ $code->status === 'used' ? 'success' : 'warning' }}">
                                        {{ $code->status }}
                                    </span>
                                </td>
                                <td>{{ $code->first_scanned_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>{{ $code->firstScannedBy?->f_name ?? '-' }}</td>
                                <td>{{ $code->first_scanned_ip ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if($codes->hasPages())
                <div class="card-footer">
                    {{ $codes->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

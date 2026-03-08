@extends('layouts.admin.app')

@section('title', translate('Authenticity_Code_Batches'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('Code_Batches') }}</h2>
            <a href="{{ route('admin.authenticity.batches.create') }}" class="btn btn-primary">
                <i class="fi fi-sr-plus"></i> {{ translate('Generate_Batch') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('batch_number') }}</th>
                                <th>{{ translate('total') }}</th>
                                <th>{{ translate('used') }}</th>
                                <th>{{ translate('unused') }}</th>
                                <th>{{ translate('usage') }} %</th>
                                <th>{{ translate('generated_at') }}</th>
                                <th class="text-center">{{ translate('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($batches as $batch)
                            @php
                                $pct = $batch->total_count > 0
                                    ? round(($batch->used_count / $batch->total_count) * 100)
                                    : 0;
                            @endphp
                            <tr>
                                <td>{{ $batch->id }}</td>
                                <td><span class="badge badge-soft-primary">{{ $batch->batch_number }}</span></td>
                                <td>{{ number_format($batch->codes_count) }}</td>
                                <td class="text-success fw-semibold">{{ number_format($batch->used_count) }}</td>
                                <td class="text-warning fw-semibold">{{ number_format($batch->unused_count) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px">
                                            <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                                        </div>
                                        <span class="small">{{ $pct }}%</span>
                                    </div>
                                </td>
                                <td>{{ $batch->generated_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="{{ route('admin.authenticity.batches.show', $batch->id) }}"
                                           class="btn btn-sm btn-outline-info"
                                           title="{{ translate('View') }}">
                                            <i class="fi fi-sr-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.authenticity.batches.export-pdf', $batch->id) }}"
                                           class="btn btn-sm btn-outline-primary"
                                           title="{{ translate('Export_PDF') }}">
                                            <i class="fi fi-sr-file-pdf"></i>
                                        </a>
                                        <a href="{{ route('admin.authenticity.batches.export-csv', $batch->id) }}"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="{{ translate('Export_CSV') }}">
                                            <i class="fi fi-sr-file-spreadsheet"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ translate('no_batches_yet') }}
                                    <a href="{{ route('admin.authenticity.batches.create') }}">{{ translate('generate_one') }}</a>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($batches->hasPages())
                <div class="card-footer">
                    {{ $batches->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

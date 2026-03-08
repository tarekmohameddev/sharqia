@extends('layouts.admin.app')

@section('title', translate('Counterfeit_Reports'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('Counterfeit_Reports') }}</h2>
        </div>

        {{-- Filter Tabs --}}
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $filter === 'all' ? 'active' : '' }}"
                   href="{{ route('admin.authenticity.counterfeit-reports.index') }}">
                    {{ translate('All') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $filter === 'pending' ? 'active' : '' }}"
                   href="{{ route('admin.authenticity.counterfeit-reports.index', ['filter' => 'pending']) }}">
                    {{ translate('Pending_Review') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $filter === 'reviewed' ? 'active' : '' }}"
                   href="{{ route('admin.authenticity.counterfeit-reports.index', ['filter' => 'reviewed']) }}">
                    {{ translate('Reviewed') }}
                </a>
            </li>
        </ul>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('code') }}</th>
                                <th>{{ translate('reported_by') }}</th>
                                <th>{{ translate('reported_at') }}</th>
                                <th>{{ translate('notes') }}</th>
                                <th>{{ translate('status') }}</th>
                                <th class="text-center">{{ translate('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($reports as $report)
                            <tr>
                                <td>{{ $report->id }}</td>
                                <td>
                                    @if($report->code)
                                        <code>{{ $report->code->code }}</code>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($report->user)
                                        {{ $report->user->f_name }} {{ $report->user->l_name }}
                                        <br>
                                        <small class="text-muted">{{ $report->user->phone }}</small>
                                    @else
                                        <span class="text-muted">{{ translate('deleted_user') }}</span>
                                    @endif
                                </td>
                                <td>{{ $report->reported_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    @if($report->notes)
                                        <span title="{{ $report->notes }}">{{ Str::limit($report->notes, 50) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($report->admin_reviewed_at)
                                        <span class="badge badge-soft-success">{{ translate('Reviewed') }}</span>
                                        <div class="text-muted small">{{ $report->admin_reviewed_at->format('Y-m-d') }}</div>
                                    @else
                                        <span class="badge badge-soft-warning">{{ translate('Pending') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(!$report->admin_reviewed_at)
                                        <button type="button"
                                                class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal"
                                                data-bs-target="#reviewModal{{ $report->id }}">
                                            {{ translate('Mark_Reviewed') }}
                                        </button>

                                        {{-- Review Modal --}}
                                        <div class="modal fade" id="reviewModal{{ $report->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <form action="{{ route('admin.authenticity.counterfeit-reports.review', $report->id) }}"
                                                      method="POST">
                                                    @csrf
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ translate('Review_Report') }} #{{ $report->id }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">{{ translate('Code') }}</label>
                                                                <input type="text" class="form-control" value="{{ $report->code?->code }}" readonly>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">{{ translate('Reporter_Notes') }}</label>
                                                                <textarea class="form-control" rows="2" readonly>{{ $report->notes ?? translate('no_notes') }}</textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">{{ translate('Admin_Notes') }} ({{ translate('optional') }})</label>
                                                                <textarea name="admin_notes" class="form-control" rows="3"
                                                                          placeholder="{{ translate('add_investigation_notes') }}"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                                {{ translate('Cancel') }}
                                                            </button>
                                                            <button type="submit" class="btn btn-success">
                                                                {{ translate('Mark_as_Reviewed') }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        @if($report->admin_notes)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info"
                                                    title="{{ $report->admin_notes }}"
                                                    data-bs-toggle="tooltip">
                                                {{ translate('Notes') }}
                                            </button>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('no_reports_found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($reports->hasPages())
                <div class="card-footer">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script')
<script>
    // Enable tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
</script>
@endpush

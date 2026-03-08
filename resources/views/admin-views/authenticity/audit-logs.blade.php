@extends('layouts.admin.app')

@section('title', translate('Authenticity_Audit_Logs'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('Scan_Audit_Logs') }}</h2>
        </div>

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label small mb-1">{{ translate('Result') }}</label>
                        <select name="result" class="form-select form-select-sm">
                            <option value="">{{ translate('All_Results') }}</option>
                            @foreach($results as $r)
                                <option value="{{ $r }}" {{ $result === $r ? 'selected' : '' }}>
                                    {{ str_replace('_', ' ', ucfirst($r)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">{{ translate('Search') }}</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="{{ translate('code_or_ip') }}"
                               value="{{ $search ?? '' }}">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            {{ translate('Filter') }}
                        </button>
                        <a href="{{ route('admin.authenticity.audit-logs') }}" class="btn btn-outline-secondary btn-sm">
                            {{ translate('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('time') }}</th>
                                <th>{{ translate('code_entered') }}</th>
                                <th>{{ translate('result') }}</th>
                                <th>{{ translate('user') }}</th>
                                <th>{{ translate('ip_address') }}</th>
                                <th>{{ translate('device_id') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($logs as $log)
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
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td><code>{{ $log->code_entered }}</code></td>
                                <td>
                                    <span class="badge badge-soft-{{ $resultClass }}">
                                        {{ str_replace('_', ' ', $log->result) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->user)
                                        {{ $log->user->f_name }} {{ $log->user->l_name }}
                                        <small class="text-muted">#{{ $log->user_id }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $log->ip_address }}</td>
                                <td>
                                    @if($log->device_id)
                                        <span title="{{ $log->device_id }}">{{ Str::limit($log->device_id, 20) }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ translate('no_logs_found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($logs->hasPages())
                <div class="card-footer">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

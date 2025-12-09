@extends('layouts.admin.app')

@section('title', translate('EasyOrders_Staging_Orders'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('EasyOrders_Staging_Orders') }}</h2>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ translate('orders') }}</h5>
                <form action="" method="get" class="d-flex gap-2">
                    <select name="status" class="form-control">
                        <option value="">{{ translate('all_statuses') }}</option>
                        <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>{{ translate('pending') }}</option>
                        <option value="imported" {{ ($status ?? '') === 'imported' ? 'selected' : '' }}>{{ translate('imported') }}</option>
                        <option value="failed" {{ ($status ?? '') === 'failed' ? 'selected' : '' }}>{{ translate('failed') }}</option>
                        <option value="rejected" {{ ($status ?? '') === 'rejected' ? 'selected' : '' }}>{{ translate('rejected') }}</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        {{ translate('filter') }}
                    </button>
                </form>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.orders.easy-orders.bulk-import') }}" method="post">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th>{{ translate('EasyOrders_ID') }}</th>
                                <th>{{ translate('name') }}</th>
                                <th>{{ translate('phone') }}</th>
                                <th>{{ translate('government') }}</th>
                                <th>{{ translate('cost') }}</th>
                                <th>{{ translate('shipping_cost') }}</th>
                                <th>{{ translate('total_cost') }}</th>
                                <th>{{ translate('status') }}</th>
                                <th>{{ translate('imported_order_id') }}</th>
                                <th>{{ translate('created_at') }}</th>
                                <th class="text-center">{{ translate('actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($easyOrders as $eo)
                                <tr>
                                    <td>
                                        @if($eo->status === 'pending')
                                            <input type="checkbox" name="ids[]" value="{{ $eo->id }}">
                                        @endif
                                    </td>
                                    <td>{{ $eo->easyorders_id }}</td>
                                    <td>{{ $eo->full_name }}</td>
                                    <td>{{ $eo->phone }}</td>
                                    <td>{{ $eo->government }}</td>
                                    <td>{{ \App\Utils\BackEndHelper::set_symbol($eo->cost) }}</td>
                                    <td>{{ \App\Utils\BackEndHelper::set_symbol($eo->shipping_cost) }}</td>
                                    <td>{{ \App\Utils\BackEndHelper::set_symbol($eo->total_cost) }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $eo->status === 'imported' ? 'success' : ($eo->status === 'failed' ? 'danger' : ($eo->status === 'rejected' ? 'secondary' : 'warning')) }}">
                                            {{ $eo->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($eo->imported_order_id)
                                            <a href="{{ route('admin.orders.details', ['id' => $eo->imported_order_id]) }}">
                                                #{{ $eo->imported_order_id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $eo->created_at ? $eo->created_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.orders.easy-orders.show', ['id' => $eo->id]) }}"
                                           class="btn btn-sm btn-outline-info">
                                            {{ translate('view') }}
                                        </a>
                                        @if($eo->status === 'pending' || $eo->status === 'failed')
                                            <button type="submit"
                                                    formaction="{{ route('admin.orders.easy-orders.import', ['id' => $eo->id]) }}"
                                                    class="btn btn-sm btn-outline-success">
                                                {{ translate('import') }}
                                            </button>
                                        @endif
                                        @if($eo->status === 'pending')
                                            <button type="submit"
                                                    formaction="{{ route('admin.orders.easy-orders.reject', ['id' => $eo->id]) }}"
                                                    class="btn btn-sm btn-outline-danger">
                                                {{ translate('reject') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center">
                                        {{ translate('no_data_found') }}
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <button type="submit" class="btn btn-primary"
                                    @if(!$easyOrders->where('status', 'pending')->count()) disabled @endif>
                                {{ translate('bulk_import_selected') }}
                            </button>
                        </div>
                        <div>
                            {!! $easyOrders->links() !!}
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('script')
        <script>
            "use strict";
            document.getElementById('select-all')?.addEventListener('change', function () {
                const checked = this.checked;
                document.querySelectorAll('input[name="ids[]"]').forEach(function (el) {
                    el.checked = checked;
                });
            });
        </script>
    @endpush
@endsection




@extends('layouts.admin.app')

@section('title', translate('EasyOrders_Order_Details'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('EasyOrders_Order_Details') }}</h2>
            <a href="{{ route('admin.orders.easy-orders.index') }}" class="btn btn-secondary">
                {{ translate('back') }}
            </a>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('basic_information') }}</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ translate('EasyOrders_ID') }}</dt>
                            <dd class="col-sm-8">{{ $easyOrder->easyorders_id }}</dd>

                            <dt class="col-sm-4">{{ translate('name') }}</dt>
                            <dd class="col-sm-8">{{ $easyOrder->full_name }}</dd>

                            <dt class="col-sm-4">{{ translate('phone') }}</dt>
                            <dd class="col-sm-8">{{ $easyOrder->phone }}</dd>

                            <dt class="col-sm-4">{{ translate('government') }}</dt>
                            <dd class="col-sm-8">{{ $easyOrder->government }}</dd>

                            <dt class="col-sm-4">{{ translate('address') }}</dt>
                            <dd class="col-sm-8">{{ $easyOrder->address }}</dd>

                            <dt class="col-sm-4">{{ translate('sku') }}</dt>
                            <dd class="col-sm-8">{{ $easyOrder->sku_string }}</dd>

                            <dt class="col-sm-4">{{ translate('status') }}</dt>
                            <dd class="col-sm-8">
                                <span class="badge badge-soft-{{ $easyOrder->status === 'imported' ? 'success' : ($easyOrder->status === 'failed' ? 'danger' : ($easyOrder->status === 'rejected' ? 'secondary' : 'warning')) }}">
                                    {{ $easyOrder->status }}
                                </span>
                            </dd>

                            <dt class="col-sm-4">{{ translate('imported_order_id') }}</dt>
                            <dd class="col-sm-8">
                                @if($easyOrder->imported_order_id)
                                    <a href="{{ route('admin.orders.details', ['id' => $easyOrder->imported_order_id]) }}">
                                        #{{ $easyOrder->imported_order_id }}
                                    </a>
                                @else
                                    -
                                @endif
                            </dd>

                            @if($easyOrder->import_error)
                                <dt class="col-sm-4 text-danger">{{ translate('last_error') }}</dt>
                                <dd class="col-sm-8 text-danger">{{ $easyOrder->import_error }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.orders.easy-orders.import', ['id' => $easyOrder->id]) }}" method="post" class="d-inline-block">
                            @csrf
                            <button type="submit" class="btn btn-success" @if($easyOrder->status === 'imported') disabled @endif>
                                {{ translate('import_now') }}
                            </button>
                        </form>

                        @if($easyOrder->status === 'pending')
                            <form action="{{ route('admin.orders.easy-orders.reject', ['id' => $easyOrder->id]) }}" method="post" class="d-inline-block ms-2">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">
                                    {{ translate('reject') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('parsed_products_from_sku') }}</h5>
                    </div>
                    <div class="card-body">
                        @if(empty($parsedItems))
                            <p class="mb-0 text-muted">{{ translate('no_products_could_be_parsed_from_sku') }}</p>
                        @else
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th>{{ translate('code') }}</th>
                                    <th>{{ translate('quantity') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($parsedItems as $item)
                                    <tr>
                                        <td>{{ $item['code'] }}</td>
                                        <td>{{ $item['quantity'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('raw_payload') }}</h5>
                    </div>
                    <div class="card-body">
                        <pre class="mb-0" style="max-height: 400px; overflow:auto; background:#f8f9fa; padding:10px;border-radius:4px;">
{{ json_encode($easyOrder->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                        </pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection




@extends('layouts.back-end.app')

@section('title', translate('city_shipping_costs'))

@section('content')
<div class="content container-fluid">
    <div class="mb-3">
        <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
            <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/shipping.png') }}" alt="">
            {{ translate('city_shipping_costs') }}
        </h2>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">{{ translate('shipping_cost_management') }}</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ translate('city_name') }}</th>
                                    <th>{{ translate('shipping_cost') }}</th>
                                    <th class="text-center">{{ translate('action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($governorates as $governorate)
                                    <tr>
                                        <td>
                                            <strong>{{ $governorate->name_ar }}</strong>
                                        </td>
                                        <td>
                                            @if($governorate->shippingCost)
                                                <form action="{{ route('admin.shipping-cost.update', $governorate->shippingCost->id) }}" method="POST" class="d-inline-flex align-items-center">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="input-group">
                                                        <input type="number" 
                                                               name="cost" 
                                                               value="{{ $governorate->shippingCost->cost }}" 
                                                               class="form-control" 
                                                               step="0.01" 
                                                               min="0" 
                                                               style="max-width: 120px;">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            {{ translate('update') }}
                                                        </button>
                                                    </div>
                                                </form>
                                            @else
                                                <form action="{{ route('admin.shipping-cost.store') }}" method="POST" class="d-inline-flex align-items-center">
                                                    @csrf
                                                    <input type="hidden" name="governorate_id" value="{{ $governorate->id }}">
                                                    <div class="input-group">
                                                        <input type="number" 
                                                               name="cost" 
                                                               value="0" 
                                                               class="form-control" 
                                                               step="0.01" 
                                                               min="0" 
                                                               style="max-width: 120px;" 
                                                               required>
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            {{ translate('set_cost') }}
                                                        </button>
                                                    </div>
                                                </form>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($governorate->shippingCost)
                                                <form action="{{ route('admin.shipping-cost.destroy', $governorate->shippingCost->id) }}" 
                                                      method="POST" 
                                                      class="d-inline"
                                                      onsubmit="return confirm('{{ translate('are_you_sure_you_want_to_delete_this_shipping_cost') }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="tio-delete"></i>
                                                        {{ translate('remove') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">{{ translate('no_cost_set') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    'use strict';
    
    // Auto-submit forms on Enter key
    $('input[name="cost"]').keypress(function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });
</script>
@endpush 
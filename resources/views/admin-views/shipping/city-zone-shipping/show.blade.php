@extends('layouts.admin.app')

@section('title', translate('view_city_zone_shipping_cost'))

@section('content')
<div class="content container-fluid">
    <div class="mb-3">
        <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
            <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/shipping.png') }}" alt="">
            {{ translate('view_city_zone_shipping_cost') }}
        </h2>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">{{ translate('shipping_cost_details') }}</h3>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.shipping.city-zone-shipping.edit', $cityZoneShipping->id) }}" 
                           class="btn btn-primary btn-sm">
                            <i class="fi fi-rr-edit"></i> {{ translate('edit') }}
                        </a>
                        <a href="{{ route('admin.shipping.city-zone-shipping.index') }}" 
                           class="btn btn-secondary btn-sm">
                            {{ translate('back_to_list') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="border rounded p-4 h-100">
                                <h5 class="mb-3 text-primary">{{ translate('city_information') }}</h5>
                                <div class="mb-3">
                                    <label class="text-muted mb-1">{{ translate('city_name') }}</label>
                                    <p class="mb-0 font-weight-bold">{{ $cityZoneShipping->governorate->name_ar ?? translate('N/A') }}</p>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted mb-1">{{ translate('city_id') }}</label>
                                    <p class="mb-0">#{{ $cityZoneShipping->governorate_id }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-4 h-100">
                                <h5 class="mb-3 text-success">{{ translate('shipping_cost_information') }}</h5>
                                <div class="mb-3">
                                    <label class="text-muted mb-1">{{ translate('shipping_cost') }}</label>
                                    <p class="mb-0 font-weight-bold h4 text-success">
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $cityZoneShipping->cost)) }}
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted mb-1">{{ translate('cost_in_base_currency') }}</label>
                                    <p class="mb-0">{{ $cityZoneShipping->cost }} {{ getDefaultCurrencyCode() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="border rounded p-4">
                                <h5 class="mb-3 text-info">{{ translate('record_information') }}</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">{{ translate('record_id') }}</label>
                                        <p class="mb-0">#{{ $cityZoneShipping->id }}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">{{ translate('created_at') }}</label>
                                        <p class="mb-0">{{ $cityZoneShipping->created_at->format('Y-m-d H:i:s') }}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">{{ translate('last_updated') }}</label>
                                        <p class="mb-0">{{ $cityZoneShipping->updated_at->format('Y-m-d H:i:s') }}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">{{ translate('time_difference') }}</label>
                                        <p class="mb-0">{{ $cityZoneShipping->updated_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="bg-light rounded p-4">
                                <h6 class="mb-3">{{ translate('quick_actions') }}</h6>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="{{ route('admin.shipping.city-zone-shipping.edit', $cityZoneShipping->id) }}" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fi fi-rr-edit"></i> {{ translate('edit_this_record') }}
                                    </a>
                                    <a href="{{ route('admin.shipping.city-zone-shipping.create') }}" 
                                       class="btn btn-outline-success btn-sm">
                                        <i class="fi fi-rr-plus"></i> {{ translate('add_new_shipping_cost') }}
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="confirmDelete()">
                                        <i class="fi fi-rr-trash"></i> {{ translate('delete_this_record') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">{{ translate('delete_confirmation') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{{ translate('are_you_sure_you_want_to_delete_this_shipping_cost') }}?</p>
                <div class="bg-light p-3 rounded mt-3">
                    <strong>{{ translate('city') }}:</strong> {{ $cityZoneShipping->governorate->name_ar ?? translate('N/A') }}<br>
                    <strong>{{ translate('cost') }}:</strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $cityZoneShipping->cost)) }}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                <form action="{{ route('admin.shipping.city-zone-shipping.destroy', $cityZoneShipping->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ translate('delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    function confirmDelete() {
        $('#deleteModal').modal('show');
    }
</script>
@endpush

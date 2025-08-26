@extends('layouts.admin.app')

@section('title', translate('edit_city_zone_shipping_cost'))

@section('content')
<div class="content container-fluid">
    <div class="mb-3">
        <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
            <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/shipping.png') }}" alt="">
            {{ translate('edit_city_zone_shipping_cost') }}
        </h2>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">{{ translate('update_shipping_cost_information') }}</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.shipping.city-zone-shipping.update', $cityZoneShipping->id) }}" method="POST" id="editCityZoneShippingForm">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="governorate_id" class="title-color">
                                        {{ translate('select_city') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="governorate_id" id="governorate_id" class="form-control select2" required>
                                        <option value="">{{ translate('select_city') }}</option>
                                        @foreach($governorates as $governorate)
                                            <option value="{{ $governorate->id }}" 
                                                {{ old('governorate_id', $cityZoneShipping->governorate_id) == $governorate->id ? 'selected' : '' }}>
                                                {{ $governorate->name_ar }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('governorate_id')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cost" class="title-color">
                                        {{ translate('shipping_cost') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                        </div>
                                        <input type="number" step="0.01" min="0" max="999999.99" 
                                               name="cost" id="cost" class="form-control" 
                                               placeholder="{{ translate('enter_shipping_cost') }}" 
                                               value="{{ old('cost', $cityZoneShipping->cost) }}" required>
                                    </div>
                                    @error('cost')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="bg-light p-3 rounded">
                                    <h6 class="mb-2">{{ translate('current_information') }}</h6>
                                    <p class="mb-1"><strong>{{ translate('city') }}:</strong> {{ $cityZoneShipping->governorate->name_ar ?? translate('N/A') }}</p>
                                    <p class="mb-1"><strong>{{ translate('current_cost') }}:</strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $cityZoneShipping->cost)) }}</p>
                                    <p class="mb-0"><strong>{{ translate('last_updated') }}:</strong> {{ $cityZoneShipping->updated_at->format('Y-m-d H:i:s') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end mt-4">
                            <a href="{{ route('admin.shipping.city-zone-shipping.index') }}" class="btn btn-secondary">
                                {{ translate('back') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fi fi-rr-disk"></i> {{ translate('update') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: "{{ translate('select_city') }}",
            allowClear: true
        });

        $('#editCityZoneShippingForm').on('submit', function(e) {
            let governorateId = $('#governorate_id').val();
            let cost = $('#cost').val();

            if (!governorateId) {
                e.preventDefault();
                toastr.error("{{ translate('please_select_a_city') }}");
                return false;
            }

            if (!cost || cost < 0) {
                e.preventDefault();
                toastr.error("{{ translate('please_enter_valid_shipping_cost') }}");
                return false;
            }
        });
    });
</script>
@endpush

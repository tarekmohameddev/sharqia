<div class="modal fade" id="add-customer" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 d-flex gap-3 justify-content-between align-items-center">
                <h3 class="modal-title">{{ translate('add_new_customer') }}</h3>
                <button type="button" class="btn-close border-0 btn-circle bg-section2 shadow-none"
                                    data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body px-20 pb-0 mb-30">
                <form action="{{route('admin.customer.add') }}" method="post" id="product_form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <label class="form-label mb-1">{{ translate('first_name') }} <span
                                        class="input-label-secondary text-danger">*</span></label>
                                <input type="text" name="f_name" class="form-control" value="{{ old('f_name') }}"
                                       placeholder="{{ translate('first_name') }}" required>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <label class="form-label mb-1">{{ translate('phone') }} <span
                                        class="input-label-secondary text-danger">*</span></label>
                                <input class="form-control"
                                       type="tel" id="exampleInputPhone" value="{{old('phone')}}" name="phone"
                                       placeholder="{{ translate('enter_phone_number') }}" required>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <label class="form-label mb-1">{{ translate('city') }} <span class="input-label-secondary text-danger">*</span></label>
                                <select name="city_id" id="city_id" class="custom-select" data-live-search="true" required>
                                    <option value="">{{ translate('select') }}</option>
                                    @foreach($governorates as $governorate)
                                        <option value="{{ $governorate->id }}">{{ $governorate->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <label class="form-label mb-1">{{ translate('seller') }} <span class="input-label-secondary text-danger">*</span></label>
                                <select name="seller_id" id="seller_id" class="custom-select" required></select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label mb-1">{{ translate('address') }}</label>
                                <input type="text" name="address" class="form-control" value="{{ old('address') }}"
                                       placeholder="{{ translate('address') }}">
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" id="submit_new_customer"
                                class="btn btn-primary max-w-120 flex-grow-1">{{ translate('submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

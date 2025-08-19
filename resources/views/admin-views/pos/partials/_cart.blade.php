<form action="{{route('admin.pos.place-order') }}" method="post" id='order-place'>
    @csrf
    <input type="hidden" name="city_id" value="{{ session('selected_city_id') }}">
    <input type="hidden" name="seller_id" value="{{ session('selected_seller_id') }}">
    <div id="cart" data-currency-symbol="{{ getCurrencySymbol() }}" data-currency-position="{{ getWebConfig('currency_symbol_position') }}">
        <div class="table-responsive pos-cart-table border">
            <table class="table table-align-middle m-0">
                <thead class="text-capitalize bg-light">
                    <tr>
                        <th class="border-0 min-w-120">{{ translate('item') }}</th>
                        <th class="border-0">{{ translate('qty') }}</th>
                        <th class="border-0">{{ translate('price') }}</th>
                        <th class="border-0 text-center">{{ translate('delete') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="pt-4 pb-60">
            <dl>
                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">{{ translate('sub_total') }} : </dt>
                    <dd class="cart-sub-total">{{ setCurrencySymbol(amount:0) }}</dd>
                </div>

                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">{{ translate('product_Discount') }} :</dt>
                    <dd class="cart-product-discount">{{ setCurrencySymbol(amount:0) }}</dd>
                </div>

                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">{{ translate('extra_Discount') }} :</dt>
                    <dd>
                        <button id="extra_discount" class="btn btn-sm p-0" type="button" data-bs-toggle="modal" data-bs-target="#add-discount">
                            <i class="fi fi-rr-pencil"></i>
                        </button>
                        <span class="cart-extra-discount">{{ setCurrencySymbol(amount:0) }}</span>
                    </dd>
                </div>

                <div class="d-flex justify-content-between">
                    <dt class="title-color gap-2 text-capitalize font-weight-normal">{{ translate('coupon_Discount') }} :</dt>
                    <dd>
                        <button id="coupon_discount" class="btn btn-sm p-0" type="button" data-bs-toggle="modal" data-bs-target="#add-coupon-discount">
                            <i class="fi fi-rr-pencil"></i>
                        </button>
                        <span class="cart-coupon-discount">{{ setCurrencySymbol(amount:0) }}</span>
                    </dd>
                </div>

                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">{{ translate('tax') }} : </dt>
                    <dd class="cart-tax-total">{{ setCurrencySymbol(amount:0) }}</dd>
                </div>

                <div class="d-flex gap-2 border-top justify-content-between pt-2">
                    <dt class="title-color text-capitalize font-weight-bold title-color">{{ translate('total') }} : </dt>
                    <dd class="font-weight-bold title-color cart-grand-total">{{ setCurrencySymbol(amount:0) }}</dd>
                </div>
            </dl>

            <div class="form-group col-12">
                <input type="hidden" class="form-control total-amount" name="amount" min="0" step="0.01" value="0" readonly>
            </div>
            <div class="p-4 bg-section rounded mt-4">
                <div>
                    <div class="text-dark d-flex mb-2">{{ translate('paid_By') }}:</div>
                    <ul class="list-unstyled option-buttons d-flex flex-wrap gap-2 align-items-center">
                        <li>
                            <input type="radio" class="paid-by-cash" id="cash" value="cash" name="type" hidden checked>
                            <label for="cash" class="btn btn-outline-dark btn-sm mb-0">{{ translate('cash') }}</label>
                        </li>
                        <li>
                            <input type="radio" value="card" id="card" name="type" hidden>
                            <label for="card" class="btn btn-outline-dark btn-sm mb-0">{{ translate('card') }}</label>
                        </li>
                        @php( $walletStatus = getWebConfig('wallet_status') ?? 0)
                        @if ($walletStatus)
                        <li class="{{ (str_contains(session('current_user'), 'walk-in-customer')) ? 'd-none':'' }}">
                            <input type="radio" value="wallet" id="wallet" name="type" hidden>
                            <label for="wallet" class="btn btn-outline-dark btn-sm mb-0">{{ translate('wallet') }}</label>
                        </li>
                        @endif
                    </ul>
                </div>
                <div class="cash-change-amount cash-change-section">
                    <div class="d-flex gap-2 justify-content-between align-items-center pt-4">
                        <dt class="text-capitalize font-weight-normal">{{ translate('Paid_Amount') }} : </dt>
                        <dd>
                            <input type="number" class="form-control text-end pos-paid-amount-element remove-spin" placeholder="{{ translate('ex') }}: 1000"
                            value="0"
                            name="paid_amount"
                            min="0"
                            data-currency-position="{{ getWebConfig('currency_symbol_position') }}"
                            data-currency-symbol="{{ getCurrencySymbol() }}">
                        </dd>
                    </div>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <dt class="text-capitalize font-weight-normal">{{ translate('Change_Amount') }} : </dt>
                        <dd class="font-weight-bold title-color pos-change-amount-element">{{ setCurrencySymbol(amount: 0) }}</dd>
                    </div>
                </div>
                <div class="cash-change-card cash-change-section d-none">
                    <div class="d-flex gap-2 justify-content-between align-items-center pt-4">
                        <dt class="text-capitalize font-weight-normal">{{ translate('Paid_Amount') }} : </dt>
                        <dd>
                            <input type="number" class="form-control text-end" placeholder="{{ translate('ex') }}: 1000" value="0" disabled>
                        </dd>
                    </div>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <dt class="text-capitalize font-weight-normal">{{ translate('Change_Amount') }} : </dt>
                        <dd class="font-weight-bold title-color">{{ setCurrencySymbol(amount: 0) }}</dd>
                    </div>
                </div>
                <div class="cash-change-wallet cash-change-section d-none">
                    <div class="d-flex gap-2 justify-content-between align-items-center pt-4">
                        <dt class="text-capitalize font-weight-normal">{{ translate('Paid_Amount') }} : <span class="badge badge-soft-danger" id="message-insufficient-balance" data-text="{{ translate('insufficient_balance') }}"></span></dt>
                        <dd>
                            <input type="number" class="form-control text-end wallet-balance-input" placeholder="{{ translate('ex') }}: 1000" value="0" disabled>
                        </dd>
                    </div>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <dt class="text-capitalize font-weight-normal">{{ translate('Change_Amount') }} : </dt>
                        <dd class="font-weight-bold title-color">{{ setCurrencySymbol(amount: 0) }}</dd>
                    </div>
                </div>

            </div>
        </div>

        <div class="d-flex gap-3 align-items-center pt-3 bottom-absolute-buttons z-1">
            <span class="btn btn-danger btn-block action-empty-cart">
                <i class="fa fa-times-circle"></i>
                {{ translate('cancel_Order') }}
            </span>

            <button id="submit_order" type="button" class="btn btn-primary btn-block m-0 action-form-submit" data-message="{{ translate('want_to_place_this_order').'?'}}" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="fa fa-shopping-bag"></i>
                {{ translate('place_Order') }}
            </button>

        </div>
    </div>
</form>

@push('script_2')
<script>
    'use strict';
    $('#type_ext_dis').on('change', function (){
        let type = $('#type_ext_dis').val();
        if(type === 'amount'){
            $('#dis_amount').attr('placeholder', 'Ex: 500');
        }else if(type === 'percent'){
            $('#dis_amount').attr('placeholder', 'Ex: 10%');
        }
    });
    $(function () {
        $('[data-bs-toggle="tooltip"]').tooltip()
    })
</script>
@endpush

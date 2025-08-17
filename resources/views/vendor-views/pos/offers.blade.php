@extends('layouts.vendor.app')

@section('title', translate('offers'))

@section('content')
<div class="content container-fluid">
    <h2 class="mb-4">{{ translate('offers') }}</h2>
    <form method="POST" action="{{ route('vendor.pos.offers.store') }}" id="offer-form">
        @csrf
        <div id="offer-rows">
            <div class="row g-3 offer-row">
                <div class="col-md-3">
                    <label class="form-label">{{ translate('product') }}</label>
                    <select name="offers[0][product_id]" class="form-control">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ translate('variant') }}</label>
                    <input type="text" name="offers[0][variant]" class="form-control" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ translate('quantity') }}</label>
                    <input type="number" name="offers[0][quantity]" class="form-control" min="1" value="1" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ translate('bundle_price') }}</label>
                    <input type="number" name="offers[0][bundle_price]" step="0.01" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ translate('gift_product') }}</label>
                    <select name="offers[0][gift_product_id]" class="form-control">
                        <option value="">{{ translate('none') }}</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-secondary" id="add-offer-row">{{ translate('add_more') }}</button>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary">{{ translate('save') }}</button>
        </div>
    </form>
    @if(count($offers))
    <hr/>
    <h4 class="mt-4">{{ translate('existing_offers') }}</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>{{ translate('product') }}</th>
                <th>{{ translate('quantity') }}</th>
                <th>{{ translate('bundle_price') }}</th>
                <th>{{ translate('gift_product') }}</th>
                <th>{{ translate('status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($offers as $offer)
            <tr>
                <td>{{ $offer['product_name'] ?? '' }}</td>
                <td>{{ $offer['quantity'] ?? '' }}</td>
                <td>{{ $offer['bundle_price'] ?? '' }}</td>
                <td>{{ $offer['gift_product_name'] ?? '' }}</td>
                <td>
                    <form method="POST" action="{{ route('vendor.pos.offers.status') }}">
                        @csrf
                        <input type="hidden" name="id" value="{{ $offer['id'] ?? '' }}" />
                        <label class="switcher">
                            <input class="switcher_input" type="checkbox" name="status" value="1" {{ !empty($offer['status']) ? 'checked' : '' }} onchange="this.form.submit()">
                            <span class="switcher_control"></span>
                        </label>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection

@push('script')
<script>
let offerIndex = 1;
document.getElementById('add-offer-row').addEventListener('click', function() {
    const container = document.getElementById('offer-rows');
    const template = container.querySelector('.offer-row');
    const clone = template.cloneNode(true);
    clone.querySelectorAll('input, select').forEach(function(el){
        const name = el.getAttribute('name').replace('0', offerIndex);
        el.setAttribute('name', name);
        if(el.tagName === 'INPUT') el.value = '';
    });
    container.appendChild(clone);
    offerIndex++;
});
</script>
@endpush

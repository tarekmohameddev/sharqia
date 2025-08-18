@php($formId = isset($formIdPrefix) ? $formIdPrefix . '-' . $product['id'] : $product['id'])
<div class="pos-product-item card p-3 mb-3">
    <form id="add-to-cart-form-{{ $formId }}" class="add-to-cart-details-form">
        @csrf
        <input type="hidden" name="id" value="{{ $product['id'] }}">
        <input type="hidden" name="name" value="{{ $product['name'] }}">
        <input type="hidden" name="price" value="{{ getProductPriceByType(product: $product, type: 'discounted_unit_price', result: 'float', price: $product['unit_price'], from: 'panel') }}">
        <div class="d-flex gap-3 align-items-start">
            <div class="pos-product-item_thumb position-relative">
                @if($product?->clearanceSale)
                    <div class="position-absolute badge badge-soft-warning user-select-none m-2">
                        {{ translate('Clearance_Sale') }}
                    </div>
                @endif
                <img class="img-fit aspect-1" src="{{ getStorageImages(path:$product->thumbnail_full_url, type: 'backend-product') }}" alt="{{ $product['name'] }}">
            </div>
            <div class="flex-grow-1">
                <h6 class="pos-product-item_title mb-2">{{ $product['name'] }}</h6>
                <div class="mb-2">
                    {{ getProductPriceByType(product: $product, type: 'discounted_unit_price', result: 'string', price: $product['unit_price'], from: 'panel') }}
                </div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @if(count(json_decode($product->colors)) > 0)
                        <select name="color" class="form-select form-select-sm w-auto">
                            @foreach(json_decode($product->colors) as $color)
                                <option value="{{ $color }}">{{ $color }}</option>
                            @endforeach
                        </select>
                    @endif
                    @foreach(json_decode($product->choice_options) as $key => $choice)
                        <select name="{{ $choice->name }}" class="form-select form-select-sm w-auto">
                            @foreach($choice->options as $index => $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    @endforeach
                    <input type="number" name="quantity" value="1" min="1" class="form-control form-control-sm cart-qty-field" style="width:80px;"/>
                </div>
                <button type="button" class="btn btn-primary btn-sm action-add-to-cart" data-form-id="add-to-cart-form-{{ $formId }}">{{ translate('add_to_cart') }}</button>
            </div>
        </div>
    </form>
</div>

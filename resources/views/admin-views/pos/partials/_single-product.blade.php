<div class="pos-product-item card action-select-product" data-id="{{ $product['id'] }}">
    <div class="pos-product-item_thumb position-relative">
        @if($product?->clearanceSale)
            <div class="position-absolute badge badge-soft-warning user-select-none m-2">
                {{ translate('Clearance_Sale') }}
            </div>
        @endif
        <img class="img-fit aspect-1" src="{{ getStorageImages(path:$product->thumbnail_full_url, type: 'backend-product') }}"
             alt="{{ $product['name'] }}">
    </div>

    <div class="pos-product-item_content">
        <div class="pos-product-item_title">
            {{ $product['name'] }}
        </div>
        <div class="pos-product-item_price">
            {{ getProductPriceByType(product: $product, type: 'discounted_unit_price', result: 'string', price: $product['unit_price'], from: 'panel') }}
        </div>
        <div class="pos-product-item_stock">
            @if($product['product_type'] == 'physical')
                @if($product['current_stock'] > 0)
                    <span class="text-success fz-12">{{ $product['current_stock'].' '.$product['unit'].($product['current_stock']>1?'s':'') }} {{ translate('in_stock') }}</span>
                @else
                    <span class="text-danger fz-12">{{ translate('out_of_stock') }}</span>
                @endif
            @else
                <span class="text-info fz-12">{{ translate('digital_product') }}</span>
            @endif
        </div>
        <div class="pos-product-item_actions mt-2">
            @if($product['product_type'] == 'physical' && $product['current_stock'] <= 0)
                <button class="btn btn-secondary btn-sm w-100" disabled>
                    <i class="fi fi-rr-shopping-cart"></i> {{ translate('out_of_stock') }}
                </button>
            @else
                <button class="btn btn-primary btn-sm w-100 action-direct-add-to-cart" 
                        data-product-id="{{ $product['id'] }}"
                        data-product-name="{{ $product['name'] }}"
                        data-product-price="{{ $product['unit_price'] }}"
                        data-product-image="{{ getStorageImages(path:$product->thumbnail_full_url, type: 'backend-product') }}"
                        data-product-stock="{{ $product['current_stock'] }}"
                        data-product-type="{{ $product['product_type'] }}"
                        data-product-unit="{{ $product['unit'] }}"
                        data-product-tax="{{ $product['tax'] }}"
                        data-product-tax-type="{{ $product['tax_type'] }}"
                        data-product-tax-model="{{ $product['tax_model'] }}"
                        data-product-discount="{{ getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $product['unit_price']) }}"
                        data-product-discount-type="{{ $product['discount_type'] }}"
                        data-has-variants="{{ (count(json_decode($product->colors ?? '[]')) > 0 || count(json_decode($product->choice_options ?? '[]')) > 0) ? 'true' : 'false' }}">
                    <i class="fi fi-rr-shopping-cart"></i> {{ translate('add_to_cart') }}
                </button>
                
                @if($product->activeDiscountRules && $product->activeDiscountRules->count() > 0)
                    <div class="discount-offers mt-2">
                        @foreach($product->activeDiscountRules as $rule)
                            <button class="btn btn-success btn-sm w-100 mb-1 action-add-offer-to-cart" 
                                    data-product-id="{{ $product['id'] }}"
                                    data-product-name="{{ $product['name'] }}"
                                    data-product-price="{{ $product['unit_price'] }}"
                                    data-product-image="{{ getStorageImages(path:$product->thumbnail_full_url, type: 'backend-product') }}"
                                    data-product-stock="{{ $product['current_stock'] }}"
                                    data-product-type="{{ $product['product_type'] }}"
                                    data-product-unit="{{ $product['unit'] }}"
                                    data-product-tax="{{ $product['tax'] }}"
                                    data-product-tax-type="{{ $product['tax_type'] }}"
                                    data-product-tax-model="{{ $product['tax_model'] }}"
                                    data-rule-id="{{ $rule->id }}"
                                    data-rule-quantity="{{ $rule->quantity }}"
                                    data-rule-discount-amount="{{ $rule->discount_amount }}"
                                    data-rule-discount-type="{{ $rule->discount_type }}"
                                    data-rule-gift-product-id="{{ $rule->gift_product_id }}"
                                    data-has-variants="{{ (count(json_decode($product->colors ?? '[]')) > 0 || count(json_decode($product->choice_options ?? '[]')) > 0) ? 'true' : 'false' }}">
                                <i class="fi fi-rr-tags"></i> +{{ $rule->quantity }} {{ translate('offer') }}
                                ({{ $rule->discount_display }})
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

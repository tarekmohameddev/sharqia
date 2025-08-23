<div class="pos-product-item-horizontal card action-select-product d-flex flex-row align-items-center" data-id="{{ $product['id'] }}">
    <!-- Product Image -->
    <div class="pos-product-item_thumb-horizontal position-relative">
        @if($product?->clearanceSale)
            <div class="position-absolute badge badge-soft-warning user-select-none" style="top: 5px; left: 5px; font-size: 10px;">
                {{ translate('Clearance_Sale') }}
            </div>
        @endif
        <img class="img-fit" src="{{ getStorageImages(path:$product->thumbnail_full_url, type: 'backend-product') }}"
             alt="{{ $product['name'] }}">
    </div>

    <!-- Product Info -->
    <div class="pos-product-item_content-horizontal flex-grow-1">
        <div class="pos-product-item_title-horizontal">
            {{ $product['name'] }}
        </div>
        <div class="pos-product-item_price-horizontal">
            Price: {{ getProductPriceByType(product: $product, type: 'discounted_unit_price', result: 'string', price: $product['unit_price'], from: 'panel') }}
        </div>
        
        <!-- Offer Buttons Section -->
        @if($product->activeDiscountRules && $product->activeDiscountRules->count() > 0 && ($product['product_type'] != 'physical' || $product['current_stock'] > 0))
            <div class="offers-section">
                @foreach($product->activeDiscountRules as $rule)
                    <button class="btn btn-success btn-sm offer-btn action-add-offer-to-cart" 
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
                            @if($rule->giftProduct)
                            data-gift-product-name="{{ $rule->giftProduct->name }}"
                            data-gift-product-image="{{ getStorageImages(path:$rule->giftProduct->thumbnail_full_url, type: 'backend-product') }}"
                            data-gift-product-unit="{{ $rule->giftProduct->unit }}"
                            data-gift-product-stock="{{ $rule->giftProduct->current_stock }}"
                            @endif
                            data-has-variants="{{ (count(json_decode($product->colors ?? '[]')) > 0 || count(json_decode($product->choice_options ?? '[]')) > 0) ? 'true' : 'false' }}">
                        <i class="fi fi-rr-tags"></i> +{{ $rule->quantity }} {{ translate('offer') }}
                        ({{ $rule->discount_display }})@if($rule->giftProduct) + {{ translate('gift') }}@endif
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Action Buttons -->
    <div class="pos-product-item_actions-horizontal">
        @if($product['product_type'] == 'physical' && $product['current_stock'] <= 0)
            <button class="btn btn-secondary btn-sm" disabled>
                <i class="fi fi-rr-shopping-cart"></i> {{ translate('out_of_stock') }}
            </button>
        @else
            <button class="btn btn-primary btn-sm action-direct-add-to-cart" 
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
        @endif
    </div>
</div>

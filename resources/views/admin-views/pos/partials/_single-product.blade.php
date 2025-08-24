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
        
        <!-- Quick Quantity Buttons -->
        @if(($product['product_type'] != 'physical' || $product['current_stock'] > 0))
            <div class="d-flex flex-wrap gap-2 mt-2">
                @php
                    $hasVariants = (count(json_decode($product->colors ?? '[]')) > 0 || count(json_decode($product->choice_options ?? '[]')) > 0) ? 'true' : 'false';
                @endphp
                @foreach([2,3,5,10] as $qty)
                    <button class="btn btn-outline-primary btn-sm action-add-quantity-to-cart"
                            data-quantity="{{ $qty }}"
                            data-product-id="{{ $product['id'] }}"
                            data-product-name="{{ $product['name'] }}"
                            data-product-price="{{ $product['unit_price'] }}"
                            data-product-image="{{ getStorageImages(path:$product->thumbnail_full_url, type: 'backend-product') }}"
                            data-product-stock="{{ $product['current_stock'] }}"
                            data-product-type="{{ $product['product_type'] }}"
                            data-product-category-id="{{ $product['category_id'] }}"
                            data-product-unit="{{ $product['unit'] }}"
                            data-product-tax="{{ $product['tax'] }}"
                            data-product-tax-type="{{ $product['tax_type'] }}"
                            data-product-tax-model="{{ $product['tax_model'] }}"
                            data-product-discount="{{ getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $product['unit_price']) }}"
                            data-product-discount-type="{{ $product['discount_type'] }}"
                            data-has-variants="{{ $hasVariants }}">
                        {{ $qty }} {{ $product['unit'] ?? 'kg' }}
                    </button>
                @endforeach
            </div>
        @endif

        <!-- Mobile Add to Cart Button (hidden on desktop) -->
        <div class="add-to-cart-mobile">
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
                        data-product-category-id="{{ $product['category_id'] }}"
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

    <!-- Desktop Action Buttons (hidden on mobile) -->
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
                    data-product-category-id="{{ $product['category_id'] }}"
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

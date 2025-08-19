@php
    $productPayload = [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => getProductPriceByType(product: $product, type: 'discounted_unit_price', result: 'value', price: $product['unit_price'], from: 'panel'),
        'productType' => $product['product_type'],
        'image' => getStorageImages(path:$product->thumbnail_full_url, type: 'backend-product'),
        'tax_model' => $product['tax_model'],
        'discount' => getProductDiscount(product: $product, price: $product['unit_price']),
        'has_variants' => isset($product['variations']) && count($product['variations']) > 0,
        'variations' => [],
    ];
    if(isset($product['variations']) && is_array($product['variations'])) {
        foreach($product['variations'] as $variation) {
            $productPayload['variations'][] = [
                'sku' => $variation['sku'] ?? '',
                'variant_key' => $variation['type'] ?? '',
                'price' => $variation['price'] ?? 0,
                'stock' => $variation['stock'] ?? 0,
                'is_default' => $variation['is_default'] ?? false,
            ];
        }
    }
@endphp
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

    <div class="pos-product-item_content clickable">
        <div class="pos-product-item_title">
            {{ $product['name'] }}
        </div>
        <div class="pos-product-item_price">
            {{ getProductPriceByType(product: $product, type: 'discounted_unit_price', result: 'string', price: $product['unit_price'], from: 'panel') }}
        </div>
        <button type="button" class="btn btn-primary mt-2 action-add-to-cart" data-product='@json($productPayload)'>
            {{ translate('add_to_cart') }}
        </button>
    </div>
</div>

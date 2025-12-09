<div class="card mt-3 rest-part">
    <div class="card-header">
        <div class="d-flex gap-2">
            <i class="fi fi-sr-tags"></i>
            <h3 class="mb-0">{{ translate('discount_rules') }}</h3>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="enable-discount-rules" name="enable_discount_rules"
                       {{ $product->discountRules && $product->discountRules->count() > 0 ? 'checked' : '' }}>
                <label class="form-check-label" for="enable-discount-rules">
                    {{ translate('enable_quantity_based_discount_rules') }}
                </label>
            </div>
        </div>
        
        <div id="discount-rules-section" style="display: {{ $product->discountRules && $product->discountRules->count() > 0 ? 'block' : 'none' }};">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">{{ translate('discount_rules') }}</h5>
                <button type="button" class="btn btn-primary btn-sm" id="add-discount-rule">
                    <i class="fi fi-rr-plus"></i> {{ translate('add_rule') }}
                </button>
            </div>
            
            <div id="discount-rules-container">
                @if($product->discountRules && $product->discountRules->count() > 0)
                    @foreach($product->discountRules as $index => $rule)
                        <div class="discount-rule-item border rounded p-3 mb-3">
                            <div class="row gy-3">
                                <div class="col-md-3">
                                    <label class="form-label">
                                        {{ translate('quantity') }}
                                        <span class="input-required-icon">*</span>
                                    </label>
                                    <input type="number" min="2" class="form-control" 
                                           name="discount_rules[{{ $index }}][quantity]" 
                                           value="{{ $rule->quantity }}"
                                           placeholder="{{ translate('ex: 2') }}" required>
                                    <input type="hidden" name="discount_rules[{{ $index }}][id]" value="{{ $rule->id }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">
                                        {{ translate('discount_type') }}
                                        <span class="input-required-icon">*</span>
                                    </label>
                                    <select class="form-control" name="discount_rules[{{ $index }}][discount_type]" required>
                                        <option value="flat" {{ $rule->discount_type == 'flat' ? 'selected' : '' }}>{{ translate('flat') }}</option>
                                        <option value="percent" {{ $rule->discount_type == 'percent' ? 'selected' : '' }}>{{ translate('percent') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">
                                        {{ translate('discount_amount') }}
                                        <span class="input-required-icon">*</span>
                                    </label>
                                    <input type="number" min="0" step="0.01" class="form-control" 
                                           name="discount_rules[{{ $index }}][discount_amount]" 
                                           value="{{ $rule->discount_amount }}"
                                           placeholder="{{ translate('ex: 10') }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ translate('gift_product') }}</label>
                                    <select class="form-control gift-product-select" name="discount_rules[{{ $index }}][gift_product_id]" data-selected-value="{{ $rule->gift_product_id }}">
                                        <option value="">{{ translate('select_gift_product') }}</option>
                                        @foreach($giftProducts as $giftProduct)
                                            <option value="{{ $giftProduct->id }}" {{ $rule->gift_product_id == $giftProduct->id ? 'selected' : '' }}>
                                                {{ $giftProduct->name }} ({{ $giftProduct->unit_price }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger btn-sm remove-discount-rule">
                                        <i class="fi fi-rr-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Template for discount rule -->
<template id="discount-rule-template">
    <div class="discount-rule-item border rounded p-3 mb-3">
        <div class="row gy-3">
            <div class="col-md-3">
                <label class="form-label">
                    {{ translate('quantity') }}
                    <span class="input-required-icon">*</span>
                </label>
                <input type="number" min="2" class="form-control" name="discount_rules[INDEX][quantity]" 
                       placeholder="{{ translate('ex: 2') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">
                    {{ translate('discount_type') }}
                    <span class="input-required-icon">*</span>
                </label>
                <select class="form-control" name="discount_rules[INDEX][discount_type]" required>
                    <option value="flat">{{ translate('flat') }}</option>
                    <option value="percent">{{ translate('percent') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    {{ translate('discount_amount') }}
                    <span class="input-required-icon">*</span>
                </label>
                <input type="number" min="0" step="0.01" class="form-control" 
                       name="discount_rules[INDEX][discount_amount]" 
                       placeholder="{{ translate('ex: 10') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    {{ translate('gift_product') }}
                    <span class="tooltip-icon cursor-pointer" data-bs-toggle="tooltip"
                          aria-label="{{ translate('optional_gift_product_to_add_with_this_rule') }}">
                        <i class="fi fi-sr-info"></i>
                    </span>
                </label>
                <select class="form-control gift-product-select" name="discount_rules[INDEX][gift_product_id]">
                    <option value="">{{ translate('select_gift_product') }}</option>
                    <!-- Gift products will be loaded via AJAX -->
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger btn-sm remove-discount-rule">
                    <i class="fi fi-rr-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

@push('script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let discountRuleIndex = {{ $product->discountRules ? $product->discountRules->count() : 0 }};

    // Toggle discount rules section
    document.getElementById('enable-discount-rules').addEventListener('change', function() {
        const section = document.getElementById('discount-rules-section');
        section.style.display = this.checked ? 'block' : 'none';
    });

    // Add discount rule
    document.getElementById('add-discount-rule').addEventListener('click', function() {
        addDiscountRule();
    });

    // Remove discount rule for existing rules
    document.querySelectorAll('.remove-discount-rule').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.discount-rule-item').remove();
        });
    });

    // Gift products for existing selects are already populated server-side

    function addDiscountRule() {
        const template = document.getElementById('discount-rule-template');
        const container = document.getElementById('discount-rules-container');
        
        // Clone template content
        const clone = template.content.cloneNode(true);
        
        // Replace INDEX with actual index
        const inputs = clone.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name) {
                input.name = input.name.replace('INDEX', discountRuleIndex);
            }
        });
        
        // Add remove functionality
        const removeBtn = clone.querySelector('.remove-discount-rule');
        removeBtn.addEventListener('click', function() {
            this.closest('.discount-rule-item').remove();
        });
        
        // Load gift products for the select
        loadGiftProducts(clone.querySelector('.gift-product-select'));
        
        container.appendChild(clone);
        discountRuleIndex++;
    }

    function loadGiftProducts(selectElement, selectedValue = null) {
        // Load gift products via AJAX
        fetch('{{ route("admin.products.gift-products") }}')
            .then(response => response.json())
            .then(data => {
                data.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = product.name + ' (' + product.unit_price + ')';
                    if (selectedValue && selectedValue == product.id) {
                        option.selected = true;
                    }
                    selectElement.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading gift products:', error);
            });
    }
});
</script>
@endpush 
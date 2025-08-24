<div class="card mt-3 rest-part">
    <div class="card-header">
        <div class="d-flex gap-2">
            <i class="fi fi-sr-tags"></i>
            <h3 class="mb-0">{{ translate('category_discount_rules') }}</h3>
            <span class="tooltip-icon cursor-pointer" data-bs-toggle="tooltip"
                  aria-label="{{ translate('create_quantity_based_discount_rules_for_this_category') }}"
                  data-bs-title="{{ translate('create_quantity_based_discount_rules_for_this_category') }}">
                <i class="fi fi-sr-info"></i>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="enable-category-discount-rules" name="enable_category_discount_rules" {{ isset($category) && $category->discountRules && $category->discountRules->count() ? 'checked' : '' }}>
                <label class="form-check-label" for="enable-category-discount-rules">
                    {{ translate('enable_quantity_based_discount_rules') }}
                </label>
            </div>
        </div>
        <div id="category-discount-rules-section" style="display: {{ isset($category) && $category->discountRules && $category->discountRules->count() ? 'block' : 'none' }};">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">{{ translate('discount_rules') }}</h5>
                <button type="button" class="btn btn-primary btn-sm" id="add-category-discount-rule">
                    <i class="fi fi-rr-plus"></i> {{ translate('add_rule') }}
                </button>
            </div>

            <div id="category-discount-rules-container">
                @if(isset($category) && $category->discountRules)
                    @foreach($category->discountRules as $index => $rule)
                        <div class="discount-rule-item border rounded p-3 mb-3">
                            <div class="row gy-3">
                                <div class="col-md-3">
                                    <label class="form-label">{{ translate('quantity') }} <span class="input-required-icon">*</span></label>
                                    <input type="number" min="2" class="form-control" name="category_discount_rules[{{ $index }}][quantity]" value="{{ $rule->quantity }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ translate('discount_amount') }} <span class="input-required-icon">*</span></label>
                                    <input type="number" min="0" step="0.01" class="form-control" name="category_discount_rules[{{ $index }}][discount_amount]" value="{{ $rule->discount_amount }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ translate('gift_product') }}</label>
                                    <select class="form-control category-gift-product-select" name="category_discount_rules[{{ $index }}][gift_product_id]">
                                        <option value="">{{ translate('select_gift_product') }}</option>
                                        @if($rule->giftProduct)
                                            <option value="{{ $rule->giftProduct->id }}" selected>{{ $rule->giftProduct->name }}</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger btn-sm remove-category-discount-rule">
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

<template id="category-discount-rule-template">
    <div class="discount-rule-item border rounded p-3 mb-3">
        <div class="row gy-3">
            <div class="col-md-3">
                <label class="form-label">{{ translate('quantity') }} <span class="input-required-icon">*</span></label>
                <input type="number" min="2" class="form-control" name="category_discount_rules[INDEX][quantity]" placeholder="{{ translate('ex: 5') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ translate('discount_amount') }} <span class="input-required-icon">*</span></label>
                <input type="number" min="0" step="0.01" class="form-control" name="category_discount_rules[INDEX][discount_amount]" placeholder="{{ translate('ex: 20') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ translate('gift_product') }}</label>
                <select class="form-control category-gift-product-select" name="category_discount_rules[INDEX][gift_product_id]">
                    <option value="">{{ translate('select_gift_product') }}</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger btn-sm remove-category-discount-rule">
                    <i class="fi fi-rr-trash"></i>
                </button>
            </div>
        </div>
    </div>
    
</template>

@push('script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let categoryDiscountRuleIndex = document.querySelectorAll('#category-discount-rules-container .discount-rule-item').length || 0;

    const toggleSection = () => {
        document.getElementById('category-discount-rules-section').style.display = document.getElementById('enable-category-discount-rules').checked ? 'block' : 'none';
    };
    const enableCheckbox = document.getElementById('enable-category-discount-rules');
    if (enableCheckbox) {
        enableCheckbox.addEventListener('change', toggleSection);
    }

    const addBtn = document.getElementById('add-category-discount-rule');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            const template = document.getElementById('category-discount-rule-template');
            const container = document.getElementById('category-discount-rules-container');
            const clone = template.content.cloneNode(true);
            clone.querySelectorAll('input, select').forEach(el => {
                if (el.name) el.name = el.name.replace('INDEX', categoryDiscountRuleIndex);
            });
            const giftSelect = clone.querySelector('.category-gift-product-select');
            loadGiftProductsForSelect(giftSelect);
            const removeBtn = clone.querySelector('.remove-category-discount-rule');
            removeBtn.addEventListener('click', function() {
                this.closest('.discount-rule-item').remove();
            });
            container.appendChild(clone);
            categoryDiscountRuleIndex++;
        });
    }

    document.querySelectorAll('.category-gift-product-select').forEach(loadGiftProductsForSelect);

    function loadGiftProductsForSelect(selectElement) {
        if (!selectElement) return;
        if (selectElement.dataset.loaded === '1') return;
        fetch('{{ route("admin.products.gift-products") }}')
            .then(response => response.json())
            .then(data => {
                const hasSelected = !!selectElement.querySelector('option[selected]');
                data.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.id;
                    option.textContent = p.name + ' (' + p.unit_price + ')';
                    selectElement.appendChild(option);
                });
                selectElement.dataset.loaded = '1';
            })
            .catch(() => {});
    }
});
</script>
@endpush


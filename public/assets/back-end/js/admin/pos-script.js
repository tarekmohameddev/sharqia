"use strict";

// Client-side cart management
let clientCart = {
    items: [],
    subtotal: 0,
    totalTax: 0,
    discountOnProduct: 0,
    extraDiscount: 0,
    couponDiscount: 0,
    shippingCost: 0,
    total: 0
};

// Initialize client cart from session if exists
function initializeClientCart() {
    const savedCart = sessionStorage.getItem('pos_client_cart');
    if (savedCart) {
        clientCart = JSON.parse(savedCart);
        // Ensure shipping cost property exists for backward compatibility
        if (typeof clientCart.shippingCost === 'undefined') {
            clientCart.shippingCost = 0;
        }
        updateShippingCost();
        updateCartDisplay();
    } else {
        // Initialize with empty cart if no saved cart exists
        updateShippingCost();
        updateCartDisplay();
    }
}

// Save cart to session storage
function saveClientCart() {
    sessionStorage.setItem('pos_client_cart', JSON.stringify(clientCart));
}

// Update shipping cost based on selected city
function updateShippingCost() {
    // Get shipping cost from session
    const sessionShippingCost = $('#session-shipping-cost').data('value') || 0;
    clientCart.shippingCost = parseFloat(sessionShippingCost);
}

// Add product to client cart
function addToClientCart(productData) {
    const existingItemIndex = clientCart.items.findIndex(item => 
        item.id === productData.id && item.variant === (productData.variant || '')
    );

    if (existingItemIndex !== -1) {
        // Update existing item quantity
        clientCart.items[existingItemIndex].quantity += 1;
    } else {
        // Add new item
        const newItem = {
            id: productData.id,
            name: productData.name,
            price: parseFloat(productData.price),
            image: productData.image,
            quantity: 1,
            productType: productData.productType,
            unit: productData.unit || '',
            tax: parseFloat(productData.tax || 0),
            taxType: productData.taxType || 'percent',
            taxModel: productData.taxModel || 'exclude',
            discount: parseFloat(productData.discount || 0),
            discountType: productData.discountType || 'flat',
            variant: productData.variant || '',
            variations: productData.variations || [],
            stock: parseInt(productData.stock || 0)
        };
        clientCart.items.push(newItem);
    }

    calculateCartTotals();
    updateCartDisplay();
    saveClientCart();
    
    // Show success message
    toastMagic.success(
        $("#message-item-has-been-added-in-your-cart").data("text"), '',
        {
            CloseButton: true,
            ProgressBar: true,
        }
    );
}

// Add offer to client cart
function addOfferToClientCart(button) {
    const productData = {
        id: parseInt(button.data('product-id')),
        name: button.data('product-name'),
        price: parseFloat(button.data('product-price')),
        image: button.data('product-image'),
        productType: button.data('product-type'),
        unit: button.data('product-unit'),
        tax: parseFloat(button.data('product-tax') || 0),
        taxType: button.data('product-tax-type'),
        taxModel: button.data('product-tax-model'),
        stock: parseInt(button.data('product-stock') || 0),
        variant: '',
        variations: []
    };

    const ruleData = {
        id: parseInt(button.data('rule-id')),
        quantity: parseInt(button.data('rule-quantity')),
        discountAmount: parseFloat(button.data('rule-discount-amount')),
        discountType: button.data('rule-discount-type'),
        giftProductId: button.data('rule-gift-product-id') || null
    };

    // Get gift product data from button attributes if exists
    const giftProductData = ruleData.giftProductId ? {
        id: parseInt(ruleData.giftProductId),
        name: button.data('gift-product-name'),
        image: button.data('gift-product-image'),
        unit: button.data('gift-product-unit'),
        stock: parseInt(button.data('gift-product-stock') || 0)
    } : null;

    // Calculate discount
    const totalPrice = productData.price * ruleData.quantity;
    let unitDiscount = 0;
    
    if (ruleData.discountType === 'percent') {
        unitDiscount = (productData.price * ruleData.discountAmount) / 100;
    } else {
        unitDiscount = ruleData.discountAmount / ruleData.quantity;
    }

    // Create unique offer key
    const offerKey = 'offer_' + ruleData.id + '_' + Date.now();

    // Prepare items to add
    const itemsToAdd = [];

    // Add main product with offer discount
    const mainOfferItem = {
        id: productData.id,
        name: productData.name + ' (+' + ruleData.quantity + ' Offer)',
        price: productData.price,
        image: productData.image,
        quantity: ruleData.quantity,
        productType: productData.productType,
        unit: productData.unit,
        tax: productData.tax,
        taxType: productData.taxType,
        taxModel: productData.taxModel,
        discount: unitDiscount,
        discountType: 'flat',
        variant: '',
        variations: [],
        stock: productData.stock,
        isOffer: true,
        offerKey: offerKey,
        isGift: false,
        ruleId: ruleData.id,
        isLocked: true // Quantity cannot be changed
    };

    itemsToAdd.push(mainOfferItem);

    // Add gift product if exists (using preloaded data)
    if (giftProductData && giftProductData.name) {
        const giftItem = {
            id: giftProductData.id,
            name: giftProductData.name + ' (Gift)',
            price: 0,
            image: giftProductData.image,
            quantity: 1,
            productType: 'physical',
            unit: giftProductData.unit,
            tax: 0,
            taxType: 'flat',
            taxModel: 'exclude',
            discount: 0,
            discountType: 'flat',
            variant: '',
            variations: [],
            stock: giftProductData.stock,
            isOffer: true,
            offerKey: offerKey,
            isGift: true,
            ruleId: ruleData.id,
            isLocked: true
        };

        itemsToAdd.push(giftItem);
    }

    // Add all items to cart at once
    itemsToAdd.forEach(item => {
        clientCart.items.push(item);
    });

    calculateCartTotals();
    updateCartDisplay();
    saveClientCart();
    
    // Show success message
    const offerMessage = giftProductData && giftProductData.name ? 
        "Offer with gift added to cart successfully!" : 
        "Offer added to cart successfully!";
        
    toastMagic.success(
        offerMessage, '',
        {
            CloseButton: true,
            ProgressBar: true,
        }
    );
}

// Remove item from client cart
function removeFromClientCart(productId, variant = '', offerKey = '', isOffer = false) {
    if (isOffer && offerKey) {
        // Remove all items with the same offer key
        clientCart.items = clientCart.items.filter(item => 
            item.offerKey !== offerKey
        );
    } else {
        // Remove specific item
        clientCart.items = clientCart.items.filter(item => 
            !(item.id === productId && item.variant === variant)
        );
    }
    
    updateShippingCost();
    calculateCartTotals();
    updateCartDisplay();
    saveClientCart();
    
    toastMagic.info($("#message-item-has-been-removed-from-cart").data("text"));
}

// Update item quantity in client cart
function updateClientCartQuantity(productId, variant = '', newQuantity) {
    const item = clientCart.items.find(item => 
        item.id === productId && item.variant === variant
    );
    
    if (item) {
        // Prevent quantity changes for locked offer items
        if (item.isLocked) {
            toastMagic.warning("Cannot change quantity of offer items. Remove the entire offer instead.");
            updateCartDisplay(); // Reset display to original values
            return;
        }
        
        if (newQuantity <= 0) {
            removeFromClientCart(productId, variant);
            return;
        }
        
        // Check stock for physical products
        if (item.productType === 'physical' && newQuantity > item.stock) {
            toastMagic.warning($("#message-sorry-stock-limit-exceeded").data("text"));
            return;
        }
        
        item.quantity = parseInt(newQuantity);
        updateShippingCost();
        calculateCartTotals();
        updateCartDisplay();
        saveClientCart();
        
        toastMagic.success($("#message-product-quantity-updated").data("text"));
    }
}

// Calculate cart totals
function calculateCartTotals() {
    clientCart.subtotal = 0;
    clientCart.totalTax = 0;
    clientCart.discountOnProduct = 0;

    clientCart.items.forEach(item => {
        const itemSubtotal = item.price * item.quantity;
        const itemDiscount = item.discount * item.quantity;
        
        clientCart.subtotal += itemSubtotal;
        clientCart.discountOnProduct += itemDiscount;
        
        // Calculate tax
        let taxAmount = 0;
        if (item.tax > 0) {
            if (item.taxType === 'percent') {
                if (item.taxModel === 'include') {
                    taxAmount = (itemSubtotal * item.tax) / (100 + item.tax);
                } else {
                    taxAmount = (itemSubtotal * item.tax) / 100;
                }
            } else {
                taxAmount = item.tax * item.quantity;
            }
        }
        clientCart.totalTax += taxAmount;
    });

    clientCart.total = clientCart.subtotal - clientCart.discountOnProduct + clientCart.totalTax + clientCart.shippingCost - clientCart.extraDiscount - clientCart.couponDiscount;
    
    if (clientCart.total < 0) {
        clientCart.total = 0;
    }
}

// Clear client cart
function clearClientCart() {
    clientCart = {
        items: [],
        subtotal: 0,
        totalTax: 0,
        discountOnProduct: 0,
        extraDiscount: 0,
        couponDiscount: 0,
        shippingCost: 0,
        total: 0
    };
    saveClientCart();
    updateCartDisplay();
    toastMagic.info($("#message-item-has-been-removed-from-cart").data("text"));
}

// Update cart display
function updateCartDisplay() {
    const cartContainer = document.getElementById('cart');
    if (!cartContainer) return;

    const currencySymbol = $('#get-currency-symbol').data('symbol') || 'EGP';
    const currencyPosition = $('#get-currency-position').data('position') || 'left';
    
    function formatCurrency(amount) {
        const formattedAmount = parseFloat(amount || 0).toFixed(2);
        return currencyPosition === 'left' ? `${currencySymbol}${formattedAmount}` : `${formattedAmount}${currencySymbol}`;
    }
    
    function getTranslation(elementId, fallback) {
        const element = document.getElementById(elementId);
        return element ? element.getAttribute('data-text') : fallback;
    }

    let cartHTML = `
        <style>
            .offer-item {
                background-color: #f8f9ff !important;
                border-left: 3px solid #28a745 !important;
            }
            .offer-item .badge {
                font-size: 0.7em;
            }
        </style>
        <div class="table-responsive pos-cart-table border">
            <table class="table table-align-middle m-0">
                <thead class="text-capitalize bg-light">
                    <tr>
                        <th class="border-0 min-w-120">${getTranslation("translate-item", "Item")}</th>
                        <th class="border-0">${getTranslation("translate-qty", "Qty")}</th>
                        <th class="border-0">${getTranslation("translate-price", "Price")}</th>
                        <th class="border-0 text-center">${getTranslation("translate-delete", "Delete")}</th>
                    </tr>
                </thead>
                <tbody>`;

    if (clientCart.items.length === 0) {
        cartHTML += `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="text-muted">${$("#message-cart-is-empty").data("text") || "Cart is empty"}</div>
                        </td>
                    </tr>`;
    } else {
        clientCart.items.forEach((item, index) => {
            const itemTotal = (item.price - item.discount) * item.quantity;
            const isOfferItem = item.isOffer || false;
            const isGiftItem = item.isGift || false;
            const isLocked = item.isLocked || false;
            
            let itemNameDisplay = item.name;
            if (isGiftItem) {
                itemNameDisplay += ' <span class="badge bg-success">Gift</span>';
            } else if (isOfferItem) {
                itemNameDisplay += ' <span class="badge bg-primary">Offer</span>';
            }
            
            cartHTML += `
                    <tr ${isOfferItem ? 'class="offer-item"' : ''}>
                        <td>
                            <div class="media d-flex align-items-center gap-10">
                                <img class="avatar avatar-sm" src="${item.image}" alt="${item.name} Image">
                                <div class="media-body">
                                    <h5 class="text-hover-primary mb-0 d-flex flex-wrap gap-2">
                                        ${(itemNameDisplay.length > 20 ? itemNameDisplay.substring(0, 20) + '...' : itemNameDisplay)}
                                        ${item.taxModel === 'include' ? '<span data-bs-toggle="tooltip" data-bs-title="Tax Included"><img class="info-img" src="/public/assets/back-end/img/info-circle.svg" alt="img"></span>' : ''}
                                    </h5>
                                    <small>${item.variant}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            ${isLocked ? 
                                `<div class="text-center"><strong>${item.quantity}</strong><br><small class="text-muted">Locked</small></div>` :
                                `<input type="number" class="form-control qty client-cart-quantity w-max-content" 
                                       value="${item.quantity}" min="1" max="${item.productType === 'physical' ? item.stock : 999}"
                                       data-product-id="${item.id}" data-variant="${item.variant}">`
                            }
                        </td>
                        <td>
                            <div>${formatCurrency(itemTotal)}</div>
                        </td>
                        <td>
                            <div class="d-flex justify-content-center">
                                <a href="javascript:" class="btn btn-danger rounded-circle icon-btn client-remove-from-cart"
                                   data-product-id="${item.id}" data-variant="${item.variant}" data-offer-key="${item.offerKey || ''}"
                                   data-is-offer="${isOfferItem}">
                                    <i class="fi fi-rr-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>`;
        });
    }

    cartHTML += `
                </tbody>
            </table>
        </div>
        <div class="pt-4 pb-60">
            <dl>
                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">${$("#translate-subtotal").data("text") || "Sub total"} : </dt>
                    <dd>${formatCurrency(clientCart.subtotal)}</dd>
                </div>
                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">${$("#translate-product-discount").data("text") || "Product Discount"} :</dt>
                    <dd>${formatCurrency(clientCart.discountOnProduct)}</dd>
                </div>
                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">${$("#translate-extra-discount").data("text") || "Extra Discount"} :</dt>
                    <dd>
                        <button id="extra_discount" class="btn btn-sm p-0" type="button" data-bs-toggle="modal" data-bs-target="#add-discount">
                            <i class="fi fi-rr-pencil"></i>
                        </button>
                        ${formatCurrency(clientCart.extraDiscount)}
                    </dd>
                </div>
                <div class="d-flex justify-content-between">
                    <dt class="title-color gap-2 text-capitalize font-weight-normal">${$("#translate-coupon-discount").data("text") || "Coupon Discount"} :</dt>
                    <dd>
                        <button id="coupon_discount" class="btn btn-sm p-0" type="button" data-bs-toggle="modal" data-bs-target="#add-coupon-discount">
                            <i class="fi fi-rr-pencil"></i>
                        </button>
                        ${formatCurrency(clientCart.couponDiscount)}
                    </dd>
                </div>
                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">${$("#translate-tax").data("text") || "Tax"} : </dt>
                    <dd>${formatCurrency(clientCart.totalTax)}</dd>
                </div>
                <div class="d-flex gap-2 justify-content-between">
                    <dt class="title-color text-capitalize font-weight-normal">${$("#translate-shipping-cost").data("text") || "Shipping Cost"} : </dt>
                    <dd>${formatCurrency(clientCart.shippingCost)}</dd>
                </div>
                <div class="d-flex gap-2 border-top justify-content-between pt-2">
                    <dt class="title-color text-capitalize font-weight-bold title-color">${$("#translate-total").data("text") || "Total"} : </dt>
                    <dd class="font-weight-bold title-color">${formatCurrency(clientCart.total)}</dd>
                </div>
            </dl>
            
            <div class="form-group col-12">
                <input type="hidden" class="form-control total-amount" name="amount" min="0" step="0.01"
                       value="${clientCart.total}" readonly>
                <input type="hidden" name="shipping_cost" value="${clientCart.shippingCost}">
            </div>
            
            <div class="p-4 bg-section rounded mt-4 d-none">
                <div>
                    <div class="text-dark d-flex mb-2">${$("#translate-paid-by").data("text") || "Paid By"}:</div>
                    <ul class="list-unstyled option-buttons d-flex flex-wrap gap-2 align-items-center">
                        <li>
                            <input type="radio" class="paid-by-cash" id="cash" value="cash" name="type" hidden checked>
                            <label for="cash" class="btn btn-outline-dark btn-sm mb-0">${$("#translate-cash").data("text") || "Cash"}</label>
                        </li>
                        <li>
                            <input type="radio" value="card" id="card" name="type" hidden>
                            <label for="card" class="btn btn-outline-dark btn-sm mb-0">${$("#translate-card").data("text") || "Card"}</label>
                        </li>
                        <li class="d-none">
                            <input type="radio" value="wallet" id="wallet" name="type" hidden>
                            <label for="wallet" class="btn btn-outline-dark btn-sm mb-0">${$("#translate-wallet").data("text") || "Wallet"}</label>
                        </li>
                    </ul>
                </div>
                <div class="cash-change-amount cash-change-section">
                    <div class="d-flex gap-2 justify-content-between align-items-center pt-4">
                        <dt class="text-capitalize font-weight-normal">${$("#translate-paid-amount").data("text") || "Paid Amount"} : </dt>
                        <dd>
                            <input type="number" class="form-control text-end pos-paid-amount-element remove-spin" 
                                   placeholder="Ex: 1000" value="${clientCart.total}" name="paid_amount"
                                   min="${clientCart.total}" data-currency-position="left" data-currency-symbol="${currencySymbol}">
                        </dd>
                    </div>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <dt class="text-capitalize font-weight-normal">${$("#translate-change-amount").data("text") || "Change Amount"} : </dt>
                        <dd class="font-weight-bold title-color pos-change-amount-element">${formatCurrency(0)}</dd>
                    </div>
                </div>
                <div class="cash-change-card cash-change-section d-none">
                    <div class="d-flex gap-2 justify-content-between align-items-center pt-4">
                        <dt class="text-capitalize font-weight-normal">${$("#translate-paid-amount").data("text") || "Paid Amount"} : </dt>
                        <dd>
                            <input type="number" class="form-control text-end" placeholder="Ex: 1000" value="${clientCart.total}" disabled>
                        </dd>
                    </div>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <dt class="text-capitalize font-weight-normal">${$("#translate-change-amount").data("text") || "Change Amount"} : </dt>
                        <dd class="font-weight-bold title-color">${formatCurrency(0)}</dd>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex gap-3 align-items-center pt-3 bottom-absolute-buttons z-1">
            ${clientCart.items.length > 0 ? `
                <span class="btn btn-danger btn-block client-empty-cart">
                    <i class="fa fa-times-circle"></i>
                    ${$("#translate-cancel-order").data("text") || "Cancel Order"}
                </span>
                <button id="submit_order" type="button" class="btn btn-primary btn-block m-0 client-place-order" 
                        data-message="${$("#translate-want-to-place-order").data("text") || "Want to place this order?"}">
                    <i class="fa fa-shopping-bag"></i>
                    ${$("#translate-place-order").data("text") || "Place Order"}
                </button>
            ` : `
                <span class="btn btn-danger btn-block" onclick="toastMagic.warning('${$("#message-cart-is-empty").data("text") || "Cart is empty"}')">
                    <i class="fa fa-times-circle"></i>
                    ${$("#translate-cancel-order").data("text") || "Cancel Order"}
                </span>
                <button type="button" class="btn btn-primary btn-block m-0" onclick="toastMagic.warning('${$("#message-cart-is-empty").data("text") || "Cart is empty"}')">
                    <i class="fa fa-shopping-bag"></i>
                    ${$("#translate-place-order").data("text") || "Place Order"}
                </button>
            `}
        </div>`;

    cartContainer.innerHTML = cartHTML;
    
    // Reinitialize event handlers
    attachClientCartEventHandlers();
    basicFunctionalityForCartSummary();
}

// Attach event handlers for client cart
function attachClientCartEventHandlers() {
    // Direct add to cart buttons
    $('.action-direct-add-to-cart').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const hasVariants = button.data('has-variants') === 'true';
        
        if (hasVariants) {
            // Show quick view for products with variants
            quickView(button.data('product-id'));
        } else {
            // Add directly to cart for simple products
            const productData = {
                id: parseInt(button.data('product-id')),
                name: button.data('product-name'),
                price: parseFloat(button.data('product-price')),
                image: button.data('product-image'),
                productType: button.data('product-type'),
                unit: button.data('product-unit'),
                tax: parseFloat(button.data('product-tax') || 0),
                taxType: button.data('product-tax-type'),
                taxModel: button.data('product-tax-model'),
                discount: parseFloat(button.data('product-discount') || 0),
                discountType: button.data('product-discount-type'),
                stock: parseInt(button.data('product-stock') || 0),
                variant: '',
                variations: []
            };
            
            // Check stock for physical products
            if (productData.productType === 'physical' && productData.stock <= 0) {
                toastMagic.warning($("#message-sorry-product-is-out-of-stock").data("text"));
                return;
            }
            
            addToClientCart(productData);
        }
    });

    // Add offer to cart buttons
    $('.action-add-offer-to-cart').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const hasVariants = button.data('has-variants') === 'true';
        
        if (hasVariants) {
            toastMagic.warning("Offers are not available for products with variants");
            return;
        }

        const requiredQuantity = parseInt(button.data('rule-quantity'));
        const availableStock = parseInt(button.data('product-stock') || 0);
        
        // Check stock for physical products
        if (button.data('product-type') === 'physical' && availableStock < requiredQuantity) {
            toastMagic.warning("Insufficient stock for this offer. Required: " + requiredQuantity + ", Available: " + availableStock);
            return;
        }

        // Add offer to cart directly
        addOfferToClientCart(button);
    });
    
    // Quantity change handlers
    $('.client-cart-quantity').off('change').on('change', function() {
        const productId = parseInt($(this).data('product-id'));
        const variant = $(this).data('variant') || '';
        const newQuantity = parseInt($(this).val());
        
        updateClientCartQuantity(productId, variant, newQuantity);
    });
    
    // Remove from cart handlers
    $('.client-remove-from-cart').off('click').on('click', function() {
        const productId = parseInt($(this).data('product-id'));
        const variant = $(this).data('variant') || '';
        const offerKey = $(this).data('offer-key') || '';
        const isOffer = $(this).data('is-offer') === true || $(this).data('is-offer') === 'true';
        
        removeFromClientCart(productId, variant, offerKey, isOffer);
    });
    
    // Clear cart handler
    $('.client-empty-cart').off('click').on('click', function() {
        Swal.fire({
            title: messageAreYouSure,
            text: $("#message-you-want-to-remove-all-items-from-cart").data("text"),
            icon: "warning",
            showCancelButton: true,
            cancelButtonColor: "#dd3333",
            confirmButtonColor: "#161853",
            cancelButtonText: getNoWord,
            confirmButtonText: getYesWord,
            reverseButtons: true,
        }).then((result) => {
            if (result.value) {
                clearClientCart();
            }
        });
    });
    
    // Place order handler
    $('.client-place-order').off('click').on('click', function() {
        if (clientCart.items.length === 0) {
            toastMagic.warning($("#message-cart-is-empty").data("text"));
            return;
        }
        
        if (checkedPaidAmount()) {
            Swal.fire({
                title: messageAreYouSure,
                icon: "warning",
                text: $(this).data("message"),
                showCancelButton: true,
                showConfirmButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                cancelButtonText: getNoWord,
                confirmButtonText: getYesWord,
                reverseButtons: true,
            }).then(function (result) {
                if (result.value) {
                    placeClientOrder();
                }
            });
        }
    });
}

// Place order with client cart data
function placeClientOrder() {
    // Validate customer information
    const customerData = {
        f_name: $('#customer_f_name').val().trim(),
        phone: $('#customer_phone').val().trim(),
        city_id: $('#customer_city_id').val(),
        seller_id: $('#customer_seller_id').val(),
        address: $('#customer_address').val().trim()
    };
    
    // Validate required fields
    if (!customerData.f_name) {
        toastMagic.error('Please enter customer first name');
        $('#customer_f_name').focus();
        return;
    }
    
    if (!customerData.phone) {
        toastMagic.error('Please enter customer phone number');
        $('#customer_phone').focus();
        return;
    }
    
    if (!customerData.city_id) {
        toastMagic.error('Please select a city');
        $('#customer_city_id').focus();
        return;
    }
    
    if (!customerData.seller_id) {
        toastMagic.error('Please select a seller');
        $('#customer_seller_id').focus();
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', $('meta[name="_token"]').attr('content'));
    formData.append('customer_id', 0); // Always 0 for new customer flow
    formData.append('cart_data', JSON.stringify(clientCart));
    formData.append('amount', clientCart.total);
    formData.append('paid_amount', $('.pos-paid-amount-element').val());
    formData.append('type', $('input[name="type"]:checked').val());
    
    // Add customer data for automatic creation/update
    formData.append('customer_data', JSON.stringify(customerData));
    formData.append('city_id', customerData.city_id);
    formData.append('seller_id', customerData.seller_id);
    
    $.ajaxSetup({
        headers: {
            "X-XSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
    });
    
    $.post({
        url: $("#route-admin-pos-place-order-direct").data("url") || $("#order-place").attr("action"),
        data: formData,
        contentType: false,
        processData: false,
        beforeSend: function () {
            $("#loading").fadeIn();
        },
        success: function (response) {
            if (Boolean(response.checkProductTypeForWalkingCustomer) === true) {
                $("#add-customer").modal("show");
                $(".alert--message-for-pos").addClass("active");
                $(".alert--message-for-pos .warning-message")
                    .empty()
                    .html(response.message);
            } else {
                // Clear client cart on successful order
                clearClientCart();
                // Clear customer form for next order
                clearCustomerForm();
                location.reload();
            }
        },
        error: function(xhr) {
            console.error('Order placement failed:', xhr);
            let errorMessage = 'Failed to place order. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            toastMagic.error(errorMessage);
        },
        complete: function () {
            $("#loading").fadeOut();
        },
    });
}

// Clear customer form for next order
function clearCustomerForm() {
    $('#customer_f_name').val('');
    $('#customer_phone').val('');
    $('#customer_city_id').val('').trigger('change');
    $('#customer_seller_id').val('').trigger('change');
    $('#customer_address').val('');
}

// Initialize client cart when document is ready
$(document).ready(function() {
    // Check if we should use client-side cart (when cart is empty or enabled)
    const shouldUseClientCart = true; // You can add conditions here if needed
    
    if (shouldUseClientCart) {
        initializeClientCart();
        attachClientCartEventHandlers();
    }
});

// Also initialize when the page loads
window.addEventListener('load', function() {
    const shouldUseClientCart = true;
    
    if (shouldUseClientCart) {
        setTimeout(() => {
            initializeClientCart();
            attachClientCartEventHandlers();
        }, 100);
    }
});

let elementViewAllHoldOrdersSearch = $(".view_all_hold_orders_search");
let getYesWord = $("#message-yes-word").data("text");
let getNoWord = $("#message-no-word").data("text");
let messageAreYouSure = $("#message-are-you-sure").data("text");

document.addEventListener("keydown", function (event) {
    if (event.altKey && event.code === "KeyO") {
        $("#submit_order").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyZ") {
        $("#payment_close").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyS") {
        $("#order_complete").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyC") {
        emptyCart();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyA") {
        $("#add_new_customer").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyN") {
        $("#submit_new_customer").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyK") {
        $("#short-cut").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyP") {
        $("#print_invoice").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyQ") {
        $("#search").focus();
        $("#-pos-search-box").css("display", "none");
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyE") {
        $("#pos-search-box").css("display", "none");
        $("#extra_discount").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyD") {
        $("#pos-search-box").css("display", "none");
        $("#coupon_discount").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyB") {
        $("#invoice_close").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyX") {
        $(".action-clear-cart").click();
        event.preventDefault();
    }
    if (event.altKey && event.code === "KeyR") {
        $(".action-new-order").click();
        event.preventDefault();
    }
});

$(".action-pos-update-quantity").on("focus", function () {
    $(this).select();
});


$(".search-bar-input").on("keyup", function () {
    $(".pos-search-card").removeClass("d-none").show();
    let name = $(".search-bar-input").val();
    let elementSearchResultBox = $(".search-result-box");
    if (name.length > 0) {
        $("#pos-search-box").removeClass("d-none").show();
        $.get({
            url: $("#route-admin-products-search-product").data("url"),
            dataType: "json",
            data: {
                name: name,
            },
            beforeSend: function () {
                $("#loading").fadeIn();
            },
            success: function (data) {
                elementSearchResultBox.empty().html(data.result);
                renderSelectProduct();
                renderQuickViewSearchFunctionality();
            },
            complete: function () {
                $("#loading").fadeOut();
            },
        });
    } else {
        elementSearchResultBox.empty().hide();
    }
});

$(".action-category-filter").on("change", (event) => {
    let getUrl = new URL(window.location.href);
    getUrl.searchParams.set("category_id", $(event.target).val());
    window.location.href = getUrl.toString();
});

function renderCustomerAmountForPay() {
    if (
        parseFloat($(".customer-wallet-balance").val()) <
        parseFloat($(".total-amount").val())
    ) {
        disableOrderPlaceButton();
        $(".wallet-balance-input").addClass("border-danger");
    } else {
        $(".wallet-balance-input").removeClass("border-danger");
    }
}

function disableOrderPlaceButton() {
    var selectedPaymentType = $('input[name="type"]:checked').val();
    if (selectedPaymentType === "wallet") {
        $(".action-form-submit").attr("disabled", true);
    } else {
        $(".action-form-submit").attr("disabled", false);
    }
}
$(".action-customer-change").on("change", function () {
    $.post({
        url: $("#route-admin-pos-change-customer").data("url"),
        data: {
            _token: $('meta[name="_token"]').attr("content"),
            user_id: $(this).val(),
        },
        beforeSend: function () {
            $("#loading").fadeIn();
        },
        success: function (data) {
            $("#cart-summary").empty().html(data.view);
            reinitializeTooltips();
            viewAllHoldOrders("keyup");
            basicFunctionalityForCartSummary();
            posUpdateQuantityFunctionality();
            removeFromCart();
            renderCustomerAmountForPay();
        },
        complete: function () {
            $("#loading").fadeOut();
        },
    });
});

$(".action-view-all-hold-orders").on("click", () => viewAllHoldOrders());
elementViewAllHoldOrdersSearch.on("input", () => viewAllHoldOrders("keyup"));

function viewAllHoldOrders(action = null) {
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
        },
    });
    $.post({
        url: $("#route-admin-pos-view-hold-orders").data("url"),
        data: {
            customer: elementViewAllHoldOrdersSearch.val(),
        },
        beforeSend: function () {
            $("#loading").fadeIn();
        },
        success: function (data) {
            $("#hold-orders-modal-content").empty().html(data.view);
            if (action !== "keyup") {
                $("#hold-orders-modal-btn").click();
            }
            $(".total_hold_orders").text(data.totalHoldOrders);
            renderViewHoldOrdersFunctionality();
            basicFunctionalityForCartSummary();
            posUpdateQuantityFunctionality();
        },
        complete: function () {
            $("#loading").fadeOut();
        },
    });
}

function renderSelectProduct() {
    $(".action-get-variant-for-already-in-cart").on("click", function () {
        getVariantForAlreadyInCart($(this).data("action"));
    });

    $(".action-add-to-cart").on("click", function (e) {
        addToCart();
    });

    $(".action-color-change").on("click", function () {
        let val = $(this).val();
        $(".color-border").removeClass("border-add");
        $("#label-" + val.id).addClass("border-add");
    });

    cartQuantityInitialize();
    getVariantPrice();
    $(".variant-change input , .cart-qty-field").on("change", function () {
        getVariantPrice();
    });
    $("#add-to-cart-form .in-cart-quantity-field").on("change", function () {
        getVariantPrice("already_in_cart");
    });

    $(".cart-qty-field").focus(function () {
        $(this).closest(".product-quantity-group").addClass("border-primary");
    });

    $(".cart-qty-field").blur(function () {
        $(this)
            .closest(".product-quantity-group")
            .removeClass("border-primary");
    });

    $(".in-cart-quantity-field").focus(function () {
        $(this).closest(".product-quantity-group").addClass("border-primary");
    });

    $(".in-cart-quantity-field").blur(function () {
        $(this)
            .closest(".product-quantity-group")
            .removeClass("border-primary");
    });
}

renderSelectProduct();
renderQuickViewFunctionality();

function renderQuickViewFunctionality() {
    $(".action-select-product").on("click", function (e) {
        // Don't trigger quick view if clicking on the add to cart button
        if ($(e.target).closest('.action-direct-add-to-cart').length) {
            return;
        }
        
        const hasVariants = $(this).find('.action-direct-add-to-cart').data('has-variants') === 'true';
        if (hasVariants) {
            quickView($(this).data("id"));
        }
    });
}

function renderQuickViewSearchFunctionality() {
    $(".action-select-search-product").on("click", function () {
        quickView($(this).data("id"));
    });
}

function basicFunctionalityForCartSummary() {
    $(".action-empty-alert-show").on("click", () => {
        toastMagic.warning($("#message-cart-is-empty").data("text"));
    });
    $(".action-clear-cart").on("click", () => {
        document.location.href = $("#route-admin-pos-clear-cart-ids").data(
            "url"
        );
    });

    $(".action-new-order").on("click", () => {
        Swal.fire({
            title: messageAreYouSure,
            text: $("#message-you-want-to-create-new-order").data("text"),
            icon: "warning",
            showCancelButton: true,
            cancelButtonColor: "#dd3333",
            confirmButtonColor: "#161853",
            cancelButtonText: getNoWord,
            confirmButtonText: getYesWord,
            reverseButtons: true,
        }).then((result) => {
            if (result.value) {
                document.location.href = $("#route-admin-pos-new-cart-id").data(
                    "url"
                );
            }
        });
    });

    $(".action-cart-change").on("click", function () {
        let value = $(this).data("cart");
        let dynamicUrl = $("#route-admin-pos-change-cart-editable").data("url");
        dynamicUrl = dynamicUrl.replace(":value", `${value}`);
        window.location.href = dynamicUrl;
    });

    $(".action-empty-cart").on("click", function () {
        Swal.fire({
            title: messageAreYouSure,
            text: $("#message-you-want-to-remove-all-items-from-cart").data(
                "text"
            ),
            icon: "warning",
            showCancelButton: true,
            cancelButtonColor: "#dd3333",
            confirmButtonColor: "#161853",
            cancelButtonText: getNoWord,
            confirmButtonText: getYesWord,
            reverseButtons: true,
        }).then((result) => {
            if (result.value) {
                $.post(
                    $("#route-admin-pos-empty-cart").data("url"),
                    {
                        _token: $('meta[name="_token"]').attr("content"),
                    },
                    function (data) {
                        $("#cart-summary").empty().html(data.view);
                        toastMagic.info(
                            $("#message-item-has-been-removed-from-cart").data(
                                "text"
                            )
                        );
                    }
                );
            }
        });
    });

    $(".action-form-submit").on("click", function () {
        if (checkedPaidAmount()) {
            Swal.fire({
                title: messageAreYouSure,
                icon: "warning",
                text: $(this).data("message"),
                showCancelButton: true,
                showConfirmButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                cancelButtonText: getNoWord,
                confirmButtonText: getYesWord,
                reverseButtons: true,
            }).then(function (result) {
                if (result.value) {
                    let formData = new FormData(
                        document.getElementById("order-place")
                    );
                    $.ajaxSetup({
                        headers: {
                            "X-XSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                                "content"
                            ),
                        },
                    });
                    $.post({
                        url: $("#order-place").attr("action"),
                        data: formData,
                        contentType: false,
                        processData: false,
                        beforeSend: function () {
                            $("#loading").fadeIn();
                        },
                        success: function (response) {
                            if (
                                Boolean(
                                    response.checkProductTypeForWalkingCustomer
                                ) === true
                            ) {
                                $("#add-customer").modal("show");
                                $(".alert--message-for-pos").addClass("active");
                                $(".alert--message-for-pos .warning-message")
                                    .empty()
                                    .html(response.message);
                            } else {
                                location.reload();
                            }
                        },
                        complete: function () {
                            $("#loading").fadeOut();
                        },
                    });
                }
            });
        }
    });

    $(".option-buttons input").on("change", function () {
        renderCustomerAmountForPay();
        let type = $(this).val();
        if ($(this).is(":checked")) {
            $(".cash-change-section").hide();
            if (type === "cash") {
                $(".cash-change-amount").show();
            } else if (type === "card") {
                $(".cash-change-card").removeClass("d-none").show();
            } else if (type === "wallet") {
                let insufficientBalanceMessage = $(
                    "#message-insufficient-balance"
                );
                let cashChangeWallet = $(".cash-change-wallet");
                if (
                    parseFloat($(".customer-wallet-balance").val()) <
                    parseFloat($(".total-amount").val())
                ) {
                    insufficientBalanceMessage.text(
                        insufficientBalanceMessage.data("text")
                    );
                }
                cashChangeWallet.show();
                cashChangeWallet.removeClass("d-none").show();
            }
        }
    });

    $(".option-buttons input").trigger("change");

    $(".pos-paid-amount-element")
        .on("keypress", function (event) {
            let charCode = event.which || event.keyCode;
            let inputValue = $(this).val();

            if ((charCode < 48 || charCode > 57) && charCode !== 46) {
                event.preventDefault();
            }

            if (charCode === 46 && inputValue.includes(".")) {
                event.preventDefault();
            }
        })
        .on("input", function () {
            let minimumAmount = parseFloat($(this).attr("min")) || 0;
            let GivenAmount = parseFloat($(this).val()) || 0;
            let currencyPosition = $(this).data("currency-position");
            let currencySymbol = $(this).data("currency-symbol");
            let decimalPoint = $('#get-decimal-point').data('decimal-point')

            if (GivenAmount < minimumAmount) {
                $("#submit_order").prop("disabled", true);
            } else {
                $("#submit_order").prop("disabled", false);
            }

            let amount = Number(GivenAmount - minimumAmount).toFixed(decimalPoint);
            let result = "";

            if (currencyPosition?.toString() === "left") {
                result = currencySymbol + amount;
            } else {
                result = amount + currencySymbol;
            }

            $(".pos-change-amount-element").text(result);
        });
}

basicFunctionalityForCartSummary();
posUpdateQuantityFunctionality();

function checkedPaidAmount() {
    let paidAmount = $(".pos-paid-amount-element");
    if ($(".paid-by-cash").prop("checked") && paidAmount.val() === "") {
        toastMagic.error($("#message-enter-valid-amount").data("text"));
        return false;
    } else if (
        $(".paid-by-cash").prop("checked") &&
        parseFloat(paidAmount.val()) < parseFloat(paidAmount.attr("min"))
    ) {
        toastMagic.error($("#message-less-than-total-amount").data("text"));
        return false;
    }
    return true;
}

$(".action-coupon-discount").on("click", function (event) {
    let couponCode = $("#coupon_code").val();
    if (couponCode.length === 0) {
        toastMagic.error($(this).data("error-message"));
        event.preventDefault();
    } else {
        $.ajaxSetup({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
            },
        });
        $.post({
            url: $("#route-admin-pos-coupon-discount").data("url"),
            data: {
                coupon_code: couponCode,
            },
            beforeSend: function () {
                $("#loading").fadeIn();
            },
            success: function (data) {
                if (data.coupon === "success") {
                    toastMagic.success(
                        $("#message-coupon-added-successfully").data("text"), '',
                        {
                            CloseButton: true,
                            ProgressBar: true,
                        }
                    );
                } else if (data.coupon === "amount_low") {
                    toastMagic.warning($("#message-this-discount-is-not-applied-for-this-amount").data("text"));
                } else if (data.coupon === "cart_empty") {
                    toastMagic.warning($("#message-cart-is-empty").data("text"));
                } else if (data.cart === "empty") {
                    toastMagic.warning($("#message-please-add-product-in-cart-before-applying-coupon").data("text"));
                } else {
                    toastMagic.warning($("#message-coupon-is-invalid").data("text"));
                }
                $("#add-coupon-discount").modal("hide");
                $("#cart").empty().html(data.view);
                reinitializeTooltips();
                basicFunctionalityForCartSummary();
                posUpdateQuantityFunctionality();
                viewAllHoldOrders("keyup");
                removeFromCart();
                $("#search").focus();
            },
            complete: function () {
                $(".modal-backdrop").addClass("d-none");
                $(".footer-offset").removeClass("modal-open");
                $("#loading").fadeOut();
            },
        });
    }
});

$(".action-extra-discount").on("click", function (event) {
    let discount = $("#dis_amount").val();
    let type = $("#type_ext_dis").val();
    if (discount.length === 0) {
        toastMagic.error($(this).data("error-message"));
        event.preventDefault();
    } else if (discount > 0) {
        $.ajaxSetup({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
            },
        });
        $.post({
            url: $("#route-admin-pos-update-discount").data("url"),
            data: {
                discount: discount,
                type: type,
            },
            beforeSend: function () {
                $("#loading").fadeIn();
            },
            success: function (data) {
                if (data.extraDiscount === "success") {
                    toastMagic.success(
                        $("#message-extra-discount-added-successfully").data(
                            "text"
                        ), '',
                        {
                            CloseButton: true,
                            ProgressBar: true,
                        }
                    );
                }else if((data.cart === "empty")){
                     toastMagic.warning(
                        $("#message-please-add-product-in-cart-before-applying-discount").data("text"), '',
                        {
                            CloseButton: true,
                            ProgressBar: true,
                        }
                    );
                }
                 else if (data.extraDiscount === "empty") {
                    toastMagic.warning(
                        $("#message-cart-is-empty").data("text"), '',
                        {
                            CloseButton: true,
                            ProgressBar: true,
                        }
                    );
                } else {
                    toastMagic.warning($("#message-this-discount-is-not-applied-for-this-amount").data("text"));
                }
                $("#add-discount").modal("hide");
                $(".modal-backdrop").addClass("d-none");
                $("#cart").empty().html(data.view);
                reinitializeTooltips();
                basicFunctionalityForCartSummary();
                posUpdateQuantityFunctionality();
                removeFromCart();
                $("#search").focus();
            },
            complete: function () {
                $(".modal-backdrop").addClass("d-none");
                $(".footer-offset").removeClass("modal-open");
                $("#loading").fadeOut();
            },
        });
    } else {
        toastMagic.warning($("#message-amount-can-not-be-negative-or-zero").data("text"));
    }
});

function posUpdateQuantityFunctionality() {
    $(".action-pos-update-quantity").on("change", function (event) {
        let getKey = $(this).data("product-key");
        let quantity = $(this).val();
        let variant = $(this).data("product-variant");
        getPOSUpdateQuantity(getKey, quantity, event, variant);
    });
}

document.addEventListener('input', function(event) {
    if (event.target.classList.contains('action-pos-update-quantity')) {
        sanitizeAndValidateQuantityInput(event.target);
    }
});
function getPOSUpdateQuantity(key, qty, e, variant = null) {
    if (qty !== "") {
        $.post(
            $("#route-admin-pos-update-quantity").data("url"),
            {
                _token: $('meta[name="_token"]').attr("content"),
                key: key,
                quantity: qty,
                variant: variant,
            },
            function (data) {
                updateQuantityResponseProcess(data);
            }
        );
    } else {
        let element = $(e.target);
        let minValue = parseInt(element.attr("min"));
        $.post(
            $("#route-admin-pos-update-quantity").data("url"),
            {
                _token: $('meta[name="_token"]').attr("content"),
                key: key,
                quantity: minValue,
                variant: variant,
            },
            function (data) {
                updateQuantityResponseProcess(data);
            }
        );
    }

    if (e.type == "keydown") {
        if (
            $.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
            (e.keyCode == 65 && e.ctrlKey === true) ||
            (e.keyCode >= 35 && e.keyCode <= 39)
        ) {
            return;
        }
        if (
            (e.shiftKey || e.keyCode < 48 || e.keyCode > 57) &&
            (e.keyCode < 96 || e.keyCode > 105)
        ) {
            e.preventDefault();
        }
    }
}

function updateQuantityResponseProcess(data) {
    if (data.productType === "physical" && data.qty < 0) {
        toastMagic.warning($("#message-product-quantity-is-not-enough").data("text"));
    }
    if (data.upQty === "zeroNegative") {
        toastMagic.warning($("#message-product-quantity-cannot-be-zero-in-cart").data("text"));
    }
    if (data.quantityUpdate == 1) {
        toastMagic.success(
            $("#message-product-quantity-updated").data("text"), '',
            {
                CloseButton: true,
                ProgressBar: true,
            }
        );
    }
    $("#cart").empty().html(data.view);
    reinitializeTooltips();
    posUpdateQuantityFunctionality();
    viewAllHoldOrders("keyup");
    removeFromCart();
}

let dropdownSelect = $("#dropdown-order-select");
dropdownSelect.on(
    "click",
    ".dropdown-menu .dropdown-item:not(:last-child)",
    function () {
        let selectedText = $(this).text();
        dropdownSelect.find(".dropdown-toggle").text(selectedText);
    }
);

$("#order-place").submit(function (eventObj) {
    eventObj.preventDefault();
    let customerValue = $("#customer").val();
    if (customerValue) {
        $(this).append(
            '<input type="hidden" name="customer[id]" value="' +
            customerValue +
            '" /> '
        );
    }
    return true;
});

$(function () {
    $(document).on("click", "input[type=number]", function () {
        this.select();
    });
});

window.addEventListener("click", function (event) {
    let searchResultBoxes =
        document.getElementsByClassName("search-result-box");
    for (let i = 0; i < searchResultBoxes.length; i++) {
        let searchResultBox = searchResultBoxes[i];
        if (
            event.target !== searchResultBox &&
            !searchResultBox.contains(event.target)
        ) {
            searchResultBox.style.display = "none";
        }
    }
});

function renderViewHoldOrdersFunctionality() {
    $(".action-cancel-customer-order").on("click", function () {
        $.ajaxSetup({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
            },
        });
        $.post({
            url: $("#route-admin-pos-cancel-order").data("url"),
            data: {
                cart_id: $(this).data("cart-id"),
            },
            beforeSend: function () {
                $("#loading").fadeIn();
            },
            success: function (data) {
                $("#hold-orders-modal-content").empty().html(data.view);
                renderViewHoldOrdersFunctionality();
                toastMagic.info(data.message);
                location.reload();
            },
            complete: function () {
                $("#loading").fadeOut();
            },
        });
    });
}

$(".action-print-pos-invoice").on("click", function () {
    const divName = $(this).data("print");
    printSpecificSectionWithPrintArea(divName);
});

function printSpecificSectionWithPrintArea(selector) {
    try {
        $(selector).printThis();
    } catch (e) {
        console.error("Printing failed:", e);
    }
}

const renderRippleEffect = () => {
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.classList.add("ripple");
        const ripple = button.getElementsByClassName("ripple")[0];
        if (ripple) {
            ripple.remove();
        }
        button.appendChild(circle);
    }
    const buttons = document.getElementsByClassName("btn-number");
    for (const button of buttons) {
        button.addEventListener("click", createRipple);
    }
};

function quickView(product_id) {
    $.ajax({
        url: $("#route-admin-pos-quick-view").data("url"),
        type: "GET",
        data: {
            product_id: product_id,
        },
        dataType: "json",
        beforeSend: function () {
            $("#loading").fadeIn();
        },
        success: function (data) {
            $("#quick-view-modal").empty().html(data.view);
            renderSelectProduct();
            renderRippleEffect();
            closeAlertMessage();
            $("#quick-view").modal("show");
        },
        complete: function () {
            $("#loading").fadeOut();
        },
    });
}

function getVariantForAlreadyInCart(event = null) {
    let current_val = parseFloat($(".in-cart-quantity-field").val());
    if (current_val > 0) {
        $(".in-cart-quantity-minus").removeAttr("disabled");
        if (event == "plus") {
            $(".in-cart-quantity-field").val(current_val + 1);
        } else {
            $(".in-cart-quantity-field").val(current_val - 1);
            if (current_val <= 2) {
                $(".in-cart-quantity-minus").attr("disabled", true);
            }
        }
    } else {
        $(".in-cart-quantity-minus").attr("disabled", true);
    }
    getVariantPrice("already_in_cart");
}

function checkAddToCartValidity() {
    var names = {};
    $("#add-to-cart-form input:radio").each(function () {
        names[$(this).attr("name")] = true;
    });
    var count = 0;
    $.each(names, function () {
        count++;
    });

    if ($("input:radio:checked").length - 1 == count) {
        return true;
    }
    return false;
}

function cartQuantityInitialize() {
    $(".btn-number").click(function (e) {
        e.preventDefault();
        let fieldName = $(this).attr("data-field");
        let type = $(this).attr("data-type");
        let input = $("input[name='" + fieldName + "']");
        let currentVal = parseInt(input.val());

        if (!isNaN(currentVal)) {
            if (type == "minus") {
                if (currentVal > input.attr("min")) {
                    input.val(currentVal - 1).change();
                }
                if (parseInt(input.val()) == input.attr("min")) {
                    $(this).attr("disabled", true);
                }
            } else if (type == "plus") {
                if (currentVal < input.attr("max")) {
                    input.val(currentVal + 1).change();
                }
                if (parseInt(input.val()) == input.attr("max")) {
                    $(this).attr("disabled", true);
                }
            }
        } else {
            input.val(0);
        }
    });

    $(".input-number").focusin(function () {
        $(this).data("oldValue", $(this).val());
    });

    $(".input-number").change(function () {
        sanitizeAndValidateQuantityInput(this);
        let minValue = parseInt($(this).attr("min"));
        let maxValue = parseInt($(this).attr("max"));
        let valueCurrent = parseInt($(this).val());
        let name = $(this).attr("name");
        if (valueCurrent >= minValue) {
            $(
                ".btn-number[data-type='minus'][data-field='" + name + "']"
            ).removeAttr("disabled");
        } else {
            sanitizeAndValidateQuantityInput(this);
            $(this).val($(this).data("oldValue"));
        }
        if (valueCurrent <= maxValue) {
            $(
                ".btn-number[data-type='plus'][data-field='" + name + "']"
            ).removeAttr("disabled");
        } else {
            $(this).val($(this).data("oldValue"));
        }
    });
     $(".cart-qty-field").on('change',function(){
        sanitizeAndValidateQuantityInput(this);
    });
    $(".input-number").keydown(function (e) {
        if (
            $.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
            (e.keyCode == 65 && e.ctrlKey === true) ||
            (e.keyCode >= 35 && e.keyCode <= 39)
        ) {
            return;
        }
        if (
            (e.shiftKey || e.keyCode < 48 || e.keyCode > 57) &&
            (e.keyCode < 96 || e.keyCode > 105)
        ) {
            e.preventDefault();
        }

        sanitizeAndValidateQuantityInput(this);
    });
}
function sanitizeAndValidateQuantityInput(inputElement) {
    inputElement.value = inputElement.value.replace(/[^0-9]/g, '').replace(/^0+/, '');
    const min = parseInt(inputElement.getAttribute("min")) || 1;
    const max = parseInt(inputElement.getAttribute("max")) || 100;
    const val = parseInt(inputElement.value);

    if (inputElement.value !== '' && (val < min || val > max)) {
        inputElement.value = min;
    }
}
function updateProductDetailsTopSection(response) {
    let formSelector = ".add-to-cart-details-form";
    $(formSelector)
        .find(".discounted-unit-price")
        .html(response?.discounted_unit_price);
    $(formSelector)
        .find(".product-details-chosen-price-amount")
        .html(response?.price);
    $(formSelector)
        .find(".product-total-unit-price")
        .html(response?.discount_amount > 0 ? response?.total_unit_price : "");

    if (response?.discount_amount > 0) {
        if (response?.discount_type === "flat") {
            $(formSelector)
                .find(".discounted_badge")
                .html(`${response?.discount}`);
        } else {
            $(formSelector)
                .find(".discounted_badge")
                .html(`- ${response?.discount}`);
        }
        $(formSelector).find(".discounted-badge-element").removeClass("d-none");
    } else {
        $(formSelector).find(".discounted-badge-element").addClass("d-none");
    }
}

function getVariantPrice(type = null) {
    if (
        $("#add-to-cart-form input[name=quantity]").val() > 0 &&
        checkAddToCartValidity()
    ) {
        $.ajaxSetup({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
            },
        });
        $.ajax({
            type: "POST",
            url:
                $("#route-admin-pos-get-variant-price").data("url") +
                (type ? "?type=" + type : ""),
            data: $("#add-to-cart-form").serializeArray(),
            success: function (response) {
                updateProductDetailsTopSection(response);

                let price;
                let tax;
                let discount;
                stockStatus(
                    response.quantity,
                    "cart-qty-field-plus",
                    "cart-qty-field"
                );
                if (response.inCartStatus == 0) {
                    $(".default-quantity-system").removeClass("d-none");
                    $(".quick-view-modal-add-cart-button").text(
                        $("#message-add-to-cart").data("text")
                    );
                    $(".in-cart-quantity-system").addClass("d--none");
                    $(".default-quantity-system").removeClass("d--none");
                    price = response.price;
                    tax = response.tax;
                    discount = response.discount * response.requestQuantity;
                } else {
                    $(".default-quantity-system").addClass("d--none");
                    $(".in-cart-quantity-system").removeClass("d--none");
                    $(".quick-view-modal-add-cart-button").text(
                        $("#message-update-to-cart").data("text")
                    );
                    if (type == null) {
                        $(".in-cart-quantity-field").val(
                            response.inCartData.quantity
                        );
                        response.inCartData.quantity == 1
                            ? buttonDisableOrEnableFunction(
                                "in-cart-quantity-minus",
                                true
                            )
                            : "";
                        price = response.inCartData.price;
                        tax = response.inCartData.tax;
                        discount =
                            response.inCartData.discount *
                            response.inCartData.quantity;
                    } else {
                        price = response.price;
                        tax = response.tax;
                        discount = response.discount * response.requestQuantity;
                    }
                    stockStatus(
                        response.quantity,
                        "in-cart-quantity-plus",
                        "in-cart-quantity-field"
                    );
                }
                setProductData(
                    "add-to-cart-details-form",
                    response.price,
                    tax,
                    response.discount_text
                );
            },
        });
    }
}

function addToCart(form_id = "add-to-cart-form") {
    if (checkAddToCartValidity()) {
        // Get form data for client-side cart
        const formData = $("#" + form_id).serializeArray();
        const productData = {};
        
        // Convert form data to object
        formData.forEach(item => {
            productData[item.name] = item.value;
        });
        
        // Get product details from form or current product being viewed
        const productId = parseInt(productData.id);
        const quantity = parseInt(productData.quantity || productData.quantity_in_cart || 1);
        
        // Extract product info from hidden fields if available
        const hiddenProductData = {
            name: productData.product_name,
            price: parseFloat(productData.product_price),
            image: productData.product_image,
            productType: productData.product_type,
            unit: productData.product_unit,
            tax: parseFloat(productData.product_tax || 0),
            taxType: productData.product_tax_type,
            taxModel: productData.product_tax_model,
            discount: parseFloat(productData.product_discount || 0),
            discountType: productData.product_discount_type,
            stock: parseInt(productData.product_stock || 0)
        };
        
        // Get product info from the modal or product card
        let productElement = $(`.action-select-product[data-id="${productId}"]`);
        if (!productElement.length) {
            // Try to get data from the modal if product card not found
            productElement = $(`#add-to-cart-form input[name="id"][value="${productId}"]`).closest('form');
            if (!productElement.length) {
                console.error('Product element not found');
                return;
            }
        }
        
        // Build variant string
        let variant = '';
        let variations = [];
        
        // Handle color
        if (productData.color) {
            variant += productData.color;
            variations.push({type: 'color', value: productData.color});
        }
        
        // Handle other variations
        Object.keys(productData).forEach(key => {
            if (key !== 'id' && key !== 'quantity' && key !== 'quantity_in_cart' && key !== 'color' && key !== '_token') {
                if (variant) variant += '-';
                variant += productData[key];
                variations.push({type: key, value: productData[key]});
            }
        });
        
        // Get product data for client cart - prefer hidden form data if available
        let productName, productPrice, productImage, productType, productUnit, productTax, productTaxType, productTaxModel, productDiscount, productDiscountType, productStock;
        
        if (hiddenProductData.name) {
            // Use data from hidden form fields (from quick view modal)
            productName = hiddenProductData.name;
            productPrice = hiddenProductData.price;
            productImage = hiddenProductData.image;
            productType = hiddenProductData.productType;
            productUnit = hiddenProductData.unit;
            productTax = hiddenProductData.tax;
            productTaxType = hiddenProductData.taxType;
            productTaxModel = hiddenProductData.taxModel;
            productDiscount = hiddenProductData.discount;
            productDiscountType = hiddenProductData.discountType;
            productStock = hiddenProductData.stock;
        } else if (productElement.hasClass('action-select-product')) {
            // Data from product card
            productName = productElement.find('.pos-product-item_title').text().trim();
            productPrice = parseFloat(productElement.data('product-price')) || 0;
            productImage = productElement.find('img').attr('src');
            productType = productElement.data('product-type');
            productUnit = productElement.data('product-unit');
            productTax = parseFloat(productElement.data('product-tax') || 0);
            productTaxType = productElement.data('product-tax-type');
            productTaxModel = productElement.data('product-tax-model');
            productDiscount = parseFloat(productElement.data('product-discount') || 0);
            productDiscountType = productElement.data('product-discount-type');
            productStock = parseInt(productElement.data('product-stock') || 0);
        } else {
            // Data from modal/form - try to get from original product card
            const originalProductElement = $(`.action-select-product[data-id="${productId}"]`);
            if (originalProductElement.length) {
                productName = originalProductElement.find('.pos-product-item_title').text().trim();
                productPrice = parseFloat(originalProductElement.data('product-price')) || 0;
                productImage = originalProductElement.find('img').attr('src');
                productType = originalProductElement.data('product-type');
                productUnit = originalProductElement.data('product-unit');
                productTax = parseFloat(originalProductElement.data('product-tax') || 0);
                productTaxType = originalProductElement.data('product-tax-type');
                productTaxModel = originalProductElement.data('product-tax-model');
                productDiscount = parseFloat(originalProductElement.data('product-discount') || 0);
                productDiscountType = originalProductElement.data('product-discount-type');
                productStock = parseInt(originalProductElement.data('product-stock') || 0);
            } else {
                // Fallback to modal data
                productName = $('.product-title').text().trim() || 'Product';
                productPrice = parseFloat($('.discounted-unit-price').text().replace(/[^\d.]/g, '')) || 0;
                productImage = $('.modal img').first().attr('src') || '';
                productType = 'physical';
                productUnit = '';
                productTax = 0;
                productTaxType = 'percent';
                productTaxModel = 'exclude';
                productDiscount = 0;
                productDiscountType = 'flat';
                productStock = 999;
            }
        }
        
        const clientProductData = {
            id: productId,
            name: productName,
            price: productPrice,
            image: productImage,
            productType: productType || 'physical',
            unit: productUnit || '',
            tax: productTax,
            taxType: productTaxType || 'percent',
            taxModel: productTaxModel || 'exclude',
            discount: productDiscount,
            discountType: productDiscountType || 'flat',
            stock: productStock,
            variant: variant,
            variations: variations
        };
        
        // Check if this exact variant already exists in cart
        const existingItem = clientCart.items.find(item => 
            item.id === productId && item.variant === variant
        );
        
        if (existingItem) {
            // Update quantity of existing item
            const newQuantity = existingItem.quantity + quantity;
            updateClientCartQuantity(productId, variant, newQuantity);
        } else {
            // Add new item with specified quantity
            clientProductData.quantity = quantity;
            clientCart.items.push(clientProductData);
            calculateCartTotals();
            updateCartDisplay();
            saveClientCart();
            
            toastMagic.success(
                $("#message-item-has-been-added-in-your-cart").data("text"), '',
                {
                    CloseButton: true,
                    ProgressBar: true,
                }
            );
        }
        
        // Close modal and clear search
        $(".close-quick-view-modal").click();
        $("#quick-view").modal("hide");
        $(".search-result-box").empty().hide();
        $("#search").val("");
        
    } else {
        Swal.fire({
            type: "info",
            title: $("#message-cart-word").data("text"),
            text: $("#message-please-choose-all-the-options").data("text"),
        });
    }
}
function removeFromCart() {
    $(".remove-from-cart").on("click", function () {
        let id = $(this).data("id");
        let variant = $(this).data("variant");
        $.post(
            $("#route-admin-pos-remove-cart").data("url"),
            {
                _token: $('meta[name="_token"]').attr("content"),
                id: id,
                variant: variant,
            },
            function (data) {
                $("#cart").empty().html(data.view);
                reinitializeTooltips();
                if (data.errors) {
                    for (let index = 0; index < data.errors.length; index++) {
                        setTimeout(() => {
                            toastMagic.error(data.errors[index].message);
                        }, index * 500);
                    }
                } else {
                    toastMagic.info($("#message-item-has-been-removed-from-cart").data("text"));
                    viewAllHoldOrders("keyup");
                }
                posUpdateQuantityFunctionality();
                posUpdateQuantityFunctionality();
                removeFromCart();
            }
        );
    });
}
removeFromCart();

$(".js-example-matcher").select2({
    matcher: matchCustom,
});

function matchCustom(params, data) {
    if ($.trim(params.term) === "") {
        return data;
    }
    if (typeof data.text === "undefined") {
        return null;
    }

    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
        let modifiedData = $.extend({}, data, true);
        return modifiedData;
    }
    return null;
}

function closeAlertMessage() {
    $(".close-alert-message").on("click", function () {
        $(".pos-alert-message").addClass("d-none");
    });
}

function productStockMessage(type) {
    $(".product-stock-message")
        .empty()
        .html($("#get-product-stock-message").data(type));
    $(".pos-alert-message").removeClass("d-none");
}
function stockStatus(
    quantity,
    buttonDisableOrEnableClassName,
    inputQuantityClassName
) {
    let stockOutMessage = $("#message-stock-out").data("text");
    let stockInMessage = $("#message-stock-id").data("text");
    let elementStockStatusInQuickView = $(".stock-status-in-quick-view");
    let inputQuantity = $("." + inputQuantityClassName);
    if (quantity <= 0) {
        elementStockStatusInQuickView
            .removeClass("text-success")
            .addClass("text-danger");
        elementStockStatusInQuickView.html(
            `<i class="tio-checkmark-circle-outlined"></i> ` + stockOutMessage
        );
        productStockMessage("out-of-stock");
        buttonDisableOrEnableFunction(buttonDisableOrEnableClassName, true);
        inputQuantity.val(1);
        $(".btn-number[data-type='minus']").attr("disabled", true);
    } else if (inputQuantity.val() >= quantity) {
        productStockMessage("limited-stock");
        buttonDisableOrEnableFunction(buttonDisableOrEnableClassName, true);
        inputQuantity.val(quantity);
    } else {
        $(".pos-alert-message").addClass("d-none");
        elementStockStatusInQuickView
            .removeClass("text-danger")
            .addClass("text-success");
        elementStockStatusInQuickView.html(
            `<i class="tio-checkmark-circle-outlined"></i> ` + stockInMessage
        );
        buttonDisableOrEnableFunction(buttonDisableOrEnableClassName, false);
    }
}

function setProductData(parentClass, price, tax, discount) {
    let updatedTax = tax.replace(/[^\d.,]/g, "");
    if (updatedTax <= 0) {
        $(".tax-container").empty();
    }
    $("." + parentClass + " " + ".set-product-tax").html(tax);
    $("." + parentClass + " " + ".set-discount-amount").html(discount);
}
$(".close-alert--message-for-pos").on("click", function () {
    $(".alert--message-for-pos").removeClass("active");
});

$(document).on("change", "#customer_city_id, #address_city_id, #add_customer_city_id", function () {
    let target;
    let cityId = $(this).val();
    let elementId = $(this).attr("id");
    
    // Determine the correct target based on the element ID
    if (elementId === "customer_city_id") {
        target = "#customer_seller_id";
    } else if (elementId === "address_city_id") {
        target = "#address_seller_id";
    } else if (elementId === "add_customer_city_id") {
        target = "#add_customer_seller_id";
    }
    
    $.get({
        url: $("#route-admin-pos-get-sellers").data("url"),
        data: {governorate_id: cityId},
        success: function (data) {
            let seller = $(target);
            seller.empty();
            seller.append('<option value="">Select Seller</option>');
            
            // Handle new response format
            let sellers = data.sellers || data; // fallback for backward compatibility
            let shippingCost = data.shipping_cost || 0;
            
            $.each(sellers, function (key, value) {
                seller.append('<option value="' + value.id + '">' + value.name + '</option>');
            });
            
            // Only store shipping cost and update cart for the main customer form
            if (elementId === "customer_city_id") {
                // Store shipping cost and city ID in session via AJAX
                $.post({
                    url: $("#route-admin-pos-set-shipping").data("url"),
                    data: {
                        city_id: cityId,
                        shipping_cost: shippingCost,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(data) {
                        // Update cart summary without page reload
                        if (data.view) {
                            $("#cart-summary").empty().html(data.view);
                            // Reinitialize any necessary functionality
                            basicFunctionalityForCartSummary();
                            posUpdateQuantityFunctionality();
                            removeFromCart();
                        }
                        
                        // Update session shipping cost data and client cart if using client-side cart
                        if (typeof clientCart !== 'undefined') {
                            // Update the session shipping cost data element
                            $('#session-shipping-cost').data('value', shippingCost);
                            
                            if (clientCart.items.length > 0) {
                                updateShippingCost();
                                calculateCartTotals();
                                updateCartDisplay();
                                saveClientCart();
                            }
                        }
                    },
                    error: function() {
                        console.error('Failed to update shipping cost');
                    }
                });
            }
        },
        error: function() {
            console.error('Failed to load sellers for selected city');
        }
    });
});

$("#add_new_customer").on("click", function () {
    $("#add-customer-card").toggleClass("d-none");
    $("#add-address-card").addClass("d-none");
});

$("#add_new_address").on("click", function () {
    $("#address_customer_id").val($("#customer").val());
    $("#add-address-card").toggleClass("d-none");
    $("#add-customer-card").addClass("d-none");
});

$("#customer").on("change", function () {
    if ($(this).val() == 0) {
        $("#add_new_address").addClass("d-none");
    } else {
        $("#add_new_address").removeClass("d-none");
    }
});

$("#customer_form").on("submit", function (e) {
    e.preventDefault();
    $.post({
        url: $("#route-admin-customer-add").data("url"),
        data: $(this).serialize(),
        beforeSend: function () {
            $("#loading").fadeIn();
        },
        success: function (data) {
            if (data.status === "exists") {
                toastMagic.warning(data.message);
            } else {
                toastMagic.success(data.message);
            }

            if (data.customer) {
                let optionExists = $("#customer option[value=" + data.customer.id + "]").length;
                let customerText = data.customer.f_name + " " + (data.customer.l_name ?? "") + " (" + data.customer.phone + ")";
                if (!optionExists) {
                    $("#customer").append('<option value="' + data.customer.id + '">' + customerText + "</option>");
                }
                $("#customer").val(data.customer.id).trigger("change");
            }

            $("#customer_form")[0].reset();
            $("#add-customer-card").addClass("d-none");
        },
        error: function (jqXHR) {
            let message =
                jqXHR.responseJSON && jqXHR.responseJSON.message
                    ? jqXHR.responseJSON.message
                    : "An unexpected error occurred";
            toastMagic.error(message);
        },
        complete: function () {
            $("#loading").fadeOut();
        },
    });
});

$("#customer_address_form").on("submit", function (e) {
    e.preventDefault();
    $.post({
        url: $("#route-admin-customer-address-add").data("url"),
        data: $(this).serialize(),
        beforeSend: function () {
            $("#loading").fadeIn();
        },
        success: function (data) {
            toastMagic.success(data.message);
            $("#customer_address_form")[0].reset();
            $("#add-address-card").addClass("d-none");
        },
        complete: function () {
            $("#loading").fadeOut();
        },
    });
});


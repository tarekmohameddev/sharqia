"use strict";

let cart = [];
let selectedCustomer = {id: 0, name: 'Walk-In Customer'};

function renderCart() {
    const tbody = document.querySelector('#cart-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    let subtotal = 0;

    cart.forEach((item, index) => {
        subtotal += item.price * item.quantity;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.name}</td>
            <td><input type="number" min="1" value="${item.quantity}" data-index="${index}" class="form-control cart-qty"></td>
            <td>${item.price.toFixed(2)}</td>
            <td class="text-center"><button class="btn btn-sm btn-danger remove-item" data-index="${index}"><i class="fi fi-rr-trash"></i></button></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('cart-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('cart-total').textContent = subtotal.toFixed(2);
}

function addToCart(product) {
    const existing = cart.find(p => p.id === product.id && p.variant === product.variant);
    if (existing) {
        existing.quantity += product.quantity;
    } else {
        cart.push(product);
    }
    renderCart();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.action-select-product').forEach(el => {
        el.addEventListener('click', () => {
            const id = parseInt(el.dataset.id);
            const name = el.querySelector('.pos-product-item_title').innerText.trim();
            const priceText = el.querySelector('.pos-product-item_price').innerText;
            const price = parseFloat(priceText.replace(/[^0-9\.]/g, '')) || 0;
            addToCart({id, name, variant: null, quantity: 1, price});
        });
    });

    const customerSelect = document.getElementById('customer');
    if (customerSelect) {
        customerSelect.addEventListener('change', function () {
            selectedCustomer = {
                id: this.value,
                name: this.options[this.selectedIndex].text.trim()
            };
        });
    }

    const cartSummary = document.getElementById('cart-summary');
    if (cartSummary) {
        cartSummary.addEventListener('change', function (e) {
            if (e.target.classList.contains('cart-qty')) {
                const idx = e.target.dataset.index;
                cart[idx].quantity = parseInt(e.target.value) || 1;
                renderCart();
            }
        });

        cartSummary.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
                const btn = e.target.closest('.remove-item');
                const idx = btn.dataset.index;
                cart.splice(idx, 1);
                renderCart();
            }
        });
    }

    const cancelBtn = document.getElementById('cancel-order');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            cart = [];
            renderCart();
        });
    }

    const placeOrderBtn = document.getElementById('place-order');
    if (placeOrderBtn) {
        placeOrderBtn.addEventListener('click', () => {
            if (!cart.length) {
                alert('Cart is empty');
                return;
            }
            const payload = {
                customer: selectedCustomer,
                payment_type: document.querySelector('input[name="type"]:checked').value,
                items: cart,
                subtotal: parseFloat(document.getElementById('cart-subtotal').textContent) || 0,
                total: parseFloat(document.getElementById('cart-total').textContent) || 0,
            };
            fetch(document.getElementById('route-admin-pos-place-order').dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify(payload),
            })
                .then(res => res.json())
                .then(() => {
                    cart = [];
                    renderCart();
                });
        });
    }
});


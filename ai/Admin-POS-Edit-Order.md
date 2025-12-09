# Admin POS Edit Order - Documentation

## Overview
This README documents the Admin POS edit-order feature and related POS local cart changes. It covers:
- Opening an existing order in POS for editing
- Local cart payload structure and initialization
- Category-based discounts and gift handling (no double-apply on edits)
- Amount and payment handling during edits (no change amount)
- Redirect behavior after saving edits
- Role permission to allow editing orders (`order_edit`)

## Key Behaviors
### Open order in POS (Edit)
- Clicking Edit from Orders list redirects to POS with `edit_order_id`.
- Server composes an edit payload and injects it into the POS page as JSON (`#edit-order-payload`).
- Client-side POS script reads the payload and preloads the cart and customer details.

Files
- `app/Http/Controllers/Admin/Order/OrderController.php` (routes edit ➜ POS)
- `app/Http/Controllers/Admin/POS/POSController.php` (builds `editPayload`)
- `resources/views/admin-views/pos/index.blade.php` (renders payload placeholders)
- `public/assets/back-end/js/admin/pos-script.js` (loads payload into local cart)

### Local cart initialization rules
- If `#edit-order-payload` exists: load items, then:
  - Reset any prior extra discounts (`extraDiscount`, `extraDiscountValue`, `extraDiscountType`, `ext_discount`, `ext_discount_type`).
  - Remove any gift items that were previously saved on the order.
  - Recompute totals; gifts will be re-added automatically by rules as needed.
- If NOT editing: the POS always opens with an empty cart (local cart cleared on page load).

Files
- `public/assets/back-end/js/admin/pos-script.js`

### Category-based discount and gifts
- Category discounts and gifts are determined by active `CategoryDiscountRule`s.
- On edit, category discounts are recomputed from current items (no double-apply).
- Gift products are excluded from category discount calculations and are auto-managed on the client.

Files
- `app/Models/CategoryDiscountRule.php`
- `app/Http/Controllers/Admin/POS/POSOrderController.php` and `Vendor/...POSOrderController.php` (reference logic)
- `public/assets/back-end/js/admin/pos-script.js` (computeCategoryDeals, ensureCategoryGifts)

### Amounts, paid amount, and change amount
- On place/update from POS during edit:
  - Server recomputes: subtotal, product discounts, tax, shipping, coupon, and extra discount (category + manual if provided) from the payload.
  - Paid amount is set equal to the computed total to avoid showing a change amount in edits.

Files
- `app/Http/Controllers/Admin/Order/OrderUpdateAction.php`

### Redirect after saving edits
- After a successful order edit, the POS clears the local cart and redirects to the Orders list.
- New orders (non-edit) still clear and reload POS by default.

Files
- `resources/views/admin-views/pos/index.blade.php` (adds `#route-admin-orders-list`)
- `public/assets/back-end/js/admin/pos-script.js` (success handler redirect)

### Permissions: order_edit
- A new role permission key `order_edit` is available under Custom Role add/edit pages.
- Controllers use `Helpers::module_permission_check('order_edit')` to gate edit/update.

Files
- `app/Enums/GlobalConstant.php` (adds `order_edit` to `EMPLOYEE_ROLE_MODULE_PERMISSION`)
- `resources/views/admin-views/custom-role/create.blade.php` (auto lists permissions)
- `app/Http/Controllers/Admin/Order/OrderController.php` (permission checks)

## Data Contracts
### Edit payload (rendered into POS page)
```json
{
  "order": {
    "id": 123,
    "couponCode": "ABC10",
    "couponDiscount": 10,
    "extraDiscount": 20,
    "shippingCost": 10,
    "orderNote": "",
    "sellerId": 5,
    "cityId": 2
  },
  "customer": {
    "f_name": "John",
    "l_name": "Doe",
    "phone": "01000000000",
    "alternative_phone": "01100000000",
    "address": "...",
    "city_id": 2,
    "seller_id": 5
  },
  "cart": {
    "items": [
      {
        "id": 1,
        "name": "Product A",
        "price": 100,
        "quantity": 2,
        "image": "...",
        "productType": "physical",
        "unit": "pc",
        "tax": 0,
        "taxType": "percent",
        "taxModel": "exclude",
        "discount": 0,
        "discountType": "discount_on_product",
        "variant": "",
        "variations": [],
        "categoryId": 10,
        "isGift": false
      }
    ],
    "coupon_discount": 10,
    "coupon_code": "ABC10",
    "coupon_bearer": "inhouse",
    "ext_discount": 20,
    "ext_discount_type": "amount"
  }
}
```
Notes
- On client load for edits, extra discount fields are cleared and gifts are removed; totals are recomputed.

## UX Notes
- POS opens empty unless editing a specific order.
- During edit save, user is redirected to Orders list with success.
- Alternative phone is supported and persisted.

## Troubleshooting
- Cart empty on edit: ensure `#edit-order-payload` renders and `pos-script.js` assigns to `clientCart` (not `window.clientCart`).
- Double discount/gifts on edit: confirm gifts are skipped in the payload, extra discount is cleared on load, and server recomputes discounts.
- Change amount visible after edit: verify `paid_amount` is set to computed total in `OrderUpdateAction`.
- Permission denied: add `order_edit` to the role under `/admin/custom-role/add`.

## Testing Checklist
1) Edit existing order with category-discount-eligible items
- POS loads items, applies category discount once, shows gifts once
- Save ➜ Orders list; order totals match POS summary; no change amount

2) Create new POS order (not editing)
- POS opens empty
- Place order; success toast; POS reloads

3) Permission
- Remove `order_edit` from role ➜ Edit blocked
- Add `order_edit` ➜ Edit allowed

## Files Reference (touched/related)
- Controllers: `Admin/Order/OrderController.php`, `Admin/Order/OrderUpdateAction.php`, `Admin/POS/POSController.php`
- Views: `resources/views/admin-views/pos/index.blade.php`
- Frontend: `public/assets/back-end/js/admin/pos-script.js`
- Models/Rules: `app/Models/CategoryDiscountRule.php`
- Permissions: `app/Enums/GlobalConstant.php`, `Helpers::module_permission_check`

## Changelog (recent)
- POS local cart: preload edit payload fix (assign to `clientCart`); clear gifts/extra discount on edit load
- Server recomputation of extra discount; `paid_amount = total` on edit
- Redirect to orders list after successful edit
- New role permission `order_edit` added to Custom Role module

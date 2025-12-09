# POS Edit Order — README

## Overview
Admins can edit existing orders using the same POS interface used to create POS orders. Opening an order in edit mode preloads items, customer details, shipping cost, discounts, and order note. Submitting applies changes transactionally, recalculates totals, adjusts stock, and resets invoice printed status.

## Entry Points & Permissions
- Orders list: Edit action is visible only for roles with `order_edit` permission
- Routes (guarded by `module:order_edit`):
  - GET `admin/orders/edit/{id}` → redirects to POS with `edit_order_id`
  - POST `admin/orders/update/{id}` → performs the update

## Request Flow
1. Click Edit on Orders list → GET `admin/orders/edit/{id}`
2. Redirect to POS: `admin/pos?edit_order_id={id}`
3. Server builds an `editPayload` (items, customer, city, seller, shipping cost, coupon/extra discounts, order note) and injects it into the POS page
4. Client-side POS JS hydrates the local cart and customer form (including order note)
5. On submit in edit mode, POS posts to `admin/orders/update/{id}` with the client cart and customer/meta fields
6. Server validates, reverts previous stock, persists new `order_details`, recomputes totals, updates `orders` fields, and returns success

## Key Files
- Routes: `routes/admin/routes.php`
- Controller (redirect): `app/Http/Controllers/Admin/Order/OrderController.php`
- POS page preload: `app/Http/Controllers/Admin/POS/POSController.php`
- Server update: `app/Http/Controllers/Admin/Order/OrderUpdateAction.php`
- View (payload injected): `resources/views/admin-views/pos/index.blade.php`
- POS JS (preload/submit): `public/assets/back-end/js/admin/pos-script.js`

## Client-side Behavior
- On detecting `#edit-order-payload`, the script:
  - Preloads cart items and resets manual extra discount to avoid double-discounting
  - Prefills customer fields: first name, phone, alternative phone, address, city, seller
  - Prefills the order note (`#order_note`) from payload
  - Ensures shipping cost aligns with server session and recalculates totals
- Vendor preselection logic when reloading sellers for a city now preserves the order’s seller if present in the list; falls back to the first seller only when no preselected seller is available

## Server-side Update Logic
- `OrderUpdateAction` responsibilities:
  - Parse client cart and validate presence of items
  - Revert stock for previous `order_details`, delete them, and recreate from client cart
  - Recompute totals (subtotal, product discount, tax, shipping, coupon, extra discount)
  - Update `orders` with: `order_amount`, `discount_amount/coupon_code`, `extra_discount/_type`, `shipping_cost`, `order_note`, `paid_amount`, `is_printed = 0`
  - Persist order meta from request/client cart:
    - `city_id`, `seller_id`, `customer_id`
    - Update/create the customer (`users`) from `customer_data` (first name, last name, phone, alternative phone)
    - Update/create a HOME `shipping_addresses` row (no `alternative_phone` column there)
    - Embed a snapshot into `orders.shipping_address_data` and include `alternative_phone` inside the JSON snapshot

## Data Notes
- `users.alternative_phone` is populated from POS
- `orders.shipping_address_data` contains `alternative_phone` for historical accuracy
- `shipping_addresses` table does not have `alternative_phone`; it is intentionally not written there

## Testing Checklist
- Permissions
  - As admin with `order_edit`, Edit action is visible; without it, hidden
- Edit flow
  - Items preloaded; changing items/quantities/discounts/shipping updates totals
  - Customer fields (name, phone, alt phone, address) can be modified and persist
  - City, seller, and order note changes persist
  - Vendor dropdown preserves the order’s seller when a city has multiple sellers
- Stock
  - Removing old items restores stock; adding new items reduces stock
- Totals
  - Server-side totals match expectations (no change amount in edit)
- Invoice
  - `is_printed` resets to 0; printing later sets it again

## Known Considerations
- Variant-level stock adjustments can be extended if needed
- Coupon revalidation can be added on update to mirror POS create behavior

## Related Docs
- `ai/Admin-Order-Edit-via-POS.md`
- `ai/Admin-POS-Edit-Order.md`


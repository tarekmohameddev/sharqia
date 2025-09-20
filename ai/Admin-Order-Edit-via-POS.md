# Admin Order Edit via POS — Reference

## Overview
This feature enables admins to edit existing orders from the Order List by reusing the POS interface and logic. Clicking Edit opens the POS in an "edit mode" preloaded with the order’s items, customer, shipping cost and discounts. Submitting updates applies changes transactionally, adjusts stock, recalculates totals, and resets invoice printed status.

Goals:
- Single, consistent interface for creating and editing POS-like orders
- Clean, isolated implementation with minimal impact on existing flows
- Permission-gated access

## Entry Points and Permissions
- Order list action: Edit button in `resources/views/admin-views/order/list.blade.php`
  - Shown only if `order_edit` permission is granted.
- Routes (guarded by `module:order_edit`):
  - GET `admin/orders/edit/{id}` — redirects to POS with `edit_order_id`
  - POST `admin/orders/update/{id}` — updates the order

Permission setup:
- The system uses module permissions via `Helpers::module_permission_check($module)`.
- Add `order_edit` to the admin role’s `module_access` (JSON) in your role management.
  - Super-admin (`admin_role_id == 1`) bypasses checks.

## Request Flow
1) From Order List, click Edit → GET `admin/orders/edit/{id}`
2) Redirected to POS: `admin/pos?edit_order_id={id}`
3) POS preloads the order payload (items, customer, shipping, coupon/extra discounts) and hydrates the client cart and customer form
4) On submit, POS JS posts to `admin/orders/update/{id}` (edit mode only)
5) Server validates/recomputes totals, reverts old stock, persists new rows, and returns success

## Touched Code (high-level)
- Routes: `routes/admin/routes.php`
  - + `admin.orders.edit` (GET), `admin.orders.update` (POST) with `module:order_edit`
- Controller: `app/Http/Controllers/Admin/Order/OrderController.php`
  - + `edit()` → permission check + redirect to POS with `edit_order_id`
  - + `update()` → permission check + delegates to action class
- Action: `app/Http/Controllers/Admin/Order/OrderUpdateAction.php`
  - Transactional update: revert old stock, replace `order_details`, recompute and update order totals, reset `is_printed`
- POS preload: `app/Http/Controllers/Admin/POS/POSController.php`
  - When `edit_order_id` present: build `editPayload` (customer, cart, order meta) and stash shipping in session
- Views:
  - `resources/views/admin-views/order/list.blade.php`: Edit button (permission-gated)
  - `resources/views/admin-views/pos/index.blade.php`: inject `editPayload`, `route-admin-orders-update`, and success messages
- JS: `public/assets/back-end/js/admin/pos-script.js`
  - On load: if edit payload present, prefill client cart and customer fields
  - On submit: post to `admin.orders.update` in edit mode; otherwise default POS place-order

## Server-Side Update Logic (OrderUpdateAction)
- Validates presence of cart items
- Reverts stock for previous `order_details` (physical items)
- Deletes existing `order_details` and re-creates from client cart
- Recomputes totals (subtotal, product discount, tax, shipping, coupon, extra discount)
- Updates order fields accordingly and resets `is_printed` to 0

Notes:
- Variant-level stock can be added to mirror POS creation if required
- Coupon revalidation can be added if you want stricter enforcement on edit

## Stock Handling
- Old items (physical products): stock is incremented back
- New items (physical products): stock is decremented
- Current implementation adjusts base product stock; extend for variant JSON stock if required

## Totals & Discounts
- Totals are recomputed server-side for consistency:
  - `total = subtotal - productDiscount + totalTax + shippingCost - couponDiscount - extraDiscount`
- Shipping cost is taken from the request or existing session value (`selected_shipping_cost`)
- Coupon and extra discount amounts are applied from client cart data; add revalidation if desired

## Translations
- Newly used messages:
  - `order_updated_successfully` — used on success
- Also present in POS view as messages:
  - `order_placed_successfully`, `order_updated_successfully`
- Add or verify these keys in your admin language JSON/PHP files if needed

## Testing Checklist
- Permission:
  - As an admin with `order_edit`, see Edit on order list; without it, the button is hidden
- Edit flow:
  - Edit a pending/confirmed order: POS opens with items preloaded
  - Change quantities/items/discounts/shipping; submit → success toast; page reloads
- Stock:
  - Deleting an item restores its stock; adding new items reduces stock accordingly
- Totals:
  - Verify order totals reflect updated items and shipping
- Invoice:
  - `is_printed` resets to 0 after update; printing later sets it again

## Known Limitations / Options
- Status guard: currently not enforced; optionally restrict editing to `pending/confirmed`
- Variant stock: currently base product only; add variant-level adjustments if needed
- Coupon validation: not revalidated in update; can mirror POS coupon checks
- Shipping changes: allowed via request; lock if needed

## Rollback
- Remove new routes from `routes/admin/routes.php`
- Remove Edit button from `resources/views/admin-views/order/list.blade.php`
- Remove preload and payload wiring from POS controller/view/JS
- Delete `OrderUpdateAction`

## File Index
- routes/admin/routes.php
- app/Http/Controllers/Admin/Order/OrderController.php
- app/Http/Controllers/Admin/Order/OrderUpdateAction.php
- app/Http/Controllers/Admin/POS/POSController.php
- resources/views/admin-views/order/list.blade.php
- resources/views/admin-views/pos/index.blade.php
- public/assets/back-end/js/admin/pos-script.js

## Future Enhancements
- Extract shared total calculation into a service to avoid duplication
- Add full variant stock synchronization like POS order placement
- Add audit trail for edits (who/when/what changed)
- Soft-compare/diff viewer in POS edit mode to highlight changes before submit

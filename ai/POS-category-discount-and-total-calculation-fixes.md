### POS category-based discount and totals fixes (Frontend + Backend)

This document summarizes the fixes applied to resolve incorrect extra discount carryover and duplicated extra discount on order placement, and to align totals between the POS screen and the saved order.

### Frontend changes (POS page)

- Reset extra discount when rules no longer apply
  - File: `public/assets/back-end/js/admin/pos-script.js`
  - Function: `calculateCartTotals()`
  - Change: Removed fallback that reused a previous `extraDiscount` when no category rules match. Now `extraDiscount = categoryDealsDiscount + manualAmount` only, computed from current items.

- Prevent change amount by capping paid amount to total
  - File: `public/assets/back-end/js/admin/pos-script.js`
  - Areas: Cart summary paid input and change calculation
  - Change: Set `max` on paid input to `total`, clamp input to total, render change as 0.

- Send manual extra discount separately to avoid double-counting
  - File: `public/assets/back-end/js/admin/pos-script.js`
  - Function: `placeClientOrder()`
  - Change: Added `manual_extra_set` flag and send `ext_discount`/`ext_discount_type` only if cashier explicitly set a manual discount; the category discount is not sent, it is recomputed server-side.

### Backend changes (Admin and Vendor POS)

- Compute category-based discount server-side and exclude gifts
  - Files: `app/Http/Controllers/Admin/POS/POSOrderController.php`, `app/Http/Controllers/Vendor/POS/POSOrderController.php`
  - Change: Aggregate counts per `category_id` for non-gift items, apply active `CategoryDiscountRule` greedily with multiples to derive category discount.

- Apply manual extra discount only when explicitly set
  - Files: same as above
  - Change: Read `manual_extra_set` from the request; if set, compute manual extra from `ext_discount_type` and `ext_discount`, otherwise treat as 0. This prevents duplicating the category discount with manual extra.

- Recompute order amount server-side and align paid amount
  - Files: same as above
  - Change: Recalculate `amount = subtotal − productDiscount + tax + shipping − coupon − (category + manual extra)`. For cash, set `paidAmount = amount` to avoid change.

### Affected files summary

- `public/assets/back-end/js/admin/pos-script.js`
  - `calculateCartTotals()`
  - Paid amount input handling and change display
  - `placeClientOrder()` request payload

- `app/Http/Controllers/Admin/POS/POSOrderController.php`
  - Import `CategoryDiscountRule`
  - Server-side totals + category discount + manual extra + paid amount alignment

- `app/Http/Controllers/Vendor/POS/POSOrderController.php`
  - Import `CategoryDiscountRule`
  - Server-side totals + category discount + manual extra + paid amount alignment

### QA checklist

- Add items from a discount-enabled category to trigger category discount; remove them and add non-discount items: extra discount resets to 0.
- Manual extra discount only: total reflects manual extra; placed order matches POS total.
- Category discount only: total reflects category discount; placed order matches POS total.
- Category + manual: backend total equals frontend total; extra discount is the sum of category and manual (no duplication).
- Shipping inclusion: total includes shipping on POS and after order placement.
- Cash payment: paid amount equals total; change amount is 0.



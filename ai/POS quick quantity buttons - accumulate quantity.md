# POS Quick Quantity Buttons – Accumulate Quantity

This feature updates the behavior of the POS quick quantity buttons (2/3/5/10) so that clicking a button adds the selected quantity to the existing cart quantity for that product instead of replacing it.

## What changed
- Previously: clicking a quick quantity button set the cart row quantity to the clicked amount (replaced previous value).
- Now: clicking a quick quantity button increases the cart row quantity by the clicked amount (accumulates). Example: click 5 → quantity 5; click 5 again → quantity 10; click 10 → quantity 20.

## Behavior
- For products with variants: clicking a quick quantity button still opens Quick View to select options (no direct add without variant selection).
- For simple products (no variants):
  - If item is already in the cart, the target quantity becomes existingQuantity + clickedQuantity.
  - If item is not yet in the cart, it is added with the clickedQuantity.
  - Stock is validated against the final target quantity for physical products.
- All existing cart totals, category-based discounts, and gifts continue to work as before.

## Implementation details
- File: `public/assets/back-end/js/admin/pos-script.js`
  - Inside `attachClientCartEventHandlers()` handler for `.action-add-quantity-to-cart`:
    - Compute `desiredQuantity = existingItem.quantity + clickedQuantity` when the item exists.
    - Validate stock against `desiredQuantity` for physical products.
    - Call `updateClientCartQuantity(productId, '', desiredQuantity)` to update an existing row.
    - For first-time adds, preserve the existing stock check and add as a new row.
- No changes required in `public/assets/back-end/js/vendor/pos-script.js` because vendor POS does not use these quick quantity buttons.

## Files touched
- `public/assets/back-end/js/admin/pos-script.js`

## Edge cases handled
- Physical products: prevent accumulated quantity from exceeding available stock; show the stock limit warning message.
- Products with variants: keep opening Quick View; no direct add.
- Category deals/gifts: continue to be applied via existing totals recomputation.

## QA checklist
- Simple product with stock:
  - Click 5 → row shows quantity 5 and totals update.
  - Click 5 again → row shows quantity 10 and totals update.
  - Click 10 → row shows quantity 20 and totals update.
- Stock boundary:
  - If accumulated quantity would exceed stock, show the stock warning and do not update/add.
- Variant product:
  - Clicking 2/3/5/10 opens Quick View, does not add directly.
- Discounts/Gifts:
  - Accumulating quantities across thresholds adds/removes gifts/discounts correctly on recompute.

## Notes
- Existing UX, UI, and other cart behaviors remain unchanged.
- If you extend the quick quantities ([2,3,5,10]), only the Blade partial needs the new values; the accumulation logic is quantity-agnostic.

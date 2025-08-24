# POS Quick Quantity Buttons on Product Card

This document describes the POS enhancement that replaces legacy per-product offer buttons with direct quantity buttons (2/3/5/10) on each product card. These buttons add the selected quantity of the product straight into the local cart and integrate seamlessly with the category-based discount and gift rules.

## What this adds
- Quick actions on each POS product card to add 2, 3, 5, or 10 units with a single click.
- For simple products (no variants): quantity is added directly to the local cart.
- For products with variants: opens Quick View to select options (no direct add without variant selection).
- Preserves `categoryId` on cart items so category-based discount rules and gifts continue to work automatically.

## Behavior
- Clicking a quick quantity button:
  - If the product has variants, opens Quick View.
  - Otherwise, checks stock for physical products and either sets the existing cart row’s quantity to the selected amount (if already present) or adds a new row with that quantity.
  - Triggers cart recomputation so that category-based discounts and gifts recalculate in real-time.

## Integration details
- Uses the existing local client cart structure in `public/assets/back-end/js/admin/pos-script.js`:
  - `clientCart.items[]` items include `categoryId`, which is consumed by `computeCategoryDeals(items, CATEGORY_RULES_MAP)`.
  - `calculateCartTotals()` recomputes `subtotal`, `discountOnProduct`, `totalTax`, and applies category deals (including ensuring gifts) before computing `total`.
- The new handler leverages existing helpers:
  - `updateClientCartQuantity(productId, variant, newQuantity)` for products already present.
  - Falls back to pushing a new item into `clientCart.items` for first-time adds.
  - Calls `calculateCartTotals()`, `updateCartDisplay()`, and `saveClientCart()`.

## UI changes
- Removed: Offer buttons block on product card.
- Added: A row of quick quantity buttons for 2, 3, 5, and 10 units.
- Buttons carry required data attributes (`data-product-category-id`, tax, discount, stock, etc.) so cart math stays consistent.

## Edge cases handled
- **Variants**: Quick buttons do not directly add; Quick View opens to pick options.
- **Stock**: For physical products, prevents setting quantity above current stock.
- **Gifts/Deals**: Category gifts are auto-added/removed via the existing `ensureCategoryGifts` path during totals recalculation.

## Files touched
- View: `resources/views/admin-views/pos/partials/_single-product.blade.php`
  - Replaced offers block with quick quantity buttons (2/3/5/10).
  - Ensures buttons include `data-product-category-id` to power category rules.
- POS JS (Admin): `public/assets/back-end/js/admin/pos-script.js`
  - Added handler for `.action-add-quantity-to-cart` inside `attachClientCartEventHandlers()`.
  - Handler sets or adds the selected quantity for simple products; opens Quick View for variant products.

## QA checklist
- On POS, for a simple product:
  - Click 2/3/5/10 → cart row quantity equals clicked amount; totals update.
  - If existing cart row was at a different quantity, it updates to exactly the selected amount.
  - Physical product with insufficient stock shows stock warning and does not add.
- For a variant product:
  - Clicking any quick quantity button opens Quick View instead of adding directly.
- Category-based discount and gifts:
  - Adding items from categories with rules adjusts the Extra Discount and gifts consistently.
  - Increasing/decreasing quantities via quick buttons adds/removes gifts as thresholds are crossed.

## Notes for developers
- If additional quick quantities are needed, extend the `[2,3,5,10]` list in the blade partial and no JS changes are required unless behavior differs per quantity.
- Keep product data attributes in sync with cart expectations (price, discount, tax model, `data-product-category-id`).



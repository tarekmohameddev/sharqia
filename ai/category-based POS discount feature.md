# Category-based POS Discounts & Gifts

This document explains the category-level quantity-based discount rules feature added to the POS local cart system.

## What this adds
- Define discount rules on main categories: threshold quantity → flat amount off; optional gift product
- POS applies rules on the client (local cart) while you add/remove items
- Gifts are auto-added/removed based on thresholds; discounts are summed into Extra Discount
- Rules support multiples and greedy highest-threshold-first application per category

## Rules semantics
- Scope: counts only products whose `category_id` equals the main category id (no subcategories)
- Discount type: flat amount only (no percent)
- Application:
  - For a given category, locate the highest threshold rule applicable to the current count
  - Apply it as many times as possible (multiples)
  - Remaining quantity is then considered for the next lower rule, and so on (greedy)
  - Gifts: added once per multiple for the rule that specifies a gift
- Stacking with manual Extra Discount: amounts are added together

## Data model
- `App/Models/CategoryDiscountRule`
  - `category_id`: main category id
  - `quantity`: threshold (e.g., 5, 10)
  - `discount_amount`: flat amount off
  - `gift_product_id` (nullable): optional gift product
  - `is_active`: bool
- Migration: `category_discount_rules` with FKs to `categories` and `products`
- Relations on `App/Models/Category`:
  - `discountRules()` and `activeDiscountRules()` (ordered by `quantity` desc)

## Admin UI
- Category add/edit forms include a “Category Discount Rules” section (`resources/views/admin-views/category/partials/_discount-rules.blade.php`).
- You can enable the section, add multiple rules, and select an optional gift product.
- Rules are saved/updated in `CategoryController@add` and `CategoryController@update`.

## POS integration
- Server: `Admin/POS/POSController@index` loads active category rules with gift products and exposes a `window.CATEGORY_RULES_MAP` to the POS page.
- Client: `public/assets/back-end/js/admin/pos-script.js`
  - Items in the local cart track `categoryId` (added to product card data attributes).
  - `computeCategoryDeals(items, CATEGORY_RULES_MAP)` computes:
    - Total category discount across categories (flat)
    - Gifts to ensure in the cart based on multiples
  - `ensureCategoryGifts(gifts)` adds missing gifts and removes extras when quantities change
  - Extra Discount is set to: `categoryDiscount + manualExtraDiscount` (manual can be amount or percent)
  - Total is always computed after category deals are applied to keep UI consistent with backend

## User flow notes
- Adding/removing items immediately updates:
  - Subtotal, product discounts, category extra discount, gifts, tax, and total
- Gifts use price = 0 and are marked `isGift` and `isLocked` to prevent quantity edits
- If cart quantities fall below thresholds, matching gifts are removed
- Gift stock is not blocking: gifts are still added per requirements

## Edge cases handled
- +2 → +5 → +10 transitions: totals and gifts update correctly in real-time
- Multiple thresholds: e.g., 15 items trigger +10 once and +5 once (greedy)
- 20 items trigger +10 twice (two gifts)
- Change Amount stays 0 unless cashier enters a different paid amount

## Developer notes
- If adding new categories of rules in the future (e.g., percent), update:
  - Model and UI validations
  - `computeCategoryDeals` logic and combination rules for Extra Discount
- If you want subcategory inclusion, change the counting logic to consider child category ids
- Keep `CATEGORY_RULES_MAP` payload small by sending only active rules and necessary gift data (id, name, image, unit, stock)

## Files touched
- Models: `app/Models/Category.php`, `app/Models/CategoryDiscountRule.php`
- Migration: `database/migrations/2025_08_24_000001_create_category_discount_rules_table.php`
- Admin UI: `resources/views/admin-views/category/partials/_discount-rules.blade.php`, included in category add/edit views
- Controllers: `app/Http/Controllers/Admin/Product/CategoryController.php`, `app/Http/Controllers/Admin/POS/POSController.php`
- POS view: `resources/views/admin-views/pos/index.blade.php`
- POS JS: `public/assets/back-end/js/admin/pos-script.js`

## QA checklist
- Create category rules (+5 flat 20, +10 gift X) and save
- In POS: add items from that category → expect Extra Discount 20 at 5 qty, gift at 10
- Increase/decrease quantities → gifts added/removed accordingly; totals accurate
- Place order → backend totals match UI; Change Amount 0 unless paid amount differs

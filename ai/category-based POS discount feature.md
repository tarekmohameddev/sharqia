# Category-based POS Discounts & Gifts

This document explains the category-level quantity-based discount rules feature added to the POS local cart system.

## What this adds
- Define discount rules on main categories: threshold quantity → flat amount off; optional gift products (multiple per rule)
- POS applies rules on the client (local cart) while you add/remove items
- Gifts are auto-added/removed based on thresholds; discounts are summed into Extra Discount
- Discounts use greedy highest-threshold-first application; gifts use the single highest applicable rule only

## Rules semantics
- Scope: counts only products whose `category_id` equals the main category id (no subcategories)
- Discount type: flat amount only (no percent)
- Application:
  - **Discounts**: for a given category, locate the highest threshold rule applicable to the current count, apply it as many times as possible (multiples), then consider the remainder against the next lower rule, and so on (greedy)
  - **Gifts**: find the single highest applicable rule (highest threshold ≤ current count); add all gift products attached to that rule exactly once — no summing across lower rules, no multiples
- Stacking with manual Extra Discount: amounts are added together

## Data model
- `App/Models/CategoryDiscountRule`
  - `category_id`: main category id
  - `quantity`: threshold (e.g., 5, 10)
  - `discount_amount`: flat amount off
  - `is_active`: bool
  - `giftProducts()`: BelongsToMany → `products` via `category_discount_rule_gifts` pivot table
- `category_discount_rule_gifts` pivot table:
  - `category_discount_rule_id` (index)
  - `product_id` (index)
  - unique constraint on `(category_discount_rule_id, product_id)`
- Migration: `database/migrations/2025_08_24_000001_create_category_discount_rules_table.php` (original)
- Migration: `database/migrations/2026_05_07_000001_create_category_discount_rule_gifts_table.php` (multi-gift refactor — creates pivot table, migrates old `gift_product_id` data, drops the column)
- Relations on `App/Models/Category`:
  - `discountRules()` and `activeDiscountRules()` (ordered by `quantity` desc)

## Admin UI
- Category add/edit forms include a "Category Discount Rules" section (`resources/views/admin-views/category/partials/_discount-rules.blade.php`).
- Each rule has a **multi-select** gift products field (Select2, `name="...[gift_product_ids][]"`); you can attach zero or more gift products to a single rule.
- Existing rules pre-populate the multi-select with previously saved gift products.
- Rules are saved/updated in `CategoryController@add` and `CategoryController@update` using `giftProducts()->sync()`.
- The edit view eager-loads `discountRules.giftProducts` for efficient pre-population.

## POS integration
- Server: `Admin/POS/POSController@index` loads active category rules with their gift products and exposes a `window.CATEGORY_RULES_MAP` to the POS page.
  - Each rule entry in the map has a `giftProducts` array (each item: `id, name, image, unit, stock`).
- Client: `public/assets/back-end/js/admin/pos-script.js`
  - Items in the local cart track `categoryId` (added to product card data attributes).
  - `computeCategoryDeals(items, CATEGORY_RULES_MAP)` computes:
    - Total category discount across categories (flat, greedy multiples)
    - Gifts: the single highest applicable rule per category, all its gift products pushed once each
  - `ensureCategoryGifts(gifts)` adds missing gifts and removes extras when quantities change
  - Gift keys use the format `cat_${catId}_rule_${ruleId}_gift_${giftId}` — handles multiple gifts per rule and ensures clean add/remove
  - Extra Discount is set to: `categoryDiscount + manualExtraDiscount` (manual can be amount or percent)
  - Total is always computed after category deals are applied to keep UI consistent with backend

## User flow notes
- Adding/removing items immediately updates:
  - Subtotal, product discounts, category extra discount, gifts, tax, and total
- Gifts use price = 0 and are marked `isGift` and `isLocked` to prevent quantity edits
- If cart quantities fall below the threshold of the currently active rule, all gifts for that rule are removed; the next lower applicable rule's gifts are added if one exists
- Gift stock is not blocking: gifts are still added per requirements

## Edge cases handled
- +2 → +5 → +10 transitions: totals and gifts update correctly in real-time
- At 10 items with rules for 5 and 10: only the 10-item rule's gifts are applied (not the 5-item rule as well)
- At 15 items with rules for 5 and 10: the 10-item rule is still the highest applicable → its gifts applied once
- A rule can have multiple gift products; all are added simultaneously when the rule activates
- Change Amount stays 0 unless cashier enters a different paid amount

## Developer notes
- If adding new categories of rules in the future (e.g., percent), update:
  - Model and UI validations
  - `computeCategoryDeals` logic and combination rules for Extra Discount
- If you want subcategory inclusion, change the counting logic to consider child category ids
- Keep `CATEGORY_RULES_MAP` payload small by sending only active rules and necessary gift data (id, name, image, unit, stock)
- The `category_discount_rules` table uses the MyISAM engine (inherited from the project default); no FK enforcement at the DB level — integrity is managed at the application layer

## Files touched
- Models: `app/Models/Category.php`, `app/Models/CategoryDiscountRule.php`
- Migrations:
  - `database/migrations/2025_08_24_000001_create_category_discount_rules_table.php`
  - `database/migrations/2026_05_07_000001_create_category_discount_rule_gifts_table.php` *(new)*
- Admin UI: `resources/views/admin-views/category/partials/_discount-rules.blade.php`, included in category add/edit views
- Controllers: `app/Http/Controllers/Admin/Product/CategoryController.php`, `app/Http/Controllers/Admin/POS/POSController.php`
- POS view: `resources/views/admin-views/pos/index.blade.php`
- POS JS: `public/assets/back-end/js/admin/pos-script.js`

## QA checklist
- Create a category rule with quantity=5, discount=20, and **two** gift products; save
- Create a second rule on the same category with quantity=10, discount=0, and **one different** gift product; save
- In POS: add 5 items from that category → expect Extra Discount 20 and both gifts from the 5-item rule; no gifts from the 10-item rule
- Add 5 more (total 10) → gifts switch to only the 10-item rule's gift product (5-item rule gifts removed)
- Add 5 more (total 15) → still only the 10-item rule's gift (no doubling, no lower-rule gifts)
- Remove items below 10 → 10-item rule gifts removed, 5-item rule gifts re-applied
- Remove items below 5 → all category gifts removed
- Place order → backend totals match UI; Change Amount 0 unless paid amount differs

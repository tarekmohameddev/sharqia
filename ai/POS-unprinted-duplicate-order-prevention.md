### POS: Prevent duplicate order if unprinted order exists (last 24 hours)

#### Overview
When placing a POS order for a customer, if the same customer has any POS order created within the past 24 hours that is still unprinted (`orders.is_printed = 0`), block the new order and show a warning in the POS. In all other cases, proceed as usual (update customer info and create the new order).

#### Why
This avoids creating multiple unprinted orders for the same customer within a short window, reducing duplicate deliveries and confusion in operations.

#### Data model dependency
- `orders.is_printed`: boolean, default false.
  - Set to true when invoice is printed (bulk or single). See existing invoice generation flows in Admin/Vendor Order controllers.

#### Behavior
- On POS order placement:
  - If customer has an unprinted POS order within the last 24 hours:
    - Show a warning toast.
    - Respond with HTTP 409 and JSON: `{ duplicate_unprinted: true, message: '...' }`.
    - Do not update customer info and do not create a new order.
  - Otherwise: proceed normally (update or create customer, compute totals/discounts, create order, return success).

#### Backend changes (summary)
- Added early and definitive duplicate checks in both controllers:
  - `app/Http/Controllers/Admin/POS/POSOrderController.php::placeOrder`
  - `app/Http/Controllers/Vendor/POS/POSOrderController.php::placeOrder`
  - Early check: BEFORE mutating customer data, attempt to resolve `customer_id` by payload/phone and block if unprinted order exists in last 24h.
  - Definitive check: AFTER resolving `userId`, block again if condition matches.
  - On block: `ToastMagic::warning(...)` and `return response()->json([...], 409)`.

- Repository support for filters in counts:
  - `app/Repositories/OrderRepository.php::getCountWhere` now accepts:
    - `customer_id`, `order_type`, `is_printed`, optional window: `created_at_from`, `created_at_to`.

- Order id generation hardening (both Admin/Vendor POS order controllers):
  - Use `Order::max('id') + 1` instead of count-based ID to reduce collisions.

#### Frontend/UX notes
- POS should treat HTTP 409 as a blocked action and display the returned `message`. The backend already triggers `ToastMagic::warning`, but the client should not show a success state when status is 409.

#### Edge cases
- Walk-in customers (`userId = 0`) are not blocked by this rule (no unique identity). If needed, adapt to use phone resolution for walk-ins.
- Printing the pending order (which sets `is_printed = 1`) immediately allows new POS orders.
- The 24h window uses `now()->subDay()` to `now()` on `orders.created_at`.

#### QA checklist
1) Create POS order for a customer; do not print it → attempt a new POS order within 24h → should block with toast and 409; no new order created.
2) Print the first order (or set `is_printed=1`) → attempt new POS order → should succeed and create order.
3) Wait >24 hours (or adjust created_at in DB) → attempt new POS order → should succeed.
4) Wallet/cash/card paths behave identically regarding the block.

#### Files touched (reference)
- `app/Repositories/OrderRepository.php` (extended `getCountWhere` filters)
- `app/Http/Controllers/Admin/POS/POSOrderController.php` (duplicate check + ID generation)
- `app/Http/Controllers/Vendor/POS/POSOrderController.php` (duplicate check + ID generation)

#### Rollback
- Revert the duplicate-check blocks in both POS controllers and remove the added filters from `OrderRepository::getCountWhere` if needed.



## POS: Customer Alternative Phone (Admin POS → Orders)

### Overview
- Adds an optional Alternative phone for customers in the Admin POS (/admin/pos) checkout flow.
- Persists the value to a real column on `users` (`users.alternative_phone`).
- Also embeds the alternative phone into the order’s `shipping_address_data` snapshot for historical accuracy.
- Displays the alternative phone in:
  - Orders list (`/admin/orders/list/all`) under Customer info
  - Order details page (`/admin/orders/details/{id}`) in both Customer Information and Shipping Address sections
  - Admin invoice view
- Makes orders searchable by alternative phone (matches customer column and shipping/billing JSON).

### Deploy/Setup
1) Run database migrations
```bash
php artisan migrate
```

2) No other setup is required. New POS orders will start saving `alternative_phone` to the `users` table and to orders’ `shipping_address_data`.

### Data Model
- New column on `users`:
  - `alternative_phone` (nullable, string)
- Rationale: Mirrors how primary `phone` is handled, enables global search and consistent surfaces across back office.
- Additionally embedded in `orders.shipping_address_data.alternative_phone` to preserve the snapshot at order time.

### User Experience Changes
- Admin POS page (`/admin/pos`): Customer form has an optional Alternative phone field.
  - If provided, it’s sent in the one-shot order payload, and saved on the customer record and on the order’s shipping address.

- Orders list (`/admin/orders/list/all`):
  - Shows “Alt: {phone}” beneath the customer phone if present.

- Order details (`/admin/orders/details/{id}`):
  - Customer Information box: shows alternative phone right under the main phone.
  - Shipping Address card: shows alternative phone if present in the order snapshot; falls back to the customer record if the snapshot is missing it.

- Invoice (admin):
  - Shows a row for alternative phone if present (prefers order snapshot, falls back to customer record).

### Searchability
- Orders search now matches alternative phone via:
  - `shipping_address_data->alternative_phone`
  - `billing_address_data->alternative_phone`
  - `customer.alternative_phone`

### Affected Files
- Database
  - `database/migrations/2025_09_10_000001_add_alternative_phone_to_users_table.php`

- Models
  - `app/Models/User.php` (added `alternative_phone` to `$fillable` and `$casts`)

- POS (UI + JS)
  - `resources/views/admin-views/pos/index.blade.php` (added input `#customer_alt_phone`)
  - `public/assets/back-end/js/admin/pos-script.js` (sends `alternative_phone` in `customer_data`, resets on success)

- POS (Server)
  - `app/Http/Controllers/Admin/POS/POSOrderController.php`
    - When creating/updating customers from POS, saves `alternative_phone` on `users`
    - Embeds `alternative_phone` to `shipping_address_data` for the order

- Orders (Admin views)
  - `resources/views/admin-views/order/list.blade.php` (shows Alt phone under customer info)
  - `resources/views/admin-views/order/order-details.blade.php` (shows Alt phone in Customer Info and Shipping Address)
  - `resources/views/admin-views/order/invoice.blade.php` (shows Alt phone row)

- Orders (Repository, search)
  - `app/Repositories/OrderRepository.php` (matches alternative phone in JSON and `customer.alternative_phone`)

### Data Flow
1) Cashier enters Alternative phone in POS customer form
2) Frontend sends it in `customer_data` with the single order request
3) Server:
   - Updates/creates the `users` record with `alternative_phone`
   - Builds `shipping_address_data` for the order and includes the alternative phone
4) Views render alternative phone from the order snapshot or customer record
5) Order search queries also match alternative phone

### Fallback Behavior
- Rendering order pages uses this precedence:
  - Prefer `order.shipping_address_data.alternative_phone`
  - Else fallback to `order.customer.alternative_phone`
- If neither is set, the UI hides the label.

### Backfilling (Optional)
If you have legacy orders with `shipping_address_data.alternative_phone` and want to backfill the customer column:
```sql
-- Example (adjust to your DB):
-- For each order with a customer and alternative phone in snapshot, update the user's alternative_phone
UPDATE users u
JOIN orders o ON o.customer_id = u.id
SET u.alternative_phone = JSON_UNQUOTE(o.shipping_address_data->'$.alternative_phone')
WHERE u.alternative_phone IS NULL
  AND JSON_UNQUOTE(o.shipping_address_data->'$.alternative_phone') IS NOT NULL
  AND o.customer_id <> 0;
```

### QA Checklist
- Migration runs successfully (`users.alternative_phone` exists)
- POS page: entering alternative phone submits and saves
- Orders list: alternative phone shows under customer info when set
- Order details: alternative phone appears in Customer Information and Shipping Address cards
- Invoice: alternative phone row renders
- Searching orders by alternative phone returns the order(s)

### Rollback
1) Remove the feature display by reverting the view changes if needed.
2) Revert repository search addition if required.
3) Drop the column by rolling back the migration:
```bash
php artisan migrate:rollback --path=database/migrations/2025_09_10_000001_add_alternative_phone_to_users_table.php
```

### Notes
- The order snapshot still stores the alternative phone to maintain historical accuracy of contact details at the time of ordering.
- Using a real column on `users` ensures consistency and discoverability across admin features.



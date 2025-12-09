### Print Unprinted by City Distribution (Admin & Vendor)

Adds a secondary printing flow allowing users to print all unprinted orders scoped to a selected city. Available on both admin and vendor order list pages.

### Scope
- Admin: `resources/views/admin-views/order/list.blade.php`
- Vendor: `resources/views/vendor-views/order/list.blade.php`
- Controllers:
  - Admin: `App\Http\Controllers\Admin\Order\OrderController::bulkInvoices`
  - Vendor: `App\Http\Controllers\Vendor\Order\OrderController::bulkInvoices`, `getListView`

### UI/UX Changes
- New button placed next to the existing “Print Unprinted” button:
  - ID: `print-unprinted-by-city`
  - Label: `print_unprinted_by_city_distribution`
- Clicking the button opens a modal to select a city and confirms printing:
  - Modal ID: `print-by-city-modal`
  - City select ID: `print-city-select`
  - Confirm button ID: `confirm-print-by-city`
- Vendor modal shows only cities covered by the authenticated vendor (from `seller_governorate_coverages`). If no coverage is configured, falls back to the full list.

### Behavior and Data Flow
- Posting target: existing bulk invoice endpoints (no new routes)
  - Admin: `POST admin/orders/bulk-invoices`
  - Vendor: `POST vendor/orders/bulk-invoices`
- The form is submitted programmatically and appends the current page query string (`+ window.location.search`) to preserve other active filters (date, status, etc.).
- Submitted POST payload includes:
  - `_token`: CSRF token
  - `status`: current tab status (from `#current-order-status`)
  - `apply_to = all` (applies to all filtered orders)
  - `is_printed = 0` (unprinted only)
  - `city_id = <selected city id>` (from modal)

### Backend Changes
- Admin `bulkInvoices` now respects `city_id` when `apply_to=all` by including it in the filters sent to `OrderRepository::getListWhere`.
- Vendor `bulkInvoices` now also includes `city_id` when `apply_to=all`.
- Vendor `getListView` passes two lists to the view:
  - `governorates`: full list (for page filters)
  - `coverageGovernorates`: coverage-only list for the modal; view falls back to full list when coverage is empty

### Data Source
- Cities are `governorates` records (Arabic names used): table `governorates (id, name_ar)`.
- Vendor coverage: pivot table `seller_governorate_coverages (seller_id, governorate_id)`.
- Models/Relations:
  - `Governorate::belongsToMany(Seller, 'seller_governorate_coverages', 'governorate_id', 'seller_id')`
  - `Seller::belongsToMany(Governorate, 'seller_governorate_coverages', 'seller_id', 'governorate_id')`

### Affected Files
- Admin
  - `resources/views/admin-views/order/list.blade.php`: button + modal + JS submit
  - `app/Http/Controllers/Admin/Order/OrderController.php`: `bulkInvoices` includes `city_id`
- Vendor
  - `resources/views/vendor-views/order/list.blade.php`: button + modal + JS submit; modal uses `coverageGovernorates` if available
  - `app/Http/Controllers/Vendor/Order/OrderController.php`: `bulkInvoices` includes `city_id`; `getListView` passes `coverageGovernorates`

### How to Use
1. Navigate to Orders list (Admin or Vendor).
2. Click “Print unprinted by city distribution”.
3. Choose a city from the modal.
4. Click “Print unprinted”. A merged PDF will download, and included orders are marked printed and moved to `out_for_delivery` status.

### Notes & Edge Cases
- The current query filters (customer, seller, date range, etc.) are preserved; `city_id` from the modal further constrains the set.
- If no unprinted orders exist for the chosen city and filters, the existing flow returns a “no order found” toast and no PDF is created.
- Vendor modal lists coverage-only cities; if the vendor has no coverage configured, it shows the full list as a fallback.

### Translations
Consider adding these keys to messages if not already present:
- `print_unprinted_by_city_distribution`
- `select_city_to_print_unprinted`
- `only_cities_you_cover_are_listed`

### Testing Checklist
- Admin
  - Modal opens and lists all cities.
  - Printing with a selected city downloads a merged PDF including only unprinted orders for that city.
  - After download, included orders are marked `is_printed=1` and status updated to `out_for_delivery`.
  - Preserves other filters (status/date/customer/seller) when present.
- Vendor
  - Modal opens and lists only coverage cities (or full list if no coverage set).
  - Printing behaves as above, but scoped to the authenticated vendor’s orders.



### Bulk Orders Actions & Printed Status

This feature adds bulk actions and a printed-state tracker for Orders in both Admin and Vendor panels.

### Highlights
- **Bulk status updates**: Change status for multiple selected orders with safe transition rules.
- **Merged invoice download**: Generate a single PDF containing invoices for selected orders, or all orders from the current filtered result set.
- **Printed tracking**: New `is_printed` flag on orders; visible in list and filterable.
- **Print Unprinted button**: One-click download of merged invoices for only unprinted orders in the current filtered result; marks included orders as printed.
- **Top stats summary**: A summary row shows counts for total orders, this month, today, printed, and unprinted.

### Data Model
- **orders.is_printed**: boolean (default false)
  - Migration: `add_is_printed_to_orders_table`
  - Model: `App\Models\Order` has `is_printed` in `fillable` and `casts` (boolean).

### UI Changes
- Admin: `resources/views/admin-views/order/list.blade.php`
  - Top stats summary row (cards): total, this month, today, printed, unprinted
  - Checkbox column with Select All
  - Bulk actions dropdown (change status, print selected, print all)
  - Print Unprinted button beside bulk actions
  - Printed column (Yes/No)
  - Printed status filter (All, Printed only, Unprinted only)
- Vendor: `resources/views/vendor-views/order/list.blade.php`
  - Same additions as Admin
  - Includes the top stats summary row
  - Includes the Print Unprinted button next to bulk actions

### Status Transition Rules
Allowed transitions only:
- pending → confirmed, canceled
- confirmed → processing, canceled
- processing → out_for_delivery, failed, returned, canceled
- out_for_delivery → delivered, failed, returned
- delivered/returned/failed/canceled → no further transitions

Additional vendor constraint:
- Delivering requires payment to be paid unless payment method is COD.

### Endpoints
- Admin (routes/admin/routes.php)
  - POST `admin/orders/bulk-status` → `Admin\Order\OrderController@bulkUpdateStatus`
  - POST `admin/orders/bulk-invoices` → `Admin\Order\OrderController@bulkInvoices`
  - GET  `admin/orders/generate-invoice/{id}` → single invoice (also marks printed)
- Vendor (routes/vendor/routes.php)
  - POST `vendor/orders/bulk-status` → `Vendor\Order\OrderController@bulkUpdateStatus`
  - POST `vendor/orders/bulk-invoices` → `Vendor\Order\OrderController@bulkInvoices`
  - GET  `vendor/orders/generate-invoice/{id}` → single invoice (also marks printed)

Button wiring:
- Print Unprinted posts to the existing bulk-invoices endpoints with `apply_to=all` and `is_printed=0`, preserving current query filters (status, date range, seller, customer, etc.).

### Stats Summary
- Visible at the top of the orders list (Admin and Vendor).
- Shows five numbers:
  - Total orders
  - Total orders this month
  - Total orders today
  - Printed orders
  - Unprinted orders
- Counts respect the viewing context:
  - Admin: respects optional `seller_id`/`seller_is` filter when set.
  - Vendor: automatically scoped to the authenticated seller.
- Implementation:
  - Repository: `OrderRepository::getCountWhere(array $filters)` computes counts efficiently.
  - Controllers compute and pass `stats` to the views:
    - Admin: `App\Http\Controllers\Admin\Order\OrderController@index`
    - Vendor: `App\Http\Controllers\Vendor\Order\OrderController@getListView`

### Printing Behavior
- Single invoice: downloads PDF and marks `is_printed = 1` for that order.
- Merged invoices: compiles all selected (or all filtered) orders into one PDF, downloads it, then marks each included order as printed.
- Print Unprinted: compiles only orders where `is_printed = 0` within the current filtered result, downloads a merged PDF, then marks those orders as printed.

### Filtering
- Orders list accepts `is_printed` filter:
  - `all` (default), `1` (printed only), `0` (unprinted only)
- Wired through controllers to `OrderRepository@getListWhere`.

### How to Use (Admin/Vendor)
1) Navigate to Orders list and set filters as needed (including Printed status).
2) Select rows via checkboxes or use Select All.
3) Choose an action:
   - Change status → confirm → updates allowed orders.
   - Print selected → downloads a merged PDF of selected orders.
   - Print all in filtered results → downloads a merged PDF of all orders matching current filters.
   - Print unprinted → downloads a merged PDF of only the unprinted orders matching current filters.

### Notes
- Printed status is a historical flag and is not reset on subsequent order edits.
- Bulk update API returns counts and skips; UI surfaces generic success/error notifications.



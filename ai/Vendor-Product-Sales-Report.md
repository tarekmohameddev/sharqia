## Vendor Product Sales Report

### Overview
Provides a per-product sales summary for a selected vendor. It aggregates quantities and amounts from order line items and supports date-range filtering, keyword search, and Excel export.

### Navigation
- Admin Panel → Reports & Analysis → Vendor Products Sales
- Direct route: `admin/report/vendor-product-sales`

### Primary Files
- Controller: `app/Http/Controllers/Admin/VendorProductSalesReportController.php`
- View (web): `resources/views/admin-views/report/vendor-product-sales.blade.php`
- Export class: `app/Exports/VendorProductSalesExport.php`
- Export view: `resources/views/file-exports/vendor-product-sales.blade.php`
- Routes: `routes/admin/routes.php`

### How it works
The feature joins `products` with `order_details` and `orders` to aggregate sales for a specific vendor (`orders.seller_id`).

Aggregations per product:
- Total amount sold: `SUM(order_details.qty * order_details.price)`
- Total quantity sold: `SUM(order_details.qty)`
- Total discount given: `SUM(order_details.discount)`
- Average product value (display only): `total_amount / total_quantity`

The vendor used here is the `seller_id` saved on the `orders` record. This ties in with the POS change where selecting a city resolves to the vendor via `seller_governorate_coverages` and sets `orders.seller_id`.

### Filters
Query params accepted by both the page and export:
- `seller_id` (int | 'all') — required for vendor selection (default: 'all')
- `search` (string) — product name contains
- `date_type` (string) — one of: `this_year`, `this_month`, `this_week`, `today`, `custom_date` (default: `this_year`)
- `from` (date) — used when `date_type=custom_date`
- `to` (date) — used when `date_type=custom_date`

Date filter is applied on `orders.updated_at` to reflect activity timeline for orders.

### Routes
- Page: `GET admin/report/vendor-product-sales` → `admin.report.vendor-product-sales`
- Export: `GET admin/report/vendor-product-sales-export` → `admin.report.vendor-product-sales-export`

Example export with filters:
`admin/report/vendor-product-sales-export?seller_id=123&date_type=custom_date&from=2025-09-01&to=2025-09-30&search=milk`

### UI Summary
Page presents:
- Filter form: vendor selector, date type (+ from/to for custom), search
- KPIs: total quantity sold, total amount sold, total discount given
- Table columns: SL, Product Name, Unit Price, Total Amount Sold, Total Quantity Sold, Average Product Value, Current Stock Amount, Average Ratings
- Export button: downloads an Excel file of the current filtered dataset

### Permissions
Under the `report` module group (same permissions scope as other reports). Sidebar entry added under “Reports & Analysis”.

### Data Sources
- `orders` (uses `orders.seller_id` and `orders.updated_at` for filtering)
- `order_details` (qty, price, discount; joined via `order_id`)
- `products` (product name, unit price, stock, rating)
- `sellers` (vendor list for selector)

### Notes & Considerations
- Current page aggregation does not constrain by `delivery_status`; it aggregates all matching order details within the date filter. If you need “delivered-only” stats, add `where('od.delivery_status', 'delivered')` to both the index and export queries.
- Export currently mirrors the page filters and structure; adjust the export columns by editing `resources/views/file-exports/vendor-product-sales.blade.php`.
- Date filtering uses `orders.updated_at`. Change to another timestamp if your reporting needs differ.

### Extending
- Add more columns to the table and export by extending the `select(...)` in the controller and updating both views.
- Add additional filters (e.g., category) by joining `categories` and updating the filter form and query.



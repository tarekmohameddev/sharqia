### Orders List — City Filter (Vendor and Admin)

Adds a city filter to the orders list pages for both vendor and admin, using governorate names (Arabic) from the `governorates` table. This enables filtering orders by `city_id` across list views, exports, and bulk operations.

### Scope
- Vendor orders: `/vendor/orders/list/all`
- Admin orders: `/admin/orders/list/all`
- Export links and bulk invoice actions respect `city_id`

### UI/UX Changes
- A new City dropdown appears in the filters panel of both pages:
  - Source: `governorates.name_ar`
  - Param: `city_id` (value is governorate `id`)
  - Option “All” clears the filter

### Backend Changes
- Repository
  - `App\Repositories\OrderRepository::getListWhere(...)`
    - Added support: when `filters['city_id']` is set and not `all`, query is constrained by `orders.city_id`.

- Vendor
  - `App\Http\Controllers\Vendor\Order\OrderController`
    - Includes `city_id` in filter arrays for list and export.
    - Loads `$governorates = Governorate::orderBy('name_ar')->get(['id','name_ar'])` and passes to view.
  - View: `resources/views/vendor-views/order/list.blade.php`
    - Added City dropdown (populated by `$governorates`).
    - Export link carries `city_id`.

- Admin
  - `App\Http\Controllers\Admin\Order\OrderController`
    - Includes `city_id` in filter arrays for list, export, `listIds`, and `bulkInvoices`.
    - Loads `$governorates` and passes to view.
  - View: `resources/views/admin-views/order/list.blade.php`
    - Added City dropdown (populated by `$governorates`).
    - Export link carries `city_id`.

### Data Source
- Table: `governorates`
- Columns: `id`, `name_ar`
- `orders.city_id` stores the selected governorate `id`.

### How to Use
1. Open vendor or admin orders list.
2. Use the City filter to select a governorate (Arabic names shown).
3. Click “Show data” to apply. Export and bulk actions will reflect this filter.

### Testing Checklist
- Vendor `/vendor/orders/list/all`:
  - Without City filter: results unchanged.
  - With a specific City: results only from that `city_id`.
  - Export includes only filtered orders.
  - “Print all in filtered results” applies only to filtered set.
- Admin `/admin/orders/list/all`:
  - Same validation as vendor, including `listIds` and bulk invoices.

### Edge Cases & Notes
- If `city_id=all` or not provided, no city constraint is applied.
- Ensure `orders.city_id` is populated during order creation to benefit from this filter.
- Invoices display governorate name (resolved from `orders.city_id`).

### Affected Files
- `app/Repositories/OrderRepository.php`
- `app/Http/Controllers/Vendor/Order/OrderController.php`
- `resources/views/vendor-views/order/list.blade.php`
- `app/Http/Controllers/Admin/Order/OrderController.php`
- `resources/views/admin-views/order/list.blade.php`



## Employee Monthly POS Order Report

### Overview

This feature tracks which **admin employee** creates each POS order and exposes a monthly/custom-date **Employee Order Report** in the admin panel.

- Menu path: `Admin → Reports → Employee Order Report`
- URL: `admin/report/employee-order`
- Excel export route: `admin/report/employee-order-export-excel`
- Only **POS** orders (`order_type = 'POS'`) with a non-null `created_by_admin_id` are included.

### Data Model Changes

**Migration**

- File: `database/migrations/2025_12_10_000001_add_created_by_admin_id_to_orders_table.php`
- Adds to `orders` table:
  - `created_by_admin_id` `unsignedBigInteger`, nullable
  - FK → `admins.id`, `nullOnDelete()`

**Model**

- File: `app/Models/Order.php`
  - `$fillable` includes `created_by_admin_id`.
  - `$casts['created_by_admin_id'] = 'integer'`.
  - Relation:
    - `createdByAdmin(): BelongsTo` → `Admin::class`, key `created_by_admin_id`.

### Where `created_by_admin_id` Is Set

**Admin POS order placement**

- File: `app/Http/Controllers/Admin/POS/POSOrderController.php`
- Orders are created through `placeOrder()`, which calls:

```startLine:endLine:app/Services/OrderService.php
public function getPOSOrderData(
    int|string $orderId,
    array $cart,
    float $amount,
    float $paidAmount,
    string $paymentType,
    string $addedBy,
    int $userId,
    ?int $sellerId = null,
    ?int $cityId = null,
    float $shippingCost = 0,
    ?string $orderNote = null,
    ?int $createdByAdminId = null
): array
```

- The admin POS controller passes the logged-in admin ID:

```startLine:endLine:app/Http/Controllers/Admin/POS/POSOrderController.php
$order = $this->orderService->getPOSOrderData(
    orderId: $orderId,
    cart: $cart,
    amount: $amount,
    paidAmount: $paidAmount,
    paymentType: $request['type'],
    addedBy: 'seller',
    userId: $userId,
    sellerId: $sellerId,
    cityId: $cityId,
    shippingCost: $shippingCost,
    orderNote: $orderNote,
    createdByAdminId: auth('admin')->id(),
);
```

**Vendor POS**

- File: `app/Http/Controllers/Vendor/POS/POSOrderController.php`
- Uses the same `getPOSOrderData()` signature **without** setting `created_by_admin_id` (remains `null`).

### Report Controller

- File: `app/Http/Controllers/Admin/EmployeeOrderReportController.php`
- Routes:

```startLine:endLine:routes/admin/routes.php
Route::controller(EmployeeOrderReportController::class)->group(function () {
    Route::get('employee-order', 'index')->name('employee-order');
    Route::get('employee-order-export-excel', 'exportExcel')->name('employee-order-export-excel');
});
```

**Filtering**

- Request params:
  - `date_type` (string): `this_year`, `this_month`, `this_week`, `today`, `custom_date`
  - `from` (Y-m-d, optional for `custom_date`)
  - `to` (Y-m-d, optional for `custom_date`)
- Base query:

```startLine:endLine:app/Http/Controllers/Admin/EmployeeOrderReportController.php
protected function baseQuery()
{
    return Order::whereNotNull('created_by_admin_id')
        ->where('order_type', 'POS');
}
```

- Date filtering:

```startLine:endLine:app/Http/Controllers/Admin/EmployeeOrderReportController.php
protected function dateWiseCommonFilter($query, string $date_type, ?string $from, ?string $to)
{
    return $query->when(($date_type == 'this_year'), function ($query) {
            return $query->whereYear('created_at', date('Y'));
        })
        ->when(($date_type == 'this_month'), function ($query) {
            return $query->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'));
        })
        ->when(($date_type == 'this_week'), function ($query) {
            return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        })
        ->when(($date_type == 'today'), function ($query) {
            return $query->whereBetween('created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
        })
        ->when(($date_type == 'custom_date' && !is_null($from) && !is_null($to)), function ($query) use ($from, $to) {
            return $query->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to);
        });
}
```

**Aggregation**

- Both `index()` and `exportExcel()` aggregate:
  - `COUNT(*) AS total_orders`
  - Grouped by `created_by_admin_id`
  - With `createdByAdmin.role` eager-loaded.

### Blade Views

**Admin report page**

- File: `resources/views/admin-views/report/employee-order-report.blade.php`
- Key elements:
  - Filter section:
    - `date_type` select
    - `from` / `to` date inputs
    - Export button calling `admin.report.employee-order-export-excel` with same filter params.
  - Summary card:
    - Shows `$totalOrders` as **total orders created** in the range.
  - Table:
    - Columns: SL, Employee Name (and email), Role, Total Orders Created.

**Excel export view**

- File: `resources/views/file-exports/employee-order-report-export.blade.php`
- Contains a simple `<table>` with:
  - Employee Name
  - Role
  - Total Orders Created

### Sidebar Navigation

- File: `resources/views/layouts/admin/partials/_side-bar.blade.php`
- Under the Reports section (beside `order_Report`):

```startLine:endLine:resources/views/layouts/admin/partials/_side-bar.blade.php
<li>
    <a class="nav-link {{ Request::is('admin/report/employee-order') ? 'active' : '' }}"
       href="{{ route('admin.report.employee-order') }}" title="{{ translate('employee_order_Report') }}">
        <i class="fi fi-sr-rectangle-list"></i>
        <span class="aside-mini-hidden-element text-truncate">
            {{ translate('employee_order_Report') }}
        </span>
    </a>
</li>
```

### Behavior Notes

- Historical orders (before this feature/migration) will have `created_by_admin_id = null` and will **not** appear in the employee report.
- Only POS orders are counted; other order types are excluded.
- If an employee or role is deleted, rows may show `N/A` for name/role but still count their historical orders.



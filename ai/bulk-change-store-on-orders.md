### Bulk Change Store (seller_id) for Orders

This feature lets Admins change the store (seller) of multiple orders at once, either for selected rows or for all orders in the current filtered result set.

### Highlights
- Change `seller_id` (and `seller_is`) for many orders in bulk
- Works on either:
  - Selected orders via checkboxes
  - All orders in the current filtered results (date range, status, printed, store, customer, etc.)
- Safety rules: delivered/returned/failed/canceled orders are skipped
- Keeps invoices correct by also updating `order_details.seller_id`

### UI Changes (Admin Panel)
- File: `resources/views/admin-views/order/list.blade.php`
- Added near the existing bulk actions:
  - Dropdown: Select Store (Inhouse + all sellers)
  - Buttons:
    - Change store for selected
    - Change store for filtered
- Confirmation popup appears before applying changes

### Backend Endpoint
- Route: `POST admin/orders/bulk-change-seller`
- Controller: `App\Http\Controllers\Admin\Order\OrderController@bulkChangeSeller`

#### Request (Selected Orders)
```
seller_id: number   // 0 = Inhouse, otherwise vendor id
apply_to: "selected"
ids[]: number[]     // order IDs
```

#### Request (Filtered Orders)
```
seller_id: number   // 0 = Inhouse, otherwise vendor id
apply_to: "all"
status: string      // current tab (e.g., all, pending, confirmed, ...)
// Other filters are taken from the current page URL query string
```

#### Response
```
{
  updated: number,
  skipped: Array<{ id: number, reason: string }>
}
```
`reason` can be `not_found`, `no_change`, or `immutable_status`.

### Behavior & Rules
- Updates:
  - `orders.seller_id`
  - `orders.seller_is` (0 → `admin`, else `seller`)
  - `order_details.seller_id` for all details in the order
- Skips orders with statuses: `delivered`, `returned`, `failed`, `canceled`.
- No payment/wallet recalculations are triggered by this action.
- Delivery assignment remains unchanged.

### How to Use
1) Navigate to Admin → Orders list and set filters (date, store, status, printed, customer, etc.) as needed.
2) Choose a target store from the dropdown (Inhouse or vendor).
3) Either:
   - Select specific orders via checkboxes and click “Change store for selected”, or
   - Click “Change store for filtered” to apply to all orders in the current filtered result.
4) Confirm the action in the dialog; on success, the page reloads.

### Notes
- Action is limited to selected orders or the currently filtered result set. It does not apply to all orders globally.
- If an order already belongs to the target store, it is reported as `no_change` and skipped.
- Invoices depend on `order_details.seller_id`; updating details preserves invoice correctness after the store change.



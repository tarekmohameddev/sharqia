## Create Refund Request from Orders List

### Overview
- **Goal**: Allow admins to create a refund request directly from the Orders list view.
- **What changed**: Added a button in the Actions column to trigger a refund request for the selected order. Implemented a backend endpoint to create refund requests for all eligible order items.

### User Flow (Admin)
1. Navigate to Orders list in the admin panel.
2. Click the new red refund icon in the Actions column.
3. Confirm the prompt. A refund request will be created for each order item without an existing refund request.
4. Success or error feedback is shown with a toast.

### Backend
- **Route**
  - Name: `admin.orders.create-refund`
  - Method: `POST`
  - Path: `admin/orders/create-refund/{orderId}`

```http
POST /admin/orders/create-refund/{orderId}
```

- **Controller**: `App\Http\Controllers\Admin\Order\OrderController::createRefundRequest`
  - Loads the order with details.
  - For each `order_details` with `refund_request == 0`:
    - Creates a `RefundRequest` with status `pending`.
    - Calculates `amount` using `OrderManager::getRefundDetailsForSingleOrderDetails`.
    - Sets `order_details_id`, `order_id`, `product_id`, `customer_id`.
    - Updates the corresponding `order_details.refund_request = 1`.
    - Dispatches `RefundEvent` with status `refund_request`.
  - Returns JSON with success message and count created, or an error if all items already have requests.

### Frontend (Blade + JS)
- File: `resources/views/admin-views/order/list.blade.php`
  - Added refund action button in each row:
    - Button class: `js-create-refund`
    - Tooltip: translate(`refund_request`)
  - JS handler:
    - Shows a confirmation dialog (Swal).
    - Sends `POST` to `admin/orders/create-refund/{orderId}`.
    - Shows toast on success or failure.

### Files Changed
- `routes/admin/routes.php`
  - Added route: `Route::post('create-refund/{orderId}', 'createRefundRequest')->name('create-refund');`
- `app/Http/Controllers/Admin/Order/OrderController.php`
  - Added `createRefundRequest` method.
  - Imports `RefundRequest` and `RefundEvent`.
- `resources/views/admin-views/order/list.blade.php`
  - Added refund action button and JS click handler.

### Permissions & Modules
- Route is under the `orders` group with middleware `module:order_management`, aligning with existing order routes.

### Behavior Details
- Creates refund requests for all eligible items in the order (items where `refund_request == 0`).
- Default refund reason: "Created by admin" (can be enhanced with a prompt/modal if needed).
- Appropriate events are dispatched for downstream notifications.

### How to Test
1. Open admin Orders list.
2. Choose any order with at least one item without a refund request.
3. Click the refund icon and confirm.
4. Verify success toast.
5. Validate new entries under Admin → Refund Section → Refund → List.

### Edge Cases
- If every order item already has a refund request, the API responds with an error message indicating it has already been applied.
- Guest orders will set `customer_id` as available on the order (0 if not set).

### Future Enhancements (Optional)
- Ask admin for a custom reason via a prompt/modal before creating the request.
- Support creating refund requests per line item from the list.
- Add role-based permission toggle for this action.



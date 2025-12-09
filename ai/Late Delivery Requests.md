Late Delivery Requests

A backend and admin/vendor UI feature to track orders flagged as late, with statuses and audit history, mirroring the Refund Requests pattern.

Overview
- Allow agents/admins to flag an order as “Late”.
- Manage requests across four statuses: pending, in_progress, resolved, rejected.
- Vendors see late requests for their orders and can update status.
- Admins can list, update status, and access order/customer info quickly.

Data Model
Tables
1) late_delivery_requests
- id (PK)
- order_id (FK → orders.id)
- customer_id (nullable, FK → users.id)
- status: enum-like string: pending | in_progress | resolved | rejected
- resolved_note (nullable)
- rejected_note (nullable)
- change_by (nullable: 'admin' | 'seller')
- timestamps

2) late_delivery_statuses
- id (PK)
- late_delivery_request_id (FK → late_delivery_requests.id)
- change_by ('admin' | 'seller')
- change_by_id (user id of actor)
- status (pending | in_progress | resolved | rejected)
- message (nullable)
- timestamps

Migrations
- database/migrations/2025_08_26_000000_create_late_delivery_requests_table.php
- database/migrations/2025_08_26_000001_create_late_delivery_statuses_table.php

Models
- app/Models/LateDeliveryRequest.php
- app/Models/LateDeliveryStatus.php

Relationships:
- LateDeliveryRequest belongsTo Order, belongsTo User as customer, hasMany LateDeliveryStatus as statusHistory

Repository Layer
Interfaces:
- app/Contracts/Repositories/LateDeliveryRequestRepositoryInterface.php
- app/Contracts/Repositories/LateDeliveryStatusRepositoryInterface.php

Implementations:
- app/Repositories/LateDeliveryRequestRepository.php
- app/Repositories/LateDeliveryStatusRepository.php

Notes:
- LateDeliveryRequestRepository supports filtering by status, search by id/order_id, and whereHas order with seller filters (seller_is, seller_id).
- LateDeliveryStatusRepository implements full RepositoryInterface (add, getFirstWhere, getList, getListWhere, update, delete).

Service
- app/Services/LateDeliveryStatusService.php
  - getStatusData(request, late, changeBy) → maps request to status history row with actor and message (resolved/rejected note).

Requests (Validation)
- app/Http/Requests/Admin/LateDeliveryStatusRequest.php
  - late_status: in pending,in_progress,resolved,rejected
  - rejected_note required if status=rejected
  - resolved_note optional

- app/Http/Requests/Vendor/LateDeliveryStatusRequest.php
  - Same rules for vendor updates (without payment info).

Controllers & Routes
Admin
- Controller: app/Http/Controllers/Admin/Order/LateDeliveryController.php
- Routes (routes/admin/routes.php)
  - GET  admin/late-delivery/list/{status}          → list (status in pending|in_progress|resolved|rejected)
  - POST admin/late-delivery/flag/{orderId}         → create request (Flag as Late)
  - POST admin/late-delivery/status-update          → update status (admin)

Vendor
- Controller: app/Http/Controllers/Vendor/LateDeliveryController.php
- Routes (routes/vendor/routes.php)
  - GET  vendor/late-delivery/index/{status}        → list for vendor
  - POST vendor/late-delivery/update-status         → update status (vendor)

UI/Views
Admin List
- resources/views/admin-views/late-delivery/list.blade.php
  - Header and columns aligned to Orders list: SL, Request ID, Order ID, Order Date, Customer Info, Store, Actions
  - Actions (icons): View Order, Invoice, Set In Progress, Set Resolved, Set Rejected
  - AJAX status updates with optional/required notes

Vendor List
- resources/views/vendor-views/late-delivery/index.blade.php
  - Compact list with status badge; filtered to vendor’s orders

Sidebars
Admin: resources/views/layouts/admin/partials/_side-bar.blade.php
- New “Late Delivery Requests” submenu with links for Pending, In Progress, Resolved, Rejected

Vendor: resources/views/layouts/vendor/partials/_side-bar.blade.php
- New “Late Delivery Requests” submenu with counts per status

Order List Integration
- resources/views/admin-views/order/list.blade.php
  - Added “Flag as Late” icon button per order (AJAX → admin/late-delivery/flag/{orderId})

Status Semantics
- Pending: just flagged
- In Progress: operations/seller working with courier
- Resolved: delivered/closed (resolved_note optional)
- Rejected: mis-flag/customer mistake (rejected_note required)

API Examples
Flag an order (admin):
```bash
curl -X POST \
  -H "X-CSRF-TOKEN: <token>" \
  http://127.0.0.1:8000/admin/late-delivery/flag/12345
```

Update status (admin):
```bash
curl -X POST -F id=1 -F late_status=in_progress \
  -H "X-CSRF-TOKEN: <token>" \
  http://127.0.0.1:8000/admin/late-delivery/status-update
```

Reject with note:
```bash
curl -X POST -F id=1 -F late_status=rejected -F rejected_note="Wrong order referenced" \
  -H "X-CSRF-TOKEN: <token>" \
  http://127.0.0.1:8000/admin/late-delivery/status-update
```

Resolve with optional note:
```bash
curl -X POST -F id=1 -F late_status=resolved -F resolved_note="Delivered yesterday" \
  -H "X-CSRF-TOKEN: <token>" \
  http://127.0.0.1:8000/admin/late-delivery/status-update
```

Permissions/Modules
- Admin and vendor routes are under module:order_management (consistent with refund-section usage).
- If you use a centralized ACL, add menu permissions as needed.

Deployment
1) Run migrations
```bash
php artisan migrate
```
2) Clear caches if needed
```bash
php artisan optimize:clear
```

Rollback
```bash
php artisan migrate:rollback --step=2
```
(rolls back the two late-delivery migrations)

QA Checklist
- Admin
  - List shows correct totals and columns
  - Flagging an order creates a Pending request (idempotent if already exists)
  - Status transitions work; rejected requires note; resolved can save note
  - Invoice and View links open correctly
- Vendor
  - List scoped to vendor’s orders only
  - Status transitions allowed and recorded in history
- DB
  - Requests and status history created and updated as expected

Known/Next
- Details pages (admin/vendor) with timeline of status history can be added similar to refund details.
- Search integration (advanced/global) can be extended to include late delivery requests.



# EasyOrders Integration - Full System Context

This document provides complete context for the EasyOrders integration, covering the current architecture, data model, services, API details, admin UI, and existing features. Use it as a starting point to plan new features.

---

## 1. Architecture Overview

EasyOrders is an external e-commerce builder. Orders flow into our system via two paths:

1. **Webhook** (real-time): EasyOrders sends a POST request when a new order is created.
2. **Excel Import** (manual): Admin uploads an Excel/CSV file with order data.

Both paths stage orders in the `easy_orders` table, then import them into the main `orders` table.

### Flow Diagram

```
EasyOrders Platform
    |
    |--- Webhook POST /api/v1/easyorders/webhook
    |         |
    |         v
    |    EasyOrdersWebhookController::handle()
    |         |
    |         v
    |    easy_orders table (staging)
    |         |
    |         v (auto-import if enabled, or manual from admin)
    |    EasyOrdersService::importOrder()
    |         |
    |         v
    |    orders + order_details tables (main system)
    |
    |--- EasyOrders Product API (outbound, our system calls EasyOrders)
              |
              v
         EasyOrdersApiService::getAllProducts()
              |
              v
         SKU Validation Feature (compare EasyOrders SKUs vs local products)
```

---

## 2. Data Model

### 2.1 `easy_orders` table (staging)

| Column              | Type                                           | Description                                    |
|---------------------|------------------------------------------------|------------------------------------------------|
| id                  | bigint PK                                      | Auto-increment                                 |
| easyorders_id       | string, unique                                 | EasyOrders order UUID                          |
| raw_payload         | json, nullable                                 | Full webhook/import payload                    |
| full_name           | string, nullable                               | Customer name                                  |
| phone               | string, nullable                               | Customer phone                                 |
| government          | string, nullable                               | Governorate name from EasyOrders               |
| address             | text, nullable                                 | Customer address                               |
| sku_string          | string, nullable                               | Combined SKU e.g. "112244(1)+112233(1)"        |
| cost                | decimal(18,2), default 0                       | Product cost                                   |
| shipping_cost       | decimal(18,2), default 0                       | Shipping cost                                  |
| total_cost          | decimal(18,2), default 0                       | Total cost                                     |
| status              | enum: pending/imported/failed/rejected          | Current staging status                         |
| import_error        | text, nullable                                 | Last import error message                      |
| imported_order_id   | bigint, nullable, FK to orders.id              | ID of imported order in main system            |
| imported_at         | timestamp, nullable                            | When the order was imported                    |
| created_at          | timestamp                                      |                                                |
| updated_at          | timestamp                                      |                                                |

### 2.2 `easy_orders_governorate_mappings` table

| Column          | Type                    | Description                                    |
|-----------------|-------------------------|------------------------------------------------|
| id              | bigint PK               |                                                |
| easyorders_name | string, unique          | Governorate name from EasyOrders               |
| governorate_id  | bigint, FK              | FK to governorates.id                          |
| timestamps      |                         |                                                |

### 2.3 `orders` table (relevant fields for EasyOrders imports)

| Column                | Notes                                           |
|-----------------------|-------------------------------------------------|
| id                    | bigint PK (generated as max(id)+1)              |
| customer_id           | FK to users.id                                  |
| order_type            | Set to `'EasyOrders'` for imported orders       |
| payment_method        | Set to `'cod'`                                  |
| order_status          | Set to `'pending'`                              |
| payment_status        | Set to `'unpaid'`                               |
| seller_id             | From governorate->sellers mapping               |
| city_id               | governorate.id                                  |
| order_amount          | Calculated from products + tax - discounts      |
| shipping_cost         | From EasyOrders                                 |
| extra_discount        | Category discount (POS rules)                   |
| shipping_address_data | JSON snapshot of shipping address               |

### 2.4 `products` table (SKU field)

| Column | Type                  | Description                              |
|--------|-----------------------|------------------------------------------|
| code   | string, nullable, 191 | Product SKU code used for matching       |

### 2.5 `business_settings` table (EasyOrders entries)

| type                       | value                 | Description                              |
|----------------------------|-----------------------|------------------------------------------|
| easyorders_auto_import     | 0 or 1                | Auto-import on webhook                   |
| easyorders_webhook_secret  | string (unused)       | Reserved for future signature validation |
| easyorders_api_key         | string                | API key for EasyOrders Product/Orders API|

---

## 3. Models

### `App\Models\EasyOrder`
- File: `app/Models/EasyOrder.php`
- Fillable: easyorders_id, raw_payload, full_name, phone, government, address, sku_string, cost, shipping_cost, total_cost, status, import_error, imported_order_id, imported_at
- Casts: raw_payload (array), cost/shipping_cost/total_cost (decimal:2), imported_at (datetime)
- Relations: `order()` -> `Order` via imported_order_id
- Scopes: `pending()`, `imported()`, `failed()`

### `App\Models\EasyOrdersGovernorateMapping`
- File: `app/Models/EasyOrdersGovernorateMapping.php`
- Fillable: easyorders_name, governorate_id
- Relations: `governorate()` -> `Governorate`

---

## 4. Services

### 4.1 `App\Services\EasyOrdersService`
- File: `app/Services/EasyOrdersService.php`
- Core service for SKU processing and order import.

**Key methods:**

- `buildSkuStringFromPayload(array $payload): string`
  - Iterates all `cart_items` in the webhook payload
  - Extracts `product.sku` from each cart item, parses compound SKUs
  - Multiplies parsed quantities by cart item `quantity`
  - Returns combined SKU string like `"112244(1)+5456567768(1)"`

- `parseSkuString(?string $sku): array`
  - Splits by `+`, matches regex `^([A-Za-z0-9_-]+)\((\d+)\)$`
  - Only `CODE(quantity)` format is accepted; bare codes like "112233" are **rejected**
  - Returns `[['code' => '112244', 'quantity' => 1], ...]`

- `findProductsBySku(array $skuItems): array`
  - Queries `Product::whereIn('code', $codes)` to find matches
  - Returns `[['product' => Product, 'quantity' => int], ...]`
  - Silently skips unmatched codes

- `mapGovernorate(?string $easyOrdersGovName): ?Governorate`
  - Checks `easy_orders_governorate_mappings` first, then fallback to `Governorate::where('name_ar', ...)`

- `findSellerForGovernorate(?Governorate $governorate): ?int`
  - Returns first seller from `$governorate->sellers()`

- `buildCartAndCalculateDiscount(array $products): array`
  - Builds POS-like cart items with prices, discounts, taxes
  - Applies category discount rules (same logic as POS)
  - Returns `['cartItems' => [...], 'extraDiscount' => float, 'totals' => [...]]`

- `importOrder(EasyOrder $easyOrder): Order`
  - Full import pipeline:
    1. Resolve products from payload (fallback to sku_string)
    2. Map governorate + find seller
    3. Resolve/create customer by phone
    4. Create/update shipping address
    5. Build cart + calculate discounts
    6. Create order_details (decrement stock for physical)
    7. Create order (order_type='EasyOrders', payment_method='cod', status='pending')
    8. Update staging row (status='imported', imported_order_id, imported_at)
  - Runs in a DB transaction
  - Throws RuntimeException if no matching products found

### 4.2 `App\Services\EasyOrdersApiService`
- File: `app/Services/EasyOrdersApiService.php`
- HTTP client for the EasyOrders external API.

**Key methods:**

- `getApiKey(): ?string`
  - Reads `easyorders_api_key` from `business_settings`

- `getAllProducts(): array`
  - Endpoint: `GET https://api.easy-orders.net/api/v1/external-apps/products`
  - Auth: `Api-Key` header
  - Uses `fields=id,name,sku` and `limit=200` with pagination
  - Rate limit: 40 requests/minute (built-in sleep logic)
  - Returns `[['id' => string, 'name' => string, 'sku' => string|null], ...]`

---

## 5. EasyOrders External API

### 5.1 Authentication
- Header: `Api-Key: YOUR_API_KEY`
- Permissions are per-key: `products:read`, `orders:read`, etc.

### 5.2 Rate Limit
- **40 requests per minute** across all endpoints

### 5.3 Query Parameters (all endpoints)
- `filter=field||operator||value` (eq, ne, gt, lt, gte, lte, $in, cont, isnull, notnull)
- `sort=field,direction` (ASC/DESC)
- `limit=number` (records per page)
- `page=number`
- `fields=field1,field2` (select specific fields)
- `join=RelatedEntity` (include relations)

### 5.4 Products API
- **List**: `GET /api/v1/external-apps/products`
  - Permission: `products:read`
  - Default response does NOT include `sku` -- must use `fields=id,name,sku`
  - Returns array of product objects (or single object if only one result)
  - Current store has 43 products, all single products (no variants)

- **Single product**: `GET /api/v1/external-apps/products/:product_id`
  - Returns full product details including categories, description, etc.
  - Note: `join=Variations.Props,Variants.VariationProps` returns 400 for this store (no variants)

### 5.5 Orders API
- **Get by ID**: `GET /api/v1/external-apps/orders/:order_id`
  - Permission: `orders:read`
  - Response includes full order data + cart_items with nested product/variant data

- **List all orders**: `GET /api/v1/external-apps/orders` (implied by query parameter support)
  - Same filter/sort/limit/page/fields parameters apply
  - Not explicitly documented but follows same pattern as products endpoint

### 5.6 Order Response Structure (from API docs)
```javascript
{
  id: "2692e31f-27f6-472d-b4cd-c0c1c168511c",  // EasyOrders order UUID
  updated_at: "2024-04-08T03:01:02.474921+02:00",
  created_at: "2024-04-08T03:01:02.474921+02:00",
  store_id: "29bafd4f-5e5a-4faf-8f0f-6c4379eb65ef",
  cost: 730,               // products cost
  shipping_cost: 20,       // shipping cost
  total_cost: 750,         // total cost
  status: "pending",       // order status on EasyOrders side
  full_name: "Violet Henson",
  phone: "01034567890",
  government: "...",
  address: "...",
  payment_method: "cod",
  cart_items: [
    {
      id: "...",
      product_id: "...",
      variant_id: "...",      // optional, only if product has variants
      store_id: "...",
      price: 220,
      quantity: 1,
      product: {
        id: "...",
        name: "...",
        price: 220,
        sku: "EG010102RO5G06",
        taager_code: "020501DR0523",
        drop_shipping_provider: "taager"
      },
      variant: {              // optional
        id: "...",
        product_id: "...",
        price: 220,
        sale_price: 0,
        quantity: 0,
        taager_code: "020501WL0530",
        variation_props: [
          { variation: "color", variation_prop: "#808080" },
          { variation: "size", variation_prop: "L" }
        ]
      }
    }
  ]
}
```

### 5.7 Webhook Payload Structure
Same as the Order Response above. Sent as POST to `/api/v1/easyorders/webhook` when a new order is created on EasyOrders.

### 5.8 Order Status Change Webhook
```javascript
{
  event_type: "order-status-update",
  order_id: "...",         // EasyOrders order UUID
  old_status: "pending",
  new_status: "paid",
  payment_ref_id: "TX1234567890"   // if available
}
```

---

## 6. Controllers

### 6.1 Webhook Controller
- File: `app/Http/Controllers/RestAPI/v1/EasyOrdersWebhookController.php`
- Route: `POST /api/v1/easyorders/webhook`
- No authentication (signature validation was removed)
- Flow:
  1. Validate `id` present in payload
  2. Build SKU string from cart_items via `EasyOrdersService::buildSkuStringFromPayload()`
  3. `EasyOrder::updateOrCreate` by easyorders_id
  4. Check `easyorders_auto_import` setting
  5. If enabled: call `EasyOrdersService::importOrder()` with try/catch
  6. On failure: set status='failed', store import_error
  7. Always returns `{'success': true}`

### 6.2 Admin Staging Orders Controller
- File: `app/Http/Controllers/Admin/EasyOrders/EasyOrderController.php`
- Routes (under `admin/orders`):
  - `GET admin/orders/easy-orders` -> `index` (list with status filter, paginated 20)
  - `GET admin/orders/easy-orders/{id}` -> `show` (details + parsed SKU items)
  - `POST admin/orders/easy-orders/{id}/import` -> `import` (single order import)
  - `POST admin/orders/easy-orders/bulk-import` -> `bulkImport` (bulk import by IDs)
  - `POST admin/orders/easy-orders/{id}/reject` -> `reject` (mark as rejected)

### 6.3 Excel Import Controller
- File: `app/Http/Controllers/Admin/EasyOrders/EasyOrdersExcelImportController.php`
- Routes:
  - `GET admin/orders/easy-orders/excel-import` -> `index`
  - `POST admin/orders/easy-orders/excel-import` -> `import`
- Uses `Maatwebsite\Excel` for import and report generation

### 6.4 Governorate Mapping Controller
- File: `app/Http/Controllers/Admin/EasyOrders/EasyOrdersGovernorateMappingController.php`
- Routes (under `admin/business-settings/easyorders`):
  - `GET governorate-mappings` -> index
  - `POST governorate-mappings` -> store
  - `POST governorate-mappings/{id}` -> update
  - `DELETE governorate-mappings/{id}` -> destroy

### 6.5 SKU Validation Controller
- File: `app/Http/Controllers/Admin/EasyOrders/EasyOrdersSkuValidationController.php`
- Routes (under `admin/business-settings/easyorders`):
  - `GET sku-validation` -> `index`
  - `POST sku-validation/run` -> `runValidation`
  - `GET sku-validation/download-report` -> `downloadReport`
  - `POST sku-validation/save-api-key` -> `saveApiKey`
- Flow:
  1. Fetch all products from EasyOrders API via `EasyOrdersApiService::getAllProducts()`
  2. Parse each product's SKU using `EasyOrdersService::parseSkuString()`
  3. Collect all unique individual SKU codes
  4. Query `Product::whereIn('code', $codes)` to find matches
  5. Build report rows (Matched/Unmatched) with product context
  6. Store results in session for Excel download
  7. Show results in admin view with summary + table

---

## 7. Exports

### 7.1 `App\Exports\EasyOrdersImportReportExport`
- File: `app/Exports/EasyOrdersImportReportExport.php`
- Columns: Order ID, Customer Name, Phone, City, Total Cost, Status, Reason, Imported Order ID
- Color-coded: green=Imported, yellow=Skipped, red=Failed

### 7.2 `App\Exports\EasyOrdersSkuReportExport`
- File: `app/Exports/EasyOrdersSkuReportExport.php`
- Columns: SKU Code, EasyOrders Product Name, EasyOrders Product ID, Compound SKU, Status, Local Product Name
- Color-coded: green=Matched, red=Unmatched

---

## 8. Admin Sidebar

Under **3rd Party Setup** section, there is an **EasyOrders Integration** menu with:
1. **EasyOrders Staging Orders** -> `admin/orders/easy-orders`
2. **EasyOrders Governorate Mappings** -> `admin/business-settings/easyorders/governorate-mappings`
3. **Excel Import** -> `admin/orders/easy-orders/excel-import`
4. **EasyOrders SKU Validation** -> `admin/business-settings/easyorders/sku-validation`

File: `resources/views/layouts/admin/partials/_side-bar.blade.php` (around line 1242)

---

## 9. Views

| View File | Description |
|-----------|-------------|
| `resources/views/admin-views/easy-orders/index.blade.php` | Staging orders list with status filter, bulk import |
| `resources/views/admin-views/easy-orders/show.blade.php` | Single order details + parsed SKU + raw JSON |
| `resources/views/admin-views/easy-orders/governorate-mappings.blade.php` | CRUD for governorate name mappings |
| `resources/views/admin-views/easy-orders/excel-import.blade.php` | Excel/CSV upload form |
| `resources/views/admin-views/easy-orders/sku-validation.blade.php` | API key config + run validation + results table |

---

## 10. Migrations

| Migration File | Description |
|---------------|-------------|
| `2025_12_09_000001_create_easy_orders_table.php` | Creates `easy_orders` staging table |
| `2025_12_09_000002_create_easy_orders_governorate_mappings_table.php` | Creates mapping table |
| `2025_12_09_000003_add_easyorders_settings_to_business_settings.php` | Seeds auto_import + webhook_secret settings |
| `2026_02_06_000001_add_easyorders_api_key_to_business_settings.php` | Seeds easyorders_api_key setting |

---

## 11. Key Implementation Details

### SKU Format Rules
- **Accepted**: `CODE(quantity)` format only, e.g. `112244(1)`, `112233(5)`
- **Compound**: Multiple codes joined by `+`, e.g. `112244(1)+112233(1)`
- **Rejected**: Bare codes like `112233` (no parentheses) are ignored by the parser
- Regex: `^([A-Za-z0-9_-]+)\((\d+)\)$`

### Import Behavior
- Products matched by `products.code` field (not variations)
- Unmatched SKU codes are silently skipped (no error for individual SKU, but if ALL products are unmatched, throws RuntimeException)
- Customer created by phone if not found (default password: 123456)
- Order ID generated as `max(orders.id) + 1`
- Stock decremented for physical products
- Category discount rules applied (same as POS)

### Settings
- `easyorders_auto_import`: 0/1 -- controls auto-import on webhook
- `easyorders_api_key`: string -- API key for EasyOrders Product/Orders API
- Read via `getWebConfig()` helper with direct DB fallback

### Packages Used
- `guzzlehttp/guzzle: ^7.2` (HTTP client)
- `maatwebsite/excel: *` (Excel import/export)
- `devrabiul/toast-magic` (Toast notifications in admin)
- Laravel's `Http` facade for EasyOrders API calls

### UI Pattern
- Controllers extend `BaseController` (avoid naming methods `validate()` -- conflicts with base)
- Views extend `layouts.admin.app`, use `translate()` for labels
- Toast notifications via `ToastMagic::success/error/warning`
- Cards, tables, forms follow the existing admin panel design
- Routes use named route groups: `admin.orders.easy-orders.*` and `admin.business-settings.easyorders.*`

---

## 12. File Index

```
app/
  Models/
    EasyOrder.php
    EasyOrdersGovernorateMapping.php
  Services/
    EasyOrdersService.php          -- SKU parsing, product matching, order import
    EasyOrdersApiService.php       -- HTTP client for EasyOrders external API
  Http/Controllers/
    RestAPI/v1/
      EasyOrdersWebhookController.php
    Admin/EasyOrders/
      EasyOrderController.php                  -- Admin staging CRUD
      EasyOrdersExcelImportController.php      -- Excel import
      EasyOrdersGovernorateMappingController.php  -- Governorate mappings
      EasyOrdersSkuValidationController.php    -- SKU validation feature
  Exports/
    EasyOrdersImportReportExport.php
    EasyOrdersSkuReportExport.php
  Imports/
    EasyOrdersExcelImport.php

resources/views/admin-views/easy-orders/
  index.blade.php
  show.blade.php
  excel-import.blade.php
  governorate-mappings.blade.php
  sku-validation.blade.php

resources/views/layouts/admin/partials/
  _side-bar.blade.php              -- Sidebar with EasyOrders menu

routes/
  rest_api/v1/api.php              -- Webhook route
  admin/routes.php                 -- Admin routes

database/migrations/
  2025_12_09_000001_create_easy_orders_table.php
  2025_12_09_000002_create_easy_orders_governorate_mappings_table.php
  2025_12_09_000003_add_easyorders_settings_to_business_settings.php
  2026_02_06_000001_add_easyorders_api_key_to_business_settings.php

database/seeders/
  EasyOrdersGovernorateMappingsSeeder.php

ai/
  EasyOrders-Webhook-Integration.md   -- Original integration docs
  EasyOrders-Full-Context.md           -- This file
```

---

## 13. EasyOrders API Documentation Reference

Official docs: https://public-api-docs.easy-orders.net/docs/intro

Key pages:
- Authentication: https://public-api-docs.easy-orders.net/docs/authentication
- Query Parameters: https://public-api-docs.easy-orders.net/docs/query-parameters
- Get All Products: https://public-api-docs.easy-orders.net/docs/get-all-products
- Get Order by ID: https://public-api-docs.easy-orders.net/docs/get-order-by-id
- Webhooks: https://public-api-docs.easy-orders.net/docs/webhooks

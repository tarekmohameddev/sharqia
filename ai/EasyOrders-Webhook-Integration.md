# EasyOrders Webhook Integration & Staging Orders

## Summary

This feature integrates EasyOrders (external e‑commerce builder) with our system using a webhook. Incoming orders are first stored in a staging table (`easy_orders`) and can then be imported into the main `orders` table (manually or automatically). POS category discount rules and seller/governorate mapping are reused so EasyOrders orders behave like POS orders.

## Data Model

- `easy_orders` (staging)
  - `easyorders_id` (string, unique) – EasyOrders order UUID
  - `raw_payload` (json) – full webhook payload
  - `full_name`, `phone`, `government`, `address`
  - `sku_string` – SKU like `313DMT(5)+XTJGAI(5)`
  - `cost`, `shipping_cost`, `total_cost`
  - `status` – `pending | imported | failed | rejected`
  - `import_error` – last import error message
  - `imported_order_id` – FK to `orders.id`
  - `imported_at` – timestamp

- `easy_orders_governorate_mappings`
  - `easyorders_name` – governorate name from EasyOrders (e.g. `القاهره و الجيزه`)
  - `governorate_id` – FK to `governorates.id`

## Models

- `App\Models\EasyOrder`
  - Staging record for each incoming EasyOrders order
  - Relation: `order()` → `Order` via `imported_order_id`
  - Scopes: `pending()`, `imported()`, `failed()`

- `App\Models\EasyOrdersGovernorateMapping`
  - Relation: `governorate()` → `Governorate`

## Settings (`business_settings`)

- `easyorders_auto_import` (0/1)
  - If `1`, webhook handler tries to import immediately after staging.
  - If `0`, orders remain `pending` until imported from admin.
  - The webhook handler uses `getWebConfig()` to retrieve this setting, with a database fallback if the cache returns `null`.

- `easyorders_webhook_secret`
  - Currently unused. Webhook signature validation has been removed for easier integration.

## Webhook Endpoint

- Route: `POST /api/v1/easyorders/webhook`
  - Controller: `App\Http\Controllers\RestAPI\v1\EasyOrdersWebhookController@handle`
  - Behavior:
    1. Validate presence of `id`.
    2. Extract:
       - Basic customer fields (`full_name`, `phone`, `government`, `address`)
       - Money fields (`cost`, `shipping_cost`, `total_cost`)
       - `sku_string` from `cart_items[0].product.sku`
    3. `EasyOrder::updateOrCreate` by `easyorders_id`.
    4. Check `easyorders_auto_import` setting:
       - Uses `getWebConfig('easyorders_auto_import')` to retrieve the setting.
       - If `getWebConfig()` returns `null`, falls back to direct database query.
       - Accepts string `"1"`, integer `1`, or boolean `true` as enabled values.
    5. If `easyorders_auto_import` is enabled, call `EasyOrdersService::importOrder($easyOrder)` inside `try/catch`:
       - On success: logs success with `imported_order_id`.
       - On failure: set `status = failed`, store `import_error`, logs error with full trace.
    6. Comprehensive logging:
       - Logs auto-import check with value and type for debugging.
       - Logs when auto-import is skipped (disabled).
       - Logs warnings when database fallback is used or setting is not found.

## Import Logic (EasyOrders → Orders)

Service: `App\Services\EasyOrdersService`

- **SKU parsing**
  - `parseSkuString("313DMT(5)+XTJGAI(5)")` →  
    `[['code' => '313DMT', 'quantity' => 5], ['code' => 'XTJGAI', 'quantity' => 5]]`

- **Product lookup**
  - `findProductsBySku()` loads `Product` records by `code` and pairs them with requested quantities.

- **Governorate & seller mapping**
  - `mapGovernorate($easyOrdersGovName)`
    - First tries `easy_orders_governorate_mappings.easyorders_name`.
    - Fallback: `Governorate::where('name_ar', $easyOrdersGovName)`.
  - `findSellerForGovernorate($governorate)`
    - Returns first seller from `$governorate->sellers()` (matches “first seller” requirement).
  - Result is used as:
    - `orders.city_id = governorate.id` (if found)
    - `orders.seller_id = sellerId`, `orders.seller_is = 'seller'` (or `'admin'` if none).

- **Cart & category discounts**
  - `buildCartAndCalculateDiscount($products)`:
    - Builds a POS‑like `cartItems` array using:
      - `product.unit_price` as `price`
      - Product discount (`discount`, `discount_type`)
      - Tax (`tax`, `tax_type`, `tax_model`)
      - `category_id`
    - Totals:
      - `subtotal`, `productDiscount`, `totalTax`
    - **Category discount logic** (same as POS):
      - Count quantity per `category_id` (excluding gift items).
      - Load `CategoryDiscountRule` for those categories.
      - For each category, apply rules greedily:
        - Highest `quantity` rule first, allow multiples (`intdiv`), subtract remainder.
      - Sum discount into `extraDiscount`.

- **Order creation**
  - Generates a new order id (`max(orders.id) + 1` style).
  - Resolves/creates customer:
    - Looks up user by phone; if missing, creates a new customer with a default password.
  - Creates/updates a `home` shipping address via `ShippingAddressService::getAddAddressData()`.
  - Recomputes final amount:
    - `subtotal - productDiscount + totalTax + shipping_cost - couponDiscount - extraDiscount`
    - Coupon discount is `0` for now (no coupon integration).
  - Creates `order_details` using `OrderDetailsService::getPOSOrderDetailsData()`, reusing POS detail structure and stock decrement for physical products.
  - Inserts an `Order` row with:
    - `order_type = 'EasyOrders'`
    - `payment_method = 'cod'`
    - `order_status = 'pending'`, `payment_status = 'unpaid'`
    - `city_id`, `seller_id`, `extra_discount` (category discount), `shipping_cost`
  - Embeds the shipping address snapshot into `orders.shipping_address_data`.
  - Updates staging row:
    - `status = 'imported'`, `imported_order_id`, `imported_at`, clears `import_error`.

## Admin UI

### Staging Orders List

- Controller: `App\Http\Controllers\Admin\EasyOrders\EasyOrderController`
- Routes (under `admin/orders`, requires `order_management` module):
  - `GET admin/orders/easy-orders` → list staging orders with optional `status` filter.
  - `GET admin/orders/easy-orders/{id}` → details view.
  - `POST admin/orders/easy-orders/{id}/import` → import single order.
  - `POST admin/orders/easy-orders/bulk-import` → bulk import selected `pending` orders.
  - `POST admin/orders/easy-orders/{id}/reject` → mark as `rejected`.
- Views:
  - `resources/views/admin-views/easy-orders/index.blade.php`
    - Table with EasyOrders id, customer info, amounts, status, imported order link, created_at.
    - Per‑row `view`, `import`, `reject` actions; bulk import with checkboxes.
  - `resources/views/admin-views/easy-orders/show.blade.php`
    - Shows basic info + parsed SKU items + raw JSON payload.

### Governorate Mapping Management

- Controller: `App\Http\Controllers\Admin\EasyOrders\EasyOrdersGovernorateMappingController`
  - Implements common `index(?Request, string $type = null)` signature (compatible with `ControllerInterface`).
  - `index()`:
    - Shows add/edit form and paginated list of mappings.
    - Supports `?edit={id}` to load an existing mapping into the form.
  - `store()`:
    - Validates and creates a mapping:
      - `easyorders_name` unique
      - `governorate_id` exists
  - `update()`:
    - Validates and updates mapping with uniqueness rule excluding current id.
  - `destroy()`:
    - Deletes mapping.

- Routes (under business settings):
  - Prefix: `admin/business-settings/easyorders`
  - Names: `admin.business-settings.easyorders.governorate-mappings.*`
    - `GET governorate-mappings` → index
    - `POST governorate-mappings` → store
    - `POST governorate-mappings/{id}` → update
    - `DELETE governorate-mappings/{id}` → destroy

- View:
  - `resources/views/admin-views/easy-orders/governorate-mappings.blade.php`
    - Left: add/edit form (EasyOrders governorate name + system governorate select).
    - Right: list of mappings with edit/delete actions and pagination.

- Sidebar entry:
  - Under **Business Settings → Business Setup**:
    - Added a nav link to the mappings screen:
      - Path: `admin/business-settings/easyorders/governorate-mappings`
      - Label: `EasyOrders_Governorate_Mappings`
      - Included in the Business Setup active route conditions.

## Notes & Future Ideas

- We currently only use `cart_items[0].product.sku` as SKU source; if EasyOrders starts sending multiple products per order, consider concatenating all product SKUs into `sku_string` or parsing from all `cart_items`.
- Category discount logic on EasyOrders orders mirrors POS; if POS rules change, keep this service aligned with POS controllers.
- **Webhook Security**: Signature validation has been removed for easier integration. If security is needed in the future, consider implementing:
  - HMAC-based signature using the request body
  - API key authentication via header or request body
  - IP whitelisting at the web server level
- **Caching**: The auto-import setting uses `getWebConfig()` which may cache values. If the setting is updated but not reflected, clear the cache with `php artisan cache:clear` or clear business settings cache specifically.
- **Logging**: All webhook operations are logged to `storage/logs/laravel.log` for debugging. Check logs if auto-import is not working as expected.  



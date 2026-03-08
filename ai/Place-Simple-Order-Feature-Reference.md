# Place Simple Order — Feature Reference

This document is a single reference for the **Place Simple Order** (POS-style checkout) feature: purpose, API endpoints, data flow, controllers, services, middleware, models, and file index. Use it when extending or debugging this feature.

---

## 1. Overview

**Purpose**: Allow the mobile app to place an order from the customer’s **checked** cart in one API call. No coupon, no multi-step shipping selection — just cart + shipping address + city. The server creates an order (COD, unpaid, `default_type`), assigns seller and shipping cost from the selected governorate, and clears the checked cart items.

**Flows**:
- **Logged-in**: `Authorization: Bearer {token}` → cart resolved by `auth('api')->user()->id` → place order.
- **Guest**: No token; request must include `guest_id` (same as cart). Cart resolved by `guest_id`.

**Authentication**:
- **place-simple**: `apiGuestCheck` middleware — allows either logged-in user (Bearer) or guest (body has `guest_id`). Returns 401 if neither.
- **governorates**: No auth; public list for city dropdown.

**Base paths**: `/api/v1/customer/order` (place-simple), `/api/v1` (governorates).

---

## 2. API Endpoints

### 2.1 List Governorates (Cities)

| Method | Path | Controller | Auth |
|--------|------|------------|------|
| GET | `/api/v1/governorates` | `GovernorateController@index` | None |

**Response**: `{ "governorates": [ { "id", "name", "shipping_cost" }, ... ] }`  
- `id`: use as `city_id` in place-simple.  
- `name`: from `Governorate.name_ar`.  
- `shipping_cost`: from `CityShippingCost.cost` or 0.

**Route**: `routes/rest_api/v1/api.php` — top-level under `v1`, no middleware except `api_lang`.

---

### 2.2 Place Simple Order

| Method | Path | Controller | Auth |
|--------|------|------------|------|
| POST | `/api/v1/customer/order/place-simple` | `SimpleOrderController@placeOrder` | `apiGuestCheck` |

**Request body**: `customer_name`, `phone`, `address`, `city_id` (required); `order_note`, `guest_id` (optional; `guest_id` required for guest).

**Success response**: `{ "message": translate('order_placed_successfully'), "order_id": <int> }`.

**Error responses** (all JSON with `message` key unless validation):
- 401: Unauthorized (no token and no `guest_id`).
- 403: Cart empty or out of stock (`cart_is_empty`, `out_of_stock`).
- 404: Governorate not found or no seller for governorate (`governorate_not_found`, `no_seller_assigned_to_this_governorate`).
- 422: Validation or guest without `guest_id` (`guest_id_required_for_guest_checkout`).
- 500: Exception during transaction (`something_went_wrong`).

**Route**: Inside `Route::group(['middleware' => 'apiGuestCheck'], ...)` → `prefix => 'customer'` → `prefix => 'order'` → `Route::post('place-simple', ...)`.

---

## 3. Data Flow (place-simple)

1. **Validate** request: `customer_name`, `phone`, `address`, `city_id`, optional `order_note`, `guest_id`.
2. **Resolve customer**: `auth('api')->user()` → if null, guest; then `customerId = isGuest ? request('guest_id') : apiUser->id`. If guest and no `guest_id` → 422.
3. **Cart**: `CartManager::get_cart_group_ids(request, type: 'checked')`. Empty → 403.
4. **Load carts**: `Cart::whereHas('product', active())->whereIn('cart_group_id', $cartGroupIds)->where('is_checked', 1)->get()`. Empty → 403.
5. **Stock**: `CartManager::product_stock_check($carts)` fails → 403.
6. **Governorate**: `Governorate::with(['sellers', 'shippingCost'])->find($request->city_id)`. Null → 404. No `sellers->first()` → 404.
7. **Seller & shipping**: `$sellerId = $governorate->sellers->first()->id`, `$shippingCost = $governorate->shippingCost?->cost ?? 0`.
8. **Order ID**: `Order::max('id') + 1` (manual ID to avoid auto-increment mismatch).
9. **Transaction**:
   - For each cart: build order detail via `OrderDetailsService::getPOSOrderDetailsData`, set `payment_status = 'unpaid'`, insert into `order_details`; update product stock if physical; update variation if variant; accumulate subtotal, discount, tax.
   - `order_amount = subtotal - productDiscount + taxTotal + shippingCost`.
   - `shipping_address_data` = JSON of `contact_person_name`, `phone`, `address`, `city_id`.
   - `OrderService::getSimpleOrderData(...)` + `shipping_address_data` → insert into `orders`.
   - `CommonTrait::add_order_status_history($orderId, $customerId, 'pending', 'customer')`.
   - Commit.
10. **Clean cart**: `CartManager::cartCleanByCartGroupIds($cartGroupIds)`.
11. Return 200 with `message` and `order_id`.

---

## 4. Models & Tables

### 4.1 Governorate

- **Table**: `governorates` (id, name_ar, timestamps).
- **Model**: `App\Models\Governorate`.
- **Relations**: `sellers()` BelongsToMany (pivot `seller_governorate_coverages`), `shippingCost()` HasOne `CityShippingCost`.
- **Usage**: Resolve delivery city and seller; `city_id` in place-simple is `governorate.id`.

### 4.2 CityShippingCost

- **Table**: `city_shipping_costs` (governorate_id, cost, etc.).
- **Model**: `App\Models\CityShippingCost`.
- **Usage**: Shipping cost per governorate; optional (default 0).

### 4.3 Order / OrderDetail / OrderStatusHistory

- **place-simple** writes to `orders` and `order_details` (and `order_status_histories`). Uses same schema as rest of app: `order_type = 'default_type'`, `payment_method = 'cash_on_delivery'`, `payment_status = 'unpaid'`, `order_status = 'pending'`, `seller_is = 'seller'`, etc. See `OrderService::getSimpleOrderData()` for full row shape.

---

## 5. Services & Traits

### 5.1 OrderService

- **File**: `app/Services/OrderService.php`.
- **Method**: `getSimpleOrderData(orderId, amount, userId, isGuest, sellerId, cityId, shippingCost, orderNote)`.
- **Returns**: Array for `orders` insert: id, customer_id, is_guest, customer_type, payment_status, order_status, seller_id, seller_is, payment_method, order_type, checked, order_amount, city_id, shipping_cost, order_note, timestamps, etc. Uses `currencyConverter(amount)` for money fields.

### 5.2 OrderDetailsService

- **File**: `app/Services/OrderDetailsService.php`.
- **Method**: `getPOSOrderDetailsData(orderId, item, product, price, tax)` — used to build each `order_details` row from cart item.

### 5.3 POSService

- **File**: `app/Services/POSService.php`.
- **Method**: `getVariantData(...)` — used when cart item has variant; updates product variation after order.

### 5.4 CartManager

- **File**: `app/Utils/CartManager.php` (or `app/Utils/CartManager.php`).
- **Methods**: `get_cart_group_ids(request, type: 'checked')` (uses `Helpers::getCustomerInformation($request)` for customer/guest), `product_stock_check($carts)`, `cartCleanByCartGroupIds(cartGroupIDs)`.

### 5.5 Helpers::getCustomerInformation

- **File**: `app/Utils/helpers.php`.
- **Behavior**: For API, must support `auth('api')->check()` and return `auth('api')->user()` so cart is resolved for logged-in user. Otherwise cart lookup fails (e.g. 403 cart empty in tests).

### 5.6 CommonTrait

- **File**: `app/Traits/CommonTrait.php`.
- **Method**: `add_order_status_history($order_id, $user_id, $status, $user_type, $cause = null)` — inserts into `order_status_histories`.

### 5.7 CalculatorTrait

- **File**: Used by `SimpleOrderController` for `getTaxAmount()`.

---

## 6. Middleware

### 6.1 APIGuestMiddleware (`apiGuestCheck`)

- **File**: `app/Http/Middleware/APIGuestMiddleware.php`.
- **Logic**: If `auth('api')->check()` → pass. Else if `Authorization` header and guard user → pass. Else if `request->guest_id` → pass. Else return 401 `['message' => 'Unauthorized']`.
- **Note**: 401 response must use status code 401 (not 200 with error body).

---

## 7. Key Conventions

- **city_id** in request = `governorate.id`. There is no separate “cities” table in this flow; governorates act as delivery cities.
- **Order ID**: Manually computed as `Order::max('id') + 1` and used in both `order_details` and `orders` so the same id is returned to the client.
- **Cart**: Only **checked** cart items (`is_checked = 1`) are included. Cart is identified by customer (logged-in) or `guest_id` (guest).
- **Translate keys**: All user-facing messages use `translate(...)`: e.g. `order_placed_successfully`, `cart_is_empty`, `out_of_stock`, `governorate_not_found`, `no_seller_assigned_to_this_governorate`, `guest_id_required_for_guest_checkout`, `something_went_wrong`.

---

## 8. File Index

```
app/
  Http/Controllers/RestAPI/v1/
    SimpleOrderController.php   # placeOrder
    GovernorateController.php  # index (list governorates)
  Http/Middleware/
    APIGuestMiddleware.php
  Services/
    OrderService.php           # getSimpleOrderData
    OrderDetailsService.php    # getPOSOrderDetailsData
  Traits/
    CommonTrait.php            # add_order_status_history
    CalculatorTrait.php
  Models/
    Governorate.php
    CityShippingCost.php
    Order.php
    OrderDetail.php
    OrderStatusHistory.php
    Cart.php
  Utils/
    CartManager.php
    helpers.php                # getCustomerInformation

routes/
  rest_api/v1/api.php          # GET governorates; POST customer/order/place-simple (under apiGuestCheck)

tests/
  Feature/SimpleOrderApiTest.php

ai/
  Place-Simple-Order-Mobile-API.md   # Mobile engineer request/response spec
  Place-Simple-Order-Feature-Reference.md  # This file
```

---

## 9. Related Docs

- **ai/Place-Simple-Order-Mobile-API.md** — Request/response spec for mobile: governorates list, place-simple body, all status codes, recommended app flow, and related endpoints.

# Place Simple Order — Mobile API Reference

## Overview

This endpoint lets the mobile app place an order from the customer’s **checked** cart in one call. It is intended for a simple, POS-style flow: cart is already built in the app, user enters shipping details, then the app submits the order. The server creates the order (COD, unpaid, default type), assigns seller and shipping cost from the selected governorate/city, and clears the checked cart items.

**Endpoint**: `POST /api/v1/customer/order/place-simple`

**Authentication**: Either **logged-in customer** (Bearer token) or **guest** (no token but `guest_id` required in body).

---

## Get Cities / Governorates

Use this endpoint to load the list of delivery cities (governorates). The response includes the `id` to send as `city_id` when placing an order.

### Request

```
GET /api/v1/governorates
```

**Authentication**: None. Safe to call without a token (e.g. for guest checkout city dropdown).

### Response — 200 OK

```json
{
  "governorates": [
    {
      "id": 1,
      "name": "الرياض",
      "shipping_cost": 25.00
    },
    {
      "id": 2,
      "name": "جدة",
      "shipping_cost": 30.00
    }
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `governorates` | array | List of governorates (cities) available for delivery. |
| `governorates[].id` | integer | Use this value as `city_id` in the place-simple request. |
| `governorates[].name` | string | Display name (e.g. for dropdown); typically Arabic. |
| `governorates[].shipping_cost` | number | Default shipping cost for this city (for display only; server uses it when placing the order). |

**Note**: If a governorate has no seller assigned, place-simple will return 404 when that `city_id` is used. You can still show all governorates and handle 404 in the UI, or filter client-side if you track which have sellers (e.g. from another API).

---

## 1. Place Order

### Request

```
POST /api/v1/customer/order/place-simple
Content-Type: application/json
```

**Authentication (one of):**

- **Logged-in**: `Authorization: Bearer {access_token}` (same token as other customer API endpoints).
- **Guest**: No `Authorization` header; include `guest_id` in the JSON body (same ID used when adding to cart as guest).

| Field           | Type   | Required | Description |
|----------------|--------|----------|-------------|
| `customer_name`| string | Yes      | Full name; max 255 characters. |
| `phone`        | string | Yes      | Contact phone; max 20 characters. |
| `address`      | string | Yes      | Full shipping address; max 1000 characters. |
| `city_id`      | integer| Yes      | ID of the city/governorate for delivery (must exist and have a seller assigned). |
| `order_note`   | string | No       | Optional note; max 1000 characters. |
| `guest_id`     | string | If guest | Required when not sending Bearer token; same ID used for guest cart. |

#### Example (logged-in)

```json
{
  "customer_name": "Ahmed Ali",
  "phone": "+966501234567",
  "address": "123 Main Street, Riyadh",
  "city_id": 5,
  "order_note": "Please call before delivery"
}
```

#### Example (guest)

```json
{
  "customer_name": "Ahmed Ali",
  "phone": "+966501234567",
  "address": "123 Main Street, Riyadh",
  "city_id": 5,
  "guest_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

---

### Responses

#### 200 — Order placed

Order was created. Cart items that were used for this order are cleared.

```json
{
  "message": "order placed successfully",
  "order_id": 130001
}
```

| Field      | Type   | Description |
|-----------|--------|-------------|
| `message` | string | Success message (may be translated per app language). |
| `order_id`| integer| Created order ID; use for order details, tracking, etc. |

---

#### 401 — Unauthorized

Neither a valid Bearer token nor a `guest_id` was provided.

```json
{
  "message": "Unauthorized"
}
```

**Action**: For guest flow, send `guest_id`. For logged-in flow, send a valid `Authorization: Bearer {token}` or redirect to login.

---

#### 403 — Cart empty

No checked cart items found for this customer/guest.

```json
{
  "message": "cart is empty"
}
```

**Action**: Ensure the user has added items to the cart and that those items are **checked** (selected for checkout). Refresh cart and only enable “Place order” when there is at least one checked item.

---

#### 403 — Out of stock

At least one checked cart item has quantity greater than current stock.

```json
{
  "message": "out of stock"
}
```

**Action**: Refresh cart, show which items are out of stock, and ask the user to reduce quantity or remove them before placing the order.

---

#### 404 — Governorate not found

`city_id` does not exist.

```json
{
  "message": "governorate not found"
}
```

**Action**: Show an error and ask the user to pick a valid city from the list returned by `GET /api/v1/governorates`.

---

#### 404 — No seller for governorate

`city_id` exists but no seller is assigned to deliver there.

```json
{
  "message": "no seller assigned to this governorate"
}
```

**Action**: Show that delivery is not available for this city and ask the user to choose another city or contact support.

---

#### 422 — Validation error

Request body failed validation (missing/invalid fields).

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "customer_name": ["The customer name field is required."],
    "city_id": ["The city id field is required."]
  }
}
```

**Action**: Show validation errors next to the relevant fields and do not place the order until valid.

---

#### 422 — Guest checkout without guest_id

User is not authenticated and did not send `guest_id`.

```json
{
  "message": "guest id required for guest checkout"
}
```

**Action**: For guest checkout, always send the same `guest_id` used when adding to cart (e.g. device UUID). If you don’t have one, add cart items with a new guest ID first, then use that ID here.

---

#### 500 — Server error

An unexpected error occurred; order was not created.

```json
{
  "message": "something went wrong"
}
```

**Action**: Ask the user to try again later; optionally show a support contact.

---

## 2. Response summary

| Scenario                          | HTTP | Body key   | Action |
|----------------------------------|------|------------|--------|
| Success                          | 200  | `message`, `order_id` | Show success, go to order details/tracking. |
| Not authenticated / no guest_id  | 401  | `message`  | Require login or send `guest_id`. |
| No checked cart items            | 403  | `message`  | Refresh cart, ensure items are checked. |
| Cart item out of stock           | 403  | `message`  | Refresh cart, fix quantities. |
| Invalid city_id                  | 404  | `message`  | Choose valid city from list. |
| City has no seller               | 404  | `message`  | Choose another city or show “no delivery”. |
| Validation / missing fields      | 422  | `errors`   | Show field errors. |
| Guest without guest_id          | 422  | `message`  | Send `guest_id` for guest checkout. |
| Server error                     | 500  | `message`  | Retry later / contact support. |

---

## 3. Recommended app flow

1. **Cart screen**
   - Load cart (e.g. `GET /api/v1/cart` or equivalent).
   - User selects items to buy (checked).
   - Only enable “Proceed to checkout” when at least one item is checked.

2. **Checkout / Place order screen**
   - Collect: customer name, phone, address, **city** (from `GET /api/v1/governorates` — use each item’s `id` as `city_id`).
   - Optional: order note.
   - If **guest**: ensure you have a stable `guest_id` (e.g. from when items were added to cart) and send it in the request body. Do **not** send `Authorization` for guest.
   - If **logged-in**: send `Authorization: Bearer {token}`; do not send `guest_id` unless your backend expects it.

3. **Call API**
   - `POST /api/v1/customer/order/place-simple`
   - Body: `customer_name`, `phone`, `address`, `city_id`, optional `order_note`, and `guest_id` only for guest.

4. **Handle response**
   - **200**: Show success, show or navigate to order using `order_id` (e.g. order details or tracking).
   - **401**: Redirect to login or ensure guest_id is sent for guest flow.
   - **403**: Show “Cart is empty” or “Out of stock” and refresh cart.
   - **404**: Show “Invalid city” or “No delivery to this city” and let user change city.
   - **422**: Show validation errors or “Guest ID required” as needed.
   - **500**: Show “Something went wrong, try again later”.

5. **After 200**
   - Cart is updated on the server (checked items used for this order are removed). Refresh cart in the app if the user can place another order.

---

## 4. Notes for mobile engineer

- **city_id**: Must be an `id` from `GET /api/v1/governorates`. Do not use arbitrary numbers.
- **Cart**: Only **checked** (selected) cart items are included. The server uses the same cart as your existing cart API (same customer or same `guest_id`).
- **Order type**: Orders created by this endpoint are simple orders: default type, cash on delivery (COD), unpaid. No coupon or complex shipping options in this call.
- **Seller & shipping**: Seller and shipping cost are determined by the backend from `city_id`; you do not send them.
- **Messages**: The `message` field may be translated according to app language; prefer showing it to the user and use HTTP status + `errors` for validation.

---

## 5. Related endpoints (for context)

- **Governorates (cities)**: `GET /api/v1/governorates` — list of delivery cities; use each `id` as `city_id` in place-simple.
- **Cart**: e.g. `GET /api/v1/cart`, `POST /api/v1/cart/add`, update/remove and **select cart items for checkout** (so they become “checked”).
- **Order details**: use `order_id` with your order-details or tracking API (e.g. `GET /api/v1/customer/order/get-order-by-id` or similar) to show the placed order.

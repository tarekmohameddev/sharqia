# POS Customer Account Claiming and Optional Email — Feature Reference

This document is the single reference for the account-claiming and optional-email feature. Use it when extending, debugging, or onboarding to this feature.

---

## 1. Overview

### Problem Solved

POS, EasyOrders, and order-update flows create real `users` rows with `email = null` and a random password. When the same customer later tried to register via mobile or web, all registration endpoints rejected them with "phone already taken". There was no way to claim the account.

Additionally, email was required in registration endpoints even though the database column was already nullable and phone is the primary identity in this project.

### Solution

1. **`registration_source`** column tracks how each account was created (`pos`, `import`, `web`, `mobile`, `social`, `otp`).
2. **`claimed_at`** timestamp marks when a system-created account was formally "claimed" by the customer. `NULL` = unclaimed / claimable.
3. **Claim flow**: when a mobile/web registration hits a phone that already exists and is unclaimed, instead of rejecting with "already taken", the system sends an OTP to verify phone ownership, then atomically updates the existing row.
4. **Email optional**: email is no longer required in any registration endpoint.

---

## 2. Database Changes

### 2.1 Migrations

| File | Description |
|------|-------------|
| `database/migrations/2026_03_08_132402_add_registration_source_and_claimed_at_to_users_table.php` | Adds `registration_source`, `claimed_at`, and UNIQUE INDEX on `phone` to `users`. Backfills existing users. |
| `database/migrations/2026_03_08_133052_add_claim_data_to_phone_or_email_verifications_table.php` | Adds `claim_data TEXT NULL` to `phone_or_email_verifications` for multi-server-safe claim payloads. |

### 2.2 New Columns on `users`

| Column | Type | Default | Values |
|--------|------|---------|--------|
| `registration_source` | `varchar(20)` nullable | `null` | `pos`, `import`, `web`, `mobile`, `social`, `otp` |
| `claimed_at` | `timestamp` nullable | `null` | `null` = unclaimed; non-null = claimed |

**Critical: UNIQUE INDEX on `phone`** — the migration also adds `$table->unique('phone')`. This prevents duplicate phone entries under concurrency and turns every phone lookup from a full table scan into an index seek.

### 2.3 Backfill Logic (runs in the migration)

```php
// All users who have an email are marked as already claimed (they registered themselves)
DB::table('users')
    ->whereNotNull('email')
    ->whereNull('claimed_at')
    ->update([
        'registration_source' => 'web',
        'claimed_at' => DB::raw('created_at'),
    ]);
// Users with email = null remain unclaimed (POS-created) -- correct behavior
```

### 2.4 New Column on `phone_or_email_verifications`

| Column | Type | Purpose |
|--------|------|---------|
| `claim_data` | `TEXT` nullable | JSON payload `{f_name, l_name, email, password, temporary_token}` stored during claim-OTP flow; DB-backed so it works across multiple servers |

### 2.5 Model Changes

- **`app/Models/User.php`** — added `registration_source` and `claimed_at` to `$fillable` and `$casts`; added `HasFactory` trait.
- **`app/Models/PhoneOrEmailVerification.php`** — added `claim_data` to `$fillable` and `$casts`.

---

## 3. Account States

| State | `email` | `claimed_at` | `registration_source` | Can log in? |
|-------|---------|--------------|----------------------|-------------|
| POS-created (unclaimed) | `null` | `null` | `pos` | No (random password) |
| EasyOrders-imported (unclaimed) | `null` | `null` | `import` | No (random password) |
| Self-registered (web) | non-null | non-null | `web` | Yes |
| Self-registered (mobile API) | optional | non-null | `mobile` | Yes |
| OTP-registered | optional | non-null | `otp` | Yes |
| Social login | optional | non-null | `social` | Yes |
| POS-claimed | optional | non-null | `mobile` / `web` | Yes |

---

## 4. Claim Flow (How It Works)

```
Mobile app: POST /api/v1/auth/register  {phone: "+966...", f_name: "Ahmed", password: "..."}
                    │
                    ▼
    Phone exists in users table?
                    │
         ┌──────────┴──────────┐
        YES                    NO
         │                     │
    claimed_at IS NULL?    Create new user normally
         │                 (registration_source='mobile', claimed_at=now())
    ┌────┴────┐
   YES        NO
    │          │
Send OTP    Return 403: "already registered, please login"
    │
Store {f_name, l_name, email, password} in
phone_or_email_verifications.claim_data (JSON)
    │
Return: {claim_account: true, temporary_token: "...", status: false}
    │
Mobile: POST /api/v1/auth/verify-phone  {phone, token (OTP)}
    │
OTP correct AND claim_data present?
    │
Atomic UPDATE:
  WHERE phone = ? AND claimed_at IS NULL
  SET f_name, l_name, email, password, claimed_at=now(),
      registration_source='mobile', is_phone_verified=1
    │
affected rows = 0?  → another process claimed first → 403
affected rows = 1?  → success → return Passport token
```

### Race Condition Safety

The atomic `WHERE claimed_at IS NULL` update means two simultaneous claim attempts for the same phone can never both succeed — only the first `UPDATE` wins (returns 1 affected row); the second gets 0 and returns 403.

---

## 5. Registration Source Tagging

Every user creation point now sets `registration_source` and `claimed_at`:

| Location | File | Source | Claimed? |
|----------|------|--------|----------|
| Admin POS (add) | `app/Http/Controllers/Admin/POS/POSOrderController.php` | `pos` | `null` |
| Admin POS (updateOrCreate) | Same file | `pos` | `null` (via `wasRecentlyCreated`) |
| Vendor POS | `app/Http/Controllers/Vendor/POS/POSOrderController.php` | `pos` | `null` |
| EasyOrders | `app/Services/EasyOrdersService.php` | `import` | `null` |
| Order Update | `app/Http/Controllers/Admin/Order/OrderUpdateAction.php` | `pos` | `null` |
| RestAPI v3 Seller POS | `app/Http/Controllers/RestAPI/v3/seller/POSController.php` | `pos` | `null` |
| Admin Customer Add | `app/Http/Controllers/Admin/Customer/CustomerController.php` | `pos` | `null` |
| Vendor Customer Add | `app/Http/Controllers/Vendor/CustomerController.php` | `pos` | `null` |
| Web Registration | `app/Http/Controllers/Customer/Auth/RegisterController.php` | `web` | `now()` |
| API Registration (register) | `app/Http/Controllers/RestAPI/v1/auth/CustomerAPIAuthController.php` | `mobile` | `now()` |
| API Registration (registration) | Same file | `mobile` | `now()` |
| API OTP Registration | Same file | `otp` | `now()` |
| API Social Registration | Same file | `social` | `now()` |
| Passport Registration | `app/Http/Controllers/RestAPI/v1/auth/PassportAuthController.php` | `mobile` | `now()` |
| Social Auth (v1 API) | `app/Http/Controllers/RestAPI/v1/auth/SocialAuthController.php` | `social` | `now()` |
| Social Auth (web) | `app/Http/Controllers/Customer/Auth/SocialAuthController.php` | `social` | `now()` |
| Firebase OTP | `app/Http/Controllers/Customer/Auth/CustomerAuthController.php` | `otp` | `now()` |
| Web Checkout (COD) | `app/Http/Controllers/Web/WebController.php` | `web` | `now()` |
| Web Checkout (offline) | Same file | `web` | `now()` |
| Digital Payment | `app/Utils/module-helper.php` | `web` | `now()` |
| API Checkout | `app/Http/Controllers/RestAPI/v1/OrderController.php` | `mobile` | `now()` |
| Auth RegisterController | `app/Http/Controllers/Auth/RegisterController.php` | `web` | `now()` |

### POS `updateOrCreate` Pattern (Race-Condition Safe)

```php
// Keep updateOrCreate (atomic in MySQL 8) to prevent TOCTOU race conditions
$customer = $this->customerRepo->updateOrCreate(
    ['phone' => $phone],
    ['f_name' => $customerInfo['f_name'] ?? '', 'l_name' => $customerInfo['l_name'] ?? '']
);

// Only set source/password on brand-new records -- never overwrite a claimed account
if ($customer->wasRecentlyCreated) {
    $customer->update([
        'email' => null,
        'password' => bcrypt(Str::random(32)),
        'registration_source' => 'pos',
        'claimed_at' => null,
        'is_active' => 1,
    ]);
}
```

---

## 6. Login Fix (Hash::check instead of auth()->attempt)

Three login paths used `auth()->attempt(['email' => $user['email'], ...])` which fails when `email = null` (multiple users match `email = null`). All three were fixed to use `Hash::check() + auth()->login($user)`:

| File | Method |
|------|--------|
| `app/Http/Controllers/Customer/Auth/LoginController.php` L156 | `submit()` |
| `app/Http/Controllers/RestAPI/v1/auth/CustomerAPIAuthController.php` L136 | `login()` |
| `app/Http/Controllers/Customer/Auth/RegisterController.php` L468 | `login_process()` (static) |

**Also fixed:** `CustomerAuthController.php` Firebase bug — `base64_encode($user['email'])` → `base64_encode($user['phone'])` when email is null.

---

## 7. Email Optional — Validation Changes

Email changed from `required` to `nullable|email|unique:users` in:

| File | Line(s) |
|------|---------|
| `app/Http/Controllers/RestAPI/v1/auth/CustomerAPIAuthController.php` | L54, L499 |
| `app/Http/Controllers/RestAPI/v1/auth/PassportAuthController.php` | L35 |
| `app/Http/Requests/Web/CustomerRegistrationRequest.php` | L31 |
| `app/Http/Requests/Vendor/CustomerRequest.php` | L28 |
| `app/Http/Controllers/RestAPI/v3/seller/POSController.php` | L165 |
| `app/Http/Controllers/RestAPI/v1/auth/CustomerAPIAuthController.php` (registrationWithOTP) | L731 |

Note: `app/Http/Requests/Admin/CustomerRequest.php` already did NOT require email — no change needed.

### Email Verification Gating

All email verification checks are now guarded with `&& $user->email` so phone-only users skip the email verification step entirely:

```php
// Before (broken for null-email users):
if ($emailVerification && !$user->is_email_verified) { ... }

// After (safe):
if ($emailVerification && $user->email && !$user->is_email_verified) { ... }
```

Files updated: `CustomerAPIAuthController.php`, `PassportAuthController.php`, `RegisterController.php`, `LoginController.php`, `CustomerAuthController.php`.

---

## 8. Security — No More Default Password

All POS/import user creation previously used `bcrypt('123456')`. Changed to `bcrypt(Str::random(32))` so unclaimed accounts cannot be logged into:

| File |
|------|
| `app/Http/Controllers/Admin/POS/POSOrderController.php` |
| `app/Http/Controllers/Vendor/POS/POSOrderController.php` |
| `app/Services/EasyOrdersService.php` |
| `app/Http/Controllers/Admin/Order/OrderUpdateAction.php` |
| `app/Http/Controllers/RestAPI/v3/seller/POSController.php` |

---

## 9. Null-Safe Email in Views and Events

### Blade Views (added `@if($email)` guards)

| File | Lines |
|------|-------|
| `resources/views/admin-views/pos/order/order-details.blade.php` | ~L427 |
| `resources/views/vendor-views/pos/order/order-details.blade.php` | ~L423 |
| `resources/views/admin-views/vendor/order-details.blade.php` | ~L538 |
| `resources/views/vendor-views/order/order-details.blade.php` | ~L562 |
| `resources/views/admin-views/order/list.blade.php` | ~L479 |
| `resources/views/vendor-views/order/list.blade.php` | ~L312 |
| `resources/views/admin-views/customer/list.blade.php` | ~L136 |
| `resources/views/admin-views/refund/list.blade.php` | ~L99 |
| `resources/views/vendor-views/refund/index.blade.php` | ~L90 |
| `resources/views/admin-views/delivery-man/view.blade.php` | ~L154 |
| `resources/views/email-templates/digital-product-download.blade.php` | ~L218, L263 |
| `resources/views/file-exports/customer-list.blade.php` | ~L52 |

### Events/Notifications (guarded with `if ($email)`)

| File | Event |
|------|-------|
| `app/Utils/OrderManager.php` | `OrderPlacedEvent` — skip if email is null |
| `app/Http/Controllers/Admin/Customer/CustomerWalletController.php` | `AddFundToWalletEvent` |
| `app/Http/Controllers/Admin/Customer/CustomerController.php` | `CustomerStatusUpdateEvent` |
| `app/Utils/module-helper.php` | `AddFundToWalletEvent` |

---

## 10. API Endpoints Affected

### Registration Endpoints — New Behavior

| Endpoint | Phone already exists AND unclaimed | Phone already exists AND claimed | Phone not exists |
|----------|-------------------------------------|----------------------------------|------------------|
| `POST /api/v1/auth/register` | Returns `{claim_account: true, temporary_token, phone, status: false}` | Returns 403 "already registered, please login" | Creates new user normally |
| `POST /api/v1/auth/registration` | Same as above | Same as above | Creates new user normally |
| `POST /api/v1/auth/registration-with-otp` | Atomically claims account (direct, no OTP step) | Returns 403 | Creates new user normally |
| Passport `POST /api/v1/auth/register` | OTP claim flow | Returns 403 | Creates new user normally |
| Web registration | OTP claim flow (redirects to phone verification) | Returns error | Creates new user normally |

### Phone Verification — Extended

`POST /api/v1/auth/verify-phone` now checks `claim_data` in the verification record. If present, it applies the atomic claim update instead of just marking `is_phone_verified = 1`.

---

## 11. Factory and Tests

### `database/factories/UserFactory.php`

Upgraded from legacy `$factory->define()` to class-based factory. Added states:

| State | Description |
|-------|-------------|
| `posUnclaimed()` | `email=null`, random password, `registration_source='pos'`, `claimed_at=null` |
| `noEmail()` | `email=null`, but claimed |
| `claimed()` | `registration_source='mobile'`, `claimed_at=now()` |

### Test Files

| File | What it tests |
|------|---------------|
| `tests/Feature/CustomerLoginNullEmailTest.php` | Login works for `email=null` users via phone + password |
| `tests/Feature/CustomerAccountClaimTest.php` | Full claim flow: unclaimed → OTP → claim; concurrent claim race condition; POS non-overwrite |
| `tests/Feature/CustomerRegistrationOptionalEmailTest.php` | Email is truly optional in all registration endpoints |
| `tests/Unit/CustomerPosCreationTest.php` | POS user has correct source/claimed_at/password; no duplicate for existing phone; backfill logic |

---

## 12. Key Design Decisions

### Why `claimed_at` instead of `is_from_pos`?

`is_from_pos` (boolean) can only answer "was this from POS?" but `claimed_at` (timestamp) answers:
- Is this account claimable? (`claimed_at IS NULL`)
- When was it claimed?
- Was it ever a system-created account?

A timestamp is strictly more useful than a boolean for this use case.

### Why `registration_source` instead of just checking `email`?

Email presence works as a heuristic for the backfill but is fragile going forward — users might provide email during claiming, and admins might add emails manually. `registration_source` records the actual origin of the account regardless of later modifications.

### Why keep `updateOrCreate` in POS instead of read-then-write?

`getFirstWhere()` + conditional `add()` introduces a TOCTOU (time-of-check-to-time-of-use) race condition. Two concurrent POS operators serving the same customer can both see "not found" and both insert, causing a duplicate key error. `updateOrCreate` is atomic at the MySQL level and the new `UNIQUE` index on `phone` provides an additional hard guarantee.

### Why use `phone_or_email_verifications.claim_data` and not session/cache?

- Session lives on a single server — fails with multiple PHP servers (load balancer).
- Redis/file cache could be purged or unavailable.
- The `phone_or_email_verifications` table is already database-backed, transactional, and replicated. It's the correct place for temporary verification state.

### Why `WHERE claimed_at IS NULL` atomic update for the claim?

If two devices try to claim the same account simultaneously, both pass the "unclaimed" check. With a regular update, both would succeed and the second would overwrite the first. The `WHERE claimed_at IS NULL` constraint means MySQL only updates the row if `claimed_at` is still null — the first `UPDATE` sets it to `now()`, so the second `UPDATE` matches 0 rows and fails gracefully.

---

## 13. File Index

```
database/
  migrations/
    2026_03_08_132402_add_registration_source_and_claimed_at_to_users_table.php
    2026_03_08_133052_add_claim_data_to_phone_or_email_verifications_table.php
  factories/
    UserFactory.php  (upgraded to class-based; posUnclaimed, noEmail, claimed states)

app/
  Models/
    User.php  (added registration_source, claimed_at to fillable/casts; HasFactory)
    PhoneOrEmailVerification.php  (added claim_data to fillable/casts)

  Http/Controllers/
    Customer/Auth/
      LoginController.php           (Hash::check login fix; email verification guard)
      RegisterController.php        (Hash::check fix; claim flow; email optional)
      CustomerAuthController.php    (Firebase bug fix; web claim apply; OTP source tag)
      SocialAuthController.php      (registration_source=social)
    RestAPI/v1/auth/
      CustomerAPIAuthController.php (claim flow in register/registration/registrationWithOTP;
                                     Hash::check login; email optional; verifyPhone claim)
      PassportAuthController.php    (claim flow; email optional)
      SocialAuthController.php      (registration_source=social)
    Admin/
      POS/POSOrderController.php    (wasRecentlyCreated pattern; random password; source=pos)
      Order/OrderUpdateAction.php   (random password; source=pos)
      Customer/
        CustomerController.php      (source from CustomerService; event guard)
        CustomerWalletController.php (wallet event guard)
    Vendor/
      POS/POSOrderController.php    (wasRecentlyCreated pattern; random password; source=pos)
      CustomerController.php        (source from CustomerService)
    Auth/
      RegisterController.php        (source=web via CustomerAuthService)
    Web/
      WebController.php             (source=web for checkout user creation)
    RestAPI/v1/
      OrderController.php           (source=mobile for checkout user creation)
    RestAPI/v3/seller/
      POSController.php             (source=pos; email optional)

  Services/
    Web/CustomerAuthService.php     (registration_source=web, claimed_at=now())
    CustomerService.php             (registration_source=pos, claimed_at=null)
    EasyOrdersService.php           (registration_source=import; random password)

  Utils/
    OrderManager.php                (email null guard for OrderPlacedEvent)
    module-helper.php               (source=web; email null guard for wallet event)

resources/views/
  admin-views/pos/order/order-details.blade.php    (email null guard)
  vendor-views/pos/order/order-details.blade.php   (email null guard)
  admin-views/vendor/order-details.blade.php       (email null guard)
  vendor-views/order/order-details.blade.php       (email null guard)
  admin-views/order/list.blade.php                 (email null guard)
  vendor-views/order/list.blade.php                (email null guard)
  admin-views/customer/list.blade.php              (email null guard)
  admin-views/refund/list.blade.php                (email null guard)
  vendor-views/refund/index.blade.php              (email null guard)
  admin-views/delivery-man/view.blade.php          (email null guard)
  email-templates/digital-product-download.blade.php (fallback to phone)
  file-exports/customer-list.blade.php             (email ?? '')

tests/
  Feature/
    CustomerLoginNullEmailTest.php
    CustomerAccountClaimTest.php
    CustomerRegistrationOptionalEmailTest.php
  Unit/
    CustomerPosCreationTest.php

app/Http/Requests/
  Web/CustomerRegistrationRequest.php   (email nullable)
  Vendor/CustomerRequest.php            (email nullable)
```

---

## 14. Related Docs

- The original plan: `c:\Users\icecr\.cursor\plans\pos_customer_claiming_and_optional_email_9df65ef3.plan.md`
- Previous conversation: [POS customer claiming plan & implementation](7c6c1871-efaf-4133-8aa7-8f3239890b6c)

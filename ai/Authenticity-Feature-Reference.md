# Authenticity Scratch Cards — Feature Reference

This document is a single reference for the Authenticity (scratch-card verification) feature: data model, admin panel, mobile API, services, and file index. Use it when extending or debugging this feature.

---

## 1. Overview

**Purpose**: Allow customers to verify product authenticity via scratch-card codes printed on packaging. First scan marks the code as used; re-scans show when it was first activated. Customers can report suspected counterfeits for already-scanned codes.

**Flows**:
- **Admin**: Generate batches of codes → export PDF/CSV for printing → view dashboard, audit logs, counterfeit reports → mark reports as reviewed.
- **Mobile**: Customer scans/enters code → `POST /api/v1/authenticity/verify` → first scan marks code used; re-scan shows first scan date and option to report counterfeit → `POST /api/v1/authenticity/report-counterfeit` for already-scanned codes.

**Authentication**:
- Admin: standard admin middleware (`admin`, `actch:admin_panel`).
- API: `auth:api` (Laravel Passport, customer token). Base path: `/api/v1/authenticity`.

---

## 2. Data Model

### 2.1 Tables (Migration: `2026_03_08_000001_create_authenticity_tables.php`)

| Table | Description |
|-------|--------------|
| `authenticity_code_batches` | Batch metadata (batch_number, total_count, generated_at). |
| `authenticity_codes` | Individual codes; status `unused`/`used`; first scan user/IP/time. |
| `authenticity_scan_logs` | Every verify attempt (valid_first, valid_rescan, invalid, rate_limited, blocked). |
| `authenticity_counterfeit_reports` | User reports on used codes; admin review fields. |

### 2.2 `authenticity_code_batches`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| batch_number | string, unique | e.g. `BATCH-2026-0001` |
| total_count | integer | Number of codes in batch |
| generated_at | timestamp nullable | |
| timestamps | | |

### 2.3 `authenticity_codes`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| batch_id | FK → authenticity_code_batches | onDelete cascade |
| code | string(20), unique | Canonical format `XXXX-XXXX-XXXX` |
| status | enum: unused, used | default `unused` |
| first_scanned_at | timestamp nullable | Set on first successful verify |
| first_scanned_by_user_id | FK → users nullable | onDelete set null |
| first_scanned_ip | string(45) nullable | |
| timestamps | | |

Indexes: `batch_id`, `status`.

### 2.4 `authenticity_scan_logs`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| code_id | FK → authenticity_codes nullable | onDelete set null |
| code_entered | string(50) | As entered/normalized |
| user_id | FK → users nullable | onDelete set null |
| result | enum: valid_first, valid_rescan, invalid, rate_limited, blocked | |
| ip_address | string(45) | |
| user_agent | string(500) nullable | |
| device_id | string(255) nullable | From request (abuse detection) |
| created_at | timestamp | No updated_at |

Indexes: `code_id`, `user_id`, `ip_address`, `result`, `created_at`.

### 2.5 `authenticity_counterfeit_reports`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| code_id | FK → authenticity_codes | onDelete cascade |
| user_id | FK → users | onDelete cascade |
| notes | text nullable | Customer notes |
| reported_at | timestamp | |
| admin_reviewed_at | timestamp nullable | Set when admin marks reviewed |
| admin_notes | text nullable | Admin investigation notes |
| timestamps | | |

Indexes: `code_id`, `user_id`.

---

## 3. Models

| Model | File | Key relations / scopes |
|-------|------|-------------------------|
| `AuthenticityCodeBatch` | `app/Models/AuthenticityCodeBatch.php` | `codes()` HasMany; `usedCount()`, `unusedCount()` helpers |
| `AuthenticityCode` | `app/Models/AuthenticityCode.php` | `batch()` BelongsTo; `firstScannedBy()` User; `scanLogs()`, `counterfeitReports()` HasMany; scopes: `unused()`, `used()` |
| `AuthenticityScanLog` | `app/Models/AuthenticityScanLog.php` | `code()` BelongsTo; `user()` BelongsTo; no timestamps (created_at only) |
| `AuthenticityCounterfeitReport` | `app/Models/AuthenticityCounterfeitReport.php` | `code()` BelongsTo; `user()` BelongsTo; scopes: `unreviewed()`, `reviewed()` |

---

## 4. Services

### 4.1 `AuthenticityCodeService`  
**File**: `app/Services/AuthenticityCodeService.php`

- **CHARSET**: `23456789ABCDEFGHJKLMNPQRSTUVWXYZ` (excludes 0/O, 1/I/L).
- **Code format**: `XXXX-XXXX-XXXX` (3 segments of 4 chars).
- **Methods**:
  - `generateCode(): string` — single code with dashes.
  - `normalizeCode(string $input): string` — accepts with/without dashes or spaces; returns canonical `XXXX-XXXX-XXXX`.
  - `generateUniqueCode(): string` — generates until unique in DB (max 100 attempts).
  - `generateBatch(int $count): AuthenticityCodeBatch` — count 1–100,000; creates batch with `BATCH-{year}-{seq}`; inserts codes in chunks of 500 with `insertOrIgnore` to handle collisions.

### 4.2 `AuthenticityAbuseDetectionService`  
**File**: `app/Services/AuthenticityAbuseDetectionService.php`

- **Storage**: Laravel Cache (keys prefixed `authenticity_`).
- **Thresholds (rolling 60 min)**: User 50 invalid → block 24h; IP 100 invalid → block 24h; Device 30 invalid → block 24h.
- **Methods**:
  - `isBlocked(?int $userId, string $ip, ?string $deviceId): bool`
  - `recordInvalidAttempt(?int $userId, string $ip, ?string $deviceId): void`
  - `unblockUser(int $userId): void`, `unblockIp(string $ip): void` (no device unblock in current code).

---

## 5. Admin Panel

### 5.1 Routes (`routes/admin/routes.php`)

All under prefix `admin`, name prefix `admin.`, then `authenticity.`:

| Method | Path | Name | Controller method |
|--------|------|------|-------------------|
| GET | `/admin/authenticity` | admin.authenticity.index | AuthenticityDashboardController@index |
| GET | `/admin/authenticity/batches` | admin.authenticity.batches.index | AuthenticityBatchController@index |
| GET | `/admin/authenticity/batches/create` | admin.authenticity.batches.create | AuthenticityBatchController@create |
| POST | `/admin/authenticity/batches` | admin.authenticity.batches.store | AuthenticityBatchController@store |
| GET | `/admin/authenticity/batches/{id}` | admin.authenticity.batches.show | AuthenticityBatchController@show |
| GET | `/admin/authenticity/batches/{id}/export-pdf` | admin.authenticity.batches.export-pdf | AuthenticityBatchExportController@exportPdf |
| GET | `/admin/authenticity/batches/{id}/export-csv` | admin.authenticity.batches.export-csv | AuthenticityBatchExportController@exportCsv |
| GET | `/admin/authenticity/audit-logs` | admin.authenticity.audit-logs | AuthenticityAuditLogController@index |
| GET | `/admin/authenticity/counterfeit-reports` | admin.authenticity.counterfeit-reports.index | AuthenticityCounterfeitReportController@index |
| POST | `/admin/authenticity/counterfeit-reports/{id}/review` | admin.authenticity.counterfeit-reports.review | AuthenticityCounterfeitReportController@review |

**ControllerInterface**: All admin controllers extending `BaseController` must implement `index(?Request $request, ?string $type = null)` with the interface return type (e.g. `View`, `RedirectResponse`). AuthenticityBatchExportController has a stub `index()` that redirects to `admin.authenticity.batches.index`.

### 5.2 Controllers

| Controller | Responsibility |
|------------|----------------|
| `AuthenticityDashboardController` | Dashboard: stats (batches, codes, scans, reports), recent scans, recent batches. |
| `AuthenticityBatchController` | List/create batches, store new batch (count 1–10000), show batch with codes paginated. |
| `AuthenticityBatchExportController` | exportPdf (print-batch view, dompdf), exportCsv (code, status, first_scanned_at). |
| `AuthenticityAuditLogController` | List scan logs with filters: result, user_id, search (code_entered, ip_address). |
| `AuthenticityCounterfeitReportController` | List reports (filter: all|pending|reviewed), review (set admin_reviewed_at, admin_notes). |

### 5.3 Views

| View | Path | Purpose |
|------|------|---------|
| Dashboard | `admin-views/authenticity/index.blade.php` | Stats cards, recent scans, recent batches. |
| Batches list | `admin-views/authenticity/batches/index.blade.php` | Paginated batches, create link, export links. |
| Create batch | `admin-views/authenticity/batches/create.blade.php` | Form: count (validate 1–10000). |
| Batch show | `admin-views/authenticity/batches/show.blade.php` | Batch info, used/unused counts, paginated codes, export PDF/CSV. |
| Print batch | `admin-views/authenticity/print-batch.blade.php` | Layout for PDF: cards in grid (28 per page). |
| Audit logs | `admin-views/authenticity/audit-logs.blade.php` | Table of scan logs, filters. |
| Counterfeit reports | `admin-views/authenticity/counterfeit-reports.blade.php` | Tabs All/Pending/Reviewed, table, modal to mark reviewed with admin_notes. |

### 5.4 Sidebar (`resources/views/layouts/admin/partials/_side-bar.blade.php`)

Section **Authenticity Scratch Cards** (around line 1306):

- Dashboard → `route('admin.authenticity.index')`
- Code Batches → `route('admin.authenticity.batches.index')`
- Audit Logs → `route('admin.authenticity.audit-logs')`
- Counterfeit Reports → `route('admin.authenticity.counterfeit-reports.index')`

Uses `translate()` keys: `Authenticity_Scratch_Cards`, `Dashboard`, `Code_Batches`, `Audit_Logs`, `Counterfeit_Reports`.

---

## 6. Mobile / REST API

**Base**: `/api/v1/authenticity`  
**Auth**: `auth:api` (Passport).  
**Controller**: `App\Http\Controllers\RestAPI\v1\AuthenticityVerifyController`.

### 6.1 Rate limiting (`RouteServiceProvider`)

- **Name**: `throttle:authenticity-verify` (on verify route only).
- **Limits**: 10/min per user, 30/min per IP (both applied).

### 6.2 Endpoints

| Method | Path | Action | Throttle |
|--------|------|--------|----------|
| POST | `/api/v1/authenticity/verify` | verify | authenticity-verify |
| POST | `/api/v1/authenticity/report-counterfeit` | reportCounterfeit | default |

### 6.3 Verify (`verify`)

- **Input**: `code` (required, string, max 50), `device_id` (optional, for abuse detection).
- **Flow**: Normalize code → if blocked (user/IP/device) → log `blocked`, 403. Else find code; if not found → record invalid attempt, log `invalid`, 404. If code status `used` → log `valid_rescan`, 200 with first_scanned_at and can_report_counterfeit. If `unused` → transaction lock, set status=used, first_scanned_at, first_scanned_by_user_id, first_scanned_ip, log `valid_first`, 200.
- **Responses**: 200 (first_scan true/false), 404 invalid, 403 blocked, 429 rate limit, 422 validation, 401 unauthenticated.

### 6.4 Report counterfeit (`reportCounterfeit`)

- **Input**: `code` (required), `notes` (optional, max 1000).
- **Rules**: Code must exist and status `used`. One report per (code_id, user_id); duplicate returns 200 with message equivalent to “already reported this code”.
- **Responses**: 200 created or duplicate, 404 code not found, 422 “can only report scanned codes” or validation.

Full request/response examples and recommended app flow: see **ai/Authenticity-Mobile-API.md**.

---

## 7. Key Conventions

- **Code format**: `XXXX-XXXX-XXXX`; charset excludes 0/O, 1/I/L. Accept input with/without dashes/spaces; normalize before lookup.
- **First scan**: Only the first successful verify marks the code used (DB transaction + lockForUpdate to avoid races).
- **Scan log**: Every verify attempt is logged (valid_first, valid_rescan, invalid, rate_limited, blocked).
- **Counterfeit reports**: Allowed only for codes with status `used`; one report per user per code; admin reviews via admin panel.
- **ControllerInterface**: Admin controllers use `index(?Request $request, ?string $type = null)` and return type compatible with `ControllerInterface` (View|RedirectResponse|…).
- **Route names**: Counterfeit reports list is `admin.authenticity.counterfeit-reports.index` (not `admin.authenticity.counterfeit-reports`). Audit logs list is `admin.authenticity.audit-logs` (no `.index`).
- **Translate keys**: API and views use `translate(...)` for messages (e.g. `invalid_code`, `product_is_authentic`, `counterfeit_report_submitted`, `account_temporarily_blocked`).

---

## 8. File Index

```
app/
  Models/
    AuthenticityCode.php
    AuthenticityCodeBatch.php
    AuthenticityCounterfeitReport.php
    AuthenticityScanLog.php
  Services/
    AuthenticityCodeService.php
    AuthenticityAbuseDetectionService.php
  Http/Controllers/
    Admin/Authenticity/
      AuthenticityDashboardController.php
      AuthenticityBatchController.php
      AuthenticityBatchExportController.php
      AuthenticityAuditLogController.php
      AuthenticityCounterfeitReportController.php
    RestAPI/v1/
      AuthenticityVerifyController.php

resources/views/admin-views/authenticity/
  index.blade.php
  print-batch.blade.php
  audit-logs.blade.php
  counterfeit-reports.blade.php
  batches/
    index.blade.php
    create.blade.php
    show.blade.php

resources/views/layouts/admin/partials/
  _side-bar.blade.php   # Authenticity menu block

routes/
  admin/routes.php              # Admin authenticity routes
  rest_api/v1/api.php           # API verify + report-counterfeit

app/Providers/
  RouteServiceProvider.php      # RateLimiter authenticity-verify

database/migrations/
  2026_03_08_000001_create_authenticity_tables.php

tests/
  Feature/AuthenticityVerifyApiTest.php
  Unit/AuthenticityCodeServiceTest.php
  Unit/AuthenticityAbuseDetectionServiceTest.php

ai/
  Authenticity-Mobile-API.md    # Mobile API request/response reference
  Authenticity-Feature-Reference.md  # This file
```

---

## 9. Related Docs

- **ai/Authenticity-Mobile-API.md** — Full mobile API spec: verify and report-counterfeit request/response examples, code format, error matrix, recommended app flow.

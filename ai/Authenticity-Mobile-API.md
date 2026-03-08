# Authenticity Scratch Cards — Mobile API Reference

## Overview

Two endpoints allow the customer mobile app to verify product authenticity and report suspected counterfeits. Both require a logged-in customer (`auth:api`).

**Base URL**: `/api/v1/authenticity`

**Authentication**: Bearer token (Laravel Passport), same as all other customer API endpoints.

---

## 1. Verify Code

Checks whether a scratch-card code is authentic. Marks it as "used" on the very first scan. Subsequent scans return details of the original scan (date/time) so the customer knows someone else already activated it.

### Request

```
POST /api/v1/authenticity/verify
Authorization: Bearer {token}
Content-Type: application/json
```

| Field       | Type   | Required | Description                                                                 |
|-------------|--------|----------|-----------------------------------------------------------------------------|
| `code`      | string | Yes      | The scratch-card code. Accepted formats:<br>• `FSY9-6HKI-7TOP` (with dashes)<br>• `FSY96HKI7TOP` (no dashes)<br>• `fsy9 6hki 7top` (spaces, lowercase)<br>All are normalized to `XXXX-XXXX-XXXX` before lookup. |
| `device_id` | string | No       | Unique device identifier from the app. Used for abuse detection. |

#### Example Request Body

```json
{
  "code": "FSY9-6HKI-7TOP",
  "device_id": "a1b2c3d4-device-uuid"
}
```

---

### Responses

#### ✅ 200 — Valid Code, First Scan

The code exists and has never been scanned before. It is now marked as used.

```json
{
  "authentic": true,
  "first_scan": true,
  "message": "Product is authentic"
}
```

---

#### ✅ 200 — Valid Code, Already Scanned (Re-scan)

The code exists but was already scanned previously. Shows when it was first activated. The customer can optionally report it as counterfeit.

```json
{
  "authentic": true,
  "first_scan": false,
  "first_scanned_at": "2026-03-01 14:30:00",
  "message": "Code was previously verified",
  "can_report_counterfeit": true
}
```

| Field                | Type    | Description                                          |
|----------------------|---------|------------------------------------------------------|
| `authentic`          | boolean | Always `true` — the code itself is genuine           |
| `first_scan`         | boolean | `false` — this code was already activated before     |
| `first_scanned_at`   | string  | UTC datetime of the original scan (`Y-m-d H:i:s`)   |
| `can_report_counterfeit` | boolean | `true` — customer may submit a counterfeit report |

> **UX guidance**: If `first_scan` is `false`, the UI should warn the customer that this card was already activated, and offer a "Report Counterfeit" button.

---

#### ❌ 404 — Invalid Code

The code does not exist in the system. Either it was never generated, or it was tampered with.

```json
{
  "authentic": false,
  "message": "Invalid code"
}
```

> **Note**: The response is intentionally generic to prevent attackers from distinguishing between "code never existed" and other states.

---

#### ❌ 422 — Validation Error

Request body is missing required fields or fields are in the wrong format.

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": ["The code field is required."]
  }
}
```

---

#### ❌ 429 — Rate Limited

Too many requests in a short time window.

- **10 requests/minute** per user account
- **30 requests/minute** per IP address

```json
{
  "message": "Too Many Attempts."
}
```

---

#### ❌ 403 — Account Blocked

The account, IP, or device has been temporarily blocked due to suspicious activity (many invalid code attempts).

```json
{
  "message": "Account temporarily blocked"
}
```

Block thresholds (rolling 1-hour window):

| Trigger          | Threshold | Block Duration |
|------------------|-----------|----------------|
| User account     | 50 invalid attempts | 24 hours |
| IP address       | 100 invalid attempts | 24 hours |
| Device ID        | 30 invalid attempts | 24 hours |

---

#### ❌ 401 — Unauthenticated

No valid Bearer token was provided.

```json
{
  "message": "Unauthenticated."
}
```

---

### Full Response Matrix

| Scenario              | HTTP Status | `authentic` | `first_scan` |
|-----------------------|-------------|-------------|--------------|
| First scan (valid)    | 200         | `true`      | `true`       |
| Re-scan (valid)       | 200         | `true`      | `false`      |
| Code not found        | 404         | `false`     | —            |
| Validation error      | 422         | —           | —            |
| Rate limited          | 429         | —           | —            |
| Account blocked       | 403         | —           | —            |
| Not authenticated     | 401         | —           | —            |

---

---

## 2. Report Counterfeit

Allows a customer to flag a code as potentially counterfeit. Only works for codes that have already been scanned (status `used`). Each user can submit at most one report per code.

### Request

```
POST /api/v1/authenticity/report-counterfeit
Authorization: Bearer {token}
Content-Type: application/json
```

| Field    | Type   | Required | Description                                      |
|----------|--------|----------|--------------------------------------------------|
| `code`   | string | Yes      | The scratch-card code (same formats accepted as verify). |
| `notes`  | string | No       | Optional customer notes (max 1000 characters). E.g. "Packaging looked different". |

#### Example Request Body

```json
{
  "code": "FSY9-6HKI-7TOP",
  "notes": "The hologram sticker on the box looks fake and the packaging font is different."
}
```

---

### Responses

#### ✅ 200 — Report Submitted

```json
{
  "message": "Counterfeit report submitted"
}
```

Also returned (with same 200) if the user already submitted a report for this code — duplicate reports are silently deduplicated.

---

#### ❌ 404 — Code Not Found

```json
{
  "message": "Invalid code"
}
```

---

#### ❌ 422 — Code Not Yet Scanned

Counterfeit reports can only be filed for codes that have been verified at least once. Unused codes cannot be reported.

```json
{
  "message": "Can only report scanned codes"
}
```

---

#### ❌ 422 — Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": ["The code field is required."]
  }
}
```

---

#### ❌ 401 — Unauthenticated

```json
{
  "message": "Unauthenticated."
}
```

---

---

## Code Format

Codes are printed on scratch cards in the format:

```
XXXX-XXXX-XXXX
```

- 12 meaningful characters split into 3 groups of 4 by dashes
- Characters only from the set `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`
- Characters `0`, `O`, `1`, `I`, `L` are excluded to avoid visual confusion
- Example: `FSY9-6HKI-7TOP`, `A3K9-B7MX-2PQW`

The verify endpoint accepts all of these equivalent inputs for the same code:

| Input                | Accepted? |
|----------------------|-----------|
| `FSY9-6HKI-7TOP`     | ✅ Yes (canonical) |
| `FSY96HKI7TOP`       | ✅ Yes (no dashes) |
| `fsy9-6hki-7top`     | ✅ Yes (lowercase) |
| `FSY9 6HKI 7TOP`     | ✅ Yes (spaces)    |
| `fsy96hki7top`       | ✅ Yes (lowercase, no separator) |

---

## Recommended Mobile App Flow

```
1. Customer installs app and logs in (phone number verification)

2. Tap "Verify Product" → open camera to scan barcode on card
   └─ Barcode contains the raw code (e.g. FSY96HKI7TOP)
   └─ Alternatively, customer can type the code manually from under the scratch layer

3. App calls POST /api/v1/authenticity/verify

4. Handle response:

   ├─ 200 + first_scan: true
   │   └─ Show "✅ Authentic Product" screen
   │       └─ Green checkmark, "This product is genuine"

   ├─ 200 + first_scan: false
   │   └─ Show "⚠️ Already Verified" screen
   │       └─ "This code was already scanned on {first_scanned_at}"
   │       └─ "If you did not scan it, this may be a counterfeit."
   │       └─ [Report Counterfeit] button → calls POST /report-counterfeit

   ├─ 404
   │   └─ Show "❌ Invalid Code" screen
   │       └─ "This code was not recognized. Check the code and try again."

   ├─ 429
   │   └─ Show "⏳ Slow down" screen
   │       └─ "Too many attempts. Please wait a minute and try again."

   ├─ 403
   │   └─ Show "🚫 Blocked" screen
   │       └─ "Your account has been temporarily blocked due to suspicious activity."

5. If customer taps [Report Counterfeit]:
   └─ Optional: show text field for notes
   └─ Call POST /api/v1/authenticity/report-counterfeit
   └─ Show confirmation: "Your report has been submitted. Thank you."
```

---

## Error Handling Summary

| HTTP Status | Meaning                        | Suggested UI Message                              |
|-------------|--------------------------------|---------------------------------------------------|
| 200         | Success (check `first_scan`)   | See flow above                                    |
| 401         | Not logged in                  | Redirect to login screen                          |
| 403         | Account/IP/device blocked      | "Temporarily blocked due to suspicious activity"  |
| 404         | Code not found                 | "Invalid code — please check and try again"       |
| 422         | Validation / business rule     | Show `message` field from response                |
| 429         | Rate limited                   | "Too many attempts — wait 1 minute"               |
| 5xx         | Server error                   | "Something went wrong — please try again later"   |

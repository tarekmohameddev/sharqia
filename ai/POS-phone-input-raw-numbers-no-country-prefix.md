## POS: Phone Inputs — Raw Numbers, Preserve Leading Zeros, No Country Prefix

### Overview
- POS customer phone fields now accept raw digits, preserve leading zeros on type/paste, and do not prepend any country code.
- This change isolates the POS phone inputs from the global intl-tel-input initializer while keeping intl-tel behavior intact elsewhere in the admin.

### User Impact
- Cashier can paste numbers like `011555252984` and it remains exactly as entered.
- No automatic country/dial code is added.
- Only digits are accepted on keypress; paste strips non-digits but preserves leading zeros.

### Implementation Details
- Opted-out the two POS phone inputs from intl-tel-input by adding a marker attribute.
  - `data-no-intl="true"` set on `#customer_phone` and `#customer_alt_phone`.
- Updated the global intl-tel initializer to skip any `input[type="tel"]` with `data-no-intl`.
- Added lightweight client-side handlers on the POS page to:
  - Allow only digits on keypress
  - Allow paste while preserving leading zeros (non-digits removed)

### Changed Files
- `resources/views/admin-views/pos/index.blade.php`
  - Added `data-no-intl="true"` to:
    - `#customer_phone`
    - `#customer_alt_phone`
- `public/assets/backend/libs/intl-tel-input/js/intlTelInout-validation.js`
  - Skip initializing intl-tel-input on `input[type="tel"][data-no-intl]`.
- `public/assets/back-end/js/admin/pos-script.js`
  - Added POS-specific keypress/paste handlers for `#customer_phone` and `#customer_alt_phone`.

### How It Works
1. Admin layout still loads intl-tel-input globally for tel inputs.
2. On pages/components that need raw numbers, add `data-no-intl="true"` to the tel input to bypass intl-tel.
3. POS page adds small handlers that:
   - On keypress: block non-digit chars
   - On paste: insert only digits of the pasted text, preserving leading zeros

### Extending This Pattern
If another admin form needs raw phone input without country formatting:
1. Add `data-no-intl="true"` to the `<input type="tel">`.
2. Optionally replicate the POS keypress/paste snippet if you want identical behavior.

### QA Checklist
- Pasting `011555252984` into both `Phone` and `Alternative phone` fields keeps the leading `0`.
- No country code appears before/after paste or typing.
- Non-digit characters are blocked on keypress.
- Pasting alpha/symbol characters results in only digits being kept.
- Other admin pages (not marked with `data-no-intl`) still use intl-tel-input as before.

### Rollback
- Remove `data-no-intl` attributes from POS inputs in `index.blade.php`.
- Remove the POS-specific handlers from `pos-script.js`.
- The intl-tel initializer will again apply to those fields.

### Release Notes Snippet
Admin POS: Phone inputs now accept raw numbers and preserve leading zeros; country prefix auto-insertion is disabled for POS. Other admin forms remain unchanged.



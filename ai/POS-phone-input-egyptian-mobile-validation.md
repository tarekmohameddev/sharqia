## POS: Phone Inputs — Egyptian Mobile Validation

### Overview
- Enforces Egyptian mobile number format on POS customer phone fields.
- Keeps raw digits (no country prefix), preserves leading zeros.
- Validates both Phone and Alternative phone.

### Validation Rules
- Exactly 11 digits.
- Must start with: 010, 011, 012, or 015.
- Regex used: `^0(10|11|12|15)[0-9]{8}$`

### Affected Files
- `resources/views/admin-views/pos/index.blade.php`
  - `#customer_phone` and `#customer_alt_phone`
    - Added: `maxlength="11"`
    - Added: `pattern="0(10|11|12|15)[0-9]{8}"`
    - Title uses translate: `title="{{ translate('egyptian_mobile_input_title') }}"`
  - Injected hidden spans for translated JS messages:
    - `#message-please-enter-customer-phone`
    - `#message-valid-egyptian-mobile`
    - `#message-valid-egyptian-alt-mobile`

- `public/assets/back-end/js/admin/pos-script.js`
  - On keypress: blocks non-digit characters.
  - On input/paste: caps to 11 digits, strips non-digits, preserves leading zeros.
  - On blur: sets HTML5 custom validity using translated message.
  - On order submit: blocks submission with toast and focuses the invalid field if number fails the regex.

### User Experience
- If the phone is empty: shows translated "please enter phone".
- If the phone/alt phone is invalid: shows translated "enter valid Egyptian mobile".
- Native browser tooltip also shows a translated title when failing HTML5 pattern.

### Translations
Add these keys to your locale files (examples below in English and Arabic):

```php
// resources/lang/en/new-messages.php
return [
  // ... existing keys
  'please_enter_customer_phone_number' => 'Please enter customer phone number',
  'enter_valid_egyptian_mobile_number' => 'Enter valid Egyptian mobile number (010/011/012/015 + 8 digits)',
  'enter_valid_egyptian_alternative_mobile_number' => 'Enter valid Egyptian alternative mobile (010/011/012/015 + 8 digits)',
  'egyptian_mobile_input_title' => 'Enter valid Egyptian mobile number (e.g. 010XXXXXXXX, 011XXXXXXXX, 012XXXXXXXX, 015XXXXXXXX)',
];
```

```php
// resources/lang/sa/new-messages.php
return [
  // ... existing keys
  'please_enter_customer_phone_number' => 'يرجى إدخال رقم هاتف العميل',
  'enter_valid_egyptian_mobile_number' => 'أدخل رقم موبايل مصري صحيح (010/011/012/015 + 8 أرقام)',
  'enter_valid_egyptian_alternative_mobile_number' => 'أدخل رقم موبايل بديل مصري صحيح (010/011/012/015 + 8 أرقام)',
  'egyptian_mobile_input_title' => 'أدخل رقم موبايل مصري صحيح (مثال: 010XXXXXXXX أو 011XXXXXXXX أو 012XXXXXXXX أو 015XXXXXXXX)',
];
```

If these keys are missing, the UI may display fallback text. Ensure they exist in all active locales.

### QA Checklist
- Typing/pasting `01155525298` (11 digits) passes; `1155525298` (10 digits) fails.
- Numbers starting with 010/011/012/015 pass; others (e.g., 013…) fail.
- Both fields cap at 11 digits and accept only numerals.
- Invalid inputs show translated messages and block order submission.

### Rollback
- In Blade, remove `maxlength`, `pattern`, and the added `title` attribute on both inputs.
- In JS, remove the keypress/paste/input/blur listeners and the submit-time validation.
- Remove the three hidden `<span>` elements if not needed.



# Email Sender Configuration - Before & After

## BEFORE ❌

Email as received by supplier:

```
From: WordPress <wordpress@cogei.net>
To: fornitore@example.com
Subject: Richiesta Documenti - Cogei.net

[Email content shows generic WordPress sender]
```

**Issues:**
- Generic "WordPress" sender name
- wordpress@cogei.net address (not HSE-specific)
- Appears as automated system email
- Not professional for HSE communications

---

## AFTER ✅

Email as received by supplier:

```
From: HSE COGEI <hse@cogei.net>
To: fornitore@example.com
Subject: Richiesta Documenti - Cogei.net

[Email content shows professional HSE COGEI sender]
```

**Improvements:**
- Professional "HSE COGEI" sender name
- hse@cogei.net address (HSE-specific)
- Clearly identifies HSE department as sender
- Professional appearance for all HSE communications

---

## Technical Implementation

### Files Changed:

1. **BO HSE** (Integration Request Emails)
```php
// BEFORE
$email_sent = wp_mail($user->user_email, $subject, $body, 
    array('Content-Type: text/html; charset=UTF-8'));

// AFTER
$headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: HSE COGEI <hse@cogei.net>'
);
$email_sent = wp_mail($user->user_email, $subject, $body, $headers);
```

2. **cron/cron_controllo_scadenze_hse.php** (Expiry Notifications)
```php
// BEFORE
$headers .= 'From: <no-reply@cogei.net>' . "\r\n";

// AFTER
$headers .= 'From: HSE COGEI <hse@cogei.net>' . "\r\n";
```

3. **FRONT HSE** (Admin Update Notifications)
```php
// BEFORE
$headers .= 'From: <sistema-hse@cogei.net>' . "\r\n";
$headers .= 'Reply-To: <no-reply@cogei.net>' . "\r\n";

// AFTER
$headers .= 'From: HSE COGEI <hse@cogei.net>' . "\r\n";
$headers .= 'Reply-To: HSE COGEI <hse@cogei.net>' . "\r\n";
```

---

## All HSE Emails Now Show Correct Sender

### Email Types Affected:
1. ✉️ **Integration Request** (to suppliers) → From: HSE COGEI
2. ✉️ **Expiry Warnings** (15, 5, 0 days before) → From: HSE COGEI
3. ✉️ **Auto-Suspension** (15 days after expiry) → From: HSE COGEI
4. ✉️ **Admin Updates** (when suppliers change data) → From: HSE COGEI

### Consistency:
- All emails use same sender name: **HSE COGEI**
- All emails use same address: **hse@cogei.net**
- Professional and consistent branding
- Clear identification of HSE department

---

## Testing

To verify the change:
1. Send a document integration request from back office
2. Check the supplier's email inbox
3. Verify sender shows "HSE COGEI <hse@cogei.net>"
4. Verify it does NOT show "WordPress <wordpress@cogei.net>"

---

Commit: 6601e92

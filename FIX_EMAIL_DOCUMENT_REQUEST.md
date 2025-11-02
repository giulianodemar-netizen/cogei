# Fix: BO Albo Fornitori Document Request Email Bug

## Problem Summary

Document request emails from BO Albo Fornitori were being logged as "EMAIL SIMULATA (DEBUG MODE)" instead of being sent as real emails to users.

## Root Cause

The `request_documents` action handler in the "BO ALBO FORNITORI" file (lines 842-1042) was missing the `global $inviamail;` declaration. This caused the `$inviamail` variable to be undefined within the handler's scope, evaluating to `false` by default.

**Impact:**
- Line 971: `if ($inviamail)` - Always evaluated to false, preventing email sending
- Line 984: `!$inviamail` - Always passed `true` as `debug_mode` to the logger
- Result: Emails never sent, always logged as "EMAIL SIMULATA (DEBUG MODE)"

## Solution

Added `$inviamail` to the global variable declaration on line 846:

**Before:**
```php
global $wpdb;
```

**After:**
```php
global $wpdb, $inviamail;
```

## Technical Details

### Variable Scope Issue
- `$GLOBALS['inviamail']` is set to `true` on line 25 of "BO ALBO FORNITORI"
- Without `global $inviamail;` declaration, the variable is not accessible within the conditional block
- PHP treats undefined variables as `false` in boolean contexts

### Comparison with Working Functions

All other email functions in the same file correctly access `$inviamail`:

1. **sendActivationEmail()** (line 74):
   ```php
   $inviamail = $GLOBALS["inviamail"];
   ```

2. **sendDeactivationEmail()** (line 121):
   ```php
   $inviamail = $GLOBALS["inviamail"];
   ```

3. **sendAdminDocumentChangeNotification()** (line 554):
   ```php
   $inviamail = $GLOBALS["inviamail"];
   ```

The fix makes the request_documents handler consistent with these working functions.

## Expected Behavior After Fix

### When $GLOBALS['inviamail'] = true (Production Mode)
- ✅ Document request emails are sent via PHP `mail()` function
- ✅ Log status: "INVIATA CON SUCCESSO" (on successful delivery)
- ✅ Log status: "INVIO FALLITO" (if mail() returns false)
- ✅ Log environment: "PROD"

### When $GLOBALS['inviamail'] = false (Debug Mode)
- ⚠️ Emails are NOT sent (simulation only)
- ⚠️ Log status: "EMAIL SIMULATA (DEBUG MODE)"
- ⚠️ Log environment: "DEBUG"

## How to Verify the Fix

### 1. Check Current Email Configuration
Look for line 25 in "BO ALBO FORNITORI":
```php
$GLOBALS['inviamail'] = true; // Email ATTIVATE - Le email vengono inviate
```

If this is set to `true`, emails should be sent in production.

### 2. Test Document Request Flow
1. Access BO Albo Fornitori admin panel
2. Click "RICHIEDI DOCUMENTI" button for a supplier
3. Fill in the request form
4. Submit the request

### 3. Check Log Output
Check the log file at: `log_mail/log_mail_albo_fornitori.txt`

**Expected log entry (after fix, with inviamail=true):**
```
--------------------------------------------------------------------------------------------------
[02/11/2024 20:45:00] [PROD] RICHIESTA_DOCUMENTI
Destinatario: supplier@example.com
Oggetto: Richiesta Documenti - Cogei.net
Utente: ID=123 | Nome=Example Company | Email=supplier@example.com
Note: Please update your documents
Stato: INVIATA CON SUCCESSO
```

**Previous log entry (before fix):**
```
--------------------------------------------------------------------------------------------------
[02/11/2024 20:45:00] [DEBUG] RICHIESTA_DOCUMENTI
Destinatario: supplier@example.com
Oggetto: Richiesta Documenti - Cogei.net
Utente: ID=123 | Nome=Example Company | Email=supplier@example.com
Note: Please update your documents
Stato: EMAIL SIMULATA (DEBUG MODE)
```

### 4. Email Receipt Verification
- The supplier should receive an email with subject "Richiesta Documenti - Cogei.net"
- Email should contain the request notes entered by the administrator
- Email should be sent from `no-reply@cogei.net`

## Files Modified

- `BO ALBO FORNITORI` (1 line changed)
  - Line 846: Added `, $inviamail` to global declaration

## Related Files

- `includes/log_mail.php` - AlboFornitoriMailLogger class (no changes needed)
- `cron/cron_controllo_scadenze_fornitori.php` - Uses correct $debug_mode pattern (no issues)

## Testing Notes

- No formal test infrastructure exists in the project (no PHPUnit, etc.)
- Testing must be done manually in the WordPress environment
- HTML test files in the repository are UI demonstrations only

## Security Considerations

- The fix maintains existing security patterns
- No new vulnerabilities introduced
- Email sending respects the global configuration flag
- File upload validation remains intact (PDF validation, size limits, etc.)

## Performance Impact

- Minimal: Only adds one variable to the global scope lookup
- No additional database queries
- No changes to email content or processing logic

## Backward Compatibility

- ✅ Fully backward compatible
- ✅ No changes to function signatures
- ✅ No changes to database schema
- ✅ Existing email logs remain valid

## Deployment Notes

1. Deploy the updated "BO ALBO FORNITORI" file
2. No database migrations required
3. No cache clearing required
4. No server restart required
5. Test immediately after deployment using the verification steps above

## Related Issues

This fix addresses the following requirements from the issue:
1. ✅ Locate and fix code path for request document email (fixed missing global declaration)
2. ✅ Ensure same mailer/service used by working emails is reused (now uses same pattern)
3. ❌ Add/update tests (no test infrastructure exists, manual verification only)
4. ✅ Improve logging (logging was correct, but now works with proper email sending)

## Conclusion

This minimal, surgical fix resolves the email sending bug by ensuring the `$inviamail` global variable is properly accessible within the `request_documents` action handler. The change aligns the code with existing working patterns in the same file and maintains all security and functionality requirements.

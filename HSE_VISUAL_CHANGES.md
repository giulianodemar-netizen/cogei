# HSE System Corrections - Visual Changes Guide

## 1. Back Office: UNILAV & Idoneità Sanitaria Documents

### BEFORE ❌
```
📚 Formazioni
Base:
  🔥 Antincendio (Em: 01/01/2025 - Sc: 01/01/2026)
  🚑 Primo Soccorso (Em: 01/01/2025 - Sc: 01/01/2026)

Specifiche:
  🎓 Form. Generale e Specifica (Em: 01/01/2025 - Sc: 01/01/2026)

[No personal documents section - UNILAV and Idoneità not visible]
```

### AFTER ✅
```
📚 Formazioni
Base:
  🔥 Antincendio (Em: 01/01/2025 - Sc: 01/01/2026)
  🚑 Primo Soccorso (Em: 01/01/2025 - Sc: 01/01/2026)

Specifiche:
  🎓 Form. Generale e Specifica (Em: 01/01/2025 - Sc: 01/01/2026)

📋 DATI PERSONALI
  📄 UNILAV (Em: 15/01/2025 - Sc: 15/01/2026)
  🏥 Idoneità Sanitaria (Sc: 20/06/2026)
```

**Key Changes**:
- ✅ New "DATI PERSONALI" section added
- ✅ UNILAV shows emission and expiry dates
- ✅ Idoneità Sanitaria shows expiry date
- ✅ Both are clickable download links (like training docs)

---

## 2. Email Recipients

### BEFORE ❌
All emails sent to: `ufficio_qualita@cogei.net`

### AFTER ✅
All emails sent to: `hse@cogei.net`

**Emails Affected**:
- ✉️ Document expiry notifications (15, 5, 0 days before)
- ✉️ Auto-suspension notifications (-15 days after)
- ✉️ Admin update notifications (when suppliers update data)
- ✉️ Enable/disable access notifications

---

## 3. Equipment Expiry Calculation

### BEFORE ❌ (Bug Example)
```
Current Date/Time: 13/02/2026 at 14:30:45
Equipment Expiry: 18/02/2026
Calculation: 
  - Now: 2026-02-13 14:30:45
  - Expiry: 2026-02-18 23:59:59
  - Difference: 5 days, 9 hours, 29 minutes
  - Days calculated: ~4.4 days → rounds to 4 days
Result: Shows "4 days remaining" ❌ (Should be 5)
Status: "🚨 SCADENZA IMMINENTE" (wrong, triggers too early)
```

### AFTER ✅ (Fixed)
```
Current Date/Time: 13/02/2026 at 14:30:45
Equipment Expiry: 18/02/2026
Calculation:
  - Now: 2026-02-13 00:00:00 (normalized to midnight)
  - Expiry: 2026-02-18 00:00:00 (normalized to midnight)
  - Difference: 5 days exactly
  - Days calculated: 5 days
Result: Shows "5 days remaining" ✅ (Correct)
Status: "⚠️ AVVISO" (correct, appropriate timing)
```

**Key Changes**:
- ✅ Dates normalized to midnight for consistent calculations
- ✅ Time of day doesn't affect day count
- ✅ No more premature expiry warnings
- ✅ Accurate countdown to expiry date

**Affected Scenarios**:
- Equipment revision dates (PARTE B)
- Equipment insurance dates
- Equipment periodic verification dates
- Worker UNILAV expiry
- Worker Idoneità Sanitaria expiry
- All training document expiries

---

## 4. Integration Request Email

### BEFORE ❌
```
Subject: Richiesta Documenti

Gentile [Supplier Name],

hai ricevuto una richiesta di documenti dal pannello di amministrazione:

Richiesta:
[Request text from admin]

Richiesto da: Giovanni Brida
Data richiesta: 15/02/2026 14:30

Ti preghiamo di caricare i documenti...
```

### AFTER ✅
```
Subject: Richiesta Documenti

Gentile [Supplier Name],

hai ricevuto una richiesta di integrazione dei documenti dal gestore HSE:

Richiesta:
[Request text from admin]

Data richiesta: 15/02/2026 14:30

Ti preghiamo di caricare i documenti...
```

**Key Changes**:
- ✅ "dal pannello di amministrazione" → "dal gestore HSE"
- ✅ "richiesta di documenti" → "richiesta di integrazione dei documenti"
- ✅ Removed personal name "Richiesto da: Giovanni Brida"
- ✅ More professional and generic phrasing

---

## 5. Suspension Email Text

### BEFORE ❌
```
Per ripristinare l'accesso è necessario:
1. Aggiornare tutta la documentazione scaduta
2. Contattare l'ufficio qualità: ufficio_qualita@cogei.net
```

### AFTER ✅
```
Per ripristinare l'accesso è necessario:
1. Aggiornare tutta la documentazione scaduta
2. Contattare il gestore HSE: hse@cogei.net
```

**Key Changes**:
- ✅ "ufficio qualità" → "gestore HSE"
- ✅ Updated email address

---

## Visual Impact Summary

### User-Facing Changes
1. **Suppliers**: Can see their UNILAV and Idoneità documents in back office
2. **Suppliers**: Receive more professional integration request emails
3. **Suppliers**: Get accurate expiry warnings (not premature)
4. **Administrators**: See all personal documents when reviewing suppliers
5. **HSE Department**: Receives all notifications at correct email address

### Behind-the-Scenes Changes
- 8 date calculation fixes across codebase
- 6 email address updates
- Consistent terminology ("gestore HSE" everywhere)

---

## Testing Scenarios

### Scenario 1: View Worker Documents (Admin)
1. Login to back office as admin
2. Navigate to HSE section
3. View a supplier's workers
4. **Verify**: UNILAV appears under "DATI PERSONALI"
5. **Verify**: Idoneità Sanitaria appears under "DATI PERSONALI"
6. **Verify**: Can click and download both documents

### Scenario 2: Expiry Warning Accuracy
1. Create test equipment with expiry date 5 days from now
2. Check at morning (e.g., 09:00)
3. **Verify**: Shows "5 days remaining"
4. Check at evening (e.g., 18:00)
5. **Verify**: Still shows "5 days remaining" (not 4)
6. Check next day
7. **Verify**: Shows "4 days remaining"

### Scenario 3: Email Recipient
1. Update a worker's document as supplier
2. Check email logs
3. **Verify**: Notification sent to hse@cogei.net
4. **Verify**: NOT sent to ufficio_qualita@cogei.net

### Scenario 4: Integration Request
1. Send integration request from back office
2. Check supplier's email
3. **Verify**: Says "dal gestore HSE"
4. **Verify**: No personal name appears
5. **Verify**: Only shows request date

---

## Rollback Indicators

If you need to rollback, look for these issues:
- ❌ UNILAV/Idoneità not showing in back office
- ❌ Equipment showing as expired too early
- ❌ Emails going to wrong address
- ❌ Integration emails showing personal names

To rollback:
```bash
git revert HEAD~4..HEAD
git push
```

---

## Success Metrics

After deployment, verify:
- ✅ No premature expiry warnings
- ✅ All emails to hse@cogei.net
- ✅ Suppliers can see personal documents
- ✅ Integration requests are professional
- ✅ No complaints about incorrect expiry dates

---

## Support Contact

For issues or questions:
- HSE Department: hse@cogei.net
- Technical Support: [Your IT contact]
- Documentation: See HSE_CORRECTIONS_SUMMARY.md

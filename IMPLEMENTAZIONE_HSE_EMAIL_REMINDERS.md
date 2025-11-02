# Implementazione Sistema Email Reminder e Notifiche HSE

## Sommario delle Modifiche

Questo PR aggiunge al sistema HSE le stesse funzionalità già presenti nell'Albo Fornitori:

1. ✅ Email reminder automatiche per scadenze documenti/formazioni
2. ✅ Log separati per HSE (prefisso `log_hse_`)
3. ✅ Notifiche email all'admin quando gli utenti aggiornano i dati
4. ✅ Interfaccia BO HSE con tab Log Email e Log Disattivazioni
5. ✅ Cron job separato per HSE

## File Nuovi Creati

### 1. `/includes/log_mail_hse.php`
Logger centralizzato per tutte le email HSE. Gestisce:
- Log delle email di notifica scadenze
- Log delle notifiche admin
- Formato log consistente con quello dell'Albo Fornitori

### 2. `/cron/cron_controllo_scadenze_hse.php`
Cron job indipendente per il controllo scadenze HSE. Controlla:
- **Formazioni personale**: antincendio, primo soccorso, preposti, generale/specifica, RSPP, RLS, ASPP, PLE, carrelli
- **Documenti personale**: idoneità sanitaria, UNILAV
- **Scadenze mezzi**: revisione, assicurazione, verifiche periodiche
- **Revisioni attrezzi**

**Trigger days**: 15, 5, 0, -15 giorni (come l'Albo Fornitori)

### 3. File di log generati automaticamente:
- `/log_mail/log_hse_mail.txt` - Log email HSE
- `/log_mail/log_hse_disattivazioni.txt` - Log disattivazioni automatiche HSE

## File Modificati

### 1. `BO HSE`
**Modifiche:**
- Aggiunto include del logger HSE
- Cambiato `$inviamail` da `false` a `true` (email attivate)
- Aggiunti 2 nuovi tab button: "📧 Log Email" e "🔴 Log Disattivazioni"
- Aggiunta sezione `content-log-email` con visualizzazione log email paginata
- Aggiunta sezione `content-log-disattivazioni` con visualizzazione log disattivazioni paginata
- Aggiornato JavaScript per gestire parametro URL `tab` (per navigazione diretta)

**Funzionalità tab Log Email:**
- Mostra tutte le email inviate dal sistema HSE
- Colori diversi per tipo email (scadenza 15gg, 5gg, oggi, disattivazione, ecc.)
- Badge DEBUG/PROD per distinguere email simulate da quelle reali
- Paginazione (20 voci per pagina)

**Funzionalità tab Log Disattivazioni:**
- Mostra log delle sospensioni automatiche utenti HSE
- Timestamp e user ID evidenziati
- Messaggio dettagliato per ogni disattivazione

### 2. `FRONT HSE`
**Modifiche:**
- Aggiunto include del logger HSE
- Aggiunta funzione `sendHseAdminUpdateNotification()` per notificare l'admin
- Chiamata a notifica admin dopo salvataggio Parte A (personale, mezzi, attrezzi)
- Chiamata a notifica admin dopo salvataggio Parte B (assegnazioni cantiere)

**Email inviate all'admin quando:**
- Un utente aggiorna i dati della Parte A
- Un utente aggiorna le assegnazioni della Parte B
- Dettagli inclusi: numero personale, mezzi, attrezzi, cantiere assegnato

**Destinatario**: `ufficio_qualita@cogei.net`

## Configurazione Cron

Il cron deve essere eseguito giornalmente. Configurazione consigliata:

```bash
# Esegui alle 9:00 ogni giorno
0 9 * * * /usr/bin/php /path/to/cogei/cron/cron_controllo_scadenze_hse.php >> /var/log/hse_cron.log 2>&1
```

### Test Manuale del Cron

Per testare il cron in modalità DEBUG (senza inviare email reali):

1. Apri `cron/cron_controllo_scadenze_hse.php`
2. Modifica `$debug_mode = true;`
3. Esegui: `php cron/cron_controllo_scadenze_hse.php`

Output di esempio:
```
===================================================
CRON CONTROLLO SCADENZE HSE - 02/11/2025 21:38:42
Modalità: DEBUG (no email reali)
===================================================

Trovati 5 utenti HSE da controllare

[1/5] Controllo utente ID: 123 - Azienda XYZ
  → Trovati 15 documenti con scadenze
  → Trigger 15 giorni: 3 documenti
    ✓ Email inviata con successo
  
...
```

## Logica delle Notifiche Scadenze

Il cron invia email agli utenti nei seguenti momenti:

| Giorni alla Scadenza | Tipo Notifica | Azione |
|----------------------|---------------|---------|
| 15 giorni prima | ⚠️ AVVISO | Email di preavviso |
| 5 giorni prima | 🚨 URGENTE | Email urgente |
| 0 (oggi) | ❌ CRITICO | Email critica - documento scaduto |
| -15 (15 gg dopo) | 🔴 SOSPENSIONE | Sospensione automatica + email notifica |

**Anti-spam**: Ogni email viene inviata max 1 volta ogni 24 ore per lo stesso trigger.

## Separazione Completa da Albo Fornitori

✅ **File cron separati**: 
- Albo Fornitori: `cron_controllo_scadenze_fornitori.php`
- HSE: `cron_controllo_scadenze_hse.php`

✅ **File log separati**:
- Albo Fornitori: `log_mail_albo_fornitori.txt`, `log_cron_disattivazioni.txt`
- HSE: `log_hse_mail.txt`, `log_hse_disattivazioni.txt`

✅ **Logger separati**:
- Albo Fornitori: `AlboFornitoriMailLogger` class
- HSE: `HseMailLogger` class

✅ **User meta separati**:
- Albo Fornitori: `forced_supplier_status`, `cron_email_last_sent_{day}`
- HSE: `hse_access_status`, `cron_hse_email_last_sent_{day}`

## Email Template

Le email HSE seguono lo stesso stile visivo dell'Albo Fornitori:
- Logo Cogei in header
- Colori diversi per gravità (giallo, arancio, rosso)
- Footer con contatti azienda
- HTML responsive

## Monitoraggio e Debug

### Verificare log email:
1. Vai in **BO HSE** → **📧 Log Email**
2. Controlla le voci recenti
3. Badge verde "PROD" = email reale inviata
4. Badge giallo "DEBUG" = email simulata

### Verificare log disattivazioni:
1. Vai in **BO HSE** → **🔴 Log Disattivazioni**
2. Mostra tutti gli utenti sospesi automaticamente
3. Include timestamp, user ID e motivo

### Verificare file log direttamente:
```bash
# Log email
tail -f log_mail/log_hse_mail.txt

# Log disattivazioni
tail -f log_mail/log_hse_disattivazioni.txt
```

## Test Implementati

✅ Test sintassi PHP:
```bash
php -l cron/cron_controllo_scadenze_hse.php
php -l includes/log_mail_hse.php
php -l "BO HSE"
php -l "FRONT HSE"
```

✅ Test funzionalità:
- Script di test: `/tmp/test_hse_cron.sh`
- Verifica file esistenti
- Verifica sintassi PHP
- Verifica directory log

## Differenze Rispetto ad Albo Fornitori

| Aspetto | Albo Fornitori | HSE |
|---------|----------------|-----|
| **Documenti controllati** | CCIAA, White List, DURC, RCT-RCO, SOA, Altre Scadenze | Formazioni personale, idoneità sanitaria, UNILAV, scadenze mezzi, revisioni attrezzi |
| **Tipo utenti** | Fornitori (subscriber role) | Utenti con richieste cantiere (Lavoro, Subappalto, Nolo) |
| **Disattivazione** | `forced_supplier_status` = "Disattivo" | `hse_access_status` = "Sospeso" |
| **Admin email** | ufficio_qualita@cogei.net | ufficio_qualita@cogei.net |
| **Trigger notifiche** | 15, 5, 0, -15 giorni | 15, 5, 0, -15 giorni |

## Compatibilità

✅ Nessuna modifica al cron Albo Fornitori esistente
✅ Logger Albo Fornitori non modificato  
✅ BO ALBO FORNITORI non modificato
✅ Totale retrocompatibilità

## Note per Deploy

1. **Permessi file log**: Assicurati che la directory `log_mail/` sia scrivibile dal web server
   ```bash
   chmod 755 log_mail/
   ```

2. **Configurare cron**: Aggiungi il cron job HSE senza rimuovere quello dell'Albo Fornitori
   ```bash
   crontab -e
   # Aggiungi la riga per HSE
   ```

3. **Test post-deploy**: Esegui il cron manualmente in modalità DEBUG per verificare il funzionamento

4. **Monitoraggio**: Controlla i log nei primi giorni per assicurarsi che le email vengano inviate correttamente

## Supporto

Per problemi o domande:
- Email: ufficio_qualita@cogei.net
- Verificare i log in `log_mail/` per debug
- Eseguire cron in modalità DEBUG per test

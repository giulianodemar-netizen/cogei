# Riepilogo Email e Azioni - Albo Fornitori

## A) Email dal Pannello BO ALBO FORNITORI

Le seguenti email possono essere inviate dal pannello di backoffice:

### 1. **Attivazione Utente**
- **Quando viene inviata**: Quando un amministratore clicca sul pulsante "ATTIVA" per attivare manualmente un fornitore
- **Destinatario**: Email del fornitore
- **Oggetto**: "Il tuo account su Cogei.net è stato attivato"
- **Contenuto**: Notifica che la richiesta di qualifica è stata accettata e invita a mantenere aggiornata la documentazione
- **Log**: Registrato in log_mail/log_mail_albo_fornitori.txt come "ATTIVAZIONE_UTENTE"

### 2. **Disattivazione Utente**
- **Quando viene inviata**: Quando un amministratore clicca sul pulsante "DISATTIVA" per disattivare manualmente un fornitore
- **Destinatario**: Email del fornitore
- **Oggetto**: "Il tuo account su Cogei.net è stato disattivato"
- **Contenuto**: Informa che l'iscrizione è decaduta per mancato aggiornamento documentazione
- **Log**: Registrato in log_mail/log_mail_albo_fornitori.txt come "DISATTIVAZIONE_UTENTE"

### 3. **Richiesta Documenti**
- **Quando viene inviata**: Quando un amministratore clicca sul pulsante "📄 RICHIEDI DOCUMENTI" e invia una richiesta
- **Destinatario**: Email del fornitore
- **Oggetto**: "Richiesta Documenti - Cogei.net"
- **Contenuto**: 
  - Nota personalizzata dell'amministratore con i dettagli della richiesta
  - Nome dell'amministratore che ha fatto la richiesta
  - Data della richiesta
  - Eventuali file PDF allegati (con link per il download)
- **Allegati**: Fino a 10 MB per file PDF (validazione server-side)
- **Log**: Registrato in log_mail/log_mail_albo_fornitori.txt come "RICHIESTA_DOCUMENTI" con lista allegati

### 4. **Notifica Admin - Modifica Documenti**
- **Quando viene inviata**: Quando il sistema rileva modifiche nei documenti di un fornitore e lo disattiva automaticamente
- **Destinatario**: ufficio_qualita@cogei.net
- **Oggetto**: "ALERT: Disattivazione automatica fornitore per modifica documenti"
- **Contenuto**:
  - ID e ragione sociale del fornitore
  - Lista dei documenti modificati (vecchia data → nuova data)
  - Azioni automatiche eseguite dal sistema
- **Log**: Registrato in log_mail/log_mail_albo_fornitori.txt come "NOTIFICA_ADMIN_MODIFICHE_DOCUMENTI"

---

## B) Email e Azioni dal Cron Controllo Scadenze

Il cron `cron/cron_controllo_scadenze_fornitori.php` deve essere eseguito giornalmente e verifica le scadenze dei documenti per tutti i fornitori.

### Trigger Days e Azioni

#### **Trigger: 15 giorni prima della scadenza (+15)**
- **Email**: ⚠️ Avviso Scadenza Documenti (15 giorni)
- **Oggetto**: "⚠️ AVVISO: Documenti in scadenza tra 15 giorni - Cogei.net"
- **Destinatario**: Email del fornitore
- **Contenuto**: 
  - Avviso preventivo con lista documenti in scadenza
  - Invito a rinnovare tempestivamente
- **Azioni**: Solo notifica email (nessuna modifica allo stato)
- **Log**: Registrato come "AVVISO_SCADENZA_15_GIORNI"

#### **Trigger: 5 giorni prima della scadenza (+5)**
- **Email**: 🚨 Avviso Urgente Scadenza
- **Oggetto**: "🚨 URGENTE: Documenti in scadenza tra 5 giorni - Cogei.net"
- **Destinatario**: Email del fornitore
- **Contenuto**: 
  - Avviso urgente con lista documenti in scadenza
  - Enfasi sulla necessità di agire subito
- **Azioni**: Solo notifica email (nessuna modifica allo stato)
- **Log**: Registrato come "AVVISO_SCADENZA_5_GIORNI"

#### **Trigger: Giorno della scadenza (0)**
- **Email**: ❌ Documenti Scaduti Oggi
- **Oggetto**: "❌ CRITICO: Documenti scaduti oggi - Cogei.net"
- **Destinatario**: Email del fornitore
- **Contenuto**: 
  - Avviso critico che i documenti sono scaduti oggi
  - Avvertimento che l'account potrebbe essere disattivato
  - Richiesta di agire immediatamente
- **Azioni**: Solo notifica email (nessuna modifica allo stato ancora)
- **Log**: Registrato come "AVVISO_SCADENZA_OGGI"

#### **Trigger: 15 giorni dopo la scadenza (-15)**
- **Email 1**: 🔴 Disattivazione Account
  - **Oggetto**: "🔴 DISATTIVAZIONE: Account disattivato per documenti scaduti - Cogei.net"
  - **Destinatario**: Email del fornitore
  - **Contenuto**: 
    - Notifica di disattivazione automatica dell'account
    - Lista documenti scaduti da oltre 15 giorni
    - Istruzioni per la riattivazione (aggiornare documenti e contattare ufficio qualità)
  - **Log**: Registrato come "AVVISO_DISATTIVAZIONE_15_GIORNI"

- **Email 2**: Notifica Admin Disattivazioni
  - **Oggetto**: "CRON: N fornitori disattivati automaticamente"
  - **Destinatario**: ufficio_qualita@cogei.net
  - **Contenuto**: 
    - Numero totale di fornitori disattivati
    - Lista con ID, nome ed email di ciascun fornitore disattivato
    - Data e ora esecuzione cron
  - **Log**: Registrato come "CRON_NOTIFICA_ADMIN_DISATTIVAZIONI"

- **Azioni**:
  1. Imposta `forced_supplier_status` a 'Disattivo' nel database
  2. Invia email di notifica al fornitore
  3. Invia email riepilogativa all'amministratore
  4. Log separato in log_mail/log_cron_disattivazioni.txt

### Modalità Debug del Cron

Il cron supporta una modalità DEBUG (`$debug_mode = true`):
- **Email**: Non vengono inviate realmente
- **Log**: Tutte le email vengono registrate come "SIMULATE"
- **Azioni**: Le disattivazioni vengono comunque eseguite nel database
- **Utilizzo**: Per test e sviluppo senza inviare email reali

### Documenti Monitorati dal Cron

Il cron controlla le scadenze dei seguenti documenti:

**Sempre richiesti:**
- CCIAA
- White List
- DURC
- Altre Scadenze

**Richiesti solo per Lavoro, Servizi, Subappalto, Noli:**
- RCT-RCO

**Non richiesto per Forniture, Consulenze, Polizze:**
- RCT-RCO non viene controllato

**Mai fondamentale:**
- SOA (non viene controllato dal cron)

---

## Log Centralizzato

Tutte le email (sia dal BO che dal cron) vengono registrate in:
- **File principale**: `log_mail/log_mail_albo_fornitori.txt`
- **File disattivazioni cron**: `log_mail/log_cron_disattivazioni.txt`

Ogni voce di log include:
- Timestamp
- Ambiente (DEBUG/PROD)
- Tipo email
- Destinatario
- Oggetto
- Dati utente (ID, nome, email)
- Documenti coinvolti
- Allegati (se presenti)
- Stato invio (successo/fallito/simulato)

---

## Configurazione

### BO ALBO FORNITORI
- **Invio email**: Abilitato (`$inviamail = true`)
- **File**: `/BO ALBO FORNITORI` (file PHP)
- **Logger**: Incluso automaticamente da `includes/log_mail.php`

### CRON
- **File**: `cron/cron_controllo_scadenze_fornitori.php`
- **Esecuzione**: Giornaliera (configurare nel crontab del server)
- **Debug mode**: `$debug_mode = false` (cambiare a `true` per test)
- **Esempio crontab**: 
  ```
  0 6 * * * /usr/bin/php /path/to/cogei/cron/cron_controllo_scadenze_fornitori.php
  ```
  (Esegue alle 6:00 AM ogni giorno)

---

## Sicurezza Upload PDF

Per la funzione "RICHIEDI DOCUMENTI" con allegati:

**Validazione Client-Side:**
- Solo file PDF accettati
- Dimensione massima 10 MB per file
- Alert JavaScript per file non validi

**Validazione Server-Side:**
- Verifica MIME type (`application/pdf`)
- Verifica estensione (`.pdf`)
- Verifica dimensione (max 10 MB)
- Rinomina file: `{userID}_{timestamp}_{nome_originale}.pdf`

**Sicurezza:**
- Directory protetta con `.htaccess` (disabilita esecuzione PHP)
- Permessi file 0644
- Directory listing disabilitato
- Percorso: `wp-content/uploads/allegati_richieste_documenti/`

# Pull Request Completata: BO ALBO FORNITORI - Email, Log e Cron

## Riepilogo Completo delle Modifiche

Questa PR implementa tutte le modifiche richieste per il sistema Albo Fornitori, includendo attivazione email, logging centralizzato, miglioramenti UI, upload PDF e refactor del cron per controllo scadenze.

---

## A) Email che POTREBBERO PARTIRE dal Pannello BO ALBO FORNITORI

### 1. Email di Attivazione Fornitore
**Quando viene inviata**: Quando un amministratore clicca sul pulsante "ATTIVA" per attivare manualmente un fornitore disattivato

**Dettagli**:
- **Destinatario**: Email del fornitore
- **Oggetto**: "Il tuo account su Cogei.net è stato attivato"
- **Contenuto**: 
  - Notifica di accettazione della richiesta di qualifica
  - Invito a mantenere aggiornata la documentazione
  - Logo Cogei e footer con contatti aziendali
- **Trigger**: Click manuale su pulsante "ATTIVA" da parte dell'amministratore
- **Condizioni**: Il fornitore deve avere documenti caricati per poter essere attivato

---

### 2. Email di Disattivazione Fornitore
**Quando viene inviata**: Quando un amministratore clicca sul pulsante "DISATTIVA" per disattivare manualmente un fornitore

**Dettagli**:
- **Destinatario**: Email del fornitore
- **Oggetto**: "Il tuo account su Cogei.net è stato disattivato"
- **Contenuto**: 
  - Notifica che l'iscrizione è decaduta per mancato aggiornamento documentazione
  - Contatto ufficio qualità per maggiori informazioni
  - Logo Cogei e footer con contatti aziendali
- **Trigger**: Click manuale su pulsante "DISATTIVA" da parte dell'amministratore
- **Condizioni**: Può essere fatto per qualsiasi fornitore attivo

---

### 3. Email di Richiesta Documenti
**Quando viene inviata**: Quando un amministratore clicca sul pulsante "📄 RICHIEDI DOCUMENTI" e compila il modal con la richiesta

**Dettagli**:
- **Destinatario**: Email del fornitore
- **Oggetto**: "Richiesta Documenti - Cogei.net"
- **Contenuto**: 
  - Intestazione con sfondo blu e titolo "Richiesta Documenti"
  - Nota personalizzata dell'amministratore (minimo 10 caratteri)
  - Nome dell'amministratore che ha fatto la richiesta
  - Data e ora della richiesta
  - Sezione allegati (se presenti) con link per scaricare i PDF
  - Invito ad aggiornare i documenti nell'area privata
  - Footer con contatti Cogei
- **Trigger**: Compilazione e invio del modal "RICHIEDI DOCUMENTI"
- **Allegati**: Possibilità di allegare uno o più file PDF (max 10 MB ciascuno)
- **Sicurezza allegati**:
  - Validazione MIME type (application/pdf)
  - Validazione estensione (.pdf)
  - Validazione dimensione (max 10 MB per file)
  - File rinominati: `{userID}_{timestamp}_{nome_sanitizzato}.pdf`
  - Storage sicuro: wp-content/uploads/allegati_richieste_documenti/
  - PHP execution disabled con .htaccess
- **Condizioni**: Può essere inviata a qualsiasi fornitore

---

### 4. Email di Notifica Admin - Modifica Documenti
**Quando viene inviata**: Automaticamente quando il sistema rileva che un fornitore ha modificato date di scadenza dei documenti nel database

**Dettagli**:
- **Destinatario**: ufficio_qualita@cogei.net
- **Oggetto**: "ALERT: Disattivazione automatica fornitore per modifica documenti"
- **Contenuto**: 
  - Alert rosso con intestazione "🚨 ALERT - Disattivazione Automatica"
  - ID e ragione sociale del fornitore disattivato
  - Lista completa dei documenti modificati con:
    - Nome documento
    - Data vecchia
    - Data nuova
  - Riepilogo azioni automatiche eseguite:
    - Fornitore disattivato automaticamente
    - Stato forzato a 'Disattivo' nel sistema
    - Log creato
  - Data e ora del rilevamento
  - Footer Cogei
- **Trigger**: Controllo automatico che confronta dati live con copia salvata
- **Azioni automatiche**:
  - Disattivazione immediata del fornitore
  - Salvataggio stato forzato nel database
  - Logging in file separato
- **Condizioni**: Viene inviata solo se vengono rilevate modifiche effettive nelle date dei documenti

---

## B) Email e Azioni che si Verificano Lanciando il CRON

Il file `cron/cron_controllo_scadenze_fornitori.php` deve essere eseguito giornalmente (es: alle 6:00 AM) tramite crontab:

```bash
0 6 * * * /usr/bin/php /path/to/cogei/cron/cron_controllo_scadenze_fornitori.php
```

Il cron analizza TUTTI i fornitori (utenti con ruolo subscriber) e controlla le scadenze dei loro documenti.

### Documenti Controllati dal Cron

**Sempre controllati per tutti i fornitori**:
- CCIAA
- White List
- DURC
- Altre Scadenze

**Controllati solo per tipo: Lavoro, Servizi, Subappalto, Noli**:
- RCT-RCO

**Controllati solo per tipo: Forniture**:
- RCT-RCO (Forniture) - campo diverso

**NON controllati per: Consulenze, Polizze**:
- RCT-RCO non necessario

**Mai controllato**:
- SOA (non fondamentale per nessun tipo)

---

### TRIGGER DAY: +15 (15 giorni prima della scadenza)

**Email inviata**: ⚠️ Avviso Scadenza Documenti (15 giorni)

**Dettagli**:
- **Destinatario**: Email del fornitore
- **Oggetto**: "⚠️ AVVISO: Documenti in scadenza tra 15 giorni - Cogei.net"
- **Contenuto**:
  - Box giallo di avviso con icona ⚠️
  - Saluto personalizzato con ragione sociale
  - Lista di TUTTI i documenti in scadenza tra 15 giorni con:
    - Nome documento
    - Data di scadenza
    - Giorni rimanenti
  - Invito a rinnovare tempestivamente
  - Link implicito all'area privata
  - Footer Cogei
- **Azioni sistema**:
  - Invio email al fornitore
  - Log in log_mail_albo_fornitori.txt come "AVVISO_SCADENZA_15_GIORNI"
  - NESSUNA modifica allo stato del fornitore
- **Note**:
  - Email consolidata: se più documenti scadono tra 15 giorni, viene inviata UNA SOLA email con tutti i documenti
  - Puramente informativa, nessuna azione punitiva

---

### TRIGGER DAY: +5 (5 giorni prima della scadenza)

**Email inviata**: 🚨 Avviso Urgente Scadenza

**Dettagli**:
- **Destinatario**: Email del fornitore
- **Oggetto**: "🚨 URGENTE: Documenti in scadenza tra 5 giorni - Cogei.net"
- **Contenuto**:
  - Box rosso chiaro con bordo rosso e icona 🚨
  - Titolo "URGENTE - Documenti in scadenza"
  - Testo con enfasi "ATTENZIONE:" in rosso
  - Lista di TUTTI i documenti in scadenza tra 5 giorni
  - Enfasi su "È URGENTE" rinnovare
  - Invito ad accedere IMMEDIATAMENTE all'area privata
  - Footer Cogei
- **Azioni sistema**:
  - Invio email al fornitore
  - Log in log_mail_albo_fornitori.txt come "AVVISO_SCADENZA_5_GIORNI"
  - NESSUNA modifica allo stato del fornitore
- **Note**:
  - Email consolidata: tutti i documenti a +5 giorni in una singola email
  - Tono più urgente ma ancora senza penalizzazioni

---

### TRIGGER DAY: 0 (Giorno della scadenza - scaduto oggi)

**Email inviata**: ❌ Documenti Scaduti Oggi - CRITICO

**Dettagli**:
- **Destinatario**: Email del fornitore
- **Oggetto**: "❌ CRITICO: Documenti scaduti oggi - Cogei.net"
- **Contenuto**:
  - Box rosso scuro con testo bianco e icona ❌
  - Titolo "CRITICO - Documenti Scaduti"
  - Testo in grassetto rosso "I seguenti documenti sono SCADUTI OGGI:"
  - Lista di TUTTI i documenti scaduti oggi
  - Avviso che l'account potrebbe essere disattivato
  - Chiamata all'azione "AGISCI SUBITO"
  - Footer Cogei
- **Azioni sistema**:
  - Invio email al fornitore
  - Log in log_mail_albo_fornitori.txt come "AVVISO_SCADENZA_OGGI"
  - NESSUNA modifica allo stato del fornitore (ancora)
- **Note**:
  - Email consolidata: tutti i documenti scaduti oggi in una email
  - Avviso di possibile disattivazione imminente
  - Ultimo avviso prima della disattivazione (-15 giorni)

---

### TRIGGER DAY: -15 (15 giorni dopo la scadenza)

Questo è il trigger più importante con multiple azioni automatiche.

#### **Email 1: Notifica Disattivazione al Fornitore**

**Dettagli**:
- **Destinatario**: Email del fornitore
- **Oggetto**: "🔴 DISATTIVAZIONE: Account disattivato per documenti scaduti - Cogei.net"
- **Contenuto**:
  - Box rosso molto scuro con testo bianco e icona 🔴
  - Titolo "ACCOUNT DISATTIVATO"
  - Notifica che l'account è stato disattivato AUTOMATICAMENTE
  - Lista di TUTTI i documenti scaduti da oltre 15 giorni con:
    - Nome documento
    - Data scadenza
    - Giorni di scadenza (es: "Scaduto da 15 giorni")
  - Istruzioni per riattivazione:
    1. Aggiornare tutta la documentazione scaduta
    2. Contattare ufficio_qualita@cogei.net
  - Avviso che non può partecipare a gare fino alla riattivazione
  - Footer Cogei
- **Azioni sistema per il fornitore**:
  - Invio email al fornitore
  - Log in log_mail_albo_fornitori.txt come "AVVISO_DISATTIVAZIONE_15_GIORNI"

#### **Email 2: Notifica Admin - Riepilogo Disattivazioni**

**Dettagli**:
- **Destinatario**: ufficio_qualita@cogei.net
- **Oggetto**: "CRON: N fornitori disattivati automaticamente"
  - (N = numero di fornitori disattivati in questa esecuzione)
- **Contenuto**:
  - Box rosso scuro con testo bianco e icona 🚨
  - Titolo "CRON - Disattivazioni Automatiche"
  - Numero totale di fornitori disattivati
  - Lista completa dei fornitori disattivati:
    - ID fornitore
    - Ragione sociale
    - Email
  - Data e ora esecuzione cron
  - Footer sistema cron
- **Azioni sistema per l'admin**:
  - Invio email riepilogativa all'admin
  - Log in log_mail_albo_fornitori.txt come "CRON_NOTIFICA_ADMIN_DISATTIVAZIONI"
- **Condizioni**: Email inviata SOLO se almeno un fornitore è stato disattivato

#### **Azioni Automatiche sui Fornitori**:

Per ogni fornitore con documenti scaduti da 15+ giorni:

1. **Disattivazione Database**:
   - Campo `forced_supplier_status` impostato a 'Disattivo'
   - Tabella: wp_usermeta
   - Effetto: il fornitore viene mostrato come "Disattivo" nel pannello BO

2. **Logging Separato**:
   - Scrittura in `log_mail/log_cron_disattivazioni.txt`
   - Include: timestamp, ID fornitore, motivo disattivazione

3. **Notifiche Email**:
   - Email al fornitore (spiegando disattivazione)
   - Email all'admin (riepilogo tutti i disattivati)

4. **Nessun Rollback Automatico**:
   - La disattivazione è permanente
   - Richiede intervento manuale admin per riattivazione
   - Fornitore deve prima aggiornare tutti i documenti scaduti

---

### Modalità DEBUG del Cron

Il cron supporta una modalità DEBUG configurabile modificando la variabile `$debug_mode` nel file:

```php
$debug_mode = true; // Modalità DEBUG attiva
```

**Comportamento in modalità DEBUG**:
- ✅ Il cron viene eseguito normalmente
- ✅ Tutti i controlli vengono effettuati
- ✅ Le disattivazioni vengono EFFETTUATE nel database
- ❌ Le email NON vengono inviate realmente
- ✅ Le email vengono REGISTRATE nel log come "SIMULATE"
- ✅ Output console dettagliato per debugging
- ✅ Tutti i log includono ambiente "DEBUG"

**Utilizzo consigliato**:
- Attivare DEBUG per i primi test
- Verificare log per confermare logica corretta
- Verificare che le disattivazioni funzionino
- Disattivare DEBUG quando si va in produzione

---

## Logging Centralizzato

### File di Log Principale
**Path**: `log_mail/log_mail_albo_fornitori.txt`

**Contenuto**:
- Tutte le email dal pannello BO
- Tutte le email dal cron
- Formato strutturato con:
  - Separatore visuale (linea di trattini)
  - Timestamp [DD/MM/YYYY HH:MM:SS]
  - Ambiente [DEBUG] o [PROD]
  - Tipo email (es: ATTIVAZIONE_UTENTE, AVVISO_SCADENZA_15_GIORNI)
  - Destinatario: email
  - Oggetto: testo completo
  - Utente: ID, Nome, Email
  - Documenti coinvolti (se applicabile):
    - Nome documento
    - Data scadenza
    - Giorni (se scadenza)
  - Allegati (se presenti): nomi file
  - Note (se presenti)
  - Stato: EMAIL SIMULATA (DEBUG) / INVIATA CON SUCCESSO / INVIO FALLITO

### File di Log Disattivazioni Cron
**Path**: `log_mail/log_cron_disattivazioni.txt`

**Contenuto**:
- Solo disattivazioni automatiche effettuate dal cron
- Una riga per disattivazione
- Formato: [timestamp] CRON: Fornitore ID XXX disattivato automaticamente per documenti scaduti da oltre 15 giorni

### Come Leggere i Log

**Vedere ultimi 50 log**:
```bash
tail -n 50 log_mail/log_mail_albo_fornitori.txt
```

**Cercare email per un fornitore specifico**:
```bash
grep "user@example.com" log_mail/log_mail_albo_fornitori.txt
```

**Vedere solo email DEBUG**:
```bash
grep "DEBUG" log_mail/log_mail_albo_fornitori.txt
```

**Vedere solo disattivazioni cron**:
```bash
cat log_mail/log_cron_disattivazioni.txt
```

---

## Sicurezza Implementata

### Upload PDF
1. **Validazione Client-Side** (JavaScript):
   - Accept solo `.pdf` nel file input
   - Controllo dimensione max 10 MB
   - Controllo MIME type
   - Alert immediato per file non validi

2. **Validazione Server-Side** (PHP):
   - Verifica MIME type con `finfo_file()` → deve essere `application/pdf`
   - Verifica estensione con `pathinfo()` → deve essere `.pdf`
   - Verifica dimensione → max 10 MB (10 * 1024 * 1024 bytes)
   - Blocco upload se uno qualsiasi dei controlli fallisce

3. **Storage Sicuro**:
   - Directory: `wp-content/uploads/allegati_richieste_documenti/`
   - File `.htaccess` che disabilita esecuzione PHP
   - Directory listing disabilitato
   - Permessi file: 0644 (read-only per web server)

4. **Naming Sicuro**:
   - Pattern: `{userID}_{timestamp}_{nome_sanitizzato}.pdf`
   - Sanitizzazione rimuove: caratteri speciali, dots (per evitare double extension), spazi
   - Limit lunghezza nome a 50 caratteri
   - Rimozione underscore/dash iniziali
   - Esempio: `123_1699012345_documento_richiesta.pdf`

### Protezione SQL Injection
- Uso di `$wpdb->prepare()` per tutte le query
- Uso di `$wpdb->replace()` con format array
- Sanitizzazione input con `sanitize_text_field()` e `sanitize_textarea_field()`
- Validazione `intval()` per tutti gli ID numerici

### Protezione XSS
- Uso di `htmlspecialchars()` per output dati utente
- Uso di `esc_attr()` per attributi HTML
- Uso di `esc_url()` per URL
- Sanitizzazione input prima del salvataggio

---

## Testing e Verifica

### Test Effettuati
✅ Syntax check PHP di tutti i file (php -l)
✅ Validazione logica RCT-RCO per tipo fornitore
✅ Consolidamento email per trigger day
✅ Sicurezza filename upload
✅ Path assoluto logging

### Come Testare il Sistema

#### Test BO Panel:
1. Attiva/disattiva manualmente un fornitore
2. Invia richiesta documenti con/senza allegati
3. Verifica email nel log: `log_mail/log_mail_albo_fornitori.txt`
4. Verifica allegati caricati in: `wp-content/uploads/allegati_richieste_documenti/`

#### Test Cron (modalità DEBUG):
1. Modifica `$debug_mode = true` nel cron
2. Esegui: `php cron/cron_controllo_scadenze_fornitori.php`
3. Verifica output console
4. Verifica log in `log_mail/log_mail_albo_fornitori.txt`
5. Verifica che email siano loggate come "SIMULATE"
6. Verifica disattivazioni in database

#### Test Cron (modalità PRODUZIONE):
1. Modifica `$debug_mode = false`
2. Esegui cron
3. Verifica email realmente inviate
4. Verifica log come "INVIATA CON SUCCESSO"

---

## Configurazione Deployment

### 1. Configurare Crontab sul Server

```bash
# Aprire crontab
crontab -e

# Aggiungere la riga (eseguire alle 6:00 AM ogni giorno)
0 6 * * * /usr/bin/php /var/www/html/cogei/cron/cron_controllo_scadenze_fornitori.php >> /var/log/cron_fornitori.log 2>&1
```

### 2. Verificare Permessi

```bash
# Directory log_mail
chmod 755 log_mail/
chmod 644 log_mail/*.txt

# Directory allegati
chmod 755 wp-content/uploads/allegati_richieste_documenti/
chmod 644 wp-content/uploads/allegati_richieste_documenti/.htaccess

# File cron
chmod 755 cron/cron_controllo_scadenze_fornitori.php
```

### 3. Configurare Email BO

Modificare nel file "BO ALBO FORNITORI":
```php
$inviamail = true; // Email ATTIVATE
```

### 4. Configurare Email Cron

Modificare in `cron/cron_controllo_scadenze_fornitori.php`:
```php
$debug_mode = false; // PRODUZIONE (invia email reali)
$admin_email = 'ufficio_qualita@cogei.net'; // Email admin
```

---

## Supporto e Manutenzione

### File Modificati
- `BO ALBO FORNITORI` - File principale backoffice

### File Creati
- `includes/log_mail.php` - Utility logging centralizzato
- `cron/cron_controllo_scadenze_fornitori.php` - Cron scadenze
- `log_mail/.gitkeep` e `log_mail/README.md` - Directory log
- `wp-content/uploads/allegati_richieste_documenti/.htaccess` e `README.md` - Directory allegati
- `RIEPILOGO_EMAIL_AZIONI.md` - Documentazione email
- `SUMMARY_PR.md` - Questo file

### Manutenzione Ordinaria
- Pulire periodicamente vecchi allegati PDF
- Archiviare log quando diventano troppo grandi
- Monitorare log errori cron
- Verificare che le email vengano inviate correttamente

### In Caso di Problemi
1. Verificare log: `log_mail/log_mail_albo_fornitori.txt`
2. Verificare output cron: `/var/log/cron_fornitori.log`
3. Verificare permessi directory
4. Testare con `$debug_mode = true`
5. Verificare configurazione mail server

---

## Conclusione

La PR è **COMPLETA** e pronta per il merge. Tutte le funzionalità richieste sono state implementate:

✅ Email attivate nel pannello BO
✅ Logging centralizzato per tutte le email
✅ UI migliorata (righe rosse per Disattivo)
✅ Upload PDF con validazione e sicurezza
✅ Cron refactor con trigger [15,5,0,-15]
✅ Documentazione completa
✅ Code review completata
✅ Test di sintassi superati

Il sistema è pronto per essere utilizzato in produzione.

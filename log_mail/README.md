# Log Email Albo Fornitori

Questa cartella contiene i log centralizzati per tutte le email inviate dal sistema di gestione Albo Fornitori.

## File di Log

- **log_mail_albo_fornitori.txt** - Log principale contenente tutte le email inviate dal pannello BO ALBO FORNITORI e dal cron di controllo scadenze.

## Formato Log

Ogni voce di log contiene:
- **Timestamp** - Data e ora dell'invio
- **Ambiente** - DEBUG o PROD
- **Tipo Email** - Tipo di notifica (attivazione, disattivazione, richiesta documenti, scadenza, ecc.)
- **Destinatario** - Email del destinatario
- **Oggetto** - Oggetto dell'email
- **Utente** - ID, ragione sociale ed email dell'utente fornitore
- **Documenti** - Documenti coinvolti nella notifica (se applicabile)
- **Allegati** - File allegati (se presenti)
- **Stato Invio** - Successo o fallimento

## Modalità DEBUG

Quando il sistema è in modalità DEBUG:
- Le email NON vengono inviate realmente
- Tutte le email vengono registrate nel log come "SIMULATE"
- Utile per test e sviluppo senza inviare email reali

## Lettura Log

Per visualizzare gli ultimi log:
```bash
tail -n 50 log_mail/log_mail_albo_fornitori.txt
```

Per cercare email specifiche:
```bash
grep "user@example.com" log_mail/log_mail_albo_fornitori.txt
```

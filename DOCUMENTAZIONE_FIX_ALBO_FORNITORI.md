# Documentazione Fix BO Albo Fornitori

## Panoramica delle Modifiche

Questo documento descrive le modifiche implementate nel sistema BO Albo Fornitori per migliorare la gestione delle notifiche email e la gestione degli utenti in fase di registrazione.

## 1. Tracking delle Modifiche Documenti (Anti-Duplicazione Email)

### Problema Risolto
In precedenza, ogni volta che l'amministratore visualizzava il pannello BO, il sistema inviava email duplicate all'admin per le stesse modifiche ai documenti già notificate.

### Soluzione Implementata
- **Usermeta `last_notified_document_changes`**: Memorizza un hash MD5 delle modifiche ai documenti già notificate
- **Verifica prima dell'invio**: Il sistema confronta le modifiche correnti con quelle già notificate
- **No email duplicate**: Se l'hash coincide, nessuna email viene inviata

### Codice Rilevante
```php
// In checkDocumentChangesAndDisableUser()
$last_notified_changes = get_user_meta($user_id, 'last_notified_document_changes', true);
$current_changes_hash = md5(json_encode($changed_documents));

// Se le modifiche sono identiche all'ultima notifica, non inviare email
if ($last_notified_changes === $current_changes_hash) {
    return $changed_documents; // Modifiche già notificate
}

// ... dopo l'invio dell'email ...
update_user_meta($user_id, 'last_notified_document_changes', $current_changes_hash);
```

### Benefici
- ✅ Elimina email duplicate all'admin
- ✅ Riduce spam e confusione
- ✅ Mantiene traccia storica delle notifiche inviate

---

## 2. Gestione Utenti Nuovi in Registrazione

### Problema Risolto
Gli utenti appena registrati che caricavano documenti incompleti venivano automaticamente disattivati e ricevevano notifiche di autodisattivazione, anche se stavano semplicemente completando la registrazione.

### Soluzione Implementata

#### 2.1 Rilevamento Utenti Nuovi
- **Criterio**: Utenti registrati da meno di 7 giorni
- **Logica**: `$days_since_registration <= 7`

#### 2.2 Comportamenti Differenziati

##### Utente Nuovo con Documenti Incompleti
- ❌ **NON viene disattivato**
- 📧 **Email diversa all'admin**: "NUOVO UTENTE: Caricamento documenti in corso"
- 📊 **Include progresso**: Mostra percentuale completamento (es. 3/5 documenti = 60%)

##### Utente Nuovo con Documenti Completi
- ✅ **Resta attivo** (o può essere attivato manualmente)
- 📧 **Email di completamento all'admin**: "NUOVO UTENTE: Documenti completati - Pronto per la revisione"
- 🔍 **Richiede verifica manuale** prima dell'attivazione

##### Utente Esistente con Modifiche
- ⚠️ **Comportamento originale mantenuto**
- 🔴 **Disattivazione automatica**
- 📧 **Email standard di disattivazione**

### Nuove Funzioni Email

#### `sendAdminNewUserProgressNotification()`
Invia notifica quando un nuovo utente inizia a caricare documenti:
- Badge progresso visuale
- Percentuale completamento
- Numero documenti caricati/totali
- Stile colore azzurro (#17a2b8)

#### `sendAdminNewUserCompletionNotification()`
Invia notifica quando un nuovo utente completa tutti i documenti:
- Badge 100% completamento
- Indicazione "Pronto per revisione"
- Call-to-action per verifica manuale
- Stile colore verde (#28a745)

### Codice Rilevante
```php
// Verifica se utente è nuovo
$registration_time = strtotime($user->user_registered);
$days_since_registration = (time() - $registration_time) / (60 * 60 * 24);
$is_new_user = $days_since_registration <= 7;

// Logica differenziata
if ($is_new_user && $filled_documents < $total_required) {
    // Nuovo utente incompleto: solo notifica progresso
    sendAdminNewUserProgressNotification($user_id, $filled_documents, $total_required, $rag_soc);
} elseif ($is_new_user && $filled_documents === $total_required) {
    // Nuovo utente completo: notifica completamento
    sendAdminNewUserCompletionNotification($user_id, $rag_soc);
} else {
    // Utente esistente: comportamento originale (disattivazione)
    update_user_meta($user_id, 'forced_supplier_status', 'Disattivo');
    sendAdminDocumentChangeNotification($user_id, $changed_documents, $rag_soc);
}
```

---

## 3. Nuovo Tab "Utenti in Registrazione"

### Descrizione
Nuova sezione del pannello BO che mostra tutti gli utenti in fase di completamento registrazione.

### Accesso
- **Posizione**: Secondo tab nel pannello BO
- **Icona**: 📝 Utenti in Registrazione
- **URL**: `?tab=registrazione`

### Funzionalità

#### 3.1 Visualizzazione Utenti
- **Lista ordinata**: Utenti pronti per verifica prima, poi per percentuale completamento
- **Card per utente** con dettagli completi

#### 3.2 Informazioni per Utente
Ogni card mostra:
- 🏢 **Nome azienda** e ID utente
- 📧 **Email**
- 📋 **Tipo fornitore** (Lavoro, Servizi, Forniture, etc.)
- 📅 **Data registrazione** e giorni dalla registrazione
- 📊 **Barra progresso** visuale con percentuale
- ✅ **Documenti completati** (lista con date scadenza)
- ❌ **Documenti mancanti** (lista evidenziata)

#### 3.3 Stati Visuali

##### Utente Pronto per Verifica
- 🟢 **Bordo e header verdi**
- ✅ **Badge**: "PRONTO PER VERIFICA"
- 💯 **Progresso**: 100%
- 📍 **Posizione**: Top della lista

##### Utente in Completamento
- 🟡 **Bordo e header gialli**
- ⏳ **Badge**: "IN COMPLETAMENTO"
- 📊 **Progresso**: < 100%
- 📍 **Posizione**: Ordinati per percentuale

#### 3.4 Statistiche
Banner informativo mostra:
- Numero totale utenti in registrazione
- Numero utenti pronti per verifica (se presenti)

### Funzione Backend

#### `getAllUsersInRegistrationPhase()`
```php
// Criteri di inclusione:
// 1. Utenti con documenti incompleti
// 2. Utenti nuovi (< 7 giorni) che hanno iniziato il caricamento

// Restituisce array con:
return [
    'user_id' => int,
    'rag_soc' => string,
    'email' => string,
    'tipo' => string,
    'registration_date' => string,
    'days_since_registration' => float,
    'is_new_user' => bool,
    'completed_docs' => array,      // Documenti caricati
    'missing_docs' => array,        // Documenti mancanti
    'filled_count' => int,
    'total_required' => int,
    'percentage' => int,            // 0-100
    'is_ready_for_review' => bool   // true se 100%
];
```

---

## 4. Logging delle Nuove Email

### Tipi di Email nel Log

#### `NOTIFICA_ADMIN_PROGRESSO_NUOVO_UTENTE`
- **Colore**: Azzurro (#d1ecf1)
- **Quando**: Utente nuovo carica documenti (non completi)
- **Include**: Percentuale progresso

#### `NOTIFICA_ADMIN_COMPLETAMENTO_NUOVO_UTENTE`
- **Colore**: Verde (#d4edda)
- **Quando**: Utente nuovo completa tutti i documenti
- **Include**: Indicazione "Pronto per attivazione"

### Log Struttura
```php
AlboFornitoriMailLogger::log([
    'ambiente' => 'PROD',
    'tipo_email' => 'NOTIFICA_ADMIN_PROGRESSO_NUOVO_UTENTE',
    'destinatario' => 'ufficio_qualita@cogei.net',
    'oggetto' => 'NUOVO UTENTE: Caricamento documenti in corso',
    'user_id' => $user_id,
    'user_name' => $rag_soc,
    'user_email' => $user_email,
    'email_sent' => true/false,
    'note' => "Progresso: 3/5 documenti (60%)"
]);
```

---

## 5. Compatibilità con Sistemi Esistenti

### Email Logger
- ✅ Utilizza `AlboFornitoriMailLogger` esistente
- ✅ Nuovi tipi di email integrati
- ✅ Mantiene tracciabilità completa

### Sistema Disattivazione
- ✅ Comportamento originale per utenti esistenti
- ✅ Cron scadenze non modificato
- ✅ Log disattivazioni automatiche preservato

### Database
- ✅ Usa tabella `suppliers_copy` esistente
- ✅ Aggiunge solo usermeta `last_notified_document_changes`
- ✅ Non richiede migrazione dati

---

## 6. Workflow Completo

### Caso 1: Nuovo Utente si Registra

```
1. Utente si registra → Solo_Registrato
2. Carica primo documento → Email admin "Progresso in corso" (20%)
3. Carica secondo documento → Email admin "Progresso in corso" (40%)
4. Carica tutti documenti → Email admin "Completamento - Pronto per revisione" (100%)
5. Admin verifica nel tab "Utenti in Registrazione"
6. Admin attiva manualmente → Email attivazione a utente
```

### Caso 2: Utente Esistente Modifica Documenti

```
1. Utente modifica data documento
2. Sistema rileva modifica
3. Verifica hash notifica precedente
4. Se diverso:
   - Disattiva utente
   - Invia email admin "Disattivazione automatica"
   - Salva nuovo hash
5. Se identico:
   - Nessuna azione (già notificato)
```

### Caso 3: Admin Visualizza Pannello

```
1. Admin carica pagina BO
2. Sistema controlla tutti utenti
3. Per ogni utente:
   - Verifica stato documenti
   - Confronta con hash notifica
   - Invia email SOLO se cambiate
4. No email duplicate!
```

---

## 7. Configurazione

### Parametri Modificabili

#### Giorni Finestra Nuovo Utente
```php
// In checkDocumentChangesAndDisableUser()
$is_new_user = $days_since_registration <= 7; // Cambia 7 per modificare finestra
```

#### Email Destinatario
```php
// Nelle funzioni send*Notification()
$admin_email = 'ufficio_qualita@cogei.net'; // Modifica destinatario
```

#### Attivazione/Disattivazione Email
```php
// In cima al file BO ALBO FORNITORI
$GLOBALS['inviamail'] = true; // false per modalità DEBUG
```

---

## 8. Testing e Validazione

### Test Scenario 1: No Email Duplicate
1. Utente modifica documento
2. Admin visualizza pannello → Email inviata
3. Admin ricarica pannello → NO email
4. Utente modifica altro documento
5. Admin visualizza pannello → Email inviata (nuova modifica)

### Test Scenario 2: Nuovo Utente
1. Crea nuovo utente test
2. Carica 2 documenti su 5 → Verifica email progresso
3. Carica restanti 3 documenti → Verifica email completamento
4. Verifica tab "Utenti in Registrazione" → Utente presente con 100%

### Test Scenario 3: Tab Registrazione
1. Crea 3 utenti:
   - Utente A: 2/5 documenti
   - Utente B: 5/5 documenti
   - Utente C: 4/5 documenti
2. Vai al tab "Utenti in Registrazione"
3. Verifica ordine: B (100%) → C (80%) → A (40%)
4. Verifica badge: B = "PRONTO PER VERIFICA", altri = "IN COMPLETAMENTO"

---

## 9. Troubleshooting

### Email Non Inviate
**Problema**: Le email non vengono inviate
**Soluzione**: Verifica `$GLOBALS['inviamail'] = true`

### Email Duplicate Ancora Presenti
**Problema**: Ricevi email duplicate
**Soluzione**: 
1. Verifica che `last_notified_document_changes` sia salvato
2. Controlla il log per vedere se hash viene aggiornato

### Tab Registrazione Vuoto
**Problema**: Nessun utente mostrato
**Soluzione**:
1. Verifica che esistano utenti con documenti incompleti
2. Controlla che `getAllUsersInRegistrationPhase()` restituisca dati

### Utenti Nuovi Disattivati
**Problema**: Utenti nuovi vengono ancora disattivati
**Soluzione**:
1. Verifica calcolo `$days_since_registration`
2. Assicurati che `$is_new_user` sia true
3. Controlla che `$filled_documents < $total_required`

---

## 10. Manutenzione Futura

### Estensione Finestra Nuovo Utente
Se serve più tempo per la registrazione:
```php
$is_new_user = $days_since_registration <= 14; // Estendi a 14 giorni
```

### Cambio Destinatario Email
Per inviare a multipli destinatari:
```php
$admin_email = 'ufficio_qualita@cogei.net, admin@cogei.net';
```

### Aggiunta Nuovi Documenti Obbligatori
1. Aggiungi campo in `$document_fields` array
2. Aggiorna `getRequiredDocuments()` se necessario
3. Aggiorna `getAllUsersInRegistrationPhase()` con stesso campo

---

## 11. File Modificati

### File Principali
- **BO ALBO FORNITORI**: File principale con tutte le modifiche
  - Funzione `checkDocumentChangesAndDisableUser()`: Logica anti-duplicazione e gestione nuovi utenti
  - Funzione `sendAdminNewUserProgressNotification()`: Nuova email progresso
  - Funzione `sendAdminNewUserCompletionNotification()`: Nuova email completamento
  - Funzione `getAllUsersInRegistrationPhase()`: Backend nuovo tab
  - HTML nuovo tab: Interfaccia visuale tab registrazione
  - JavaScript `switchTab()`: Gestione nuovo tab

### File Non Modificati (Compatibilità)
- ✅ **includes/log_mail.php**: Usa funzioni esistenti
- ✅ **cron/cron_controllo_scadenze_fornitori.php**: Non toccato
- ✅ **Database structure**: Nessuna migrazione necessaria

---

## 12. Checklist Implementazione

- [x] Req 1: Sistema tracking anti-duplicazione email
- [x] Req 2: Gestione differenziata utenti nuovi
  - [x] Rilevamento utenti nuovi
  - [x] Email progresso caricamento
  - [x] Email completamento documenti
  - [x] No disattivazione automatica per nuovi utenti
- [x] Req 3: Nuovo tab "Utenti in Registrazione"
  - [x] Backend: `getAllUsersInRegistrationPhase()`
  - [x] Frontend: HTML card utenti
  - [x] UI: Barre progresso e stati visuali
  - [x] Navigation: Integrazione tab switching
- [x] Req 4: Documentazione interna completa

---

## Contatti e Supporto

Per domande o problemi relativi a queste modifiche:
- **Sistema**: BO Albo Fornitori
- **Versione**: Post-Fix 2024
- **Email**: ufficio_qualita@cogei.net

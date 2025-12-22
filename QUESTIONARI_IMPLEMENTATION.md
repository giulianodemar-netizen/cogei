# Implementazione Sistema Questionari Fornitori

## 📋 Descrizione

Il file `bo-questionnaires.php` è un sistema completo e autonomo per la gestione dei questionari destinati ai fornitori HSE. Segue le stesse convenzioni e pattern utilizzati in BO HSE, BO ALBO FORNITORI e FRONT HSE.

## 🎯 Funzionalità Principali

### 1. Gestione Questionari
- **Creazione** questionari con titolo, descrizione e stato (bozza/pubblicato)
- **Modifica** questionari esistenti
- **Eliminazione** questionari (con cascade su dati correlati)

### 2. Struttura Gerarchica
- **Questionari** → **Aree Tematiche** → **Domande** → **Opzioni di Risposta**
- Ogni livello supporta ordinamento personalizzabile
- Pesi configurabili per aree e opzioni

### 3. Invio e Raccolta
- **Invio via email** con link personalizzato e token univoco
- **Form pubblico** per la compilazione da parte dei fornitori
- **Tracciamento stato**: pending, completed, expired

### 4. Calcolo Punteggi
- **Formula**: `punteggio_domanda = peso_opzione × peso_area`
- **Punteggio finale**: normalizzato 0-1
- **Valutazioni automatiche** secondo soglie predefinite

### 5. Reporting
- **Visualizzazione risultati** dettagliata
- **Export CSV** di tutti gli invii e risultati
- **Dashboard** con statistiche

## 🗄️ Tabelle Database

Il sistema crea automaticamente 6 tabelle con prefisso `wp_` (configurabile):

### cogei_questionnaires
```sql
- id (PK)
- title
- description
- created_by
- status (draft/published)
- created_at
- updated_at
```

### cogei_areas
```sql
- id (PK)
- questionnaire_id (FK)
- title
- weight (decimal)
- sort_order
- created_at
- updated_at
```

### cogei_questions
```sql
- id (PK)
- area_id (FK)
- text
- is_required (boolean)
- sort_order
- created_at
- updated_at
```

### cogei_options
```sql
- id (PK)
- question_id (FK)
- text
- weight (decimal)
- sort_order
```

### cogei_assignments
```sql
- id (PK)
- questionnaire_id (FK)
- target_user_id (nullable)
- target_email
- sent_by
- sent_at
- status (pending/completed/expired)
- token (unique)
```

### cogei_responses
```sql
- id (PK)
- assignment_id (FK)
- question_id (FK)
- selected_option_id (FK)
- computed_score (decimal)
- answered_at
```

## 📊 Soglie di Valutazione

Il sistema utilizza le seguenti soglie per la valutazione automatica:

| Punteggio | Valutazione |
|-----------|-------------|
| ≥ 0.85 | **Eccellente** |
| ≥ 0.70 | **Molto Buono** |
| ≥ 0.55 | **Adeguato** |
| ≥ 0.40 | **Critico** |
| < 0.40 | **Inadeguato** |

## 🔧 Integrazione WordPress

### Metodo 1: Inclusione Diretta
```php
// Nel template WordPress
require_once(get_template_directory() . '/bo-questionnaires.php');
```

### Metodo 2: Plugin
```php
// Creare un plugin wrapper
/*
Plugin Name: Cogei Questionari
Description: Sistema gestione questionari fornitori
Version: 1.0
*/
require_once(plugin_dir_path(__FILE__) . 'bo-questionnaires.php');
```

### Metodo 3: functions.php
```php
// In functions.php del tema
if (is_admin() || isset($_GET['boq_token'])) {
    require_once(get_template_directory() . '/bo-questionnaires.php');
}
```

## 🚀 Utilizzo

### 1. Creazione Questionario

1. Accedere all'interfaccia admin
2. Tab "Questionari" → "Crea Nuovo Questionario"
3. Compilare titolo, descrizione e stato
4. Salvare

### 2. Struttura Questionario

1. Dopo aver creato il questionario, cliccare "Modifica"
2. **Aggiungere Aree**:
   - Titolo area (es. "Sicurezza sul Lavoro")
   - Peso area (es. 1.5 per aree più importanti)
   - Ordine di visualizzazione
3. **Aggiungere Domande** all'area:
   - Testo domanda
   - Flag "Obbligatoria"
   - Ordine
4. **Aggiungere Opzioni** alla domanda:
   - Testo opzione (es. "Sì", "No", "Parzialmente")
   - Peso opzione (es. 1.00 per "Sì", 0.50 per "Parzialmente", 0.00 per "No")
   - Ordine

### 3. Invio Questionario

1. Tab "Invii" → cliccare "📤 Invia" sul questionario desiderato
2. Selezionare:
   - **Utente HSE** dalla lista (opzionale)
   - Oppure inserire **email manualmente**
3. Il sistema:
   - Genera token univoco
   - Invia email con link personalizzato
   - Crea record in `cogei_assignments`

### 4. Compilazione (Fornitore)

1. Il fornitore riceve email con link + token
2. Clicca sul link e accede al form pubblico
3. Risponde alle domande (obbligatorie contrassegnate con *)
4. Invia le risposte
5. Visualizza il punteggio finale e la valutazione

### 5. Visualizzazione Risultati

1. Tab "Risultati" → lista tutti i questionari completati
2. Cliccare "📊 Dettagli" per vedere:
   - Punteggio finale
   - Valutazione
   - Risposte dettagliate per ogni domanda
   - Punteggio parziale per ogni risposta

### 6. Export CSV

1. Cliccare "📥 Esporta CSV" in alto a destra
2. Download file CSV con tutti gli invii e risultati
3. Colonne: ID, Questionario, Destinatario, Email, Data Invio, Stato, Punteggio, Valutazione

## 🔒 Sicurezza

- **Nonce verification** su tutti i form admin
- **Token univoci** per ogni assignment
- **Sanitizzazione** di tutti gli input utente
- **Prepared statements** per query database
- **Escaping** di tutti gli output HTML
- **Email validation** per indirizzi email

## 📧 Email Template

Le email seguono lo stesso template delle altre funzionalità Cogei:
- Header con logo aziendale
- Messaggio personalizzato
- Link CTA con token
- Footer con contatti aziendali

## 🎨 Interfaccia

L'interfaccia admin utilizza:
- **Colore primario**: #03679e (Cogei blue)
- **Tab navigation** per separare funzionalità
- **Tabelle responsive** per liste
- **Form inline** per gestione rapida
- **Icone emoji** per migliore UX
- **Colori semantici** per stati (verde=completato, arancione=pending, rosso=critico)

## 🔄 Workflow Completo

```
1. Admin crea questionario
   ↓
2. Admin definisce aree, domande, opzioni
   ↓
3. Admin pubblica questionario (status = published)
   ↓
4. Admin invia questionario a fornitore
   ↓
5. Sistema genera token e invia email
   ↓
6. Fornitore riceve email e clicca link
   ↓
7. Fornitore compila questionario
   ↓
8. Sistema salva risposte e calcola punteggio
   ↓
9. Sistema mostra valutazione a fornitore
   ↓
10. Admin visualizza risultati in dashboard
```

## 📝 Note Tecniche

### Pattern Utilizzati
- Segue convenzioni di **BO HSE** e **BO ALBO FORNITORI**
- Usa `wpdb` per operazioni database
- Usa `wp_mail` per invio email
- Usa `wp_nonce_field` e `wp_verify_nonce` per sicurezza
- Usa funzioni WordPress native (`esc_html`, `esc_attr`, `sanitize_text_field`, ecc.)

### Configurazione Email
```php
$inviamail = true; // Riga 52 - Email ATTIVATE
```

### Timezone
```php
date_default_timezone_set('Europe/Rome'); // Riga 49
```

### Compatibilità
- WordPress 5.0+
- PHP 7.2+
- MySQL 5.6+

## 🧪 Testing

### Test Manuale Consigliato

1. **Test Creazione**:
   - Creare questionario
   - Aggiungere 2-3 aree con pesi diversi
   - Aggiungere 3-4 domande per area
   - Aggiungere 3-5 opzioni per domanda con pesi 0.00-1.00

2. **Test Invio**:
   - Inviare a email di test
   - Verificare ricezione email
   - Verificare link con token

3. **Test Compilazione**:
   - Aprire link da email
   - Compilare tutte le domande
   - Verificare validazione domande obbligatorie
   - Inviare risposte
   - Verificare calcolo punteggio

4. **Test Visualizzazione**:
   - Verificare dashboard risultati
   - Verificare dettaglio singolo risultato
   - Verificare export CSV

5. **Test Sicurezza**:
   - Tentare accesso con token invalido
   - Tentare doppia compilazione stesso questionario
   - Verificare protezione nonce

## 🐛 Troubleshooting

### Email non arrivano
- Verificare `$inviamail = true` (riga 52)
- Controllare configurazione SMTP WordPress
- Verificare spam folder
- Controllare log mail (se abilitato)

### Tabelle non create
- Verificare permessi database utente WordPress
- Controllare log PHP errors
- Eseguire manualmente query CREATE TABLE

### Errori calcolo punteggio
- Verificare che tutte le opzioni abbiano peso impostato
- Verificare che tutte le aree abbiano peso impostato
- Controllare che tutte le domande abbiano almeno una opzione

### Token non funziona
- Verificare che token sia univoco in database
- Controllare che assignment non sia già completato
- Verificare URL completo con parametro `?boq_token=...`

## 📚 Riferimenti Codice

### Funzioni Principali

| Funzione | Descrizione |
|----------|-------------|
| `boq_createQuestionnaireTablesIfNotExists()` | Crea tabelle database |
| `boq_generateToken()` | Genera token univoco |
| `boq_getQuestionnaire($id)` | Recupera questionario |
| `boq_getAreas($questionnaire_id)` | Recupera aree |
| `boq_getQuestions($area_id)` | Recupera domande |
| `boq_getOptions($question_id)` | Recupera opzioni |
| `boq_calculateScore($assignment_id)` | Calcola punteggio |
| `boq_evaluateScore($score)` | Valuta punteggio |
| `boq_sendQuestionnaireEmail($assignment_id)` | Invia email |
| `boq_renderAdminInterface()` | Render interfaccia admin |

## 🔗 Link Utili

- **Documentazione BO HSE**: Vedere file `BO HSE`
- **Documentazione BO ALBO FORNITORI**: Vedere file `BO ALBO FORNITORI`
- **Documentazione FRONT HSE**: Vedere file `FRONT HSE`

## 📄 Licenza

Copyright © 2023 Cogei S.r.l. - P.IVA: IT06569020636

---

**Versione**: 1.0  
**Data**: Dicembre 2024  
**Autore**: Cogei System  
**File**: bo-questionnaires.php (1570 righe)

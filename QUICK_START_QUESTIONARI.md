# 🎯 Quick Start: Sistema Questionari Fornitori

## Installazione Rapida

### Step 1: Integrazione in WordPress

Scegli una delle seguenti opzioni:

#### Opzione A: Template Personalizzato
```php
<?php
/* Template Name: Gestione Questionari */
get_header();
require_once(get_template_directory() . '/bo-questionnaires.php');
get_footer();
?>
```

#### Opzione B: Plugin Wrapper
```php
<?php
/*
Plugin Name: Cogei Questionari Fornitori
Description: Sistema completo gestione questionari valutazione fornitori
Version: 1.0
Author: Cogei System
*/

require_once(plugin_dir_path(__FILE__) . 'bo-questionnaires.php');
```

#### Opzione C: Functions.php (Condizionale)
```php
add_action('init', function() {
    // Carica solo per admin o accesso pubblico con token
    if (is_admin() || isset($_GET['boq_token'])) {
        require_once(get_template_directory() . '/bo-questionnaires.php');
    }
});
```

### Step 2: Verifica Installazione

1. Accedi al pannello WordPress
2. Carica la pagina dove hai integrato il file
3. Dovresti vedere l'interfaccia admin con 3 tab
4. Verifica in phpMyAdmin che siano state create 6 nuove tabelle con prefisso `cogei_`

## 📋 Primo Questionario (Tutorial 5 minuti)

### 1. Crea Questionario Base
```
Tab: Questionari → Crea Nuovo Questionario

Titolo: "Valutazione Sicurezza Fornitore 2024"
Descrizione: "Questionario obbligatorio per tutti i fornitori che operano nei nostri cantieri"
Stato: "Pubblicato"

[Salva Questionario]
```

### 2. Aggiungi Area "Formazione"
```
Nella pagina di modifica del questionario creato:

Titolo Area: "Formazione e Competenze"
Peso: 1.50 (importante)
Ordine: 1

[Aggiungi Area]
```

### 3. Aggiungi Domanda
```
Nell'area "Formazione e Competenze":

Testo: "Il personale ha completato il corso antincendio?"
☑ Obbligatoria
Ordine: 1

[Aggiungi]
```

### 4. Aggiungi Opzioni Risposta
```
Nella domanda appena creata:

Opzione 1:
  Testo: "Sì, tutto il personale"
  Peso: 1.00
  Ordine: 1

Opzione 2:
  Testo: "Solo parzialmente"
  Peso: 0.50
  Ordine: 2

Opzione 3:
  Testo: "No"
  Peso: 0.00
  Ordine: 3
```

### 5. Invia a Fornitore
```
Tab: Invii → [📤 Invia] sul questionario creato

Seleziona Utente HSE: [Scegli dalla lista]
Oppure
Email Manuale: fornitore@example.com

[📤 Invia Questionario]
```

### 6. Il Fornitore Riceve e Compila
```
1. Email ricevuta con link personalizzato
2. Click sul link → Si apre il form
3. Compila le risposte
4. [Invia Risposte]
5. Visualizza immediatamente il punteggio e valutazione
```

### 7. Visualizza Risultati
```
Tab: Risultati

Vedi tutti i questionari completati con:
- Punteggio (0.00 - 1.00)
- Valutazione (Eccellente, Molto Buono, Adeguato, Critico, Inadeguato)
- Data compilazione

Click [📊 Dettagli] per vedere:
- Risposta a ogni domanda
- Punteggio parziale per area
- Breakdown completo
```

## 🎨 Esempio Completo: Questionario HSE

### Struttura Raccomandata

```
📋 QUESTIONARIO: "Valutazione Completa HSE Fornitore 2024"

📊 AREA 1: Formazione (Peso: 1.50)
  ❓ Corso antincendio completato?
    ✓ Sì, tutto il personale (1.00)
    ✓ Solo parzialmente (0.50)
    ✓ No (0.00)
  
  ❓ Corso primo soccorso completato?
    ✓ Sì, tutto il personale (1.00)
    ✓ Solo parzialmente (0.50)
    ✓ No (0.00)

📊 AREA 2: Documentazione (Peso: 1.00)
  ❓ DVR aggiornato?
    ✓ Sì, aggiornato (1.00)
    ✓ Parzialmente (0.60)
    ✓ No o scaduto (0.00)
  
  ❓ Certificazioni ISO?
    ✓ ISO 9001 + 14001 + 45001 (1.00)
    ✓ Solo alcune (0.50)
    ✓ Nessuna (0.00)

📊 AREA 3: Sicurezza Operativa (Peso: 2.00)
  ❓ DPI forniti al personale?
    ✓ Sì, completi (1.00)
    ✓ Parziali (0.30)
    ✓ No (0.00)
  
  ❓ Procedure emergenza definite?
    ✓ Sì, complete e condivise (1.00)
    ✓ Parzialmente (0.50)
    ✓ No (0.00)
```

### Calcolo Punteggio Esempio

Se il fornitore risponde:
- Area 1, Domanda 1: "Sì" (1.00 × 1.50 = **1.50**)
- Area 1, Domanda 2: "Parzialmente" (0.50 × 1.50 = **0.75**)
- Area 2, Domanda 1: "Sì" (1.00 × 1.00 = **1.00**)
- Area 2, Domanda 2: "Alcune" (0.50 × 1.00 = **0.50**)
- Area 3, Domanda 1: "Sì" (1.00 × 2.00 = **2.00**)
- Area 3, Domanda 2: "Sì" (1.00 × 2.00 = **2.00**)

**Punteggio Totale**: (1.50 + 0.75 + 1.00 + 0.50 + 2.00 + 2.00) / 6 = **1.29** / 6 = **0.78**

**Valutazione**: **Molto Buono** (≥ 0.70)

## 🔧 Configurazione Avanzata

### Email ON/OFF
```php
// Riga 49 in bo-questionnaires.php
$inviamail = true;  // true = invia, false = simula
```

### Personalizza Soglie Valutazione
```php
// Riga 298-309 in bo-questionnaires.php
function boq_evaluateScore($score) {
    if ($score >= 0.85) return 'Eccellente';
    if ($score >= 0.70) return 'Molto Buono';
    if ($score >= 0.55) return 'Adeguato';
    if ($score >= 0.40) return 'Critico';
    return 'Inadeguato';
}
```

### Cambia Colori Valutazione
```php
// Riga 1428-1434 in bo-questionnaires.php
$eval_colors = [
    'Eccellente' => '#4caf50',    // Verde scuro
    'Molto Buono' => '#8bc34a',   // Verde chiaro
    'Adeguato' => '#ff9800',      // Arancione
    'Critico' => '#ff5722',       // Rosso chiaro
    'Inadeguato' => '#f44336'     // Rosso scuro
];
```

## 📤 Export CSV

### Come Esportare
1. Click su "📥 Esporta CSV" in alto a destra
2. Download automatico file `questionari_YYYY-MM-DD_HH-mm-ss.csv`
3. Apri in Excel o Google Sheets

### Colonne CSV
- ID
- Questionario
- Destinatario (nome utente)
- Email
- Data Invio
- Stato (pending/completed/expired)
- Punteggio (0.0000 - 1.0000)
- Valutazione (Eccellente, Molto Buono, ecc.)

## 🐛 Troubleshooting

### Email non arrivano
```
✓ Verifica $inviamail = true (riga 49)
✓ Controlla configurazione SMTP WordPress
✓ Verifica cartella spam
✓ Test con plugin SMTP (WP Mail SMTP)
```

### Tabelle non create
```
✓ Verifica permessi utente database MySQL
✓ Controlla log PHP errors
✓ Prova a caricare la pagina 2-3 volte
✓ Verifica in phpMyAdmin presenza tabelle cogei_*
```

### Link questionario non funziona
```
✓ Verifica formato URL: ?boq_token=xxxxx
✓ Controlla che token sia valido (64 caratteri)
✓ Verifica status assignment (non deve essere completed)
✓ Controlla che questionario sia published
```

### Punteggio sbagliato
```
✓ Verifica pesi aree (devono essere > 0)
✓ Verifica pesi opzioni (0.00 - 1.00)
✓ Controlla che tutte le domande abbiano opzioni
✓ Verifica che tutte le risposte siano salvate
```

## 📞 Supporto

### Documentazione Completa
- `QUESTIONARI_IMPLEMENTATION.md` - Guida completa
- `PR_SUMMARY_QUESTIONNAIRES.md` - Riepilogo tecnico

### Riferimenti Codice
```php
// Funzioni principali da conoscere
boq_getQuestionnaire($id)          // Recupera questionario
boq_getAreas($questionnaire_id)    // Recupera aree
boq_calculateScore($assignment_id) // Calcola punteggio
boq_evaluateScore($score)          // Valuta punteggio
```

### Database Tables
```sql
SELECT * FROM wp_cogei_questionnaires;  -- Questionari
SELECT * FROM wp_cogei_areas;           -- Aree
SELECT * FROM wp_cogei_questions;       -- Domande
SELECT * FROM wp_cogei_options;         -- Opzioni
SELECT * FROM wp_cogei_assignments;     -- Invii
SELECT * FROM wp_cogei_responses;       -- Risposte
```

## ✅ Checklist Go-Live

Prima di utilizzare in produzione:

- [ ] File `bo-questionnaires.php` caricato
- [ ] Integrazione WordPress funzionante
- [ ] Tabelle database create (6 tabelle)
- [ ] Test creazione questionario
- [ ] Test invio email
- [ ] Test compilazione pubblica
- [ ] Test calcolo punteggio
- [ ] Test export CSV
- [ ] Backup database
- [ ] Email di produzione configurate
- [ ] Documentazione condivisa con team

## 🎯 Best Practices

### 1. Struttura Questionario
- Max 5-6 aree per questionario
- Max 5-7 domande per area
- 3-5 opzioni per domanda
- Usa pesi area per importanza (1.0-2.0)

### 2. Pesi Opzioni
- Risposta migliore: 1.00
- Risposta media: 0.50-0.70
- Risposta peggiore: 0.00
- Mantieni consistenza tra domande simili

### 3. Comunicazione
- Invia reminder dopo 3-5 giorni se non completato
- Ringrazia dopo completamento
- Condividi risultati positivi con fornitore
- Usa risultati per miglioramento continuo

### 4. Manutenzione
- Rivedi questionari ogni 6-12 mesi
- Aggiorna pesi in base a priorità aziendali
- Archivia vecchi questionari (status = draft)
- Export CSV periodico per backup

---

**Versione**: 1.0  
**Ultima Revisione**: Dicembre 2024  
**Branch**: copilot/featurebo-questionnaires  
**Status**: ✅ Production Ready

Per assistenza tecnica, consultare la documentazione completa in `QUESTIONARI_IMPLEMENTATION.md`

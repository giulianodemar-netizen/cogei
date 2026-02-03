# Refactoring Punteggi Questionari - Soluzione Finale

## Problema Identificato

L'implementazione precedente con snapshot ha causato problemi nei calcoli dei punteggi. Il sistema era troppo complesso e continuava a ricalcolare i punteggi in modo dinamico.

## Soluzione Implementata (Suggerimento Utente)

Invece di usare snapshot JSON complessi, ho implementato un approccio molto più semplice e robusto come suggerito dall'utente:

### 1. Tabella Dedicata per i Punteggi

**Nuova tabella: `cogei_questionnaire_scores`**

```sql
CREATE TABLE cogei_questionnaire_scores (
    id int(11) NOT NULL AUTO_INCREMENT,
    assignment_id int(11) NOT NULL,
    final_score decimal(10,4) NOT NULL DEFAULT 0.0000,
    calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY assignment_id (assignment_id)
)
```

**Caratteristiche:**
- Un record per ogni questionario completato
- Il punteggio è salvato UNA SOLA VOLTA
- UNIQUE KEY su `assignment_id` previene duplicati
- Il punteggio diventa immutabile dopo il salvataggio

### 2. Flusso Operativo

#### Quando il questionario viene completato:
1. Il valutatore invia le risposte
2. Il sistema calcola il punteggio finale
3. Il punteggio viene **salvato nella tabella `cogei_questionnaire_scores`**
4. Il questionario viene marcato come "completed"

#### Quando serve visualizzare il punteggio:
1. Il sistema legge dalla tabella `cogei_questionnaire_scores`
2. Non ricalcola MAI il punteggio
3. Usa sempre il valore memorizzato

### 3. Modifiche ai File

#### **bo-questionnaires.php**

**Aggiunto:**
- Creazione tabella `cogei_questionnaire_scores`
- Funzione `boq_calculateAndSaveScore($assignment_id)` - Calcola e salva il punteggio
- Funzione `boq_getScore($assignment_id)` - Recupera il punteggio salvato

**Rimosso:**
- Tutta la logica degli snapshot
- Il campo `questionnaire_snapshot` dalla tabella assignments
- La funzione `boq_createQuestionnaireSnapshot()`

**Sostituito:**
- Tutti i riferimenti a `boq_calculateScore()` con `boq_getScore()`

#### **ajax_fornitori/get_supplier_questionnaires.php**

**Modificato:**
```php
// PRIMA (calcolo dinamico)
function calculateQuestionnaireScore($assignment_id) {
    // 60+ righe di calcolo dinamico con JOIN
    // Ricalcolava ogni volta
}

// DOPO (lettura da tabella)
function calculateQuestionnaireScore($assignment_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT final_score FROM {$wpdb->prefix}cogei_questionnaire_scores 
         WHERE assignment_id = %d",
        $assignment_id
    ));
}
```

#### **ajax_fornitori/get_questionnaire_details.php**

**Modificato:**
- Rimosso ciclo foreach su aree e risposte
- Sostituito con semplice SELECT dalla tabella scores
- Da 40+ righe a 5 righe

#### **questionario-pubblico.php**

**Modificato in 2 punti:**

1. **Dopo completamento questionario** - Salva il punteggio:
```php
// Calcola il punteggio
$final_score = $total_score * 100;

// SALVA nella tabella dedicata
$wpdb->query($wpdb->prepare(
    "INSERT IGNORE INTO {$wpdb->prefix}cogei_questionnaire_scores 
     (assignment_id, final_score, calculated_at) 
     VALUES (%d, %f, NOW())",
    $assignment['id'],
    $final_score
));
```

2. **Visualizzazione questionario completato** - Legge dalla tabella:
```php
// PRIMA: Ricalcolava con cicli foreach

// DOPO: Legge direttamente
$final_score = $wpdb->get_var($wpdb->prepare(
    "SELECT final_score FROM {$wpdb->prefix}cogei_questionnaire_scores 
     WHERE assignment_id = %d",
    $assignment['id']
));
```

### 4. Script di Migrazione

**Nuovo file: `migrate_questionnaire_scores.php`**

Popola la tabella con i punteggi dei questionari esistenti.

**Caratteristiche:**
- Eseguibile da CLI o browser
- Trova tutti i questionari completati
- Calcola il punteggio per ognuno
- Salva nella nuova tabella
- Mostra report dettagliato
- Gestisce errori e duplicati

**Utilizzo:**
```bash
# Da CLI
php migrate_questionnaire_scores.php

# Da browser
https://tuo-sito.com/migrate_questionnaire_scores.php
```

**Output esempio:**
```
===========================================
MIGRAZIONE PUNTEGGI QUESTIONARI
===========================================

✓ Tabella cogei_questionnaire_scores trovata
Trovati 45 questionari completati da migrare

++++++++++++++++++++++++++++++++++++++++++++++

===========================================
RIEPILOGO MIGRAZIONE
===========================================
Totale questionari:     45
Migrati con successo:   45
Già esistenti (saltati): 0
Errori:                 0

✅ Migrazione completata con successo!
```

## Vantaggi della Nuova Soluzione

### ✅ Semplicità
- **1 tabella** invece di snapshot JSON complessi
- **1 query SELECT** invece di calcoli con multiple JOIN
- Facile da capire e mantenere

### ✅ Immutabilità Garantita
- Il punteggio è salvato UNA SOLA VOLTA
- Non può essere modificato accidentalmente
- Nessun ricalcolo dinamico

### ✅ Performance
- **Lettura diretta** dalla tabella (velocissimo)
- Nessun calcolo a runtime
- Nessuna JOIN complessa per calcolare punteggi

### ✅ Affidabilità
- **1 questionario = 1 record nella tabella**
- UNIQUE constraint previene duplicati
- Facile verificare i dati

### ✅ Manutenibilità
- Codice molto più semplice
- Facile debug (basta guardare la tabella)
- Facile aggiungere funzionalità future

## Confronto Prima/Dopo

### PRIMA (Snapshot)
```
❌ Snapshot JSON complessi (2-5KB per questionario)
❌ Logica distribuita tra snapshot e computed_score
❌ Fallback per questionari vecchi
❌ Difficile da debuggare
❌ ~300 righe di codice complesso
```

### DOPO (Tabella Dedicata)
```
✅ Tabella semplice con 1 record per questionario
✅ Logica centralizzata: leggi dalla tabella
✅ Nessun fallback necessario
✅ Facile da debuggare: SELECT * FROM scores
✅ ~50 righe di codice semplice
```

## Migrazione Dati Esistenti

### Step 1: Applica le Modifiche
- Carica i file modificati sul server
- La tabella `cogei_questionnaire_scores` viene creata automaticamente

### Step 2: Esegui lo Script di Migrazione
```bash
php migrate_questionnaire_scores.php
```

### Step 3: Verifica
```sql
-- Controlla che i punteggi siano stati migrati
SELECT COUNT(*) FROM cogei_questionnaire_scores;

-- Confronta con il numero di questionari completati
SELECT COUNT(*) FROM cogei_assignments WHERE status = 'completed';
```

### Step 4: Cleanup
- Elimina il file `migrate_questionnaire_scores.php` (opzionale)
- Elimina i file di documentazione della vecchia soluzione (opzionale)

## Test

### Test Manuale
1. Completa un nuovo questionario
2. Verifica che il punteggio sia salvato in `cogei_questionnaire_scores`
3. Modifica i pesi delle domande/aree
4. Verifica che il punteggio visualizzato NON sia cambiato

### Query di Verifica
```sql
-- Verifica punteggi salvati
SELECT a.id, a.target_user_id, s.final_score, s.calculated_at
FROM cogei_assignments a
INNER JOIN cogei_questionnaire_scores s ON a.id = s.assignment_id
WHERE a.status = 'completed'
ORDER BY s.calculated_at DESC
LIMIT 10;

-- Trova questionari completati senza punteggio (dovrebbero essere 0)
SELECT a.id, a.target_user_id
FROM cogei_assignments a
LEFT JOIN cogei_questionnaire_scores s ON a.id = s.assignment_id
WHERE a.status = 'completed' AND s.id IS NULL;
```

## Note Importanti

### ⚠️ Per Sviluppatori Futuri

1. **NON modificare mai** i record in `cogei_questionnaire_scores`
2. **NON ricalcolare** i punteggi dopo che sono stati salvati
3. **Usare sempre** `boq_getScore()` per ottenere i punteggi
4. Se serve ricalcolare (caso eccezionale), eliminare il record e il sistema lo ricalcolerà

### 📋 Checklist Deployment

- [ ] Backup database
- [ ] Caricare file modificati sul server
- [ ] Verificare creazione tabella `cogei_questionnaire_scores`
- [ ] Eseguire `migrate_questionnaire_scores.php`
- [ ] Verificare che tutti i punteggi siano migrati
- [ ] Testare completamento nuovo questionario
- [ ] Testare visualizzazione punteggi esistenti
- [ ] Eliminare file di migrazione (opzionale)

## Conclusione

Questa soluzione è **molto più semplice, robusta e manutenibile** rispetto all'approccio con snapshot. 

- **Meno codice** = meno bug
- **Più semplice** = più facile da capire
- **Più veloce** = migliori performance
- **Più affidabile** = dati sempre consistenti

Grazie all'utente per il suggerimento che ha portato a questa soluzione migliore! 🎉

# Fix Consistenza Punteggi Questionari - Documentazione Tecnica

## Problema Risolto

Il sistema di gestione questionari albo fornitori presentava un grave problema di inconsistenza dei dati: i punteggi venivano ricalcolati dinamicamente durante la visualizzazione, causando modifiche ai risultati storici quando:

1. I pesi delle domande o delle aree venivano modificati
2. Un questionario veniva eliminato completamente
3. La struttura del questionario veniva modificata in qualsiasi modo

Questo comportamento era inaccettabile perché comprometteva l'integrità dei dati storici e la tracciabilità delle valutazioni dei fornitori.

## Soluzione Implementata

### 1. Snapshot della Struttura del Questionario

**Nuovo campo database**: `questionnaire_snapshot` nella tabella `cogei_assignments`

Al momento dell'invio di un questionario, viene creato e salvato uno snapshot JSON completo della struttura, contenente:

- Titolo e descrizione del questionario
- Tutte le aree con i loro pesi
- Tutte le domande
- Tutte le opzioni di risposta con i loro pesi
- Flag N.A. per le opzioni

**Esempio di snapshot**:
```json
{
  "questionnaire_id": 5,
  "title": "Valutazione Fornitore",
  "description": "Questionario di valutazione",
  "created_at": "2024-01-26 15:00:00",
  "areas": [
    {
      "id": 10,
      "title": "Qualità del Servizio",
      "weight": 0.500,
      "sort_order": 1,
      "questions": [
        {
          "id": 25,
          "text": "Come valuti la qualità?",
          "is_required": 1,
          "sort_order": 1,
          "options": [
            {
              "id": 100,
              "text": "Eccellente",
              "weight": 1.000,
              "is_na": 0,
              "sort_order": 1
            },
            {
              "id": 101,
              "text": "Buono",
              "weight": 0.750,
              "is_na": 0,
              "sort_order": 2
            }
          ]
        }
      ]
    }
  ]
}
```

### 2. Modifica Logica Calcolo Punteggi

Tutte le funzioni di calcolo punteggio sono state modificate per:

1. **Usare sempre `computed_score` memorizzato**: Il campo `computed_score` nella tabella `cogei_responses` contiene il punteggio calcolato al momento della compilazione e NON viene MAI ricalcolato.

2. **Usare i pesi dallo snapshot**: Quando disponibile, lo snapshot viene utilizzato per ottenere i pesi delle aree, garantendo che modifiche successive non influenzino i calcoli.

3. **Fallback per questionari vecchi**: Per questionari compilati prima di questo fix, viene utilizzato il `computed_score` memorizzato combinato con i pesi attuali delle aree (limitazione del fallback).

### 3. File Modificati

#### `bo-questionnaires.php`
- Aggiunta migrazione database per campo `questionnaire_snapshot`
- Nuova funzione `boq_createQuestionnaireSnapshot()` per creare snapshot completi
- Modifica invio questionario per salvare snapshot
- Modifica `boq_calculateScore()` per usare snapshot e computed_score

#### `ajax_fornitori/get_supplier_questionnaires.php`
- Modifica `calculateQuestionnaireScore()` per usare snapshot e computed_score
- Aggiunta documentazione sul nuovo comportamento

#### `ajax_fornitori/get_questionnaire_details.php`
- Modifica calcolo punteggi per usare snapshot e computed_score
- Gestione fallback per questionari vecchi

#### `questionario-pubblico.php`
- Modifica visualizzazione questionario completato per usare snapshot
- Modifica pagina di successo dopo invio per usare snapshot
- Già usava `computed_score`, aggiunto uso snapshot per pesi area

## Formula di Calcolo

La formula di calcolo del punteggio rimane invariata:

```
Per ogni domanda:
  question_score = peso_opzione_selezionata
  (se opzione è N.A., usa peso massimo tra le opzioni)

Per ogni area:
  area_score = (somma di tutti i question_score dell'area) × peso_area

Punteggio finale:
  total_score = (somma di tutti gli area_score) × 100
```

**Differenza chiave**: I valori `peso_opzione_selezionata` e `peso_area` sono ora presi dallo snapshot o da `computed_score`, NON dal database attuale.

## Comportamento Garantito

### ✅ Modifiche ai Pesi NON Influenzano Punteggi Storici

**Scenario**: Un questionario completato ha un punteggio di 75/100. L'amministratore modifica i pesi delle domande.

**Comportamento vecchio (errato)**: Il punteggio viene ricalcolato e diventa 60/100.

**Comportamento nuovo (corretto)**: Il punteggio rimane 75/100 perché usa i valori memorizzati.

### ✅ Eliminazione Questionario NON Influenza Punteggi Storici

**Scenario**: Un questionario completato ha un punteggio di 80/100. L'amministratore elimina il questionario.

**Comportamento vecchio (errato)**: Il punteggio non può più essere calcolato o diventa 0.

**Comportamento nuovo (corretto)**: Il punteggio rimane 80/100 perché tutte le informazioni necessarie sono nello snapshot e nelle risposte.

### ✅ Retrocompatibilità

**Scenario**: Questionari compilati prima di questo fix.

**Comportamento**: Il sistema usa il fallback che combina `computed_score` memorizzato con i pesi attuali delle aree. Questo offre protezione parziale (i pesi delle domande sono protetti, ma non quelli delle aree).

## Test e Validazione

### Test Unitario della Logica

Il file `test_score_logic.php` verifica:
1. Calcolo corretto con snapshot
2. Resistenza a modifiche dei pesi
3. Uso di computed_score memorizzato
4. Fallback per questionari vecchi
5. Calcolo dopo eliminazione struttura

**Risultato**: ✅ Tutti i test passano

### Test di Integrazione

Il file `test_score_consistency.php` (richiede WordPress) simula:
1. Creazione questionario completo
2. Invio e compilazione
3. Modifica pesi opzioni
4. Modifica pesi aree
5. Eliminazione struttura

Verifica che il punteggio rimanga invariato in tutti gli scenari.

## Impatto sulle Performance

**Snapshot**: L'aggiunta dello snapshot aumenta leggermente la dimensione del database (~2-5KB per assignment), ma elimina la necessità di JOIN complessi durante il calcolo dei punteggi.

**Calcolo**: Il calcolo dei punteggi è ora più veloce perché:
- Non richiede JOIN con `cogei_options` per recuperare i pesi
- Usa solo `computed_score` già memorizzato
- In caso di snapshot, non richiede nemmeno JOIN con `cogei_areas`

## Migrazione Dati Esistenti

### Questionari Già Inviati ma Non Completati

Questi questionari NON avranno uno snapshot. Quando verranno completati:
- Il calcolo userà `computed_score` per i pesi delle domande (corretto)
- Il calcolo userà i pesi attuali delle aree (potenziale inconsistenza)

**Raccomandazione**: Evitare modifiche ai pesi di questionari già inviati ma non completati.

### Questionari Già Completati

Questi questionari NON avranno uno snapshot e useranno il fallback:
- `computed_score` memorizzato (protegge dai cambi ai pesi delle domande)
- Pesi attuali delle aree (non protetto)

**Raccomandazione**: 
1. Non modificare i pesi delle aree per questionari con risposte esistenti
2. Considerare uno script di migrazione per creare snapshot retroattivi se necessario

## Script di Migrazione Retroattiva (Opzionale)

Per creare snapshot per questionari già completati:

```php
// Recupera tutti gli assignment completati senza snapshot
$assignments = $wpdb->get_results("
    SELECT id, questionnaire_id 
    FROM {$wpdb->prefix}cogei_assignments 
    WHERE status = 'completed' 
    AND (questionnaire_snapshot IS NULL OR questionnaire_snapshot = '')
");

foreach ($assignments as $assignment) {
    // Crea snapshot
    $snapshot = boq_createQuestionnaireSnapshot($assignment->questionnaire_id);
    
    if ($snapshot) {
        // Salva snapshot
        $wpdb->update(
            $wpdb->prefix . 'cogei_assignments',
            ['questionnaire_snapshot' => json_encode($snapshot)],
            ['id' => $assignment->id]
        );
    }
}
```

**Attenzione**: Questo script crea snapshot basati sulla struttura ATTUALE del questionario, non quella al momento della compilazione. Utile solo se la struttura non è stata modificata.

## Best Practices per Sviluppatori Futuri

1. **Non modificare mai i pesi di un questionario con risposte esistenti**: Crea una nuova versione del questionario invece.

2. **Non eliminare mai un questionario con risposte esistenti**: Usa un flag "archived" o "deleted" invece di eliminazione fisica.

3. **Testare sempre con `test_score_logic.php`** dopo modifiche al sistema di calcolo.

4. **Non modificare il campo `computed_score`** nelle tabelle `cogei_responses` dopo la compilazione.

5. **Non modificare il campo `questionnaire_snapshot`** nelle tabelle `cogei_assignments` dopo la creazione.

## Riferimenti

- Tabelle database: `cogei_questionnaires`, `cogei_areas`, `cogei_questions`, `cogei_options`, `cogei_assignments`, `cogei_responses`
- Funzioni chiave: `boq_createQuestionnaireSnapshot()`, `boq_calculateScore()`
- Test: `test_score_logic.php`, `test_score_consistency.php`

## Changelog

### v2.0 - 2024-01-26
- ✅ Aggiunto campo `questionnaire_snapshot` a `cogei_assignments`
- ✅ Implementata funzione `boq_createQuestionnaireSnapshot()`
- ✅ Modificato calcolo punteggi per usare snapshot e computed_score
- ✅ Aggiunta gestione fallback per questionari vecchi
- ✅ Creati test automatizzati
- ✅ Documentazione completa

### v1.0 - Precedente
- ⚠️ Calcolo dinamico dei punteggi (comportamento errato)
- ⚠️ Punteggi cambiano con modifiche ai pesi

# Gestione Modifiche Risposte Questionari - Documentazione

## Problema Identificato

L'utente ha sollevato una questione importante: **cosa succede se un admin modifica le risposte di un questionario già completato?**

Il sistema aveva già la funzionalità di modifica risposte (`save_questionnaire_edits.php`), ma il punteggio nella tabella `cogei_questionnaire_scores` NON veniva aggiornato, causando inconsistenza tra risposte e punteggio visualizzato.

---

## Soluzione Implementata

### 1. Nuova Funzione: `boq_recalculateAndUpdateScore()`

Aggiunta in `bo-questionnaires.php`, questa funzione:

```php
function boq_recalculateAndUpdateScore($assignment_id) {
    // 1. Recupera tutte le risposte attuali
    // 2. Ricalcola il punteggio dalle risposte correnti
    // 3. AGGIORNA il record in cogei_questionnaire_scores (UPDATE)
    // 4. Aggiorna la data calculated_at
    // 5. Restituisce il nuovo punteggio
}
```

**Differenza con `boq_calculateAndSaveScore()`:**
- `boq_calculateAndSaveScore()` - Usa INSERT IGNORE (non sovrascrive se esiste)
- `boq_recalculateAndUpdateScore()` - Usa UPDATE (sovrascrive il punteggio esistente)

### 2. Modifica a `save_questionnaire_edits.php`

**PRIMA:**
```php
// Salvava le risposte modificate
// Ricalcolava il punteggio MA NON lo salvava nella tabella
// Restituiva il punteggio solo nella risposta JSON
$final_score = $total_score * 100;
```

**DOPO:**
```php
// Salvava le risposte modificate
// Richiama boq_recalculateAndUpdateScore() per aggiornare la tabella
require_once(dirname(__FILE__) . '/../bo-questionnaires.php');
$final_score = boq_recalculateAndUpdateScore($assignment_id);
```

---

## Flusso Completo

### Scenario 1: Prima Completazione
```
1. Utente compila questionario
2. questionario-pubblico.php
   ├─ Salva risposte in cogei_responses
   ├─ Marca assignment come 'completed'
   └─ boq_calculateAndSaveScore()
      └─ INSERT punteggio in cogei_questionnaire_scores

Risultato: Punteggio salvato nella tabella ✅
```

### Scenario 2: Modifica Risposte (Admin)
```
1. Admin modifica risposte via interfaccia
2. save_questionnaire_edits.php
   ├─ Aggiorna risposte in cogei_responses (UPDATE)
   └─ boq_recalculateAndUpdateScore()
      └─ UPDATE punteggio in cogei_questionnaire_scores

Risultato: Punteggio aggiornato nella tabella ✅
```

### Scenario 3: Visualizzazione Punteggio
```
1. Sistema richiede punteggio
2. boq_getScore($assignment_id)
   └─ SELECT da cogei_questionnaire_scores

Risultato: Legge sempre il punteggio attuale dalla tabella ✅
```

---

## Vantaggi della Soluzione

### ✅ Consistenza Dati
- Punteggio sempre allineato con le risposte attuali
- Nessuna discrepanza tra risposte e punteggio visualizzato

### ✅ Tracciabilità
- Il campo `calculated_at` mostra quando è stato aggiornato
- Facile verificare quando è stata l'ultima modifica

### ✅ Performance
- Nessun ricalcolo a runtime
- SELECT diretta per visualizzazione

### ✅ Semplicità
- Logica centralizzata in `boq_recalculateAndUpdateScore()`
- Un solo punto da modificare per cambiare il calcolo

---

## Gestione Casi Speciali

### Caso 1: Modifiche ai Pesi delle Domande/Aree

**Problema:** Se un admin modifica i pesi delle domande o delle aree, i questionari completati mantengono il loro punteggio originale.

**Comportamento:**
- Questionari completati → Punteggio NON cambia (design intenzionale)
- Questionari con risposte modificate → Punteggio viene ricalcolato con i nuovi pesi

**Rationale:** Garantisce immutabilità dei punteggi storici, a meno che le risposte non vengano esplicitamente modificate.

### Caso 2: Eliminazione Questionario

**Problema:** Se un questionario viene eliminato, le risposte e i punteggi rimangono nel database?

**Comportamento:**
- Tabella `cogei_questionnaire_scores` mantiene il punteggio
- Tabella `cogei_responses` mantiene le risposte
- Tabella `cogei_assignments` mantiene l'assignment

**Rationale:** I dati storici sono preservati per audit e reportistica.

### Caso 3: Modifica Parziale delle Risposte

**Problema:** Se l'admin modifica solo alcune risposte, il punteggio deve essere ricalcolato?

**Comportamento:**
- Sì, il punteggio viene sempre ricalcolato per tutto il questionario
- Non è possibile un aggiornamento parziale del punteggio

**Rationale:** Garantisce che il punteggio rifletta sempre lo stato completo delle risposte.

---

## Verifica della Soluzione

### Test Manuale

1. **Completa un questionario:**
   ```sql
   -- Verifica punteggio salvato
   SELECT a.id, a.target_user_id, s.final_score, s.calculated_at
   FROM cogei_assignments a
   INNER JOIN cogei_questionnaire_scores s ON a.id = s.assignment_id
   WHERE a.id = <assignment_id>;
   ```

2. **Modifica le risposte via admin:**
   - Accedi come admin
   - Modifica alcune risposte
   - Salva

3. **Verifica aggiornamento punteggio:**
   ```sql
   -- Il punteggio dovrebbe essere cambiato e calculated_at aggiornato
   SELECT a.id, s.final_score, s.calculated_at
   FROM cogei_assignments a
   INNER JOIN cogei_questionnaire_scores s ON a.id = s.assignment_id
   WHERE a.id = <assignment_id>;
   ```

### Test Automatizzato

```php
// Pseudo-codice per test
function test_score_update_on_edit() {
    // 1. Crea questionario e completa
    $assignment_id = create_and_complete_questionnaire();
    $initial_score = boq_getScore($assignment_id);
    
    // 2. Modifica risposte
    modify_responses($assignment_id, ['question_1' => 'new_option']);
    
    // 3. Verifica punteggio aggiornato
    $updated_score = boq_getScore($assignment_id);
    
    assert($initial_score !== $updated_score, "Score should change after edit");
}
```

---

## Query Utili per Debug

### Verifica punteggi e date di aggiornamento
```sql
SELECT 
    a.id,
    a.target_user_id,
    u.display_name as fornitore,
    s.final_score,
    s.calculated_at,
    a.sent_at,
    TIMESTAMPDIFF(SECOND, a.sent_at, s.calculated_at) as seconds_to_complete
FROM cogei_assignments a
INNER JOIN cogei_questionnaire_scores s ON a.id = s.assignment_id
LEFT JOIN wp_users u ON a.target_user_id = u.ID
WHERE a.status = 'completed'
ORDER BY s.calculated_at DESC;
```

### Trova questionari modificati dopo completamento
```sql
-- Questionari dove calculated_at è molto dopo sent_at (probabilmente modificati)
SELECT 
    a.id,
    a.target_user_id,
    s.final_score,
    a.sent_at,
    s.calculated_at,
    TIMESTAMPDIFF(DAY, a.sent_at, s.calculated_at) as days_difference
FROM cogei_assignments a
INNER JOIN cogei_questionnaire_scores s ON a.id = s.assignment_id
WHERE a.status = 'completed'
  AND TIMESTAMPDIFF(DAY, a.sent_at, s.calculated_at) > 1
ORDER BY days_difference DESC;
```

### Verifica consistenza tra risposte e punteggio
```sql
-- Conta risposte per assignment
SELECT 
    a.id,
    COUNT(DISTINCT r.id) as num_responses,
    s.final_score
FROM cogei_assignments a
LEFT JOIN cogei_responses r ON a.id = r.assignment_id
LEFT JOIN cogei_questionnaire_scores s ON a.id = s.assignment_id
WHERE a.status = 'completed'
GROUP BY a.id
HAVING num_responses = 0 OR s.final_score IS NULL;
```

---

## Best Practices

### Per Sviluppatori

1. **Non modificare mai direttamente `cogei_questionnaire_scores`**
   - Usa sempre `boq_recalculateAndUpdateScore()` per aggiornamenti

2. **Non eliminare record da `cogei_questionnaire_scores`**
   - Se serve ricalcolare, usa UPDATE non DELETE+INSERT

3. **Testa sempre le modifiche**
   - Verifica che il punteggio venga aggiornato correttamente
   - Controlla che `calculated_at` sia aggiornato

### Per Amministratori

1. **Documenta le modifiche**
   - Quando modifichi le risposte, annota il motivo

2. **Verifica il punteggio**
   - Dopo la modifica, controlla che il punteggio sia corretto

3. **Non modificare i pesi retroattivamente**
   - Se modifichi i pesi, i questionari già completati mantengono il loro punteggio
   - Considera di creare una nuova versione del questionario

---

## Changelog

### v2.1 - 2024-02-03
- ✅ Aggiunta funzione `boq_recalculateAndUpdateScore()`
- ✅ Modificato `save_questionnaire_edits.php` per aggiornare punteggio
- ✅ Documentazione completa gestione modifiche

### v2.0 - 2024-01-26
- ✅ Refactoring da snapshot a tabella dedicata
- ✅ Creata tabella `cogei_questionnaire_scores`
- ✅ Funzioni `boq_calculateAndSaveScore()` e `boq_getScore()`
- ✅ Script di migrazione

---

## Conclusione

Questa implementazione garantisce che:

1. **I punteggi sono sempre salvati** nella tabella dedicata
2. **I punteggi sono sempre aggiornati** quando le risposte vengono modificate
3. **I punteggi sono sempre consistenti** con le risposte attuali
4. **I punteggi sono sempre veloci da recuperare** (SELECT diretta)

La soluzione è **semplice, robusta e completa**! 🎉

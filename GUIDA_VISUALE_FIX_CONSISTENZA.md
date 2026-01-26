# Guida Visuale: Fix Consistenza Punteggi Questionari

## 📊 Il Problema

### PRIMA del Fix (❌ Comportamento Errato)

```
┌─────────────────────────────────────────────────────────┐
│  FASE 1: Creazione e Invio Questionario                │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Questionario:                                          │
│  ├─ Area "Qualità" (peso: 0.500)                       │
│  │   └─ Domanda "Valutazione?"                         │
│  │       ├─ Opzione "Eccellente" (peso: 1.000) ◄────   │
│  │       ├─ Opzione "Buono" (peso: 0.750)              │
│  │       └─ Opzione "Sufficiente" (peso: 0.500)        │
│  │                                                      │
│  └─ Salvato in: cogei_questionnaires + cogei_areas     │
│                 + cogei_questions + cogei_options       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  FASE 2: Compilazione Questionario                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Valutatore seleziona: "Eccellente" (peso: 1.000)      │
│                                                         │
│  Calcolo punteggio:                                     │
│  (1.000 × 0.500) × 100 = 50.0                          │
│                                                         │
│  Salvato in cogei_responses:                            │
│  ├─ selected_option_id: 1                              │
│  └─ computed_score: 1.000                               │
│                                                         │
│  ✅ Punteggio: 50.0 / 100                              │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  FASE 3: Modifica Pesi (Admin)                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Admin modifica peso opzione:                           │
│  "Eccellente": 1.000 → 0.300                           │
│                                                         │
│  Aggiornato in: cogei_options                           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  FASE 4: Visualizzazione Punteggio ❌ PROBLEMA!        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Sistema RICALCOLA leggendo dal DB:                     │
│                                                         │
│  SELECT o.weight                                        │
│  FROM cogei_options o                                   │
│  WHERE o.id = selected_option_id                        │
│                                                         │
│  Peso trovato: 0.300 (peso MODIFICATO!)                │
│                                                         │
│  Nuovo calcolo:                                         │
│  (0.300 × 0.500) × 100 = 15.0                          │
│                                                         │
│  ❌ Punteggio CAMBIATO: 50.0 → 15.0                    │
│  ❌ INCONSISTENZA DATI!                                 │
└─────────────────────────────────────────────────────────┘
```

### DOPO il Fix (✅ Comportamento Corretto)

```
┌─────────────────────────────────────────────────────────┐
│  FASE 1: Creazione e Invio Questionario                │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Questionario salvato in DB (come prima)                │
│                                                         │
│  🆕 NOVITÀ: Crea SNAPSHOT JSON                         │
│  {                                                      │
│    "areas": [{                                          │
│      "id": 1,                                           │
│      "title": "Qualità",                                │
│      "weight": 0.500,                                   │
│      "questions": [{                                    │
│        "id": 1,                                         │
│        "text": "Valutazione?",                          │
│        "options": [                                     │
│          {"id": 1, "text": "Eccellente", "weight": 1.0}│
│          {"id": 2, "text": "Buono", "weight": 0.75}    │
│        ]                                                │
│      }]                                                 │
│    }]                                                   │
│  }                                                      │
│                                                         │
│  Salvato in: cogei_assignments.questionnaire_snapshot   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  FASE 2: Compilazione Questionario                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Valutatore seleziona: "Eccellente" (peso: 1.000)      │
│                                                         │
│  🆕 Salva computed_score (IMMUTABILE):                 │
│  ├─ selected_option_id: 1                              │
│  └─ computed_score: 1.000  ◄─── VALORE FISSO           │
│                                                         │
│  Calcolo punteggio:                                     │
│  (1.000 × 0.500) × 100 = 50.0                          │
│                                                         │
│  ✅ Punteggio: 50.0 / 100                              │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  FASE 3: Modifica Pesi (Admin)                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Admin modifica peso opzione:                           │
│  "Eccellente": 1.000 → 0.300                           │
│                                                         │
│  ⚠️  Modifica solo cogei_options                       │
│  ✅ computed_score NON viene toccato                   │
│  ✅ snapshot NON viene toccato                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  FASE 4: Visualizzazione Punteggio ✅ RISOLTO!         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Sistema USA snapshot + computed_score:                 │
│                                                         │
│  1. Legge snapshot da cogei_assignments                 │
│     → peso area: 0.500                                  │
│                                                         │
│  2. Legge computed_score da cogei_responses             │
│     → score memorizzato: 1.000                          │
│                                                         │
│  3. Calcola usando valori MEMORIZZATI:                  │
│     (1.000 × 0.500) × 100 = 50.0                       │
│                                                         │
│  ✅ Punteggio INVARIATO: 50.0                          │
│  ✅ CONSISTENZA GARANTITA!                              │
│                                                         │
│  🚫 IGNORA peso attuale nel DB (0.300)                 │
└─────────────────────────────────────────────────────────┘
```

## 🗂️ Struttura Database

### Schema Aggiornato

```
┌────────────────────────────────────────────────┐
│  cogei_assignments                             │
├────────────────────────────────────────────────┤
│  id                                            │
│  questionnaire_id                              │
│  target_user_id                                │
│  inspector_email                               │
│  sent_by                                       │
│  sent_at                                       │
│  status                                        │
│  token                                         │
│  questionnaire_snapshot  🆕 NUOVO!             │
│  └─ LONGTEXT (JSON completo)                   │
└────────────────────────────────────────────────┘

┌────────────────────────────────────────────────┐
│  cogei_responses                               │
├────────────────────────────────────────────────┤
│  id                                            │
│  assignment_id                                 │
│  question_id                                   │
│  selected_option_id                            │
│  computed_score  ◄─── VALORE IMMUTABILE        │
│  answered_at                                   │
└────────────────────────────────────────────────┘
```

## 🔄 Flusso Dati

### Invio Questionario

```
┌─────────────┐
│  Admin      │
│  invia      │
│  questionario│
└──────┬──────┘
       │
       v
┌──────────────────────────────┐
│  boq_createQuestionnaireSnapshot()│
│  ├─ Legge da DB              │
│  ├─ Crea JSON completo       │
│  └─ Ritorna snapshot         │
└──────┬───────────────────────┘
       │
       v
┌──────────────────────────────┐
│  Salva in cogei_assignments  │
│  ├─ questionnaire_id         │
│  ├─ target_user_id           │
│  └─ questionnaire_snapshot 🆕│
└──────────────────────────────┘
```

### Compilazione Questionario

```
┌─────────────┐
│  Valutatore │
│  compila    │
│  questionario│
└──────┬──────┘
       │
       v
┌──────────────────────────────┐
│  Per ogni risposta:          │
│  ├─ Legge peso opzione       │
│  ├─ Se N.A., usa peso max    │
│  └─ Calcola computed_score   │
└──────┬───────────────────────┘
       │
       v
┌──────────────────────────────┐
│  Salva in cogei_responses    │
│  ├─ selected_option_id       │
│  └─ computed_score (FISSO) 🔒│
└──────────────────────────────┘
```

### Calcolo Punteggio

```
┌─────────────┐
│  Richiesta  │
│  punteggio  │
└──────┬──────┘
       │
       v
┌──────────────────────────────┐
│  boq_calculateScore()        │
│  └─ Legge assignment         │
└──────┬───────────────────────┘
       │
       v
    ┌──┴──┐
    │ HA  │
    │SNAP?│
    └──┬──┘
       │
    ┌──┴──────────────┐
    │                 │
  SI│               NO│
    │                 │
    v                 v
┌───────────┐   ┌───────────┐
│ USA       │   │ FALLBACK  │
│ SNAPSHOT  │   │ USA DB    │
│ + computed│   │ + computed│
│ _score    │   │ _score    │
└─────┬─────┘   └─────┬─────┘
      │               │
      v               v
┌──────────────────────────────┐
│  Punteggio IMMUTABILE        │
│  ✅ Garantito consistente    │
└──────────────────────────────┘
```

## 📋 Confronto Funzioni

### Prima del Fix

```php
function boq_calculateScore($assignment_id) {
    // ❌ RICALCOLA da pesi attuali
    $area_responses = $wpdb->get_results("
        SELECT o.weight as option_weight  ◄── Peso ATTUALE
        FROM responses r
        JOIN options o ON r.selected_option_id = o.id
    ");
    
    foreach ($area_responses as $resp) {
        $area_sum += $resp['option_weight'];  ◄── USA peso ATTUALE
    }
    
    return $area_sum * $area['weight'] * 100;  ◄── Peso area ATTUALE
}
```

### Dopo il Fix

```php
function boq_calculateScore($assignment_id) {
    // ✅ USA snapshot se disponibile
    $assignment = $wpdb->get_row("
        SELECT questionnaire_snapshot
        FROM assignments
        WHERE id = $assignment_id
    ");
    
    $snapshot = json_decode($assignment['questionnaire_snapshot']);
    
    if ($snapshot) {
        foreach ($snapshot['areas'] as $area) {
            // ✅ USA computed_score MEMORIZZATO
            $area_responses = $wpdb->get_results("
                SELECT r.computed_score  ◄── Valore MEMORIZZATO
                FROM responses r
            ");
            
            $area_sum = array_sum($area_responses);
            
            // ✅ USA peso area da SNAPSHOT
            $total += $area_sum * $area['weight'];  ◄── Da SNAPSHOT
        }
    }
    
    return $total * 100;
}
```

## 🎯 Casi d'Uso

### Caso 1: Modifica Pesi

```
Scenario: Admin modifica peso domanda da 1.0 a 0.3

Prima del Fix:
  Punteggio storico: 50/100
  Dopo modifica:     15/100  ❌ CAMBIATO!

Dopo il Fix:
  Punteggio storico: 50/100
  Dopo modifica:     50/100  ✅ INVARIATO!
```

### Caso 2: Eliminazione Questionario

```
Scenario: Admin elimina questionario completato

Prima del Fix:
  Punteggio: 75/100
  Dopo eliminazione: ERROR o 0/100  ❌ PERSO!

Dopo il Fix:
  Punteggio: 75/100
  Dopo eliminazione: 75/100  ✅ PRESERVATO!
  (grazie allo snapshot)
```

### Caso 3: Questionari Vecchi

```
Scenario: Questionario completato PRIMA del fix

Protezione:
  ✅ Pesi domande: PROTETTI (computed_score)
  ⚠️  Pesi aree: Usa valori attuali DB

Raccomandazione:
  - Non modificare pesi aree per questionari vecchi
  - Oppure: eseguire script migrazione snapshot
```

## 📝 Checklist Sviluppatori

### ✅ Quando Invii Questionario
- [x] Chiamare `boq_createQuestionnaireSnapshot()`
- [x] Salvare snapshot in `questionnaire_snapshot`
- [x] Verificare che snapshot sia JSON valido

### ✅ Quando Calcoli Punteggi
- [x] Controllare presenza snapshot
- [x] Usare snapshot se disponibile
- [x] Usare sempre `computed_score`
- [x] NON leggere pesi da cogei_options

### ❌ Da NON Fare MAI
- [ ] Modificare `computed_score` dopo compilazione
- [ ] Modificare `questionnaire_snapshot` dopo creazione
- [ ] Ricalcolare punteggi da pesi attuali DB
- [ ] Eliminare risposte senza eliminare assignment

## 🧪 Come Testare

```bash
# Test unitari (non richiede WordPress)
php test_score_logic.php

# Output atteso:
# ✅ Calcolo con snapshot
# ✅ Resistenza modifiche pesi
# ✅ Uso computed_score memorizzato
# ✅ Fallback questionari vecchi
# ✅ Calcolo dopo eliminazione
# 🎉 TUTTI I TEST SONO PASSATI!

# Test integrazione (richiede WordPress)
php test_score_consistency.php
```

## 📚 Risorse

- **Documentazione Tecnica**: `DOCUMENTAZIONE_FIX_CONSISTENZA_PUNTEGGI.md`
- **PR Summary**: `PR_SUMMARY_FIX_CONSISTENZA_PUNTEGGI.md`
- **Test Unitari**: `test_score_logic.php`
- **Test Integrazione**: `test_score_consistency.php`

---

**Versione**: 2.0
**Data**: 2024-01-26
**Status**: ✅ Implementato e testato

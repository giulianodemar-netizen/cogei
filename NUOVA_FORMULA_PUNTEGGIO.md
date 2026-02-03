# Modifica Formula Calcolo Punteggio Questionari

## Data: 2026-02-03

## Obiettivo
Modificare la formula di calcolo del punteggio per i questionari albo fornitori per implementare una nuova logica che gestisce diversamente le risposte contrassegnate come "N.A." (Non Applicabile).

## Problema
La vecchia logica contava le risposte N.A. come se avessero il peso massimo possibile, il che poteva gonfiare artificialmente i punteggi quando molte domande erano marcate come non applicabili.

## Soluzione Implementata

### Vecchia Logica
```
Per ogni area:
  - Somma i pesi delle risposte (se N.A., usa peso massimo)
  - Moltiplica per il peso dell'area
  - Aggiungi al punteggio totale
Punteggio finale = totale × 100
```

**Esempio:** Area weight=0.5, Q1 risposta=0.75, Q2 N.A. (max=1.0)
- Area sum = 0.75 + 1.0 = 1.75
- Area score = 1.75 × 0.5 = 0.875
- Final score = 0.875 × 100 = **87.5/100**

### Nuova Logica
```
Per ogni domanda:
  1. Peso Effettivo = max_weight × area_weight (0 se N.A.)
  2. Punteggio = answer_weight × area_weight (0 se N.A.)

Punteggio finale = (Σ Punteggi / Σ Pesi Effettivi) × 100
```

**Stesso Esempio:** Area weight=0.5, Q1 risposta=0.75, Q2 N.A. (max=1.0)
- Peso Effettivo Q1 = 1.0 × 0.5 = 0.5
- Peso Effettivo Q2 = 0 (N.A. esclusa)
- Total Peso Effettivo = 0.5
- Punteggio Q1 = 0.75 × 0.5 = 0.375
- Punteggio Q2 = 0 (N.A. esclusa)
- Total Punteggio = 0.375
- Final score = (0.375 / 0.5) × 100 = **75.0/100**

### Differenza Chiave
Le risposte N.A. sono ora **completamente escluse** dal calcolo invece di contribuire con il peso massimo. Il punteggio finale è normalizzato solo sulle domande applicabili.

## File Modificati

### 1. `ajax_fornitori/save_questionnaire_edits.php`
- **Righe modificate:** 203-248
- **Funzione:** Gestione delle modifiche admin ai questionari completati
- **Cambiamento:** Implementata nuova logica di calcolo con Peso Effettivo e Punteggio

### 2. `questionario-pubblico.php`
- **Righe modificate:** 325-378
- **Funzione:** Calcolo punteggio alla prima compilazione pubblica del questionario
- **Cambiamento:** Implementata nuova logica di calcolo con Peso Effettivo e Punteggio

### 3. `bo-questionnaires.php`
- **Funzioni modificate:**
  - `boq_calculateAndSaveScore()` (righe 290-386)
  - `boq_recalculateAndUpdateScore()` (righe 388-495)
- **Funzione:** Calcolo e ricalcolo dei punteggi nel back office
- **Cambiamento:** Implementata nuova logica in entrambe le funzioni

### 4. `migrate_questionnaire_scores.php`
- **Righe modificate:** 133-190
- **Funzione:** Script di migrazione per ricalcolare punteggi esistenti
- **Cambiamento:** Aggiornato per usare la nuova logica quando viene eseguito

## Test Implementati

### File: `test_new_scoring_logic.php`
Contiene 5 test automatici:
1. ✅ Scenario Base (Nessuna N.A.)
2. ✅ Scenario con una risposta N.A.
3. ✅ Scenario con tutte risposte N.A.
4. ✅ Scenario con più aree
5. ✅ Confronto con vecchia logica

**Esecuzione:**
```bash
php test_new_scoring_logic.php
```

## Impatto

### Punteggi Più Bassi per Questionari con N.A.
I questionari con molte risposte N.A. vedranno punteggi generalmente più bassi, in quanto le N.A. non contribuiscono più al punteggio finale.

### Esempio di Impatto
- **Scenario:** 10 domande, 8 perfette (1.0), 2 N.A.
- **Vecchia logica:** (8×1.0 + 2×1.0) / 10 = 100% ✨
- **Nuova logica:** (8×1.0) / (8×1.0) = 100% ✨ (stessa cosa se tutte perfette)
  
- **Scenario:** 10 domande, 8 medie (0.5), 2 N.A.
- **Vecchia logica:** (8×0.5 + 2×1.0) / 10 × 100 = 60%
- **Nuova logica:** (8×0.5) / (8×1.0) × 100 = 50% 📉 (differenza!)

### Vantaggi
1. **Più Accurato:** I punteggi riflettono solo le risposte realmente applicabili
2. **Più Giusto:** Non si premiano i fornitori che marcano molte domande come N.A.
3. **Più Trasparente:** È chiaro che le N.A. non influenzano il calcolo

## Retrocompatibilità

⚠️ **IMPORTANTE:** Questa modifica cambia il metodo di calcolo del punteggio. I questionari già completati manterranno i loro punteggi originali fino a quando:
1. Non vengono modificati manualmente dall'admin
2. Non viene eseguito lo script di migrazione

Se si desidera ricalcolare tutti i punteggi esistenti con la nuova logica, eseguire:
```bash
php migrate_questionnaire_scores.php
```

## Note Tecniche

### Gestione Caso Speciale
Se **tutte** le risposte sono N.A., il punteggio finale è 0:
```php
$final_score = ($total_peso_effettivo > 0) 
    ? ($total_punteggio / $total_peso_effettivo) * 100 
    : 0;
```

### Database
Non sono necessarie modifiche al database. La logica di calcolo è completamente implementata a livello applicativo.

## Checklist Verifica

- [x] Test unitari passano
- [x] Logica implementata in tutti i punti di calcolo
- [x] Script di migrazione aggiornato
- [x] Documentazione creata
- [ ] Test manuale su ambiente di sviluppo
- [ ] Code review completato
- [ ] Security scan completato
- [ ] Deploy in produzione
- [ ] Comunicazione stakeholder

## Riferimenti

- **Issue originale:** Modifica richiesta per formula calcolo punteggio
- **File principale:** `ajax_fornitori/save_questionnaire_edits.php`
- **Test:** `test_new_scoring_logic.php`

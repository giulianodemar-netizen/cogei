# Confronto Visuale: Vecchia vs Nuova Formula

## Scenario di Test

Immaginiamo un questionario con **2 aree**, ciascuna con **3 domande**:

### Configurazione
- **Area 1** - Peso: 0.6 (60%)
  - Domanda 1: Risposta peso 1.0 (Eccellente) - Max: 1.0
  - Domanda 2: Risposta peso 0.7 (Buono) - Max: 1.0
  - Domanda 3: **N.A.** (Non Applicabile) - Max: 1.0

- **Area 2** - Peso: 0.4 (40%)
  - Domanda 4: Risposta peso 0.5 (Adeguato) - Max: 1.0
  - Domanda 5: Risposta peso 0.8 (Molto Buono) - Max: 1.0
  - Domanda 6: Risposta peso 1.0 (Eccellente) - Max: 1.0

## 🔴 VECCHIA FORMULA

### Calcolo Area 1
```
Somma pesi = 1.0 + 0.7 + 1.0 (N.A. = max) = 2.7
Area score = 2.7 × 0.6 = 1.62
```

### Calcolo Area 2
```
Somma pesi = 0.5 + 0.8 + 1.0 = 2.3
Area score = 2.3 × 0.4 = 0.92
```

### Punteggio Finale
```
Total = 1.62 + 0.92 = 2.54
Final Score = 2.54 × 100 = 254 / 100 ❌ (over 100!)
```

**Nota:** Con normalizzazione:
```
Final Score = (2.54 / somma_max_possibile) × 100
```

## 🟢 NUOVA FORMULA

### Area 1

#### Calcolo Peso Effettivo
```
Q1: 1.0 (max) × 0.6 (area) = 0.6
Q2: 1.0 (max) × 0.6 (area) = 0.6
Q3: 0 (N.A. esclusa)
Total Peso Effettivo Area 1 = 1.2
```

#### Calcolo Punteggio
```
Q1: 1.0 (risposta) × 0.6 (area) = 0.6
Q2: 0.7 (risposta) × 0.6 (area) = 0.42
Q3: 0 (N.A. esclusa)
Total Punteggio Area 1 = 1.02
```

### Area 2

#### Calcolo Peso Effettivo
```
Q4: 1.0 (max) × 0.4 (area) = 0.4
Q5: 1.0 (max) × 0.4 (area) = 0.4
Q6: 1.0 (max) × 0.4 (area) = 0.4
Total Peso Effettivo Area 2 = 1.2
```

#### Calcolo Punteggio
```
Q4: 0.5 (risposta) × 0.4 (area) = 0.2
Q5: 0.8 (risposta) × 0.4 (area) = 0.32
Q6: 1.0 (risposta) × 0.4 (area) = 0.4
Total Punteggio Area 2 = 0.92
```

### Punteggio Finale
```
Total Peso Effettivo = 1.2 + 1.2 = 2.4
Total Punteggio = 1.02 + 0.92 = 1.94

Final Score = (1.94 / 2.4) × 100 = 80.83 / 100 ✅
```

## 📊 Confronto Risultati

| Metrica | Vecchia Formula | Nuova Formula | Differenza |
|---------|----------------|---------------|------------|
| Area 1 Score | 1.62 | 1.02 / 1.2 = 0.85 | -47.5% |
| Area 2 Score | 0.92 | 0.92 / 1.2 = 0.767 | -16.6% |
| **Final Score** | ~84.67* | **80.83** | **-3.84 punti** |

*Con normalizzazione appropriata

## 🎯 Punti Chiave

### Vecchia Formula
- ✅ Semplice da calcolare
- ❌ Le N.A. contano come punteggi perfetti
- ❌ Può gonfiare artificialmente i punteggi
- ❌ Incentiva a marcare domande come N.A.

### Nuova Formula
- ✅ Le N.A. sono completamente escluse
- ✅ Punteggio normalizzato solo su domande applicabili
- ✅ Più accurato e trasparente
- ✅ Non incentiva l'uso improprio di N.A.
- ⚠️ Calcolo leggermente più complesso

## 💡 Esempio Estremo

**Questionario con 10 domande, tutte N.A.**

### Vecchia Formula
```
Tutte le 10 = max weight = 1.0
Score = 100 / 100 ⭐⭐⭐⭐⭐
```
→ Fornitore ottiene punteggio perfetto senza rispondere!

### Nuova Formula
```
Total Peso Effettivo = 0 (tutte escluse)
Total Punteggio = 0
Score = 0 / 100 ⭐☆☆☆☆
```
→ Punteggio zero se non ci sono domande applicabili

## 🔄 Impatto sui Questionari Reali

### Caso A: Poche N.A. (1-2 su 20 domande)
**Impatto:** Minimo (< 5% differenza)

### Caso B: Moderate N.A. (5-8 su 20 domande)  
**Impatto:** Medio (5-15% differenza)

### Caso C: Molte N.A. (>10 su 20 domande)
**Impatto:** Significativo (>15% differenza)

## ✅ Raccomandazioni

1. **Comunicare il cambiamento** ai fornitori e al team interno
2. **Eseguire lo script di migrazione** per ricalcolare i punteggi esistenti (se desiderato)
3. **Monitorare i nuovi punteggi** per le prime settimane
4. **Aggiornare la documentazione** per i fornitori sull'uso appropriato di N.A.

## 📝 Note Implementative

La nuova formula è implementata in modo consistente in tutti i punti di calcolo:
- ✅ `save_questionnaire_edits.php` - Modifiche admin
- ✅ `questionario-pubblico.php` - Compilazione pubblica
- ✅ `bo-questionnaires.php` - Funzioni di calcolo
- ✅ `migrate_questionnaire_scores.php` - Script di migrazione

Tutti i test automatici passano ✅

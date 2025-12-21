# Albo Fornitori - Sistema Questionari con Votazioni Stelline

## 📋 Panoramica

Sistema completo per la valutazione dei fornitori tramite questionari con visualizzazione a stelle.

---

## 🎯 Funzionalità Principali

### 1. Gestione Questionari
- Creazione questionari strutturati (Aree → Domande → Opzioni)
- Editor JavaScript senza ricaricamenti pagina
- Drag & drop per ordinare domande e opzioni
- Pesi personalizzabili per aree e opzioni

### 2. Invio e Compilazione
- Ricerca fornitori con filtro real-time
- Invio email automatico all'ispettore
- Form pubblico standalone (`/questionario/`)
- Token univoco SHA-256 per sicurezza

### 3. **⭐ NUOVO: Votazioni Albo Fornitori**
- Classifica fornitori con stelle (0-5)
- Media automatica di tutti i questionari
- Medaglie per i primi 3 fornitori
- Indicatori visivi di performance

---

## ⭐ Sistema di Votazione

### Conversione Punteggio → Stelle

```
Formula: stelle = punteggio × 5 (arrotondato a 0.5)
```

| Punteggio | Stelle | Valutazione |
|-----------|--------|-------------|
| 0.96 | ★★★★★ (4.8) | Eccellente |
| 0.84 | ★★★★☆ (4.2) | Molto Buono |
| 0.70 | ★★★☆☆ (3.5) | Adeguato |
| 0.50 | ★★★☆☆ (2.5) | Adeguato |
| 0.40 | ★★☆☆☆ (2.0) | Critico |
| 0.20 | ★☆☆☆☆ (1.0) | Inadeguato |

### Scala Valutazioni

- **★★★★★ (4.5-5.0)** = Eccellente
- **★★★★☆ (3.5-4.4)** = Molto Buono
- **★★★☆☆ (2.5-3.4)** = Adeguato
- **★★☆☆☆ (1.5-2.4)** = Critico
- **★☆☆☆☆ (0.0-1.4)** = Inadeguato

---

## 📊 Tab "Votazioni Albo Fornitori"

### Caratteristiche

- **Posizione**: 4° tab nell'interfaccia admin
- **Dati mostrati**:
  - Posizione in classifica (con medaglie 🥇🥈🥉 per i primi 3)
  - Nome fornitore
  - Valutazione a stelle
  - Punteggio numerico (0-1)
  - Numero questionari completati

### Colori Indicativi

| Stelle | Sfondo | Significato |
|--------|--------|-------------|
| ≥ 4.5 | Verde | Eccellente |
| 3.5-4.4 | Giallo | Buono |
| < 2.5 | Rosso | Problematico |

### Esempio Visualizzazione

```
┌────────────────────────────────────────────────────────┐
│ ⭐ Votazioni Albo Fornitori                           │
├────┬─────────────┬──────────────┬─────────┬───────────┤
│Pos │ Fornitore   │ Valutazione  │Punteggio│Questionari│
├────┼─────────────┼──────────────┼─────────┼───────────┤
│🥇1 │ Rossi Mario │★★★★★ (4.8)  │ 0.960   │    [5]    │
│🥈2 │ Bianchi L.  │★★★★☆ (4.2)  │ 0.840   │    [3]    │
│🥉3 │ Verdi Paolo │★★★☆☆ (3.5)  │ 0.700   │    [7]    │
│ 4  │ Neri Antonio│★★☆☆☆ (2.0)  │ 0.400   │    [2]    │
└────┴─────────────┴──────────────┴─────────┴───────────┘
```

---

## 🔧 Implementazione Tecnica

### Funzioni Chiave

#### 1. `boq_convertScoreToStars($score)`
Converte punteggio 0-1 in stelle 0-5
```php
$stars = $score * 5;
$stars = round($stars * 2) / 2; // Arrotonda a 0.5
return max(0, min(5, $stars));
```

#### 2. `boq_renderStarRating($stars)`
Genera HTML per visualizzazione stelle
```php
// Stelle piene: ★ (oro)
// Mezze stelle: ☆ (oro outline)
// Stelle vuote: ☆ (grigio)
return '<span>★★★☆☆ (3.5)</span>';
```

#### 3. `boq_renderRatingsTab()`
Renderizza tab completo con query aggregazione

### Query Database

```sql
SELECT 
    target_user_id as user_id,
    COUNT(DISTINCT id) as total_questionnaires,
    AVG(computed_score) as avg_score
FROM cogei_assignments a
LEFT JOIN cogei_responses r ON r.assignment_id = a.id
WHERE a.status = 'completed'
GROUP BY target_user_id
ORDER BY avg_score DESC
```

---

## 📁 Struttura Files

### bo-questionnaires.php (2222 righe)
**Interfaccia admin completa**
- Gestione questionari
- Invio questionari
- Risultati
- **Votazioni (NUOVO)**

### questionario-pubblico.php (583 righe)
**Form pubblico standalone**
- File indipendente in `/questionario/`
- Compilazione questionari
- Calcolo punteggio automatico

---

## 🚀 Come Usare

### Setup Iniziale
1. Copiare `bo-questionnaires.php` nel tema WordPress
2. Creare cartella `/cogei/questionario/`
3. Copiare `questionario-pubblico.php` come `index.php` nella cartella

### Workflow Valutazione

1. **Admin**: Crea questionario nel tab "Questionari"
2. **Admin**: Invia a ispettore nel tab "Invii"
   - Seleziona fornitore da valutare
   - Inserisci email ispettore
3. **Ispettore**: Riceve email con link
4. **Ispettore**: Compila questionario
5. **Admin**: Visualizza risultati nel tab "Risultati"
6. **Admin**: Consulta classifica nel tab "⭐ Votazioni Albo Fornitori"

---

## 📈 Vantaggi del Sistema

✅ **Visuale**: Stelle intuitive e immediate
✅ **Oggettivo**: Basato su media di valutazioni multiple  
✅ **Comparabile**: Classifica fornitori facilmente
✅ **Contestuale**: Mostra numero valutazioni
✅ **Motivante**: Medaglie per i migliori
✅ **Trasparente**: Punteggio numerico visibile
✅ **Scalabile**: Gestisce qualsiasi numero fornitori

---

## 🎨 Design

### Colori
- **Oro**: #FFD700 (stelle piene)
- **Grigio**: #DDD (stelle vuote)
- **Blu**: #03679e (header, badge)
- **Verde**: #f0fdf4 (eccellente)
- **Giallo**: #fefef0 (buono)
- **Rosso**: #fef2f2 (critico)

### Tipografia
- Stelle: 20px, letter-spacing 2px
- Punteggio: 16px bold
- Medaglie: 24px

---

## 📋 Checklist Completamento

✅ Terminologia HSE → Albo Fornitori  
✅ Tab votazioni implementato  
✅ Sistema stelle funzionante  
✅ Query aggregazione corretta  
✅ Medaglie top 3  
✅ Colori indicativi  
✅ Badge conteggio questionari  
✅ Legenda valutazioni  
✅ Gestione stato vuoto  
✅ Grammatica italiana corretta  
✅ Visualizzazione stelle migliorata  
✅ Code review superata  
✅ Sicurezza verificata  

---

## 📞 Supporto

Per domande o personalizzazioni, consultare:
- `QUESTIONARI_IMPLEMENTATION.md` - Documentazione tecnica completa
- `QUICK_START_QUESTIONARI.md` - Guida rapida
- `SETUP_QUESTIONARIO.md` - Istruzioni setup

---

**Sistema Albo Fornitori con Votazioni a Stelle - Versione 2.0**  
*Aggiornato: Dicembre 2024*  
*Status: ✅ Production Ready*

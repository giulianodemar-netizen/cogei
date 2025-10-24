# Linee Guida Stile HSE - Front-end

## Introduzione

Questo documento descrive le linee guida di stile per il front-end HSE (Health, Safety & Environment) del sistema Albo Fornitori Cogei. Gli stili sono stati progettati per:

- **Massimizzare la leggibilità** con tipografia chiara e contrasto elevato
- **Migliorare l'usabilità** con campi form ben spaziati e dimensioni ottimali per il tocco
- **Garantire l'accessibilità** secondo gli standard WCAG 2.1 AA
- **Mantenere coerenza** con il back-office (BO HSE)

## Riferimenti

Gli stili sono stati estratti e adattati dalle sezioni seguenti del file "BO HSE":
- `#mezzo-details-modal` - Modal dettagli mezzi meccanici
- `#assignments-summary` - Sezione riepilogo assegnazioni
- `.assignments-summary-style` - Classe riutilizzabile per card e contenuti

## Palette Colori

### Colori Testo (High Contrast)

| Uso | Colore | Hex | Contrast Ratio |
|-----|--------|-----|----------------|
| Testo principale | Nero scuro | `#222` | 16:1 su bianco |
| Testo label | Grigio scuro | `#333` | 12.6:1 su bianco |
| Testo secondario | Grigio medio | `#666` | 7:1 su bianco |
| Testo helper/placeholder | Grigio | `#6c757d` | 4.6:1 su bianco |

### Colori Accento

| Uso | Colore | Hex |
|-----|--------|-----|
| Link e focus | Blu primario | `#0066a2` |
| Header modal | Blu chiaro | `#2196f3` |
| Header gradient | Blu scuro | `#1976d2` |

### Colori Stati

| Stato | Colore | Hex | Uso |
|-------|--------|-----|-----|
| Success | Verde | `#28a745` | Operazioni completate, validazione OK |
| Warning | Giallo | `#ffc107` | Attenzione, scadenze vicine |
| Danger | Rosso | `#dc3545` | Errori, campi invalid, scadenze passate |
| Info | Ciano | `#17a2b8` | Informazioni generiche |

### Colori Sfondo

| Uso | Colore | Hex |
|-----|--------|-----|
| Bianco principale | Bianco | `#fff` |
| Grigio chiaro | Grigio chiaro | `#f7f7f7` |
| Grigio card | Grigio card | `#f8f9fa` |
| Grigio bordo | Grigio bordo | `#dee2e6` |

## Tipografia

### Font Family

```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, 
             "Helvetica Neue", Arial, sans-serif;
```

Questa stack garantisce:
- Font nativi ottimizzati per ogni sistema operativo
- Eccellente leggibilità su tutti i dispositivi
- Rendering veloce senza download di font esterni

### Font Sizes

| Elemento | Size | Line-height | Uso |
|----------|------|-------------|-----|
| Input/Label | `16px` | `1.5` | Campi form, evita zoom iOS |
| Testo helper | `14px` | `1.6` | Descrizioni, note |
| Badge | `13px` | `1.4` | Badge, tag piccoli |
| Sottotitoli | `18px` | `1.4` | Sottosezioni |
| Card title | `19px` | `1.4` | Titoli card e modal |
| Section title | `20px` | `1.4` | Titoli principali |

### Font Weights

- **Normal (400)**: Testo body, paragrafi
- **Medium (500)**: Testo enfatizzato
- **Semibold (600)**: Label, strong
- **Bold (700)**: Titoli, header

### Regole Chiave

1. **Mai usare font-size < 16px per input** - Previene lo zoom automatico su iOS
2. **Line-height min 1.5** - Migliora la leggibilità del testo
3. **Line-height 1.6-1.8 per paragrafi** - Comfort ottimale di lettura
4. **Letter-spacing 0.3px per titoli** - Migliora la separazione visiva

## Campi Form

### Input Base

```css
/* Dimensioni */
font-size: 16px;
padding: 12px 14px;
min-height: 44px;

/* Bordi */
border: 2px solid #dee2e6;
border-radius: 6px;

/* Colori */
color: #222;
background-color: #fff;

/* Placeholder */
placeholder {
    color: #6c757d;
    opacity: 1;
}
```

### Stati Input

#### Focus
```css
border-color: #0066a2;
box-shadow: 0 0 0 3px rgba(0, 102, 162, 0.2);
outline: 2px solid #0066a2;
outline-offset: 1px;
```

#### Hover
```css
border-color: #2196f3;
```

#### Error
```css
border-color: #dc3545;
background-color: rgba(220, 53, 69, 0.05);
box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25); /* su focus */
```

#### Success
```css
border-color: #28a745;
background-color: rgba(40, 167, 69, 0.05);
```

#### Disabled
```css
background-color: #f8f9fa;
color: #6c757d;
opacity: 0.6;
cursor: not-allowed;
```

### File Input

```css
font-size: 16px;
padding: 12px;
min-height: 44px;
border: 2px dashed #dee2e6;
background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
cursor: pointer;
```

Su focus diventa solid border per indicare interazione.

## Bottoni

### Dimensioni Minime (Accessibilità Mobile)

```css
font-size: 16px;
padding: 12px 24px;
min-height: 44px;
min-width: 44px;
```

**Rationale**: La dimensione minima 44x44px è raccomandata da WCAG e Apple Human Interface Guidelines per garantire tappabilità facile su touchscreen.

### Varianti

#### Primary
```css
background: linear-gradient(135deg, #0066a2, #004d7a);
color: #fff;
box-shadow: 0 2px 8px rgba(0, 102, 162, 0.3);
```

#### Success
```css
background: linear-gradient(135deg, #28a745, #20c997);
color: #fff;
```

#### Danger
```css
background: linear-gradient(135deg, #dc3545, #c82333);
color: #fff;
```

#### Warning
```css
background: linear-gradient(135deg, #ffc107, #fd7e14);
color: #000;
```

### Stati Hover
- `transform: translateY(-2px)` - Sollevamento leggero
- Ombra aumentata per enfasi
- Gradiente leggermente più scuro

## Spacing

### Padding

| Uso | Valore |
|-----|--------|
| Input interno | `12-14px` |
| Card | `22px` |
| Modal header | `20px` |
| Modal body | `25px` |
| Badge | `6-14px` |

### Margin & Gap

| Uso | Valore |
|-----|--------|
| Tra campi form | `20px` |
| Card title bottom | `14px` |
| Grid gap | `18-20px` |
| Mobile gap | `16px` |

### Border

| Uso | Valore |
|-----|--------|
| Input/Card | `2px` |
| Border-left accent | `3-4px` |
| High contrast | `3px` |

### Border Radius

| Elemento | Valore |
|----------|--------|
| Input | `6px` |
| Card | `10px` |
| Badge/Pill | `20px` |

## Modal e Card

### Modal Container (da BO HSE)

```css
background: #fff;
border: 2px solid #dee2e6;
border-radius: 10px;
max-width: 700px;
max-height: 80vh;
box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
```

### Modal Header

```css
background: linear-gradient(135deg, #2196f3, #1976d2);
color: #fff;
padding: 20px;
font-size: 20px;
font-weight: 700;
```

### Card Stile (assignments-summary)

```css
background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
border: 2px solid #e9ecef;
border-radius: 10px;
padding: 22px;
line-height: 1.8;
box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
```

### Card Title

```css
font-size: 19px;
font-weight: 700;
color: #0066a2;
line-height: 1.4;
letter-spacing: 0.3px;
margin-bottom: 14px;
```

### Card Info

```css
font-size: 15px;
color: #495057;
line-height: 1.8;
margin-bottom: 8px;
```

## Messaggi di Validazione

### Errore

```css
/* Container */
display: flex;
align-items: flex-start;
gap: 8px;
font-size: 14px;
color: #721c24;
background-color: #f8d7da;
border: 1px solid #f5c6cb;
border-radius: 6px;
padding: 12px;
line-height: 1.6;

/* Icona */
::before {
    content: "⚠️";
    font-size: 16px;
}
```

### Success

```css
color: #155724;
background-color: #d4edda;
border: 1px solid #c3e6cb;

::before {
    content: "✓";
    font-weight: 700;
}
```

### Warning

```css
color: #856404;
background-color: #fff3cd;
border: 1px solid #ffeaa7;

::before {
    content: "⚡";
}
```

## Best Practices

### 1. Font Size Input

✅ **DO**: Usare sempre `font-size: 16px` per input
```css
input { font-size: 16px; }
```

❌ **DON'T**: Usare font-size < 16px (causa zoom su iOS)
```css
input { font-size: 14px; } /* NO! */
```

### 2. Touch Targets

✅ **DO**: Rispettare dimensioni minime 44x44px
```css
button {
    min-height: 44px;
    min-width: 44px;
}
```

❌ **DON'T**: Bottoni troppo piccoli
```css
button {
    padding: 4px 8px; } /* NO! */
```

### 3. Focus States

✅ **DO**: Focus visibile senza layout shift
```css
input:focus {
    box-shadow: 0 0 0 3px rgba(0, 102, 162, 0.2);
    outline: 2px solid #0066a2;
}
```

❌ **DON'T**: Focus con scale (causa layout shift)
```css
input:focus {
    transform: scale(1.1); /* NO! */
}
```

## Testing

### Accessibility Testing
- Lighthouse (punteggio >= 90)
- axe DevTools (0 errori critici)
- Keyboard navigation
- Screen reader (NVDA/JAWS/VoiceOver)
- Color contrast checker

### Responsive Testing
- Desktop: 1920x1080, 1366x768
- Tablet: 768x1024
- Mobile: 375x667 (iPhone SE), 414x896 (iPhone 11)

## Riferimenti

- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [Apple Human Interface Guidelines](https://developer.apple.com/design/human-interface-guidelines/ios/visual-design/adaptivity-and-layout/)
- [Material Design - Accessibility](https://material.io/design/usability/accessibility.html)
- [WebAIM - Contrast Checker](https://webaim.org/resources/contrastchecker/)

## Changelog

### 2025-10-24 - Versione Iniziale
- Creazione linee guida basate su BO HSE
- Estrazione valori da #mezzo-details-modal e #assignments-summary
- Definizione palette colori ad alto contrasto
- Standardizzazione tipografia (16-20px, line-height 1.5-1.8)
- Ottimizzazione form (44px touch targets, font 16px)
- Miglioramenti accessibilità (focus, reduced-motion, high-contrast)
- Responsive design (grid 1 colonna < 768px)

---

**Autore**: Team Cogei HSE  
**Ultima modifica**: 2025-10-24  
**Versione**: 1.0

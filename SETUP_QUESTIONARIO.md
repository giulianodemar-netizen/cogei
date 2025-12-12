# Setup Questionario Pubblico - Guida Rapida

## 📁 File Standalone

Il sistema questionari ora utilizza un **file PHP standalone** (`questionario-pubblico.php`) che funziona indipendentemente da WordPress, evitando conflitti con temi e shortcode.

## 🚀 Installazione (3 Passi)

### 1. Crea la cartella sul server

Accedi via FTP o File Manager e crea la cartella:

```
/public_html/questionario/
```

Oppure se WordPress è in una sottocartella:

```
/public_html/cogei/questionario/
```

### 2. Carica il file

1. Scarica `questionario-pubblico.php` dal repository
2. **Rinominalo** in `index.php`
3. Caricalo nella cartella `/questionario/`

Struttura finale:
```
/public_html/
├── questionario/
│   └── index.php  (il file questionario-pubblico.php rinominato)
├── wp-admin/
├── wp-content/
└── ...altri file WordPress...
```

### 3. Verifica il percorso wp-load.php

Apri il file `index.php` e controlla la riga 13:

```php
$wp_load_path = '../wp-load.php';
```

**Se WordPress è nella root:** lascia così  
**Se WordPress è in una sottocartella** (es: `/cogei/`): cambia in:
```php
$wp_load_path = '../cogei/wp-load.php';
```

## ✅ Verifica Installazione

### Test URL

Visita: `https://tuosito.com/questionario/`

Dovresti vedere:
- Un messaggio "Token Mancante" (è normale senza token)
- Nessun errore PHP
- La pagina si carica correttamente

### Test con Token

1. Vai nel backoffice questionari
2. Invia un questionario di test
3. Copia il link dalla colonna "Link Questionario"
4. Apri il link in una nuova finestra
5. Dovresti vedere il questionario completo

## 🔧 Troubleshooting

### Errore "Impossibile caricare WordPress"

**Problema:** Il file non trova wp-load.php  
**Soluzione:** Controlla e aggiusta il percorso alla riga 13

### Pagina bianca o errore 500

**Problema:** Errore PHP  
**Soluzione:** 
1. Attiva debug WordPress in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
2. Controlla il file `/wp-content/debug.log`

### Link porta a 404

**Problema:** La cartella non esiste o il file non si chiama index.php  
**Soluzione:**
1. Verifica che la cartella `/questionario/` esista
2. Verifica che il file si chiami `index.php` (non `questionario-pubblico.php`)

## 📝 Come Funziona

### Flusso Operativo

1. **Admin invia questionario**
   - Sistema genera token univoco
   - Crea URL: `https://sito.com/questionario/?boq_token=ABC123`
   - Invia email all'ispettore con il link

2. **Ispettore clicca link**
   - Browser carica `/questionario/index.php`
   - File PHP si connette al database WordPress
   - Legge questionario e domande

3. **Compilazione**
   - Ispettore risponde alle domande
   - Invia form

4. **Risultato**
   - Sistema calcola punteggio
   - Mostra valutazione (Eccellente, Molto Buono, ecc.)
   - Salva risposte nel database

## 🎨 Personalizzazione

### Modificare Colori

Nel file `index.php`, cerca la sezione `<style>` (riga ~54) e modifica:

```css
/* Gradient header - cambia i colori qui */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

### Modificare Logo o Testo

Cerca la sezione `.header` (riga ~110) e personalizza il contenuto HTML.

## 📊 URL Generati Automaticamente

Il sistema `bo-questionnaires.php` ora genera automaticamente URL nel formato:

```
https://gest.gruppoconsam.com/questionario/?boq_token=XXXXX
```

Questo vale per:
- Email inviate agli ispettori
- Link nella tabella "Storico Invii"
- Export CSV

## 🔒 Sicurezza

Il file standalone mantiene tutte le misure di sicurezza:

- ✅ Validazione token univoci
- ✅ Nonce verification per form submit
- ✅ Input sanitization
- ✅ Output escaping
- ✅ Prepared statements per query database
- ✅ Prevenzione re-submission

## 📱 Responsive Design

Il questionario è completamente responsive e funziona su:
- Desktop
- Tablet
- Mobile

## 🆘 Supporto

In caso di problemi:

1. Verifica i 3 passi di installazione
2. Controlla il log di debug WordPress
3. Verifica i permessi della cartella (755)
4. Verifica che PHP sia versione 7.0+

## ✨ Vantaggi Soluzione Standalone

✅ **Nessun conflitto** con temi WordPress  
✅ **Nessun problema** con shortcode  
✅ **Design personalizzato** senza interferenze  
✅ **Performance migliori** (meno overhead WordPress)  
✅ **Facile debugging** (un solo file)  
✅ **Controllo completo** su layout e stile  

---

**Versione:** 1.0  
**Ultimo aggiornamento:** Dicembre 2024  
**Sistema:** Cogei Questionnaires Management

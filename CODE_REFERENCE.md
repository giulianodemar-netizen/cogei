# Code Changes Reference

## Quick Reference for HSE Bug Fixes

### Issue 1: Button Click Registration (FRONT HSE)

#### Location: Line 2590
**Before:**
```php
echo '<button type="submit" name="hse_cantiere_submit_parte_b" value="' . $hse_cantiere['id'] . '" class="hse_submit_parte_b_btn" style="background: #2196f3; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; transition: background 0.3s;">🏗️ Salva per ' . htmlspecialchars($hse_cantiere['nome']) . '</button>';
```

**After:**
```php
echo '<button type="submit" name="hse_cantiere_submit_parte_b" value="' . $hse_cantiere['id'] . '" class="hse_submit_parte_b_btn" id="hse_submit_btn_' . $hse_cantiere['id'] . '" style="background: #2196f3; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; transition: background 0.3s; position: relative; z-index: 10;" onmousedown="console.log(\'Button clicked for cantiere ' . $hse_cantiere['id'] . '\');">🏗️ Salva per ' . htmlspecialchars($hse_cantiere['nome']) . '</button>';
```

**Changes:**
- Added: `id="hse_submit_btn_{id}"`
- Added: `z-index: 10`
- Added: `onmousedown` debug logging

---

#### Location: Lines 5060-5068
**Added Event Handler:**
```javascript
// Previeni che i click sui pulsanti submit triggherino l'accordion
document.addEventListener('click', function(e) {
    // Se il click è su un pulsante submit, non fare nulla
    if (e.target.closest('.hse_submit_parte_b_btn') || e.target.closest('.hse_submit_parte_a_btn')) {
        console.log('✅ Click su pulsante submit registrato:', e.target);
        return; // Non propagare l'evento
    }
    
    if (e.target.closest('.hse_accordion_header') || 
        e.target.closest('.hse_persona_accordion_header') || 
        e.target.closest('.hse_cantiere_accordion_header')) {
        setTimeout(hse_saveAccordionStates, 400);
    }
});
```

**Purpose:** Prevents accordion clicks from interfering with button clicks

---

#### Location: Lines 3915-3957
**Added Debug Logging to Accordion:**
```javascript
function hse_toggleCantiereAccordion(cantiereId) {
    console.log('🔧 Toggling accordion for cantiere:', cantiereId);
    
    // ... existing code ...
    
    if (content.style.display === 'none' || content.style.display === '') {
        console.log('📂 Opening accordion for cantiere:', cantiereId);
        // ... open accordion code ...
        console.log('✅ Accordion opened for cantiere:', cantiereId);
    } else {
        console.log('📁 Closing accordion for cantiere:', cantiereId);
        // ... close accordion code ...
        console.log('✅ Accordion closed for cantiere:', cantiereId);
    }
}
```

**Purpose:** Provides debugging feedback for accordion state changes

---

### Issue 2: Automezzi/Attrezzi Display (BO HSE)

#### Location: Lines 1636-1658
**Added Data Retrieval:**
```php
// 🚛 NUOVO: Recupera TUTTI gli automezzi dell'utente (assegnati e non assegnati)
$richiesta = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d", $user_id
), ARRAY_A);

$all_automezzi = array();
if ($richiesta) {
    $all_automezzi = getAutomezziByRichiestaId($richiesta['id']);
}

$automezzi_assegnazioni = getAutomezziAssegnazioniByUser($user_id);
$automezzi_grouped = groupAutomezziAssegnazioniByAutomezzo($automezzi_assegnazioni);

// 🔧 NUOVO: Recupera TUTTI gli attrezzi dell'utente (assegnati e non assegnati)
$all_attrezzi = array();
if ($richiesta) {
    $all_attrezzi = getAttrezziByRichiestaId($richiesta['id']);
}

$attrezzi_assegnazioni = getAttrezziAssegnazioniByUser($user_id);
$attrezzi_grouped = groupAttrezziAssegnazioniByAttrezzo($attrezzi_assegnazioni);
```

**Purpose:** Fetches complete list of automezzi/attrezzi (not just assigned)

---

#### Location: Lines 1754-1788
**Automezzi Display Logic:**
```php
// 🚛 NUOVO: Automezzi (assegnati e non assegnati con distinzione visiva)
echo '<td style="padding: 8px; border: 1px solid #ddd; font-size: 10px;">';

// Mostra automezzi assegnati
if (!empty($automezzi_grouped)) {
    foreach ($automezzi_grouped as $automezzo) {
        echo '<div style="background: #fff3cd; color: #856404; padding: 3px 6px; margin: 2px 0; border-radius: 4px; border-left: 3px solid #ffc107;">';
        echo '<strong>🚛 ' . htmlspecialchars($automezzo['descrizione_automezzo']) . '</strong>';
        echo ' <span style="background: #28a745; color: white; padding: 1px 4px; border-radius: 3px; font-size: 8px; margin-left: 3px;">✓ Assegnato</span><br>';
        echo '<span style="font-size: 9px; color: #6c757d;">📋 ' . htmlspecialchars($automezzo['targa']) . '</span><br>';
        foreach ($automezzo['cantieri'] as $cantiere_assegnazione) {
            echo '<span style="font-size: 9px; color: #6c757d;">├ ' . htmlspecialchars($cantiere_assegnazione['cantiere_nome']) . '</span><br>';
        }
        echo '</div>';
    }
}

// Mostra automezzi NON assegnati con stile diverso
if (!empty($all_automezzi)) {
    $automezzi_assegnati_ids = array();
    foreach ($automezzi_grouped as $automezzo) {
        $automezzi_assegnati_ids[] = $automezzo['automezzo_id'];
    }
    
    foreach ($all_automezzi as $automezzo) {
        if (!in_array($automezzo['id'], $automezzi_assegnati_ids)) {
            echo '<div style="background: #f8f9fa; color: #6c757d; padding: 3px 6px; margin: 2px 0; border-radius: 4px; border-left: 3px solid #dee2e6; opacity: 0.7;">';
            echo '<strong>🚛 ' . htmlspecialchars($automezzo['descrizione_automezzo']) . '</strong>';
            echo ' <span style="background: #6c757d; color: white; padding: 1px 4px; border-radius: 3px; font-size: 8px; margin-left: 3px;">⚪ Non assegnato</span><br>';
            echo '<span style="font-size: 9px; color: #6c757d;">📋 ' . htmlspecialchars($automezzo['targa']) . '</span><br>';
            echo '<span style="font-size: 9px; color: #adb5bd; font-style: italic;">Nessun cantiere</span>';
            echo '</div>';
        }
    }
}

if (empty($automezzi_grouped) && empty($all_automezzi)) {
    echo '<span style="color: #6c757d; font-style: italic;">Nessun automezzo</span>';
}
echo '</td>';
```

**Purpose:** Shows both assigned (yellow) and unassigned (gray) automezzi

---

#### Location: Lines 1791-1839
**Attrezzi Display Logic:**
```php
// 🔧 NUOVO: Attrezzi (assegnati e non assegnati con distinzione visiva)
echo '<td style="padding: 8px; border: 1px solid #ddd; font-size: 10px;">';

// Mostra attrezzi assegnati
if (!empty($attrezzi_grouped)) {
    foreach ($attrezzi_grouped as $attrezzo) {
        echo '<div style="background: #e3f2fd; color: #1565c0; padding: 3px 6px; margin: 2px 0; border-radius: 4px; border-left: 3px solid #2196f3;">';
        echo '<strong>🔧 ' . htmlspecialchars($attrezzo['descrizione_attrezzo']) . '</strong>';
        echo ' <span style="background: #28a745; color: white; padding: 1px 4px; border-radius: 3px; font-size: 8px; margin-left: 3px;">✓ Assegnato</span><br>';
        if ($attrezzo['data_revisione']) {
            echo '<span style="font-size: 9px; color: #6c757d;">📅 Rev: ' . date('d/m/Y', strtotime($attrezzo['data_revisione'])) . '</span><br>';
        }
        foreach ($attrezzo['cantieri'] as $cantiere_assegnazione) {
            echo '<span style="font-size: 9px; color: #6c757d;">├ ' . htmlspecialchars($cantiere_assegnazione['cantiere_nome']) . '</span><br>';
        }
        echo '</div>';
    }
}

// Mostra attrezzi NON assegnati con stile diverso
if (!empty($all_attrezzi)) {
    $attrezzi_assegnati_ids = array();
    foreach ($attrezzi_grouped as $attrezzo) {
        $attrezzi_assegnati_ids[] = $attrezzo['attrezzo_id'];
    }
    
    foreach ($all_attrezzi as $attrezzo) {
        if (!in_array($attrezzo['id'], $attrezzi_assegnati_ids)) {
            echo '<div style="background: #f8f9fa; color: #6c757d; padding: 3px 6px; margin: 2px 0; border-radius: 4px; border-left: 3px solid #dee2e6; opacity: 0.7;">';
            echo '<strong>🔧 ' . htmlspecialchars($attrezzo['descrizione_attrezzo']) . '</strong>';
            echo ' <span style="background: #6c757d; color: white; padding: 1px 4px; border-radius: 3px; font-size: 8px; margin-left: 3px;">⚪ Non assegnato</span><br>';
            if ($attrezzo['data_revisione']) {
                echo '<span style="font-size: 9px; color: #6c757d;">📅 Rev: ' . date('d/m/Y', strtotime($attrezzo['data_revisione'])) . '</span><br>';
            }
            echo '<span style="font-size: 9px; color: #adb5bd; font-style: italic;">Nessun cantiere</span>';
            echo '</div>';
        }
    }
}

if (empty($attrezzi_grouped) && empty($all_attrezzi)) {
    echo '<span style="color: #6c757d; font-style: italic;">Nessun attrezzo</span>';
}
echo '</td>';
```

**Purpose:** Shows both assigned (blue) and unassigned (gray) attrezzi

---

## CSS Styles Reference

### Assigned Automezzi
```css
background: #fff3cd;      /* Soft yellow */
color: #856404;           /* Dark yellow-brown */
border-left: 3px solid #ffc107;  /* Amber */
```

### Assigned Attrezzi
```css
background: #e3f2fd;      /* Light blue */
color: #1565c0;           /* Dark blue */
border-left: 3px solid #2196f3;  /* Blue */
```

### Unassigned Items (Both)
```css
background: #f8f9fa;      /* Light gray */
color: #6c757d;           /* Dark gray */
border-left: 3px solid #dee2e6;  /* Medium gray */
opacity: 0.7;             /* Slightly faded */
```

### Badge Styles

**Assigned Badge:**
```css
background: #28a745;      /* Green */
color: white;
padding: 1px 4px;
border-radius: 3px;
font-size: 8px;
/* Text: "✓ Assegnato" */
```

**Unassigned Badge:**
```css
background: #6c757d;      /* Gray */
color: white;
padding: 1px 4px;
border-radius: 3px;
font-size: 8px;
/* Text: "⚪ Non assegnato" */
```

---

## Database Queries Used

### Fetch All Automezzi
```php
$richiesta = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d", 
    $user_id
), ARRAY_A);

$all_automezzi = getAutomezziByRichiestaId($richiesta['id']);
```

### Fetch All Attrezzi
```php
$all_attrezzi = getAttrezziByRichiestaId($richiesta['id']);
```

### Check Assignment Status
```php
// Get IDs of assigned items
$automezzi_assegnati_ids = array();
foreach ($automezzi_grouped as $automezzo) {
    $automezzi_assegnati_ids[] = $automezzo['automezzo_id'];
}

// Check if item is in assigned list
if (!in_array($automezzo['id'], $automezzi_assegnati_ids)) {
    // Item is unassigned
}
```

---

## Testing Commands

### Console Debug Messages to Look For

**Front Office:**
```
✅ Click su pulsante submit registrato: [button element]
🔧 Toggling accordion for cantiere: [ID]
📂 Opening accordion for cantiere: [ID]
✅ Accordion opened for cantiere: [ID]
Button clicked for cantiere [ID]
```

### Browser Dev Tools
1. Press F12 to open developer tools
2. Go to Console tab
3. Perform actions (click button, toggle accordion)
4. Watch for debug messages

---

## Rollback Instructions

If you need to revert changes:

### Revert Button Changes (FRONT HSE Line 2590)
```php
// Remove: id="hse_submit_btn_{id}", z-index: 10, onmousedown
// Keep: Original button structure
```

### Revert Event Handler (FRONT HSE Lines 5060-5068)
```javascript
// Remove the new event handler section
// Keep only the original accordion state saving
```

### Revert Display Logic (BO HSE Lines 1754-1839)
```php
// Remove: all_automezzi/all_attrezzi fetching
// Remove: Unassigned items display logic
// Keep: Original assigned-only display
```

---

## Performance Notes

- New queries: 2 additional per user (automezzi + attrezzi)
- Impact: Minimal - queries are simple SELECT by user_id
- Caching: Consider adding if many users
- No indexes needed - existing keys are sufficient

---

## Maintenance

### To Remove Debug Logging

**FRONT HSE Line 2590:**
```php
// Remove onmousedown attribute
onmousedown="console.log(...)"
```

**FRONT HSE Lines 3915-3957:**
```javascript
// Remove all console.log() statements
```

**FRONT HSE Lines 5060-5068:**
```javascript
// Remove console.log() statement
console.log('✅ Click su pulsante submit registrato:', e.target);
```

---

End of Code Reference

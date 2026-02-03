<?php
/**
 * Script di Migrazione: Popola Tabella Punteggi Questionari
 * 
 * Questo script migra i punteggi esistenti dalla logica di calcolo dinamico
 * alla nuova tabella cogei_questionnaire_scores.
 * 
 * IMPORTANTE: Eseguire questo script UNA SOLA VOLTA dopo aver applicato le modifiche al codice.
 * 
 * UTILIZZO:
 * 1. Caricare questo file sul server nella stessa cartella di bo-questionnaires.php
 * 2. Eseguire: php migrate_questionnaire_scores.php
 *    OPPURE accedere via browser: https://tuo-sito.com/migrate_questionnaire_scores.php
 * 
 * @version 1.0
 * @author Cogei System
 */

// Determina se eseguito da CLI o web
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    // Esecuzione da web - aggiungi protezione
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migrazione Punteggi</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{background:#efe;border:1px solid #cfc;padding:15px;border-radius:5px;margin:10px 0;}";
    echo ".error{background:#fee;border:1px solid #fcc;padding:15px;border-radius:5px;margin:10px 0;color:#c00;}";
    echo ".info{background:#e7f3ff;border:1px solid #b3d9ff;padding:15px;border-radius:5px;margin:10px 0;}";
    echo "pre{background:#f5f5f5;padding:10px;border-radius:3px;overflow:auto;}</style></head><body>";
    echo "<h1>🔄 Migrazione Punteggi Questionari</h1>";
}

// Carica WordPress
$wp_load_paths = [
    __DIR__ . '/wp-load.php',
    __DIR__ . '/../wp-load.php',
    __DIR__ . '/../../wp-load.php',
    dirname(__FILE__) . '/wp-load.php',
    dirname(__FILE__) . '/../wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    $msg = "ERRORE: Impossibile caricare WordPress. Verificare il percorso.\n";
    if ($is_cli) {
        echo $msg;
        exit(1);
    } else {
        echo "<div class='error'>$msg</div></body></html>";
        exit;
    }
}

global $wpdb;

// Output helper
function output($message, $type = 'info') {
    global $is_cli;
    if ($is_cli) {
        echo $message . "\n";
    } else {
        $class = $type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info');
        echo "<div class='$class'>" . nl2br(htmlspecialchars($message)) . "</div>";
    }
}

output("===========================================");
output("MIGRAZIONE PUNTEGGI QUESTIONARI");
output("===========================================");
output("");

// Verifica che la tabella esista
$table_scores = $wpdb->prefix . 'cogei_questionnaire_scores';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_scores'");

if (!$table_exists) {
    output("ERRORE: La tabella $table_scores non esiste.", 'error');
    output("Assicurati di aver caricato il file bo-questionnaires.php aggiornato prima di eseguire questa migrazione.", 'error');
    if (!$is_cli) echo "</body></html>";
    exit(1);
}

output("✓ Tabella $table_scores trovata", 'success');
output("");

// Recupera tutti gli assignment completati
$assignments = $wpdb->get_results("
    SELECT a.id, a.questionnaire_id
    FROM {$wpdb->prefix}cogei_assignments a
    WHERE a.status = 'completed'
    ORDER BY a.id ASC
");

$total = count($assignments);
output("Trovati $total questionari completati da migrare");
output("");

if ($total === 0) {
    output("Nessun questionario da migrare. Operazione completata.", 'success');
    if (!$is_cli) echo "</body></html>";
    exit(0);
}

$migrated = 0;
$skipped = 0;
$errors = 0;

foreach ($assignments as $assignment) {
    $assignment_id = $assignment->id;
    
    // Verifica se il punteggio esiste già
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_scores WHERE assignment_id = %d",
        $assignment_id
    ));
    
    if ($existing) {
        $skipped++;
        if ($is_cli) {
            echo ".";
        }
        continue;
    }
    
    // Calcola il punteggio usando la NUOVA LOGICA
    try {
        // Ottieni tutte le aree del questionario
        $areas = $wpdb->get_results($wpdb->prepare(
            "SELECT id, weight FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d",
            $assignment->questionnaire_id
        ), ARRAY_A);
        
        $total_punteggio = 0;
        $total_peso_effettivo = 0;
        
        foreach ($areas as $area) {
            // Ottieni tutte le risposte per quest'area
            $area_responses = $wpdb->get_results($wpdb->prepare(
                "SELECT r.question_id, r.selected_option_id, o.weight as option_weight, o.is_na
                FROM {$wpdb->prefix}cogei_responses r
                INNER JOIN {$wpdb->prefix}cogei_questions q ON r.question_id = q.id
                INNER JOIN {$wpdb->prefix}cogei_options o ON r.selected_option_id = o.id
                WHERE r.assignment_id = %d AND q.area_id = %d",
                $assignment_id,
                $area['id']
            ), ARRAY_A);
            
            $area_weight = floatval($area['weight']);
            
            foreach ($area_responses as $resp) {
                $is_na = isset($resp['is_na']) && $resp['is_na'] == 1;
                
                if ($is_na) {
                    // Se N.A., contributo è 0 sia al punteggio che al peso effettivo
                    continue;
                }
                
                // Calcola peso massimo per questa domanda
                $max_weight = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(weight) FROM {$wpdb->prefix}cogei_options WHERE question_id = %d",
                    $resp['question_id']
                ));
                $max_weight = $max_weight !== null ? floatval($max_weight) : 1.0;
                
                // Peso Effettivo = peso massimo * peso area
                $peso_effettivo = $max_weight * $area_weight;
                $total_peso_effettivo += $peso_effettivo;
                
                // Punteggio = peso risposta * peso area
                $answer_weight = floatval($resp['option_weight']);
                $punteggio = $answer_weight * $area_weight;
                $total_punteggio += $punteggio;
            }
        }
        
        // Calcola score finale: (somma punteggi / somma pesi effettivi) * 100
        $final_score = ($total_peso_effettivo > 0) 
            ? ($total_punteggio / $total_peso_effettivo) * 100 
            : 0;
        
        // Salva il punteggio
        $result = $wpdb->insert(
            $table_scores,
            [
                'assignment_id' => $assignment_id,
                'final_score' => $final_score,
                'calculated_at' => current_time('mysql')
            ],
            ['%d', '%f', '%s']
        );
        
        if ($result) {
            $migrated++;
            if ($is_cli) {
                echo "+";
            }
        } else {
            $errors++;
            if ($is_cli) {
                echo "E";
            } else {
                output("Errore salvando punteggio per assignment $assignment_id", 'error');
            }
        }
        
    } catch (Exception $e) {
        $errors++;
        if ($is_cli) {
            echo "E";
        } else {
            output("Errore elaborando assignment $assignment_id: " . $e->getMessage(), 'error');
        }
    }
}

if ($is_cli) {
    echo "\n";
}

output("");
output("===========================================");
output("RIEPILOGO MIGRAZIONE");
output("===========================================");
output("Totale questionari:     $total");
output("Migrati con successo:   $migrated", 'success');
output("Già esistenti (saltati): $skipped", 'info');
output("Errori:                 $errors", $errors > 0 ? 'error' : 'info');
output("");

if ($migrated > 0) {
    output("✅ Migrazione completata con successo!", 'success');
    output("I punteggi sono stati salvati nella tabella $table_scores", 'success');
    output("D'ora in poi i punteggi verranno letti da questa tabella e non saranno più ricalcolati.", 'info');
} else if ($skipped === $total) {
    output("ℹ️  Tutti i punteggi erano già stati migrati.", 'info');
} else {
    output("⚠️  La migrazione è stata completata con alcuni errori.", 'error');
}

output("");
output("IMPORTANTE: Dopo aver verificato che tutto funzioni correttamente,");
output("puoi eliminare questo file (migrate_questionnaire_scores.php) dal server.");

if (!$is_cli) {
    echo "</body></html>";
}

exit($errors > 0 ? 1 : 0);

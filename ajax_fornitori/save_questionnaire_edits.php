<?php
/**
 * Endpoint AJAX - Save Questionnaire Edits
 * File: save_questionnaire_edits.php
 * Posizione: cogei/ajax_fornitori/save_questionnaire_edits.php
 * 
 * Salva le modifiche alle risposte di un questionario completato (admin-only)
 */

// Sicurezza e setup WordPress
if (!defined('ABSPATH')) {
    // Prova diversi percorsi per wp-load.php
    $possible_paths = [
        dirname(dirname(__FILE__)) . '/wp-load.php',           // 1 livello sopra (cogei/wp-load.php)
        dirname(__FILE__) . '/../wp-load.php',                 // 1 livello sopra (alternativo)
        $_SERVER['DOCUMENT_ROOT'] . '/cogei/wp-load.php',      // Root + cartella cogei
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'             // Root del server
    ];
    
    $wp_loaded = false;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        die(json_encode([
            'error' => 'WordPress non trovato',
            'debug' => [
                'tried_paths' => $possible_paths,
                'current_file' => __FILE__
            ]
        ]));
    }
}

// Headers per AJAX
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Verifica richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Metodo non consentito']));
}

// Verifica che l'utente sia un amministratore
if (!current_user_can('administrator')) {
    http_response_code(403);
    die(json_encode(['error' => 'Accesso negato. Solo gli amministratori possono modificare i questionari.']));
}

// Recupera parametri
$assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
$responses_json = isset($_POST['responses']) ? $_POST['responses'] : '';

if ($assignment_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'ID assignment non valido']));
}

if (empty($responses_json)) {
    http_response_code(400);
    die(json_encode(['error' => 'Nessuna risposta fornita']));
}

// Decodifica risposte
$responses = json_decode($responses_json, true);
if (!is_array($responses) || empty($responses)) {
    http_response_code(400);
    die(json_encode(['error' => 'Formato risposte non valido']));
}

global $wpdb;

// Verifica che l'assignment esista e sia completato
$assignment = $wpdb->get_row($wpdb->prepare("
    SELECT *
    FROM {$wpdb->prefix}cogei_assignments
    WHERE id = %d AND status = 'completed'
", $assignment_id));

if (!$assignment) {
    http_response_code(404);
    die(json_encode(['error' => 'Assignment non trovato o non completato']));
}

// Inizia transazione per garantire consistenza
$wpdb->query('START TRANSACTION');

try {
    // Per ogni risposta, aggiorna o inserisci
    foreach ($responses as $question_id => $option_id) {
        $question_id = intval($question_id);
        $option_id = intval($option_id);
        
        if ($question_id <= 0 || $option_id <= 0) {
            continue;
        }
        
        // Recupera peso opzione e flag is_na
        $option = $wpdb->get_row($wpdb->prepare(
            "SELECT weight, is_na, question_id FROM {$wpdb->prefix}cogei_options WHERE id = %d",
            $option_id
        ), ARRAY_A);
        
        if (!$option || $option['question_id'] != $question_id) {
            throw new Exception("Opzione non valida per la domanda {$question_id}");
        }
        
        // Se l'opzione è N.A., usa il peso massimo disponibile per questa domanda
        $weight_to_use = floatval($option['weight']);
        if ($option['is_na'] == 1) {
            $max_weight = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(weight) FROM {$wpdb->prefix}cogei_options WHERE question_id = %d",
                $question_id
            ));
            $weight_to_use = $max_weight !== null ? floatval($max_weight) : floatval($option['weight']);
        }
        
        // Controlla se esiste già una risposta
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cogei_responses 
             WHERE assignment_id = %d AND question_id = %d",
            $assignment_id,
            $question_id
        ));
        
        if ($existing) {
            // Aggiorna risposta esistente
            $wpdb->update(
                $wpdb->prefix . 'cogei_responses',
                [
                    'selected_option_id' => $option_id,
                    'computed_score' => $weight_to_use,
                    'answered_at' => current_time('mysql')
                ],
                [
                    'assignment_id' => $assignment_id,
                    'question_id' => $question_id
                ]
            );
        } else {
            // Inserisci nuova risposta
            $wpdb->insert(
                $wpdb->prefix . 'cogei_responses',
                [
                    'assignment_id' => $assignment_id,
                    'question_id' => $question_id,
                    'selected_option_id' => $option_id,
                    'computed_score' => $weight_to_use,
                    'answered_at' => current_time('mysql')
                ]
            );
        }
    }
    
    // Commit transazione
    $wpdb->query('COMMIT');
    
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    http_response_code(500);
    die(json_encode(['error' => 'Errore durante il salvataggio: ' . $e->getMessage()]));
}

// Ricalcola punteggio finale usando la stessa logica del questionario pubblico
// Per ogni area: area_score = (somma pesi domande area) × peso_area
// Punteggio totale = somma di tutti gli area_score × 100

$questionnaire_areas = $wpdb->get_results($wpdb->prepare(
    "SELECT id, weight FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d",
    $assignment->questionnaire_id
), ARRAY_A);

$total_score = 0;

foreach ($questionnaire_areas as $q_area) {
    // Ottieni tutte le risposte per quest'area con informazioni complete
    $area_responses = $wpdb->get_results($wpdb->prepare(
        "SELECT r.question_id, r.selected_option_id, o.weight as option_weight, o.is_na
        FROM {$wpdb->prefix}cogei_responses r
        INNER JOIN {$wpdb->prefix}cogei_questions q ON r.question_id = q.id
        INNER JOIN {$wpdb->prefix}cogei_options o ON r.selected_option_id = o.id
        WHERE r.assignment_id = %d AND q.area_id = %d",
        $assignment_id,
        $q_area['id']
    ), ARRAY_A);
    
    // Somma i pesi delle domande in quest'area
    $area_sum = 0;
    foreach ($area_responses as $resp) {
        $question_weight = floatval($resp['option_weight']);
        
        // Se è N.A., usa il peso massimo per quella domanda
        if (isset($resp['is_na']) && $resp['is_na'] == 1) {
            $max_weight = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(weight) FROM {$wpdb->prefix}cogei_options WHERE question_id = %d",
                $resp['question_id']
            ));
            $question_weight = $max_weight !== null ? floatval($max_weight) : $question_weight;
        }
        
        $area_sum += $question_weight;
    }
    
    // Moltiplica la somma per il peso dell'area
    $area_score = $area_sum * floatval($q_area['weight']);
    $total_score += $area_score;
}

$final_score = $total_score * 100; // Scala a 0-100

// Determina valutazione
if ($final_score >= 85) {
    $evaluation = "Eccellente";
    $eval_class = "excellent";
    $eval_color = "#4caf50";
} elseif ($final_score >= 70) {
    $evaluation = "Molto Buono";
    $eval_class = "very-good";
    $eval_color = "#8bc34a";
} elseif ($final_score >= 55) {
    $evaluation = "Adeguato";
    $eval_class = "adequate";
    $eval_color = "#ffc107";
} elseif ($final_score >= 40) {
    $evaluation = "Critico";
    $eval_class = "critical";
    $eval_color = "#ff9800";
} else {
    $evaluation = "Inadeguato";
    $eval_class = "inadequate";
    $eval_color = "#f44336";
}

// Converti score in stelle
$stars = ($final_score / 100) * 5;
$stars = round($stars * 2) / 2; // Arrotonda a 0.5
$stars = max(0, min(5, $stars)); // Clamp tra 0 e 5

// Restituisci risposta con nuovo punteggio
die(json_encode([
    'success' => true,
    'message' => 'Modifiche salvate con successo!',
    'score' => [
        'value' => number_format($final_score, 2),
        'stars' => $stars,
        'evaluation' => $evaluation,
        'eval_class' => $eval_class,
        'eval_color' => $eval_color
    ]
]));

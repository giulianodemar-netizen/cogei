<?php
/**
 * Endpoint AJAX - Get Editable Questionnaire
 * File: get_editable_questionnaire.php
 * Posizione: cogei/ajax_fornitori/get_editable_questionnaire.php
 * 
 * Recupera un questionario completato con le risposte attuali per la modifica (admin-only)
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

// Verifica nonce per CSRF protection
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'boq_edit_questionnaire')) {
    http_response_code(403);
    die(json_encode(['error' => 'Errore di sicurezza. Token non valido.']));
}

// Recupera parametri
$assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;

if ($assignment_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'ID assignment non valido']));
}

global $wpdb;

// Recupera assignment e questionario
$assignment = $wpdb->get_row($wpdb->prepare("
    SELECT a.*, q.title as questionnaire_title, q.description
    FROM {$wpdb->prefix}cogei_assignments a
    INNER JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
    WHERE a.id = %d AND a.status = 'completed'
", $assignment_id));

if (!$assignment) {
    http_response_code(404);
    die(json_encode(['error' => 'Assignment non trovato o non completato']));
}

// Recupera info fornitore
$hse_user = get_userdata($assignment->target_user_id);
$hse_name = $hse_user ? $hse_user->display_name : 'Fornitore';
$ragione_sociale = get_user_meta($assignment->target_user_id, 'user_registration_rag_soc', true);
$fornitore_display_name = $ragione_sociale ? $ragione_sociale : ($hse_user ? $hse_user->display_name : 'Fornitore');

// Recupera tutte le aree con domande
$areas = $wpdb->get_results($wpdb->prepare("
    SELECT *
    FROM {$wpdb->prefix}cogei_areas
    WHERE questionnaire_id = %d
    ORDER BY sort_order ASC
", $assignment->questionnaire_id));

// Genera HTML del form
$html = '<div style="padding: 0;">';

// Titolo questionario
$html .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px;">';
$html .= '<h3 style="margin: 0 0 10px 0; font-size: 24px;">✏️ Modifica Questionario</h3>';
$html .= '<p style="margin: 0; font-size: 16px; opacity: 0.9;">' . esc_html($assignment->questionnaire_title) . '</p>';
$html .= '</div>';

// Info fornitore
$html .= '<div style="background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin-bottom: 25px; border-radius: 4px;">';
$html .= '<p style="margin: 0; font-size: 16px;"><strong style="color: #667eea;">🏢 Fornitore:</strong> ' . esc_html($fornitore_display_name) . '</p>';
$html .= '</div>';

// Form
$html .= '<form id="boqEditForm" style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">';

foreach ($areas as $area) {
    // Area header
    $html .= '<div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 20px; overflow: hidden;">';
    $html .= '<div style="background: #f8f9fa; padding: 15px; border-bottom: 2px solid #667eea;">';
    $html .= '<div style="font-size: 18px; font-weight: 600; color: #333;">📍 ' . esc_html($area->title) . '</div>';
    $html .= '<div style="color: #666; font-size: 13px; margin-top: 4px;">Peso area: ' . number_format($area->weight, 2) . '</div>';
    $html .= '</div>';
    
    // Recupera domande per questa area
    $questions = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM {$wpdb->prefix}cogei_questions
        WHERE area_id = %d
        ORDER BY sort_order ASC
    ", $area->id));
    
    foreach ($questions as $question) {
        // Recupera risposta corrente
        $current_response = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, o.text as option_text
            FROM {$wpdb->prefix}cogei_responses r
            INNER JOIN {$wpdb->prefix}cogei_options o ON r.selected_option_id = o.id
            WHERE r.assignment_id = %d AND r.question_id = %d
        ", $assignment_id, $question->id));
        
        // Recupera tutte le opzioni
        $options = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}cogei_options
            WHERE question_id = %d
            ORDER BY sort_order ASC
        ", $question->id));
        
        // Question container
        $html .= '<div style="padding: 20px; border-bottom: 1px solid #e9ecef;">';
        $html .= '<div style="color: #212529; font-weight: 500; margin-bottom: 12px; font-size: 15px;">';
        $html .= '❓ ' . esc_html($question->text);
        if ($question->is_required) {
            $html .= ' <span style="color: #c00; font-weight: bold;">*</span>';
        }
        $html .= '</div>';
        
        // Mostra risposta corrente
        if ($current_response) {
            $html .= '<div style="background: #e3f2fd; padding: 8px 12px; border-radius: 4px; margin-bottom: 10px; font-size: 13px;">';
            $html .= '<strong style="color: #03679e;">Risposta attuale:</strong> ' . esc_html($current_response->option_text);
            $html .= '</div>';
        }
        
        // Options
        $html .= '<div style="display: flex; flex-direction: column; gap: 8px;">';
        foreach ($options as $option) {
            $checked = ($current_response && $current_response->selected_option_id == $option->id) ? 'checked' : '';
            $selected_class = $checked ? 'boq-option-selected' : '';
            
            $html .= '<label class="boq-edit-option ' . $selected_class . '" style="display: flex; align-items: center; padding: 10px 12px; border: 2px solid ' . ($checked ? '#667eea' : '#e0e0e0') . '; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: ' . ($checked ? '#f0f3ff' : 'white') . ';">';
            $html .= '<input type="radio" name="question_' . $question->id . '" value="' . $option->id . '" ' . $checked;
            if ($question->is_required) {
                $html .= ' required';
            }
            $html .= ' style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">';
            $html .= '<span style="flex: 1; font-size: 14px;">' . esc_html($option->text);
            if ($option->is_na) {
                $html .= ' <span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; margin-left: 6px;">N.A.</span>';
            }
            $html .= '</span>';
            $html .= '<span style="color: #999; font-size: 12px; margin-left: 8px;">Peso: ' . number_format($option->weight, 2) . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
}

$html .= '</form>';

// Buttons
$html .= '<div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #e0e0e0; display: flex; justify-content: space-between; gap: 10px;">';
$html .= '<button type="button" onclick="boqCloseEditModal()" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s;">✕ Annulla</button>';
$html .= '<button type="button" onclick="boqSaveEdits(' . $assignment_id . ')" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s;">✓ Salva Modifiche</button>';
$html .= '</div>';

$html .= '</div>';

// Aggiungi CSS per gestire la selezione delle opzioni
$html .= '<style>
.boq-edit-option:hover {
    border-color: #667eea !important;
    background: #f8f9ff !important;
}
.boq-option-selected {
    border-color: #667eea !important;
    background: #f0f3ff !important;
}
</style>';

// Aggiungi JavaScript per gestire la selezione visiva
$html .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    const options = document.querySelectorAll(".boq-edit-option");
    options.forEach(option => {
        const radio = option.querySelector("input[type=radio]");
        option.addEventListener("click", function() {
            // Rimuovi selected da tutte le opzioni dello stesso gruppo
            const name = radio.name;
            document.querySelectorAll("input[name=\"" + name + "\"]").forEach(r => {
                const label = r.closest(".boq-edit-option");
                label.classList.remove("boq-option-selected");
                label.style.borderColor = "#e0e0e0";
                label.style.background = "white";
            });
            // Aggiungi selected all\'opzione cliccata
            radio.checked = true;
            option.classList.add("boq-option-selected");
            option.style.borderColor = "#667eea";
            option.style.background = "#f0f3ff";
        });
    });
});
</script>';

// Restituisci risposta
die(json_encode([
    'success' => true,
    'html' => $html
]));

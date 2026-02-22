<?php
/**
 * Endpoint AJAX - Importazione Questionario
 * File: import_questionnaire.php
 * Posizione: cogei/ajax_fornitori/import_questionnaire.php
 *
 * Importa un questionario da un file JSON precedentemente esportato.
 * Accesso riservato agli amministratori.
 */

// Sicurezza e setup WordPress
if (!defined('ABSPATH')) {
    // Prova diversi percorsi per wp-load.php
    $possible_paths = [
        dirname(dirname(__FILE__)) . '/wp-load.php',
        dirname(__FILE__) . '/../wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/cogei/wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
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

// Solo amministratori
if (!current_user_can('administrator')) {
    http_response_code(403);
    die(json_encode(['error' => 'Accesso non autorizzato']));
}

// Verifica richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Metodo non consentito']));
}

// Verifica nonce
$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
if (!wp_verify_nonce($nonce, 'boq_import_questionnaire')) {
    http_response_code(403);
    die(json_encode(['error' => 'Verifica di sicurezza fallita']));
}

// Verifica file caricato
if (empty($_FILES['questionnaire_file']) || $_FILES['questionnaire_file']['error'] !== UPLOAD_ERR_OK) {
    $upload_error = isset($_FILES['questionnaire_file']['error']) ? $_FILES['questionnaire_file']['error'] : -1;
    http_response_code(400);
    die(json_encode(['error' => 'Nessun file caricato o errore nel caricamento (codice: ' . $upload_error . ')']));
}

// Verifica tipo MIME e estensione
$file_tmp  = $_FILES['questionnaire_file']['tmp_name'];
$file_name = $_FILES['questionnaire_file']['name'];
$file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

if ($file_ext !== 'json') {
    http_response_code(400);
    die(json_encode(['error' => 'Formato file non supportato. Caricare un file JSON.']));
}

// Verifica dimensione (max 1 MB)
if ($_FILES['questionnaire_file']['size'] > 1048576) {
    http_response_code(400);
    die(json_encode(['error' => 'Il file supera la dimensione massima consentita (1 MB).']));
}

// Leggi e decodifica il JSON
$json_content = file_get_contents($file_tmp);
if ($json_content === false) {
    http_response_code(500);
    die(json_encode(['error' => 'Impossibile leggere il file caricato.']));
}

$data = json_decode($json_content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(['error' => 'File JSON non valido: ' . json_last_error_msg()]));
}

// Validazione struttura
if (empty($data['cogei_export_version']) || empty($data['questionnaire'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Il file non è un export valido di questionario Cogei.']));
}

$q = $data['questionnaire'];

if (empty($q['title'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Il file di importazione non contiene un titolo valido per il questionario.']));
}

if (!isset($q['areas']) || !is_array($q['areas'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Il file di importazione non contiene aree valide.']));
}

global $wpdb;

// Gestione conflitto di nome: aggiungi suffisso se esiste già un questionario con lo stesso titolo
$title       = sanitize_text_field($q['title']);
$description = isset($q['description']) ? sanitize_textarea_field($q['description']) : '';
$status      = (isset($q['status']) && in_array($q['status'], ['draft', 'published'], true)) ? $q['status'] : 'draft';

$existing_count = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}cogei_questionnaires WHERE title = %s",
    $title
));

if ($existing_count > 0) {
    $title .= ' (importato ' . date('d/m/Y H:i') . ')';
}

// Inizia transazione
$wpdb->query('START TRANSACTION');

$insert_ok = true;
$error_msg = '';
$counts = ['aree' => 0, 'domande' => 0, 'opzioni' => 0];

// Inserisci questionario
$q_insert = $wpdb->insert(
    $wpdb->prefix . 'cogei_questionnaires',
    [
        'title'       => $title,
        'description' => $description,
        'status'      => $status,
        'created_by'  => get_current_user_id(),
    ],
    ['%s', '%s', '%s', '%d']
);

if ($q_insert === false || $wpdb->last_error) {
    $insert_ok = false;
    $error_msg = 'Errore creazione questionario: ' . $wpdb->last_error;
}

$new_questionnaire_id = (int) $wpdb->insert_id;

if ($insert_ok && $new_questionnaire_id <= 0) {
    $insert_ok = false;
    $error_msg = 'Errore creazione questionario: insert_id non valido.';
}

// Inserisci aree, domande e opzioni
if ($insert_ok) {
    foreach ($q['areas'] as $area_idx => $area) {
        if (empty($area['title'])) {
            continue;
        }

        $area_title      = sanitize_text_field($area['title']);
        $area_weight     = isset($area['weight']) ? floatval($area['weight']) : 1.0;
        $area_sort_order = isset($area['sort_order']) ? intval($area['sort_order']) : $area_idx;

        $a_insert = $wpdb->insert(
            $wpdb->prefix . 'cogei_areas',
            [
                'questionnaire_id' => $new_questionnaire_id,
                'title'            => $area_title,
                'weight'           => $area_weight,
                'sort_order'       => $area_sort_order,
            ],
            ['%d', '%s', '%f', '%d']
        );

        if ($a_insert === false || $wpdb->last_error) {
            $insert_ok = false;
            $error_msg = 'Errore creazione area: ' . $wpdb->last_error;
            break;
        }

        $new_area_id = (int) $wpdb->insert_id;
        $counts['aree']++;

        if (!isset($area['questions']) || !is_array($area['questions'])) {
            continue;
        }

        foreach ($area['questions'] as $q_idx => $question) {
            if (empty($question['text'])) {
                continue;
            }

            $q_text       = sanitize_textarea_field($question['text']);
            $q_required   = isset($question['is_required']) ? (int) $question['is_required'] : 1;
            $q_sort_order = isset($question['sort_order']) ? intval($question['sort_order']) : $q_idx;

            $qu_insert = $wpdb->insert(
                $wpdb->prefix . 'cogei_questions',
                [
                    'area_id'     => $new_area_id,
                    'text'        => $q_text,
                    'is_required' => $q_required,
                    'sort_order'  => $q_sort_order,
                ],
                ['%d', '%s', '%d', '%d']
            );

            if ($qu_insert === false || $wpdb->last_error) {
                $insert_ok = false;
                $error_msg = 'Errore creazione domanda: ' . $wpdb->last_error;
                break 2;
            }

            $new_question_id = (int) $wpdb->insert_id;
            $counts['domande']++;

            if (!isset($question['options']) || !is_array($question['options'])) {
                continue;
            }

            foreach ($question['options'] as $o_idx => $option) {
                if (!isset($option['text'])) {
                    continue;
                }

                $o_text       = sanitize_text_field($option['text']);
                $o_weight     = isset($option['weight']) ? floatval($option['weight']) : 0.0;
                $o_sort_order = isset($option['sort_order']) ? intval($option['sort_order']) : $o_idx;
                $o_is_na      = isset($option['is_na']) ? (int) $option['is_na'] : 0;

                $o_insert = $wpdb->insert(
                    $wpdb->prefix . 'cogei_options',
                    [
                        'question_id' => $new_question_id,
                        'text'        => $o_text,
                        'weight'      => $o_weight,
                        'sort_order'  => $o_sort_order,
                        'is_na'       => $o_is_na,
                    ],
                    ['%d', '%s', '%f', '%d', '%d']
                );

                if ($o_insert === false || $wpdb->last_error) {
                    $insert_ok = false;
                    $error_msg = 'Errore creazione opzione: ' . $wpdb->last_error;
                    break 3;
                }

                $counts['opzioni']++;
            }
        }
    }
}

if ($insert_ok) {
    $wpdb->query('COMMIT');
    die(json_encode([
        'success'          => true,
        'questionnaire_id' => $new_questionnaire_id,
        'title'            => $title,
        'message'          => 'Questionario importato con successo (' . $counts['aree'] . ' aree, ' . $counts['domande'] . ' domande, ' . $counts['opzioni'] . ' opzioni).',
        'counts'           => $counts,
    ]));
} else {
    $wpdb->query('ROLLBACK');
    http_response_code(500);
    die(json_encode(['error' => $error_msg ?: 'Errore durante l\'importazione.']));
}

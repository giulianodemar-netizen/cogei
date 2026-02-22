<?php
/**
 * Endpoint AJAX - Esportazione Questionario
 * File: export_questionnaire.php
 * Posizione: cogei/ajax_fornitori/export_questionnaire.php
 *
 * Esporta un questionario completo (aree, domande, opzioni) in formato JSON.
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

// Solo amministratori
if (!current_user_can('administrator')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    die(json_encode(['error' => 'Accesso non autorizzato']));
}

// Verifica richiesta GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    die(json_encode(['error' => 'Metodo non consentito']));
}

// Verifica nonce
$nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
if (!wp_verify_nonce($nonce, 'boq_export_questionnaire')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    die(json_encode(['error' => 'Verifica di sicurezza fallita']));
}

// Recupera parametri
$questionnaire_id = isset($_GET['questionnaire_id']) ? intval($_GET['questionnaire_id']) : 0;

if ($questionnaire_id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    die(json_encode(['error' => 'ID questionario non valido']));
}

global $wpdb;

// Recupera il questionario
$questionnaire = $wpdb->get_row($wpdb->prepare(
    "SELECT id, title, description, status FROM {$wpdb->prefix}cogei_questionnaires WHERE id = %d",
    $questionnaire_id
), ARRAY_A);

if (!$questionnaire) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    die(json_encode(['error' => 'Questionario non trovato']));
}

// Recupera aree con domande e opzioni
$areas = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, weight, sort_order FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d ORDER BY sort_order ASC",
    $questionnaire_id
), ARRAY_A);

$areas_data = [];
foreach ($areas as $area) {
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT id, text, is_required, sort_order FROM {$wpdb->prefix}cogei_questions WHERE area_id = %d ORDER BY sort_order ASC",
        $area['id']
    ), ARRAY_A);

    $questions_data = [];
    foreach ($questions as $question) {
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT text, weight, sort_order, is_na FROM {$wpdb->prefix}cogei_options WHERE question_id = %d ORDER BY sort_order ASC",
            $question['id']
        ), ARRAY_A);

        $questions_data[] = [
            'text'        => $question['text'],
            'is_required' => (int) $question['is_required'],
            'sort_order'  => (int) $question['sort_order'],
            'options'     => array_map(function ($o) {
                return [
                    'text'       => $o['text'],
                    'weight'     => (float) $o['weight'],
                    'sort_order' => (int) $o['sort_order'],
                    'is_na'      => (int) $o['is_na'],
                ];
            }, $options),
        ];
    }

    $areas_data[] = [
        'title'      => $area['title'],
        'weight'     => (float) $area['weight'],
        'sort_order' => (int) $area['sort_order'],
        'questions'  => $questions_data,
    ];
}

// Componi struttura di esportazione
$export = [
    'cogei_export_version' => '1.0',
    'exported_at'          => current_time('c', true),
    'questionnaire'        => [
        'title'       => $questionnaire['title'],
        'description' => $questionnaire['description'],
        'status'      => $questionnaire['status'],
        'areas'       => $areas_data,
    ],
];

$filename = 'questionario_' . sanitize_title($questionnaire['title']) . '_' . date('Y-m-d') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;

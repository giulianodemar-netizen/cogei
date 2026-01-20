<?php
/**
 * Endpoint AJAX - Dettaglio Questionario Completo
 * File: get_questionnaire_details.php
 * Posizione: cogei/ajax_fornitori/get_questionnaire_details.php
 * 
 * Recupera il dettaglio completo di un questionario con tutte le risposte
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
    die(json_encode(['error' => 'Assignment non trovato']));
}

// Recupera tutte le aree con domande e risposte
$areas = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT ar.*
    FROM {$wpdb->prefix}cogei_areas ar
    WHERE ar.questionnaire_id = %d
    ORDER BY ar.sort_order ASC
", $assignment->questionnaire_id));

// Calcola score medio (convert from 0-1 scale to 0-100 scale, excluding N.A. options)
$avg_score = $wpdb->get_var($wpdb->prepare("
    SELECT AVG(r.computed_score) * 100
    FROM {$wpdb->prefix}cogei_responses r
    INNER JOIN {$wpdb->prefix}cogei_options o ON r.selected_option_id = o.id
    WHERE r.assignment_id = %d AND o.is_na = 0
", $assignment_id));

// Funzioni helper
function convertScoreToStars($score) {
    // Score is now on 0-100 scale, convert to 0-5 stars
    $stars = ($score / 100) * 5;
    return round($stars * 2) / 2;
}

function renderStars($stars) {
    $full = floor($stars);
    $half = ($stars - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    
    $html = '<span style="color: #FFD700; font-size: 24px; letter-spacing: 2px;">';
    $html .= str_repeat('★', $full);
    if ($half) {
        $html .= '<span style="color: #FFD700;">☆</span>';
    }
    $html .= '</span>';
    $html .= '<span style="color: #DDD; font-size: 24px; letter-spacing: 2px;">';
    $html .= str_repeat('☆', $empty);
    $html .= '</span>';
    
    return $html;
}

function getEvaluationText($stars) {
    // Convert stars to 0-100 score for consistent thresholds
    $score = ($stars / 5) * 100;
    if ($score >= 85) return 'Eccellente';
    if ($score >= 70) return 'Molto Buono';
    if ($score >= 55) return 'Adeguato';
    if ($score >= 40) return 'Critico';
    return 'Inadeguato';
}

function getEvaluationColor($stars) {
    // Convert stars to 0-100 score for consistent thresholds
    $score = ($stars / 5) * 100;
    if ($score >= 85) return '#4caf50';
    if ($score >= 70) return '#8bc34a';
    if ($score >= 55) return '#ffc107';
    if ($score >= 40) return '#ff9800';
    return '#f44336';
}

// Genera HTML
$stars = convertScoreToStars($avg_score);
$evaluation = getEvaluationText($stars);
$color = getEvaluationColor($stars);

$html = '<div style="padding: 0;">';

// Titolo questionario
$html .= '<div style="background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 4px;">';
$html .= '<h3 style="margin: 0 0 10px 0; color: #333;">📋 ' . esc_html($assignment->questionnaire_title) . '</h3>';
if ($assignment->description) {
    $html .= '<p style="margin: 0; color: #666; font-size: 14px;">' . esc_html($assignment->description) . '</p>';
}
$html .= '</div>';

// Valutazione complessiva
$html .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; text-align: center;">';
$html .= '<div style="font-size: 16px; margin-bottom: 10px; opacity: 0.9;">Valutazione Complessiva</div>';
$html .= '<div style="margin: 15px 0;">' . renderStars($stars) . '</div>';
$html .= '<div style="font-size: 28px; font-weight: 700; margin: 10px 0;">' . number_format($avg_score, 2) . ' / 100</div>';
$html .= '<div style="background: ' . $color . '; display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 16px; font-weight: 600; margin-top: 10px;">' . $evaluation . '</div>';
$html .= '</div>';

// Per ogni area, mostra domande e risposte
foreach ($areas as $area) {
    $html .= '<div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin-bottom: 20px;">';
    $html .= '<div style="background: #f8f9fa; padding: 12px 16px; margin: -20px -20px 20px -20px; border-radius: 6px 6px 0 0; border-bottom: 2px solid #dee2e6;">';
    $html .= '<h4 style="margin: 0; color: #495057; font-size: 16px;">📍 ' . esc_html($area->title) . '</h4>';
    $html .= '<div style="color: #6c757d; font-size: 13px; margin-top: 4px;">Peso area: ' . number_format($area->weight, 2) . '</div>';
    $html .= '</div>';
    
    // Recupera domande per questa area
    $questions = $wpdb->get_results($wpdb->prepare("
        SELECT q.*
        FROM {$wpdb->prefix}cogei_questions q
        WHERE q.area_id = %d
        ORDER BY q.sort_order ASC
    ", $area->id));
    
    foreach ($questions as $question) {
        // Recupera risposta per questa domanda (include is_na flag)
        $response = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, o.text as option_text, o.weight as option_weight, o.is_na
            FROM {$wpdb->prefix}cogei_responses r
            INNER JOIN {$wpdb->prefix}cogei_options o ON r.selected_option_id = o.id
            WHERE r.assignment_id = %d AND r.question_id = %d
        ", $assignment_id, $question->id));
        
        if ($response) {
            $html .= '<div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e9ecef;">';
            $html .= '<div style="color: #212529; font-weight: 500; margin-bottom: 8px; font-size: 15px;">❓ ' . esc_html($question->text) . '</div>';
            
            // Check if this is an N.A. option
            if ($response->is_na == 1) {
                // N.A. response with badge
                $html .= '<div style="margin-left: 24px; margin-bottom: 6px; font-size: 14px;">';
                $html .= '<span style="color: #6c757d;">✓ ' . esc_html($response->option_text) . '</span> ';
                $html .= '<span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 8px;">N.A.</span>';
                $html .= ' <span style="color: #999; font-size: 12px; font-style: italic;">(Esclusa dal calcolo)</span>';
                $html .= '</div>';
            } else {
                // Normal response
                $html .= '<div style="color: #28a745; margin-left: 24px; margin-bottom: 6px; font-size: 14px;">✓ ' . esc_html($response->option_text) . ' <span style="color: #6c757d;">(Peso: ' . number_format($response->option_weight, 2) . ')</span></div>';
                $html .= '<div style="color: #6c757d; margin-left: 24px; font-size: 13px;">Punteggio calcolato: <strong style="color: #495057;">' . number_format($response->computed_score, 3) . '</strong></div>';
            }
            
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
}

$html .= '</div>';

// Restituisci risposta
die(json_encode([
    'success' => true,
    'html' => $html
]));

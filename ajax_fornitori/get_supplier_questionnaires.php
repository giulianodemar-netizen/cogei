<?php
/**
 * Endpoint AJAX - Lista Questionari Fornitore
 * File: get_supplier_questionnaires.php
 * Posizione: cogei/ajax_fornitori/get_supplier_questionnaires.php
 * 
 * Recupera tutti i questionari completati per un fornitore specifico
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
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'ID utente non valido']));
}

// Recupera informazioni utente
$user = get_userdata($user_id);
if (!$user) {
    http_response_code(404);
    die(json_encode(['error' => 'Utente non trovato']));
}

global $wpdb;

// Helper function to calculate score using correct formula
/**
 * IMPORTANTE - COMPORTAMENTO MODIFICATO PER GARANTIRE CONSISTENZA DATI:
 * 
 * Questa funzione USA SEMPRE il campo 'computed_score' memorizzato nella tabella cogei_responses.
 * I punteggi NON vengono MAI ricalcolati dinamicamente, nemmeno se i pesi vengono modificati
 * o il questionario viene eliminato.
 * 
 * Questo garantisce che i punteggi storici rimangano immutati e consistenti nel tempo.
 */
function calculateQuestionnaireScore($assignment_id) {
    global $wpdb;
    
    // Ottieni assignment con snapshot
    $assignment = $wpdb->get_row($wpdb->prepare(
        "SELECT questionnaire_id, questionnaire_snapshot FROM {$wpdb->prefix}cogei_assignments WHERE id = %d",
        $assignment_id
    ), ARRAY_A);
    
    if (!$assignment) {
        return 0;
    }
    
    // Usa lo snapshot se disponibile (questionari compilati dopo il fix)
    if (!empty($assignment['questionnaire_snapshot'])) {
        $snapshot = json_decode($assignment['questionnaire_snapshot'], true);
        if ($snapshot && isset($snapshot['areas'])) {
            $total_score = 0;
            
            foreach ($snapshot['areas'] as $area_data) {
                // USA computed_score MEMORIZZATO
                $area_responses = $wpdb->get_results($wpdb->prepare(
                    "SELECT r.computed_score
                    FROM {$wpdb->prefix}cogei_responses r
                    INNER JOIN {$wpdb->prefix}cogei_questions q ON r.question_id = q.id
                    WHERE r.assignment_id = %d AND q.area_id = %d",
                    $assignment_id,
                    $area_data['id']
                ), ARRAY_A);
                
                // Somma i punteggi memorizzati
                $area_sum = 0;
                foreach ($area_responses as $resp) {
                    $area_sum += floatval($resp['computed_score']);
                }
                
                // Usa il peso dell'area DALLO SNAPSHOT
                $area_score = $area_sum * floatval($area_data['weight']);
                $total_score += $area_score;
            }
            
            return $total_score * 100;
        }
    }
    
    // FALLBACK per questionari compilati PRIMA del fix
    $areas = $wpdb->get_results($wpdb->prepare(
        "SELECT id, weight FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d",
        $assignment['questionnaire_id']
    ), ARRAY_A);
    
    $total_score = 0;
    
    foreach ($areas as $area) {
        // USA computed_score MEMORIZZATO, non ricalcolare
        $area_responses = $wpdb->get_results($wpdb->prepare(
            "SELECT r.computed_score
            FROM {$wpdb->prefix}cogei_responses r
            INNER JOIN {$wpdb->prefix}cogei_questions q ON r.question_id = q.id
            WHERE r.assignment_id = %d AND q.area_id = %d",
            $assignment_id,
            $area['id']
        ), ARRAY_A);
        
        $area_sum = 0;
        foreach ($area_responses as $resp) {
            $area_sum += floatval($resp['computed_score']);
        }
        
        $area_score = $area_sum * floatval($area['weight']);
        $total_score += $area_score;
    }
    
    return $total_score * 100;
}

// Query per recuperare tutti i questionari completati del fornitore
$assignments = $wpdb->get_results($wpdb->prepare("
    SELECT 
        a.id as assignment_id,
        a.sent_at,
        a.questionnaire_id,
        q.title as questionnaire_title,
        q.description as questionnaire_description,
        (SELECT MAX(r2.answered_at)
         FROM {$wpdb->prefix}cogei_responses r2
         WHERE r2.assignment_id = a.id) as completion_date
    FROM {$wpdb->prefix}cogei_assignments a
    INNER JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
    WHERE a.target_user_id = %d 
      AND a.status = 'completed'
      AND EXISTS (
          SELECT 1 
          FROM {$wpdb->prefix}cogei_responses r3 
          WHERE r3.assignment_id = a.id
      )
    ORDER BY a.sent_at DESC
", $user_id));

// Calcola score per ogni assignment
foreach ($assignments as $assignment) {
    $assignment->avg_score = calculateQuestionnaireScore($assignment->assignment_id);
}

if (empty($assignments)) {
    die(json_encode([
        'success' => true,
        'html' => '<p style="text-align: center; padding: 40px; color: #666;">Nessun questionario completato trovato per questo fornitore.</p>'
    ]));
}

// Funzione per convertire score in stelle
function convertScoreToStars($score) {
    $stars = ($score / 100) * 5;
    return round($stars * 2) / 2; // Arrotonda a 0.5
}

// Funzione per renderizzare stelle
function renderStars($stars) {
    // Ensure stars is in valid range 0-5
    $stars = max(0, min(5, $stars));
    
    $full = floor($stars);
    $half = ($stars - $full) >= 0.5 ? 1 : 0;
    $empty = max(0, 5 - $full - $half); // Ensure non-negative
    
    $html = '<span style="color: #FFD700; font-size: 20px; letter-spacing: 2px;">';
    $html .= str_repeat('★', $full);
    if ($half) {
        $html .= '<span style="color: #FFD700;">☆</span>';
    }
    $html .= '</span>';
    $html .= '<span style="color: #DDD; font-size: 20px; letter-spacing: 2px;">';
    $html .= str_repeat('☆', $empty);
    $html .= '</span>';
    
    return $html;
}

// Funzione per ottenere valutazione testuale
function getEvaluationText($stars) {
    $score = ($stars / 5) * 100; // Convert stars to 0-100 score
    if ($score >= 85) return 'Eccellente';
    if ($score >= 70) return 'Molto Buono';
    if ($score >= 55) return 'Adeguato';
    if ($score >= 40) return 'Critico';
    return 'Inadeguato';
}

// Funzione per ottenere colore badge
function getEvaluationColor($stars) {
    $score = ($stars / 5) * 100; // Convert stars to 0-100 score
    if ($score >= 85) return '#4caf50';
    if ($score >= 70) return '#8bc34a';
    if ($score >= 55) return '#ffc107';
    if ($score >= 40) return '#ff9800';
    return '#f44336';
}

// Genera HTML
$html = '<div style="padding: 0;">';

foreach ($assignments as $assignment) {
    $stars = convertScoreToStars($assignment->avg_score);
    $evaluation = getEvaluationText($stars);
    $color = getEvaluationColor($stars);
    $completion_date = $assignment->completion_date ? date('d/m/Y H:i', strtotime($assignment->completion_date)) : '-';
    
    $html .= '<div style="border-bottom: 1px solid #e0e0e0; padding: 20px; margin-bottom: 10px; background: #fff;">';
    $html .= '<div style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 10px;">● ' . esc_html($assignment->questionnaire_title) . '</div>';
    
    // Rating e badge
    $html .= '<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px; flex-wrap: wrap;">';
    $html .= '<div>' . renderStars($stars) . ' <span style="color: #666; font-size: 14px;">(' . number_format($stars, 1) . ')</span></div>';
    $html .= '<div style="background: #f0f0f0; padding: 6px 12px; border-radius: 4px;"><strong style="color: #03679e; font-size: 15px;">' . number_format($assignment->avg_score, 2) . '</strong> <span style="color: #999; font-size: 13px;">/ 100</span></div>';
    $html .= '<span style="background: ' . $color . '; color: white; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;">' . $evaluation . '</span>';
    $html .= '</div>';
    
    // Data e pulsante
    $html .= '<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">';
    $html .= '<div style="color: #666; font-size: 14px;">📅 Completato: ' . $completion_date . '</div>';
    $html .= '<button onclick="boqOpenDetails(' . $assignment->assignment_id . ')" style="background: #2196F3; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 6px;">';
    $html .= '👁️ Vedi Dettaglio</button>';
    $html .= '</div>';
    
    $html .= '</div>';
}

$html .= '</div>';

// Restituisci risposta
die(json_encode([
    'success' => true,
    'html' => $html
]));

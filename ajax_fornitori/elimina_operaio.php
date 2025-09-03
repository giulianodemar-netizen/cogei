<?php
/**
 * File AJAX per eliminazione operaio cantiere - VERSIONE CORRETTA
 * Posizionare in: [ROOT_WORDPRESS]/ajax_fornitori/elimina_operaio.php
 * 
 * CORREZIONI APPLICATE:
 * - Pulisce output buffer per prevenire errori di WordPress
 * - Disabilita visualizzazione errori mantenendo il logging
 * - Imposta header corretti prima del caricamento WordPress
 * - Gestisce correttamente le risposte JSON
 */

// STEP 1: Pulisci qualsiasi output precedente e inizia output buffering
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// STEP 2: Disabilita visualizzazione errori (ma mantieni logging per debug)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE); // Solo errori fatali

// STEP 3: Carica WordPress - rileva automaticamente la posizione
if (file_exists('../wp-load.php')) {
    // WordPress in root o sottocartella standard
    define('WP_USE_THEMES', false);
    require_once('../wp-load.php');
} elseif (file_exists('./wp-load.php')) {
    // Se per caso siamo già nella root WordPress
    define('WP_USE_THEMES', false);
    require_once('./wp-load.php');
} else {
    // Percorso personalizzato - adattare se necessario
    define('WP_USE_THEMES', false);
    require_once(dirname(__FILE__) . '/../wp-load.php');
}

// STEP 4: Pulisci output generato da WordPress e plugin
$wp_output = ob_get_clean();
if (!empty($wp_output)) {
    // Log dell'output "sporco" per debug
    error_log("Output WordPress catturato e rimosso: " . substr($wp_output, 0, 200) . "...");
}

// STEP 5: Funzione helper per rispondere in modo sicuro
function safe_json_response($data, $http_code = 200) {
    // Assicurati che non ci sia output precedente
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Imposta header corretti
    if (!headers_sent()) {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    }
    
    // Output JSON e termina
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// STEP 6: Validazioni iniziali
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_json_response(['success' => false, 'message' => 'Metodo non consentito'], 405);
}

if (!isset($_POST['action']) || $_POST['action'] !== 'elimina_operaio' || !isset($_POST['operaio_id'])) {
    safe_json_response(['success' => false, 'message' => 'Parametri mancanti'], 400);
}

$current_user_id = get_current_user_id();
if (!$current_user_id) {
    safe_json_response(['success' => false, 'message' => 'Utente non autenticato'], 401);
}

if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cantiere_form_' . $current_user_id)) {
    safe_json_response(['success' => false, 'message' => 'Errore di sicurezza'], 403);
}

// STEP 7: Elaborazione richiesta
$operaio_id = intval($_POST['operaio_id']);

if ($operaio_id <= 0) {
    safe_json_response(['success' => false, 'message' => 'ID operaio non valido'], 400);
}

// Log per debug
error_log("=== AJAX ELIMINAZIONE OPERAIO ===");
error_log("Operaio ID: $operaio_id, User ID: $current_user_id");

global $wpdb;

try {
    
    // Verifica che l'operaio appartenga all'utente corrente
    $operaio = $wpdb->get_row($wpdb->prepare("
        SELECT p.*, r.user_id 
        FROM {$wpdb->prefix}cantiere_personale p
        JOIN {$wpdb->prefix}cantiere_richieste r ON p.richiesta_id = r.id
        WHERE p.id = %d
    ", $operaio_id));
    
    if (!$operaio || $operaio->user_id != $current_user_id) {
        error_log("HSE: Operaio non trovato o non autorizzato per utente $current_user_id");
        safe_json_response([
            'success' => false, 
            'message' => 'Operaio non trovato o non autorizzato'
        ]);
    }
    
    // Rimuovi dalle assegnazioni cantieri
    $deleted_assignments = $wpdb->delete(
        $wpdb->prefix . 'cantiere_operai_assegnazioni',
        ['operaio_id' => $operaio_id, 'user_id' => $current_user_id]
    );
    
    error_log("HSE: Rimosse $deleted_assignments assegnazioni cantieri per operaio $operaio_id");
    
    // Rimuovi l'operaio
    $result = $wpdb->delete(
        $wpdb->prefix . 'cantiere_personale',
        ['id' => $operaio_id]
    );
    
    if ($result !== false) {
        // Aggiorna il numero totale di personale
        $remaining_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}cantiere_personale p
            JOIN {$wpdb->prefix}cantiere_richieste r ON p.richiesta_id = r.id
            WHERE r.user_id = %d
        ", $current_user_id));
        
        $wpdb->update(
            $wpdb->prefix . 'cantiere_richieste',
            ['numero_personale' => $remaining_count],
            ['user_id' => $current_user_id]
        );
        
        error_log("HSE: Operaio ID $operaio_id eliminato con successo. Personale rimanente: $remaining_count");
        safe_json_response([
            'success' => true, 
            'message' => 'Operaio eliminato con successo',
            'remaining_count' => $remaining_count
        ]);
    } else {
        error_log("HSE: Errore eliminazione operaio ID $operaio_id: " . $wpdb->last_error);
        safe_json_response([
            'success' => false, 
            'message' => 'Errore durante l\'eliminazione dell\'operaio'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Eccezione durante eliminazione operaio: " . $e->getMessage());
    safe_json_response([
        'success' => false, 
        'message' => 'Errore interno del server'
    ]);
}

// Questo punto non dovrebbe mai essere raggiunto, ma per sicurezza
safe_json_response([
    'success' => false, 
    'message' => 'Errore sconosciuto'
]);
?>
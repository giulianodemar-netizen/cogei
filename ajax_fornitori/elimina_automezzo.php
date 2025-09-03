<?php
/**
 * AJAX - Eliminazione Automezzi con Controlli Cantieri
 * Sistema HSE Cantieri - Cogei
 * VERSIONE MIGLIORATA: Con logging, gestione errori e autorizzazioni migliorate
 */

// Supporto per diversi setup di WordPress
if (!defined('ABSPATH')) {
    // Prova diversi percorsi per wp-config.php
    $wp_config_paths = [
        '../../../../wp-config.php',
        '../../../wp-config.php',
        '../../wp-config.php',
        '../wp-config.php',
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-config.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_config_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        // Log dell'errore
        error_log("ERRORE AUTOMEZZO: Impossibile caricare WordPress. Percorso: " . __FILE__);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Errore di configurazione sistema']);
        exit;
    }
}

// Headers per AJAX
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Log della richiesta per debugging
error_log("AUTOMEZZO DELETION REQUEST: " . print_r($_POST, true));

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $error = ['success' => false, 'message' => 'Metodo non consentito', 'debug' => 'Method: ' . $_SERVER['REQUEST_METHOD']];
    error_log("ERRORE AUTOMEZZO: " . json_encode($error));
    echo json_encode($error);
    exit;
}

// Verifica parametri con logging migliorato
if (!isset($_POST['automezzo_id']) || !isset($_POST['user_id'])) {
    $error = [
        'success' => false, 
        'message' => 'Parametri mancanti',
        'debug' => [
            'automezzo_id_present' => isset($_POST['automezzo_id']),
            'user_id_present' => isset($_POST['user_id']),
            'post_data' => array_keys($_POST)
        ]
    ];
    error_log("ERRORE AUTOMEZZO PARAMETRI: " . json_encode($error));
    echo json_encode($error);
    exit;
}

$automezzo_id = intval($_POST['automezzo_id']);
$user_id = intval($_POST['user_id']);
$force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';

// Log parametri ricevuti
error_log("AUTOMEZZO PARAMETRI: ID={$automezzo_id}, User={$user_id}, Force={$force_delete}");

// Verifica autorizzazioni con controlli migliorati
$current_user_id = get_current_user_id();
if (!$current_user_id) {
    $error = [
        'success' => false, 
        'message' => 'Non autorizzato - sessione scaduta',
        'debug' => 'get_current_user_id() returned: ' . var_export($current_user_id, true)
    ];
    error_log("ERRORE AUTOMEZZO AUTH: " . json_encode($error));
    echo json_encode($error);
    exit;
}

// Controllo corrispondenza utente (l'utente può eliminare solo i propri automezzi o admin)
if ($current_user_id != $user_id && !current_user_can('manage_options')) {
    $error = [
        'success' => false,
        'message' => 'Non autorizzato - utente non corrispondente',
        'debug' => "Current user: {$current_user_id}, Requested user: {$user_id}"
    ];
    error_log("ERRORE AUTOMEZZO AUTH MISMATCH: " . json_encode($error));
    echo json_encode($error);
    exit;
}

// Verifica che l'automezzo appartenga all'utente con controlli migliorati
global $wpdb;

if (!$wpdb) {
    $error = [
        'success' => false,
        'message' => 'Errore database - connessione non disponibile',
        'debug' => 'wpdb object not available'
    ];
    error_log("ERRORE AUTOMEZZO DB: " . json_encode($error));
    echo json_encode($error);
    exit;
}

error_log("AUTOMEZZO: Ricerca automezzo ID={$automezzo_id} per User={$user_id}");

$automezzo = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}cantiere_automezzi WHERE id = %d AND user_id = %d",
    $automezzo_id, $user_id
), ARRAY_A);

// Log risultato query
if ($wpdb->last_error) {
    error_log("ERRORE AUTOMEZZO QUERY: " . $wpdb->last_error);
}

if (!$automezzo) {
    $error = [
        'success' => false,
        'message' => 'Automezzo non trovato o non autorizzato',
        'debug' => [
            'automezzo_id' => $automezzo_id,
            'user_id' => $user_id,
            'db_error' => $wpdb->last_error,
            'query_result' => $automezzo
        ]
    ];
    error_log("ERRORE AUTOMEZZO NOT FOUND: " . json_encode($error));
    echo json_encode($error);
    exit;
}

error_log("AUTOMEZZO TROVATO: " . print_r($automezzo, true));

// Controlla se l'automezzo è assegnato a cantieri attivi
$cantieri_assegnati = $wpdb->get_results($wpdb->prepare("
    SELECT c.nome, c.stato, aa.cantiere_id
    FROM {$wpdb->prefix}cantiere_automezzi_assegnazioni aa
    JOIN {$wpdb->prefix}cantieri c ON aa.cantiere_id = c.id
    WHERE aa.automezzo_id = %d AND aa.user_id = %d
    ORDER BY c.nome
", $automezzo_id, $user_id), ARRAY_A);

$cantieri_attivi = array_filter($cantieri_assegnati, function($c) {
    return $c['stato'] === 'attivo';
});

// Se ci sono cantieri attivi e non è forzata la cancellazione, restituisci warning
if (!empty($cantieri_attivi) && !$force_delete) {
    $cantieri_names = array_map(function($c) { return $c['nome']; }, $cantieri_attivi);
    
    echo json_encode([
        'success' => false,
        'warning' => true,
        'message' => 'Questo automezzo è assegnato a ' . count($cantieri_attivi) . ' cantieri attivi: ' . implode(', ', $cantieri_names) . '. Eliminandolo verrà rimosso da tutti i cantieri. Continuare?',
        'cantieri_count' => count($cantieri_attivi),
        'cantieri_names' => $cantieri_names
    ]);
    exit;
}

// Procedi con l'eliminazione
try {
    $wpdb->query('START TRANSACTION');
    
    // 1. Rimuovi tutte le assegnazioni ai cantieri
    $removed_assignments = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}cantiere_automezzi_assegnazioni WHERE automezzo_id = %d AND user_id = %d",
        $automezzo_id, $user_id
    ));
    
    // 2. Elimina l'automezzo
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'cantiere_automezzi',
        array('id' => $automezzo_id, 'user_id' => $user_id),
        array('%d', '%d')
    );
    
    if ($deleted === false) {
        throw new Exception('Errore durante l\'eliminazione dell\'automezzo');
    }
    
    $wpdb->query('COMMIT');
    
    // Log dell'operazione con più dettagli
    $operation_details = [
        'automezzo_id' => $automezzo_id,
        'user_id' => $user_id,
        'current_user_id' => $current_user_id,
        'descrizione' => $automezzo['descrizione_automezzo'],
        'targa' => $automezzo['targa'],
        'removed_assignments' => $removed_assignments,
        'cantieri_affected' => count($cantieri_assegnati),
        'force_delete' => $force_delete
    ];
    error_log("AUTOMEZZO ELIMINATO SUCCESSFULLY: " . json_encode($operation_details));
    
    echo json_encode([
        'success' => true,
        'message' => 'Automezzo "' . $automezzo['descrizione_automezzo'] . '" (targa: ' . $automezzo['targa'] . ') eliminato con successo.',
        'removed_assignments' => $removed_assignments,
        'cantieri_count' => count($cantieri_assegnati),
        'debug' => [
            'operation_time' => date('Y-m-d H:i:s'),
            'affected_cantieri' => array_column($cantieri_assegnati, 'nome')
        ]
    ]);
    
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    
    $error_details = [
        'automezzo_id' => $automezzo_id,
        'user_id' => $user_id,
        'current_user_id' => $current_user_id,
        'error_message' => $e->getMessage(),
        'error_trace' => $e->getTraceAsString(),
        'db_error' => $wpdb->last_error
    ];
    error_log("ERRORE ELIMINAZIONE AUTOMEZZO: " . json_encode($error_details));
    
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante l\'eliminazione: ' . $e->getMessage(),
        'debug' => [
            'error_time' => date('Y-m-d H:i:s'),
            'db_error' => $wpdb->last_error,
            'error_code' => $e->getCode()
        ]
    ]);
}

// Log fine operazione
error_log("AUTOMEZZO OPERATION COMPLETED: " . json_encode(['automezzo_id' => $automezzo_id, 'timestamp' => time()]));

exit;
?>
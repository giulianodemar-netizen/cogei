<?php
/**
 * AJAX - Eliminazione Attrezzi con Controlli Cantieri
 * Sistema HSE Cantieri - Cogei
 * VERSIONE MIGLIORATA: Con logging, gestione errori e autorizzazioni migliorate
 */

// STEP 3: Carica WordPress - rileva automaticamente la posizione
if (!defined('ABSPATH')) {
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
    
    // Verifica che WordPress sia stato caricato correttamente
    if (!defined('ABSPATH')) {
        // Log dell'errore
        error_log("ERRORE ATTREZZO: Impossibile caricare WordPress. Percorso: " . __FILE__);
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
error_log("ATTREZZO DELETION REQUEST: " . print_r($_POST, true));

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $error = ['success' => false, 'message' => 'Metodo non consentito', 'debug' => 'Method: ' . $_SERVER['REQUEST_METHOD']];
    error_log("ERRORE ATTREZZO: " . json_encode($error));
    echo json_encode($error);
    exit;
}

// Verifica parametri con logging migliorato
if (!isset($_POST['attrezzo_id']) || !isset($_POST['user_id'])) {
    $error = [
        'success' => false, 
        'message' => 'Parametri mancanti',
        'debug' => [
            'attrezzo_id_present' => isset($_POST['attrezzo_id']),
            'user_id_present' => isset($_POST['user_id']),
            'post_data' => array_keys($_POST)
        ]
    ];
    error_log("ERRORE ATTREZZO PARAMETRI: " . json_encode($error));
    echo json_encode($error);
    exit;
}

$attrezzo_id = intval($_POST['attrezzo_id']);
$user_id = intval($_POST['user_id']);
$force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';

// Log parametri ricevuti
error_log("ATTREZZO PARAMETRI: ID={$attrezzo_id}, User={$user_id}, Force={$force_delete}");

// Verifica autorizzazioni con controlli migliorati
$current_user_id = get_current_user_id();
if (!$current_user_id) {
    $error = [
        'success' => false, 
        'message' => 'Non autorizzato - sessione scaduta',
        'debug' => 'get_current_user_id() returned: ' . var_export($current_user_id, true)
    ];
    error_log("ERRORE ATTREZZO AUTH: " . json_encode($error));
    echo json_encode($error);
    exit;
}

// Controllo corrispondenza utente (l'utente può eliminare solo i propri attrezzi o admin)
if ($current_user_id != $user_id && !current_user_can('manage_options')) {
    $error = [
        'success' => false,
        'message' => 'Non autorizzato - utente non corrispondente',
        'debug' => "Current user: {$current_user_id}, Requested user: {$user_id}"
    ];
    error_log("ERRORE ATTREZZO AUTH MISMATCH: " . json_encode($error));
    echo json_encode($error);
    exit;
}

// Verifica che l'attrezzo appartenga all'utente con controlli migliorati
global $wpdb;

if (!$wpdb) {
    $error = [
        'success' => false,
        'message' => 'Errore database - connessione non disponibile',
        'debug' => 'wpdb object not available'
    ];
    error_log("ERRORE ATTREZZO DB: " . json_encode($error));
    echo json_encode($error);
    exit;
}

error_log("ATTREZZO: Ricerca attrezzo ID={$attrezzo_id} per User={$user_id}");

$attrezzo = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}cantiere_attrezzi WHERE id = %d AND user_id = %d",
    $attrezzo_id, $user_id
), ARRAY_A);

// Log risultato query
if ($wpdb->last_error) {
    error_log("ERRORE ATTREZZO QUERY: " . $wpdb->last_error);
}

if (!$attrezzo) {
    $error = [
        'success' => false,
        'message' => 'Attrezzo non trovato o non autorizzato',
        'debug' => [
            'attrezzo_id' => $attrezzo_id,
            'user_id' => $user_id,
            'db_error' => $wpdb->last_error,
            'query_result' => $attrezzo
        ]
    ];
    error_log("ERRORE ATTREZZO NOT FOUND: " . json_encode($error));
    echo json_encode($error);
    exit;
}

error_log("ATTREZZO TROVATO: " . print_r($attrezzo, true));

// Controlla se l'attrezzo è assegnato a cantieri attivi
$cantieri_assegnati = $wpdb->get_results($wpdb->prepare("
    SELECT c.nome, c.stato, at.cantiere_id
    FROM {$wpdb->prefix}cantiere_attrezzi_assegnazioni at
    JOIN {$wpdb->prefix}cantieri c ON at.cantiere_id = c.id
    WHERE at.attrezzo_id = %d AND at.user_id = %d
    ORDER BY c.nome
", $attrezzo_id, $user_id), ARRAY_A);

$cantieri_attivi = array_filter($cantieri_assegnati, function($c) {
    return $c['stato'] === 'attivo';
});

// Se ci sono cantieri attivi e non è forzata la cancellazione, restituisci warning
if (!empty($cantieri_attivi) && !$force_delete) {
    $cantieri_names = array_map(function($c) { return $c['nome']; }, $cantieri_attivi);
    
    echo json_encode([
        'success' => false,
        'warning' => true,
        'message' => 'Questo attrezzo è assegnato a ' . count($cantieri_attivi) . ' cantieri attivi: ' . implode(', ', $cantieri_names) . '. Eliminandolo verrà rimosso da tutti i cantieri. Continuare?',
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
        "DELETE FROM {$wpdb->prefix}cantiere_attrezzi_assegnazioni WHERE attrezzo_id = %d AND user_id = %d",
        $attrezzo_id, $user_id
    ));
    
    // 2. Elimina l'attrezzo
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'cantiere_attrezzi',
        array('id' => $attrezzo_id, 'user_id' => $user_id),
        array('%d', '%d')
    );
    
    if ($deleted === false) {
        throw new Exception('Errore durante l\'eliminazione dell\'attrezzo');
    }
    
    $wpdb->query('COMMIT');
    
    // Log dell'operazione con più dettagli
    $data_revisione_text = $attrezzo['data_revisione'] ? date('d/m/Y', strtotime($attrezzo['data_revisione'])) : 'N/A';
    $operation_details = [
        'attrezzo_id' => $attrezzo_id,
        'user_id' => $user_id,
        'current_user_id' => $current_user_id,
        'descrizione' => $attrezzo['descrizione_attrezzo'],
        'data_revisione' => $data_revisione_text,
        'removed_assignments' => $removed_assignments,
        'cantieri_affected' => count($cantieri_assegnati),
        'force_delete' => $force_delete
    ];
    error_log("ATTREZZO ELIMINATO SUCCESSFULLY: " . json_encode($operation_details));
    
    echo json_encode([
        'success' => true,
        'message' => 'Attrezzo "' . $attrezzo['descrizione_attrezzo'] . '" eliminato con successo.',
        'removed_assignments' => $removed_assignments,
        'cantieri_count' => count($cantieri_assegnati),
        'debug' => [
            'operation_time' => date('Y-m-d H:i:s'),
            'affected_cantieri' => array_column($cantieri_assegnati, 'nome'),
            'data_revisione' => $data_revisione_text
        ]
    ]);
    
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    
    $error_details = [
        'attrezzo_id' => $attrezzo_id,
        'user_id' => $user_id,
        'current_user_id' => $current_user_id,
        'error_message' => $e->getMessage(),
        'error_trace' => $e->getTraceAsString(),
        'db_error' => $wpdb->last_error
    ];
    error_log("ERRORE ELIMINAZIONE ATTREZZO: " . json_encode($error_details));
    
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
error_log("ATTREZZO OPERATION COMPLETED: " . json_encode(['attrezzo_id' => $attrezzo_id, 'timestamp' => time()]));

exit;
?>
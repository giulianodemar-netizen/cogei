<?php
/**
 * AJAX - Eliminazione Automezzi con Controlli Cantieri
 * Sistema HSE Cantieri - Cogei
 */

// Verifica che sia una richiesta AJAX
if (!defined('ABSPATH')) {
    // Se non è WordPress, configura la connessione database
    require_once('../../../../wp-config.php');
}

header('Content-Type: application/json');

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Verifica parametri
if (!isset($_POST['automezzo_id']) || !isset($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
    exit;
}

$automezzo_id = intval($_POST['automezzo_id']);
$user_id = intval($_POST['user_id']);
$force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';

// Verifica autorizzazioni
$current_user_id = get_current_user_id();
if (!$current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

// Verifica che l'automezzo appartenga all'utente
global $wpdb;

$automezzo = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}cantiere_automezzi WHERE id = %d AND user_id = %d",
    $automezzo_id, $user_id
), ARRAY_A);

if (!$automezzo) {
    echo json_encode(['success' => false, 'message' => 'Automezzo non trovato o non autorizzato']);
    exit;
}

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
    
    // Log dell'operazione
    error_log("AUTOMEZZO ELIMINATO - ID: {$automezzo_id} | User: {$user_id} | Descrizione: {$automezzo['descrizione_automezzo']} | Targa: {$automezzo['targa']} | Assegnazioni rimosse: {$removed_assignments}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Automezzo "' . $automezzo['descrizione_automezzo'] . '" (targa: ' . $automezzo['targa'] . ') eliminato con successo.',
        'removed_assignments' => $removed_assignments,
        'cantieri_count' => count($cantieri_assegnati)
    ]);
    
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    
    error_log("ERRORE ELIMINAZIONE AUTOMEZZO - ID: {$automezzo_id} | User: {$user_id} | Errore: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante l\'eliminazione: ' . $e->getMessage()
    ]);
}

exit;
?>
<?php
/**
 * File AJAX per eliminazione file cantiere - VERSIONE CORRETTA + UNILAV + IDONEITÀ
 * Posizionare in: [ROOT_WORDPRESS]/ajax_fornitori/elimina_file.php
 * 
 * CORREZIONI APPLICATE:
 * - Pulisce output buffer per prevenire errori di WordPress
 * - Disabilita visualizzazione errori mantenendo il logging
 * - Imposta header corretti prima del caricamento WordPress
 * - Gestisce correttamente le risposte JSON
 * - AGGIUNTO: Gestione file UNILAV e Idoneità Sanitaria
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

if (!isset($_POST['action']) || $_POST['action'] !== 'elimina_file' || !isset($_POST['file_type'])) {
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
$file_type = sanitize_text_field($_POST['file_type']);
$persona_index = isset($_POST['persona_index']) ? intval($_POST['persona_index']) : null;

// Log per debug
error_log("=== AJAX ELIMINAZIONE FILE ===");
error_log("File type: $file_type, Persona index: $persona_index, User ID: $current_user_id");

global $wpdb;

try {
    
    // ELIMINAZIONE FILE PRINCIPALI (DVR, RCT, POS, MEZZI)
    if (in_array($file_type, ['dvr', 'rct', 'pos', 'mezzi'])) {
        
        $field_name = $file_type . '_file';
        
        // Prima recupera il file esistente per eliminazione fisica (opzionale)
        $existing_file = $wpdb->get_var($wpdb->prepare(
            "SELECT $field_name FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d",
            $current_user_id
        ));
        
        // Aggiorna il database
        $result = $wpdb->update(
            $wpdb->prefix . 'cantiere_richieste',
            [$field_name => ''],
            ['user_id' => $current_user_id]
        );
        
        if ($result !== false) {
            // OPZIONALE: Elimina anche il file fisico
            if (!empty($existing_file) && filter_var($existing_file, FILTER_VALIDATE_URL)) {
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $existing_file);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                    error_log("File fisico eliminato: $file_path");
                }
            }
            
            error_log("File $file_type eliminato con successo per utente $current_user_id");
            safe_json_response([
                'success' => true, 
                'message' => 'File eliminato con successo',
                'file_type' => $file_type
            ]);
        } else {
            error_log("Errore eliminazione file $file_type: " . $wpdb->last_error);
            safe_json_response([
                'success' => false, 
                'message' => 'Errore durante l\'eliminazione del file'
            ]);
        }
        
    }
    // ELIMINAZIONE FILE FORMAZIONE PERSONALE
    elseif (in_array($file_type, ['formazione_antincendio', 'formazione_primo_soccorso', 'formazione_preposti']) && $persona_index !== null) {
        
        $field_name = $file_type . '_file';
        
        // Trova l'ID della richiesta
        $richiesta = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d",
            $current_user_id
        ));
        
        if (!$richiesta) {
            error_log("Richiesta non trovata per utente $current_user_id");
            safe_json_response([
                'success' => false, 
                'message' => 'Richiesta cantiere non trovata'
            ]);
        }
        
        // Trova il record del personale
        $personale_records = $wpdb->get_results($wpdb->prepare(
            "SELECT id, $field_name FROM {$wpdb->prefix}cantiere_personale WHERE richiesta_id = %d ORDER BY id ASC",
            $richiesta->id
        ));
        
        if (!isset($personale_records[$persona_index])) {
            error_log("Persona con indice $persona_index non trovata per richiesta {$richiesta->id}");
            safe_json_response([
                'success' => false, 
                'message' => 'Persona non trovata nell\'elenco'
            ]);
        }
        
        $personale_record = $personale_records[$persona_index];
        $personale_id = $personale_record->id;
        $existing_file = $personale_record->$field_name;
        
        // Aggiorna il database
        $result = $wpdb->update(
            $wpdb->prefix . 'cantiere_personale',
            [$field_name => ''],
            ['id' => $personale_id]
        );
        
        if ($result !== false) {
            // OPZIONALE: Elimina anche il file fisico
            if (!empty($existing_file) && filter_var($existing_file, FILTER_VALIDATE_URL)) {
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $existing_file);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                    error_log("File fisico eliminato: $file_path");
                }
            }
            
            error_log("File formazione $file_type eliminato con successo per persona ID $personale_id");
            safe_json_response([
                'success' => true, 
                'message' => 'File formazione eliminato con successo',
                'file_type' => $file_type,
                'persona_index' => $persona_index
            ]);
        } else {
            error_log("Errore eliminazione formazione $file_type per persona ID $personale_id: " . $wpdb->last_error);
            safe_json_response([
                'success' => false, 
                'message' => 'Errore durante l\'eliminazione del file formazione'
            ]);
        }
        
    }
    // 🚀 NUOVA SEZIONE: ELIMINAZIONE FILE DOCUMENTI PERSONALI (UNILAV E IDONEITÀ SANITARIA)
    elseif (in_array($file_type, ['unilav', 'idoneita_sanitaria']) && $persona_index !== null) {
        
        $field_name = $file_type . '_file';
        
        // Trova l'ID della richiesta
        $richiesta = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d",
            $current_user_id
        ));
        
        if (!$richiesta) {
            error_log("Richiesta non trovata per utente $current_user_id");
            safe_json_response([
                'success' => false, 
                'message' => 'Richiesta cantiere non trovata'
            ]);
        }
        
        // Trova il record del personale
        $personale_records = $wpdb->get_results($wpdb->prepare(
            "SELECT id, $field_name FROM {$wpdb->prefix}cantiere_personale WHERE richiesta_id = %d ORDER BY id ASC",
            $richiesta->id
        ));
        
        if (!isset($personale_records[$persona_index])) {
            error_log("Persona con indice $persona_index non trovata per richiesta {$richiesta->id}");
            safe_json_response([
                'success' => false, 
                'message' => 'Persona non trovata nell\'elenco'
            ]);
        }
        
        $personale_record = $personale_records[$persona_index];
        $personale_id = $personale_record->id;
        $existing_file = $personale_record->$field_name;
        
        // Aggiorna il database
        $result = $wpdb->update(
            $wpdb->prefix . 'cantiere_personale',
            [$field_name => ''],
            ['id' => $personale_id]
        );
        
        if ($result !== false) {
            // OPZIONALE: Elimina anche il file fisico
            if (!empty($existing_file) && filter_var($existing_file, FILTER_VALIDATE_URL)) {
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $existing_file);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                    error_log("File fisico eliminato: $file_path");
                }
            }
            
            error_log("File documento $file_type eliminato con successo per persona ID $personale_id");
            safe_json_response([
                'success' => true, 
                'message' => 'File documento eliminato con successo',
                'file_type' => $file_type,
                'persona_index' => $persona_index
            ]);
        } else {
            error_log("Errore eliminazione documento $file_type per persona ID $personale_id: " . $wpdb->last_error);
            safe_json_response([
                'success' => false, 
                'message' => 'Errore durante l\'eliminazione del file documento'
            ]);
        }
        
    }
    // TIPO FILE NON VALIDO
    else {
        error_log("Tipo file non valido: $file_type (persona_index: $persona_index)");
        safe_json_response([
            'success' => false, 
            'message' => 'Tipo di file non riconosciuto'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Eccezione durante eliminazione file: " . $e->getMessage());
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
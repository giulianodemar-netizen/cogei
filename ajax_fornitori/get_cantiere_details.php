<?php
/**
 * Endpoint AJAX - Dettagli Cantiere (VERSIONE CORRETTA)
 * File: get_cantiere_details.php
 * Posizione: https://cogei.provasiti.it/cogei/ajax_fornitori/get_cantiere_details.php
 */

// IMPORTANT: Aumenta memoria e timeout
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 60);

// Sicurezza e setup WordPress MIGLIORATO
if (!defined('ABSPATH')) {
    // Prova diversi percorsi per wp-load.php
    $possible_paths = [
        dirname(dirname(dirname(__FILE__))) . '/wp-load.php',  // 3 livelli sopra
        dirname(dirname(__FILE__)) . '/wp-load.php',           // 2 livelli sopra  
        dirname(__FILE__) . '/../wp-load.php',                 // 1 livello sopra
        dirname(__FILE__) . '/../../wp-load.php',              // 2 livelli sopra (alternativo)
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',            // Root del server
        $_SERVER['DOCUMENT_ROOT'] . '/cogei/wp-load.php'       // Root + cartella cogei
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
        // Se non trova WordPress, restituisce errore specifico
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        die(json_encode([
            'error' => 'WordPress non trovato',
            'debug' => [
                'tried_paths' => $possible_paths,
                'current_file' => __FILE__,
                'document_root' => $_SERVER['DOCUMENT_ROOT']
            ]
        ]));
    }
}

// Headers per AJAX
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Verifica metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Metodo non consentito. Usare POST.']));
}

// Verifica parametri
if (!isset($_POST['cantiere_id']) || empty($_POST['cantiere_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'ID cantiere mancante. Inviare cantiere_id via POST.']));
}

$cantiere_id = intval($_POST['cantiere_id']);

if ($cantiere_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'ID cantiere non valido. Deve essere un numero > 0.']));
}

// ================== FUNZIONI HELPER (SICURE) ==================

function getCantiereById($cantiere_id) {
    global $wpdb;
    
    // Verifica che la tabella esista
    $table_name = $wpdb->prefix . 'cantieri';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        return false;
    }
    
    return $wpdb->get_row($wpdb->prepare("
        SELECT c.*, COUNT(ca.user_id) as aziende_assegnate
        FROM {$wpdb->prefix}cantieri c
        LEFT JOIN {$wpdb->prefix}cantieri_aziende ca ON c.id = ca.cantiere_id
        WHERE c.id = %d
        GROUP BY c.id
    ", $cantiere_id), ARRAY_A);
}

function getAziendeAssegnateCantiereAjax($cantiere_id) {
    global $wpdb;
    
    // Verifica che le tabelle esistano
    $table_cantieri_aziende = $wpdb->prefix . 'cantieri_aziende';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_cantieri_aziende'") === $table_cantieri_aziende;
    
    if (!$table_exists) {
        return [];
    }
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT u.ID, u.user_email, u.display_name,
               um.meta_value as rag_soc,
               um2.meta_value as tipo,
               ca.data_assegnazione, ca.note
        FROM {$wpdb->prefix}cantieri_aziende ca
        JOIN {$wpdb->users} u ON ca.user_id = u.ID
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'user_registration_rag_soc'
        LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'user_registration_tip_ut_rad'
        WHERE ca.cantiere_id = %d
        ORDER BY ca.data_assegnazione DESC
    ", $cantiere_id), ARRAY_A);
}

function getOperaiAssegnatiCantiereAjax($cantiere_id, $user_id = null) {
    global $wpdb;
    
    // Verifica che le tabelle esistano
    $table_operai = $wpdb->prefix . 'cantiere_operai_assegnazioni';
    $table_personale = $wpdb->prefix . 'cantiere_personale';
    
    $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_operai'") === $table_operai;
    $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_personale'") === $table_personale;
    
    if (!$table1_exists || !$table2_exists) {
        return [];
    }
    
    $where_user = $user_id ? $wpdb->prepare(" AND oa.user_id = %d", $user_id) : "";
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT oa.*, p.nome, p.cognome, p.data_nascita,
               p.formazione_antincendio_file, p.formazione_primo_soccorso_file, p.formazione_preposti_file,
               u.user_email, um.meta_value as rag_soc
        FROM {$wpdb->prefix}cantiere_operai_assegnazioni oa
        JOIN {$wpdb->prefix}cantiere_personale p ON oa.operaio_id = p.id
        JOIN {$wpdb->users} u ON oa.user_id = u.ID
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'user_registration_rag_soc'
        WHERE oa.cantiere_id = %d $where_user
        ORDER BY oa.data_assegnazione DESC
    ", $cantiere_id), ARRAY_A);
}

// ================== LOGICA PRINCIPALE (CON GESTIONE ERRORI) ==================

try {
    global $wpdb;
    
    // Test connessione database
    if (!$wpdb) {
        throw new Exception('Database WordPress non disponibile');
    }
    
    // Verifica che il cantiere esista
    $cantiere = getCantiereById($cantiere_id);
    
    if (!$cantiere) {
        http_response_code(404);
        die(json_encode([
            'error' => 'Cantiere non trovato',
            'cantiere_id' => $cantiere_id,
            'debug' => 'Il cantiere con ID ' . $cantiere_id . ' non esiste nel database'
        ]));
    }
    
    // Recupera aziende assegnate
    $aziende_assegnate = getAziendeAssegnateCantiereAjax($cantiere_id);
    
    // Recupera tutti gli operai del cantiere
    $operai_totali = getOperaiAssegnatiCantiereAjax($cantiere_id);
    
    // Raggruppa operai per azienda
    $aziende_con_operai = [];
    
    foreach ($aziende_assegnate as $azienda) {
        $operai_azienda = getOperaiAssegnatiCantiereAjax($cantiere_id, $azienda['ID']);
        
        // Calcola statistiche per questa azienda
        $operai_con_formazioni = 0;
        $competenze_azienda = [
            'antincendio' => 0,
            'primo_soccorso' => 0,
            'preposti' => 0
        ];
        
        foreach ($operai_azienda as $operaio) {
            $ha_formazioni = false;
            
            if (!empty($operaio['formazione_antincendio_file'])) {
                $competenze_azienda['antincendio']++;
                $ha_formazioni = true;
            }
            if (!empty($operaio['formazione_primo_soccorso_file'])) {
                $competenze_azienda['primo_soccorso']++;
                $ha_formazioni = true;
            }
            if (!empty($operaio['formazione_preposti_file'])) {
                $competenze_azienda['preposti']++;
                $ha_formazioni = true;
            }
            
            if ($ha_formazioni) {
                $operai_con_formazioni++;
            }
        }
        
        // Calcola conformità azienda
        $totale_operai_azienda = count($operai_azienda);
        $conformita_azienda = $totale_operai_azienda > 0 ? 
            round(($operai_con_formazioni / $totale_operai_azienda) * 100, 1) : 0;
        
        $aziende_con_operai[] = [
            'azienda' => [
                'id' => $azienda['ID'],
                'nome' => $azienda['rag_soc'] ?: $azienda['display_name'],
                'email' => $azienda['user_email'],
                'tipo' => $azienda['tipo'] ?: 'N/A',
                'data_assegnazione' => $azienda['data_assegnazione'],
                'note' => $azienda['note'],
                'conformita_percentuale' => $conformita_azienda,
                'operai_totali' => $totale_operai_azienda,
                'operai_con_formazioni' => $operai_con_formazioni
            ],
            'operai' => array_map(function($operaio) {
                // Calcola età in modo sicuro
                $eta = 0;
                if (!empty($operaio['data_nascita'])) {
                    try {
                        $data_nascita = new DateTime($operaio['data_nascita']);
                        $oggi = new DateTime();
                        $eta = $oggi->diff($data_nascita)->y;
                    } catch (Exception $e) {
                        $eta = 0;
                    }
                }
                
                // Determina competenze
                $competenze = [];
                if (!empty($operaio['formazione_antincendio_file'])) {
                    $competenze[] = 'antincendio';
                }
                if (!empty($operaio['formazione_primo_soccorso_file'])) {
                    $competenze[] = 'primo_soccorso';
                }
                if (!empty($operaio['formazione_preposti_file'])) {
                    $competenze[] = 'preposti';
                }
                
                return [
                    'id' => $operaio['operaio_id'],
                    'nome' => $operaio['nome'] ?: '',
                    'cognome' => $operaio['cognome'] ?: '',
                    'nome_completo' => trim(($operaio['nome'] ?: '') . ' ' . ($operaio['cognome'] ?: '')),
                    'data_nascita' => $operaio['data_nascita'] ?: '',
                    'eta' => $eta,
                    'competenze' => $competenze,
                    'ha_formazioni' => count($competenze) > 0,
                    'data_assegnazione' => $operaio['data_assegnazione'] ?: '',
                    'formazioni' => [
                        'antincendio' => !empty($operaio['formazione_antincendio_file']),
                        'primo_soccorso' => !empty($operaio['formazione_primo_soccorso_file']),
                        'preposti' => !empty($operaio['formazione_preposti_file'])
                    ]
                ];
            }, $operai_azienda),
            'statistiche' => [
                'competenze' => $competenze_azienda,
                'conformita' => $conformita_azienda
            ]
        ];
    }
    
    // Calcola statistiche globali cantiere
    $totale_operai = count($operai_totali);
    $operai_con_formazioni_globale = 0;
    $competenze_globali = [
        'antincendio' => 0,
        'primo_soccorso' => 0,
        'preposti' => 0
    ];
    
    foreach ($operai_totali as $operaio) {
        $ha_almeno_una_formazione = false;
        
        if (!empty($operaio['formazione_antincendio_file'])) {
            $competenze_globali['antincendio']++;
            $ha_almeno_una_formazione = true;
        }
        if (!empty($operaio['formazione_primo_soccorso_file'])) {
            $competenze_globali['primo_soccorso']++;
            $ha_almeno_una_formazione = true;
        }
        if (!empty($operaio['formazione_preposti_file'])) {
            $competenze_globali['preposti']++;
            $ha_almeno_una_formazione = true;
        }
        
        if ($ha_almeno_una_formazione) {
            $operai_con_formazioni_globale++;
        }
    }
    
    // Calcola percentuali globali
    $percentuali = [
        'antincendio' => $totale_operai > 0 ? round(($competenze_globali['antincendio'] / $totale_operai) * 100, 1) : 0,
        'primo_soccorso' => $totale_operai > 0 ? round(($competenze_globali['primo_soccorso'] / $totale_operai) * 100, 1) : 0,
        'preposti' => $totale_operai > 0 ? round(($competenze_globali['preposti'] / $totale_operai) * 100, 1) : 0
    ];
    
    // Determina conformità globale (soglia 30%)
    $conforme = ($percentuali['antincendio'] >= 30 && 
                 $percentuali['primo_soccorso'] >= 30 && 
                 $percentuali['preposti'] >= 30);
    
    // Prepara risposta finale
    $response = [
        'success' => true,
        'cantiere' => [
            'id' => $cantiere['id'],
            'nome' => $cantiere['nome'],
            'descrizione' => $cantiere['descrizione'] ?: '',
            'stato' => $cantiere['stato'],
            'data_inizio' => $cantiere['data_inizio'] ?: '',
            'data_fine' => $cantiere['data_fine'] ?: '',
            'data_creazione' => $cantiere['data_creazione'],
            'aziende_assegnate' => intval($cantiere['aziende_assegnate'])
        ],
        'statistiche_globali' => [
            'totale_aziende' => count($aziende_assegnate),
            'totale_operai' => $totale_operai,
            'operai_con_formazioni' => $operai_con_formazioni_globale,
            'percentuali' => $percentuali,
            'competenze_conteggi' => $competenze_globali,
            'conforme' => $conforme,
            'conformita_percentuale' => $totale_operai > 0 ? round(($operai_con_formazioni_globale / $totale_operai) * 100, 1) : 0
        ],
        'aziende' => $aziende_con_operai,
        'timestamp' => current_time('mysql'),
        'timezone' => 'Europe/Rome',
        'debug' => [
            'cantiere_id' => $cantiere_id,
            'wp_loaded' => defined('ABSPATH'),
            'tables_checked' => true,
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ]
    ];
    
    // Log per debugging (opzionale)
    error_log("AJAX Cantiere Details SUCCESS: Cantiere ID {$cantiere_id} - {$totale_operai} operai, {$cantiere['aziende_assegnate']} aziende");
    
    // Risposta JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Errore AJAX get_cantiere_details: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore interno del server',
        'message' => $e->getMessage(),
        'cantiere_id' => $cantiere_id,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'debug' => [
            'wp_loaded' => defined('ABSPATH'),
            'current_user' => get_current_user_id(),
            'server_info' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]
        ]
    ]);
}

exit;
?>
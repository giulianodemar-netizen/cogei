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
    
    // SELECT ALL fields from personale table for complete worker data
    return $wpdb->get_results($wpdb->prepare("
        SELECT oa.*, 
               p.id as personale_id,
               p.nome, p.cognome, p.data_nascita,
               p.unilav_data_emissione, p.unilav_data_scadenza, p.unilav_file,
               p.idoneita_sanitaria_scadenza, p.idoneita_sanitaria_file,
               p.formazione_antincendio_file, p.formazione_antincendio_data_emissione, p.formazione_antincendio_data_scadenza,
               p.formazione_primo_soccorso_file, p.formazione_primo_soccorso_data_emissione, p.formazione_primo_soccorso_data_scadenza,
               p.formazione_preposti_file, p.formazione_preposti_data_nomina, p.formazione_preposti_data_scadenza,
               p.formazione_generale_specifica_file, p.formazione_generale_specifica_data_emissione, p.formazione_generale_specifica_data_scadenza,
               p.rspp_file, p.rspp_data_nomina, p.rspp_data_scadenza,
               p.rls_file, p.rls_data_nomina, p.rls_data_scadenza,
               p.aspp_file, p.aspp_data_nomina, p.aspp_data_scadenza,
               p.formazione_ple_file, p.formazione_ple_data_emissione, p.formazione_ple_data_scadenza,
               p.formazione_carrelli_file, p.formazione_carrelli_data_emissione, p.formazione_carrelli_data_scadenza,
               p.formazione_lavori_quota_file, p.formazione_dpi_terza_categoria_file, p.formazione_ambienti_confinati_file,
               u.user_email, um.meta_value as rag_soc
        FROM {$wpdb->prefix}cantiere_operai_assegnazioni oa
        JOIN {$wpdb->prefix}cantiere_personale p ON oa.operaio_id = p.id
        JOIN {$wpdb->users} u ON oa.user_id = u.ID
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'user_registration_rag_soc'
        WHERE oa.cantiere_id = %d $where_user
        ORDER BY oa.data_assegnazione DESC
    ", $cantiere_id), ARRAY_A);
}

function getAutomezziAssegnatiCantiereAjax($cantiere_id, $user_id = null) {
    global $wpdb;
    
    // Verifica che le tabelle esistano
    $table_automezzi = $wpdb->prefix . 'cantiere_automezzi_assegnazioni';
    $table_automezzi_data = $wpdb->prefix . 'cantiere_automezzi';
    
    $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_automezzi'") === $table_automezzi;
    $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_automezzi_data'") === $table_automezzi_data;
    
    if (!$table1_exists || !$table2_exists) {
        return [];
    }
    
    $where_user = $user_id ? $wpdb->prepare(" AND aa.user_id = %d", $user_id) : "";
    
    // SELECT ALL fields from automezzi table for complete vehicle data
    return $wpdb->get_results($wpdb->prepare("
        SELECT aa.*, 
               a.id as automezzo_id,
               a.descrizione_automezzo, 
               a.targa,
               a.tipologia,
               a.file_targa,
               a.scadenza_revisione,
               a.scadenza_assicurazione,
               a.file_assicurazione,
               a.scadenza_verifiche_periodiche,
               a.file_verifiche_periodiche,
               a.data_creazione as automezzo_data_creazione,
               a.data_aggiornamento as automezzo_data_aggiornamento,
               u.user_email, um.meta_value as rag_soc
        FROM {$wpdb->prefix}cantiere_automezzi_assegnazioni aa
        JOIN {$wpdb->prefix}cantiere_automezzi a ON aa.automezzo_id = a.id
        JOIN {$wpdb->users} u ON aa.user_id = u.ID
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'user_registration_rag_soc'
        WHERE aa.cantiere_id = %d $where_user
        ORDER BY aa.data_assegnazione DESC
    ", $cantiere_id), ARRAY_A);
}

function getAttrezziAssegnatiCantiereAjax($cantiere_id, $user_id = null) {
    global $wpdb;
    
    // Verifica che le tabelle esistano
    $table_attrezzi = $wpdb->prefix . 'cantiere_attrezzi_assegnazioni';
    $table_attrezzi_data = $wpdb->prefix . 'cantiere_attrezzi';
    
    $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_attrezzi'") === $table_attrezzi;
    $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_attrezzi_data'") === $table_attrezzi_data;
    
    if (!$table1_exists || !$table2_exists) {
        return [];
    }
    
    $where_user = $user_id ? $wpdb->prepare(" AND at.user_id = %d", $user_id) : "";
    
    // SELECT ALL fields from attrezzi table for complete equipment data
    return $wpdb->get_results($wpdb->prepare("
        SELECT at.*, 
               a.id as attrezzo_id,
               a.descrizione_attrezzo, 
               a.data_revisione,
               a.data_creazione as attrezzo_data_creazione,
               a.data_aggiornamento as attrezzo_data_aggiornamento,
               u.user_email, um.meta_value as rag_soc
        FROM {$wpdb->prefix}cantiere_attrezzi_assegnazioni at
        JOIN {$wpdb->prefix}cantiere_attrezzi a ON at.attrezzo_id = a.id
        JOIN {$wpdb->users} u ON at.user_id = u.ID
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'user_registration_rag_soc'
        WHERE at.cantiere_id = %d $where_user
        ORDER BY at.data_assegnazione DESC
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
    
    // Recupera tutti gli automezzi del cantiere
    $automezzi_totali = getAutomezziAssegnatiCantiereAjax($cantiere_id);
    
    // Recupera tutti gli attrezzi del cantiere
    $attrezzi_totali = getAttrezziAssegnatiCantiereAjax($cantiere_id);
    
    // Raggruppa risorse per azienda
    $aziende_con_risorse = [];
    
    foreach ($aziende_assegnate as $azienda) {
        $operai_azienda = getOperaiAssegnatiCantiereAjax($cantiere_id, $azienda['ID']);
        $automezzi_azienda = getAutomezziAssegnatiCantiereAjax($cantiere_id, $azienda['ID']);
        $attrezzi_azienda = getAttrezziAssegnatiCantiereAjax($cantiere_id, $azienda['ID']);
        
        // Calcola statistiche per questa azienda
        $operai_con_formazioni = 0;
        $competenze_azienda = [
            'antincendio' => 0,
            'primo_soccorso' => 0,
            'preposti' => 0
        ];
        
        // Processa operai con TUTTI i documenti
        $operai_processed = [];
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
            if (!empty($operaio['formazione_antincendio_file'])) $competenze[] = 'antincendio';
            if (!empty($operaio['formazione_primo_soccorso_file'])) $competenze[] = 'primo_soccorso';
            if (!empty($operaio['formazione_preposti_file'])) $competenze[] = 'preposti';
            
            // Prepara array con TUTTI i documenti dell'operaio
            $documenti = [];
            
            // UNILAV
            if (!empty($operaio['unilav_file'])) {
                $documenti[] = [
                    'name' => 'UNILAV',
                    'type' => 'documento_personale',
                    'url' => $operaio['unilav_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['unilav_data_scadenza'] ?? null,
                    'emission_date' => $operaio['unilav_data_emissione'] ?? null
                ];
            }
            
            // Idoneità Sanitaria
            if (!empty($operaio['idoneita_sanitaria_file'])) {
                $documenti[] = [
                    'name' => 'Idoneità Sanitaria',
                    'type' => 'certificazione_medica',
                    'url' => $operaio['idoneita_sanitaria_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['idoneita_sanitaria_scadenza'] ?? null
                ];
            }
            
            // Formazione Antincendio
            if (!empty($operaio['formazione_antincendio_file'])) {
                $documenti[] = [
                    'name' => 'Formazione Antincendio',
                    'type' => 'formazione',
                    'url' => $operaio['formazione_antincendio_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['formazione_antincendio_data_scadenza'] ?? null,
                    'emission_date' => $operaio['formazione_antincendio_data_emissione'] ?? null
                ];
            }
            
            // Formazione Primo Soccorso
            if (!empty($operaio['formazione_primo_soccorso_file'])) {
                $documenti[] = [
                    'name' => 'Formazione Primo Soccorso',
                    'type' => 'formazione',
                    'url' => $operaio['formazione_primo_soccorso_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['formazione_primo_soccorso_data_scadenza'] ?? null,
                    'emission_date' => $operaio['formazione_primo_soccorso_data_emissione'] ?? null
                ];
            }
            
            // Formazione Preposti
            if (!empty($operaio['formazione_preposti_file'])) {
                $documenti[] = [
                    'name' => 'Formazione Preposti',
                    'type' => 'formazione',
                    'url' => $operaio['formazione_preposti_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['formazione_preposti_data_scadenza'] ?? null,
                    'emission_date' => $operaio['formazione_preposti_data_nomina'] ?? null
                ];
            }
            
            // Formazione Generale e Specifica
            if (!empty($operaio['formazione_generale_specifica_file'])) {
                $documenti[] = [
                    'name' => 'Formazione Generale e Specifica',
                    'type' => 'formazione',
                    'url' => $operaio['formazione_generale_specifica_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['formazione_generale_specifica_data_scadenza'] ?? null,
                    'emission_date' => $operaio['formazione_generale_specifica_data_emissione'] ?? null
                ];
            }
            
            // RSPP
            if (!empty($operaio['rspp_file'])) {
                $documenti[] = [
                    'name' => 'RSPP',
                    'type' => 'ruolo_sicurezza',
                    'url' => $operaio['rspp_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['rspp_data_scadenza'] ?? null,
                    'emission_date' => $operaio['rspp_data_nomina'] ?? null
                ];
            }
            
            // RLS
            if (!empty($operaio['rls_file'])) {
                $documenti[] = [
                    'name' => 'RLS',
                    'type' => 'ruolo_sicurezza',
                    'url' => $operaio['rls_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['rls_data_scadenza'] ?? null,
                    'emission_date' => $operaio['rls_data_nomina'] ?? null
                ];
            }
            
            // ASPP
            if (!empty($operaio['aspp_file'])) {
                $documenti[] = [
                    'name' => 'ASPP',
                    'type' => 'ruolo_sicurezza',
                    'url' => $operaio['aspp_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['aspp_data_scadenza'] ?? null,
                    'emission_date' => $operaio['aspp_data_nomina'] ?? null
                ];
            }
            
            // Formazione PLE
            if (!empty($operaio['formazione_ple_file'])) {
                $documenti[] = [
                    'name' => 'Formazione PLE',
                    'type' => 'formazione_specifica',
                    'url' => $operaio['formazione_ple_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['formazione_ple_data_scadenza'] ?? null,
                    'emission_date' => $operaio['formazione_ple_data_emissione'] ?? null
                ];
            }
            
            // Formazione Carrelli
            if (!empty($operaio['formazione_carrelli_file'])) {
                $documenti[] = [
                    'name' => 'Formazione Carrelli Elevatori',
                    'type' => 'formazione_specifica',
                    'url' => $operaio['formazione_carrelli_file'],
                    'uploaded_at' => null,
                    'expires_at' => $operaio['formazione_carrelli_data_scadenza'] ?? null,
                    'emission_date' => $operaio['formazione_carrelli_data_emissione'] ?? null
                ];
            }
            
            // Formazione Lavori in Quota
            if (!empty($operaio['formazione_lavori_quota_file'])) {
                $documenti[] = [
                    'name' => 'Formazione Lavori in Quota',
                    'type' => 'formazione_specifica',
                    'url' => $operaio['formazione_lavori_quota_file'],
                    'uploaded_at' => null,
                    'expires_at' => null
                ];
            }
            
            // Formazione DPI Terza Categoria
            if (!empty($operaio['formazione_dpi_terza_categoria_file'])) {
                $documenti[] = [
                    'name' => 'Formazione DPI Terza Categoria',
                    'type' => 'formazione_specifica',
                    'url' => $operaio['formazione_dpi_terza_categoria_file'],
                    'uploaded_at' => null,
                    'expires_at' => null
                ];
            }
            
            // Formazione Ambienti Confinati
            if (!empty($operaio['formazione_ambienti_confinati_file'])) {
                $documenti[] = [
                    'name' => 'Formazione Ambienti Confinati',
                    'type' => 'formazione_specifica',
                    'url' => $operaio['formazione_ambienti_confinati_file'],
                    'uploaded_at' => null,
                    'expires_at' => null
                ];
            }
            
            $operai_processed[] = [
                'id' => $operaio['operaio_id'],
                'personale_id' => $operaio['personale_id'],
                'nome' => $operaio['nome'] ?: '',
                'cognome' => $operaio['cognome'] ?: '',
                'nome_completo' => trim(($operaio['nome'] ?: '') . ' ' . ($operaio['cognome'] ?: '')),
                'data_nascita' => $operaio['data_nascita'] ?: '',
                'eta' => $eta,
                'competenze' => $competenze,
                'ha_formazioni' => count($competenze) > 0,
                'data_assegnazione' => $operaio['data_assegnazione'] ?: '',
                'documenti' => $documenti,
                'formazioni' => [
                    'antincendio' => !empty($operaio['formazione_antincendio_file']),
                    'primo_soccorso' => !empty($operaio['formazione_primo_soccorso_file']),
                    'preposti' => !empty($operaio['formazione_preposti_file']),
                    'generale_specifica' => !empty($operaio['formazione_generale_specifica_file']),
                    'ple' => !empty($operaio['formazione_ple_file']),
                    'carrelli' => !empty($operaio['formazione_carrelli_file']),
                    'lavori_quota' => !empty($operaio['formazione_lavori_quota_file']),
                    'dpi_terza_categoria' => !empty($operaio['formazione_dpi_terza_categoria_file']),
                    'ambienti_confinati' => !empty($operaio['formazione_ambienti_confinati_file'])
                ],
                'ruoli' => [
                    'rspp' => !empty($operaio['rspp_file']),
                    'rls' => !empty($operaio['rls_file']),
                    'aspp' => !empty($operaio['aspp_file'])
                ]
            ];
        }
        
        // Processa automezzi con TUTTI i campi
        $automezzi_processed = [];
        foreach ($automezzi_azienda as $automezzo) {
            $documenti_automezzo = [];
            
            // File Targa
            if (!empty($automezzo['file_targa'])) {
                $documenti_automezzo[] = [
                    'name' => 'Libretto/Carta di Circolazione',
                    'type' => 'documento_mezzo',
                    'url' => $automezzo['file_targa'],
                    'uploaded_at' => null,
                    'expires_at' => null
                ];
            }
            
            // File Assicurazione
            if (!empty($automezzo['file_assicurazione'])) {
                $documenti_automezzo[] = [
                    'name' => 'Assicurazione',
                    'type' => 'assicurazione',
                    'url' => $automezzo['file_assicurazione'],
                    'uploaded_at' => null,
                    'expires_at' => $automezzo['scadenza_assicurazione'] ?? null
                ];
            }
            
            // File Verifiche Periodiche
            if (!empty($automezzo['file_verifiche_periodiche'])) {
                $documenti_automezzo[] = [
                    'name' => 'Verifiche Periodiche',
                    'type' => 'verifica_periodica',
                    'url' => $automezzo['file_verifiche_periodiche'],
                    'uploaded_at' => null,
                    'expires_at' => $automezzo['scadenza_verifiche_periodiche'] ?? null
                ];
            }
            
            $automezzi_processed[] = [
                'id' => $automezzo['automezzo_id'],
                'descrizione' => $automezzo['descrizione_automezzo'] ?: '',
                'targa' => $automezzo['targa'] ?: '',
                'tipologia' => $automezzo['tipologia'] ?: 'AUTO',
                'scadenza_revisione' => $automezzo['scadenza_revisione'] ?? null,
                'scadenza_assicurazione' => $automezzo['scadenza_assicurazione'] ?? null,
                'scadenza_verifiche_periodiche' => $automezzo['scadenza_verifiche_periodiche'] ?? null,
                'data_creazione' => $automezzo['automezzo_data_creazione'] ?? null,
                'data_aggiornamento' => $automezzo['automezzo_data_aggiornamento'] ?? null,
                'data_assegnazione' => $automezzo['data_assegnazione'] ?: '',
                'documenti' => $documenti_automezzo
            ];
        }
        
        // Processa attrezzi con TUTTI i campi
        $attrezzi_processed = [];
        foreach ($attrezzi_azienda as $attrezzo) {
            $attrezzi_processed[] = [
                'id' => $attrezzo['attrezzo_id'],
                'descrizione' => $attrezzo['descrizione_attrezzo'] ?: '',
                'data_revisione' => $attrezzo['data_revisione'] ?? null,
                'data_creazione' => $attrezzo['attrezzo_data_creazione'] ?? null,
                'data_aggiornamento' => $attrezzo['attrezzo_data_aggiornamento'] ?? null,
                'data_assegnazione' => $attrezzo['data_assegnazione'] ?: '',
                'documenti' => [] // Attrezzi attualmente non hanno documenti allegati nel DB
            ];
        }
        
        // Calcola conformità azienda
        $totale_operai_azienda = count($operai_azienda);
        $conformita_azienda = $totale_operai_azienda > 0 ? 
            round(($operai_con_formazioni / $totale_operai_azienda) * 100, 1) : 0;
        
        $aziende_con_risorse[] = [
            'azienda' => [
                'id' => $azienda['ID'],
                'nome' => $azienda['rag_soc'] ?: $azienda['display_name'],
                'email' => $azienda['user_email'],
                'tipo' => $azienda['tipo'] ?: 'N/A',
                'data_assegnazione' => $azienda['data_assegnazione'],
                'note' => $azienda['note'],
                'conformita_percentuale' => $conformita_azienda,
                'operai_totali' => $totale_operai_azienda,
                'operai_con_formazioni' => $operai_con_formazioni,
                'automezzi_totali' => count($automezzi_azienda),
                'attrezzi_totali' => count($attrezzi_azienda)
            ],
            'operai' => $operai_processed,
            'mezzi' => $automezzi_processed,
            'attrezzature' => $attrezzi_processed,
            'statistiche' => [
                'competenze' => $competenze_azienda,
                'conformita' => $conformita_azienda
            ]
        ];
    }
    
    // Calcola statistiche globali cantiere
    $totale_operai = count($operai_totali);
    $totale_automezzi = count($automezzi_totali);
    $totale_attrezzi = count($attrezzi_totali);
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
            'totale_mezzi' => $totale_automezzi,
            'totale_attrezzature' => $totale_attrezzi,
            'operai_con_formazioni' => $operai_con_formazioni_globale,
            'percentuali' => $percentuali,
            'competenze_conteggi' => $competenze_globali,
            'conforme' => $conforme,
            'conformita_percentuale' => $totale_operai > 0 ? round(($operai_con_formazioni_globale / $totale_operai) * 100, 1) : 0
        ],
        'aziende' => $aziende_con_risorse,
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
    error_log("AJAX Cantiere Details SUCCESS: Cantiere ID {$cantiere_id} - {$totale_operai} operai, {$totale_automezzi} mezzi, {$totale_attrezzi} attrezzature, {$cantiere['aziende_assegnate']} aziende");
    
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
<?php
/**
 * Cron per Controllo Scadenze Fornitori
 * 
 * Questo script controlla le scadenze dei documenti dei fornitori e invia
 * notifiche email in base ai giorni rimanenti alla scadenza.
 * 
 * Trigger Days:
 * - 15 giorni prima: avviso scadenza imminente
 * - 5 giorni prima: avviso urgente
 * - 0 giorni (oggi): documento scaduto
 * - -15 giorni (15 giorni dopo scadenza): disattivazione automatica
 * 
 * Esecuzione: questo script deve essere eseguito giornalmente tramite cron.
 * 
 * @version 1.0
 * @author Cogei System
 */

// Configurazione
$debug_mode = false; // Cambia a true per modalità DEBUG (non invia email reali)

// Definisci ABSPATH se non esiste (per WordPress)
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__FILE__)) . '/');
}

// Carica WordPress se necessario
if (file_exists(ABSPATH . 'wp-load.php')) {
    require_once(ABSPATH . 'wp-load.php');
}

// Carica logger centralizzato
require_once(ABSPATH . 'includes/log_mail.php');

// Imposta timezone
date_default_timezone_set('Europe/Rome');

// Email amministratore
$admin_email = 'ufficio_qualita@cogei.net';

// Giorni trigger per le notifiche
$trigger_days = [15, 5, 0, -15];

/**
 * Calcola i giorni rimanenti alla scadenza
 */
function calculateDaysToExpiry($scadenza_date) {
    if (empty($scadenza_date)) {
        return null;
    }
    
    $converted_date = str_replace("/", "-", $scadenza_date);
    try {
        $now = new DateTime("now");
        $expiry_date = new DateTime($converted_date . ' 23:59:59');
        $interval = $now->diff($expiry_date);
        return (int)$interval->format('%r%a');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Determina il tipo di fornitore e i documenti necessari
 */
function getRequiredDocuments($user_id) {
    $tipo = get_user_meta($user_id, 'user_registration_tip_ut_rad', true);
    
    // Documenti sempre fondamentali
    $required_docs = [
        'user_registration_scad_CCIAA' => 'CCIAA',
        'user_registration_scad_wit' => 'White List',
        'user_registration_scad_durc' => 'DURC',
        'user_registration_scadenza_altre_scadenze' => 'Altre Scadenze'
    ];
    
    // RCT-RCO serve solo per: LAVORO, SERVIZI, SUBAPPALTO, NOLI
    if ($tipo === 'Lavoro' || $tipo === 'Servizi' || $tipo === 'Subappalto' || $tipo === 'Noli') {
        if ($tipo === 'Forniture') {
            $required_docs['user_registration_scad_rct_rco_forni'] = 'RCT-RCO (Forniture)';
        } else {
            $required_docs['user_registration_scad_rct_rco'] = 'RCT-RCO';
        }
    }
    
    return $required_docs;
}

/**
 * Invia email di notifica scadenza
 */
function sendExpiryNotification($user_id, $trigger_day, $expiring_docs, $debug_mode) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $rag_soc = get_user_meta($user_id, 'user_registration_rag_soc', true);
    $user_email = $user->user_email;
    
    // Determina il tipo di notifica e l'oggetto
    $subjects = [
        15 => '⚠️ AVVISO: Documenti in scadenza tra 15 giorni - Cogei.net',
        5 => '🚨 URGENTE: Documenti in scadenza tra 5 giorni - Cogei.net',
        0 => '❌ CRITICO: Documenti scaduti oggi - Cogei.net',
        -15 => '🔴 DISATTIVAZIONE: Account disattivato per documenti scaduti - Cogei.net'
    ];
    
    $subject = $subjects[$trigger_day] ?? 'Notifica scadenza documenti - Cogei.net';
    
    // Costruisci lista documenti
    $docs_list = '';
    foreach ($expiring_docs as $doc) {
        $giorni_text = '';
        if ($doc['giorni'] > 0) {
            $giorni_text = "Scade tra {$doc['giorni']} giorni";
        } elseif ($doc['giorni'] === 0) {
            $giorni_text = "Scade OGGI";
        } else {
            $giorni_text = "Scaduto da " . abs($doc['giorni']) . " giorni";
        }
        
        $docs_list .= "• <strong>{$doc['nome']}</strong>: {$doc['scadenza']} ({$giorni_text})<br>";
    }
    
    // Costruisci corpo email basato sul trigger day
    $body = buildEmailBody($trigger_day, $rag_soc, $docs_list);
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: <no-reply@cogei.net>' . "\r\n";
    
    $email_sent = false;
    if (!$debug_mode) {
        $email_sent = mail($user_email, $subject, $body, $headers);
    }
    
    // Log centralizzato
    $tipo_notifica = [
        15 => 'scadenza_15giorni',
        5 => 'scadenza_5giorni',
        0 => 'scadenza_oggi',
        -15 => 'avviso_disattivazione'
    ];
    
    AlboFornitoriMailLogger::logCronNotification(
        $tipo_notifica[$trigger_day] ?? 'scadenza',
        $user_id,
        $rag_soc ?: 'N/A',
        $user_email,
        $user_email,
        $email_sent,
        $expiring_docs,
        $trigger_day,
        $debug_mode
    );
    
    return $email_sent;
}

/**
 * Costruisce il corpo dell'email in base al trigger day
 */
function buildEmailBody($trigger_day, $rag_soc, $docs_list) {
    $base_html = "<html>
<head>
<title>Notifica Scadenza Documenti</title>
</head>
<body>
<div style='background: #03679e; text-align: center; padding: 10px; margin-bottom: 30px;'><img style='max-width: 150px;' src='https://cogei.provasiti.it/cogei/wp-content/uploads/2023/02/logo_bianco-1.png' /></div>";
    
    switch ($trigger_day) {
        case 15:
            $body = $base_html . "
<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;'>
<h3 style='margin: 0; color: #856404;'>⚠️ Avviso Scadenza Documenti</h3>
</div>
Gentile {$rag_soc},<br><br>
ti informiamo che i seguenti documenti sono in scadenza tra <strong>15 giorni</strong>:<br><br>
{$docs_list}
<br>
Ti invitiamo a rinnovare tempestivamente la documentazione per evitare la sospensione dell'account.<br><br>
Accedi alla tua area privata per aggiornare i documenti.<br><br>";
            break;
            
        case 5:
            $body = $base_html . "
<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin-bottom: 20px;'>
<h3 style='margin: 0; color: #721c24;'>🚨 URGENTE - Documenti in scadenza</h3>
</div>
Gentile {$rag_soc},<br><br>
<strong style='color: #dc3545;'>ATTENZIONE:</strong> i seguenti documenti sono in scadenza tra <strong>5 giorni</strong>:<br><br>
{$docs_list}
<br>
<strong>È URGENTE</strong> rinnovare la documentazione per evitare la disattivazione dell'account.<br><br>
Accedi immediatamente alla tua area privata per aggiornare i documenti.<br><br>";
            break;
            
        case 0:
            $body = $base_html . "
<div style='background: #dc3545; color: white; padding: 15px; margin-bottom: 20px;'>
<h3 style='margin: 0; color: white;'>❌ CRITICO - Documenti Scaduti</h3>
</div>
Gentile {$rag_soc},<br><br>
<strong style='color: #dc3545;'>I seguenti documenti sono SCADUTI OGGI:</strong><br><br>
{$docs_list}
<br>
Il tuo account potrebbe essere disattivato se non provvedi immediatamente al rinnovo della documentazione.<br><br>
<strong>AGISCI SUBITO</strong> accedendo alla tua area privata.<br><br>";
            break;
            
        case -15:
            $body = $base_html . "
<div style='background: #721c24; color: white; padding: 15px; margin-bottom: 20px;'>
<h3 style='margin: 0; color: white;'>🔴 ACCOUNT DISATTIVATO</h3>
</div>
Gentile {$rag_soc},<br><br>
Ti informiamo che il tuo account è stato <strong>disattivato automaticamente</strong> a causa dei seguenti documenti scaduti da oltre 15 giorni:<br><br>
{$docs_list}
<br>
Per riattivare l'account è necessario:<br>
1. Aggiornare tutta la documentazione scaduta<br>
2. Contattare l'ufficio qualità: <a href='mailto:ufficio_qualita@cogei.net'>ufficio_qualita@cogei.net</a><br><br>
Non potrai partecipare a gare o commesse fino alla riattivazione dell'account.<br><br>";
            break;
            
        default:
            $body = $base_html . "
Gentile {$rag_soc},<br><br>
Ti informiamo che alcuni documenti necessitano di attenzione:<br><br>
{$docs_list}
<br>
Ti invitiamo a verificare e aggiornare la documentazione.<br><br>";
    }
    
    $body .= "Cordiali Saluti,<br>Cogei S.r.l.
<div class='footer' style='background: #03679e; padding: 10px; margin-top: 20px;'>
<div class='rigainfofo primariga'><a style='color: white; text-decoration: none;' href='#' target='_blank' rel='noopener'>Via Francesco Lomonaco, 3 - 80121 Napoli</a></div>
<div class='rigainfofo'><a style='color: white; text-decoration: none;' href='tel:+390812303782'>TEL: +39 081.230.37.82</a></div>
<div class='rigainfofo primariga'><a style='color: white; text-decoration: none;' href='mailto:cogei@pec.cogei.net'>PEC: cogei@pec.cogei.net</a></div>
<div style='margin-top: 40px; text-align: center; color: white; font-size: 12px !important;'>COGEI SRL - P.IVA: IT06569020636 - Copyright © 2023 Cogei. All Rights Reserved.</div>
</div>
</body>
</html>";
    
    return $body;
}

/**
 * Disattiva automaticamente un fornitore
 */
function disableSupplier($user_id) {
    update_user_meta($user_id, 'forced_supplier_status', 'Disattivo');
    
    // Log dell'azione
    $log_entry = "[" . date('d/m/Y H:i:s') . "] CRON: Fornitore ID {$user_id} disattivato automaticamente per documenti scaduti da oltre 15 giorni\n";
    $log_file = ABSPATH . 'log_mail/log_cron_disattivazioni.txt';
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Notifica admin delle disattivazioni
 */
function notifyAdminDisabling($disabled_users, $debug_mode) {
    global $admin_email;
    
    if (empty($disabled_users)) {
        return;
    }
    
    $subject = "CRON: " . count($disabled_users) . " fornitori disattivati automaticamente";
    
    $users_list = '';
    foreach ($disabled_users as $user_info) {
        $users_list .= "• ID: {$user_info['id']} - {$user_info['name']} ({$user_info['email']})<br>";
    }
    
    $body = "<html>
<head>
<title>Notifica Disattivazioni Automatiche</title>
</head>
<body>
<div style='background: #dc3545; color: white; padding: 20px; text-align: center; margin-bottom: 20px;'>
<h2 style='margin: 0; color: white;'>🚨 CRON - Disattivazioni Automatiche</h2>
</div>

<p>Ciao Admin,</p>

<p>Il cron di controllo scadenze ha disattivato automaticamente <strong>" . count($disabled_users) . " fornitori</strong> 
per documenti scaduti da oltre 15 giorni.</p>

<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
<h3 style='margin: 0 0 10px 0; color: #721c24;'>Fornitori disattivati:</h3>
{$users_list}
</div>

<p><strong>Data esecuzione:</strong> " . date('d/m/Y H:i:s') . "</p>

<div class='footer' style='background: #03679e; padding: 10px; margin-top: 30px;'>
<div style='text-align: center; color: white; font-size: 12px;'>
COGEI SRL - Sistema Cron Controllo Scadenze<br>
Copyright © 2023 Cogei. All Rights Reserved.
</div>
</div>
</body>
</html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: <cron@cogei.net>' . "\r\n";
    
    if (!$debug_mode) {
        mail($admin_email, $subject, $body, $headers);
    }
    
    // Log notifica admin
    AlboFornitoriMailLogger::log([
        'ambiente' => $debug_mode ? 'DEBUG' : 'PROD',
        'tipo_email' => 'CRON_NOTIFICA_ADMIN_DISATTIVAZIONI',
        'destinatario' => $admin_email,
        'oggetto' => $subject,
        'user_id' => 0,
        'user_name' => 'Sistema Cron',
        'user_email' => 'cron@cogei.net',
        'note' => count($disabled_users) . ' fornitori disattivati',
        'email_sent' => !$debug_mode
    ]);
}

// ==================== ESECUZIONE PRINCIPALE ====================

echo "===================================================\n";
echo "CRON CONTROLLO SCADENZE FORNITORI - " . date('d/m/Y H:i:s') . "\n";
echo "Modalità: " . ($debug_mode ? "DEBUG (no email reali)" : "PRODUZIONE") . "\n";
echo "===================================================\n\n";

// Ottieni tutti i fornitori (utenti con ruolo subscriber)
$suppliers = get_users([
    'role' => 'subscriber',
    'orderby' => 'ID',
    'order' => 'ASC'
]);

$total_suppliers = count($suppliers);
$notifications_sent = 0;
$disabled_users = [];

echo "Trovati {$total_suppliers} fornitori da controllare...\n\n";

foreach ($suppliers as $user) {
    $user_id = $user->ID;
    $rag_soc = get_user_meta($user_id, 'user_registration_rag_soc', true) ?: $user->display_name;
    
    echo "Controllo fornitore ID {$user_id} ({$rag_soc})...\n";
    
    // Ottieni documenti richiesti
    $required_docs = getRequiredDocuments($user_id);
    
    // Controlla ogni documento
    foreach ($required_docs as $meta_key => $doc_name) {
        $scadenza_date = get_user_meta($user_id, $meta_key, true);
        
        if (empty($scadenza_date)) {
            continue; // Documento non caricato
        }
        
        $days = calculateDaysToExpiry($scadenza_date);
        
        if ($days === null) {
            continue; // Data non valida
        }
        
        // Verifica se corrisponde a un trigger day
        if (in_array($days, $trigger_days)) {
            echo "  - {$doc_name}: scadenza in {$days} giorni (TRIGGER)\n";
            
            // Prepara info documento per la notifica
            $expiring_docs = [[
                'nome' => $doc_name,
                'scadenza' => $scadenza_date,
                'giorni' => $days
            ]];
            
            // Invia notifica
            sendExpiryNotification($user_id, $days, $expiring_docs, $debug_mode);
            $notifications_sent++;
            
            // Se trigger -15, disattiva fornitore
            if ($days === -15) {
                echo "  --> DISATTIVAZIONE AUTOMATICA\n";
                disableSupplier($user_id);
                $disabled_users[] = [
                    'id' => $user_id,
                    'name' => $rag_soc,
                    'email' => $user->user_email
                ];
            }
        }
    }
}

// Notifica admin delle disattivazioni
if (!empty($disabled_users)) {
    echo "\nInvio notifica admin per " . count($disabled_users) . " disattivazioni...\n";
    notifyAdminDisabling($disabled_users, $debug_mode);
}

echo "\n===================================================\n";
echo "CRON COMPLETATO\n";
echo "Notifiche inviate: {$notifications_sent}\n";
echo "Fornitori disattivati: " . count($disabled_users) . "\n";
echo "===================================================\n";

<?php
/**
 * Cron per Controllo Scadenze HSE
 * 
 * Questo script controlla le scadenze dei documenti/formazioni HSE e invia
 * notifiche email in base ai giorni rimanenti alla scadenza.
 * 
 * Documenti controllati:
 * - Formazioni personale (antincendio, primo soccorso, preposti, generale/specifica, RSPP, RLS, ASPP, PLE, carrelli)
 * - Idoneità sanitaria personale
 * - Unilav personale
 * - Scadenze mezzi (revisione, assicurazione, verifiche periodiche)
 * - Revisioni attrezzi
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

// Carica logger centralizzato HSE
require_once(ABSPATH . 'includes/log_mail_hse.php');

// Imposta timezone
date_default_timezone_set('Europe/Rome');

// Email amministratore HSE
$admin_email = 'hse@cogei.net';

// Giorni trigger per le notifiche
$trigger_days = [15, 5, 0, -15];

/**
 * Calcola i giorni rimanenti alla scadenza
 */
function calculateDaysToExpiry($scadenza_date) {
    if (empty($scadenza_date)) {
        return null;
    }
    
    // Gestisci formato date SQL (Y-m-d) e italiano (d/m/Y)
    try {
        // Normalizza la data di oggi a mezzanotte per calcolo corretto dei giorni
        $now = new DateTime("today");
        $expiry_date = null;
        
        // Prova formato italiano d/m/Y
        if (strpos($scadenza_date, '/') !== false) {
            $expiry_date = DateTime::createFromFormat('d/m/Y', $scadenza_date);
        }
        
        // Prova formato SQL Y-m-d se il precedente fallisce
        if (!$expiry_date) {
            $expiry_date = DateTime::createFromFormat('Y-m-d', $scadenza_date);
        }
        
        // Fallback al parsing standard se entrambi falliscono
        if (!$expiry_date) {
            $expiry_date = new DateTime($scadenza_date);
        }
        
        // Normalizza la data di scadenza a mezzanotte
        $expiry_date->setTime(0, 0, 0);
        
        $interval = $now->diff($expiry_date);
        return (int)$interval->format('%r%a');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Ottieni tutti i documenti HSE con scadenze
 */
function getHseExpiringDocuments($user_id) {
    global $wpdb;
    
    $expiring_docs = [];
    
    // 1. Formazioni personale
    $personale = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}cantiere_personale 
        WHERE richiesta_id IN (
            SELECT id FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d
        )
    ", $user_id), ARRAY_A);
    
    $formazioni_fields = [
        'formazione_antincendio_data_scadenza' => 'Formazione Antincendio',
        'formazione_primo_soccorso_data_scadenza' => 'Formazione Primo Soccorso',
        'formazione_preposti_data_scadenza' => 'Formazione Preposti',
        'formazione_generale_specifica_data_scadenza' => 'Formazione Generale e Specifica',
        'rspp_data_scadenza' => 'RSPP',
        'rls_data_scadenza' => 'RLS',
        'aspp_data_scadenza' => 'ASPP',
        'formazione_ple_data_scadenza' => 'Formazione PLE',
        'formazione_carrelli_data_scadenza' => 'Formazione Carrelli',
        'idoneita_sanitaria_scadenza' => 'Idoneità Sanitaria',
        'unilav_data_scadenza' => 'Unilav'
    ];
    
    foreach ($personale as $persona) {
        $nome_completo = $persona['nome'] . ' ' . $persona['cognome'];
        foreach ($formazioni_fields as $field => $label) {
            if (!empty($persona[$field])) {
                $giorni = calculateDaysToExpiry($persona[$field]);
                if ($giorni !== null) {
                    $expiring_docs[] = [
                        'nome' => $label . ' (' . $nome_completo . ')',
                        'scadenza' => $persona[$field],
                        'giorni' => $giorni,
                        'tipo' => 'personale',
                        'id' => $persona['id']
                    ];
                }
            }
        }
    }
    
    // 2. Scadenze mezzi (automezzi)
    $mezzi = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}cantiere_automezzi 
        WHERE richiesta_id IN (
            SELECT id FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d
        )
    ", $user_id), ARRAY_A);
    
    $mezzi_fields = [
        'scadenza_revisione' => 'Revisione mezzo',
        'scadenza_assicurazione' => 'Assicurazione mezzo',
        'scadenza_verifiche_periodiche' => 'Verifiche periodiche mezzo'
    ];
    
    foreach ($mezzi as $mezzo) {
        $descrizione = $mezzo['descrizione_automezzo'] . ' (' . $mezzo['targa'] . ')';
        foreach ($mezzi_fields as $field => $label) {
            if (!empty($mezzo[$field])) {
                $giorni = calculateDaysToExpiry($mezzo[$field]);
                if ($giorni !== null) {
                    $expiring_docs[] = [
                        'nome' => $label . ' - ' . $descrizione,
                        'scadenza' => $mezzo[$field],
                        'giorni' => $giorni,
                        'tipo' => 'mezzo',
                        'id' => $mezzo['id']
                    ];
                }
            }
        }
    }
    
    // 3. Revisioni attrezzi
    $attrezzi = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}cantiere_attrezzi 
        WHERE richiesta_id IN (
            SELECT id FROM {$wpdb->prefix}cantiere_richieste WHERE user_id = %d
        ) AND data_revisione IS NOT NULL
    ", $user_id), ARRAY_A);
    
    foreach ($attrezzi as $attrezzo) {
        if (!empty($attrezzo['data_revisione'])) {
            $giorni = calculateDaysToExpiry($attrezzo['data_revisione']);
            if ($giorni !== null) {
                $expiring_docs[] = [
                    'nome' => 'Revisione attrezzo - ' . $attrezzo['descrizione_attrezzo'],
                    'scadenza' => $attrezzo['data_revisione'],
                    'giorni' => $giorni,
                    'tipo' => 'attrezzo',
                    'id' => $attrezzo['id']
                ];
            }
        }
    }
    
    return $expiring_docs;
}

/**
 * Verifica se l'email è già stata inviata di recente
 */
function hasRecentEmailSent($user_id, $trigger_day) {
    // Ottieni il timestamp dell'ultimo invio per questo utente e trigger day
    $last_sent_key = "cron_hse_email_last_sent_{$trigger_day}";
    $last_sent = get_user_meta($user_id, $last_sent_key, true);
    
    if (empty($last_sent)) {
        return false; // Mai inviata
    }
    
    // Controlla se è stata inviata nelle ultime 24 ore
    $now = time();
    $time_diff = $now - $last_sent;
    $hours_diff = $time_diff / 3600;
    
    // Se l'email è stata inviata meno di 24 ore fa, considera come già inviata
    return $hours_diff < 24;
}

/**
 * Registra l'invio dell'email
 */
function recordEmailSent($user_id, $trigger_day) {
    $last_sent_key = "cron_hse_email_last_sent_{$trigger_day}";
    update_user_meta($user_id, $last_sent_key, time());
}

/**
 * Invia email di notifica scadenza
 */
function sendExpiryNotification($user_id, $trigger_day, $expiring_docs, $debug_mode) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Controlla se l'email è già stata inviata di recente
    if (hasRecentEmailSent($user_id, $trigger_day)) {
        return 'skipped'; // Ritorna 'skipped' invece di false
    }
    
    $rag_soc = get_user_meta($user_id, 'user_registration_rag_soc', true);
    $user_email = $user->user_email;
    
    // Determina il tipo di notifica e l'oggetto
    $subjects = [
        15 => '⚠️ AVVISO: Documenti/Formazioni HSE in scadenza tra 15 giorni - Cogei.net',
        5 => '🚨 URGENTE: Documenti/Formazioni HSE in scadenza tra 5 giorni - Cogei.net',
        0 => '❌ CRITICO: Documenti/Formazioni HSE scaduti oggi - Cogei.net',
        -15 => '🔴 DISATTIVAZIONE: Account disattivato per documenti HSE scaduti - Cogei.net'
    ];
    
    $subject = $subjects[$trigger_day] ?? 'Notifica scadenza documenti HSE - Cogei.net';
    
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
    
    HseMailLogger::logCronNotification(
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
    
    // Registra l'invio dell'email
    recordEmailSent($user_id, $trigger_day);
    
    return $email_sent;
}

/**
 * Costruisce il corpo dell'email in base al trigger day
 */
function buildEmailBody($trigger_day, $rag_soc, $docs_list) {
    $base_html = "<html>
<head>
<title>Notifica Scadenza Documenti HSE</title>
</head>
<body>
<div style='background: #03679e; text-align: center; padding: 10px; margin-bottom: 30px;'><img style='max-width: 150px;' src='https://cogei.provasiti.it/cogei/wp-content/uploads/2023/02/logo_bianco-1.png' /></div>";
    
    switch ($trigger_day) {
        case 15:
            $body = $base_html . "
<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;'>
<h3 style='margin: 0; color: #856404;'>⚠️ Avviso Scadenza Documenti/Formazioni HSE</h3>
</div>
Gentile {$rag_soc},<br><br>
ti informiamo che i seguenti documenti/formazioni HSE sono in scadenza tra <strong>15 giorni</strong>:<br><br>
{$docs_list}
<br>
Ti invitiamo a rinnovare tempestivamente la documentazione per evitare problemi di accesso ai cantieri.<br><br>
Accedi alla tua area privata per aggiornare i documenti.<br><br>";
            break;
            
        case 5:
            $body = $base_html . "
<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin-bottom: 20px;'>
<h3 style='margin: 0; color: #721c24;'>🚨 URGENTE - Documenti/Formazioni HSE in scadenza</h3>
</div>
Gentile {$rag_soc},<br><br>
<strong style='color: #dc3545;'>ATTENZIONE:</strong> i seguenti documenti/formazioni HSE sono in scadenza tra <strong>5 giorni</strong>:<br><br>
{$docs_list}
<br>
<strong>È URGENTE</strong> rinnovare la documentazione per evitare problemi di accesso ai cantieri.<br><br>
Accedi immediatamente alla tua area privata per aggiornare i documenti.<br><br>";
            break;
            
        case 0:
            $body = $base_html . "
<div style='background: #dc3545; color: white; padding: 15px; margin-bottom: 20px;'>
<h3 style='margin: 0; color: white;'>❌ CRITICO - Documenti/Formazioni HSE Scaduti</h3>
</div>
Gentile {$rag_soc},<br><br>
<strong style='color: #dc3545;'>I seguenti documenti/formazioni HSE sono SCADUTI OGGI:</strong><br><br>
{$docs_list}
<br>
Il tuo accesso ai cantieri potrebbe essere sospeso se non provvedi immediatamente al rinnovo della documentazione.<br><br>
<strong>AGISCI SUBITO</strong> accedendo alla tua area privata.<br><br>";
            break;
            
        case -15:
            $body = $base_html . "
<div style='background: #721c24; color: white; padding: 15px; margin-bottom: 20px;'>
<h3 style='margin: 0; color: white;'>🔴 ACCESSO SOSPESO</h3>
</div>
Gentile {$rag_soc},<br><br>
Ti informiamo che il tuo accesso ai cantieri è stato <strong>sospeso automaticamente</strong> a causa dei seguenti documenti/formazioni HSE scaduti da oltre 15 giorni:<br><br>
{$docs_list}
<br>
Per ripristinare l'accesso è necessario:<br>
1. Aggiornare tutta la documentazione scaduta<br>
2. Contattare il gestore HSE: <a href='mailto:hse@cogei.net'>hse@cogei.net</a><br><br>
Non potrai accedere ai cantieri fino al ripristino dell'accesso.<br><br>";
            break;
            
        default:
            $body = $base_html . "
Gentile {$rag_soc},<br><br>
Ti informiamo che alcuni documenti/formazioni HSE necessitano di attenzione:<br><br>
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
 * Disattiva automaticamente un utente HSE
 */
function disableHseUser($user_id, $expired_docs) {
    $current_status = get_user_meta($user_id, 'hse_access_status', true);
    $rag_soc = get_user_meta($user_id, 'user_registration_rag_soc', true);
    
    // Costruisci lista documenti scaduti
    $docs_list = [];
    foreach ($expired_docs as $doc) {
        $docs_list[] = $doc['nome'] . " (scaduto da " . abs($doc['giorni']) . " giorni)";
    }
    $docs_text = implode(', ', $docs_list);
    
    // Log dell'azione
    $log_file = ABSPATH . 'log_mail/log_hse_disattivazioni.txt';
    
    if ($current_status === 'Sospeso') {
        // Utente già sospeso - solo log informativo
        $log_entry = "[" . date('d/m/Y H:i:s') . "] [USER ID: {$user_id}] Utente HSE \"{$rag_soc}\" già SOSPESO - Documenti scaduti: {$docs_text}\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        return false; // Non sospeso (già era sospeso)
    } else {
        // Sospendi utente
        update_user_meta($user_id, 'hse_access_status', 'Sospeso');
        $log_entry = "[" . date('d/m/Y H:i:s') . "] [USER ID: {$user_id}] Utente HSE \"{$rag_soc}\" SOSPESO automaticamente - Documenti scaduti: {$docs_text}\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        return true; // Sospeso
    }
}

/**
 * Notifica admin delle sospensioni
 */
function notifyAdminDisabling($disabled_users, $debug_mode) {
    global $admin_email;
    
    if (empty($disabled_users)) {
        return;
    }
    
    $subject = "CRON HSE: " . count($disabled_users) . " utenti sospesi automaticamente";
    
    $users_list = '';
    foreach ($disabled_users as $user_info) {
        $users_list .= "• ID: {$user_info['id']} - {$user_info['name']} ({$user_info['email']})<br>";
    }
    
    $body = "<html>
<head>
<title>Notifica Sospensioni Automatiche HSE</title>
</head>
<body>
<div style='background: #dc3545; color: white; padding: 20px; text-align: center; margin-bottom: 20px;'>
<h2 style='margin: 0; color: white;'>🚨 CRON HSE - Sospensioni Automatiche</h2>
</div>

<p>Ciao Admin,</p>

<p>Il cron di controllo scadenze HSE ha sospeso automaticamente <strong>" . count($disabled_users) . " utenti</strong> 
per documenti/formazioni scaduti da oltre 15 giorni.</p>

<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
<h3 style='margin: 0 0 10px 0; color: #721c24;'>Utenti sospesi:</h3>
{$users_list}
</div>

<p><strong>Data esecuzione:</strong> " . date('d/m/Y H:i:s') . "</p>

<div class='footer' style='background: #03679e; padding: 10px; margin-top: 30px;'>
<div style='text-align: center; color: white; font-size: 12px;'>
COGEI SRL - Sistema Cron Controllo Scadenze HSE<br>
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
    HseMailLogger::log([
        'ambiente' => $debug_mode ? 'DEBUG' : 'PROD',
        'tipo_email' => 'CRON_NOTIFICA_ADMIN_SOSPENSIONI',
        'destinatario' => $admin_email,
        'oggetto' => $subject,
        'user_id' => 0,
        'user_name' => 'Sistema Cron HSE',
        'user_email' => 'cron@cogei.net',
        'note' => count($disabled_users) . ' utenti sospesi',
        'email_sent' => !$debug_mode
    ]);
}

// ==================== ESECUZIONE PRINCIPALE ====================

echo "===================================================\n";
echo "CRON CONTROLLO SCADENZE HSE - " . date('d/m/Y H:i:s') . "\n";
echo "Modalità: " . ($debug_mode ? "DEBUG (no email reali)" : "PRODUZIONE") . "\n";
echo "===================================================\n\n";

// Ottieni tutti gli utenti con richieste HSE (utenti con ruolo subscriber che hanno richieste cantiere)
global $wpdb;
$hse_users = $wpdb->get_results("
    SELECT DISTINCT user_id 
    FROM {$wpdb->prefix}cantiere_richieste
", ARRAY_A);

$total_users = count($hse_users);
$processed = 0;
$emails_sent = 0;
$emails_skipped = 0;
$disabled_users = [];

echo "Trovati {$total_users} utenti HSE da controllare\n\n";

foreach ($hse_users as $hse_user_row) {
    $user_id = $hse_user_row['user_id'];
    $user = get_userdata($user_id);
    
    if (!$user) {
        continue;
    }
    
    $processed++;
    $rag_soc = get_user_meta($user_id, 'user_registration_rag_soc', true);
    
    echo "[$processed/$total_users] Controllo utente ID: $user_id - {$rag_soc}\n";
    
    // Ottieni tutti i documenti con scadenze
    $all_docs = getHseExpiringDocuments($user_id);
    
    if (empty($all_docs)) {
        echo "  → Nessun documento con scadenza trovato\n";
        continue;
    }
    
    echo "  → Trovati " . count($all_docs) . " documenti con scadenze\n";
    
    // Raggruppa documenti per trigger day
    $docs_by_trigger = [];
    foreach ($all_docs as $doc) {
        foreach ($trigger_days as $trigger) {
            if ($doc['giorni'] == $trigger) {
                if (!isset($docs_by_trigger[$trigger])) {
                    $docs_by_trigger[$trigger] = [];
                }
                $docs_by_trigger[$trigger][] = $doc;
            }
        }
    }
    
    // Invia notifiche per ogni trigger day
    foreach ($docs_by_trigger as $trigger => $docs) {
        echo "  → Trigger {$trigger} giorni: " . count($docs) . " documenti\n";
        
        $result = sendExpiryNotification($user_id, $trigger, $docs, $debug_mode);
        
        if ($result === 'skipped') {
            echo "    ✓ Email già inviata nelle ultime 24h (saltata)\n";
            $emails_skipped++;
        } elseif ($result) {
            echo "    ✓ Email inviata con successo\n";
            $emails_sent++;
        } else {
            echo "    ✗ Invio email fallito o simulato (debug)\n";
            if ($debug_mode) {
                $emails_sent++; // Conta come inviata in debug
            }
        }
        
        // Disattivazione automatica per trigger -15
        if ($trigger === -15) {
            $was_disabled = disableHseUser($user_id, $docs);
            if ($was_disabled) {
                echo "    🚨 UTENTE SOSPESO AUTOMATICAMENTE\n";
                $disabled_users[] = [
                    'id' => $user_id,
                    'name' => $rag_soc,
                    'email' => $user->user_email
                ];
            } else {
                echo "    ℹ️ Utente già sospeso\n";
            }
        }
    }
    
    echo "\n";
}

// Notifica admin delle sospensioni
if (!empty($disabled_users)) {
    $num_disabled = count($disabled_users);
    echo "Invio notifica admin per {$num_disabled} utenti sospesi...\n";
    notifyAdminDisabling($disabled_users, $debug_mode);
    echo "✓ Notifica admin inviata\n\n";
}

// Riepilogo finale
echo "===================================================\n";
echo "RIEPILOGO ESECUZIONE\n";
echo "===================================================\n";
echo "Utenti controllati: {$processed}/{$total_users}\n";
echo "Email inviate: {$emails_sent}\n";
echo "Email saltate (già inviate): {$emails_skipped}\n";
echo "Utenti sospesi: " . count($disabled_users) . "\n";
echo "===================================================\n";
echo "Esecuzione completata: " . date('d/m/Y H:i:s') . "\n";
echo "===================================================\n";

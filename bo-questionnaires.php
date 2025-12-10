<?php

/**
 * Plugin Snippet WordPress - Backoffice Gestione Questionari Fornitori
 * 
 * DESCRIZIONE:
 * Questo file fornisce un sistema completo e autonomo per la gestione dei questionari
 * destinati ai fornitori HSE. Include funzionalità per:
 * - Creazione e gestione questionari con aree tematiche
 * - Definizione domande e opzioni di risposta con pesi personalizzabili
 * - Invio questionari via email con token univoco
 * - Raccolta risposte e calcolo punteggi automatici
 * - Valutazione fornitori secondo soglie predefinite
 * 
 * TABELLE DATABASE CREATE:
 * - cogei_questionnaires: questionari (id, title, description, created_by, status, created_at, updated_at)
 * - cogei_areas: aree tematiche (id, questionnaire_id, title, weight, sort_order, created_at, updated_at)
 * - cogei_questions: domande (id, area_id, text, is_required, sort_order, created_at, updated_at)
 * - cogei_options: opzioni risposta (id, question_id, text, weight, sort_order)
 * - cogei_assignments: assegnazioni questionari (id, questionnaire_id, target_user_id (HSE), inspector_email, sent_by, sent_at, status, token)
 * - cogei_responses: risposte (id, assignment_id, question_id, selected_option_id, computed_score, answered_at)
 * 
 * INTEGRAZIONE WORDPRESS:
 * Per utilizzare questo file in WordPress:
 * 1. Copiare il file nella root del tema o plugin
 * 2. Includere il file nel template: require_once('bo-questionnaires.php');
 * 3. Oppure usare uno shortcode o do_action personalizzato
 * 
 * CONVENZIONI:
 * Questo file segue le stesse convenzioni utilizzate in:
 * - BO HSE (gestione cantieri e HSE)
 * - BO ALBO FORNITORI (gestione fornitori)
 * - FRONT HSE (form pubblici HSE)
 * Verificare questi file per comprendere meglio i pattern utilizzati.
 * 
 * @version 1.0
 * @author Cogei System
 */

// Verifica che sia WordPress
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
date_default_timezone_set('Europe/Rome');

// ================== CONFIGURAZIONE ==================
$inviamail = true; // Email ATTIVATE - Le email vengono inviate

// ================== CREAZIONE TABELLE DATABASE ==================

/**
 * Crea le tabelle necessarie per il sistema questionari
 * Pattern analogo a quello usato in BO HSE e BO ALBO FORNITORI
 */
function boq_createQuestionnaireTablesIfNotExists() {
    global $wpdb;
    
    // 📋 TABELLA QUESTIONARI
    $table_questionnaires = $wpdb->prefix . 'cogei_questionnaires';
    $sql_questionnaires = "CREATE TABLE IF NOT EXISTS $table_questionnaires (
        id int(11) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text,
        created_by int(11),
        status enum('draft','published') DEFAULT 'draft',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY created_by (created_by),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_questionnaires);
    
    // 📊 TABELLA AREE TEMATICHE
    $table_areas = $wpdb->prefix . 'cogei_areas';
    $sql_areas = "CREATE TABLE IF NOT EXISTS $table_areas (
        id int(11) NOT NULL AUTO_INCREMENT,
        questionnaire_id int(11) NOT NULL,
        title varchar(255) NOT NULL,
        weight decimal(5,2) DEFAULT 1.00,
        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY questionnaire_id (questionnaire_id),
        KEY sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_areas);
    
    // ❓ TABELLA DOMANDE
    $table_questions = $wpdb->prefix . 'cogei_questions';
    $sql_questions = "CREATE TABLE IF NOT EXISTS $table_questions (
        id int(11) NOT NULL AUTO_INCREMENT,
        area_id int(11) NOT NULL,
        text text NOT NULL,
        is_required tinyint(1) DEFAULT 1,
        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY area_id (area_id),
        KEY sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_questions);
    
    // ✅ TABELLA OPZIONI RISPOSTA
    $table_options = $wpdb->prefix . 'cogei_options';
    $sql_options = "CREATE TABLE IF NOT EXISTS $table_options (
        id int(11) NOT NULL AUTO_INCREMENT,
        question_id int(11) NOT NULL,
        text varchar(255) NOT NULL,
        weight decimal(5,2) DEFAULT 0.00,
        sort_order int(11) DEFAULT 0,
        PRIMARY KEY (id),
        KEY question_id (question_id),
        KEY sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_options);
    
    // 📤 TABELLA ASSEGNAZIONI
    $table_assignments = $wpdb->prefix . 'cogei_assignments';
    $sql_assignments = "CREATE TABLE IF NOT EXISTS $table_assignments (
        id int(11) NOT NULL AUTO_INCREMENT,
        questionnaire_id int(11) NOT NULL,
        target_user_id int(11) NOT NULL,
        inspector_email varchar(255) NOT NULL,
        sent_by int(11),
        sent_at datetime DEFAULT CURRENT_TIMESTAMP,
        status enum('pending','completed','expired') DEFAULT 'pending',
        token varchar(64) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY questionnaire_id (questionnaire_id),
        KEY target_user_id (target_user_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_assignments);
    
    // Migrazione: rinomina target_email in inspector_email se esiste
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_assignments LIKE 'target_email'");
    if (!empty($columns)) {
        $wpdb->query("ALTER TABLE $table_assignments CHANGE target_email inspector_email VARCHAR(255) NOT NULL");
    }
    
    // Migrazione: rendi target_user_id NOT NULL se necessario
    $column_info = $wpdb->get_row("SHOW COLUMNS FROM $table_assignments LIKE 'target_user_id'");
    if ($column_info && strpos($column_info->Type, 'int') !== false && strpos($column_info->Null, 'YES') !== false) {
        // Prima imposta un valore di default per i record esistenti senza target_user_id
        $wpdb->query("UPDATE $table_assignments SET target_user_id = 0 WHERE target_user_id IS NULL");
        $wpdb->query("ALTER TABLE $table_assignments MODIFY target_user_id INT(11) NOT NULL");
    }
    
    // 📝 TABELLA RISPOSTE
    $table_responses = $wpdb->prefix . 'cogei_responses';
    $sql_responses = "CREATE TABLE IF NOT EXISTS $table_responses (
        id int(11) NOT NULL AUTO_INCREMENT,
        assignment_id int(11) NOT NULL,
        question_id int(11) NOT NULL,
        selected_option_id int(11) NOT NULL,
        computed_score decimal(10,4) DEFAULT 0.0000,
        answered_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY assignment_id (assignment_id),
        KEY question_id (question_id),
        KEY selected_option_id (selected_option_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_responses);
    
    error_log("Tabelle questionari create/verificate: $table_questionnaires, $table_areas, $table_questions, $table_options, $table_assignments, $table_responses");
}

// Esegui creazione tabelle
boq_createQuestionnaireTablesIfNotExists();


// ================== FUNZIONI HELPER ==================

/**
 * Genera un token univoco per l'assegnazione
 */
function boq_generateToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Ottieni questionario per ID
 */
function boq_getQuestionnaire($questionnaire_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cogei_questionnaires WHERE id = %d",
        $questionnaire_id
    ), ARRAY_A);
}

/**
 * Ottieni aree di un questionario
 */
function boq_getAreas($questionnaire_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d ORDER BY sort_order ASC",
        $questionnaire_id
    ), ARRAY_A);
}

/**
 * Ottieni domande di un'area
 */
function boq_getQuestions($area_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cogei_questions WHERE area_id = %d ORDER BY sort_order ASC",
        $area_id
    ), ARRAY_A);
}

/**
 * Ottieni opzioni di una domanda
 */
function boq_getOptions($question_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cogei_options WHERE question_id = %d ORDER BY sort_order ASC",
        $question_id
    ), ARRAY_A);
}

/**
 * Ottieni assignment per token
 */
function boq_getAssignmentByToken($token) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cogei_assignments WHERE token = %s",
        $token
    ), ARRAY_A);
}

/**
 * Ottieni risposte di un assignment
 */
function boq_getResponses($assignment_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cogei_responses WHERE assignment_id = %d",
        $assignment_id
    ), ARRAY_A);
}

/**
 * Calcola punteggio per un assignment
 * Formula: punteggio_domanda = option.weight * area.weight
 * Punteggio_finale = sum(punteggio_domanda) / numero_domande (normalizzato 0-1)
 */
function boq_calculateScore($assignment_id) {
    global $wpdb;
    
    $responses = boq_getResponses($assignment_id);
    if (empty($responses)) {
        return 0;
    }
    
    $total_score = 0;
    $count = 0;
    
    foreach ($responses as $response) {
        $question_id = $response['question_id'];
        $option_id = $response['selected_option_id'];
        
        // Ottieni peso opzione
        $option = $wpdb->get_row($wpdb->prepare(
            "SELECT weight FROM {$wpdb->prefix}cogei_options WHERE id = %d",
            $option_id
        ), ARRAY_A);
        
        if (!$option) continue;
        
        // Ottieni area della domanda
        $question = $wpdb->get_row($wpdb->prepare(
            "SELECT area_id FROM {$wpdb->prefix}cogei_questions WHERE id = %d",
            $question_id
        ), ARRAY_A);
        
        if (!$question) continue;
        
        // Ottieni peso area
        $area = $wpdb->get_row($wpdb->prepare(
            "SELECT weight FROM {$wpdb->prefix}cogei_areas WHERE id = %d",
            $question['area_id']
        ), ARRAY_A);
        
        if (!$area) continue;
        
        // Calcola punteggio domanda = peso_opzione * peso_area
        $question_score = floatval($option['weight']) * floatval($area['weight']);
        $total_score += $question_score;
        $count++;
    }
    
    // Normalizza il punteggio (0-1)
    if ($count > 0) {
        return $total_score / $count;
    }
    
    return 0;
}

/**
 * Valuta il punteggio secondo le soglie definite
 * Soglie di riferimento:
 * >= 0.85 = Eccellente
 * >= 0.70 = Molto Buono
 * >= 0.55 = Adeguato
 * >= 0.40 = Critico
 * < 0.40 = Inadeguato
 */
function boq_evaluateScore($score) {
    if ($score >= 0.85) {
        return 'Eccellente';
    } elseif ($score >= 0.70) {
        return 'Molto Buono';
    } elseif ($score >= 0.55) {
        return 'Adeguato';
    } elseif ($score >= 0.40) {
        return 'Critico';
    } else {
        return 'Inadeguato';
    }
}

// ================== FUNZIONI EMAIL ==================

/**
 * Invia email con link questionario
 */
function boq_sendQuestionnaireEmail($assignment_id) {
    global $wpdb, $inviamail;
    
    $assignment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cogei_assignments WHERE id = %d",
        $assignment_id
    ), ARRAY_A);
    
    if (!$assignment) {
        return false;
    }
    
    $questionnaire = boq_getQuestionnaire($assignment['questionnaire_id']);
    if (!$questionnaire) {
        return false;
    }
    
    // Get HSE user info
    $hse_user = get_userdata($assignment['target_user_id']);
    $hse_name = $hse_user ? $hse_user->display_name : 'N/A';
    
    $token = $assignment['token'];
    $inspector_email = $assignment['inspector_email'];
    
    // Genera link
    $link = add_query_arg('boq_token', $token, site_url());
    
    $to = $inspector_email;
    $subject = "Questionario Valutazione HSE - " . esc_html($questionnaire['title']);
    
    $body = "<html>
<head>
<title>Questionario Valutazione HSE</title>
</head>
<body>
<div style='background: #03679e; text-align: center; padding: 10px; margin-bottom: 30px;'>
    <img style='max-width: 150px;' src='https://cogei.provasiti.it/cogei/wp-content/uploads/2023/02/logo_bianco-1.png' />
</div>
<p>Gentile Ispettore,</p>
<p>Le è stato assegnato il seguente questionario per valutare l'operato dell'utente HSE: <strong>" . esc_html($hse_name) . "</strong></p>
<h3>" . esc_html($questionnaire['title']) . "</h3>
<p>" . esc_html($questionnaire['description']) . "</p>
<p>Per compilare il questionario, clicchi sul link seguente:</p>
<p><a href='" . esc_url($link) . "' style='display: inline-block; padding: 10px 20px; background: #03679e; color: white; text-decoration: none; border-radius: 5px;'>Compila Questionario</a></p>
<p>Il link è personale e non deve essere condiviso.</p>
<br>
<p>Cordiali Saluti,<br>Cogei S.r.l.</p>
<div class='footer' style='background: #03679e; padding: 10px; margin-top: 20px;'>
<div class='rigainfofo primariga'><a style='color: white; text-decoration: none;' href='#' target='_blank' rel='noopener'>Via Francesco Lomonaco, 3 - 80121 Napoli</a></div>
<div class='rigainfofo'><a style='color: white; text-decoration: none;' href='tel:+390812303782'>TEL: +39 081.230.37.82</a></div>
<div class='rigainfofo primariga'><a style='color: white; text-decoration: none;' href='mailto:cogei@pec.cogei.net'>PEC: cogei@pec.cogei.net</a></div>
<div style='margin-top: 40px; text-align: center; color: white; font-size: 12px !important;'>COGEI SRL - P.IVA: IT06569020636 - Copyright © 2023 Cogei. All Rights Reserved.</div>
</div>
</body>
</html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: <no-reply@cogei.net>' . "\r\n";
    
    $email_sent = false;
    if ($inviamail) {
        $email_sent = wp_mail($to, $subject, $body, $headers);
    }
    
    // Log (opzionale - può essere integrato con sistema logging esistente)
    error_log("Email questionario inviata a $inspector_email per valutare HSE user ID " . $assignment['target_user_id'] . " ($hse_name) - Token: $token - Sent: " . ($email_sent ? 'YES' : 'NO'));
    
    return $email_sent;
}


// ================== EXPORT CSV ==================

/**
 * Esporta risultati in CSV
 */
if (isset($_GET['boq_csv_export']) && $_GET['boq_csv_export'] === '1') {
    if (ob_get_contents()) ob_clean();
    
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="questionari_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo "ID,Questionario,Utente HSE,Email Ispettore,Data Invio,Stato,Punteggio,Valutazione\n";
    
    $assignments = $wpdb->get_results("
        SELECT a.*, q.title as questionnaire_title 
        FROM {$wpdb->prefix}cogei_assignments a
        LEFT JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
        ORDER BY a.sent_at DESC
    ", ARRAY_A);
    
    foreach ($assignments as $assignment) {
        $score = 0;
        $evaluation = 'N/A';
        
        if ($assignment['status'] === 'completed') {
            $score = boq_calculateScore($assignment['id']);
            $evaluation = boq_evaluateScore($score);
        }
        
        $hse_user_name = 'N/A';
        if ($assignment['target_user_id']) {
            $user = get_userdata($assignment['target_user_id']);
            if ($user) {
                $hse_user_name = $user->display_name;
            }
        }
        
        $row = [
            $assignment['id'],
            '"' . str_replace('"', '""', $assignment['questionnaire_title'] ?: 'N/A') . '"',
            '"' . str_replace('"', '""', $hse_user_name) . '"',
            '"' . str_replace('"', '""', $assignment['inspector_email']) . '"',
            '"' . date('d/m/Y H:i', strtotime($assignment['sent_at'])) . '"',
            '"' . $assignment['status'] . '"',
            round($score, 4),
            '"' . $evaluation . '"'
        ];
        
        echo implode(',', $row) . "\n";
    }
    
    exit;
}

// ================== GESTIONE ADMIN ACTIONS ==================

// Verifica nonce per sicurezza
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['boq_action'])) {
    // Verifica nonce
    if (!isset($_POST['boq_nonce']) || !wp_verify_nonce($_POST['boq_nonce'], 'boq_admin_action')) {
        wp_die('Verifica di sicurezza fallita');
    }
    
    $action = sanitize_text_field($_POST['boq_action']);
    
    // AZIONE: Crea/Aggiorna Questionario
    if ($action === 'save_questionnaire') {
        $questionnaire_id = isset($_POST['questionnaire_id']) ? intval($_POST['questionnaire_id']) : 0;
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $status = sanitize_text_field($_POST['status']);
        
        $data = [
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'created_by' => get_current_user_id()
        ];
        
        if ($questionnaire_id > 0) {
            // Update
            $wpdb->update(
                $wpdb->prefix . 'cogei_questionnaires',
                $data,
                ['id' => $questionnaire_id],
                ['%s', '%s', '%s', '%d'],
                ['%d']
            );
            $message = "Questionario aggiornato con successo";
        } else {
            // Insert
            $wpdb->insert(
                $wpdb->prefix . 'cogei_questionnaires',
                $data,
                ['%s', '%s', '%s', '%d']
            );
            $questionnaire_id = $wpdb->insert_id;
            $message = "Questionario creato con successo (ID: $questionnaire_id)";
        }
        
        echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
    }
    
    // AZIONE: Elimina Questionario
    if ($action === 'delete_questionnaire') {
        $questionnaire_id = intval($_POST['questionnaire_id']);
        
        // Elimina questionario e dati correlati
        $wpdb->delete($wpdb->prefix . 'cogei_questionnaires', ['id' => $questionnaire_id], ['%d']);
        
        echo '<div class="notice notice-success"><p>Questionario eliminato con successo</p></div>';
    }
    
    // AZIONE: Salva Area
    if ($action === 'save_area') {
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        $questionnaire_id = intval($_POST['questionnaire_id']);
        $title = sanitize_text_field($_POST['area_title']);
        $weight = floatval($_POST['area_weight']);
        $sort_order = intval($_POST['area_sort_order']);
        
        $data = [
            'questionnaire_id' => $questionnaire_id,
            'title' => $title,
            'weight' => $weight,
            'sort_order' => $sort_order
        ];
        
        if ($area_id > 0) {
            $wpdb->update(
                $wpdb->prefix . 'cogei_areas',
                $data,
                ['id' => $area_id],
                ['%d', '%s', '%f', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'cogei_areas',
                $data,
                ['%d', '%s', '%f', '%d']
            );
        }
        
        echo '<div class="notice notice-success"><p>Area salvata con successo</p></div>';
    }
    
    // AZIONE: Elimina Area
    if ($action === 'delete_area') {
        $area_id = intval($_POST['area_id']);
        $wpdb->delete($wpdb->prefix . 'cogei_areas', ['id' => $area_id], ['%d']);
        
        echo '<div class="notice notice-success"><p>Area eliminata con successo</p></div>';
    }
    
    // AZIONE: Salva Domanda
    if ($action === 'save_question') {
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $area_id = intval($_POST['area_id']);
        $text = sanitize_textarea_field($_POST['question_text']);
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $sort_order = intval($_POST['question_sort_order']);
        
        $data = [
            'area_id' => $area_id,
            'text' => $text,
            'is_required' => $is_required,
            'sort_order' => $sort_order
        ];
        
        if ($question_id > 0) {
            $wpdb->update(
                $wpdb->prefix . 'cogei_questions',
                $data,
                ['id' => $question_id],
                ['%d', '%s', '%d', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'cogei_questions',
                $data,
                ['%d', '%s', '%d', '%d']
            );
        }
        
        echo '<div class="notice notice-success"><p>Domanda salvata con successo</p></div>';
    }
    
    // AZIONE: Elimina Domanda
    if ($action === 'delete_question') {
        $question_id = intval($_POST['question_id']);
        $wpdb->delete($wpdb->prefix . 'cogei_questions', ['id' => $question_id], ['%d']);
        
        echo '<div class="notice notice-success"><p>Domanda eliminata con successo</p></div>';
    }
    
    // AZIONE: Salva Opzione
    if ($action === 'save_option') {
        $option_id = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
        $question_id = intval($_POST['question_id']);
        $text = sanitize_text_field($_POST['option_text']);
        $weight = floatval($_POST['option_weight']);
        $sort_order = intval($_POST['option_sort_order']);
        
        $data = [
            'question_id' => $question_id,
            'text' => $text,
            'weight' => $weight,
            'sort_order' => $sort_order
        ];
        
        if ($option_id > 0) {
            $wpdb->update(
                $wpdb->prefix . 'cogei_options',
                $data,
                ['id' => $option_id],
                ['%d', '%s', '%f', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'cogei_options',
                $data,
                ['%d', '%s', '%f', '%d']
            );
        }
        
        echo '<div class="notice notice-success"><p>Opzione salvata con successo</p></div>';
    }
    
    // AZIONE: Elimina Opzione
    if ($action === 'delete_option') {
        $option_id = intval($_POST['option_id']);
        $wpdb->delete($wpdb->prefix . 'cogei_options', ['id' => $option_id], ['%d']);
        
        echo '<div class="notice notice-success"><p>Opzione eliminata con successo</p></div>';
    }
    
    // AZIONE: Invia Questionario
    if ($action === 'send_questionnaire') {
        $questionnaire_id = intval($_POST['questionnaire_id']);
        $target_user_id = intval($_POST['target_user_id']);
        $inspector_email = sanitize_email($_POST['inspector_email']);
        
        // Validazione: target_user_id è obbligatorio
        if (empty($target_user_id) || $target_user_id <= 0) {
            echo '<div class="notice notice-error"><p>Devi selezionare un utente HSE</p></div>';
        } elseif (empty($inspector_email)) {
            echo '<div class="notice notice-error"><p>Email ispettore è obbligatoria</p></div>';
        } else {
            // Verifica che l'utente HSE esista
            $user = get_userdata($target_user_id);
            if (!$user) {
                echo '<div class="notice notice-error"><p>Utente HSE non valido</p></div>';
            } else {
                $token = boq_generateToken();
                
                $assignment_data = [
                    'questionnaire_id' => $questionnaire_id,
                    'target_user_id' => $target_user_id,
                    'inspector_email' => $inspector_email,
                    'sent_by' => get_current_user_id(),
                    'token' => $token,
                    'status' => 'pending'
                ];
                
                $wpdb->insert(
                    $wpdb->prefix . 'cogei_assignments',
                    $assignment_data,
                    ['%d', '%d', '%s', '%d', '%s', '%s']
                );
                
                $assignment_id = $wpdb->insert_id;
                
                // Invia email
                $email_sent = boq_sendQuestionnaireEmail($assignment_id);
                
                if ($email_sent) {
                    echo '<div class="notice notice-success"><p>Questionario inviato con successo a ' . esc_html($inspector_email) . ' per valutare l\'utente HSE: ' . esc_html($user->display_name) . '</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>Questionario creato ma email non inviata. Token: ' . esc_html($token) . '</p></div>';
                }
            }
        }
    }
}


// ================== HANDLER PUBBLICO RISPOSTE ==================

/**
 * Handler per la compilazione del questionario via token
 */
if (isset($_GET['boq_token']) && !empty($_GET['boq_token'])) {
    $token = sanitize_text_field($_GET['boq_token']);
    $assignment = boq_getAssignmentByToken($token);
    
    if (!$assignment) {
        wp_die('Token non valido o scaduto');
    }
    
    if ($assignment['status'] === 'completed') {
        echo '<div style="max-width: 800px; margin: 50px auto; padding: 20px; font-family: Arial, sans-serif;">';
        echo '<h2>Questionario già compilato</h2>';
        echo '<p>Questo questionario è già stato completato.</p>';
        $score = boq_calculateScore($assignment['id']);
        $evaluation = boq_evaluateScore($score);
        echo '<p><strong>Punteggio:</strong> ' . round($score, 4) . '</p>';
        echo '<p><strong>Valutazione:</strong> ' . esc_html($evaluation) . '</p>';
        echo '</div>';
        exit;
    }
    
    // Gestione submit risposte
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['boq_submit_responses'])) {
        if (!isset($_POST['boq_response_nonce']) || !wp_verify_nonce($_POST['boq_response_nonce'], 'boq_submit_' . $token)) {
            wp_die('Verifica di sicurezza fallita');
        }
        
        $questionnaire = boq_getQuestionnaire($assignment['questionnaire_id']);
        $areas = boq_getAreas($assignment['questionnaire_id']);
        
        $responses = isset($_POST['responses']) ? $_POST['responses'] : [];
        
        // Salva risposte
        foreach ($responses as $question_id => $option_id) {
            $question_id = intval($question_id);
            $option_id = intval($option_id);
            
            // Ottieni informazioni per calcolare il punteggio
            $option = $wpdb->get_row($wpdb->prepare(
                "SELECT weight FROM {$wpdb->prefix}cogei_options WHERE id = %d",
                $option_id
            ), ARRAY_A);
            
            $question = $wpdb->get_row($wpdb->prepare(
                "SELECT area_id FROM {$wpdb->prefix}cogei_questions WHERE id = %d",
                $question_id
            ), ARRAY_A);
            
            $area = $wpdb->get_row($wpdb->prepare(
                "SELECT weight FROM {$wpdb->prefix}cogei_areas WHERE id = %d",
                $question['area_id']
            ), ARRAY_A);
            
            $computed_score = floatval($option['weight']) * floatval($area['weight']);
            
            $wpdb->insert(
                $wpdb->prefix . 'cogei_responses',
                [
                    'assignment_id' => $assignment['id'],
                    'question_id' => $question_id,
                    'selected_option_id' => $option_id,
                    'computed_score' => $computed_score
                ],
                ['%d', '%d', '%d', '%f']
            );
        }
        
        // Aggiorna status assignment
        $wpdb->update(
            $wpdb->prefix . 'cogei_assignments',
            ['status' => 'completed'],
            ['id' => $assignment['id']],
            ['%s'],
            ['%d']
        );
        
        // Mostra risultato
        $final_score = boq_calculateScore($assignment['id']);
        $evaluation = boq_evaluateScore($final_score);
        
        echo '<div style="max-width: 800px; margin: 50px auto; padding: 20px; font-family: Arial, sans-serif; background: #f0f0f0; border-radius: 10px;">';
        echo '<h2 style="color: #03679e;">Questionario completato con successo!</h2>';
        echo '<p>Grazie per aver compilato il questionario.</p>';
        echo '<div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px;">';
        echo '<h3>Risultato Valutazione</h3>';
        echo '<p><strong>Punteggio:</strong> ' . round($final_score, 4) . ' / 1.00</p>';
        echo '<p><strong>Valutazione:</strong> <span style="font-size: 1.5em; color: #03679e;">' . esc_html($evaluation) . '</span></p>';
        echo '</div>';
        echo '</div>';
        exit;
    }
    
    // Mostra form questionario
    $questionnaire = boq_getQuestionnaire($assignment['questionnaire_id']);
    $areas = boq_getAreas($assignment['questionnaire_id']);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($questionnaire['title']); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f5f5f5;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 900px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                background: #03679e;
                text-align: center;
                padding: 20px;
                margin: -30px -30px 30px -30px;
                border-radius: 10px 10px 0 0;
            }
            .header img {
                max-width: 150px;
            }
            h1 {
                color: #03679e;
                margin-top: 0;
            }
            .description {
                color: #666;
                margin-bottom: 30px;
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #03679e;
            }
            .area {
                margin-bottom: 30px;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 5px;
            }
            .area-title {
                color: #03679e;
                font-size: 1.2em;
                font-weight: bold;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #03679e;
            }
            .question {
                margin-bottom: 20px;
                padding: 15px;
                background: white;
                border-radius: 5px;
            }
            .question-text {
                font-weight: bold;
                margin-bottom: 10px;
                color: #333;
            }
            .question-text .required {
                color: red;
            }
            .option {
                margin: 8px 0;
                padding: 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .option:hover {
                background: #f0f0f0;
            }
            .option label {
                cursor: pointer;
                display: block;
            }
            .submit-btn {
                background: #03679e;
                color: white;
                padding: 15px 40px;
                border: none;
                border-radius: 5px;
                font-size: 1.1em;
                cursor: pointer;
                display: block;
                margin: 30px auto 0;
            }
            .submit-btn:hover {
                background: #025a85;
            }
            .footer {
                background: #03679e;
                padding: 20px;
                margin: 30px -30px -30px -30px;
                border-radius: 0 0 10px 10px;
                color: white;
                text-align: center;
                font-size: 0.9em;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://cogei.provasiti.it/cogei/wp-content/uploads/2023/02/logo_bianco-1.png" alt="Cogei">
            </div>
            
            <h1><?php echo esc_html($questionnaire['title']); ?></h1>
            
            <?php if (!empty($questionnaire['description'])): ?>
                <div class="description">
                    <?php echo nl2br(esc_html($questionnaire['description'])); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <?php wp_nonce_field('boq_submit_' . $token, 'boq_response_nonce'); ?>
                
                <?php foreach ($areas as $area): ?>
                    <div class="area">
                        <div class="area-title">
                            <?php echo esc_html($area['title']); ?>
                            <span style="font-size: 0.8em; color: #666;">(Peso: <?php echo esc_html($area['weight']); ?>)</span>
                        </div>
                        
                        <?php 
                        $questions = boq_getQuestions($area['id']);
                        foreach ($questions as $question): 
                        ?>
                            <div class="question">
                                <div class="question-text">
                                    <?php echo esc_html($question['text']); ?>
                                    <?php if ($question['is_required']): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php 
                                $options = boq_getOptions($question['id']);
                                foreach ($options as $option): 
                                ?>
                                    <div class="option">
                                        <label>
                                            <input type="radio" 
                                                   name="responses[<?php echo $question['id']; ?>]" 
                                                   value="<?php echo $option['id']; ?>"
                                                   <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                            <?php echo esc_html($option['text']); ?>
                                            <span style="color: #999; font-size: 0.9em;">(Peso: <?php echo esc_html($option['weight']); ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" name="boq_submit_responses" class="submit-btn">Invia Risposte</button>
            </form>
            
            <div class="footer">
                <div>Via Francesco Lomonaco, 3 - 80121 Napoli</div>
                <div>TEL: +39 081.230.37.82</div>
                <div>PEC: cogei@pec.cogei.net</div>
                <div style="margin-top: 20px; font-size: 0.8em;">COGEI SRL - P.IVA: IT06569020636 - Copyright © 2023 Cogei. All Rights Reserved.</div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}


// ================== INTERFACCIA AMMINISTRATIVA ==================

/**
 * Interfaccia Admin per gestione questionari
 * Stile coerente con BO HSE e BO ALBO FORNITORI
 */
function boq_renderAdminInterface() {
    global $wpdb;
    
    // Determina tab attivo
    $active_tab = isset($_GET['boq_tab']) ? sanitize_text_field($_GET['boq_tab']) : 'questionnaires';
    
    ?>
    <div class="wrap" style="max-width: 1400px; margin: 20px auto;">
        <h1 style="color: #03679e;">🎯 Gestione Questionari Fornitori</h1>
        
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            
            <!-- Tabs Navigation -->
            <div style="border-bottom: 2px solid #03679e; margin-bottom: 20px;">
                <a href="?boq_tab=questionnaires" 
                   style="display: inline-block; padding: 10px 20px; text-decoration: none; <?php echo $active_tab === 'questionnaires' ? 'background: #03679e; color: white;' : 'color: #03679e;'; ?>">
                    Questionari
                </a>
                <a href="?boq_tab=assignments" 
                   style="display: inline-block; padding: 10px 20px; text-decoration: none; <?php echo $active_tab === 'assignments' ? 'background: #03679e; color: white;' : 'color: #03679e;'; ?>">
                    Invii
                </a>
                <a href="?boq_tab=results" 
                   style="display: inline-block; padding: 10px 20px; text-decoration: none; <?php echo $active_tab === 'results' ? 'background: #03679e; color: white;' : 'color: #03679e;'; ?>">
                    Risultati
                </a>
                <a href="?boq_csv_export=1" 
                   style="display: inline-block; padding: 10px 20px; text-decoration: none; color: #03679e; float: right;">
                    📥 Esporta CSV
                </a>
            </div>
            
            <?php if ($active_tab === 'questionnaires'): ?>
                <?php boq_renderQuestionnairesTab(); ?>
            <?php elseif ($active_tab === 'assignments'): ?>
                <?php boq_renderAssignmentsTab(); ?>
            <?php elseif ($active_tab === 'results'): ?>
                <?php boq_renderResultsTab(); ?>
            <?php endif; ?>
            
        </div>
    </div>
    <?php
}

/**
 * Tab: Gestione Questionari
 */
function boq_renderQuestionnairesTab() {
    global $wpdb;
    
    $questionnaire_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $questionnaire = $questionnaire_id > 0 ? boq_getQuestionnaire($questionnaire_id) : null;
    
    ?>
    <div style="margin-bottom: 30px;">
        <h2>
            <?php echo $questionnaire ? 'Modifica Questionario' : 'Crea Nuovo Questionario'; ?>
        </h2>
        
        <form method="POST" style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
            <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
            <input type="hidden" name="boq_action" value="save_questionnaire">
            <?php if ($questionnaire): ?>
                <input type="hidden" name="questionnaire_id" value="<?php echo $questionnaire['id']; ?>">
            <?php endif; ?>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Titolo *</label>
                <input type="text" name="title" required
                       value="<?php echo $questionnaire ? esc_attr($questionnaire['title']) : ''; ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Descrizione</label>
                <textarea name="description" rows="4"
                          style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;"><?php echo $questionnaire ? esc_textarea($questionnaire['description']) : ''; ?></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Stato</label>
                <select name="status" style="padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                    <option value="draft" <?php echo ($questionnaire && $questionnaire['status'] === 'draft') ? 'selected' : ''; ?>>Bozza</option>
                    <option value="published" <?php echo ($questionnaire && $questionnaire['status'] === 'published') ? 'selected' : ''; ?>>Pubblicato</option>
                </select>
            </div>
            
            <button type="submit" style="background: #03679e; color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer;">
                Salva Questionario
            </button>
            
            <?php if ($questionnaire): ?>
                <a href="?" style="display: inline-block; padding: 10px 30px; margin-left: 10px; text-decoration: none; color: #666;">
                    Annulla
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if ($questionnaire): ?>
        <!-- Gestione Aree, Domande, Opzioni -->
        <div style="margin-top: 40px;">
            <h2>Aree e Domande</h2>
            <?php boq_renderAreasEditor($questionnaire['id']); ?>
        </div>
    <?php endif; ?>
    
    <hr style="margin: 40px 0; border: none; border-top: 2px solid #ddd;">
    
    <!-- Lista Questionari -->
    <h2>Questionari Esistenti</h2>
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background: #03679e; color: white;">
                <th style="padding: 12px; text-align: left;">ID</th>
                <th style="padding: 12px; text-align: left;">Titolo</th>
                <th style="padding: 12px; text-align: left;">Stato</th>
                <th style="padding: 12px; text-align: left;">Data Creazione</th>
                <th style="padding: 12px; text-align: center;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $questionnaires = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cogei_questionnaires ORDER BY created_at DESC", ARRAY_A);
            foreach ($questionnaires as $q):
            ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;"><?php echo $q['id']; ?></td>
                <td style="padding: 12px;"><strong><?php echo esc_html($q['title']); ?></strong></td>
                <td style="padding: 12px;">
                    <span style="padding: 4px 12px; border-radius: 3px; <?php echo $q['status'] === 'published' ? 'background: #4caf50; color: white;' : 'background: #ff9800; color: white;'; ?>">
                        <?php echo esc_html($q['status']); ?>
                    </span>
                </td>
                <td style="padding: 12px;"><?php echo date('d/m/Y H:i', strtotime($q['created_at'])); ?></td>
                <td style="padding: 12px; text-align: center;">
                    <a href="?boq_tab=questionnaires&edit=<?php echo $q['id']; ?>" style="color: #03679e; text-decoration: none; margin: 0 5px;">✏️ Modifica</a>
                    |
                    <a href="?boq_tab=assignments&send=<?php echo $q['id']; ?>" style="color: #4caf50; text-decoration: none; margin: 0 5px;">📤 Invia</a>
                    |
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminare questo questionario?');">
                        <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                        <input type="hidden" name="boq_action" value="delete_questionnaire">
                        <input type="hidden" name="questionnaire_id" value="<?php echo $q['id']; ?>">
                        <button type="submit" style="background: none; border: none; color: #f44336; cursor: pointer; text-decoration: underline;">🗑️ Elimina</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Editor per Aree, Domande e Opzioni
 */
function boq_renderAreasEditor($questionnaire_id) {
    global $wpdb;
    $areas = boq_getAreas($questionnaire_id);
    
    ?>
    <div style="background: #f0f0f0; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h3>Aggiungi Nuova Area</h3>
        <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end;">
            <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
            <input type="hidden" name="boq_action" value="save_area">
            <input type="hidden" name="questionnaire_id" value="<?php echo $questionnaire_id; ?>">
            
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Titolo Area</label>
                <input type="text" name="area_title" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Peso</label>
                <input type="number" name="area_weight" step="0.01" value="1.00" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Ordine</label>
                <input type="number" name="area_sort_order" value="0" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            
            <button type="submit" style="background: #4caf50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Aggiungi Area
            </button>
        </form>
    </div>
    
    <?php foreach ($areas as $area): ?>
        <div style="background: white; border: 2px solid #03679e; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: #03679e;">
                    📊 <?php echo esc_html($area['title']); ?>
                    <span style="font-size: 0.8em; color: #666;">(Peso: <?php echo $area['weight']; ?>, Ordine: <?php echo $area['sort_order']; ?>)</span>
                </h3>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminare questa area?');">
                    <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                    <input type="hidden" name="boq_action" value="delete_area">
                    <input type="hidden" name="area_id" value="<?php echo $area['id']; ?>">
                    <button type="submit" style="background: #f44336; color: white; padding: 5px 15px; border: none; border-radius: 3px; cursor: pointer;">🗑️ Elimina Area</button>
                </form>
            </div>
            
            <!-- Form Aggiungi Domanda -->
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <strong>Aggiungi Domanda:</strong>
                <form method="POST" style="margin-top: 10px;">
                    <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                    <input type="hidden" name="boq_action" value="save_question">
                    <input type="hidden" name="area_id" value="<?php echo $area['id']; ?>">
                    
                    <div style="display: grid; grid-template-columns: 3fr auto auto auto; gap: 10px; align-items: end;">
                        <div>
                            <textarea name="question_text" required placeholder="Testo della domanda..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;"></textarea>
                        </div>
                        <div>
                            <label><input type="checkbox" name="is_required" checked> Obbligatoria</label>
                        </div>
                        <div>
                            <input type="number" name="question_sort_order" value="0" placeholder="Ordine" style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                        </div>
                        <button type="submit" style="background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Aggiungi</button>
                    </div>
                </form>
            </div>
            
            <!-- Lista Domande -->
            <?php 
            $questions = boq_getQuestions($area['id']);
            foreach ($questions as $question): 
            ?>
                <div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <strong>❓ <?php echo esc_html($question['text']); ?></strong>
                            <?php if ($question['is_required']): ?>
                                <span style="color: red;">*</span>
                            <?php endif; ?>
                            <span style="font-size: 0.8em; color: #666;">(Ordine: <?php echo $question['sort_order']; ?>)</span>
                        </div>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminare questa domanda?');">
                            <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                            <input type="hidden" name="boq_action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                            <button type="submit" style="background: #f44336; color: white; padding: 3px 10px; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">🗑️</button>
                        </form>
                    </div>
                    
                    <!-- Form Aggiungi Opzione -->
                    <div style="background: #f0f0f0; padding: 10px; border-radius: 3px; margin-top: 10px;">
                        <form method="POST">
                            <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                            <input type="hidden" name="boq_action" value="save_option">
                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                            
                            <div style="display: grid; grid-template-columns: 2fr 1fr auto auto; gap: 10px; align-items: end;">
                                <input type="text" name="option_text" required placeholder="Testo opzione..." style="padding: 6px; border: 1px solid #ddd; border-radius: 3px;">
                                <input type="number" name="option_weight" step="0.01" value="0.00" required placeholder="Peso" style="padding: 6px; border: 1px solid #ddd; border-radius: 3px;">
                                <input type="number" name="option_sort_order" value="0" placeholder="Ordine" style="width: 80px; padding: 6px; border: 1px solid #ddd; border-radius: 3px;">
                                <button type="submit" style="background: #4caf50; color: white; padding: 6px 15px; border: none; border-radius: 3px; cursor: pointer;">+ Opzione</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Lista Opzioni -->
                    <?php 
                    $options = boq_getOptions($question['id']);
                    if (!empty($options)): 
                    ?>
                        <ul style="list-style: none; padding: 10px 0 0 0; margin: 10px 0 0 0;">
                            <?php foreach ($options as $option): ?>
                                <li style="padding: 8px; background: #fafafa; margin: 5px 0; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;">
                                    <span>
                                        ✓ <?php echo esc_html($option['text']); ?>
                                        <span style="color: #666; font-size: 0.9em;">(Peso: <?php echo $option['weight']; ?>, Ordine: <?php echo $option['sort_order']; ?>)</span>
                                    </span>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminare questa opzione?');">
                                        <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                                        <input type="hidden" name="boq_action" value="delete_option">
                                        <input type="hidden" name="option_id" value="<?php echo $option['id']; ?>">
                                        <button type="submit" style="background: #f44336; color: white; padding: 2px 8px; border: none; border-radius: 3px; cursor: pointer; font-size: 0.8em;">✕</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php
}


/**
 * Tab: Gestione Invii
 */
function boq_renderAssignmentsTab() {
    global $wpdb;
    
    $send_id = isset($_GET['send']) ? intval($_GET['send']) : 0;
    
    if ($send_id > 0) {
        $questionnaire = boq_getQuestionnaire($send_id);
        ?>
        <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
            <h2>📤 Invia Questionario: <?php echo esc_html($questionnaire['title']); ?></h2>
            <p style="background: #e3f2fd; padding: 12px; border-left: 4px solid #03679e; margin-bottom: 20px;">
                <strong>Nota:</strong> Il questionario verrà inviato all'ispettore per valutare l'operato dell'utente HSE selezionato.
            </p>
            
            <form method="POST">
                <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                <input type="hidden" name="boq_action" value="send_questionnaire">
                <input type="hidden" name="questionnaire_id" value="<?php echo $send_id; ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                        Utente HSE da Valutare * <span style="color: red;">(Obbligatorio)</span>
                    </label>
                    <select name="target_user_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                        <option value="">-- Seleziona l'utente HSE da valutare --</option>
                        <?php
                        // Cerca utenti con ruolo HSE o subscriber
                        $users = get_users(['role__in' => ['hse', 'subscriber']]);
                        foreach ($users as $user):
                        ?>
                            <option value="<?php echo $user->ID; ?>">
                                <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666;">Seleziona l'utente HSE il cui operato verrà valutato tramite questo questionario</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                        Email Ispettore * <span style="color: red;">(Obbligatorio)</span>
                    </label>
                    <input type="email" name="inspector_email" required
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;"
                           placeholder="ispettore@example.com">
                    <small style="color: #666;">Inserisci l'email dell'ispettore che compilerà il questionario di valutazione</small>
                </div>
                
                <button type="submit" style="background: #4caf50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em;">
                    📤 Invia Questionario
                </button>
                
                <a href="?boq_tab=assignments" style="display: inline-block; padding: 12px 30px; margin-left: 10px; text-decoration: none; color: #666;">
                    Annulla
                </a>
            </form>
        </div>
        <?php
    }
    ?>
    
    <h2>Storico Invii</h2>
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background: #03679e; color: white;">
                <th style="padding: 12px; text-align: left;">ID</th>
                <th style="padding: 12px; text-align: left;">Questionario</th>
                <th style="padding: 12px; text-align: left;">Utente HSE</th>
                <th style="padding: 12px; text-align: left;">Email Ispettore</th>
                <th style="padding: 12px; text-align: left;">Data Invio</th>
                <th style="padding: 12px; text-align: center;">Stato</th>
                <th style="padding: 12px; text-align: center;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $assignments = $wpdb->get_results("
                SELECT a.*, q.title as questionnaire_title 
                FROM {$wpdb->prefix}cogei_assignments a
                LEFT JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
                ORDER BY a.sent_at DESC
                LIMIT 100
            ", ARRAY_A);
            
            foreach ($assignments as $assignment):
                $hse_user_name = 'N/A';
                if ($assignment['target_user_id']) {
                    $user = get_userdata($assignment['target_user_id']);
                    if ($user) {
                        $hse_user_name = $user->display_name;
                    }
                }
            ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;"><?php echo $assignment['id']; ?></td>
                <td style="padding: 12px;"><strong><?php echo esc_html($assignment['questionnaire_title']); ?></strong></td>
                <td style="padding: 12px;"><?php echo esc_html($hse_user_name); ?></td>
                <td style="padding: 12px;"><?php echo esc_html($assignment['inspector_email']); ?></td>
                <td style="padding: 12px;"><?php echo date('d/m/Y H:i', strtotime($assignment['sent_at'])); ?></td>
                <td style="padding: 12px; text-align: center;">
                    <?php
                    $status_colors = [
                        'pending' => '#ff9800',
                        'completed' => '#4caf50',
                        'expired' => '#f44336'
                    ];
                    $color = $status_colors[$assignment['status']] ?? '#999';
                    ?>
                    <span style="padding: 4px 12px; border-radius: 3px; background: <?php echo $color; ?>; color: white;">
                        <?php echo esc_html($assignment['status']); ?>
                    </span>
                </td>
                <td style="padding: 12px; text-align: center;">
                    <?php if ($assignment['status'] === 'completed'): ?>
                        <a href="?boq_tab=results&assignment=<?php echo $assignment['id']; ?>" style="color: #03679e; text-decoration: none;">
                            📊 Visualizza Risultato
                        </a>
                    <?php else: ?>
                        <code style="font-size: 0.8em; background: #f0f0f0; padding: 4px 8px; border-radius: 3px;" title="Token">
                            <?php echo substr($assignment['token'], 0, 16); ?>...
                        </code>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Tab: Risultati
 */
function boq_renderResultsTab() {
    global $wpdb;
    
    $assignment_id = isset($_GET['assignment']) ? intval($_GET['assignment']) : 0;
    
    if ($assignment_id > 0) {
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, q.title as questionnaire_title 
             FROM {$wpdb->prefix}cogei_assignments a
             LEFT JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
             WHERE a.id = %d",
            $assignment_id
        ), ARRAY_A);
        
        if ($assignment) {
            $score = boq_calculateScore($assignment_id);
            $evaluation = boq_evaluateScore($score);
            
            ?>
            <div style="background: white; border: 2px solid #03679e; border-radius: 10px; padding: 30px; margin-bottom: 30px;">
                <h2 style="color: #03679e; margin-top: 0;">📊 Dettaglio Risultato</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <strong>Questionario:</strong> <?php echo esc_html($assignment['questionnaire_title']); ?><br>
                        <?php
                        $hse_user = get_userdata($assignment['target_user_id']);
                        $hse_name = $hse_user ? $hse_user->display_name : 'N/A';
                        ?>
                        <strong>Utente HSE Valutato:</strong> <?php echo esc_html($hse_name); ?><br>
                        <strong>Email Ispettore:</strong> <?php echo esc_html($assignment['inspector_email']); ?><br>
                        <strong>Data Invio:</strong> <?php echo date('d/m/Y H:i', strtotime($assignment['sent_at'])); ?>
                    </div>
                    <div style="background: #f0f0f0; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 2em; font-weight: bold; color: #03679e; margin-bottom: 10px;">
                            <?php echo round($score, 4); ?> / 1.00
                        </div>
                        <div style="font-size: 1.5em; font-weight: bold; color: #4caf50;">
                            <?php echo esc_html($evaluation); ?>
                        </div>
                    </div>
                </div>
                
                <h3>Risposte Dettagliate</h3>
                <?php
                $areas = boq_getAreas($assignment['questionnaire_id']);
                $responses = boq_getResponses($assignment_id);
                
                // Organizza risposte per domanda
                $responses_by_question = [];
                foreach ($responses as $response) {
                    $responses_by_question[$response['question_id']] = $response;
                }
                
                foreach ($areas as $area):
                    $questions = boq_getQuestions($area['id']);
                ?>
                    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <h4 style="color: #03679e; margin-top: 0;">
                            <?php echo esc_html($area['title']); ?>
                            <span style="font-size: 0.8em; color: #666;">(Peso Area: <?php echo $area['weight']; ?>)</span>
                        </h4>
                        
                        <?php foreach ($questions as $question): ?>
                            <div style="background: white; padding: 12px; margin-bottom: 10px; border-radius: 3px;">
                                <strong><?php echo esc_html($question['text']); ?></strong><br>
                                
                                <?php if (isset($responses_by_question[$question['id']])): ?>
                                    <?php
                                    $response = $responses_by_question[$question['id']];
                                    $option = $wpdb->get_row($wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}cogei_options WHERE id = %d",
                                        $response['selected_option_id']
                                    ), ARRAY_A);
                                    ?>
                                    <div style="margin-top: 8px; padding: 8px; background: #e3f2fd; border-left: 4px solid #03679e; border-radius: 3px;">
                                        ✓ <?php echo esc_html($option['text']); ?>
                                        <span style="color: #666; font-size: 0.9em;">
                                            (Peso Opzione: <?php echo $option['weight']; ?>, 
                                            Punteggio: <?php echo round($response['computed_score'], 4); ?>)
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 8px; color: #999;">Nessuna risposta</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <a href="?boq_tab=results" style="display: inline-block; padding: 10px 20px; background: #03679e; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;">
                    ← Torna alla Lista
                </a>
            </div>
            <?php
        }
    } else {
        ?>
        <h2>Tutti i Risultati</h2>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background: #03679e; color: white;">
                    <th style="padding: 12px; text-align: left;">ID</th>
                    <th style="padding: 12px; text-align: left;">Questionario</th>
                    <th style="padding: 12px; text-align: left;">Utente HSE</th>
                    <th style="padding: 12px; text-align: center;">Punteggio</th>
                    <th style="padding: 12px; text-align: center;">Valutazione</th>
                    <th style="padding: 12px; text-align: center;">Data</th>
                    <th style="padding: 12px; text-align: center;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $completed_assignments = $wpdb->get_results("
                    SELECT a.*, q.title as questionnaire_title 
                    FROM {$wpdb->prefix}cogei_assignments a
                    LEFT JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
                    WHERE a.status = 'completed'
                    ORDER BY a.sent_at DESC
                    LIMIT 100
                ", ARRAY_A);
                
                foreach ($completed_assignments as $assignment):
                    $score = boq_calculateScore($assignment['id']);
                    $evaluation = boq_evaluateScore($score);
                    
                    $hse_user = get_userdata($assignment['target_user_id']);
                    $hse_name = $hse_user ? $hse_user->display_name : 'N/A';
                    
                    // Colore valutazione
                    $eval_colors = [
                        'Eccellente' => '#4caf50',
                        'Molto Buono' => '#8bc34a',
                        'Adeguato' => '#ff9800',
                        'Critico' => '#ff5722',
                        'Inadeguato' => '#f44336'
                    ];
                    $eval_color = $eval_colors[$evaluation] ?? '#999';
                ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px;"><?php echo $assignment['id']; ?></td>
                    <td style="padding: 12px;"><strong><?php echo esc_html($assignment['questionnaire_title']); ?></strong></td>
                    <td style="padding: 12px;"><?php echo esc_html($hse_name); ?></td>
                    <td style="padding: 12px; text-align: center; font-weight: bold;">
                        <?php echo round($score, 4); ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="padding: 6px 16px; border-radius: 5px; background: <?php echo $eval_color; ?>; color: white; font-weight: bold;">
                            <?php echo esc_html($evaluation); ?>
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <?php echo date('d/m/Y H:i', strtotime($assignment['sent_at'])); ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <a href="?boq_tab=results&assignment=<?php echo $assignment['id']; ?>" 
                           style="color: #03679e; text-decoration: none; font-weight: bold;">
                            📊 Dettagli
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

// ================== RENDERING PRINCIPALE ==================

// Render interface se non siamo in modalità token pubblico
if (!isset($_GET['boq_token'])) {
    boq_renderAdminInterface();
}

?>

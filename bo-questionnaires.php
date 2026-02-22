<?php

/**
 * Plugin Snippet WordPress - Backoffice Gestione Questionari Fornitori
 * 
 * DESCRIZIONE:
 * Questo file fornisce un sistema completo e autonomo per la gestione dei questionari
 * destinati ai fornitori (Albo Fornitori). Include funzionalità per:
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
 * - cogei_assignments: assegnazioni questionari (id, questionnaire_id, target_user_id (Fornitore), inspector_email, sent_by, sent_at, status, token)
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
 * - BO Albo Fornitori (gestione cantieri e HSE)
 * - BO ALBO FORNITORI (gestione fornitori)
 * - Sistema Questionari (form pubblici HSE)
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
 * Pattern analogo a quello usato in BO Albo Fornitori e BO ALBO FORNITORI
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
        weight decimal(6,3) DEFAULT 1.000,
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
        weight decimal(6,3) DEFAULT 0.000,
        sort_order int(11) DEFAULT 0,
        is_na tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        KEY question_id (question_id),
        KEY sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_options);
    
    // Add is_na column to options if it doesn't exist (migration for existing tables)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_options LIKE 'is_na'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_options ADD COLUMN is_na tinyint(1) DEFAULT 0 AFTER sort_order");
    }
    
    // Migration: Update weight column precision to support 3 decimal places (0.345)
    // Check if areas weight column needs updating
    $area_weight_column = $wpdb->get_row("SHOW COLUMNS FROM $table_areas LIKE 'weight'");
    if ($area_weight_column && stripos($area_weight_column->Type, 'decimal(5,2)') !== false) {
        $wpdb->query("ALTER TABLE $table_areas MODIFY COLUMN weight decimal(6,3) DEFAULT 1.000");
    }
    
    // Check if options weight column needs updating
    $option_weight_column = $wpdb->get_row("SHOW COLUMNS FROM $table_options LIKE 'weight'");
    if ($option_weight_column && stripos($option_weight_column->Type, 'decimal(5,2)') !== false) {
        $wpdb->query("ALTER TABLE $table_options MODIFY COLUMN weight decimal(6,3) DEFAULT 0.000");
    }
    
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
    
    // 📊 TABELLA PUNTEGGI QUESTIONARI
    // Questa tabella memorizza il punteggio finale calcolato una sola volta alla completazione del questionario
    // In questo modo il punteggio non viene mai ricalcolato e rimane immutabile anche se i pesi vengono modificati
    $table_scores = $wpdb->prefix . 'cogei_questionnaire_scores';
    $sql_scores = "CREATE TABLE IF NOT EXISTS $table_scores (
        id int(11) NOT NULL AUTO_INCREMENT,
        assignment_id int(11) NOT NULL,
        final_score decimal(10,4) NOT NULL DEFAULT 0.0000,
        calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY assignment_id (assignment_id),
        KEY final_score (final_score)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $wpdb->query($sql_scores);
    
    error_log("Tabelle questionari create/verificate: $table_questionnaires, $table_areas, $table_questions, $table_options, $table_assignments, $table_responses, $table_scores");
}

// Esegui creazione tabelle
boq_createQuestionnaireTablesIfNotExists();

// NOTA: Il questionario pubblico usa ora un file standalone (questionario-pubblico.php)
// posizionato nella cartella /questionario/ del server. Non serve più lo shortcode.

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
 * Calcola e salva il punteggio per un assignment
 * 
 * Questo metodo calcola il punteggio SOLO se non è già stato salvato nella tabella cogei_questionnaire_scores.
 * Una volta calcolato e salvato, il punteggio diventa immutabile.
 * 
 * NUOVA FORMULA:
 * 1. Peso Effettivo = peso_massimo_domanda × peso_area (0 se N.A.)
 * 2. Punteggio = peso_risposta × peso_area (0 se N.A.)
 * 3. Punteggio finale = (Somma Punteggi / Somma Pesi Effettivi) × 100
 * 
 * Le risposte N.A. vengono escluse dal calcolo (contributo 0 sia al punteggio che al peso effettivo).
 */
function boq_calculateAndSaveScore($assignment_id) {
    global $wpdb;
    
    $responses = boq_getResponses($assignment_id);
    if (empty($responses)) {
        return 0;
    }
    
    // Ottieni assignment per trovare il questionario
    $assignment = $wpdb->get_row($wpdb->prepare(
        "SELECT questionnaire_id FROM {$wpdb->prefix}cogei_assignments WHERE id = %d",
        $assignment_id
    ), ARRAY_A);
    
    if (!$assignment) {
        return 0;
    }
    
    // Ottieni tutte le aree del questionario
    $areas = $wpdb->get_results($wpdb->prepare(
        "SELECT id, weight FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d",
        $assignment['questionnaire_id']
    ), ARRAY_A);
    
    $total_punteggio = 0;
    $total_peso_effettivo = 0;
    
    foreach ($areas as $area) {
        // Ottieni tutte le risposte per quest'area con informazioni complete
        $area_responses = $wpdb->get_results($wpdb->prepare(
            "SELECT r.question_id, r.selected_option_id, o.weight as option_weight, o.is_na
            FROM {$wpdb->prefix}cogei_responses r
            INNER JOIN {$wpdb->prefix}cogei_questions q ON r.question_id = q.id
            INNER JOIN {$wpdb->prefix}cogei_options o ON r.selected_option_id = o.id
            WHERE r.assignment_id = %d AND q.area_id = %d",
            $assignment_id,
            $area['id']
        ), ARRAY_A);
        
        $area_weight = floatval($area['weight']);
        
        foreach ($area_responses as $resp) {
            $is_na = isset($resp['is_na']) && $resp['is_na'] == 1;
            
            if ($is_na) {
                // Se N.A., contributo è 0 sia al punteggio che al peso effettivo
                // (esclude la domanda dal calcolo)
                continue;
            }
            
            // Calcola peso massimo per questa domanda
            $max_weight = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(weight) FROM {$wpdb->prefix}cogei_options WHERE question_id = %d",
                $resp['question_id']
            ));
            $max_weight = $max_weight !== null ? floatval($max_weight) : 1.0;
            
            // Peso Effettivo = peso massimo * peso area
            $peso_effettivo = $max_weight * $area_weight;
            $total_peso_effettivo += $peso_effettivo;
            
            // Punteggio = peso risposta * peso area
            $answer_weight = floatval($resp['option_weight']);
            $punteggio = $answer_weight * $area_weight;
            $total_punteggio += $punteggio;
        }
    }
    
    // Calcola score finale: (somma punteggi / somma pesi effettivi) * 100
    // Se non ci sono pesi effettivi (tutte N.A.), il punteggio è 0
    $final_score = ($total_peso_effettivo > 0) 
        ? ($total_punteggio / $total_peso_effettivo) * 100 
        : 0;
    
    // Salva il punteggio nella tabella dedicata (INSERT IGNORE per evitare duplicati)
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->prefix}cogei_questionnaire_scores (assignment_id, final_score, calculated_at) 
         VALUES (%d, %f, NOW())",
        $assignment_id,
        $final_score
    ));
    
    return $final_score;
}

/**
 * Ricalcola e aggiorna il punteggio per un assignment
 * 
 * Questa funzione RICALCOLA il punteggio e lo AGGIORNA nella tabella cogei_questionnaire_scores.
 * Da usare quando le risposte di un questionario già completato vengono modificate.
 * 
 * IMPORTANTE: Usare questa funzione solo quando si modificano le risposte di un questionario già completato.
 * 
 * NUOVA FORMULA:
 * 1. Peso Effettivo = peso_massimo_domanda × peso_area (0 se N.A.)
 * 2. Punteggio = peso_risposta × peso_area (0 se N.A.)
 * 3. Punteggio finale = (Somma Punteggi / Somma Pesi Effettivi) × 100
 * 
 * Le risposte N.A. vengono escluse dal calcolo (contributo 0 sia al punteggio che al peso effettivo).
 * 
 * @param int $assignment_id ID dell'assignment
 * @return float Punteggio finale aggiornato
 */
function boq_recalculateAndUpdateScore($assignment_id) {
    global $wpdb;
    
    $responses = boq_getResponses($assignment_id);
    if (empty($responses)) {
        return 0;
    }
    
    // Ottieni assignment per trovare il questionario
    $assignment = $wpdb->get_row($wpdb->prepare(
        "SELECT questionnaire_id FROM {$wpdb->prefix}cogei_assignments WHERE id = %d",
        $assignment_id
    ), ARRAY_A);
    
    if (!$assignment) {
        return 0;
    }
    
    // Ottieni tutte le aree del questionario
    $areas = $wpdb->get_results($wpdb->prepare(
        "SELECT id, weight FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d",
        $assignment['questionnaire_id']
    ), ARRAY_A);
    
    $total_punteggio = 0;
    $total_peso_effettivo = 0;
    
    foreach ($areas as $area) {
        // Ottieni tutte le risposte per quest'area con informazioni complete
        $area_responses = $wpdb->get_results($wpdb->prepare(
            "SELECT r.question_id, r.selected_option_id, o.weight as option_weight, o.is_na
            FROM {$wpdb->prefix}cogei_responses r
            INNER JOIN {$wpdb->prefix}cogei_questions q ON r.question_id = q.id
            INNER JOIN {$wpdb->prefix}cogei_options o ON r.selected_option_id = o.id
            WHERE r.assignment_id = %d AND q.area_id = %d",
            $assignment_id,
            $area['id']
        ), ARRAY_A);
        
        $area_weight = floatval($area['weight']);
        
        foreach ($area_responses as $resp) {
            $is_na = isset($resp['is_na']) && $resp['is_na'] == 1;
            
            if ($is_na) {
                // Se N.A., contributo è 0 sia al punteggio che al peso effettivo
                // (esclude la domanda dal calcolo)
                continue;
            }
            
            // Calcola peso massimo per questa domanda
            $max_weight = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(weight) FROM {$wpdb->prefix}cogei_options WHERE question_id = %d",
                $resp['question_id']
            ));
            $max_weight = $max_weight !== null ? floatval($max_weight) : 1.0;
            
            // Peso Effettivo = peso massimo * peso area
            $peso_effettivo = $max_weight * $area_weight;
            $total_peso_effettivo += $peso_effettivo;
            
            // Punteggio = peso risposta * peso area
            $answer_weight = floatval($resp['option_weight']);
            $punteggio = $answer_weight * $area_weight;
            $total_punteggio += $punteggio;
        }
    }
    
    // Calcola score finale: (somma punteggi / somma pesi effettivi) * 100
    // Se non ci sono pesi effettivi (tutte N.A.), il punteggio è 0
    $final_score = ($total_peso_effettivo > 0) 
        ? ($total_punteggio / $total_peso_effettivo) * 100 
        : 0;
    
    // Verifica se il punteggio esiste già
    $existing_score = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}cogei_questionnaire_scores WHERE assignment_id = %d",
        $assignment_id
    ));
    
    if ($existing_score) {
        // Aggiorna il punteggio esistente
        $wpdb->update(
            $wpdb->prefix . 'cogei_questionnaire_scores',
            [
                'final_score' => $final_score,
                'calculated_at' => current_time('mysql')
            ],
            ['assignment_id' => $assignment_id],
            ['%f', '%s'],
            ['%d']
        );
    } else {
        // Inserisci nuovo punteggio
        $wpdb->insert(
            $wpdb->prefix . 'cogei_questionnaire_scores',
            [
                'assignment_id' => $assignment_id,
                'final_score' => $final_score,
                'calculated_at' => current_time('mysql')
            ],
            ['%d', '%f', '%s']
        );
    }
    
    return $final_score;
}

/**
 * Ottieni il punteggio salvato per un assignment
 * 
 * Questa funzione recupera il punteggio dalla tabella cogei_questionnaire_scores.
 * Se il punteggio non esiste ancora, lo calcola e lo salva.
 * 
 * IMPORTANTE: Questa è l'unica funzione da usare per ottenere i punteggi dei questionari.
 * Non ricalcolare mai i punteggi manualmente.
 */
function boq_getScore($assignment_id) {
    global $wpdb;
    
    // Verifica se il punteggio è già salvato
    $saved_score = $wpdb->get_var($wpdb->prepare(
        "SELECT final_score FROM {$wpdb->prefix}cogei_questionnaire_scores WHERE assignment_id = %d",
        $assignment_id
    ));
    
    if ($saved_score !== null) {
        return floatval($saved_score);
    }
    
    // Se non esiste, calcolalo e salvalo
    return boq_calculateAndSaveScore($assignment_id);
}

/**
 * Valuta il punteggio secondo le soglie definite nella legenda
 * Usa le soglie basate sulle stelle per garantire coerenza:
 * >= 4.5 stelle (90/100) = Eccellente
 * >= 3.5 stelle (70/100) = Molto Buono
 * >= 2.5 stelle (50/100) = Adeguato
 * >= 1.5 stelle (30/100) = Critico
 * < 1.5 stelle (< 30/100) = Inadeguato
 */
function boq_evaluateScore($score) {
    // Convert score to stars first
    $stars = ($score / 100) * 5;
    $stars = round($stars * 2) / 2; // Round to nearest 0.5
    
    // Use star-based thresholds to match the legend
    if ($stars >= 4.5) {
        return 'Eccellente';
    } elseif ($stars >= 3.5) {
        return 'Molto Buono';
    } elseif ($stars >= 2.5) {
        return 'Adeguato';
    } elseif ($stars >= 1.5) {
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
    global $wpdb;
    
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
    
    // Get supplier (fornitore) user info
    $hse_user = get_userdata($assignment['target_user_id']);
    $hse_name = $hse_user ? $hse_user->display_name : 'N/A';
    $ragione_sociale = $hse_user ? get_user_meta($hse_user->ID, 'user_registration_rag_soc', true) : '';
    $supplier_display = $ragione_sociale ? $ragione_sociale . " (P.IVA: " . $hse_name . ")" : $hse_name;
    
    $token = $assignment['token'];
    $inspector_email = $assignment['inspector_email'];
    
    // Genera link - punta al file standalone /questionario/ (usa site_url perché la cartella è dentro l'installazione WP)
    $link = add_query_arg('boq_token', $token, site_url('/questionario/'));
    
    $to = $inspector_email;
    $subject = "Questionario Valutazione Fornitore - " . esc_html($questionnaire['title']);
    
    $body = "<html>
<head>
<title>Questionario Valutazione Fornitore</title>
</head>
<body>
<div style='background: #03679e; text-align: center; padding: 10px; margin-bottom: 30px;'>
    <img style='max-width: 150px;' src='https://cogei.provasiti.it/cogei/wp-content/uploads/2023/02/logo_bianco-1.png' />
</div>
<p>Gentile Valutatore,</p>
<p>Le è stato assegnato il seguente questionario per valutare il fornitore: <strong>" . esc_html($supplier_display) . "</strong></p>
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
    
    // Invia sempre l'email (rimuovere il flag $inviamail che bloccava l'invio)
    $email_sent = wp_mail($to, $subject, $body, $headers);
    
    // Log per debug
    error_log("Email questionario inviata a $inspector_email per valutare Fornitore ID " . $assignment['target_user_id'] . " ($hse_name) - Token: $token - Sent: " . ($email_sent ? 'YES' : 'NO'));
    
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
    echo "ID,Questionario,Fornitore,Email Valutatore,Data Invio,Stato,Punteggio,Valutazione\n";
    
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
            $score = boq_getScore($assignment['id']);
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
            
            // Redirect to edit mode to add areas and questions
            wp_redirect(add_query_arg([
                'boq_tab' => 'questionnaires',
                'edit' => $questionnaire_id
            ]));
            exit;
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
    
    // AZIONE: Duplica Questionario
    if ($action === 'duplicate_questionnaire') {
        $source_id = intval($_POST['questionnaire_id']);
        
        // Recupera questionario originale
        $source_questionnaire = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cogei_questionnaires WHERE id = %d",
            $source_id
        ), ARRAY_A);
        
        if ($source_questionnaire) {
            // Crea copia del questionario
            $new_title = $source_questionnaire['title'] . ' (Copia)';
            $wpdb->insert(
                $wpdb->prefix . 'cogei_questionnaires',
                [
                    'title' => $new_title,
                    'description' => $source_questionnaire['description'],
                    'status' => 'draft', // Imposta come bozza
                    'created_by' => get_current_user_id()
                ],
                ['%s', '%s', '%s', '%d']
            );
            $new_questionnaire_id = $wpdb->insert_id;
            
            // Duplica aree
            $areas = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cogei_areas WHERE questionnaire_id = %d ORDER BY sort_order",
                $source_id
            ), ARRAY_A);
            
            foreach ($areas as $area) {
                $old_area_id = $area['id'];
                $wpdb->insert(
                    $wpdb->prefix . 'cogei_areas',
                    [
                        'questionnaire_id' => $new_questionnaire_id,
                        'title' => $area['title'],
                        'weight' => $area['weight'],
                        'sort_order' => $area['sort_order']
                    ],
                    ['%d', '%s', '%f', '%d']
                );
                $new_area_id = $wpdb->insert_id;
                
                // Duplica domande per questa area
                $questions = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cogei_questions WHERE area_id = %d ORDER BY sort_order",
                    $old_area_id
                ), ARRAY_A);
                
                foreach ($questions as $question) {
                    $old_question_id = $question['id'];
                    $wpdb->insert(
                        $wpdb->prefix . 'cogei_questions',
                        [
                            'area_id' => $new_area_id,
                            'text' => $question['text'],
                            'is_required' => $question['is_required'],
                            'sort_order' => $question['sort_order']
                        ],
                        ['%d', '%s', '%d', '%d']
                    );
                    $new_question_id = $wpdb->insert_id;
                    
                    // Duplica opzioni per questa domanda
                    $options = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}cogei_options WHERE question_id = %d ORDER BY sort_order",
                        $old_question_id
                    ), ARRAY_A);
                    
                    foreach ($options as $option) {
                        $wpdb->insert(
                            $wpdb->prefix . 'cogei_options',
                            [
                                'question_id' => $new_question_id,
                                'text' => $option['text'],
                                'weight' => $option['weight'],
                                'is_na' => $option['is_na'],
                                'sort_order' => $option['sort_order']
                            ],
                            ['%d', '%s', '%f', '%d', '%d']
                        );
                    }
                }
            }
            
            echo '<div class="notice notice-success"><p>Questionario duplicato con successo! <a href="?boq_tab=questionnaires&edit=' . $new_questionnaire_id . '">Modifica ora</a></p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Questionario non trovato</p></div>';
        }
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
    
    // AZIONE: Salva Struttura Completa (JavaScript Editor)
    if ($action === 'save_structure') {
        $questionnaire_id = intval($_POST['questionnaire_id']);
        $structure_json = stripslashes($_POST['structure']);
        $structure = json_decode($structure_json, true);
        
        if (!$structure || !is_array($structure)) {
            http_response_code(400);
            echo json_encode(['error' => 'Struttura non valida']);
            exit;
        }
        
        // CRITICAL: Check if this questionnaire has any responses
        // If yes, we update existing records instead of deleting/recreating to preserve response links
        $has_responses = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cogei_responses r
             INNER JOIN {$wpdb->prefix}cogei_assignments a ON r.assignment_id = a.id
             WHERE a.questionnaire_id = %d",
            $questionnaire_id
        ));
        
        if ($has_responses > 0) {
            // UPDATE mode: preserve IDs and update data only
            $existing_areas = boq_getAreas($questionnaire_id);
            
            foreach ($structure as $area_index => $area_data) {
                if (isset($area_data['id']) && $area_data['id'] > 0) {
                    // Update existing area
                    $wpdb->update(
                        $wpdb->prefix . 'cogei_areas',
                        [
                            'title' => sanitize_text_field($area_data['title']),
                            'weight' => sanitize_text_field($area_data['weight']),
                            'sort_order' => intval($area_data['sort_order'])
                        ],
                        ['id' => intval($area_data['id'])],
                        ['%s', '%s', '%d'],
                        ['%d']
                    );
                    $area_id = intval($area_data['id']);
                } else {
                    // Insert new area
                    $wpdb->insert(
                        $wpdb->prefix . 'cogei_areas',
                        [
                            'questionnaire_id' => $questionnaire_id,
                            'title' => sanitize_text_field($area_data['title']),
                            'weight' => sanitize_text_field($area_data['weight']),
                            'sort_order' => intval($area_data['sort_order'])
                        ],
                        ['%d', '%s', '%s', '%d']
                    );
                    $area_id = $wpdb->insert_id;
                }
                
                // Process questions
                if (!empty($area_data['questions'])) {
                    foreach ($area_data['questions'] as $question_data) {
                        if (isset($question_data['id']) && $question_data['id'] > 0) {
                            // Update existing question
                            $wpdb->update(
                                $wpdb->prefix . 'cogei_questions',
                                [
                                    'text' => sanitize_textarea_field($question_data['text']),
                                    'is_required' => intval($question_data['is_required']),
                                    'sort_order' => intval($question_data['sort_order'])
                                ],
                                ['id' => intval($question_data['id'])],
                                ['%s', '%d', '%d'],
                                ['%d']
                            );
                            $question_id = intval($question_data['id']);
                        } else {
                            // Insert new question
                            $wpdb->insert(
                                $wpdb->prefix . 'cogei_questions',
                                [
                                    'area_id' => $area_id,
                                    'text' => sanitize_textarea_field($question_data['text']),
                                    'is_required' => intval($question_data['is_required']),
                                    'sort_order' => intval($question_data['sort_order'])
                                ],
                                ['%d', '%s', '%d', '%d']
                            );
                            $question_id = $wpdb->insert_id;
                        }
                        
                        // Process options
                        if (!empty($question_data['options'])) {
                            foreach ($question_data['options'] as $option_data) {
                                if (isset($option_data['id']) && $option_data['id'] > 0) {
                                    // Update existing option
                                    $wpdb->update(
                                        $wpdb->prefix . 'cogei_options',
                                        [
                                            'text' => sanitize_text_field($option_data['text']),
                                            'weight' => sanitize_text_field($option_data['weight']),
                                            'is_na' => isset($option_data['is_na']) ? intval($option_data['is_na']) : 0,
                                            'sort_order' => intval($option_data['sort_order'])
                                        ],
                                        ['id' => intval($option_data['id'])],
                                        ['%s', '%s', '%d', '%d'],
                                        ['%d']
                                    );
                                } else {
                                    // Insert new option
                                    $wpdb->insert(
                                        $wpdb->prefix . 'cogei_options',
                                        [
                                            'question_id' => $question_id,
                                            'text' => sanitize_text_field($option_data['text']),
                                            'weight' => sanitize_text_field($option_data['weight']),
                                            'is_na' => isset($option_data['is_na']) ? intval($option_data['is_na']) : 0,
                                            'sort_order' => intval($option_data['sort_order'])
                                        ],
                                        ['%d', '%s', '%s', '%d', '%d']
                                    );
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // INSERT mode: No responses yet, safe to delete and recreate
            // Prima elimina tutti i dati esistenti per questo questionario
            $existing_areas = boq_getAreas($questionnaire_id);
            foreach ($existing_areas as $area) {
                $questions = boq_getQuestions($area['id']);
                foreach ($questions as $question) {
                    // Elimina opzioni
                    $wpdb->delete($wpdb->prefix . 'cogei_options', ['question_id' => $question['id']], ['%d']);
                }
                // Elimina domande
                $wpdb->delete($wpdb->prefix . 'cogei_questions', ['area_id' => $area['id']], ['%d']);
            }
            // Elimina aree
            $wpdb->delete($wpdb->prefix . 'cogei_areas', ['questionnaire_id' => $questionnaire_id], ['%d']);
            
            // Ora inserisci la nuova struttura
            foreach ($structure as $area_data) {
                // Inserisci area
                $area_insert = [
                    'questionnaire_id' => $questionnaire_id,
                    'title' => sanitize_text_field($area_data['title']),
                    'weight' => floatval($area_data['weight']),
                    'sort_order' => intval($area_data['sort_order'])
                ];
                $wpdb->insert($wpdb->prefix . 'cogei_areas', $area_insert, ['%d', '%s', '%f', '%d']);
                $area_id = $wpdb->insert_id;
                
                // Inserisci domande
                if (!empty($area_data['questions'])) {
                    foreach ($area_data['questions'] as $question_data) {
                        $question_insert = [
                            'area_id' => $area_id,
                            'text' => sanitize_textarea_field($question_data['text']),
                            'is_required' => intval($question_data['is_required']),
                            'sort_order' => intval($question_data['sort_order'])
                        ];
                        $wpdb->insert($wpdb->prefix . 'cogei_questions', $question_insert, ['%d', '%s', '%d', '%d']);
                        $question_id = $wpdb->insert_id;
                        
                        // Inserisci opzioni
                        if (!empty($question_data['options'])) {
                            foreach ($question_data['options'] as $option_data) {
                                $option_insert = [
                                    'question_id' => $question_id,
                                    'text' => sanitize_text_field($option_data['text']),
                                    'weight' => floatval($option_data['weight']),
                                    'is_na' => isset($option_data['is_na']) ? intval($option_data['is_na']) : 0,
                                    'sort_order' => intval($option_data['sort_order'])
                                ];
                                $wpdb->insert($wpdb->prefix . 'cogei_options', $option_insert, ['%d', '%s', '%f', '%d', '%d']);
                            }
                        }
                    }
                }
            }
        }
        
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // AZIONE: Invia Questionario
    if ($action === 'send_questionnaire') {
        $questionnaire_id = intval($_POST['questionnaire_id']);
        $target_user_id = intval($_POST['target_user_id']);
        $inspector_emails_raw = isset($_POST['inspector_emails']) ? $_POST['inspector_emails'] : '';
        
        // Parse multiple emails - support both comma-separated and newline-separated
        $inspector_emails_raw = str_replace([',', ';'], "\n", $inspector_emails_raw);
        $inspector_emails_array = array_filter(array_map('trim', explode("\n", $inspector_emails_raw)));
        
        // Validate and sanitize emails
        $valid_emails = [];
        $invalid_emails = [];
        foreach ($inspector_emails_array as $email) {
            $sanitized = sanitize_email($email);
            if (is_email($sanitized)) {
                $valid_emails[] = $sanitized;
            } else {
                $invalid_emails[] = $email;
            }
        }
        
        // Validazione: target_user_id è obbligatorio
        if (empty($target_user_id) || $target_user_id <= 0) {
            echo '<div class="notice notice-error"><p>Devi selezionare un fornitore</p></div>';
        } elseif (empty($valid_emails)) {
            if (!empty($invalid_emails)) {
                echo '<div class="notice notice-error"><p>Le email inserite non sono valide: ' . esc_html(implode(', ', $invalid_emails)) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Email valutatore è obbligatoria</p></div>';
            }
        } else {
            // Verifica che il fornitore esista
            $user = get_userdata($target_user_id);
            if (!$user) {
                echo '<div class="notice notice-error"><p>Fornitore non valido</p></div>';
            } else {
                $sent_count = 0;
                $failed_emails = [];
                
                // Crea un assignment separato per ogni email valutatore
                foreach ($valid_emails as $inspector_email) {
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
                        $sent_count++;
                    } else {
                        $failed_emails[] = $inspector_email;
                    }
                }
                
                // Mostra messaggio di successo/warning
                if ($sent_count > 0) {
                    $msg = "Questionario inviato con successo a <strong>" . $sent_count . " valutatore/i</strong> per valutare il fornitore: <strong>" . esc_html($user->display_name) . "</strong>";
                    if ($sent_count > 1) {
                        $msg .= "<br><small>Sono state create " . $sent_count . " valutazioni distinte (una per valutatore)</small>";
                    }
                    if (!empty($failed_emails)) {
                        $msg .= "<br><span style='color: #856404;'>⚠️ Email non inviate a: " . esc_html(implode(', ', $failed_emails)) . "</span>";
                    }
                    echo '<div class="notice notice-success"><p>' . $msg . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Errore: nessuna email inviata</p></div>';
                }
                
                // Mostra avviso se ci sono email invalide
                if (!empty($invalid_emails)) {
                    echo '<div class="notice notice-warning"><p>⚠️ Le seguenti email non sono valide e sono state ignorate: ' . esc_html(implode(', ', $invalid_emails)) . '</p></div>';
                }
            }
        }
    }
}


// ================== HANDLER PUBBLICO RISPOSTE ==================

/**
 * Shortcode per mostrare il questionario nella pagina WordPress
 * UTILIZZO: Aggiungi [boq_questionnaire_form] alla pagina /pannello-questionario
 * 
 * Questo shortcode rileva il parametro ?boq_token= nell'URL e mostra il questionario corrispondente
 * Lo shortcode viene registrato sull'hook 'init' (vedi riga ~177)
 */
/**
 * NOTA: Questa funzione non è più utilizzata. Il questionario pubblico ora usa
 * un file standalone (questionario-pubblico.php) posizionato nella cartella /questionario/
 * Mantenuta per compatibilità ma non necessaria.
 */
function boq_renderPublicQuestionnaireForm() {
    if (!isset($_GET['boq_token']) || empty($_GET['boq_token'])) {
        return '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; margin: 20px 0;"><strong>⚠️ Attenzione:</strong> Per compilare un questionario è necessario utilizzare il link fornito via email.</div>';
    }
    
    global $wpdb;
    $token = sanitize_text_field($_GET['boq_token']);
    $assignment = boq_getAssignmentByToken($token);
    
    if (!$assignment) {
        return '<div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;"><strong>❌ Errore:</strong> Token non valido o scaduto.</div>';
    }
    
    if ($assignment['status'] === 'completed') {
        $score = boq_getScore($assignment['id']);
        $evaluation = boq_evaluateScore($score);
        $output = '<div style="padding: 30px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;">';
        $output .= '<h2 style="color: #155724;">✅ Questionario già compilato</h2>';
        $output .= '<p>Questo questionario è già stato completato.</p>';
        $output .= '<p><strong>Punteggio:</strong> ' . round($score, 4) . ' / 1.00</p>';
        $output .= '<p><strong>Valutazione:</strong> <strong style="font-size: 1.2em;">' . esc_html($evaluation) . '</strong></p>';
        $output .= '</div>';
        return $output;
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
                "SELECT weight, is_na FROM {$wpdb->prefix}cogei_options WHERE id = %d",
                $option_id
            ), ARRAY_A);
            
            // Se l'opzione è N.A., usa il peso massimo disponibile per questa domanda
            $weight_to_use = floatval($option['weight']);
            if (isset($option['is_na']) && $option['is_na'] == 1) {
                $max_weight = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(weight) FROM {$wpdb->prefix}cogei_options WHERE question_id = %d",
                    $question_id
                ));
                $weight_to_use = $max_weight !== null ? floatval($max_weight) : floatval($option['weight']);
            }
            
            // Salva solo il peso della domanda (NON moltiplicato per area_weight)
            // L'area_weight verrà applicato durante il calcolo finale per area
            $computed_score = $weight_to_use;
            
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
        $final_score = boq_getScore($assignment['id']);
        $evaluation = boq_evaluateScore($final_score);
        
        $output = '<div style="padding: 30px; background: #d4edda; border: 2px solid #c3e6cb; border-radius: 10px; margin: 20px 0; text-align: center;">';
        $output .= '<h2 style="color: #03679e;">✅ Questionario completato con successo!</h2>';
        $output .= '<p style="font-size: 1.1em;">Grazie per aver compilato il questionario.</p>';
        $output .= '<div style="background: white; padding: 25px; margin: 20px auto; border-radius: 5px; max-width: 500px; border: 1px solid #03679e;">';
        $output .= '<h3 style="color: #03679e; margin-top: 0;">📊 Risultato Valutazione</h3>';
        $output .= '<p style="font-size: 1.2em;"><strong>Punteggio:</strong> ' . round($final_score, 4) . ' / 1.00</p>';
        $output .= '<p style="font-size: 1.5em; margin: 15px 0;"><strong>Valutazione:</strong><br><span style="color: #03679e; font-weight: bold;">' . esc_html($evaluation) . '</span></p>';
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }
    
    // Mostra form questionario - usa output buffering per catturare HTML
    $questionnaire = boq_getQuestionnaire($assignment['questionnaire_id']);
    $areas = boq_getAreas($assignment['questionnaire_id']);
    
    ob_start();
    ?>
    <style>
        .boq-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .boq-header {
            background: #03679e;
            text-align: center;
            padding: 20px;
            margin: -30px -30px 30px -30px;
            border-radius: 10px 10px 0 0;
        }
        .boq-header img {
            max-width: 150px;
        }
        .boq-container h1 {
            color: #03679e;
            margin-top: 0;
        }
        .boq-description {
            color: #666;
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #03679e;
        }
        .boq-area {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .boq-area-title {
            color: #03679e;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #03679e;
        }
        .boq-question {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
        }
        .boq-question-text {
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .boq-question-text .required {
            color: red;
        }
        .boq-option {
            margin: 8px 0;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .boq-option:hover {
            background: #f0f0f0;
        }
        .boq-option label {
            cursor: pointer;
            display: block;
        }
        .boq-submit-btn {
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
        .boq-submit-btn:hover {
            background: #025a85;
        }
        .boq-footer {
            background: #03679e;
            padding: 20px;
            margin: 30px -30px -30px -30px;
            border-radius: 0 0 10px 10px;
            color: white;
            text-align: center;
            font-size: 0.9em;
        }
    </style>
    
    <div class="boq-container">
        <div class="boq-header">
            <img src="https://cogei.provasiti.it/cogei/wp-content/uploads/2023/02/logo_bianco-1.png" alt="Cogei">
        </div>
        
        <h1><?php echo esc_html($questionnaire['title']); ?></h1>
        
        <?php if (!empty($questionnaire['description'])): ?>
            <div class="boq-description">
                <?php echo nl2br(esc_html($questionnaire['description'])); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <?php wp_nonce_field('boq_submit_' . $token, 'boq_response_nonce'); ?>
            
            <?php foreach ($areas as $area): ?>
                <div class="boq-area">
                    <div class="boq-area-title">
                        <?php echo esc_html($area['title']); ?>
                        <span style="font-size: 0.8em; color: #666;">(Peso: <?php echo esc_html($area['weight']); ?>)</span>
                    </div>
                    
                    <?php 
                    $questions = boq_getQuestions($area['id']);
                    foreach ($questions as $question): 
                    ?>
                        <div class="boq-question">
                            <div class="boq-question-text">
                                <?php echo esc_html($question['text']); ?>
                                <?php if ($question['is_required']): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                            $options = boq_getOptions($question['id']);
                            foreach ($options as $option): 
                            ?>
                                <div class="boq-option">
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
                
                <button type="submit" name="boq_submit_responses" class="boq-submit-btn">Invia Risposte</button>
            </form>
            
            <div class="boq-footer">
                <div>Via Francesco Lomonaco, 3 - 80121 Napoli</div>
                <div>TEL: +39 081.230.37.82</div>
                <div>PEC: cogei@pec.cogei.net</div>
                <div style="margin-top: 20px; font-size: 0.8em;">COGEI SRL - P.IVA: IT06569020636 - Copyright © 2023 Cogei. All Rights Reserved.</div>
            </div>
        </div>
    <?php
    return ob_get_clean();
}


// ================== INTERFACCIA AMMINISTRATIVA ==================

/**
 * Interfaccia Admin per gestione questionari
 * Stile coerente con BO Albo Fornitori e BO ALBO FORNITORI
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
                <a href="?boq_tab=ratings" 
                   style="display: inline-block; padding: 10px 20px; text-decoration: none; <?php echo $active_tab === 'ratings' ? 'background: #03679e; color: white;' : 'color: #03679e;'; ?>">
                    ⭐ Votazioni Albo Fornitori
                </a>
                <?php if ($active_tab === 'assignments' || $active_tab === 'results'): ?>
                <a href="?boq_csv_export=1" 
                   style="display: inline-block; padding: 10px 20px; text-decoration: none; color: #03679e; float: right;">
                    📥 Esporta CSV
                </a>
                <?php endif; ?>
            </div>
            
            <?php if ($active_tab === 'questionnaires'): ?>
                <?php boq_renderQuestionnairesTab(); ?>
            <?php elseif ($active_tab === 'assignments'): ?>
                <?php boq_renderAssignmentsTab(); ?>
            <?php elseif ($active_tab === 'results'): ?>
                <?php boq_renderResultsTab(); ?>
            <?php elseif ($active_tab === 'ratings'): ?>
                <?php boq_renderRatingsTab(); ?>
            <?php endif; ?>
            
        </div>
        
        <!-- Modal for Editing Questionnaire (Available on all tabs) -->
        <div id="boqEditModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10001; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 10px; max-width: 900px; width: 95%; max-height: 90vh; overflow-y: auto; padding: 20px; position: relative;">
                <button onclick="boqCloseEditModal()" style="position: absolute; top: 15px; right: 15px; background: #f44336; color: white; border: none; border-radius: 50%; width: 35px; height: 35px; font-size: 20px; cursor: pointer; font-weight: bold; z-index: 1;">×</button>
                <div id="boqEditContent" style="min-height: 200px;">
                    <div style="text-align: center; padding: 40px; color: #999;">
                        Caricamento form di modifica...
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Edit Modal Functions (Available on all tabs)
        function boqOpenEditModal(assignmentId) {
            const modal = document.getElementById('boqEditModal');
            const content = document.getElementById('boqEditContent');
            
            content.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 15px; color: #666;">Caricamento form di modifica...</p></div>';
            modal.style.display = 'flex';
            
            // AJAX request to get editable questionnaire
            fetch('<?php echo site_url('/ajax_fornitori/get_editable_questionnaire.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'assignment_id=' + assignmentId + '&nonce=' + encodeURIComponent('<?php echo wp_create_nonce('boq_edit_questionnaire'); ?>')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    content.innerHTML = data.html;
                } else {
                    content.innerHTML = '<div style="padding: 20px; text-align: center; color: #c00;">Errore: ' + (data.error || 'Impossibile caricare il questionario') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<div style="padding: 20px; text-align: center; color: #c00;">Errore: Impossibile caricare il questionario</div>';
            });
        }
        
        function boqCloseEditModal() {
            document.getElementById('boqEditModal').style.display = 'none';
        }
        
        function boqSaveEdits(assignmentId) {
            const form = document.getElementById('boqEditForm');
            const formData = new FormData(form);
            
            // Valida form
            if (!form.checkValidity()) {
                alert('Per favore, rispondi a tutte le domande obbligatorie.');
                form.reportValidity();
                return;
            }
            
            // Raccogli risposte
            const responses = {};
            formData.forEach((value, key) => {
                if (key.startsWith('question_')) {
                    const questionId = key.replace('question_', '');
                    responses[questionId] = value;
                }
            });
            
            // Debug: Log responses
            console.log('Responses collected:', responses);
            console.log('Number of responses:', Object.keys(responses).length);
            console.log('JSON to send:', JSON.stringify(responses));
            
            // Validate we have responses
            if (Object.keys(responses).length === 0) {
                alert('Errore: Nessuna risposta trovata nel form. Verifica che le domande siano state caricate correttamente.');
                return;
            }
            
            // Mostra loading
            const content = document.getElementById('boqEditContent');
            const originalContent = content.innerHTML;
            content.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 15px; color: #666;">Salvataggio in corso...</p></div>';
            
            // AJAX request to save edits
            fetch('<?php echo site_url('/ajax_fornitori/save_questionnaire_edits.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'assignment_id=' + assignmentId + '&responses=' + encodeURIComponent(JSON.stringify(responses)) + '&nonce=' + encodeURIComponent('<?php echo wp_create_nonce('boq_edit_questionnaire'); ?>')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostra messaggio di successo con nuovo punteggio
                    const score = data.score;
                    content.innerHTML = '<div style="text-align: center; padding: 40px;">' +
                        '<div style="font-size: 60px; color: #4caf50; margin-bottom: 20px;">✓</div>' +
                        '<h2 style="color: #4caf50; margin-bottom: 15px;">Modifiche Salvate con Successo!</h2>' +
                        '<div style="background: #f0f0f0; padding: 20px; border-radius: 8px; margin: 20px 0;">' +
                        '<div style="font-size: 16px; color: #666; margin-bottom: 10px;">Nuovo Punteggio</div>' +
                        '<div style="font-size: 36px; font-weight: bold; color: ' + score.eval_color + '; margin-bottom: 10px;">' + score.value + ' / 100</div>' +
                        '<div style="color: #FFD700; font-size: 24px; margin-bottom: 10px;">' + ('★'.repeat(Math.floor(score.stars))) + (score.stars % 1 >= 0.5 ? '☆' : '') + ('☆'.repeat(5 - Math.ceil(score.stars))) + '</div>' +
                        '<div style="background: ' + score.eval_color + '; color: white; display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 16px; font-weight: 600;">' + score.evaluation + '</div>' +
                        '</div>' +
                        '<p style="color: #666; margin-top: 20px;">Il punteggio è stato ricalcolato automaticamente.</p>' +
                        '<button onclick="boqCloseEditModal(); window.location.reload();" style="background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 20px;">Chiudi</button>' +
                        '</div>';
                } else {
                    let errorMsg = 'Errore durante il salvataggio: ' + (data.error || 'Errore sconosciuto');
                    if (data.debug) {
                        console.error('Debug info:', data.debug);
                        errorMsg += '\n\nDebug: ' + JSON.stringify(data.debug, null, 2);
                    }
                    alert(errorMsg);
                    content.innerHTML = originalContent;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante il salvataggio. Riprova.');
                content.innerHTML = originalContent;
            });
        }
        
        // Close edit modal on click outside
        document.getElementById('boqEditModal')?.addEventListener('click', function(e) {
            if (e.target === this) boqCloseEditModal();
        });
        
        // Add spinner animation
        if (!document.getElementById('boq-spinner-style')) {
            const style = document.createElement('style');
            style.id = 'boq-spinner-style';
            style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
            document.head.appendChild(style);
        }
        </script>
    </div>
    <?php
}

/**
 * Tab: Gestione Questionari
 */
function boq_renderQuestionnairesTab() {
    global $wpdb;
    
    $questionnaire_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $create_new = isset($_GET['create']) && $_GET['create'] === 'new';
    $questionnaire = $questionnaire_id > 0 ? boq_getQuestionnaire($questionnaire_id) : null;
    
    // Get all questionnaires
    $questionnaires = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cogei_questionnaires ORDER BY created_at DESC", ARRAY_A);
    $has_questionnaires = !empty($questionnaires);
    
    // Show create form if: editing, creating new, or no questionnaires exist
    $show_form = $questionnaire || $create_new || !$has_questionnaires;
    
    if ($show_form): ?>
        <div style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">
                    <?php echo $questionnaire ? 'Modifica Questionario' : 'Crea Nuovo Questionario'; ?>
                </h2>
                <?php if (!$has_questionnaires && !$questionnaire): ?>
                    <button onclick="boqOpenImportModal()" style="padding: 8px 15px; background: #03679e; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
                        📦 Importa Questionario
                    </button>
                <?php elseif ($has_questionnaires && !$questionnaire): ?>
                    <a href="?boq_tab=questionnaires" style="padding: 8px 15px; background: #999; color: white; text-decoration: none; border-radius: 3px;">
                        ← Torna alla Lista
                    </a>
                <?php endif; ?>
            </div>
            
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
        
        <?php if ($has_questionnaires): ?>
            <hr style="margin: 40px 0; border: none; border-top: 2px solid #ddd;">
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($has_questionnaires && !$show_form): ?>
        <!-- Lista Questionari -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Questionari Esistenti</h2>
            <div>
                <button onclick="boqOpenImportModal()" style="padding: 10px 20px; background: #03679e; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; margin-right: 10px;">
                    📦 Importa Questionario
                </button>
                <a href="?boq_tab=questionnaires&create=new" style="padding: 10px 20px; background: #4caf50; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    ➕ Crea Nuovo Questionario
                </a>
            </div>
        </div>
        
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
                <?php foreach ($questionnaires as $q): ?>
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
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Duplicare questo questionario?');">
                            <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                            <input type="hidden" name="boq_action" value="duplicate_questionnaire">
                            <input type="hidden" name="questionnaire_id" value="<?php echo $q['id']; ?>">
                            <button type="submit" style="background: none; border: none; color: #ff9800; cursor: pointer; text-decoration: none; font-size: 14px;">📋 Duplica</button>
                        </form>
                        |
                        <a href="?boq_tab=assignments&send=<?php echo $q['id']; ?>" style="color: #4caf50; text-decoration: none; margin: 0 5px;">📤 Invia</a>
                        |
                        <a href="<?php echo esc_url(site_url('/ajax_fornitori/export_questionnaire.php') . '?questionnaire_id=' . $q['id'] . '&nonce=' . wp_create_nonce('boq_export_questionnaire')); ?>" style="color: #03679e; text-decoration: none; margin: 0 5px;">⬇️ Esporta</a>
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
    <?php endif; ?>
    <?php
}

/**
 * Editor per Aree, Domande e Opzioni - Versione JavaScript User-Friendly
 */
function boq_renderAreasEditor($questionnaire_id) {
    global $wpdb;
    
    // Carica dati esistenti
    $areas = boq_getAreas($questionnaire_id);
    $existing_data = [];
    foreach ($areas as $area) {
        $questions_data = [];
        $questions = boq_getQuestions($area['id']);
        foreach ($questions as $question) {
            $options_data = [];
            $options = boq_getOptions($question['id']);
            foreach ($options as $option) {
                $options_data[] = [
                    'id' => $option['id'],
                    'text' => $option['text'],
                    'weight' => $option['weight'],
                    'is_na' => isset($option['is_na']) ? intval($option['is_na']) : 0,
                    'sort_order' => $option['sort_order']
                ];
            }
            $questions_data[] = [
                'id' => $question['id'],
                'text' => $question['text'],
                'is_required' => $question['is_required'],
                'sort_order' => $question['sort_order'],
                'options' => $options_data
            ];
        }
        $existing_data[] = [
            'id' => $area['id'],
            'title' => $area['title'],
            'weight' => $area['weight'],
            'sort_order' => $area['sort_order'],
            'questions' => $questions_data
        ];
    }
    
    ?>
    <!-- JavaScript-based Editor -->
    <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #03679e; margin-bottom: 20px;">
        <strong>💡 Modalità User-Friendly:</strong> Aggiungi/modifica aree, domande e opzioni qui sotto. Trascina le domande e le opzioni per riordinarle. Tutte le modifiche vengono salvate quando premi il pulsante "💾 Salva Tutto" in basso.
    </div>
    
    <div id="boq-editor-container"></div>
    
    <div style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 5px; text-align: center;">
        <button id="boq-add-area-btn" style="background: #4caf50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; margin-right: 10px;">
            ➕ Aggiungi Area
        </button>
        <button id="boq-save-all-btn" style="background: #03679e; color: white; padding: 12px 40px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.2em; font-weight: bold;">
            💾 Salva Tutto
        </button>
    </div>
    
    <script>
    // Stato dell'editor
    let boqEditorState = <?php echo json_encode($existing_data); ?>;
    let boqNextTempId = -1;
    let boqDraggedItem = null;
    let boqDraggedContext = null;
    
    // Render completo dell'editor
    function boqRenderEditor() {
        const container = document.getElementById('boq-editor-container');
        container.innerHTML = '';
        
        if (boqEditorState.length === 0) {
            container.innerHTML = '<div style="padding: 40px; text-align: center; color: #999; background: #f9f9f9; border-radius: 5px;">Nessuna area. Clicca "Aggiungi Area" per iniziare.</div>';
            return;
        }
        
        boqEditorState.forEach((area, areaIdx) => {
            const areaDiv = document.createElement('div');
            areaDiv.style.cssText = 'background: white; border: 2px solid #03679e; border-radius: 5px; padding: 20px; margin-bottom: 20px;';
            
            // Area Header con edit
            const areaHeader = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #03679e;">
                    <div style="flex: 1;">
                        <input type="text" value="${boqEsc(area.title)}" onchange="boqUpdateArea(${areaIdx}, 'title', this.value)" 
                               style="font-size: 1.2em; font-weight: bold; color: #03679e; padding: 5px; border: 1px solid #ddd; border-radius: 3px; width: 60%;" placeholder="Titolo Area">
                        <div style="margin-top: 8px;">
                            <label>
                                Peso: <input type="text" 
                                       pattern="[0-9]*[.,]?[0-9]{0,3}" 
                                       maxlength="10"
                                       value="${area.weight}" 
                                       onchange="boqUpdateArea(${areaIdx}, 'weight', parseFloat(this.value.replace(',', '.')) || 0)" 
                                       oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/[,.]/g, (m, o, s) => s.indexOf(m) === o ? '.' : '').substring(0, 5)"
                                       style="width: 100px; padding: 4px; border: 1px solid #ddd; border-radius: 3px; text-align: right; font-family: monospace;" 
                                       placeholder="1.000">
                            </label>
                        </div>
                    </div>
                    <button onclick="boqDeleteArea(${areaIdx})" style="background: #f44336; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer;">🗑️ Elimina Area</button>
                </div>
            `;
            areaDiv.innerHTML = areaHeader;
            
            // Questions
            const questionsDiv = document.createElement('div');
            if (!area.questions || area.questions.length === 0) {
                questionsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999; background: #f9f9f9; border-radius: 3px; margin-bottom: 10px;">Nessuna domanda. Aggiungi una domanda con il pulsante sotto.</div>';
            } else {
                area.questions.forEach((question, qIdx) => {
                    const questionDiv = document.createElement('div');
                    questionDiv.style.cssText = 'background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 10px; cursor: move;';
                    questionDiv.draggable = true;
                    questionDiv.dataset.areaIdx = areaIdx;
                    questionDiv.dataset.qIdx = qIdx;
                    
                    // Drag events for questions
                    questionDiv.addEventListener('dragstart', (e) => {
                        boqDraggedItem = {type: 'question', areaIdx, qIdx};
                        questionDiv.style.opacity = '0.5';
                        e.dataTransfer.effectAllowed = 'move';
                    });
                    
                    questionDiv.addEventListener('dragend', (e) => {
                        questionDiv.style.opacity = '1';
                        boqDraggedItem = null;
                    });
                    
                    questionDiv.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        if (boqDraggedItem && boqDraggedItem.type === 'question' && boqDraggedItem.areaIdx === areaIdx) {
                            e.dataTransfer.dropEffect = 'move';
                            questionDiv.style.borderTop = '3px solid #03679e';
                        }
                    });
                    
                    questionDiv.addEventListener('dragleave', (e) => {
                        questionDiv.style.borderTop = '';
                    });
                    
                    questionDiv.addEventListener('drop', (e) => {
                        e.preventDefault();
                        questionDiv.style.borderTop = '';
                        if (boqDraggedItem && boqDraggedItem.type === 'question' && boqDraggedItem.areaIdx === areaIdx) {
                            const fromIdx = boqDraggedItem.qIdx;
                            const toIdx = qIdx;
                            if (fromIdx !== toIdx) {
                                const item = boqEditorState[areaIdx].questions.splice(fromIdx, 1)[0];
                                boqEditorState[areaIdx].questions.splice(toIdx, 0, item);
                                // Update sort_order
                                boqEditorState[areaIdx].questions.forEach((q, idx) => q.sort_order = idx);
                                boqRenderEditor();
                            }
                        }
                    });
                    
                    // Question header
                    const questionHeader = `
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div style="display: flex; align-items: start; gap: 10px; flex: 1;">
                                <div style="color: #999; font-size: 1.2em; cursor: move; padding: 5px;">☰</div>
                                <div style="flex: 1;">
                                    <textarea onchange="boqUpdateQuestion(${areaIdx}, ${qIdx}, 'text', this.value)" 
                                              style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-weight: bold;" rows="2" placeholder="Inserisci il testo della domanda">${boqEsc(question.text)}</textarea>
                                    <div style="margin-top: 5px;">
                                        <label>
                                            <input type="checkbox" ${question.is_required ? 'checked' : ''} onchange="boqUpdateQuestion(${areaIdx}, ${qIdx}, 'is_required', this.checked ? 1 : 0)"> 
                                            Obbligatoria
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <button onclick="boqDeleteQuestion(${areaIdx}, ${qIdx})" style="background: #f44336; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; margin-left: 10px;">🗑️</button>
                        </div>
                    `;
                    questionDiv.innerHTML = questionHeader;
                    
                    // Options
                    const optionsDiv = document.createElement('div');
                    optionsDiv.style.cssText = 'background: #f0f0f0; padding: 10px; border-radius: 3px;';
                    
                    if (!question.options || question.options.length === 0) {
                        optionsDiv.innerHTML = '<div style="color: #999; padding: 5px;">Nessuna opzione. Aggiungi con il pulsante sotto.</div>';
                    } else {
                        const optionsList = document.createElement('ul');
                        optionsList.style.cssText = 'list-style: none; padding: 0; margin: 0 0 10px 0;';
                        
                        question.options.forEach((option, oIdx) => {
                            const optionLi = document.createElement('li');
                            optionLi.style.cssText = 'padding: 8px; background: white; margin: 5px 0; border-radius: 3px; display: flex; gap: 10px; align-items: center; cursor: move;';
                            optionLi.draggable = true;
                            optionLi.dataset.areaIdx = areaIdx;
                            optionLi.dataset.qIdx = qIdx;
                            optionLi.dataset.oIdx = oIdx;
                            
                            // Drag events for options
                            optionLi.addEventListener('dragstart', (e) => {
                                boqDraggedItem = {type: 'option', areaIdx, qIdx, oIdx};
                                optionLi.style.opacity = '0.5';
                                e.dataTransfer.effectAllowed = 'move';
                                e.stopPropagation();
                            });
                            
                            optionLi.addEventListener('dragend', (e) => {
                                optionLi.style.opacity = '1';
                                boqDraggedItem = null;
                            });
                            
                            optionLi.addEventListener('dragover', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                if (boqDraggedItem && boqDraggedItem.type === 'option' && boqDraggedItem.areaIdx === areaIdx && boqDraggedItem.qIdx === qIdx) {
                                    e.dataTransfer.dropEffect = 'move';
                                    optionLi.style.borderTop = '2px solid #03679e';
                                }
                            });
                            
                            optionLi.addEventListener('dragleave', (e) => {
                                optionLi.style.borderTop = '';
                            });
                            
                            optionLi.addEventListener('drop', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                optionLi.style.borderTop = '';
                                if (boqDraggedItem && boqDraggedItem.type === 'option' && boqDraggedItem.areaIdx === areaIdx && boqDraggedItem.qIdx === qIdx) {
                                    const fromIdx = boqDraggedItem.oIdx;
                                    const toIdx = oIdx;
                                    if (fromIdx !== toIdx) {
                                        const item = boqEditorState[areaIdx].questions[qIdx].options.splice(fromIdx, 1)[0];
                                        boqEditorState[areaIdx].questions[qIdx].options.splice(toIdx, 0, item);
                                        // Update sort_order
                                        boqEditorState[areaIdx].questions[qIdx].options.forEach((opt, idx) => opt.sort_order = idx);
                                        boqRenderEditor();
                                    }
                                }
                            });
                            
                            optionLi.innerHTML = `
                                <div style="color: #999; font-size: 1em; cursor: move;">☰</div>
                                <input type="text" value="${boqEsc(option.text)}" onchange="boqUpdateOption(${areaIdx}, ${qIdx}, ${oIdx}, 'text', this.value)" 
                                       style="flex: 2; padding: 6px; border: 1px solid #ddd; border-radius: 3px;" placeholder="Testo opzione">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    Peso: <input type="text" 
                                           pattern="[0-9]*[.,]?[0-9]{0,3}" 
                                           maxlength="10"
                                           value="${option.weight}" 
                                           onchange="boqUpdateOption(${areaIdx}, ${qIdx}, ${oIdx}, 'weight', parseFloat(this.value.replace(',', '.')) || 0)" 
                                           oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/[,.]/g, (m, o, s) => s.indexOf(m) === o ? '.' : '').substring(0, 5)"
                                           style="width: 100px; padding: 6px; border: 1px solid #ddd; border-radius: 3px; text-align: right; font-family: monospace;" 
                                           placeholder="0.000">
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px; background: #fff3cd; padding: 4px 8px; border-radius: 3px; border: 1px solid #ffc107;">
                                    <input type="checkbox" ${option.is_na ? 'checked' : ''} onchange="boqUpdateOption(${areaIdx}, ${qIdx}, ${oIdx}, 'is_na', this.checked ? 1 : 0)" 
                                           style="width: 16px; height: 16px; cursor: pointer;">
                                    <span style="font-size: 13px; font-weight: 500;">N.A.</span>
                                </label>
                                <button onclick="boqDeleteOption(${areaIdx}, ${qIdx}, ${oIdx})" style="background: #f44336; color: white; padding: 4px 10px; border: none; border-radius: 3px; cursor: pointer;">✕</button>
                            `;
                            optionsList.appendChild(optionLi);
                        });
                        optionsDiv.appendChild(optionsList);
                    }
                    
                    // Add option button
                    const addOptionBtn = document.createElement('button');
                    addOptionBtn.textContent = '+ Aggiungi Opzione';
                    addOptionBtn.style.cssText = 'background: #4caf50; color: white; padding: 6px 15px; border: none; border-radius: 3px; cursor: pointer; width: 100%;';
                    addOptionBtn.onclick = () => boqAddOption(areaIdx, qIdx);
                    optionsDiv.appendChild(addOptionBtn);
                    
                    questionDiv.appendChild(optionsDiv);
                    questionsDiv.appendChild(questionDiv);
                });
            }
            areaDiv.appendChild(questionsDiv);
            
            // Add question button
            const addQuestionBtn = document.createElement('button');
            addQuestionBtn.textContent = '+ Aggiungi Domanda';
            addQuestionBtn.style.cssText = 'background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; width: 100%;';
            addQuestionBtn.onclick = () => boqAddQuestion(areaIdx);
            areaDiv.appendChild(addQuestionBtn);
            
            container.appendChild(areaDiv);
        });
    }
    
    // Helper: Escape HTML
    function boqEsc(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
    
    // CRUD Functions
    function boqAddArea() {
        boqEditorState.push({
            id: boqNextTempId--,
            title: '',
            weight: 1.00,
            sort_order: boqEditorState.length,
            questions: []
        });
        boqRenderEditor();
    }
    
    function boqUpdateArea(areaIdx, field, value) {
        boqEditorState[areaIdx][field] = value;
    }
    
    function boqDeleteArea(areaIdx) {
        if (confirm('Eliminare questa area e tutte le sue domande?')) {
            boqEditorState.splice(areaIdx, 1);
            boqRenderEditor();
        }
    }
    
    function boqAddQuestion(areaIdx) {
        if (!boqEditorState[areaIdx].questions) boqEditorState[areaIdx].questions = [];
        boqEditorState[areaIdx].questions.push({
            id: boqNextTempId--,
            text: '',
            is_required: 1,
            sort_order: boqEditorState[areaIdx].questions.length,
            options: []
        });
        boqRenderEditor();
    }
    
    function boqUpdateQuestion(areaIdx, qIdx, field, value) {
        boqEditorState[areaIdx].questions[qIdx][field] = value;
    }
    
    function boqDeleteQuestion(areaIdx, qIdx) {
        if (confirm('Eliminare questa domanda e tutte le sue opzioni?')) {
            boqEditorState[areaIdx].questions.splice(qIdx, 1);
            boqRenderEditor();
        }
    }
    
    function boqAddOption(areaIdx, qIdx) {
        if (!boqEditorState[areaIdx].questions[qIdx].options) boqEditorState[areaIdx].questions[qIdx].options = [];
        boqEditorState[areaIdx].questions[qIdx].options.push({
            id: boqNextTempId--,
            text: '',
            weight: 0.00,
            is_na: 0,
            sort_order: boqEditorState[areaIdx].questions[qIdx].options.length
        });
        boqRenderEditor();
    }
    
    function boqUpdateOption(areaIdx, qIdx, oIdx, field, value) {
        boqEditorState[areaIdx].questions[qIdx].options[oIdx][field] = value;
    }
    
    function boqDeleteOption(areaIdx, qIdx, oIdx) {
        if (confirm('Eliminare questa opzione?')) {
            boqEditorState[areaIdx].questions[qIdx].options.splice(oIdx, 1);
            boqRenderEditor();
        }
    }
    
    // Save All
    async function boqSaveAll() {
        const btn = document.getElementById('boq-save-all-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Salvataggio in corso...';
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'boq_action': 'save_structure',
                    'boq_nonce': '<?php echo wp_create_nonce('boq_admin_action'); ?>',
                    'questionnaire_id': '<?php echo $questionnaire_id; ?>',
                    'structure': JSON.stringify(boqEditorState)
                })
            });
            
            if (response.ok) {
                alert('✅ Struttura salvata con successo!');
                window.location.reload();
            } else {
                throw new Error('Errore nel salvataggio');
            }
        } catch (error) {
            alert('❌ Errore durante il salvataggio: ' + error.message);
            btn.disabled = false;
            btn.textContent = '💾 Salva Tutto';
        }
    }
    
    // Event Listeners
    document.getElementById('boq-add-area-btn').addEventListener('click', boqAddArea);
    document.getElementById('boq-save-all-btn').addEventListener('click', boqSaveAll);
    
    // Initial Render
    boqRenderEditor();
    </script>
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
                <strong>Nota:</strong> Il questionario verrà inviato agli ispettori per valutare l'operato del fornitore selezionato.<br>
                <small>💡 Puoi inserire più email valutatore: ogni valutatore riceverà un questionario separato e verrà creata una valutazione distinta nella tabella "Storico Invii".</small>
            </p>
            
            <form method="POST">
                <?php wp_nonce_field('boq_admin_action', 'boq_nonce'); ?>
                <input type="hidden" name="boq_action" value="send_questionnaire">
                <input type="hidden" name="questionnaire_id" value="<?php echo $send_id; ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                        Fornitore da Valutare * <span style="color: red;">(Obbligatorio)</span>
                    </label>
                    <input type="text" id="boq-user-search" placeholder="🔍 Cerca per nome o email..." 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 8px; font-size: 14px;">
                    <div id="boq-user-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 3px; background: white; display: none;">
                        <?php
                        // Cerca fornitori da valutare o subscriber
                        $users = get_users(['role__in' => ['hse', 'subscriber']]);
                        foreach ($users as $user):
                            $ragione_sociale = get_user_meta($user->ID, 'user_registration_rag_soc', true);
                            $display_text = $ragione_sociale ? $ragione_sociale : $user->display_name;
                        ?>
                            <div class="boq-user-option" data-id="<?php echo $user->ID; ?>" data-name="<?php echo esc_attr(strtolower($display_text)); ?>" data-email="<?php echo esc_attr(strtolower($user->user_email)); ?>" 
                                 style="padding: 10px; border-bottom: 1px solid #f0f0f0; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="color: #333;"><?php echo esc_html($display_text); ?></strong>
                                    <br>
                                    <small style="color: #666;">P.IVA: <?php echo esc_html($user->display_name); ?> | <?php echo esc_html($user->user_email); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="target_user_id" id="boq-user-id" required>
                    <div id="boq-selected-user" style="margin-top: 8px; padding: 10px; background: #e3f2fd; border-radius: 3px; display: none;">
                        <strong>Selezionato:</strong> <span id="boq-selected-user-name"></span>
                        <button type="button" onclick="boqClearUserSelection()" style="float: right; background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">✕ Cambia</button>
                    </div>
                    <small style="color: #666; display: block; margin-top: 5px;">Cerca e seleziona il fornitore il cui operato verrà valutato tramite questo questionario</small>
                    
                    <script>
                    (function() {
                        const searchInput = document.getElementById('boq-user-search');
                        const userList = document.getElementById('boq-user-list');
                        const userIdInput = document.getElementById('boq-user-id');
                        const selectedUserDiv = document.getElementById('boq-selected-user');
                        const selectedUserName = document.getElementById('boq-selected-user-name');
                        const userOptions = document.querySelectorAll('.boq-user-option');
                        
                        // Show list when search input is focused
                        searchInput.addEventListener('focus', function() {
                            userList.style.display = 'block';
                        });
                        
                        // Hide list when clicking outside
                        document.addEventListener('click', function(e) {
                            if (!searchInput.contains(e.target) && !userList.contains(e.target)) {
                                userList.style.display = 'none';
                            }
                        });
                        
                        // Search functionality
                        searchInput.addEventListener('input', function() {
                            const searchTerm = this.value.toLowerCase();
                            userList.style.display = 'block';
                            
                            userOptions.forEach(function(option) {
                                const name = option.getAttribute('data-name');
                                const email = option.getAttribute('data-email');
                                
                                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                                    option.style.display = 'flex';
                                } else {
                                    option.style.display = 'none';
                                }
                            });
                        });
                        
                        // Select user
                        userOptions.forEach(function(option) {
                            option.addEventListener('click', function() {
                                const userId = this.getAttribute('data-id');
                                const userName = this.querySelector('strong').textContent;
                                const userEmail = this.querySelector('small').textContent;
                                
                                userIdInput.value = userId;
                                selectedUserName.textContent = userName + ' (' + userEmail + ')';
                                selectedUserDiv.style.display = 'block';
                                searchInput.style.display = 'none';
                                userList.style.display = 'none';
                            });
                            
                            // Hover effect
                            option.addEventListener('mouseenter', function() {
                                this.style.backgroundColor = '#f5f5f5';
                            });
                            option.addEventListener('mouseleave', function() {
                                this.style.backgroundColor = 'white';
                            });
                        });
                        
                        window.boqClearUserSelection = function() {
                            userIdInput.value = '';
                            selectedUserDiv.style.display = 'none';
                            searchInput.style.display = 'block';
                            searchInput.value = '';
                            searchInput.focus();
                        };
                    })();
                    </script>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                        Email Valutatore/i * <span style="color: red;">(Obbligatorio)</span>
                    </label>
                    <textarea name="inspector_emails" required rows="3"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-family: inherit;"
                           placeholder="valutatore1@example.com&#10;valutatore2@example.com&#10;valutatore3@example.com"></textarea>
                    <small style="color: #666;">
                        💡 <strong>Più ispettori:</strong> Inserisci una email per riga o separale con virgola. 
                        Ogni valutatore riceverà un questionario separato e verrà creata una valutazione distinta per ciascuno.
                    </small>
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
                <th style="padding: 12px; text-align: left;">Ragione Sociale</th>
                <th style="padding: 12px; text-align: left;">P.IVA</th>
                <th style="padding: 12px; text-align: left;">Email Valutatore</th>
                <th style="padding: 12px; text-align: left;">Data Invio</th>
                <th style="padding: 12px; text-align: left;">Data Compilazione</th>
                <th style="padding: 12px; text-align: center;">Stato</th>
                <th style="padding: 12px; text-align: left;">Link Questionario</th>
                <th style="padding: 12px; text-align: center;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $assignments = $wpdb->get_results("
                SELECT a.*, q.title as questionnaire_title,
                       (SELECT MAX(r.answered_at) FROM {$wpdb->prefix}cogei_responses r WHERE r.assignment_id = a.id) as completion_date
                FROM {$wpdb->prefix}cogei_assignments a
                LEFT JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
                ORDER BY a.sent_at DESC
                LIMIT 100
            ", ARRAY_A);
            
            foreach ($assignments as $assignment):
                $hse_user_name = 'N/A';
                $ragione_sociale = '';
                if ($assignment['target_user_id']) {
                    $user = get_userdata($assignment['target_user_id']);
                    if ($user) {
                        $hse_user_name = $user->display_name;
                        $ragione_sociale = get_user_meta($user->ID, 'user_registration_rag_soc', true);
                    }
                }
                if (!$ragione_sociale) {
                    $ragione_sociale = '-';
                }
                
                // Genera link questionario - punta al file standalone /questionario/ (usa site_url perché la cartella è dentro l'installazione WP)
                $questionnaire_link = add_query_arg('boq_token', $assignment['token'], site_url('/questionario/'));
            ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;"><?php echo $assignment['id']; ?></td>
                <td style="padding: 12px;"><strong><?php echo esc_html($assignment['questionnaire_title']); ?></strong></td>
                <td style="padding: 12px;"><?php echo esc_html($ragione_sociale); ?></td>
                <td style="padding: 12px;"><?php echo esc_html($hse_user_name); ?></td>
                <td style="padding: 12px;"><?php echo esc_html($assignment['inspector_email']); ?></td>
                <td style="padding: 12px;"><?php echo date('d/m/Y H:i', strtotime($assignment['sent_at'])); ?></td>
                <td style="padding: 12px;">
                    <?php 
                    if ($assignment['completion_date']) {
                        echo date('d/m/Y H:i', strtotime($assignment['completion_date']));
                    } else {
                        echo '<span style="color: #999;">-</span>';
                    }
                    ?>
                </td>
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
                <td style="padding: 12px;">
                    <a href="<?php echo esc_url($questionnaire_link); ?>" target="_blank" style="color: #03679e; text-decoration: none; font-size: 0.9em; word-break: break-all;">
                        🔗 Apri Questionario
                    </a>
                    <button onclick="navigator.clipboard.writeText('<?php echo esc_js($questionnaire_link); ?>'); this.textContent='✓ Copiato!'; setTimeout(() => this.textContent='📋 Copia', 2000);" 
                            style="background: #e3f2fd; border: 1px solid #03679e; color: #03679e; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 0.85em; margin-left: 5px;">
                        📋 Copia
                    </button>
                </td>
                <td style="padding: 12px; text-align: center;">
                    <?php if ($assignment['status'] === 'completed'): ?>
                        <a href="?boq_tab=results&assignment=<?php echo $assignment['id']; ?>" style="color: #03679e; text-decoration: none;">
                            📊 Visualizza Risultato
                        </a>
                        <br>
                        <button onclick="boqOpenEditModal(<?php echo $assignment['id']; ?>)" 
                                style="background: #ff9800; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em; margin-top: 8px; transition: all 0.2s;">
                            ✏️ Modifica Risposte
                        </button>
                    <?php else: ?>
                        <span style="color: #999; font-size: 0.9em;">In attesa</span>
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
            $score = boq_getScore($assignment_id);
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
                        <strong>Fornitore Valutato:</strong> <?php echo esc_html($hse_name); ?><br>
                        <strong>Email Valutatore:</strong> <?php echo esc_html($assignment['inspector_email']); ?><br>
                        <strong>Data Invio:</strong> <?php echo date('d/m/Y H:i', strtotime($assignment['sent_at'])); ?>
                    </div>
                    <div style="background: #f0f0f0; padding: 20px; border-radius: 5px; text-align: center;">
                        <?php 
                        $stars = boq_convertScoreToStars($score);
                        echo boq_renderStarRating($stars); 
                        ?>
                        <div style="font-size: 2em; font-weight: bold; color: #03679e; margin: 15px 0 10px 0;">
                            <?php echo number_format($score, 2); ?> / 100
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
                                    <?php if ($option && isset($option['is_na']) && $option['is_na'] == 1): ?>
                                        <div style="margin-top: 8px; padding: 8px; background: #f5f5f5; border-left: 4px solid #ffc107; border-radius: 3px;">
                                            <span style="color: #6c757d;">✓ <?php echo esc_html($option['text']); ?></span>
                                            <span style="display: inline-block; background: #ffc107; color: #000; font-weight: bold; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">N.A.</span>
                                            <span style="color: #999; font-size: 12px; font-style: italic; margin-left: 8px;">(Peso massimo applicato)</span>
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-top: 8px; padding: 8px; background: #e3f2fd; border-left: 4px solid #03679e; border-radius: 3px;">
                                            ✓ <?php echo esc_html($option['text']); ?>
                                            <span style="color: #666; font-size: 0.9em;">
                                                (Peso Opzione: <?php echo $option['weight']; ?>, 
                                                Punteggio: <?php echo round($response['computed_score'], 4); ?>)
                                            </span>
                                        </div>
                                    <?php endif; ?>
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
                    <th style="padding: 12px; text-align: left;">Ragione Sociale</th>
                    <th style="padding: 12px; text-align: left;">P.IVA</th>
                    <th style="padding: 12px; text-align: center;">Punteggio</th>
                    <th style="padding: 12px; text-align: center;">Valutazione</th>
                    <th style="padding: 12px; text-align: center;">Data Invio</th>
                    <th style="padding: 12px; text-align: center;">Data Compilazione</th>
                    <th style="padding: 12px; text-align: center;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $completed_assignments = $wpdb->get_results("
                    SELECT a.*, q.title as questionnaire_title,
                           (SELECT MAX(r.answered_at) FROM {$wpdb->prefix}cogei_responses r WHERE r.assignment_id = a.id) as completion_date
                    FROM {$wpdb->prefix}cogei_assignments a
                    LEFT JOIN {$wpdb->prefix}cogei_questionnaires q ON a.questionnaire_id = q.id
                    WHERE a.status = 'completed'
                    ORDER BY a.sent_at DESC
                    LIMIT 100
                ", ARRAY_A);
                
                foreach ($completed_assignments as $assignment):
                    $score = boq_getScore($assignment['id']);
                    $evaluation = boq_evaluateScore($score);
                    
                    $hse_user = get_userdata($assignment['target_user_id']);
                    $hse_name = $hse_user ? $hse_user->display_name : 'N/A';
                    $ragione_sociale = $hse_user ? get_user_meta($hse_user->ID, 'user_registration_rag_soc', true) : '';
                    if (!$ragione_sociale) {
                        $ragione_sociale = '-';
                    }
                    
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
                    <td style="padding: 12px;"><?php echo esc_html($ragione_sociale); ?></td>
                    <td style="padding: 12px;"><?php echo esc_html($hse_name); ?></td>
                    <td style="padding: 12px; text-align: center; font-weight: bold;">
                        <?php 
                        $stars = boq_convertScoreToStars($score);
                        echo boq_renderStarRating($stars); 
                        ?>
                        <div style="margin-top: 5px; color: #666; font-size: 13px;">
                            <?php echo number_format($score, 2); ?> / 100
                        </div>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="padding: 6px 16px; border-radius: 5px; background: <?php echo $eval_color; ?>; color: white; font-weight: bold; white-space: nowrap; display: inline-block;">
                            <?php echo esc_html($evaluation); ?>
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <?php echo date('d/m/Y H:i', strtotime($assignment['sent_at'])); ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <?php 
                        if ($assignment['completion_date']) {
                            echo date('d/m/Y H:i', strtotime($assignment['completion_date']));
                        } else {
                            echo '<span style="color: #999;">-</span>';
                        }
                        ?>
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

/**
 * Convert normalized score (0-1) to star rating (0-5)
 * Uses half-star precision
 */
function boq_convertScoreToStars($score) {
    // Convert 0-100 score to 0-5 scale
    $stars = ($score / 100) * 5;
    
    // Round to nearest 0.5
    $stars = round($stars * 2) / 2;
    
    // Clamp between 0 and 5
    $stars = max(0, min(5, $stars));
    
    return $stars;
}

/**
 * Render star rating HTML
 * @param float $stars - Number of stars (0-5, can have 0.5 increments)
 * @return string HTML with star visualization
 */
function boq_renderStarRating($stars) {
    $full_stars = floor($stars);
    $half_star = ($stars - $full_stars) >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    
    $html = '<span style="color: #FFD700; font-size: 20px; letter-spacing: 2px;">';
    
    // Full stars
    for ($i = 0; $i < $full_stars; $i++) {
        $html .= '★';
    }
    $html .= '</span>';
    
    // Half star using Unicode character
    if ($half_star) {
        $html .= '<span style="color: #FFD700; font-size: 20px; letter-spacing: 2px;">☆</span>';
    }
    
    // Empty stars
    $html .= '<span style="color: #DDD; font-size: 20px; letter-spacing: 2px;">';
    for ($i = 0; $i < $empty_stars; $i++) {
        $html .= '☆';
    }
    $html .= '</span>';
    
    // Add numeric value
    $html .= ' <span style="color: #666; font-size: 14px;">(' . number_format($stars, 1) . ')</span>';
    
    return $html;
}

/**
 * AJAX Handler: Get supplier questionnaires
 * NOTE: Moved to standalone file ajax_fornitori/get_supplier_questionnaires.php
 * Keeping this commented for reference
 */
/*
add_action('wp_ajax_boq_get_supplier_questionnaires', 'boq_ajax_get_supplier_questionnaires');
function boq_ajax_get_supplier_questionnaires() {
    // This function has been moved to ajax_fornitori/get_supplier_questionnaires.php
}
*/

/**
 * AJAX Handler: Get questionnaire details
 * NOTE: Moved to standalone file ajax_fornitori/get_questionnaire_details.php
 * Keeping this commented for reference
 */
/*
add_action('wp_ajax_boq_get_questionnaire_details', 'boq_ajax_get_questionnaire_details');
function boq_ajax_get_questionnaire_details() {
    // This function has been moved to ajax_fornitori/get_questionnaire_details.php
}
*/

/**
 * Tab: Votazioni Albo Fornitori
 * Shows supplier rankings based on average questionnaire scores
 */
function boq_renderRatingsTab() {
    global $wpdb;
    
    // Get all completed assignments grouped by supplier
    $query = "
        SELECT 
            a.target_user_id as user_id,
            COUNT(DISTINCT a.id) as total_questionnaires,
            COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_questionnaires,
            GROUP_CONCAT(a.id ORDER BY a.id) as assignment_ids
        FROM {$wpdb->prefix}cogei_assignments a
        WHERE a.status = 'completed'
        GROUP BY a.target_user_id
    ";
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    // Calculate average score for each supplier using correct formula
    foreach ($results as &$result) {
        $assignment_ids = array_unique(array_filter(explode(',', $result['assignment_ids']), function($id) {
            return !empty($id) && is_numeric($id);
        }));
        
        $scores = [];
        foreach ($assignment_ids as $assignment_id) {
            $score = boq_getScore(intval($assignment_id));
            // Accept any numeric score, including 0
            if ($score !== false && $score !== null) {
                $scores[] = floatval($score);
            }
        }
        
        $result['avg_score'] = !empty($scores) ? (array_sum($scores) / count($scores)) : 0;
        $result['score_count'] = count($scores);
        $result['individual_scores'] = $scores; // For debugging
    }
    
    // Filter out suppliers with no completed questionnaires (keep those with score 0)
    $results = array_filter($results, function($r) { 
        return $r['completed_questionnaires'] > 0; 
    });
    
    // Remove duplicates by user_id (in case GROUP BY didn't work properly)
    $unique_results = [];
    $seen_user_ids = [];
    foreach ($results as $result) {
        if (!in_array($result['user_id'], $seen_user_ids)) {
            $unique_results[] = $result;
            $seen_user_ids[] = $result['user_id'];
        }
    }
    $results = $unique_results;
    
    usort($results, function($a, $b) { return $b['avg_score'] <=> $a['avg_score']; });
    
    ?>
    <div style="background: white; padding: 20px; border-radius: 5px;">
        <h2 style="color: #03679e; margin-top: 0;">⭐ Votazioni Albo Fornitori</h2>
        <p style="color: #666; margin-bottom: 20px;">
            Classifica fornitori basata sulla media di tutti i questionari completati
        </p>
        
        <?php if (empty($results)): ?>
            <div style="padding: 40px; text-align: center; background: #f9f9f9; border-radius: 5px; color: #999;">
                <p style="font-size: 18px;">📊 Nessuna valutazione disponibile</p>
                <p>Completa alcuni questionari per vedere le votazioni dei fornitori.</p>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <thead>
                    <tr style="background: #03679e; color: white;">
                        <th style="padding: 15px; text-align: left; border-bottom: 2px solid #025a85;">Posizione</th>
                        <th style="padding: 15px; text-align: left; border-bottom: 2px solid #025a85;">Fornitore</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #025a85;">Valutazione</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #025a85;">Punteggio</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #025a85;">Questionari</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $position = 1;
                    foreach ($results as $result): 
                        $user = get_userdata($result['user_id']);
                        $user_name = $user ? $user->display_name : 'Utente #' . $result['user_id'];
                        $avg_score = floatval($result['avg_score']);
                        $stars = boq_convertScoreToStars($avg_score);
                        
                        // Determine row background color based on rating
                        $bg_color = '#fff';
                        if ($stars >= 4.5) {
                            $bg_color = '#f0fdf4'; // Green tint for excellent
                        } elseif ($stars >= 3.5) {
                            $bg_color = '#fefef0'; // Yellow tint for good
                        } elseif ($stars < 2.5) {
                            $bg_color = '#fef2f2'; // Red tint for poor
                        }
                    ?>
                    <tr style="border-bottom: 1px solid #eee; background: <?php echo $bg_color; ?>;">
                        <td style="padding: 15px; text-align: center;">
                            <?php 
                            // Add medals for top 3
                            if ($position === 1) {
                                echo '<span style="font-size: 24px;">🥇</span> ';
                            } elseif ($position === 2) {
                                echo '<span style="font-size: 24px;">🥈</span> ';
                            } elseif ($position === 3) {
                                echo '<span style="font-size: 24px;">🥉</span> ';
                            }
                            echo '<strong style="font-size: 18px;">' . $position . '</strong>';
                            ?>
                        </td>
                        <td style="padding: 15px;">
                            <?php 
                            $ragione_sociale = get_user_meta($result['user_id'], 'user_registration_rag_soc', true);
                            $piva = $user ? $user->display_name : '';
                            $email = $user ? $user->user_email : '';
                            $display_name = $ragione_sociale ? $ragione_sociale : $user_name;
                            ?>
                            <strong style="font-size: 16px; color: #333;">🏢 <?php echo esc_html($display_name); ?></strong>
                            <br>
                            <?php if ($piva): ?>
                            <span style="color: #666; font-size: 13px;">🔢 P.IVA: <?php echo esc_html($piva); ?></span>
                            <br>
                            <?php endif; ?>
                            <?php if ($email): ?>
                            <span style="color: #999; font-size: 12px;">📧 <?php echo esc_html($email); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <?php echo boq_renderStarRating($stars); ?>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <strong style="font-size: 16px; color: #03679e;">
                                <?php echo number_format($avg_score, 2); ?>
                            </strong>
                            <br>
                            <span style="color: #999; font-size: 12px;">/ 100</span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <a href="#" 
                               class="boq-questionnaire-count" 
                               data-user-id="<?php echo $result['user_id']; ?>"
                               data-user-name="<?php echo esc_attr($display_name); ?>"
                               style="background: #03679e; color: white; padding: 5px 12px; border-radius: 15px; font-weight: bold; text-decoration: none; display: inline-block; cursor: pointer; transition: background 0.3s;"
                               onmouseover="this.style.background='#025a85'"
                               onmouseout="this.style.background='#03679e'"
                               onclick="boqOpenQuestionnaireModal(event, <?php echo $result['user_id']; ?>, '<?php echo esc_js($display_name); ?>')">
                                <?php echo $result['completed_questionnaires']; ?>
                            </a>
                        </td>
                    </tr>
                    <?php 
                    $position++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; border-left: 4px solid #03679e;">
                <strong style="color: #03679e;">Legenda Valutazione:</strong><br>
                <span style="color: #FFD700;">★★★★★</span> 4.5-5.0 = Eccellente<br>
                <span style="color: #FFD700;">★★★★</span>☆ 3.5-4.4 = Molto Buono<br>
                <span style="color: #FFD700;">★★★</span>☆☆ 2.5-3.4 = Adeguato<br>
                <span style="color: #FFD700;">★★</span>☆☆☆ 1.5-2.4 = Critico<br>
                <span style="color: #FFD700;">★</span>☆☆☆☆ 0.0-1.4 = Inadeguato
            </div>
        <?php endif; ?>
        
        <!-- Modal for Import Questionnaire -->
        <div id="boqImportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10002; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 10px; max-width: 500px; width: 95%; padding: 30px; position: relative;">
                <button onclick="boqCloseImportModal()" style="position: absolute; top: 15px; right: 15px; background: #f44336; color: white; border: none; border-radius: 50%; width: 35px; height: 35px; font-size: 20px; cursor: pointer; font-weight: bold;">×</button>
                <h2 style="color: #03679e; margin-bottom: 20px; padding-right: 40px;">📦 Importa Questionario</h2>
                <p style="color: #666; margin-bottom: 20px;">Seleziona un file JSON precedentemente esportato per importare il questionario in questa installazione.</p>
                <div id="boqImportResult" style="display: none; margin-bottom: 15px;"></div>
                <form id="boqImportForm" enctype="multipart/form-data">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('boq_import_questionnaire'); ?>">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 8px;">File JSON del questionario *</label>
                        <input type="file" name="questionnaire_file" id="boqImportFile" accept=".json"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                    </div>
                    <button type="submit" id="boqImportBtn" style="background: #03679e; color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px;">
                        📦 Importa
                    </button>
                </form>
            </div>
        </div>

        <!-- Modal for Questionnaire List -->
        <div id="boqQuestionnaireModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 10px; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto; padding: 30px; position: relative;">
                <button onclick="boqCloseModal()" style="position: absolute; top: 15px; right: 15px; background: #f44336; color: white; border: none; border-radius: 50%; width: 35px; height: 35px; font-size: 20px; cursor: pointer; font-weight: bold;">×</button>
                <h2 id="boqModalTitle" style="color: #03679e; margin-bottom: 20px; padding-right: 40px;">📊 Questionari</h2>
                <div id="boqModalContent" style="min-height: 100px;">
                    <div style="text-align: center; padding: 40px; color: #999;">
                        Caricamento in corso...
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal for Questionnaire Details -->
        <div id="boqDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 10px; max-width: 900px; width: 95%; max-height: 85vh; overflow-y: auto; padding: 30px; position: relative;">
                <button onclick="boqCloseDetailsModal()" style="position: absolute; top: 15px; right: 15px; background: #f44336; color: white; border: none; border-radius: 50%; width: 35px; height: 35px; font-size: 20px; cursor: pointer; font-weight: bold;">×</button>
                <div id="boqDetailsContent" style="min-height: 200px;">
                    <div style="text-align: center; padding: 40px; color: #999;">
                        Caricamento dettagli...
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function boqOpenQuestionnaireModal(event, userId, userName) {
            event.preventDefault();
            const modal = document.getElementById('boqQuestionnaireModal');
            const title = document.getElementById('boqModalTitle');
            const content = document.getElementById('boqModalContent');
            
            title.innerHTML = '📊 Questionari di ' + userName;
            content.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #03679e; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 15px; color: #666;">Caricamento...</p></div>';
            modal.style.display = 'flex';
            
            // AJAX request to get questionnaires using standalone endpoint
            fetch('<?php echo site_url('/ajax_fornitori/get_supplier_questionnaires.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'user_id=' + userId
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        content.innerHTML = data.html;
                    } else {
                        content.innerHTML = '<div style="padding: 20px; text-align: center; color: #c00;">Errore: ' + (data.error || 'Impossibile caricare i dati') + '</div>';
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response text:', text);
                    content.innerHTML = '<div style="padding: 20px; color: #c00;"><strong>Errore JSON:</strong><br><pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">' + text.substring(0, 500) + '</pre></div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<div style="padding: 20px; text-align: center; color: #c00;">Errore: ' + error.message + '</div>';
            });
        }
        
        function boqCloseModal() {
            document.getElementById('boqQuestionnaireModal').style.display = 'none';
        }
        
        function boqOpenDetails(assignmentId) {
            const modal = document.getElementById('boqDetailsModal');
            const content = document.getElementById('boqDetailsContent');
            
            content.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #03679e; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 15px; color: #666;">Caricamento dettagli...</p></div>';
            modal.style.display = 'flex';
            
            // AJAX request to get questionnaire details using standalone endpoint
            fetch('<?php echo site_url('/ajax_fornitori/get_questionnaire_details.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'assignment_id=' + assignmentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    content.innerHTML = data.html;
                } else {
                    content.innerHTML = '<div style="padding: 20px; text-align: center; color: #c00;">Errore: ' + (data.error || 'Impossibile caricare i dettagli') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<div style="padding: 20px; text-align: center; color: #c00;">Errore: Impossibile caricare i dettagli</div>';
            });
        }
        
        function boqCloseDetailsModal() {
            document.getElementById('boqDetailsModal').style.display = 'none';
        }
        
        // Close modals on click outside
        document.getElementById('boqQuestionnaireModal')?.addEventListener('click', function(e) {
            if (e.target === this) boqCloseModal();
        });
        document.getElementById('boqDetailsModal')?.addEventListener('click', function(e) {
            if (e.target === this) boqCloseDetailsModal();
        });
        
        // Add spinner animation
        const style = document.createElement('style');
        style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
        document.head.appendChild(style);

        // ---- Import Modal Functions ----
        function boqOpenImportModal() {
            const modal = document.getElementById('boqImportModal');
            if (modal) {
                document.getElementById('boqImportResult').style.display = 'none';
                document.getElementById('boqImportForm').reset();
                document.getElementById('boqImportBtn').disabled = false;
                document.getElementById('boqImportBtn').textContent = '📦 Importa';
                modal.style.display = 'flex';
            }
        }

        function boqCloseImportModal() {
            const modal = document.getElementById('boqImportModal');
            if (modal) modal.style.display = 'none';
        }

        document.getElementById('boqImportModal')?.addEventListener('click', function(e) {
            if (e.target === this) boqCloseImportModal();
        });

        document.getElementById('boqImportForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('boqImportFile');
            const resultDiv = document.getElementById('boqImportResult');
            const btn = document.getElementById('boqImportBtn');

            if (!fileInput.files || fileInput.files.length === 0) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div style="background:#fff3cd;color:#856404;padding:10px;border-radius:4px;border:1px solid #ffc107;">⚠️ Seleziona un file JSON da importare.</div>';
                return;
            }

            btn.disabled = true;
            btn.textContent = '⏳ Importazione in corso...';
            resultDiv.style.display = 'none';

            const formData = new FormData(this);

            fetch('<?php echo site_url('/ajax_fornitori/import_questionnaire.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.style.display = 'block';
                if (data.success) {
                    resultDiv.innerHTML = '<div style="background:#d4edda;color:#155724;padding:10px;border-radius:4px;border:1px solid #c3e6cb;">✅ ' + (data.message || 'Questionario importato con successo.') + ' (<strong>' + (data.title || '') + '</strong>)</div>';
                    btn.textContent = '✅ Importato';
                    setTimeout(function() { window.location.reload(); }, 1500); // Attendi 1.5s per mostrare il messaggio di successo
                } else {
                    resultDiv.innerHTML = '<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:4px;border:1px solid #f5c6cb;">❌ ' + (data.error || 'Errore durante l\'importazione.') + '</div>';
                    btn.disabled = false;
                    btn.textContent = '📦 Importa';
                }
            })
            .catch(error => {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:4px;border:1px solid #f5c6cb;">❌ Errore: ' + error.message + '</div>';
                btn.disabled = false;
                btn.textContent = '📦 Importa';
            });
        });
        </script>
    </div>
    <?php
}

// ================== RENDERING PRINCIPALE ==================

// Render interface se non siamo in modalità token pubblico
if (!isset($_GET['boq_token'])) {
    boq_renderAdminInterface();
}

?>

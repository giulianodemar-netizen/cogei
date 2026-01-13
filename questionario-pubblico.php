<?php
/**
 * Questionario Pubblico - File Standalone
 * 
 * Questo file è un'interfaccia pubblica standalone per la compilazione dei questionari.
 * Può essere posizionato in una cartella fisica del server (es: /questionario/)
 * e sarà accessibile tramite URL diretto (es: https://site.com/questionario/?boq_token=xxx)
 * 
 * SETUP:
 * 1. Copiare questo file in una cartella sul server (es: /public_html/questionario/index.php)
 * 2. Il sistema genererà automaticamente URL che puntano a questa cartella
 * 3. Il file si connette al database WordPress per leggere i dati dei questionari
 * 
 * @version 1.0
 */

// Carica WordPress
$wp_load_path = '../wp-load.php'; // Percorso relativo a wp-load.php dalla cartella questionario
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    // Prova percorsi alternativi
    $alternative_paths = [
        '../../wp-load.php',
        '../../../wp-load.php',
        dirname(__FILE__) . '/../wp-load.php',
        dirname(__FILE__) . '/../../wp-load.php'
    ];
    
    $loaded = false;
    foreach ($alternative_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        die('Errore: Impossibile caricare WordPress. Verificare il percorso di wp-load.php');
    }
}

global $wpdb;

/**
 * Convert normalized score (0-1) to star rating (0-5)
 * Uses half-star precision
 */
function boq_convertScoreToStars($score) {
    // Convert 0-1 score to 0-5 scale
    $stars = $score * 5;
    
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
    
    $html = '<span style="color: #FFD700; font-size: 24px; letter-spacing: 2px;">';
    
    // Full stars
    for ($i = 0; $i < $full_stars; $i++) {
        $html .= '★';
    }
    $html .= '</span>';
    
    // Half star using Unicode character
    if ($half_star) {
        $html .= '<span style="color: #FFD700; font-size: 24px; letter-spacing: 2px;">☆</span>';
    }
    
    // Empty stars
    $html .= '<span style="color: #DDD; font-size: 24px; letter-spacing: 2px;">';
    for ($i = 0; $i < $empty_stars; $i++) {
        $html .= '☆';
    }
    $html .= '</span>';
    
    // Add numeric value
    $html .= ' <span style="color: #666; font-size: 16px;">(' . number_format($stars, 1) . ')</span>';
    
    return $html;
}

// Verifica che ci sia un token
if (!isset($_GET['boq_token']) || empty($_GET['boq_token'])) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Questionario</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .error { background: #fee; border: 1px solid #fcc; padding: 20px; border-radius: 5px; color: #c00; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>⚠️ Token Mancante</h2>
            <p>Per accedere al questionario è necessario un token valido. Verifica il link ricevuto via email.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$token = sanitize_text_field($_GET['boq_token']);

// Recupera l'assignment dal token
$table_assignments = $wpdb->prefix . 'cogei_assignments';
$assignment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_assignments WHERE token = %s",
    $token
), ARRAY_A);

if (!$assignment) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Questionario - Token Non Valido</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .error { background: #fee; border: 1px solid #fcc; padding: 20px; border-radius: 5px; color: #c00; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Token Non Valido</h2>
            <p>Il token fornito non è valido o il questionario non è più disponibile.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Verifica se il questionario è già stato completato
if ($assignment['status'] === 'completed') {
    $table_responses = $wpdb->prefix . 'cogei_responses';
    $responses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_responses WHERE assignment_id = %d",
        $assignment['id']
    ), ARRAY_A);
    
    $total_score = 0;
    $count = 0;
    foreach ($responses as $resp) {
        $total_score += floatval($resp['computed_score']);
        $count++;
    }
    
    $final_score = $count > 0 ? $total_score / $count : 0;
    
    // Determina valutazione
    if ($final_score >= 0.85) {
        $evaluation = "Eccellente";
        $eval_class = "excellent";
    } elseif ($final_score >= 0.70) {
        $evaluation = "Molto Buono";
        $eval_class = "very-good";
    } elseif ($final_score >= 0.55) {
        $evaluation = "Adeguato";
        $eval_class = "adequate";
    } elseif ($final_score >= 0.40) {
        $evaluation = "Critico";
        $eval_class = "critical";
    } else {
        $evaluation = "Inadeguato";
        $eval_class = "inadequate";
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Questionario Completato</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
            .success { background: #efe; border: 1px solid #cfc; padding: 30px; border-radius: 10px; text-align: center; }
            .score { font-size: 48px; font-weight: bold; margin: 20px 0; }
            .excellent { color: #0a0; }
            .very-good { color: #6a0; }
            .adequate { color: #a60; }
            .critical { color: #c60; }
            .inadequate { color: #c00; }
        </style>
    </head>
    <body>
        <div class="success">
            <h1>✅ Questionario Già Completato</h1>
            <p>Hai già completato questo questionario in data: <strong><?php echo esc_html(date('d/m/Y H:i', strtotime($responses[0]['answered_at']))); ?></strong></p>
            <div style="margin: 20px 0;">
                <?php 
                $stars = boq_convertScoreToStars($final_score);
                echo boq_renderStarRating($stars); 
                ?>
            </div>
            <div class="score <?php echo $eval_class; ?>">
                <?php echo number_format($final_score * 100, 1); ?>%
            </div>
            <h2>Valutazione: <span class="<?php echo $eval_class; ?>"><?php echo esc_html($evaluation); ?></span></h2>
            <p style="margin-top: 30px; color: #666;">Grazie per la tua collaborazione!</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Gestisci invio form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_questionnaire'])) {
    if (!isset($_POST['boq_nonce']) || !wp_verify_nonce($_POST['boq_nonce'], 'submit_questionnaire_' . $assignment['id'])) {
        die('Errore di sicurezza. Riprova.');
    }
    
    // Recupera tutte le domande del questionario
    $table_questions = $wpdb->prefix . 'cogei_questions';
    $table_areas = $wpdb->prefix . 'cogei_areas';
    $table_options = $wpdb->prefix . 'cogei_options';
    $table_responses = $wpdb->prefix . 'cogei_responses';
    
    $areas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_areas WHERE questionnaire_id = %d ORDER BY sort_order",
        $assignment['questionnaire_id']
    ), ARRAY_A);
    
    $all_answered = true;
    $error_message = '';
    
    foreach ($areas as $area) {
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_questions WHERE area_id = %d ORDER BY sort_order",
            $area['id']
        ), ARRAY_A);
        
        foreach ($questions as $question) {
            $answer_key = 'question_' . $question['id'];
            
            if ($question['is_required'] && (!isset($_POST[$answer_key]) || empty($_POST[$answer_key]))) {
                $all_answered = false;
                $error_message = 'Per favore, rispondi a tutte le domande obbligatorie.';
                break 2;
            }
            
            if (isset($_POST[$answer_key]) && !empty($_POST[$answer_key])) {
                $option_id = intval($_POST[$answer_key]);
                
                // Recupera peso opzione
                $option = $wpdb->get_row($wpdb->prepare(
                    "SELECT weight FROM $table_options WHERE id = %d",
                    $option_id
                ), ARRAY_A);
                
                // Calcola punteggio
                $computed_score = floatval($option['weight']) * floatval($area['weight']);
                
                // Salva risposta
                $wpdb->insert($table_responses, [
                    'assignment_id' => $assignment['id'],
                    'question_id' => $question['id'],
                    'selected_option_id' => $option_id,
                    'computed_score' => $computed_score,
                    'answered_at' => current_time('mysql')
                ]);
            }
        }
    }
    
    if ($all_answered) {
        // Aggiorna stato assignment
        $wpdb->update(
            $table_assignments,
            ['status' => 'completed'],
            ['id' => $assignment['id']]
        );
        
        // Calcola punteggio finale
        $responses = $wpdb->get_results($wpdb->prepare(
            "SELECT computed_score FROM $table_responses WHERE assignment_id = %d",
            $assignment['id']
        ), ARRAY_A);
        
        $total_score = 0;
        $count = count($responses);
        foreach ($responses as $resp) {
            $total_score += floatval($resp['computed_score']);
        }
        
        $final_score = $count > 0 ? $total_score / $count : 0;
        
        // Determina valutazione
        if ($final_score >= 0.85) {
            $evaluation = "Eccellente";
            $eval_class = "excellent";
        } elseif ($final_score >= 0.70) {
            $evaluation = "Molto Buono";
            $eval_class = "very-good";
        } elseif ($final_score >= 0.55) {
            $evaluation = "Adeguato";
            $eval_class = "adequate";
        } elseif ($final_score >= 0.40) {
            $evaluation = "Critico";
            $eval_class = "critical";
        } else {
            $evaluation = "Inadeguato";
            $eval_class = "inadequate";
        }
        
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Questionario Completato</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
                .success { background: #efe; border: 1px solid #cfc; padding: 30px; border-radius: 10px; text-align: center; }
                .score { font-size: 48px; font-weight: bold; margin: 20px 0; }
                .excellent { color: #0a0; }
                .very-good { color: #6a0; }
                .adequate { color: #a60; }
                .critical { color: #c60; }
                .inadequate { color: #c00; }
            </style>
        </head>
        <body>
            <div class="success">
                <h1>✅ Questionario Completato con Successo!</h1>
                <p>Grazie per aver completato il questionario.</p>
                <div style="margin: 20px 0;">
                    <?php 
                    $stars = boq_convertScoreToStars($final_score);
                    echo boq_renderStarRating($stars); 
                    ?>
                </div>
                <div class="score <?php echo $eval_class; ?>">
                    <?php echo number_format($final_score * 100, 1); ?>%
                </div>
                <h2>Valutazione: <span class="<?php echo $eval_class; ?>"><?php echo esc_html($evaluation); ?></span></h2>
                <p style="margin-top: 30px; color: #666;">Le tue risposte sono state registrate con successo.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Carica il questionario
$table_questionnaires = $wpdb->prefix . 'cogei_questionnaires';
$table_areas = $wpdb->prefix . 'cogei_areas';
$table_questions = $wpdb->prefix . 'cogei_questions';
$table_options = $wpdb->prefix . 'cogei_options';

$questionnaire = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_questionnaires WHERE id = %d",
    $assignment['questionnaire_id']
), ARRAY_A);

$areas = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_areas WHERE questionnaire_id = %d ORDER BY sort_order",
    $questionnaire['id']
), ARRAY_A);

// Recupera info fornitore
$hse_user = get_userdata($assignment['target_user_id']);
$hse_name = $hse_user ? $hse_user->display_name : 'Fornitore';
$ragione_sociale = get_user_meta($assignment['target_user_id'], 'user_registration_rag_soc', true);
$fornitore_piva = $hse_user ? $hse_user->display_name : '';
$fornitore_email = $hse_user ? $hse_user->user_email : '';
$fornitore_display_name = $ragione_sociale ? $ragione_sociale : ($hse_user ? $hse_user->display_name : 'Fornitore');

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($questionnaire['title']); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 { margin-bottom: 10px; font-size: 28px; }
        .header p { opacity: 0.9; font-size: 16px; }
        .hse-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px 30px;
            margin: 0;
        }
        .hse-info strong { color: #667eea; }
        .content { padding: 30px; }
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .area {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .area-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 2px solid #667eea;
        }
        .area-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .area-weight {
            font-size: 13px;
            color: #666;
        }
        .question {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .question:last-child { border-bottom: none; }
        .question-text {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 15px;
        }
        .question-text .required {
            color: #c00;
            font-weight: bold;
        }
        .options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .option {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .option input[type="radio"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .option label {
            cursor: pointer;
            flex: 1;
            font-size: 15px;
        }
        .option.selected {
            border-color: #667eea;
            background: #f0f3ff;
        }
        .submit-container {
            margin-top: 30px;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 50px;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 13px;
        }
    </style>
    <script>
        // Evidenzia opzione selezionata
        document.addEventListener('DOMContentLoaded', function() {
            const options = document.querySelectorAll('.option');
            options.forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                option.addEventListener('click', function() {
                    // Rimuovi selected da tutte le opzioni dello stesso gruppo
                    const name = radio.name;
                    document.querySelectorAll('input[name="' + name + '"]').forEach(r => {
                        r.closest('.option').classList.remove('selected');
                    });
                    // Aggiungi selected all'opzione cliccata
                    radio.checked = true;
                    option.classList.add('selected');
                });
                
                // Imposta selected se già selezionato
                if (radio.checked) {
                    option.classList.add('selected');
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 <?php echo esc_html($questionnaire['title']); ?></h1>
            <?php if ($questionnaire['description']): ?>
                <p><?php echo esc_html($questionnaire['description']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="hse-info">
            <p style="font-size: 18px; margin-bottom: 10px;">
                <strong>🏢 Fornitore Valutato:</strong> 
                <span style="color: #03679e; font-size: 20px;"><?php echo esc_html($fornitore_display_name); ?></span>
            </p>
            <?php if ($fornitore_piva): ?>
            <p style="margin-top: 8px; font-size: 15px;">
                <strong>🔢 P.IVA:</strong> <?php echo esc_html($fornitore_piva); ?>
            </p>
            <?php endif; ?>
            <?php if ($fornitore_email): ?>
            <p style="margin-top: 8px; font-size: 15px;">
                <strong>📧 Email:</strong> <?php echo esc_html($fornitore_email); ?>
            </p>
            <?php endif; ?>
            <p style="margin-top: 10px; font-size: 14px; color: #666;">
                Questo questionario valuta l'operato del fornitore selezionato.
            </p>
        </div>
        
        <div class="content">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <strong>⚠️ Attenzione:</strong> <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php wp_nonce_field('submit_questionnaire_' . $assignment['id'], 'boq_nonce'); ?>
                
                <?php foreach ($areas as $area): ?>
                    <div class="area">
                        <div class="area-header">
                            <div class="area-title"><?php echo esc_html($area['title']); ?></div>
                        </div>
                        
                        <?php
                        $questions = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM $table_questions WHERE area_id = %d ORDER BY sort_order",
                            $area['id']
                        ), ARRAY_A);
                        
                        foreach ($questions as $question):
                            $options = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM $table_options WHERE question_id = %d ORDER BY sort_order",
                                $question['id']
                            ), ARRAY_A);
                        ?>
                            <div class="question">
                                <div class="question-text">
                                    <?php echo esc_html($question['text']); ?>
                                    <?php if ($question['is_required']): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </div>
                                <div class="options">
                                    <?php foreach ($options as $option): ?>
                                        <div class="option">
                                            <input 
                                                type="radio" 
                                                name="question_<?php echo $question['id']; ?>" 
                                                value="<?php echo $option['id']; ?>" 
                                                id="option_<?php echo $option['id']; ?>"
                                                <?php echo $question['is_required'] ? 'required' : ''; ?>
                                            >
                                            <label for="option_<?php echo $option['id']; ?>">
                                                <?php echo esc_html($option['text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="submit-container">
                    <button type="submit" name="submit_questionnaire" class="submit-btn">
                        ✓ Invia Questionario
                    </button>
                </div>
            </form>
        </div>
        
        <div class="footer">
            Sistema di Gestione Questionari - Cogei © <?php echo date('Y'); ?>
        </div>
    </div>
</body>
</html>
<?php

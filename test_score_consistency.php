<?php
/**
 * Test Script: Verifica Consistenza Punteggi Questionari
 * 
 * Questo test verifica che i punteggi dei questionari completati NON cambino quando:
 * 1. I pesi delle domande/aree vengono modificati
 * 2. Il questionario viene eliminato (parzialmente - solo struttura)
 * 
 * UTILIZZO:
 * php test_score_consistency.php
 * 
 * REQUISITI:
 * - WordPress installato e configurato
 * - Database con tabelle cogei_* create
 * - Almeno un questionario di test completato
 */

// Carica WordPress
$wp_load_paths = [
    __DIR__ . '/wp-load.php',
    __DIR__ . '/../wp-load.php',
    __DIR__ . '/../../wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("ERRORE: Impossibile caricare WordPress. Verificare il percorso.\n");
}

global $wpdb;

echo "===========================================\n";
echo "TEST CONSISTENZA PUNTEGGI QUESTIONARI\n";
echo "===========================================\n\n";

// 1. Crea un questionario di test
echo "1. Creazione questionario di test...\n";

$questionnaire_data = [
    'title' => 'Test Consistency Questionnaire ' . time(),
    'description' => 'Questionario di test per verificare la consistenza dei punteggi',
    'status' => 'published',
    'created_by' => 1
];

$wpdb->insert($wpdb->prefix . 'cogei_questionnaires', $questionnaire_data);
$questionnaire_id = $wpdb->insert_id;

if (!$questionnaire_id) {
    die("ERRORE: Impossibile creare il questionario di test.\n");
}

echo "   ✓ Questionario creato: ID $questionnaire_id\n\n";

// 2. Crea un'area con peso specifico
echo "2. Creazione area di test...\n";

$area_data = [
    'questionnaire_id' => $questionnaire_id,
    'title' => 'Area Test',
    'weight' => 0.500,
    'sort_order' => 1
];

$wpdb->insert($wpdb->prefix . 'cogei_areas', $area_data);
$area_id = $wpdb->insert_id;

echo "   ✓ Area creata: ID $area_id (peso: 0.500)\n\n";

// 3. Crea una domanda
echo "3. Creazione domanda di test...\n";

$question_data = [
    'area_id' => $area_id,
    'text' => 'Domanda di test?',
    'is_required' => 1,
    'sort_order' => 1
];

$wpdb->insert($wpdb->prefix . 'cogei_questions', $question_data);
$question_id = $wpdb->insert_id;

echo "   ✓ Domanda creata: ID $question_id\n\n";

// 4. Crea opzioni di risposta con pesi diversi
echo "4. Creazione opzioni di risposta...\n";

$options = [
    ['text' => 'Ottimo', 'weight' => 1.000, 'sort_order' => 1],
    ['text' => 'Buono', 'weight' => 0.750, 'sort_order' => 2],
    ['text' => 'Sufficiente', 'weight' => 0.500, 'sort_order' => 3],
    ['text' => 'Insufficiente', 'weight' => 0.250, 'sort_order' => 4]
];

$option_ids = [];
foreach ($options as $option) {
    $option['question_id'] = $question_id;
    $wpdb->insert($wpdb->prefix . 'cogei_options', $option);
    $option_ids[] = $wpdb->insert_id;
}

echo "   ✓ Opzioni create: " . count($option_ids) . " opzioni\n\n";

// 5. Crea un assignment simulato con snapshot
echo "5. Creazione assignment simulato...\n";

// Prima crea lo snapshot usando la funzione del sistema
require_once(__DIR__ . '/bo-questionnaires.php');

$snapshot = boq_createQuestionnaireSnapshot($questionnaire_id);
$token = bin2hex(random_bytes(32));

$assignment_data = [
    'questionnaire_id' => $questionnaire_id,
    'target_user_id' => 1,
    'inspector_email' => 'test@example.com',
    'sent_by' => 1,
    'token' => $token,
    'status' => 'completed',
    'questionnaire_snapshot' => json_encode($snapshot)
];

$wpdb->insert($wpdb->prefix . 'cogei_assignments', $assignment_data);
$assignment_id = $wpdb->insert_id;

echo "   ✓ Assignment creato: ID $assignment_id\n";
echo "   ✓ Snapshot salvato: " . strlen($assignment_data['questionnaire_snapshot']) . " bytes\n\n";

// 6. Simula una risposta (seleziona opzione "Buono" con peso 0.750)
echo "6. Simulazione risposta del questionario...\n";

$selected_option_id = $option_ids[1]; // "Buono" = 0.750
$selected_option = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}cogei_options WHERE id = %d",
    $selected_option_id
), ARRAY_A);

$computed_score = floatval($selected_option['weight']); // 0.750

$response_data = [
    'assignment_id' => $assignment_id,
    'question_id' => $question_id,
    'selected_option_id' => $selected_option_id,
    'computed_score' => $computed_score,
    'answered_at' => current_time('mysql')
];

$wpdb->insert($wpdb->prefix . 'cogei_responses', $response_data);

echo "   ✓ Risposta registrata: opzione '{$selected_option['text']}' (peso {$selected_option['weight']})\n";
echo "   ✓ Computed score memorizzato: $computed_score\n\n";

// 7. Calcola il punteggio iniziale
echo "7. Calcolo punteggio iniziale...\n";

$initial_score = boq_calculateScore($assignment_id);

echo "   ✓ Punteggio iniziale: $initial_score / 100\n";
echo "   Formula: (0.750 × 0.500) × 100 = " . (0.750 * 0.500 * 100) . "\n\n";

// 8. TEST 1: Modifica i pesi delle opzioni
echo "8. TEST 1: Modifica peso opzione selezionata...\n";

$new_weight = 0.100; // Cambio drastico da 0.750 a 0.100
$wpdb->update(
    $wpdb->prefix . 'cogei_options',
    ['weight' => $new_weight],
    ['id' => $selected_option_id]
);

echo "   ✓ Peso opzione modificato: 0.750 → $new_weight\n\n";

// 9. Ricalcola il punteggio dopo la modifica
echo "9. Verifica punteggio dopo modifica peso...\n";

$score_after_weight_change = boq_calculateScore($assignment_id);

echo "   ✓ Punteggio dopo modifica: $score_after_weight_change / 100\n";

if (abs($initial_score - $score_after_weight_change) < 0.0001) {
    echo "   ✅ SUCCESSO: Il punteggio è rimasto invariato!\n\n";
} else {
    echo "   ❌ ERRORE: Il punteggio è cambiato da $initial_score a $score_after_weight_change\n\n";
}

// 10. TEST 2: Modifica il peso dell'area
echo "10. TEST 2: Modifica peso area...\n";

$new_area_weight = 0.200; // Cambio da 0.500 a 0.200
$wpdb->update(
    $wpdb->prefix . 'cogei_areas',
    ['weight' => $new_area_weight],
    ['id' => $area_id]
);

echo "    ✓ Peso area modificato: 0.500 → $new_area_weight\n\n";

// 11. Verifica punteggio dopo modifica area
echo "11. Verifica punteggio dopo modifica area...\n";

$score_after_area_change = boq_calculateScore($assignment_id);

echo "    ✓ Punteggio dopo modifica area: $score_after_area_change / 100\n";

if (abs($initial_score - $score_after_area_change) < 0.0001) {
    echo "    ✅ SUCCESSO: Il punteggio è rimasto invariato anche dopo modifica area!\n\n";
} else {
    echo "    ❌ ERRORE: Il punteggio è cambiato da $initial_score a $score_after_area_change\n\n";
}

// 12. TEST 3: Elimina la struttura del questionario (mantiene risposte)
echo "12. TEST 3: Eliminazione parziale struttura questionario...\n";

// Elimina opzioni
$wpdb->delete($wpdb->prefix . 'cogei_options', ['question_id' => $question_id]);
    echo "    ✓ Opzioni eliminate\n";

// Elimina domanda
$wpdb->delete($wpdb->prefix . 'cogei_questions', ['id' => $question_id]);
echo "    ✓ Domanda eliminata\n";

// Elimina area
$wpdb->delete($wpdb->prefix . 'cogei_areas', ['id' => $area_id]);
echo "    ✓ Area eliminata\n\n";

// 13. Verifica punteggio dopo eliminazione
echo "13. Verifica punteggio dopo eliminazione struttura...\n";

$score_after_deletion = boq_calculateScore($assignment_id);

echo "    ✓ Punteggio dopo eliminazione: $score_after_deletion / 100\n";

if (abs($initial_score - $score_after_deletion) < 0.0001) {
    echo "    ✅ SUCCESSO: Il punteggio è rimasto invariato anche dopo eliminazione!\n\n";
} else {
    echo "    ❌ ERRORE: Il punteggio è cambiato da $initial_score a $score_after_deletion\n\n";
}

// 14. Pulizia: rimuovi dati di test
echo "14. Pulizia dati di test...\n";

$wpdb->delete($wpdb->prefix . 'cogei_responses', ['assignment_id' => $assignment_id]);
$wpdb->delete($wpdb->prefix . 'cogei_assignments', ['id' => $assignment_id]);
$wpdb->delete($wpdb->prefix . 'cogei_questionnaires', ['id' => $questionnaire_id]);

echo "    ✓ Dati di test rimossi\n\n";

// 15. Riepilogo risultati
echo "===========================================\n";
echo "RIEPILOGO TEST\n";
echo "===========================================\n\n";

$all_passed = true;

echo "Punteggio iniziale:           $initial_score\n";
echo "Dopo modifica peso opzione:   $score_after_weight_change ";
if (abs($initial_score - $score_after_weight_change) < 0.0001) {
    echo "✅\n";
} else {
    echo "❌\n";
    $all_passed = false;
}

echo "Dopo modifica peso area:      $score_after_area_change ";
if (abs($initial_score - $score_after_area_change) < 0.0001) {
    echo "✅\n";
} else {
    echo "❌\n";
    $all_passed = false;
}

echo "Dopo eliminazione struttura:  $score_after_deletion ";
if (abs($initial_score - $score_after_deletion) < 0.0001) {
    echo "✅\n";
} else {
    echo "❌\n";
    $all_passed = false;
}

echo "\n";

if ($all_passed) {
    echo "🎉 TUTTI I TEST SONO PASSATI! 🎉\n";
    echo "I punteggi storici sono protetti e non cambiano.\n";
} else {
    echo "⚠️  ALCUNI TEST SONO FALLITI ⚠️\n";
    echo "Verificare l'implementazione della consistenza dei punteggi.\n";
}

echo "\n===========================================\n";

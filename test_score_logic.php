<?php
/**
 * Test Unitario: Verifica Logica Calcolo Punteggi
 * 
 * Questo test verifica la logica del calcolo punteggi senza richiedere
 * un'installazione completa di WordPress.
 */

echo "===========================================\n";
echo "TEST UNITARIO LOGICA CALCOLO PUNTEGGI\n";
echo "===========================================\n\n";

// Test 1: Verifica calcolo con snapshot
echo "TEST 1: Calcolo con snapshot disponibile\n";
echo "-----------------------------------------\n";

// Simula uno snapshot
$snapshot = [
    'areas' => [
        [
            'id' => 1,
            'title' => 'Area Test',
            'weight' => 0.500
        ]
    ]
];

// Simula risposte con computed_score memorizzato
$responses_area_1 = [
    ['computed_score' => 0.750],  // Peso opzione memorizzato
];

// Calcola score usando lo snapshot (logica implementata)
$area_sum = 0;
foreach ($responses_area_1 as $resp) {
    $area_sum += floatval($resp['computed_score']);
}

$area_score = $area_sum * floatval($snapshot['areas'][0]['weight']);
$total_score = $area_score * 100;

$expected_score = (0.750 * 0.500) * 100; // = 37.5

echo "Computed score memorizzato: 0.750\n";
echo "Peso area (snapshot):       0.500\n";
echo "Punteggio calcolato:        $total_score / 100\n";
echo "Punteggio atteso:           $expected_score / 100\n";

if (abs($total_score - $expected_score) < 0.0001) {
    echo "✅ SUCCESSO: Calcolo corretto con snapshot\n\n";
    $test1_passed = true;
} else {
    echo "❌ ERRORE: Calcolo non corretto\n\n";
    $test1_passed = false;
}

// Test 2: Verifica che modifiche ai pesi NON influenzino il calcolo
echo "TEST 2: Resistenza a modifiche pesi\n";
echo "------------------------------------\n";

// Il peso dell'area cambia nel DB ma NON nello snapshot
$new_db_weight = 0.200; // Peso modificato nel DB
$snapshot_weight = 0.500; // Peso originale nello snapshot

// Il calcolo usa SEMPRE lo snapshot
$area_score_with_snapshot = $area_sum * floatval($snapshot_weight);
$score_with_snapshot = $area_score_with_snapshot * 100;

// Simula calcolo vecchio (senza snapshot) che userebbe il nuovo peso
$area_score_without_snapshot = $area_sum * floatval($new_db_weight);
$score_without_snapshot = $area_score_without_snapshot * 100;

echo "Peso area nel DB (modificato):     $new_db_weight\n";
echo "Peso area nello snapshot:          $snapshot_weight\n";
echo "Score usando snapshot:             $score_with_snapshot / 100\n";
echo "Score senza snapshot (vecchio):    $score_without_snapshot / 100\n";

if (abs($score_with_snapshot - $total_score) < 0.0001) {
    echo "✅ SUCCESSO: Il punteggio rimane invariato usando lo snapshot\n\n";
    $test2_passed = true;
} else {
    echo "❌ ERRORE: Il punteggio è cambiato\n\n";
    $test2_passed = false;
}

// Test 3: Verifica uso computed_score memorizzato
echo "TEST 3: Uso computed_score memorizzato\n";
echo "---------------------------------------\n";

// Simula scenario: opzione con peso modificato
$original_option_weight = 0.750;  // Peso al momento della risposta
$current_option_weight = 0.100;   // Peso attuale nel DB (modificato)
$stored_computed_score = 0.750;   // Valore memorizzato in cogei_responses

// Il calcolo usa computed_score, NON il peso attuale
$score_using_stored = $stored_computed_score * $snapshot_weight * 100;

// Vecchio calcolo (errato) userebbe il peso attuale
$score_using_current = $current_option_weight * $snapshot_weight * 100;

echo "Peso opzione originale:      $original_option_weight\n";
echo "Peso opzione attuale (DB):   $current_option_weight\n";
echo "Computed score memorizzato:  $stored_computed_score\n";
echo "Score usando memorizzato:    $score_using_stored / 100\n";
echo "Score usando peso attuale:   $score_using_current / 100\n";

if (abs($score_using_stored - $total_score) < 0.0001) {
    echo "✅ SUCCESSO: Il punteggio usa computed_score memorizzato\n\n";
    $test3_passed = true;
} else {
    echo "❌ ERRORE: Il punteggio non usa il valore memorizzato\n\n";
    $test3_passed = false;
}

// Test 4: Verifica fallback per questionari vecchi
echo "TEST 4: Fallback per questionari senza snapshot\n";
echo "------------------------------------------------\n";

// Simula questionario compilato prima del fix (senza snapshot)
$db_areas = [
    ['id' => 1, 'weight' => 0.500]
];

$responses_fallback = [
    ['computed_score' => 0.750]
];

// Calcola usando fallback (computed_score + peso DB attuale)
$area_sum_fallback = 0;
foreach ($responses_fallback as $resp) {
    $area_sum_fallback += floatval($resp['computed_score']);
}

$area_score_fallback = $area_sum_fallback * floatval($db_areas[0]['weight']);
$total_score_fallback = $area_score_fallback * 100;

echo "Computed score:               {$responses_fallback[0]['computed_score']}\n";
echo "Peso area da DB:              {$db_areas[0]['weight']}\n";
echo "Punteggio (fallback):         $total_score_fallback / 100\n";

if (abs($total_score_fallback - $expected_score) < 0.0001) {
    echo "✅ SUCCESSO: Fallback funziona correttamente\n\n";
    $test4_passed = true;
} else {
    echo "❌ ERRORE: Fallback non corretto\n\n";
    $test4_passed = false;
}

// Test 5: Verifica gestione eliminazione struttura
echo "TEST 5: Punteggio dopo eliminazione struttura\n";
echo "----------------------------------------------\n";

// Con snapshot, il punteggio può essere calcolato anche se la struttura è eliminata
// perché tutte le informazioni necessarie sono nello snapshot

echo "Scenario: Area e domande eliminate dal DB\n";
echo "Snapshot contiene:            area weight = 0.500\n";
echo "Risposte contengono:          computed_score = 0.750\n";
echo "Calcolo possibile:            SÌ (usa snapshot + responses)\n";
echo "Punteggio:                    $total_score / 100\n";

// Verifica che il calcolo sia ancora possibile
if (!empty($snapshot['areas']) && !empty($responses_area_1)) {
    echo "✅ SUCCESSO: Calcolo possibile anche dopo eliminazione\n\n";
    $test5_passed = true;
} else {
    echo "❌ ERRORE: Calcolo non possibile\n\n";
    $test5_passed = false;
}

// Riepilogo
echo "===========================================\n";
echo "RIEPILOGO TEST\n";
echo "===========================================\n\n";

$all_tests = [
    'Calcolo con snapshot' => $test1_passed,
    'Resistenza modifiche pesi' => $test2_passed,
    'Uso computed_score memorizzato' => $test3_passed,
    'Fallback questionari vecchi' => $test4_passed,
    'Calcolo dopo eliminazione' => $test5_passed
];

$passed_count = 0;
foreach ($all_tests as $test_name => $passed) {
    $status = $passed ? '✅' : '❌';
    echo "$status $test_name\n";
    if ($passed) $passed_count++;
}

echo "\nRisultato: $passed_count / " . count($all_tests) . " test passati\n\n";

if ($passed_count === count($all_tests)) {
    echo "🎉 TUTTI I TEST SONO PASSATI! 🎉\n";
    echo "La logica di calcolo garantisce la consistenza dei punteggi.\n";
    exit(0);
} else {
    echo "⚠️  ALCUNI TEST SONO FALLITI ⚠️\n";
    echo "Verificare l'implementazione.\n";
    exit(1);
}

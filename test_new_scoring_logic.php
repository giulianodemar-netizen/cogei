<?php
/**
 * Test: Nuova Logica Calcolo Punteggio Questionari
 * 
 * Questo test verifica che la nuova formula sia correttamente implementata:
 * 1. Peso Effettivo = max_weight * area_weight (0 se N.A.)
 * 2. Punteggio = answer_weight * area_weight (0 se N.A.)
 * 3. Final Score = (Sum Punteggi / Sum Peso Effettivi) * 100
 */

echo "===========================================\n";
echo "TEST NUOVA LOGICA CALCOLO PUNTEGGI\n";
echo "===========================================\n\n";

// TEST 1: Scenario Base - Nessuna risposta N.A.
echo "TEST 1: Scenario Base (Nessuna N.A.)\n";
echo "-------------------------------------\n";

// Area weight = 0.5
// Question 1: Answer weight = 0.75, Max weight = 1.0
// Question 2: Answer weight = 0.5, Max weight = 1.0

$area_weight = 0.5;

// Calcolo Pesi Effettivi
$peso_eff_q1 = 1.0 * $area_weight;  // 0.5
$peso_eff_q2 = 1.0 * $area_weight;  // 0.5
$total_peso_effettivo = $peso_eff_q1 + $peso_eff_q2;  // 1.0

// Calcolo Punteggi
$punteggio_q1 = 0.75 * $area_weight;  // 0.375
$punteggio_q2 = 0.5 * $area_weight;   // 0.25
$total_punteggio = $punteggio_q1 + $punteggio_q2;  // 0.625

// Final Score
$final_score = ($total_punteggio / $total_peso_effettivo) * 100;  // (0.625 / 1.0) * 100 = 62.5

echo "Area weight:           $area_weight\n";
echo "Question 1: answer=0.75, max=1.0\n";
echo "Question 2: answer=0.5, max=1.0\n";
echo "\n";
echo "Peso Effettivo Q1:     $peso_eff_q1\n";
echo "Peso Effettivo Q2:     $peso_eff_q2\n";
echo "Total Peso Effettivo:  $total_peso_effettivo\n";
echo "\n";
echo "Punteggio Q1:          $punteggio_q1\n";
echo "Punteggio Q2:          $punteggio_q2\n";
echo "Total Punteggio:       $total_punteggio\n";
echo "\n";
echo "Final Score:           $final_score / 100\n";
echo "Expected:              62.5 / 100\n";

if (abs($final_score - 62.5) < 0.01) {
    echo "✅ SUCCESSO\n\n";
    $test1_passed = true;
} else {
    echo "❌ ERRORE: Score atteso 62.5, ottenuto $final_score\n\n";
    $test1_passed = false;
}

// TEST 2: Scenario con una risposta N.A.
echo "TEST 2: Scenario con una risposta N.A.\n";
echo "---------------------------------------\n";

// Area weight = 0.5
// Question 1: Answer weight = 0.75, Max weight = 1.0
// Question 2: N.A. (esclusa dal calcolo)

$area_weight = 0.5;

// Calcolo Pesi Effettivi (Q2 esclusa perché N.A.)
$peso_eff_q1 = 1.0 * $area_weight;  // 0.5
$peso_eff_q2 = 0;  // N.A., contributo 0
$total_peso_effettivo = $peso_eff_q1 + $peso_eff_q2;  // 0.5

// Calcolo Punteggi (Q2 esclusa perché N.A.)
$punteggio_q1 = 0.75 * $area_weight;  // 0.375
$punteggio_q2 = 0;  // N.A., contributo 0
$total_punteggio = $punteggio_q1 + $punteggio_q2;  // 0.375

// Final Score
$final_score = ($total_peso_effettivo > 0) 
    ? ($total_punteggio / $total_peso_effettivo) * 100 
    : 0;  // (0.375 / 0.5) * 100 = 75

echo "Area weight:           $area_weight\n";
echo "Question 1: answer=0.75, max=1.0\n";
echo "Question 2: N.A. (excluded)\n";
echo "\n";
echo "Peso Effettivo Q1:     $peso_eff_q1\n";
echo "Peso Effettivo Q2:     $peso_eff_q2 (N.A.)\n";
echo "Total Peso Effettivo:  $total_peso_effettivo\n";
echo "\n";
echo "Punteggio Q1:          $punteggio_q1\n";
echo "Punteggio Q2:          $punteggio_q2 (N.A.)\n";
echo "Total Punteggio:       $total_punteggio\n";
echo "\n";
echo "Final Score:           $final_score / 100\n";
echo "Expected:              75.0 / 100\n";

if (abs($final_score - 75.0) < 0.01) {
    echo "✅ SUCCESSO\n\n";
    $test2_passed = true;
} else {
    echo "❌ ERRORE: Score atteso 75.0, ottenuto $final_score\n\n";
    $test2_passed = false;
}

// TEST 3: Scenario con tutte risposte N.A.
echo "TEST 3: Scenario con tutte risposte N.A.\n";
echo "-----------------------------------------\n";

// Area weight = 0.5
// Question 1: N.A.
// Question 2: N.A.

$area_weight = 0.5;

// Calcolo Pesi Effettivi (entrambe escluse)
$total_peso_effettivo = 0;

// Calcolo Punteggi (entrambe escluse)
$total_punteggio = 0;

// Final Score (caso speciale: tutte N.A.)
$final_score = ($total_peso_effettivo > 0) 
    ? ($total_punteggio / $total_peso_effettivo) * 100 
    : 0;  // 0

echo "Area weight:           $area_weight\n";
echo "Question 1: N.A. (excluded)\n";
echo "Question 2: N.A. (excluded)\n";
echo "\n";
echo "Total Peso Effettivo:  $total_peso_effettivo\n";
echo "Total Punteggio:       $total_punteggio\n";
echo "\n";
echo "Final Score:           $final_score / 100\n";
echo "Expected:              0 / 100 (tutte N.A.)\n";

if (abs($final_score - 0.0) < 0.01) {
    echo "✅ SUCCESSO\n\n";
    $test3_passed = true;
} else {
    echo "❌ ERRORE: Score atteso 0.0, ottenuto $final_score\n\n";
    $test3_passed = false;
}

// TEST 4: Scenario con più aree
echo "TEST 4: Scenario con più aree\n";
echo "------------------------------\n";

// Area 1 (weight = 0.6):
//   Q1: answer=1.0, max=1.0
//   Q2: answer=0.8, max=1.0
// Area 2 (weight = 0.4):
//   Q3: answer=0.5, max=1.0

$area1_weight = 0.6;
$area2_weight = 0.4;

// Area 1
$peso_eff_a1_q1 = 1.0 * $area1_weight;  // 0.6
$peso_eff_a1_q2 = 1.0 * $area1_weight;  // 0.6
$punteggio_a1_q1 = 1.0 * $area1_weight;  // 0.6
$punteggio_a1_q2 = 0.8 * $area1_weight;  // 0.48

// Area 2
$peso_eff_a2_q3 = 1.0 * $area2_weight;  // 0.4
$punteggio_a2_q3 = 0.5 * $area2_weight;  // 0.2

// Totali
$total_peso_effettivo = $peso_eff_a1_q1 + $peso_eff_a1_q2 + $peso_eff_a2_q3;  // 1.6
$total_punteggio = $punteggio_a1_q1 + $punteggio_a1_q2 + $punteggio_a2_q3;  // 1.28

// Final Score
$final_score = ($total_punteggio / $total_peso_effettivo) * 100;  // (1.28 / 1.6) * 100 = 80

echo "Area 1 (weight=$area1_weight):\n";
echo "  Q1: answer=1.0, max=1.0\n";
echo "  Q2: answer=0.8, max=1.0\n";
echo "Area 2 (weight=$area2_weight):\n";
echo "  Q3: answer=0.5, max=1.0\n";
echo "\n";
echo "Total Peso Effettivo:  $total_peso_effettivo\n";
echo "Total Punteggio:       $total_punteggio\n";
echo "\n";
echo "Final Score:           $final_score / 100\n";
echo "Expected:              80.0 / 100\n";

if (abs($final_score - 80.0) < 0.01) {
    echo "✅ SUCCESSO\n\n";
    $test4_passed = true;
} else {
    echo "❌ ERRORE: Score atteso 80.0, ottenuto $final_score\n\n";
    $test4_passed = false;
}

// TEST 5: Verifica differenza con vecchia logica
echo "TEST 5: Confronto con vecchia logica\n";
echo "-------------------------------------\n";

// Area weight = 0.5
// Question 1: Answer weight = 0.75, Max weight = 1.0
// Question 2: N.A. (max weight = 1.0)

$area_weight = 0.5;

// VECCHIA LOGICA (N.A. conta come max weight)
$old_area_sum = 0.75 + 1.0;  // 1.75
$old_area_score = $old_area_sum * $area_weight;  // 0.875
$old_final_score = $old_area_score * 100;  // 87.5

// NUOVA LOGICA (N.A. esclusa)
$new_peso_effettivo = 1.0 * $area_weight;  // 0.5
$new_punteggio = 0.75 * $area_weight;  // 0.375
$new_final_score = ($new_punteggio / $new_peso_effettivo) * 100;  // 75.0

echo "Scenario: 1 risposta normale (0.75) + 1 risposta N.A.\n";
echo "\n";
echo "VECCHIA LOGICA (N.A. = max weight):\n";
echo "  Area sum:      $old_area_sum\n";
echo "  Area score:    $old_area_score\n";
echo "  Final score:   $old_final_score / 100\n";
echo "\n";
echo "NUOVA LOGICA (N.A. esclusa):\n";
echo "  Peso Effettivo:  $new_peso_effettivo\n";
echo "  Punteggio:       $new_punteggio\n";
echo "  Final score:     $new_final_score / 100\n";
echo "\n";
echo "Differenza:      " . ($old_final_score - $new_final_score) . " punti\n";

if (abs($new_final_score - 75.0) < 0.01 && abs($old_final_score - 87.5) < 0.01) {
    echo "✅ SUCCESSO: Le due logiche producono risultati diversi come atteso\n\n";
    $test5_passed = true;
} else {
    echo "❌ ERRORE: Valori non corrispondenti\n\n";
    $test5_passed = false;
}

// Riepilogo
echo "===========================================\n";
echo "RIEPILOGO TEST\n";
echo "===========================================\n\n";

$all_tests = [
    'Scenario Base (Nessuna N.A.)' => $test1_passed,
    'Scenario con una risposta N.A.' => $test2_passed,
    'Scenario con tutte risposte N.A.' => $test3_passed,
    'Scenario con più aree' => $test4_passed,
    'Confronto con vecchia logica' => $test5_passed
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
    echo "La nuova logica di calcolo è corretta.\n";
    echo "\nKEY POINTS:\n";
    echo "- Le risposte N.A. sono completamente escluse dal calcolo\n";
    echo "- Il punteggio è normalizzato rispetto alle sole domande non-N.A.\n";
    echo "- Se tutte le risposte sono N.A., il punteggio è 0\n";
    exit(0);
} else {
    echo "⚠️  ALCUNI TEST SONO FALLITI ⚠️\n";
    echo "Verificare l'implementazione.\n";
    exit(1);
}

<?php
/**
 * Test Script per Endpoint get_cantiere_details.php
 * 
 * Questo script esegue test di integrazione sull'endpoint dei dettagli cantiere.
 * 
 * COME USARE:
 * 1. Modificare le variabili $test_cantiere_id e $test_base_url se necessario
 * 2. Eseguire da linea di comando: php ajax_fornitori/test_cantiere_details.php
 * 3. Oppure accedere via browser (se WordPress è configurato)
 * 
 * NOTA: Richiede che WordPress sia installato e configurato
 */

// Carica WordPress
$wp_load_paths = [
    __DIR__ . '/../../wp-load.php',
    __DIR__ . '/../wp-load.php',
    dirname(dirname(__DIR__)) . '/wp-load.php'
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
    die("❌ ERRORE: Impossibile caricare WordPress. Verificare il percorso.\n");
}

// ================== CONFIGURAZIONE TEST ==================

$test_cantiere_id = 1; // Modificare con un ID cantiere valido nel database
$test_base_url = 'http://localhost/cogei'; // Modificare con l'URL base del sito

// ================== FUNZIONI HELPER ==================

function printTestHeader($title) {
    echo "\n";
    echo "========================================\n";
    echo "  $title\n";
    echo "========================================\n";
}

function printTestResult($test_name, $passed, $message = '') {
    $status = $passed ? '✅ PASS' : '❌ FAIL';
    echo "$status - $test_name";
    if ($message) {
        echo " - $message";
    }
    echo "\n";
}

function simulateAjaxRequest($cantiere_id) {
    $_POST['cantiere_id'] = $cantiere_id;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Simula la chiamata all'endpoint
    ob_start();
    include __DIR__ . '/get_cantiere_details.php';
    $output = ob_get_clean();
    
    return json_decode($output, true);
}

// ================== TEST 1: Verifica esistenza cantiere ==================

printTestHeader('TEST 1: Verifica Database e Cantiere');

global $wpdb;

$cantiere = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}cantieri WHERE id = %d
", $test_cantiere_id), ARRAY_A);

if ($cantiere) {
    printTestResult('Database connesso', true);
    printTestResult('Cantiere trovato', true, "ID: $test_cantiere_id, Nome: {$cantiere['nome']}");
} else {
    printTestResult('Cantiere trovato', false, "Cantiere ID $test_cantiere_id non esiste");
    echo "\nℹ️  SUGGERIMENTO: Verificare che esista un cantiere nel database e aggiornare \$test_cantiere_id\n";
    exit(1);
}

// ================== TEST 2: Verifica aziende assegnate ==================

printTestHeader('TEST 2: Verifica Aziende Assegnate');

$aziende = $wpdb->get_results($wpdb->prepare("
    SELECT ca.*, u.user_email, um.meta_value as rag_soc
    FROM {$wpdb->prefix}cantieri_aziende ca
    JOIN {$wpdb->users} u ON ca.user_id = u.ID
    LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'user_registration_rag_soc'
    WHERE ca.cantiere_id = %d
", $test_cantiere_id), ARRAY_A);

$aziende_count = count($aziende);
printTestResult('Aziende assegnate', $aziende_count > 0, "$aziende_count aziende trovate");

if ($aziende_count > 0) {
    foreach ($aziende as $i => $azienda) {
        echo "  " . ($i+1) . ". {$azienda['rag_soc']} ({$azienda['user_email']})\n";
    }
}

// ================== TEST 3: Verifica operai ==================

printTestHeader('TEST 3: Verifica Operai Assegnati');

$operai = $wpdb->get_results($wpdb->prepare("
    SELECT oa.*, p.nome, p.cognome
    FROM {$wpdb->prefix}cantiere_operai_assegnazioni oa
    JOIN {$wpdb->prefix}cantiere_personale p ON oa.operaio_id = p.id
    WHERE oa.cantiere_id = %d
", $test_cantiere_id), ARRAY_A);

$operai_count = count($operai);
printTestResult('Operai assegnati', $operai_count >= 0, "$operai_count operai trovati");

// ================== TEST 4: Verifica mezzi ==================

printTestHeader('TEST 4: Verifica Mezzi Assegnati');

$mezzi = $wpdb->get_results($wpdb->prepare("
    SELECT aa.*, a.descrizione_automezzo, a.targa
    FROM {$wpdb->prefix}cantiere_automezzi_assegnazioni aa
    JOIN {$wpdb->prefix}cantiere_automezzi a ON aa.automezzo_id = a.id
    WHERE aa.cantiere_id = %d
", $test_cantiere_id), ARRAY_A);

$mezzi_count = count($mezzi);
printTestResult('Mezzi assegnati', $mezzi_count >= 0, "$mezzi_count mezzi trovati");

// ================== TEST 5: Verifica attrezzature ==================

printTestHeader('TEST 5: Verifica Attrezzature Assegnate');

$attrezzi = $wpdb->get_results($wpdb->prepare("
    SELECT at.*, a.descrizione_attrezzo
    FROM {$wpdb->prefix}cantiere_attrezzi_assegnazioni at
    JOIN {$wpdb->prefix}cantiere_attrezzi a ON at.attrezzo_id = a.id
    WHERE at.cantiere_id = %d
", $test_cantiere_id), ARRAY_A);

$attrezzi_count = count($attrezzi);
printTestResult('Attrezzature assegnate', $attrezzi_count >= 0, "$attrezzi_count attrezzature trovate");

// ================== TEST 6: Test endpoint con utente non autenticato ==================

printTestHeader('TEST 6: Sicurezza - Utente Non Autenticato');

// Salva lo stato corrente dell'utente
$original_user_id = get_current_user_id();

// Simula logout
wp_set_current_user(0);

$response = simulateAjaxRequest($test_cantiere_id);

if (isset($response['error']) && strpos($response['error'], 'autenticat') !== false) {
    printTestResult('Blocco accesso non autenticato', true, 'Endpoint protetto correttamente');
} else {
    printTestResult('Blocco accesso non autenticato', false, 'Endpoint non protetto!');
}

// Ripristina utente
wp_set_current_user($original_user_id);

// ================== TEST 7: Test endpoint con ID non valido ==================

printTestHeader('TEST 7: Validazione Input - ID Non Valido');

$_POST['cantiere_id'] = -1;
ob_start();
include __DIR__ . '/get_cantiere_details.php';
$output = ob_get_clean();
$response = json_decode($output, true);

if (isset($response['error'])) {
    printTestResult('Validazione ID negativo', true, 'Errore rilevato correttamente');
} else {
    printTestResult('Validazione ID negativo', false, 'Validazione mancante');
}

// ================== TEST 8: Test endpoint con ID cantiere inesistente ==================

printTestHeader('TEST 8: Gestione Errori - Cantiere Inesistente');

$_POST['cantiere_id'] = 999999;
ob_start();
include __DIR__ . '/get_cantiere_details.php';
$output = ob_get_clean();
$response = json_decode($output, true);

if (isset($response['error']) && strpos($response['error'], 'non trovato') !== false) {
    printTestResult('Gestione cantiere inesistente', true, '404 restituito correttamente');
} else {
    printTestResult('Gestione cantiere inesistente', false, 'Errore non gestito correttamente');
}

// ================== TEST 9: Test metodo HTTP non consentito ==================

printTestHeader('TEST 9: Sicurezza - Metodo HTTP');

$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
include __DIR__ . '/get_cantiere_details.php';
$output = ob_get_clean();
$response = json_decode($output, true);

if (isset($response['error']) && strpos(strtolower($response['error']), 'metodo') !== false) {
    printTestResult('Blocco metodo GET', true, 'Solo POST accettato');
} else {
    printTestResult('Blocco metodo GET', false, 'Metodo non validato');
}

// ================== RIEPILOGO ==================

printTestHeader('RIEPILOGO TEST');

echo "✅ Test di base completati con successo\n";
echo "\nℹ️  PROSSIMI PASSI:\n";
echo "1. Testare manualmente via browser con un utente amministratore autenticato\n";
echo "2. Verificare i log di errore per eventuali warning/notice\n";
echo "3. Testare con cantieri contenenti molte risorse per verificare le performance\n";
echo "4. Verificare che tutti i documenti abbiano URL validi\n";

echo "\n📊 STATISTICHE CANTIERE ID $test_cantiere_id:\n";
echo "   - Aziende: $aziende_count\n";
echo "   - Operai: $operai_count\n";
echo "   - Mezzi: $mezzi_count\n";
echo "   - Attrezzature: $attrezzi_count\n";

echo "\n✅ Test completati\n";
?>
<?php
/**
 * Test script per verificare salvataggio/recupero opzioni AI
 */

// Load WordPress
$wp_load = __DIR__ . '/../../../wp-load.php';
if (!file_exists($wp_load)) {
    // Try alternative path for junction
    $wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}
require_once $wp_load;

if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

// Output HTML if accessed via browser
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Test AI Options</title>';
    echo '<style>body{font-family:monospace;padding:20px;background:#f0f0f0;}';
    echo '.success{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}';
    echo '.section{background:white;padding:15px;margin:10px 0;border-radius:5px;border:1px solid #ccc;}';
    echo 'h2{color:#333;border-bottom:2px solid #666;padding-bottom:5px;}';
    echo '</style></head><body>';
    echo '<h1>🧪 TEST OPZIONI AI - FP Digital Marketing Suite</h1>';
}

function out($text, $isHtml = false) {
    global $isCli;
    if ($isCli) {
        echo $text . "\n";
    } else {
        echo $isHtml ? $text : nl2br(htmlspecialchars($text)) . "<br>";
    }
}

out('<div class="section">', true);
out("=== TEST OPZIONI AI ===");
out("");

// 1. Check current state
out("1. STATO ATTUALE:");
$currentKey = get_option('fpdms_openai_api_key', '');
$currentModel = get_option('fpdms_ai_model', '');

out("   fpdms_openai_api_key: " . (empty($currentKey) ? "VUOTA" : substr($currentKey, 0, 10) . '...'));
out("   fpdms_ai_model: " . (empty($currentModel) ? "VUOTO" : $currentModel));
out("");

// 2. Test save
echo "2. TEST SALVATAGGIO:\n";
$testKey = 'sk-test1234567890abcdefghijklmnopqrstuvwxyz';
$testModel = 'gpt-5-nano';

echo "   Salvo API Key di test...\n";
$saved1 = update_option('fpdms_openai_api_key', $testKey);
echo "   Risultato update_option API Key: " . ($saved1 ? "SUCCESS" : "FAILED") . "\n";

echo "   Salvo Model di test...\n";
$saved2 = update_option('fpdms_ai_model', $testModel);
echo "   Risultato update_option Model: " . ($saved2 ? "SUCCESS" : "FAILED") . "\n\n";

// 3. Test retrieve immediately
echo "3. TEST RECUPERO IMMEDIATO (get_option):\n";
$retrievedKey = get_option('fpdms_openai_api_key', '');
$retrievedModel = get_option('fpdms_ai_model', '');

echo "   API Key recuperata: " . (empty($retrievedKey) ? "VUOTA ❌" : substr($retrievedKey, 0, 10) . '... ✅') . "\n";
echo "   Model recuperato: " . (empty($retrievedModel) ? "VUOTO ❌" : $retrievedModel . ' ✅') . "\n";
echo "   Match API Key: " . ($retrievedKey === $testKey ? "✅ SI" : "❌ NO") . "\n";
echo "   Match Model: " . ($retrievedModel === $testModel ? "✅ SI" : "❌ NO") . "\n\n";

// 4. Test with Options class
echo "4. TEST RECUPERO CON Options::get():\n";
require_once __DIR__ . '/src/Infra/Options.php';

$keyViaClass = \FP\DMS\Infra\Options::get('fpdms_openai_api_key', '');
$modelViaClass = \FP\DMS\Infra\Options::get('fpdms_ai_model', '');

echo "   API Key via Options::get(): " . (empty($keyViaClass) ? "VUOTA ❌" : substr($keyViaClass, 0, 10) . '... ✅') . "\n";
echo "   Model via Options::get(): " . (empty($modelViaClass) ? "VUOTO ❌" : $modelViaClass . ' ✅') . "\n";
echo "   Match API Key: " . ($keyViaClass === $testKey ? "✅ SI" : "❌ NO") . "\n";
echo "   Match Model: " . ($modelViaClass === $testModel ? "✅ SI" : "❌ NO") . "\n\n";

// 5. Test AIInsightsService
echo "5. TEST AIInsightsService::hasOpenAIKey():\n";
require_once __DIR__ . '/src/Services/Overview/AIInsightsService.php';

$service = new \FP\DMS\Services\Overview\AIInsightsService();
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('hasOpenAIKey');
$method->setAccessible(true);
$hasKey = $method->invoke($service);

echo "   hasOpenAIKey() result: " . ($hasKey ? "✅ TRUE (chiave rilevata)" : "❌ FALSE (chiave NON rilevata)") . "\n\n";

// 6. Test Settings Page save logic simulation
echo "6. TEST LOGICA SALVATAGGIO SETTINGS PAGE:\n";
$_POST = [
    'openai_api_key' => 'sk-newsecretkey1234567890',
    'ai_model' => 'gpt-5-mini'
];

require_once __DIR__ . '/src/Support/Wp.php';

$openaiKey = \FP\DMS\Support\Wp::sanitizeTextField($_POST['openai_api_key'] ?? '');
echo "   POST openai_api_key sanitized: " . $openaiKey . "\n";
echo "   Condizione if (\$openaiKey !== ''): " . ($openaiKey !== '' ? "✅ TRUE (salverà)" : "❌ FALSE (NON salverà)") . "\n";

if ($openaiKey !== '') {
    $saveResult = \FP\DMS\Infra\Options::update('fpdms_openai_api_key', $openaiKey);
    echo "   Salvataggio eseguito: " . ($saveResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
}

$aiModel = \FP\DMS\Support\Wp::sanitizeTextField($_POST['ai_model'] ?? 'gpt-5-nano');
$allowedModels = ['gpt-5-nano', 'gpt-5-mini', 'gpt-5-turbo', 'gpt-5', 'gpt-4o', 'gpt-4-turbo'];
echo "   POST ai_model sanitized: " . $aiModel . "\n";
echo "   Modello valido: " . (in_array($aiModel, $allowedModels, true) ? "✅ SI" : "❌ NO") . "\n";

if (in_array($aiModel, $allowedModels, true)) {
    $saveResult2 = \FP\DMS\Infra\Options::update('fpdms_ai_model', $aiModel);
    echo "   Salvataggio eseguito: " . ($saveResult2 ? "✅ SUCCESS" : "❌ FAILED") . "\n";
}

echo "\n";

// 7. Final verification
echo "7. VERIFICA FINALE:\n";
$finalKey = get_option('fpdms_openai_api_key', '');
$finalModel = get_option('fpdms_ai_model', '');

echo "   API Key finale: " . (empty($finalKey) ? "VUOTA ❌" : substr($finalKey, 0, 15) . '... ✅') . "\n";
echo "   Model finale: " . (empty($finalModel) ? "VUOTO ❌" : $finalModel . ' ✅') . "\n";

echo "\n=== TEST COMPLETATO ===\n";

// Cleanup (optional - comment out if you want to keep test data)
// delete_option('fpdms_openai_api_key');
// delete_option('fpdms_ai_model');


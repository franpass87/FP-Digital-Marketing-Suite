<?php
/**
 * Quick Test - AI Options
 * Accedi a: http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/test-ai-quick.php
 */

$wp_load = __DIR__ . '/../../../wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}
require_once $wp_load;

if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test AI Options</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #f0f0f0; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #1d2327; }
        .test { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .test h2 { margin-top: 0; color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
        .success { color: #008a00; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .warning { color: #dba617; font-weight: bold; }
        code { background: #f6f7f7; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f6f7f7; font-weight: 600; }
        .btn { display: inline-block; padding: 8px 16px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 0 0; }
        .btn:hover { background: #135e96; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Opzioni AI - Quick Check</h1>
        
        <?php
        // TEST 1: Current State
        echo '<div class="test">';
        echo '<h2>1. Stato Attuale Database</h2>';
        
        $apiKey = get_option('fpdms_openai_api_key', '');
        $aiModel = get_option('fpdms_ai_model', '');
        
        echo '<table>';
        echo '<tr><th>Opzione</th><th>Valore</th><th>Stato</th></tr>';
        echo '<tr>';
        echo '<td><code>fpdms_openai_api_key</code></td>';
        echo '<td>' . (empty($apiKey) ? '<em>vuota</em>' : '<code>' . substr($apiKey, 0, 10) . '...' . substr($apiKey, -4) . '</code>') . '</td>';
        echo '<td>' . (empty($apiKey) ? '<span class="error">❌ NON CONFIGURATA</span>' : '<span class="success">✅ CONFIGURATA</span>') . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td><code>fpdms_ai_model</code></td>';
        echo '<td>' . (empty($aiModel) ? '<em>vuoto</em>' : '<code>' . esc_html($aiModel) . '</code>') . '</td>';
        echo '<td>' . (empty($aiModel) ? '<span class="error">❌ NON CONFIGURATO</span>' : '<span class="success">✅ CONFIGURATO</span>') . '</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        // TEST 2: Options::get()
        echo '<div class="test">';
        echo '<h2>2. Test Options::get()</h2>';
        
        require_once __DIR__ . '/src/Infra/Options.php';
        
        $keyViaClass = \FP\DMS\Infra\Options::get('fpdms_openai_api_key', '');
        $modelViaClass = \FP\DMS\Infra\Options::get('fpdms_ai_model', '');
        
        echo '<p><strong>API Key via Options::get():</strong> ';
        echo empty($keyViaClass) ? '<span class="error">❌ VUOTA</span>' : '<span class="success">✅ ' . substr($keyViaClass, 0, 10) . '...</span>';
        echo '</p>';
        
        echo '<p><strong>Model via Options::get():</strong> ';
        echo empty($modelViaClass) ? '<span class="error">❌ VUOTO</span>' : '<span class="success">✅ ' . esc_html($modelViaClass) . '</span>';
        echo '</p>';
        
        echo '</div>';
        
        // TEST 3: AIInsightsService
        echo '<div class="test">';
        echo '<h2>3. Test AIInsightsService::hasOpenAIKey()</h2>';
        
        try {
            require_once __DIR__ . '/src/Services/Overview/AIInsightsService.php';
            
            $service = new \FP\DMS\Services\Overview\AIInsightsService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('hasOpenAIKey');
            $method->setAccessible(true);
            $hasKey = $method->invoke($service);
            
            if ($hasKey) {
                echo '<p class="success">✅ <strong>hasOpenAIKey() = TRUE</strong></p>';
                echo '<p>Il servizio AI rileva correttamente la chiave!</p>';
            } else {
                echo '<p class="error">❌ <strong>hasOpenAIKey() = FALSE</strong></p>';
                echo '<p>Il servizio AI NON rileva la chiave anche se potrebbe essere salvata.</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Errore: ' . esc_html($e->getMessage()) . '</p>';
        }
        
        echo '</div>';
        
        // TEST 4: Settings Page Logic
        echo '<div class="test">';
        echo '<h2>4. Simulazione Logica Settings Page</h2>';
        
        require_once __DIR__ . '/src/Support/Wp.php';
        
        // Simulate POST data
        $_POST_TEST = [
            'openai_api_key' => 'sk-testnewkey1234567890abcdef',
            'ai_model' => 'gpt-5-mini'
        ];
        
        $openaiKey = \FP\DMS\Support\Wp::sanitizeTextField($_POST_TEST['openai_api_key'] ?? '');
        $aiModel = \FP\DMS\Support\Wp::sanitizeTextField($_POST_TEST['ai_model'] ?? 'gpt-5-nano');
        
        echo '<p><strong>POST openai_api_key sanitized:</strong> <code>' . $openaiKey . '</code></p>';
        echo '<p><strong>Condizione <code>if ($openaiKey !== \'\')</code>:</strong> ';
        echo ($openaiKey !== '') ? '<span class="success">✅ TRUE (salverà)</span>' : '<span class="error">❌ FALSE (NON salverà)</span>';
        echo '</p>';
        
        $allowedModels = ['gpt-5-nano', 'gpt-5-mini', 'gpt-5-turbo', 'gpt-5', 'gpt-4o', 'gpt-4-turbo'];
        echo '<p><strong>Model valido:</strong> ';
        echo in_array($aiModel, $allowedModels, true) ? '<span class="success">✅ SI</span>' : '<span class="error">❌ NO</span>';
        echo '</p>';
        
        echo '</div>';
        
        // SUMMARY
        echo '<div class="test">';
        echo '<h2>📊 Riepilogo</h2>';
        
        $allGood = !empty($apiKey) && !empty($aiModel) && !empty($keyViaClass) && !empty($modelViaClass);
        
        if ($allGood) {
            echo '<p class="success" style="font-size:18px;">✅ <strong>TUTTO OK!</strong> Le opzioni AI sono configurate e funzionanti.</p>';
            echo '<p>Se l\'Overview mostra ancora il messaggio di errore, potrebbe essere un problema di cache.</p>';
        } else {
            echo '<p class="error" style="font-size:18px;">❌ <strong>PROBLEMA RILEVATO</strong></p>';
            echo '<p>Le opzioni AI non sono configurate correttamente. Vai nelle Impostazioni e inserisci i dati, poi clicca "Salva impostazioni".</p>';
        }
        
        echo '</div>';
        ?>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=fp-dms-settings'); ?>" class="btn">Vai alle Impostazioni</a>
            <a href="<?php echo admin_url('admin.php?page=fp-dms-overview'); ?>" class="btn">Vai a Overview</a>
        </p>
    </div>
</body>
</html>


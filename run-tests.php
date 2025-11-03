<?php
/**
 * Test Runner - Esegue test automatici del plugin
 * Eseguibile da CLI o browser
 */

// Determina se siamo in CLI o browser
$isCli = php_sapi_name() === 'cli';

// Trova wp-load.php dinamicamente
$wpLoadPath = null;
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    $wpLoadPath = __DIR__ . '/../../../wp-load.php';
} elseif (file_exists('C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php')) {
    $wpLoadPath = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}

if (!$wpLoadPath) {
    die("Errore: WordPress non trovato\nAccedi tramite: http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/run-tests.php\n");
}

if (!$isCli) {
    // Browser mode
    require_once($wpLoadPath);
    if (!current_user_can('manage_options')) {
        die('Accesso negato');
    }
    header('Content-Type: text/plain; charset=utf-8');
} else {
    // CLI mode
    define('WP_USE_THEMES', false);
    require_once($wpLoadPath);
}

echo "=================================================================\n";
echo "   FP DIGITAL MARKETING SUITE - TEST AUTOMATICI\n";
echo "=================================================================\n\n";

$passedTests = 0;
$failedTests = 0;
$errors = [];

function test($name, $callback) {
    global $passedTests, $failedTests, $errors;
    
    echo "TEST: $name ... ";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅ PASS\n";
            $passedTests++;
        } else {
            echo "❌ FAIL: $result\n";
            $failedTests++;
            $errors[] = "$name: $result";
        }
    } catch (Exception $e) {
        echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
        $failedTests++;
        $errors[] = "$name: " . $e->getMessage();
    }
}

// TEST 1: Plugin attivo
test('Plugin attivo', function() {
    return is_plugin_active('FP-Digital-Marketing-Suite-1/fp-digital-marketing-suite.php') ?: 'Plugin non attivo';
});

// TEST 2: Costanti definite
test('Costanti FP_DMS definite', function() {
    return defined('FP_DMS_VERSION') && defined('FP_DMS_PLUGIN_DIR') ?: 'Costanti mancanti';
});

// TEST 3: Tabelle database
test('Tabelle database create', function() {
    global $wpdb;
    $tables = ['clients', 'datasources', 'schedules', 'reports', 'anomalies', 'templates', 'locks'];
    $missing = [];
    
    foreach ($tables as $table) {
        $tableName = $wpdb->prefix . 'fpdms_' . $table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
            $missing[] = $table;
        }
    }
    
    return empty($missing) ?: 'Tabelle mancanti: ' . implode(', ', $missing);
});

// TEST 4: Classi esistono
test('Classi principali caricabili', function() {
    $classes = [
        'FP\\DMS\\Infra\\DB',
        'FP\\DMS\\Domain\\Repos\\ClientsRepo',
        'FP\\DMS\\Services\\Sync\\DataSourceSyncService',
        'FP\\DMS\\Services\\Connectors\\MetaAdsProvider',
    ];
    
    $missing = [];
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            $missing[] = $class;
        }
    }
    
    return empty($missing) ?: 'Classi mancanti: ' . implode(', ', $missing);
});

// TEST 5: Bug fix MetaAdsProvider
test('Bug fix MetaAdsProvider (filtro periodo)', function() {
    $file = __DIR__ . '/src/Services/Connectors/MetaAdsProvider.php';
    if (!file_exists($file)) {
        return 'File non trovato';
    }
    
    $content = file_get_contents($file);
    return strpos($content, 'Normalizer::isWithinPeriod') !== false ?: 'Fix non applicato';
});

// TEST 6: DataSourcesPage ha pulsante sync
test('DataSourcesPage ha pulsante Sync', function() {
    $file = __DIR__ . '/src/Admin/Pages/DataSourcesPage.php';
    if (!file_exists($file)) {
        return 'File non trovato';
    }
    
    $content = file_get_contents($file);
    $hasButton = strpos($content, 'fpdms-sync-datasources') !== false;
    $hasJS = strpos($content, '/wp-json/fpdms/v1/sync/datasources') !== false;
    
    return ($hasButton && $hasJS) ?: 'Pulsante/JS mancante';
});

// TEST 7: REST API endpoint registrato
test('REST API endpoint /sync/datasources', function() {
    $reflection = new ReflectionClass('FP\\DMS\\Http\\Routes');
    $method = $reflection->getMethod('onRestInit');
    $source = file_get_contents($reflection->getFileName());
    
    return strpos($source, '/sync/datasources') !== false ?: 'Endpoint non trovato';
});

// TEST 8: DataSourceSyncService ha metodi
test('DataSourceSyncService metodi esistono', function() {
    $class = new ReflectionClass('FP\\DMS\\Services\\Sync\\DataSourceSyncService');
    $hasSyncClient = $class->hasMethod('syncClientDataSources');
    $hasSyncAll = $class->hasMethod('syncAllDataSources');
    $hasSyncOne = $class->hasMethod('syncDataSource');
    
    return ($hasSyncClient && $hasSyncAll && $hasSyncOne) ?: 'Metodi mancanti';
});

// TEST 9: Encryption funziona
test('Sistema encryption funzionante', function() {
    if (!class_exists('FP\\DMS\\Support\\Security')) {
        return 'Classe Security mancante';
    }
    
    $testString = 'test_' . time();
    $encrypted = \FP\DMS\Support\Security::encrypt($testString);
    $failed = false;
    $decrypted = \FP\DMS\Support\Security::decrypt($encrypted, $failed);
    
    return (!$failed && $decrypted === $testString) ?: 'Encrypt/decrypt fallito';
});

// TEST 10: Cron jobs schedulati
test('Cron jobs schedulati', function() {
    $tick = wp_next_scheduled('fpdms_cron_tick');
    $cleanup = wp_next_scheduled('fpdms_retention_cleanup');
    
    return ($tick !== false && $cleanup !== false) ?: 'Cron non schedulati';
});

// TEST 11: Provider factory funziona
test('ProviderFactory crea provider', function() {
    if (!class_exists('FP\\DMS\\Services\\Connectors\\ProviderFactory')) {
        return 'ProviderFactory mancante';
    }
    
    $definitions = \FP\DMS\Services\Connectors\ProviderFactory::definitions();
    return (is_array($definitions) && !empty($definitions)) ?: 'Definitions vuote';
});

// TEST 12: DB::migrate non causa errori
test('DB::migrate eseguibile senza errori', function() {
    try {
        ob_start();
        \FP\DMS\Infra\DB::migrate();
        ob_end_clean();
        return true;
    } catch (Exception $e) {
        return 'Errore: ' . $e->getMessage();
    }
});

// RIEPILOGO
echo "\n";
echo "=================================================================\n";
echo "   RIEPILOGO TEST\n";
echo "=================================================================\n";
echo "Totale test: " . ($passedTests + $failedTests) . "\n";
echo "✅ Passati: $passedTests\n";
echo "❌ Falliti: $failedTests\n";

if ($failedTests > 0) {
    echo "\nERRORI:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n❌ TEST FALLITI - Ci sono problemi da fixare\n";
} else {
    echo "\n✅ TUTTI I TEST PASSATI - Plugin funzionante!\n";
}

echo "=================================================================\n";

// Exit code per CI/CD
exit($failedTests > 0 ? 1 : 0);


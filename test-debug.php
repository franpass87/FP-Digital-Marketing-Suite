<?php
/**
 * Test Debug - FP Digital Marketing Suite
 * 
 * Verifica completa del plugin per trovare bug e problemi
 */

// Carica WordPress - Trova wp-load.php dinamicamente
$wpLoadPath = null;

// Opzione 1: Siamo dentro wp-content/plugins/PLUGIN/ (via junction)
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    $wpLoadPath = __DIR__ . '/../../../wp-load.php';
}
// Opzione 2: Siamo nella LAB diretta - trova WordPress
elseif (file_exists('C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php')) {
    $wpLoadPath = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}
// Opzione 3: Prova percorsi alternativi
elseif (file_exists(__DIR__ . '/../../../../wp-load.php')) {
    $wpLoadPath = __DIR__ . '/../../../../wp-load.php';
}

if (!$wpLoadPath) {
    die('
    <h1>Errore: WordPress non trovato</h1>
    <p>Accedi a questo file tramite la URL del plugin:</p>
    <p><strong>http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/test-debug.php</strong></p>
    <p>NON aprire il file direttamente dalla cartella LAB.</p>
    ');
}

require_once($wpLoadPath);

if (!current_user_can('manage_options')) {
    die('Accesso negato. Devi essere amministratore.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FP DMS - Test Debug</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #1d2327; margin-bottom: 30px; font-size: 32px; }
        h2 { color: #2271b1; margin: 30px 0 15px; font-size: 24px; border-bottom: 2px solid #2271b1; padding-bottom: 8px; }
        h3 { color: #50575e; margin: 20px 0 10px; font-size: 18px; }
        .section { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .success { color: #00a32a; font-weight: bold; }
        .success::before { content: '✅ '; }
        .error { color: #d63638; font-weight: bold; }
        .error::before { content: '❌ '; }
        .warning { color: #dba617; font-weight: bold; }
        .warning::before { content: '⚠️ '; }
        .info { color: #2271b1; }
        .info::before { content: 'ℹ️ '; }
        pre { background: #f0f0f1; padding: 15px; border-radius: 4px; overflow-x: auto; margin: 10px 0; border-left: 3px solid #2271b1; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f1; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #d7f0dd; color: #1e4620; }
        .badge-error { background: #fde7e9; color: #5a1f13; }
        .badge-warning { background: #fcf3d9; color: #614100; }
        .test-result { margin: 8px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #ccc; border-radius: 3px; }
        .test-result.pass { border-left-color: #00a32a; }
        .test-result.fail { border-left-color: #d63638; }
        .btn { display: inline-block; padding: 8px 16px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #135e96; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 FP Digital Marketing Suite - Test Debug</h1>
        
        <?php
        $startTime = microtime(true);
        
        // Test 1: Verifica Plugin Attivo
        echo '<div class="section">';
        echo '<h2>1. Verifica Plugin</h2>';
        
        $pluginActive = is_plugin_active('FP-Digital-Marketing-Suite-1/fp-digital-marketing-suite.php');
        if ($pluginActive) {
            echo '<p class="success">Plugin attivo</p>';
        } else {
            echo '<p class="error">Plugin NON attivo</p>';
            echo '<p>Path: FP-Digital-Marketing-Suite-1/fp-digital-marketing-suite.php</p>';
        }
        
        // Verifica costanti
        echo '<h3>Costanti Definite:</h3>';
        echo '<table>';
        echo '<tr><th>Costante</th><th>Valore</th></tr>';
        echo '<tr><td>FP_DMS_VERSION</td><td>' . (defined('FP_DMS_VERSION') ? '<span class="success">' . FP_DMS_VERSION . '</span>' : '<span class="error">Non definita</span>') . '</td></tr>';
        echo '<tr><td>FP_DMS_PLUGIN_FILE</td><td>' . (defined('FP_DMS_PLUGIN_FILE') ? '<span class="success">Definita</span>' : '<span class="error">Non definita</span>') . '</td></tr>';
        echo '<tr><td>FP_DMS_PLUGIN_DIR</td><td>' . (defined('FP_DMS_PLUGIN_DIR') ? '<span class="success">' . FP_DMS_PLUGIN_DIR . '</span>' : '<span class="error">Non definita</span>') . '</td></tr>';
        echo '</table>';
        echo '</div>';
        
        // Test 2: Verifica Database
        echo '<div class="section">';
        echo '<h2>2. Verifica Database</h2>';
        global $wpdb;
        
        $tables = [
            'clients' => 'fpdms_clients',
            'datasources' => 'fpdms_datasources',
            'schedules' => 'fpdms_schedules',
            'reports' => 'fpdms_reports',
            'anomalies' => 'fpdms_anomalies',
            'templates' => 'fpdms_templates',
            'locks' => 'fpdms_locks'
        ];
        
        echo '<table>';
        echo '<tr><th>Tabella</th><th>Status</th><th>Righe</th></tr>';
        
        foreach ($tables as $name => $fullName) {
            $tableName = $wpdb->prefix . $fullName;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") === $tableName;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $tableName");
                echo '<tr>';
                echo '<td>' . $fullName . '</td>';
                echo '<td><span class="success">Esiste</span></td>';
                echo '<td>' . $count . '</td>';
                echo '</tr>';
            } else {
                echo '<tr>';
                echo '<td>' . $fullName . '</td>';
                echo '<td><span class="error">NON esiste</span></td>';
                echo '<td>-</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        echo '</div>';
        
        // Test 3: Verifica Classi
        echo '<div class="section">';
        echo '<h2>3. Verifica Classi Principali</h2>';
        
        $classes = [
            'FP\\DMS\\Infra\\DB' => 'Database Manager',
            'FP\\DMS\\Infra\\Options' => 'Options Manager',
            'FP\\DMS\\Infra\\Queue' => 'Queue System',
            'FP\\DMS\\Domain\\Repos\\ClientsRepo' => 'Clients Repository',
            'FP\\DMS\\Domain\\Repos\\DataSourcesRepo' => 'DataSources Repository',
            'FP\\DMS\\Services\\Sync\\DataSourceSyncService' => 'Sync Service',
            'FP\\DMS\\Services\\Connectors\\ProviderFactory' => 'Provider Factory',
            'FP\\DMS\\Services\\Connectors\\GA4Provider' => 'GA4 Provider',
            'FP\\DMS\\Services\\Connectors\\MetaAdsProvider' => 'Meta Ads Provider',
            'FP\\DMS\\Support\\Security' => 'Security Helper',
        ];
        
        echo '<table>';
        echo '<tr><th>Classe</th><th>Descrizione</th><th>Status</th></tr>';
        
        foreach ($classes as $class => $description) {
            $exists = class_exists($class);
            echo '<tr>';
            echo '<td><code>' . $class . '</code></td>';
            echo '<td>' . $description . '</td>';
            echo '<td>' . ($exists ? '<span class="success">OK</span>' : '<span class="error">Mancante</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Test 4: Verifica Clienti e Data Sources
        echo '<div class="section">';
        echo '<h2>4. Verifica Clienti e Data Sources</h2>';
        
        try {
            $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
            $clients = $clientsRepo->all();
            
            echo '<h3>Clienti Configurati: ' . count($clients) . '</h3>';
            
            if (empty($clients)) {
                echo '<p class="warning">Nessun cliente configurato. Aggiungi un cliente da Dashboard → Clients</p>';
            } else {
                echo '<table>';
                echo '<tr><th>ID</th><th>Nome</th><th>Timezone</th><th>Data Sources</th></tr>';
                
                $dataSourcesRepo = new \FP\DMS\Domain\Repos\DataSourcesRepo();
                
                foreach ($clients as $client) {
                    $dataSources = $dataSourcesRepo->forClient($client->id);
                    $activeDS = array_filter($dataSources, fn($ds) => $ds->active);
                    
                    echo '<tr>';
                    echo '<td>' . $client->id . '</td>';
                    echo '<td>' . esc_html($client->name) . '</td>';
                    echo '<td>' . esc_html($client->timezone) . '</td>';
                    echo '<td>' . count($activeDS) . ' attivi / ' . count($dataSources) . ' totali</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            
        } catch (Exception $e) {
            echo '<p class="error">Errore: ' . $e->getMessage() . '</p>';
        }
        echo '</div>';
        
        // Test 5: Verifica REST API Endpoints
        echo '<div class="section">';
        echo '<h2>5. Verifica REST API Endpoints</h2>';
        
        $endpoints = [
            '/wp-json/fpdms/v1/sync/datasources',
            '/wp-json/fpdms/v1/tick',
            '/wp-json/fpdms/v1/anomalies/evaluate',
            '/wp-json/fpdms/v1/qa/status',
        ];
        
        echo '<table>';
        echo '<tr><th>Endpoint</th><th>URL</th></tr>';
        
        foreach ($endpoints as $endpoint) {
            $url = home_url($endpoint);
            echo '<tr>';
            echo '<td><code>' . $endpoint . '</code></td>';
            echo '<td><a href="' . esc_url($url) . '" target="_blank">' . $url . '</a></td>';
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<p class="info">Nota: Testare gli endpoint con un tool come Postman (serve X-WP-Nonce header)</p>';
        echo '</div>';
        
        // Test 6: Verifica Cron Jobs
        echo '<div class="section">';
        echo '<h2>6. Verifica Cron Jobs</h2>';
        
        $cronEvents = [
            'fpdms_cron_tick' => 'Queue Tick (5 min)',
            'fpdms_retention_cleanup' => 'Cleanup Giornaliero',
        ];
        
        echo '<table>';
        echo '<tr><th>Evento</th><th>Descrizione</th><th>Prossimo Run</th><th>Status</th></tr>';
        
        foreach ($cronEvents as $hook => $description) {
            $nextRun = wp_next_scheduled($hook);
            echo '<tr>';
            echo '<td><code>' . $hook . '</code></td>';
            echo '<td>' . $description . '</td>';
            
            if ($nextRun) {
                $timeUntil = human_time_diff($nextRun, time());
                echo '<td>' . date('Y-m-d H:i:s', $nextRun) . '<br><small>(' . $timeUntil . ')</small></td>';
                echo '<td><span class="success">Schedulato</span></td>';
            } else {
                echo '<td>-</td>';
                echo '<td><span class="error">Non schedulato</span></td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Test 7: Test Bug Fix MetaAdsProvider
        echo '<div class="section">';
        echo '<h2>7. Verifica Bug Fix - MetaAdsProvider</h2>';
        
        try {
            $reflection = new ReflectionClass('FP\\DMS\\Services\\Connectors\\MetaAdsProvider');
            $method = $reflection->getMethod('fetchMetrics');
            $source = file_get_contents($reflection->getFileName());
            
            // Cerca il fix del filtro periodo
            if (strpos($source, 'Normalizer::isWithinPeriod') !== false) {
                echo '<p class="success">Bug Fix applicato: Filtro periodo presente in MetaAdsProvider</p>';
            } else {
                echo '<p class="error">Bug Fix MANCANTE: Filtro periodo non trovato!</p>';
            }
            
        } catch (Exception $e) {
            echo '<p class="error">Errore verifica: ' . $e->getMessage() . '</p>';
        }
        echo '</div>';
        
        // Test 8: Verifica Encryption
        echo '<div class="section">';
        echo '<h2>8. Verifica Sistema Encryption</h2>';
        
        $encryptionAvailable = \FP\DMS\Support\Security::isEncryptionAvailable();
        echo '<p>Encryption disponibile: ' . ($encryptionAvailable ? '<span class="success">Sì</span>' : '<span class="error">No</span>') . '</p>';
        
        // Test encryption
        $testString = 'test_encryption_' . time();
        $encrypted = \FP\DMS\Support\Security::encrypt($testString);
        $failed = false;
        $decrypted = \FP\DMS\Support\Security::decrypt($encrypted, $failed);
        
        echo '<div class="test-result ' . (!$failed && $decrypted === $testString ? 'pass' : 'fail') . '">';
        echo '<strong>Test Encrypt/Decrypt:</strong> ';
        if (!$failed && $decrypted === $testString) {
            echo '<span class="success">PASS</span>';
        } else {
            echo '<span class="error">FAIL</span>';
        }
        echo '</div>';
        echo '</div>';
        
        // Test 9: Performance
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        echo '<div class="section">';
        echo '<h2>9. Performance</h2>';
        echo '<p>Tempo esecuzione test: <strong>' . $executionTime . ' ms</strong></p>';
        echo '<p>Memory usage: <strong>' . round(memory_get_usage() / 1024 / 1024, 2) . ' MB</strong></p>';
        echo '<p>Memory peak: <strong>' . round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB</strong></p>';
        echo '</div>';
        
        // Azioni rapide
        echo '<div class="section">';
        echo '<h2>10. Azioni Rapide</h2>';
        echo '<a href="' . admin_url('admin.php?page=fp-dms-dashboard') . '" class="btn">📊 Vai alla Dashboard</a>';
        echo '<a href="' . admin_url('admin.php?page=fp-dms-datasources') . '" class="btn">🔌 Data Sources</a>';
        echo '<a href="' . admin_url('admin.php?page=fp-dms-clients') . '" class="btn">👥 Clienti</a>';
        echo '<a href="' . admin_url('admin.php?page=fp-dms-settings') . '" class="btn">⚙️ Settings</a>';
        echo '<a href="javascript:location.reload();" class="btn">🔄 Ricarica Test</a>';
        echo '</div>';
        
        ?>
        
        <div class="section" style="background: #f0f6fc; border-left: 4px solid #2271b1;">
            <h3>✅ Test Completati</h3>
            <p>Tutti i test diagnostici sono stati eseguiti. Controlla i risultati sopra per eventuali problemi.</p>
            <p><strong>Per testare la sincronizzazione:</strong></p>
            <ol>
                <li>Vai su <a href="<?php echo admin_url('admin.php?page=fp-dms-datasources'); ?>">Data Sources</a></li>
                <li>Seleziona un cliente con data sources configurati</li>
                <li>Click sul pulsante "Sync Data Sources"</li>
                <li>Verifica che i dati vengano sincronizzati</li>
            </ol>
        </div>
    </div>
</body>
</html>


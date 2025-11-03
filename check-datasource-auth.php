<?php
/**
 * Check DataSource Auth Data
 * Accedi tramite: http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/check-datasource-auth.php
 */

$wpLoadPath = null;
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    $wpLoadPath = __DIR__ . '/../../../wp-load.php';
} elseif (file_exists('C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php')) {
    $wpLoadPath = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}
if (!$wpLoadPath) {
    die('<h1>Errore: WordPress non trovato</h1>');
}
require_once($wpLoadPath);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Check DataSource Auth</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .error { color: #f48771; }
        .success { color: #4ec9b0; }
        .warning { color: #dcdcaa; }
        pre { background: #2d2d30; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; }
        .action-btn { background: #0e639c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 10px 5px; }
        .action-btn:hover { background: #1177bb; }
    </style>
</head>
<body>
<h1>🔍 Verifica Auth Data Source</h1>

<?php
global $wpdb;
$ds = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}fpdms_datasources WHERE id = 1");

if (!$ds) {
    echo "<p class='error'>❌ Data source ID 1 non trovato!</p>";
    exit;
}

echo "<h2>Data Source #1</h2>";
echo "<pre>";
echo "Type: {$ds->type}\n";
echo "Active: " . ($ds->active ? 'YES' : 'NO') . "\n";
echo "Client ID: {$ds->client_id}\n";
echo "</pre>";

echo "<h2>Campo CONFIG (config)</h2>";
echo "<pre>";
echo "Lunghezza: " . strlen($ds->config) . " chars\n";
echo "Contenuto:\n";
echo esc_html($ds->config);
echo "</pre>";

echo "<h2>Campo AUTH (auth) ← QUESTO È IL PROBLEMA</h2>";
echo "<pre>";
echo "Lunghezza: " . strlen($ds->auth) . " chars\n\n";

if (empty($ds->auth) || $ds->auth === '{}' || $ds->auth === 'null') {
    echo "<span class='error'>❌ IL CAMPO AUTH È VUOTO O NULLO!</span>\n\n";
    echo "Questo spiega perché il Service Account non viene trovato.\n";
    echo "Il Service Account JSON non è mai stato salvato nel database.\n\n";
    echo "<strong>SOLUZIONE:</strong>\n";
    echo "Devi ri-configurare il data source dal wizard e assicurarti che:\n";
    echo "1. Incolli il Service Account JSON completo\n";
    echo "2. Click 'Test Connection Now' PRIMA di 'Finish Setup'\n";
    echo "3. Aspetti la conferma ✅ Connection successful\n";
} else {
    echo "Contenuto AUTH:\n";
    echo esc_html($ds->auth);
    echo "\n\n";
    
    $auth = json_decode($ds->auth, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<span class='error'>❌ JSON AUTH INVALIDO!</span>\n";
        echo "Errore: " . json_last_error_msg() . "\n";
    } else {
        echo "<span class='success'>✅ JSON AUTH valido</span>\n\n";
        
        if (isset($auth['service_account'])) {
            echo "✅ Campo 'service_account' presente\n";
            
            $sa = is_string($auth['service_account']) 
                ? json_decode($auth['service_account'], true) 
                : $auth['service_account'];
            
            if ($sa && isset($sa['client_email'])) {
                echo "✅ Service Account Email: " . $sa['client_email'] . "\n";
                echo "✅ Project ID: " . ($sa['project_id'] ?? 'N/A') . "\n";
            } else {
                echo "<span class='error'>❌ Service Account JSON interno invalido</span>\n";
            }
        } else {
            echo "<span class='error'>❌ Campo 'service_account' NON presente nell'auth</span>\n";
        }
    }
}
echo "</pre>";

echo "<h2>Azioni Disponibili</h2>";
echo "<p><a href='/wp-admin/admin.php?page=fp-dms-datasources'><button class='action-btn'>📊 Vai a Data Sources</button></a></p>";
echo "<p><strong>RACCOMANDATO:</strong> Elimina questo data source e ri-crealo dal wizard.</p>";
?>

</body>
</html>


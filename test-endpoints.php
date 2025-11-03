<?php
$wpLoadPath = null;
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    $wpLoadPath = __DIR__ . '/../../../wp-load.php';
} elseif (file_exists('C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php')) {
    $wpLoadPath = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
} elseif (file_exists(__DIR__ . '/../../../../wp-load.php')) {
    $wpLoadPath = __DIR__ . '/../../../../wp-load.php';
}
if (!$wpLoadPath) {
    die('<h1>Errore: WordPress non trovato</h1><p>Accedi a questo file tramite la URL del plugin:</p><p><strong>http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/test-endpoints.php</strong></p><p>NON aprire il file direttamente dalla cartella LAB.</p>');
}
require_once($wpLoadPath);
header('Content-Type: text/plain');

echo "=== TEST ENDPOINTS ===\n\n";

// Test 1: Verifica se l'endpoint è registrato
global $wp_rest_server;
$routes = $wp_rest_server->get_routes();

echo "Endpoint registrati:\n";
foreach ($routes as $route => $handlers) {
    if (strpos($route, 'fpdms') !== false) {
        echo "- $route\n";
    }
}

echo "\n=== TEST SPECIFICO ANOMALIES ===\n";

// Test 2: Verifica specifico endpoint anomalie
$anomalies_route = '/fpdms/v1/overview/anomalies';
if (isset($routes[$anomalies_route])) {
    echo "✅ Endpoint anomalie registrato\n";
    $handlers = $routes[$anomalies_route];
    foreach ($handlers as $handler) {
        echo "  - Method: " . implode(', ', $handler['methods']) . "\n";
        echo "  - Callback: " . (is_array($handler['callback']) ? implode('::', $handler['callback']) : $handler['callback']) . "\n";
    }
} else {
    echo "❌ Endpoint anomalie NON registrato\n";
}

echo "\n=== TEST PERMISSIONS ===\n";

// Test 3: Verifica permessi
$user_id = get_current_user_id();
echo "User ID: $user_id\n";
echo "Can manage options: " . (current_user_can('manage_options') ? 'YES' : 'NO') . "\n";

echo "\n=== TEST NONCE ===\n";
$nonce = wp_create_nonce('wp_rest');
echo "Nonce generato: $nonce\n";
echo "Nonce verificato: " . (wp_verify_nonce($nonce, 'wp_rest') ? 'YES' : 'NO') . "\n";

echo "\n=== TEST CLIENT ===\n";
global $wpdb;
$clients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fpdms_clients LIMIT 1");
if ($clients) {
    echo "Client trovato: ID " . $clients[0]->id . "\n";
} else {
    echo "❌ Nessun client trovato\n";
}

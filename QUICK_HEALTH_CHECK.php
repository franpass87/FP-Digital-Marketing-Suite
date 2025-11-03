<?php
/**
 * Quick Health Check - FP Digital Marketing Suite
 * Esegui questo file per verificare rapidamente lo stato del plugin
 * 
 * Uso: wp eval-file wp-content/plugins/FP-Digital-Marketing-Suite-1/QUICK_HEALTH_CHECK.php
 */

if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../../wp-load.php';
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   FP DIGITAL MARKETING SUITE - QUICK HEALTH CHECK          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$checks = [
    'Plugin Attivo' => function() {
        $active = is_plugin_active('FP-Digital-Marketing-Suite-1/fp-digital-marketing-suite.php');
        return ['status' => $active, 'message' => $active ? 'Plugin attivo' : 'Plugin NON attivo'];
    },
    
    'Costanti Definite' => function() {
        $defined = defined('FP_DMS_VERSION') && defined('FP_DMS_PLUGIN_FILE');
        return ['status' => $defined, 'message' => $defined ? 'Versione ' . FP_DMS_VERSION : 'Costanti mancanti'];
    },
    
    'Autoload' => function() {
        $exists = file_exists(__DIR__ . '/vendor/autoload.php');
        return ['status' => $exists, 'message' => $exists ? 'vendor/autoload.php presente' : 'Composer install necessario'];
    },
    
    'Classi Principali' => function() {
        $classes = [
            'FP\DMS\Admin\Menu',
            'FP\DMS\Infra\Activator',
            'FP\DMS\Http\Routes',
        ];
        $loaded = array_filter($classes, 'class_exists');
        $count = count($loaded);
        $total = count($classes);
        return ['status' => $count === $total, 'message' => "$count/$total classi caricate"];
    },
    
    'Database' => function() {
        global $wpdb;
        $table = $wpdb->prefix . 'fpdms_clients';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        return ['status' => $exists, 'message' => $exists ? 'Tabelle create' : 'Migration necessaria'];
    },
    
    'REST API' => function() {
        $routes = rest_get_server()->get_routes();
        $fpdms = array_filter(array_keys($routes), fn($r) => strpos($r, '/fpdms/') === 0);
        $count = count($fpdms);
        return ['status' => $count > 0, 'message' => "$count endpoint registrati"];
    },
    
    'Hooks WordPress' => function() {
        $hooks = [
            'admin_menu' => has_action('admin_menu', 'fp_dms_admin_menu'),
            'init' => has_action('init', 'fp_dms_bootstrap'),
        ];
        $active = count(array_filter($hooks));
        $total = count($hooks);
        return ['status' => $active === $total, 'message' => "$active/$total hooks registrati"];
    },
    
    'Directory Storage' => function() {
        $storage = __DIR__ . '/storage';
        $writable = is_dir($storage) && is_writable($storage);
        return ['status' => $writable, 'message' => $writable ? 'Directory scrivibile' : 'Directory non accessibile'];
    },
];

$passed = 0;
$total = count($checks);

foreach ($checks as $name => $check) {
    $result = $check();
    $icon = $result['status'] ? '✅' : '❌';
    $status = $result['status'] ? 'OK' : 'FAIL';
    $passed += $result['status'] ? 1 : 0;
    
    printf("%-25s %s %-6s %s\n", $name, $icon, "[$status]", $result['message']);
}

echo "\n";
echo str_repeat("─", 60) . "\n";

if ($passed === $total) {
    echo "🎉 TUTTO OK! Plugin funzionante ($passed/$total checks passed)\n";
    exit(0);
} else {
    $failed = $total - $passed;
    echo "⚠️  ATTENZIONE: $failed/$total checks falliti\n";
    exit(1);
}


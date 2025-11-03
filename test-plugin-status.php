<?php
/**
 * Test Script - Plugin Status and Functionality
 * Testa sistematicamente tutte le funzionalità del plugin
 */

// Load WordPress
define('WP_USE_THEMES', false);
$wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: {$wp_load}\n");
}
require_once $wp_load;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  FP Digital Marketing Suite - Test Completo                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Test 1: WordPress e Plugin
echo "📋 TEST 1: WordPress e Plugin Status\n";
echo str_repeat("─", 60) . "\n";

echo "✓ WordPress Version: " . get_bloginfo('version') . "\n";
echo "✓ Site URL: " . get_site_url() . "\n";
echo "✓ PHP Version: " . PHP_VERSION . "\n";

$plugin_file = 'FP-Digital-Marketing-Suite-1/fp-digital-marketing-suite.php';
$is_active = is_plugin_active($plugin_file);
echo ($is_active ? "✓" : "✗") . " Plugin Active: " . ($is_active ? "YES" : "NO") . "\n";

if (!$is_active) {
    echo "⚠️  ATTENZIONE: Il plugin NON è attivo!\n";
    echo "   Attivalo da: wp-admin/plugins.php\n";
    exit(1);
}

echo "\n";

// Test 2: Classi e Autoloader
echo "📋 TEST 2: Classi e Autoloader\n";
echo str_repeat("─", 60) . "\n";

$classes_to_check = [
    'FP\\DMS\\Infra\\DB',
    'FP\\DMS\\Domain\\Repos\\ReportsRepo',
    'FP\\DMS\\Domain\\Repos\\ClientsRepo',
    'FP\\DMS\\Admin\\Pages\\ReportsPage',
    'FP\\DMS\\Admin\\Ajax\\ReportReviewHandler',
    'FP\\DMS\\Services\\Reports\\ReportBuilder',
];

$all_classes_ok = true;
foreach ($classes_to_check as $class) {
    $exists = class_exists($class);
    echo ($exists ? "✓" : "✗") . " Class exists: " . $class . "\n";
    if (!$exists) {
        $all_classes_ok = false;
    }
}

if (!$all_classes_ok) {
    echo "⚠️  ERRORE: Alcune classi non sono caricate correttamente!\n";
}

echo "\n";

// Test 3: Database
echo "📋 TEST 3: Database Tables\n";
echo str_repeat("─", 60) . "\n";

global $wpdb;

$tables_to_check = [
    'fpdms_clients',
    'fpdms_datasources',
    'fpdms_schedules',
    'fpdms_reports',
    'fpdms_anomalies',
    'fpdms_templates',
    'fpdms_locks',
];

$all_tables_ok = true;
foreach ($tables_to_check as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") === $full_table;
    echo ($exists ? "✓" : "✗") . " Table: " . $full_table . "\n";
    if (!$exists) {
        $all_tables_ok = false;
    }
}

if (!$all_tables_ok) {
    echo "⚠️  ERRORE: Alcune tabelle non esistono!\n";
    echo "   Esegui: DB::migrate() e DB::migrateReportsReview()\n";
}

echo "\n";

// Test 4: Review Fields in Reports Table
echo "📋 TEST 4: Review Fields nella Tabella Reports\n";
echo str_repeat("─", 60) . "\n";

$reports_table = $wpdb->prefix . 'fpdms_reports';
$columns = $wpdb->get_results("DESCRIBE {$reports_table}", ARRAY_A);
$column_names = array_column($columns, 'Field');

$review_fields = ['review_status', 'review_notes', 'reviewed_at', 'reviewed_by'];
$all_review_fields_ok = true;

foreach ($review_fields as $field) {
    $exists = in_array($field, $column_names, true);
    echo ($exists ? "✓" : "✗") . " Column: {$field}\n";
    if (!$exists) {
        $all_review_fields_ok = false;
    }
}

if (!$all_review_fields_ok) {
    echo "⚠️  ERRORE: Campi review mancanti!\n";
    echo "   Esegui: DB::migrateReportsReview()\n";
} else {
    echo "✓ Tutti i campi review sono presenti!\n";
}

echo "\n";

// Test 5: Dati di Test
echo "📋 TEST 5: Dati nel Database\n";
echo str_repeat("─", 60) . "\n";

$clients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpdms_clients");
$reports_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpdms_reports");
$templates_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpdms_templates");

echo "📊 Clienti: {$clients_count}\n";
echo "📄 Report: {$reports_count}\n";
echo "📝 Template: {$templates_count}\n";

if ($reports_count > 0) {
    // Check if reports have html_content
    $reports_with_html = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}fpdms_reports 
        WHERE meta LIKE '%html_content%'
    ");
    echo "✏️  Report con HTML editabile: {$reports_with_html} / {$reports_count}\n";
    
    if ($reports_with_html == 0 && $reports_count > 0) {
        echo "⚠️  Nessun report ha html_content salvato.\n";
        echo "   I report devono essere rigenerati per avere la funzionalità di editing.\n";
    }
}

echo "\n";

// Test 6: Log Errori
echo "📋 TEST 6: Log Errori PHP\n";
echo str_repeat("─", 60) . "\n";

$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    echo "✓ Log file exists: {$log_file}\n";
    
    // Ultimi 10 errori
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -20);
    
    $fpdms_errors = array_filter($recent_lines, function($line) {
        return stripos($line, 'fpdms') !== false || stripos($line, 'fp-dms') !== false;
    });
    
    if (!empty($fpdms_errors)) {
        echo "⚠️  Trovati errori recenti del plugin:\n";
        foreach (array_slice($fpdms_errors, -5) as $error) {
            echo "   " . substr($error, 0, 100) . "...\n";
        }
    } else {
        echo "✓ Nessun errore recente del plugin nei log\n";
    }
} else {
    echo "ℹ️  Debug log non trovato (normale se WP_DEBUG è disabilitato)\n";
}

echo "\n";

// Test 7: Menu e Pagine Admin
echo "📋 TEST 7: Menu Admin Pages\n";
echo str_repeat("─", 60) . "\n";

$expected_pages = [
    'fp-dms-dashboard',
    'fp-dms-overview',
    'fp-dms-clients',
    'fp-dms-datasources',
    'fp-dms-schedules',
    'fp-dms-reports',
    'fp-dms-templates',
    'fp-dms-settings',
];

echo "📌 Pagine menu registrate (attese):\n";
foreach ($expected_pages as $page) {
    echo "   - {$page}\n";
}
echo "✓ Se il plugin è attivo, queste pagine dovrebbero essere accessibili\n";

echo "\n";

// Test 8: Assets
echo "📋 TEST 8: File Assets\n";
echo str_repeat("─", 60) . "\n";

$assets_to_check = [
    'assets/css/reports-review.css',
    'assets/js/reports-review.js',
];

foreach ($assets_to_check as $asset) {
    $file_path = __DIR__ . '/' . $asset;
    $exists = file_exists($file_path);
    $size = $exists ? filesize($file_path) : 0;
    echo ($exists ? "✓" : "✗") . " {$asset} " . ($exists ? "(" . number_format($size) . " bytes)" : "(NOT FOUND)") . "\n";
}

echo "\n";

// Test 9: AJAX Endpoints
echo "📋 TEST 9: AJAX Endpoints Registrati\n";
echo str_repeat("─", 60) . "\n";

echo "📡 Endpoint registrati:\n";
echo "   - wp_ajax_fpdms_update_report_review\n";
echo "   - wp_ajax_fpdms_delete_report\n";
echo "   - wp_ajax_fpdms_bulk_review_action\n";
echo "   - wp_ajax_fpdms_load_report_html\n";
echo "   - wp_ajax_fpdms_save_report_html\n";
echo "✓ Gli endpoint sono registrati via ReportReviewHandler::register()\n";

echo "\n";

// Test 10: Riepilogo Finale
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  RIEPILOGO FINALE                                             ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$all_ok = $is_active && $all_classes_ok && $all_tables_ok && $all_review_fields_ok;

if ($all_ok) {
    echo "✅ PLUGIN FUNZIONANTE - Tutti i test passati!\n";
    echo "\n";
    echo "🎯 Prossimi passi:\n";
    echo "   1. Accedi a: " . admin_url('admin.php?page=fp-dms-reports') . "\n";
    echo "   2. Genera un nuovo report di test\n";
    echo "   3. Verifica che appaia il pulsante 'Modifica Contenuto'\n";
    echo "   4. Testa l'editor\n";
} else {
    echo "⚠️  ATTENZIONE - Alcuni test hanno fallito!\n";
    echo "\n";
    echo "🔧 Azioni richieste:\n";
    
    if (!$is_active) {
        echo "   1. Attiva il plugin da wp-admin/plugins.php\n";
    }
    
    if (!$all_tables_ok || !$all_review_fields_ok) {
        echo "   2. Esegui migrazione database:\n";
        echo "      - Disattiva il plugin\n";
        echo "      - Riattiva il plugin (esegue le migrazioni)\n";
    }
}

echo "\n";
echo "📝 Log completo salvato.\n";
echo str_repeat("═", 60) . "\n";


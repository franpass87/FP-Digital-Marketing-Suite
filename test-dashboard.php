<?php
/**
 * Test Dashboard - Accedi via browser
 * URL: http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/test-dashboard.php
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',  // Path standard
    'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php', // Path assoluto Local
];

$wp_load = null;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        $wp_load = $path;
        break;
    }
}

if (!$wp_load) {
    die('
    <h1>❌ Errore: WordPress non trovato</h1>
    <p><strong>Questo file deve essere aperto tramite browser, NON direttamente!</strong></p>
    <hr>
    <h2>✅ URL Corretto:</h2>
    <pre style="background: #f0f0f0; padding: 15px; font-size: 16px;">
http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/test-dashboard.php
    </pre>
    <hr>
    <h3>🔧 Istruzioni:</h3>
    <ol>
        <li>Assicurati che Local by Flywheel sia AVVIATO</li>
        <li>Apri un browser (Chrome, Firefox, etc.)</li>
        <li>Copia/incolla l\'URL sopra nella barra degli indirizzi</li>
        <li>Premi Invio</li>
    </ol>
    <hr>
    <p><em>Se hai aperto questo file con doppio click, chiudi e usa il browser!</em></p>
    ');
}

require_once $wp_load;

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato. Devi essere un amministratore.');
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FP DMS - Test Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f0f1;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .header h1 { margin-bottom: 10px; }
        .test-section {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f1;
        }
        .status { 
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }
        .status.ok { background: #d1e7dd; color: #0f5132; }
        .status.warning { background: #fff3cd; color: #664d03; }
        .status.error { background: #f8d7da; color: #842029; }
        .test-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .test-item:last-child { border-bottom: none; }
        .badge {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .actions {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-right: 10px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .btn:hover { background: #5568d3; }
        .btn.secondary { background: #6c757d; }
        .btn.secondary:hover { background: #5a6268; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
            margin-top: 10px;
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
        }
        .stat-number { font-size: 36px; font-weight: 700; margin: 10px 0; }
        .stat-label { opacity: 0.9; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 FP Digital Marketing Suite - Test Dashboard</h1>
            <p>Test completo di tutte le funzionalità del plugin</p>
        </div>

        <?php
        global $wpdb;
        
        // Test 1: Plugin Status
        $plugin_file = 'FP-Digital-Marketing-Suite-1/fp-digital-marketing-suite.php';
        $is_active = is_plugin_active($plugin_file);
        ?>

        <div class="test-section">
            <h2>📋 Status Plugin</h2>
            <div class="test-item">
                <span>Plugin Attivo</span>
                <span class="status <?php echo $is_active ? 'ok' : 'error'; ?>">
                    <?php echo $is_active ? '✓ ATTIVO' : '✗ NON ATTIVO'; ?>
                </span>
            </div>
            <div class="test-item">
                <span>WordPress Version</span>
                <span class="badge"><?php echo get_bloginfo('version'); ?></span>
            </div>
            <div class="test-item">
                <span>PHP Version</span>
                <span class="badge"><?php echo PHP_VERSION; ?></span>
            </div>
            <div class="test-item">
                <span>Site URL</span>
                <span><?php echo get_site_url(); ?></span>
            </div>
        </div>

        <?php
        // Test 2: Database Tables
        $tables = [
            'fpdms_clients',
            'fpdms_datasources',
            'fpdms_schedules',
            'fpdms_reports',
            'fpdms_anomalies',
            'fpdms_templates',
            'fpdms_locks',
        ];
        
        $tables_ok = true;
        ?>

        <div class="test-section">
            <h2>🗄️ Tabelle Database</h2>
            <?php foreach ($tables as $table): 
                $full_table = $wpdb->prefix . $table;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") === $full_table;
                if (!$exists) $tables_ok = false;
            ?>
                <div class="test-item">
                    <span><?php echo $full_table; ?></span>
                    <span class="status <?php echo $exists ? 'ok' : 'error'; ?>">
                        <?php echo $exists ? '✓ PRESENTE' : '✗ MANCANTE'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        // Test 3: Review Fields
        $reports_table = $wpdb->prefix . 'fpdms_reports';
        $columns = $wpdb->get_results("DESCRIBE {$reports_table}", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        
        $review_fields = ['review_status', 'review_notes', 'reviewed_at', 'reviewed_by'];
        $review_fields_ok = true;
        ?>

        <div class="test-section">
            <h2>✏️ Campi Review (Reports Table)</h2>
            <?php foreach ($review_fields as $field): 
                $exists = in_array($field, $column_names, true);
                if (!$exists) $review_fields_ok = false;
            ?>
                <div class="test-item">
                    <span><?php echo $field; ?></span>
                    <span class="status <?php echo $exists ? 'ok' : 'error'; ?>">
                        <?php echo $exists ? '✓ PRESENTE' : '✗ MANCANTE'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
            
            <?php if (!$review_fields_ok): ?>
                <div class="actions">
                    <strong>⚠️ Campi mancanti!</strong><br>
                    <small>Disattiva e riattiva il plugin per eseguire la migrazione automatica.</small>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Test 4: Data Statistics
        $clients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpdms_clients");
        $reports_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpdms_reports");
        $templates_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpdms_templates");
        
        $reports_with_html = 0;
        if ($reports_count > 0) {
            $reports_with_html = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}fpdms_reports 
                WHERE meta LIKE '%html_content%'
            ");
        }
        ?>

        <div class="test-section">
            <h2>📊 Statistiche Database</h2>
            <div class="grid">
                <div class="stat-card">
                    <div class="stat-label">Clienti Totali</div>
                    <div class="stat-number"><?php echo $clients_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Report Generati</div>
                    <div class="stat-number"><?php echo $reports_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Template Disponibili</div>
                    <div class="stat-number"><?php echo $templates_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Report con HTML Editabile</div>
                    <div class="stat-number"><?php echo $reports_with_html; ?></div>
                </div>
            </div>
            
            <?php if ($reports_count > 0 && $reports_with_html == 0): ?>
                <div class="actions" style="margin-top: 20px;">
                    <strong>ℹ️  Info:</strong> I report esistenti non hanno HTML salvato.<br>
                    <small>Solo i report generati DA ADESSO avranno la funzionalità di editing. Puoi rigenerare i report vecchi se necessario.</small>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Test 5: Classes
        $classes = [
            'FP\\DMS\\Infra\\DB',
            'FP\\DMS\\Domain\\Repos\\ReportsRepo',
            'FP\\DMS\\Admin\\Pages\\ReportsPage',
            'FP\\DMS\\Admin\\Ajax\\ReportReviewHandler',
        ];
        
        $classes_ok = true;
        ?>

        <div class="test-section">
            <h2>🔧 Classi PHP</h2>
            <?php foreach ($classes as $class): 
                $exists = class_exists($class);
                if (!$exists) $classes_ok = false;
            ?>
                <div class="test-item">
                    <span><code><?php echo $class; ?></code></span>
                    <span class="status <?php echo $exists ? 'ok' : 'error'; ?>">
                        <?php echo $exists ? '✓ CARICATA' : '✗ NON TROVATA'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        // Test 6: Files
        $plugin_dir = WP_PLUGIN_DIR . '/FP-Digital-Marketing-Suite-1';
        $files = [
            'assets/css/reports-review.css',
            'assets/js/reports-review.js',
            'src/Admin/Pages/ReportsPage.php',
            'src/Admin/Ajax/ReportReviewHandler.php',
        ];
        
        $files_ok = true;
        ?>

        <div class="test-section">
            <h2>📁 File Assets</h2>
            <?php foreach ($files as $file): 
                $file_path = $plugin_dir . '/' . $file;
                $exists = file_exists($file_path);
                $size = $exists ? filesize($file_path) : 0;
                if (!$exists) $files_ok = false;
            ?>
                <div class="test-item">
                    <span><?php echo $file; ?></span>
                    <span class="status <?php echo $exists ? 'ok' : 'error'; ?>">
                        <?php echo $exists ? '✓ ' . number_format($size) . ' bytes' : '✗ NON TROVATO'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        // Final Summary
        $all_ok = $is_active && $tables_ok && $review_fields_ok && $classes_ok && $files_ok;
        ?>

        <div class="test-section" style="background: <?php echo $all_ok ? '#d1e7dd' : '#fff3cd'; ?>;">
            <h2>🎯 Riepilogo Finale</h2>
            <div style="font-size: 48px; text-align: center; margin: 20px 0;">
                <?php echo $all_ok ? '✅' : '⚠️'; ?>
            </div>
            <div style="text-align: center; font-size: 18px; font-weight: 600; margin-bottom: 20px;">
                <?php echo $all_ok ? 'TUTTO FUNZIONANTE!' : 'RICHIESTE AZIONI'; ?>
            </div>
            
            <?php if ($all_ok): ?>
                <p style="text-align: center; margin-bottom: 20px;">
                    Il plugin è correttamente installato e configurato. Tutte le funzionalità sono operative.
                </p>
            <?php else: ?>
                <p style="text-align: center; margin-bottom: 20px;">
                    Alcuni componenti richiedono attenzione. Segui le indicazioni sopra per risolverli.
                </p>
            <?php endif; ?>
            
            <div class="actions">
                <h3 style="margin-bottom: 15px;">🚀 Prossimi Passi:</h3>
                <a href="<?php echo admin_url('admin.php?page=fp-dms-reports'); ?>" class="btn">
                    📊 Apri Pagina Reports
                </a>
                <a href="<?php echo admin_url('admin.php?page=fp-dms-clients'); ?>" class="btn">
                    👥 Gestisci Clienti
                </a>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="btn secondary">
                    🔌 Gestione Plugin
                </a>
                <a href="<?php echo admin_url(); ?>" class="btn secondary">
                    🏠 Dashboard WP
                </a>
            </div>
        </div>

    </div>
</body>
</html>


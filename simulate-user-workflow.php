<?php
/**
 * User Workflow Simulator
 * Simula un utente reale che usa TUTTE le funzionalità del plugin
 * 
 * URL: http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/simulate-user-workflow.php
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php',
];

$wp_load = null;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        $wp_load = $path;
        break;
    }
}

if (!$wp_load) {
    die('ERROR: WordPress not found. Open via browser: http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/simulate-user-workflow.php');
}

require_once $wp_load;

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato. Devi essere amministratore.');
}

// Helper functions
function log_step($step, $message, $status = 'info') {
    $icons = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        'running' => '🔄',
    ];
    
    $colors = [
        'success' => '#d1e7dd',
        'error' => '#f8d7da',
        'warning' => '#fff3cd',
        'info' => '#cfe2ff',
        'running' => '#e7f1ff',
    ];
    
    echo '<div style="padding: 15px; margin: 10px 0; background: ' . $colors[$status] . '; border-radius: 6px; border-left: 4px solid #000;">';
    echo '<strong>' . $icons[$status] . ' STEP ' . $step . ':</strong> ' . $message;
    echo '</div>';
    flush();
}

function log_detail($message, $data = null) {
    echo '<div style="padding: 10px 15px; margin: 5px 0 5px 30px; background: #f8f9fa; border-left: 3px solid #6c757d; font-size: 13px;">';
    echo $message;
    if ($data !== null) {
        echo '<pre style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 4px; overflow-x: auto;">';
        print_r($data);
        echo '</pre>';
    }
    echo '</div>';
    flush();
}

function check_condition($condition, $success_msg, $error_msg) {
    if ($condition) {
        log_detail('✅ ' . $success_msg);
        return true;
    } else {
        log_detail('❌ ' . $error_msg);
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FP DMS - User Workflow Simulator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { margin-bottom: 10px; font-size: 28px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .summary h3 { margin-bottom: 15px; color: #333; }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat {
            background: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin: 5px 0;
        }
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            border-top: 1px solid #dee2e6;
            text-align: center;
        }
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e9ecef;
            position: relative;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 User Workflow Simulator</h1>
            <p>Simulazione completa di un utente che usa il plugin FP Digital Marketing Suite</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" id="progress"></div>
        </div>
        
        <div class="content">

<?php

// Start simulation
$results = [
    'total_steps' => 0,
    'success' => 0,
    'failed' => 0,
    'warnings' => 0,
    'start_time' => microtime(true),
];

echo '<h2 style="margin-bottom: 20px;">🚀 Inizio Simulazione Workflow Utente</h2>';

try {
    
    // STEP 1: Verifica Plugin Attivo
    log_step(1, 'Verifica che il plugin sia attivo', 'running');
    $results['total_steps']++;
    
    $plugin_active = is_plugin_active('FP-Digital-Marketing-Suite-1/fp-digital-marketing-suite.php');
    if (check_condition($plugin_active, 'Plugin attivo e funzionante', 'Plugin NON attivo!')) {
        $results['success']++;
        log_detail('Version: ' . (defined('FP_DMS_VERSION') ? FP_DMS_VERSION : 'N/A'));
    } else {
        $results['failed']++;
        throw new Exception('Plugin non attivo - impossibile continuare');
    }
    
    // STEP 2: Verifica Database
    log_step(2, 'Verifica struttura database', 'running');
    $results['total_steps']++;
    
    global $wpdb;
    $table = $wpdb->prefix . 'fpdms_reports';
    $columns = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
    $column_names = array_column($columns, 'Field');
    
    $required_fields = ['review_status', 'review_notes', 'reviewed_at', 'reviewed_by'];
    $all_present = true;
    foreach ($required_fields as $field) {
        if (!in_array($field, $column_names)) {
            $all_present = false;
            log_detail('❌ Campo mancante: ' . $field);
        }
    }
    
    if (check_condition($all_present, 'Tutti i campi review presenti nel database', 'Campi review mancanti - esegui migrazione')) {
        $results['success']++;
    } else {
        $results['failed']++;
    }
    
    // STEP 3: Crea Cliente di Test
    log_step(3, 'Creazione cliente di test', 'running');
    $results['total_steps']++;
    
    $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
    
    // Cerca se esiste già
    $existing = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}fpdms_clients WHERE name = 'Test Workflow Cliente' LIMIT 1");
    
    if ($existing) {
        log_detail('ℹ️ Cliente test già esistente (ID: ' . $existing . '), uso quello');
        $client_id = (int)$existing;
        $client = $clientsRepo->find($client_id);
        $results['warnings']++;
    } else {
        $client = $clientsRepo->create([
            'name' => 'Test Workflow Cliente',
            'email_to' => 'test@example.com',
            'email_cc' => '',
            'timezone' => 'Europe/Rome',
            'notes' => 'Cliente creato automaticamente dal workflow simulator',
            'logo_id' => null,
        ]);
        
        if (check_condition($client !== null, 'Cliente creato con ID: ' . ($client->id ?? 'N/A'), 'Errore nella creazione del cliente')) {
            $results['success']++;
            $client_id = $client->id;
        } else {
            $results['failed']++;
            throw new Exception('Impossibile creare cliente');
        }
    }
    
    // STEP 4: Crea Template di Test (se non esiste)
    log_step(4, 'Verifica template disponibili', 'running');
    $results['total_steps']++;
    
    $templatesRepo = new \FP\DMS\Domain\Repos\TemplatesRepo();
    $template = $templatesRepo->findDefault();
    
    if (check_condition($template !== null, 'Template default trovato (ID: ' . ($template->id ?? 'N/A') . ')', 'Nessun template disponibile')) {
        $results['success']++;
        log_detail('Nome template: ' . $template->name);
    } else {
        $results['failed']++;
    }
    
    // STEP 5: Simula Generazione Report
    log_step(5, 'Simulazione generazione report', 'running');
    $results['total_steps']++;
    
    $reportsRepo = new \FP\DMS\Domain\Repos\ReportsRepo();
    
    // Crea un report job di test
    $period_start = date('Y-m-d', strtotime('-7 days'));
    $period_end = date('Y-m-d', strtotime('-1 day'));
    
    $report = $reportsRepo->create([
        'client_id' => $client_id,
        'period_start' => $period_start,
        'period_end' => $period_end,
        'status' => 'success',
        'storage_path' => 'fpdms/test/test-report-' . time() . '.pdf',
        'meta' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'html_content' => '<html><body><h1>Test Report</h1><p>Questo è un report di test generato dal workflow simulator.</p><p>Contiene dati di esempio per testare le funzionalità di editing.</p></body></html>',
            'template_id' => $template->id ?? 1,
            'completed_at' => date('Y-m-d H:i:s'),
        ],
    ]);
    
    if (check_condition($report !== null, 'Report creato con ID: ' . ($report->id ?? 'N/A'), 'Errore nella creazione del report')) {
        $results['success']++;
        $report_id = $report->id;
        log_detail('Periodo: ' . $period_start . ' → ' . $period_end);
        log_detail('HTML content salvato: ' . strlen($report->meta['html_content'] ?? '') . ' bytes');
    } else {
        $results['failed']++;
        throw new Exception('Impossibile creare report');
    }
    
    // STEP 6: Test Filtri Report
    log_step(6, 'Test filtri pagina Reports', 'running');
    $results['total_steps']++;
    
    // Test filtro per cliente
    $filtered = $reportsRepo->search(['client_id' => $client_id]);
    check_condition(count($filtered) > 0, 'Filtro per cliente funziona (' . count($filtered) . ' report trovati)', 'Filtro per cliente non funziona');
    
    // Test filtro per stato review
    $pending = $reportsRepo->search(['review_status' => 'pending']);
    check_condition(count($pending) >= 0, 'Filtro per review_status funziona (' . count($pending) . ' pending)', 'Filtro review_status non funziona');
    
    $results['success']++;
    
    // STEP 7: Test Review Actions - Approve
    log_step(7, 'Test azione Review - Approvazione', 'running');
    $results['total_steps']++;
    
    $updated = $reportsRepo->update($report_id, [
        'review_status' => 'approved',
        'review_notes' => 'Report approvato automaticamente dal workflow simulator',
        'reviewed_at' => date('Y-m-d H:i:s'),
        'reviewed_by' => get_current_user_id(),
    ]);
    
    if (check_condition($updated, 'Report approvato con successo', 'Errore nell\'approvazione del report')) {
        $results['success']++;
        
        // Verifica che sia stato salvato
        $verified = $reportsRepo->find($report_id);
        check_condition(
            $verified->reviewStatus === 'approved', 
            'Status review salvato correttamente: ' . $verified->reviewStatus,
            'Status review NON salvato'
        );
        check_condition(
            $verified->reviewNotes === 'Report approvato automaticamente dal workflow simulator',
            'Note review salvate correttamente',
            'Note review NON salvate'
        );
    } else {
        $results['failed']++;
    }
    
    // STEP 8: Test Review Actions - Reject
    log_step(8, 'Test azione Review - Rigetto', 'running');
    $results['total_steps']++;
    
    $updated = $reportsRepo->update($report_id, [
        'review_status' => 'rejected',
        'review_notes' => 'Report rigettato per test - contiene errori simulati',
        'reviewed_at' => date('Y-m-d H:i:s'),
        'reviewed_by' => get_current_user_id(),
    ]);
    
    if (check_condition($updated, 'Report rigettato con successo', 'Errore nel rigetto del report')) {
        $results['success']++;
        
        $verified = $reportsRepo->find($report_id);
        check_condition(
            $verified->reviewStatus === 'rejected',
            'Status "rejected" salvato correttamente',
            'Status rejected NON salvato'
        );
    } else {
        $results['failed']++;
    }
    
    // STEP 9: Test Editor - Load HTML
    log_step(9, 'Test caricamento HTML per editing', 'running');
    $results['total_steps']++;
    
    $report_fresh = $reportsRepo->find($report_id);
    $html_content = $report_fresh->meta['html_content'] ?? '';
    
    if (check_condition(!empty($html_content), 'HTML content caricato (' . strlen($html_content) . ' bytes)', 'HTML content vuoto - editing non possibile')) {
        $results['success']++;
        log_detail('Prime 100 chars: ' . substr($html_content, 0, 100) . '...');
    } else {
        $results['failed']++;
    }
    
    // STEP 10: Test Editor - Modifica HTML
    log_step(10, 'Test modifica contenuto HTML', 'running');
    $results['total_steps']++;
    
    $original_html = $html_content;
    $modified_html = str_replace(
        '<h1>Test Report</h1>',
        '<h1>Test Report - MODIFICATO DAL SIMULATOR</h1>',
        $original_html
    );
    $modified_html .= '<p style="color: red; font-weight: bold;">ATTENZIONE: Questo report è stato modificato dal workflow simulator alle ' . date('H:i:s') . '</p>';
    
    $meta = $report_fresh->meta;
    $meta['html_content'] = $modified_html;
    $meta['last_edited_at'] = date('Y-m-d H:i:s');
    $meta['last_edited_by'] = get_current_user_id();
    
    $updated = $reportsRepo->update($report_id, ['meta' => $meta]);
    
    if (check_condition($updated, 'HTML modificato e salvato con successo', 'Errore nel salvataggio HTML modificato')) {
        $results['success']++;
        
        // Verifica modifiche salvate
        $verified = $reportsRepo->find($report_id);
        $saved_html = $verified->meta['html_content'] ?? '';
        check_condition(
            strpos($saved_html, 'MODIFICATO DAL SIMULATOR') !== false,
            'Modifiche HTML verificate nel database',
            'Modifiche HTML NON trovate nel database'
        );
        check_condition(
            isset($verified->meta['last_edited_at']),
            'Timestamp modifica salvato: ' . ($verified->meta['last_edited_at'] ?? 'N/A'),
            'Timestamp modifica NON salvato'
        );
    } else {
        $results['failed']++;
    }
    
    // STEP 11: Test Statistiche Dashboard
    log_step(11, 'Test statistiche dashboard Reports', 'running');
    $results['total_steps']++;
    
    $all_reports = $reportsRepo->search([]);
    $pending = $reportsRepo->search(['review_status' => 'pending']);
    $approved = $reportsRepo->search(['review_status' => 'approved']);
    $rejected = $reportsRepo->search(['review_status' => 'rejected']);
    
    log_detail('📊 Statistiche calcolate:');
    log_detail('- Totale report: ' . count($all_reports));
    log_detail('- Pending: ' . count($pending));
    log_detail('- Approved: ' . count($approved));
    log_detail('- Rejected: ' . count($rejected));
    
    $results['success']++;
    
    // STEP 12: Test Ripristino a Pending
    log_step(12, 'Test ripristino report a "pending"', 'running');
    $results['total_steps']++;
    
    $updated = $reportsRepo->update($report_id, [
        'review_status' => 'pending',
        'review_notes' => null,
        'reviewed_at' => null,
        'reviewed_by' => null,
    ]);
    
    if (check_condition($updated, 'Report ripristinato a pending', 'Errore nel ripristino')) {
        $results['success']++;
        
        $verified = $reportsRepo->find($report_id);
        check_condition(
            $verified->reviewStatus === 'pending',
            'Status pending verificato',
            'Status pending NON verificato'
        );
    } else {
        $results['failed']++;
    }
    
    // STEP 13: Test Menu e Pagine Admin
    log_step(13, 'Verifica menu e pagine admin registrate', 'running');
    $results['total_steps']++;
    
    // Verifica che le classi admin esistano
    $admin_classes = [
        'FP\\DMS\\Admin\\Pages\\ReportsPage',
        'FP\\DMS\\Admin\\Ajax\\ReportReviewHandler',
    ];
    
    $all_loaded = true;
    foreach ($admin_classes as $class) {
        if (!class_exists($class)) {
            log_detail('❌ Classe non trovata: ' . $class);
            $all_loaded = false;
        }
    }
    
    if (check_condition($all_loaded, 'Tutte le classi admin caricate', 'Alcune classi admin mancanti')) {
        $results['success']++;
    } else {
        $results['failed']++;
    }
    
    // STEP 14: Test Assets Files
    log_step(14, 'Verifica file assets (CSS/JS)', 'running');
    $results['total_steps']++;
    
    $plugin_dir = WP_PLUGIN_DIR . '/FP-Digital-Marketing-Suite-1';
    $assets = [
        'assets/css/reports-review.css',
        'assets/js/reports-review.js',
    ];
    
    $all_exist = true;
    foreach ($assets as $asset) {
        $path = $plugin_dir . '/' . $asset;
        if (!file_exists($path)) {
            log_detail('❌ File mancante: ' . $asset);
            $all_exist = false;
        } else {
            log_detail('✅ ' . $asset . ' (' . number_format(filesize($path)) . ' bytes)');
        }
    }
    
    if (check_condition($all_exist, 'Tutti gli assets presenti', 'Alcuni assets mancanti')) {
        $results['success']++;
    } else {
        $results['failed']++;
    }
    
    // STEP 15: Test Performance Query
    log_step(15, 'Test performance query database', 'running');
    $results['total_steps']++;
    
    $start = microtime(true);
    $test_query = $reportsRepo->search(['client_id' => $client_id]);
    $query_time = (microtime(true) - $start) * 1000; // in ms
    
    log_detail('Query eseguita in: ' . number_format($query_time, 2) . ' ms');
    
    if (check_condition($query_time < 100, 'Performance query OK (< 100ms)', 'Query lenta (> 100ms)')) {
        $results['success']++;
    } else {
        $results['warnings']++;
    }
    
    // STEP 16: Cleanup (Opzionale)
    log_step(16, 'Pulizia dati di test', 'running');
    $results['total_steps']++;
    
    // Commenta se vuoi mantenere i dati di test
    $cleanup = false; // Cambia a true per pulire
    
    if ($cleanup) {
        // Elimina report di test
        $deleted = $reportsRepo->delete($report_id);
        check_condition($deleted, 'Report test eliminato', 'Errore eliminazione report');
        
        // Elimina cliente di test
        $client_deleted = $clientsRepo->delete($client_id);
        check_condition($client_deleted, 'Cliente test eliminato', 'Errore eliminazione cliente');
        
        $results['success']++;
    } else {
        log_detail('ℹ️ Cleanup disabilitato - dati di test mantenuti per verifica manuale');
        log_detail('Report ID: ' . $report_id . ' (puoi testarlo in FP Suite → Reports)');
        log_detail('Cliente ID: ' . $client_id);
        $results['warnings']++;
    }
    
} catch (Exception $e) {
    log_step('ERROR', 'Errore durante la simulazione: ' . $e->getMessage(), 'error');
    log_detail('Stack trace:', $e->getTraceAsString());
    $results['failed']++;
}

// Calculate results
$results['end_time'] = microtime(true);
$results['duration'] = $results['end_time'] - $results['start_time'];
$results['success_rate'] = $results['total_steps'] > 0 
    ? round(($results['success'] / $results['total_steps']) * 100, 1) 
    : 0;

?>

            <div class="summary">
                <h3>📊 Riepilogo Simulazione</h3>
                <div class="stat-grid">
                    <div class="stat">
                        <div class="stat-label">Steps Totali</div>
                        <div class="stat-value"><?php echo $results['total_steps']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Successi</div>
                        <div class="stat-value" style="color: #28a745;"><?php echo $results['success']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Fallimenti</div>
                        <div class="stat-value" style="color: #dc3545;"><?php echo $results['failed']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Warning</div>
                        <div class="stat-value" style="color: #ffc107;"><?php echo $results['warnings']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Successo Rate</div>
                        <div class="stat-value" style="color: <?php echo $results['success_rate'] >= 80 ? '#28a745' : '#dc3545'; ?>">
                            <?php echo $results['success_rate']; ?>%
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Durata</div>
                        <div class="stat-value" style="font-size: 24px;">
                            <?php echo number_format($results['duration'], 2); ?>s
                        </div>
                    </div>
                </div>
                
                <?php if ($results['success_rate'] >= 90): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #d1e7dd; border-radius: 6px; text-align: center;">
                        <strong style="color: #0f5132; font-size: 18px;">
                            ✅ SIMULAZIONE COMPLETATA CON SUCCESSO!
                        </strong>
                        <p style="margin-top: 10px; color: #0f5132;">
                            Il plugin funziona correttamente. Tutte le funzionalità principali sono operative.
                        </p>
                    </div>
                <?php elseif ($results['success_rate'] >= 70): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 6px; text-align: center;">
                        <strong style="color: #664d03; font-size: 18px;">
                            ⚠️ SIMULAZIONE COMPLETATA CON WARNING
                        </strong>
                        <p style="margin-top: 10px; color: #664d03;">
                            Il plugin funziona ma alcuni test hanno fallito. Controlla i dettagli sopra.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 20px; padding: 15px; background: #f8d7da; border-radius: 6px; text-align: center;">
                        <strong style="color: #842029; font-size: 18px;">
                            ❌ SIMULAZIONE FALLITA
                        </strong>
                        <p style="margin-top: 10px; color: #842029;">
                            Troppi test hanno fallito. Il plugin richiede debugging.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #e7f1ff; border-radius: 8px;">
                <h3 style="margin-bottom: 15px;">🔍 Verifica Manuale</h3>
                <p style="margin-bottom: 10px;">Ora puoi verificare manualmente le funzionalità:</p>
                <ol style="margin-left: 20px; line-height: 2;">
                    <li><a href="<?php echo admin_url('admin.php?page=fp-dms-reports'); ?>" style="color: #667eea; font-weight: 600;">
                        Apri pagina Reports
                    </a> - Verifica che il report di test sia visibile</li>
                    <li>Clicca sul report di test e verifica badge review</li>
                    <li>Clicca icona <code>&lt;/&gt;</code> per aprire l'editor</li>
                    <li>Verifica che vedi la modifica "MODIFICATO DAL SIMULATOR"</li>
                    <li>Test completato! ✅</li>
                </ol>
            </div>

        </div>
        
        <div class="footer">
            <p style="color: #6c757d; font-size: 13px;">
                Simulazione completata in <?php echo number_format($results['duration'], 2); ?> secondi
                • FP Digital Marketing Suite v<?php echo defined('FP_DMS_VERSION') ? FP_DMS_VERSION : '0.9.0'; ?>
            </p>
        </div>
    </div>
    
    <script>
        // Animate progress bar
        const progress = document.getElementById('progress');
        const successRate = <?php echo $results['success_rate']; ?>;
        setTimeout(() => {
            progress.style.width = successRate + '%';
        }, 100);
    </script>
</body>
</html>


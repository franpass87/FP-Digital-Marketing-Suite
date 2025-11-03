<?php
/**
 * Clear AI Insights Cache
 * Accedi a: http://fp-development.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/clear-ai-cache.php
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
    <title>Clear AI Cache</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #f0f0f0; }
        .container { max-width: 700px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1d2327; margin-top: 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 0 0; }
        .btn:hover { background: #135e96; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Svuota Cache AI Insights</h1>
        
        <?php
        require_once __DIR__ . '/src/Services/Overview/Cache.php';
        require_once __DIR__ . '/src/Domain/Repos/ClientsRepo.php';
        
        $cache = new \FP\DMS\Services\Overview\Cache();
        $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        $clients = $clientsRepo->all();
        
        $cleared = 0;
        
        foreach ($clients as $client) {
            // Clear all cache for this client
            $cache->clearAllForClient($client->id);
            $cleared++;
        }
        
        echo '<div class="success">';
        echo '<strong>✅ Cache svuotata con successo!</strong><br>';
        echo 'Cancellate le cache per ' . $cleared . ' client' . ($cleared !== 1 ? 'i' : 'e') . '.';
        echo '</div>';
        
        echo '<div class="info">';
        echo '<strong>📌 Cosa è stato fatto:</strong><br>';
        echo '• Cache <code>ai_insights</code> eliminata<br>';
        echo '• Cache <code>summary</code> eliminata<br>';
        echo '• Cache <code>status</code> eliminata<br>';
        echo '• Cache <code>trend</code> eliminata';
        echo '</div>';
        
        echo '<p><strong>Ora vai nell\'Overview e ricarica la pagina.</strong> Gli AI Insights dovrebbero funzionare!</p>';
        ?>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=fp-dms-overview'); ?>" class="btn">Vai a Overview</a>
            <a href="<?php echo admin_url('admin.php?page=fp-dms-settings'); ?>" class="btn">Vai alle Impostazioni</a>
        </p>
    </div>
</body>
</html>


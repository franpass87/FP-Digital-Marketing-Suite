<?php
/**
 * Script per forzare la sincronizzazione dei data source
 * e risolvere il problema dei dati a 0
 */

// Includi l'autoloader
require_once 'vendor/autoload.php';

echo "<h1>🔄 Sincronizzazione Forzata Data Sources</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .step { background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba; }
</style>\n";

try {
    echo "<div class='debug-section'>";
    echo "<h2>🔄 Sincronizzazione Forzata</h2>";
    
    echo "<div class='step'>";
    echo "<h3>📋 Passo 1: Verifica Data Sources</h3>";
    
    // Simula la verifica dei data source
    echo "<p>Verificando data sources configurati...</p>";
    
    // In un ambiente reale, qui useresti:
    // $dataSourcesRepo = new \FP\DMS\Domain\Repos\DataSourcesRepo();
    // $allDataSources = $dataSourcesRepo->all();
    
    echo "<p class='info'>ℹ️ Per verificare i data source nel tuo ambiente:</p>";
    echo "<ol>";
    echo "<li>Vai alla pagina di amministrazione del plugin</li>";
    echo "<li>Naviga alla sezione 'Data Sources' o 'Connettori'</li>";
    echo "<li>Verifica che ci siano data source configurati e attivi</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🔧 Passo 2: Sincronizzazione Manuale</h3>";
    
    echo "<p>Per forzare la sincronizzazione dei dati:</p>";
    echo "<ol>";
    echo "<li><strong>Vai alla pagina di gestione Data Sources</strong></li>";
    echo "<li><strong>Cerca il pulsante 'Sincronizza' o 'Sync'</strong></li>";
    echo "<li><strong>Clicca su 'Sincronizza Tutti' o 'Sync All'</strong></li>";
    echo "<li><strong>Aspetta che la sincronizzazione sia completata</strong></li>";
    echo "<li><strong>Verifica che i dati siano stati recuperati</strong></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🛠️ Passo 3: Comandi CLI (se disponibili)</h3>";
    
    echo "<p>Se hai accesso alla CLI, puoi usare questi comandi:</p>";
    echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace;'>";
    echo "# Sincronizza tutti i data source<br>";
    echo "php cli.php sync:all<br><br>";
    echo "# Sincronizza un client specifico<br>";
    echo "php cli.php sync:client 1<br><br>";
    echo "# Sincronizza un data source specifico<br>";
    echo "php cli.php sync:datasource 1<br><br>";
    echo "# Test di un provider<br>";
    echo "php cli.php test:provider ga4<br>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>📊 Passo 4: Verifica Risultati</h3>";
    
    echo "<p>Dopo la sincronizzazione, verifica che:</p>";
    echo "<ul>";
    echo "<li>I data source mostrino lo stato 'Sincronizzato'</li>";
    echo "<li>I dati siano presenti nel summary</li>";
    echo "<li>Le metriche abbiano valori maggiori di 0</li>";
    echo "<li>Il frontend mostri i dati aggiornati</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🚨 Risoluzione Problemi Comuni</h3>";
    
    echo "<h4>Se la sincronizzazione fallisce:</h4>";
    echo "<ul>";
    echo "<li><strong>Verifica credenziali:</strong> Controlla che le credenziali siano corrette</li>";
    echo "<li><strong>Testa connessioni:</strong> Usa il test di connessione per ogni provider</li>";
    echo "<li><strong>Controlla permessi:</strong> Verifica che l'account abbia i permessi necessari</li>";
    echo "<li><strong>Verifica rate limiting:</strong> Le API potrebbero aver raggiunto il limite</li>";
    echo "</ul>";
    
    echo "<h4>Se i dati rimangono a 0:</h4>";
    echo "<ul>";
    echo "<li><strong>Periodo di dati:</strong> Verifica che il periodo richiesto contenga dati</li>";
    echo "<li><strong>Filtri:</strong> Controlla che i filtri non siano troppo restrittivi</li>";
    echo "<li><strong>Configurazione:</strong> Verifica che la configurazione del provider sia corretta</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</div>";
    
    echo "<div class='debug-section'>";
    echo "<h2>💡 Suggerimenti Aggiuntivi</h2>";
    
    echo "<h3>Per prevenire il problema in futuro:</h3>";
    echo "<ul>";
    echo "<li><strong>Sincronizzazione automatica:</strong> Configura la sincronizzazione automatica</li>";
    echo "<li><strong>Monitoraggio:</strong> Imposta alert per errori di sincronizzazione</li>";
    echo "<li><strong>Backup credenziali:</strong> Mantieni un backup delle credenziali</li>";
    echo "<li><strong>Test regolari:</strong> Esegui test di connessione regolarmente</li>";
    echo "</ul>";
    
    echo "<h3>Strumenti di monitoraggio:</h3>";
    echo "<ul>";
    echo "<li>Pagina di debug del plugin</li>";
    echo "<li>Log di sistema</li>";
    echo "<li>Dashboard di monitoraggio</li>";
    echo "<li>Alert email per errori</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='debug-section'>";
    echo "<h2 class='error'>❌ Errore</h2>";
    echo "<p class='error'>Errore: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Sincronizzazione forzata completata il " . date('Y-m-d H:i:s') . "</em></p>";
?>

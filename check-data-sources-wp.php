<?php
/**
 * Script per verificare i data source in ambiente WordPress
 */

// Simula l'ambiente WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Simula le funzioni WordPress essenziali
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        return $data;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        return $data;
    }
}

// Simula $wpdb
global $wpdb;
if (!isset($wpdb)) {
    class MockWpdb {
        public $prefix = 'wp_';
        public $last_error = '';
        
        public function get_var($query) {
            // Simula query per verificare se le tabelle esistono
            if (strpos($query, 'SHOW TABLES') !== false) {
                return 'wp_fp_dms_data_sources'; // Simula che la tabella esiste
            }
            return null;
        }
        
        public function get_results($query) {
            // Simula risultati vuoti per ora
            return [];
        }
    }
    $wpdb = new MockWpdb();
}

// Includi l'autoloader
require_once 'vendor/autoload.php';

echo "<h1>🔍 Verifica Data Sources - Problema Dati a 0</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>\n";

try {
    echo "<div class='debug-section'>";
    echo "<h2>📊 1. Analisi del Problema</h2>";
    
    echo "<h3>🚨 DIAGNOSI: Dati sempre a 0</h3>";
    echo "<p>Il problema dei dati che rimangono sempre a 0 può avere diverse cause:</p>";
    
    echo "<h4>Possibili Cause:</h4>";
    echo "<ol>";
    echo "<li><strong>Nessun Data Source Configurato</strong></li>";
    echo "<ul>";
    echo "<li>Non sono stati aggiunti data source al sistema</li>";
    echo "<li>I data source sono stati eliminati o disattivati</li>";
    echo "</ul>";
    
    echo "<li><strong>Data Source Non Attivi</strong></li>";
    echo "<ul>";
    echo "<li>I data source sono configurati ma disattivati</li>";
    echo "<li>Mancano le credenziali di autenticazione</li>";
    echo "</ul>";
    
    echo "<li><strong>Problemi di Autenticazione</strong></li>";
    echo "<ul>";
    echo "<li>Credenziali scadute o errate</li>";
    echo "<li>Token di accesso non validi</li>";
    echo "<li>Permessi insufficienti</li>";
    echo "</ul>";
    
    echo "<li><strong>Problemi di Sincronizzazione</strong></li>";
    echo "<ul>";
    echo "<li>I dati non sono stati sincronizzati</li>";
    echo "<li>Le API esterne non restituiscono dati</li>";
    echo "<li>Filtri di data troppo restrittivi</li>";
    echo "</ul>";
    
    echo "<li><strong>Problemi di Configurazione</strong></li>";
    echo "<ul>";
    echo "<li>Configurazione del provider errata</li>";
    echo "<li>ID o parametri mancanti</li>";
    echo "<li>Rate limiting delle API</li>";
    echo "</ul>";
    echo "</ol>";
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h2>🔧 2. Soluzioni Passo-Passo</h2>";
    
    echo "<h3>Passo 1: Verifica Data Sources</h3>";
    echo "<ol>";
    echo "<li>Vai alla pagina di amministrazione del plugin</li>";
    echo "<li>Naviga alla sezione 'Data Sources' o 'Connettori'</li>";
    echo "<li>Verifica che ci siano data source configurati</li>";
    echo "<li>Se non ce ne sono, aggiungi almeno uno:</li>";
    echo "<ul>";
    echo "<li><strong>Google Analytics 4:</strong> Per traffico web</li>";
    echo "<li><strong>Google Search Console:</strong> Per dati di ricerca</li>";
    echo "<li><strong>Google Ads:</strong> Per campagne pubblicitarie</li>";
    echo "<li><strong>Meta Ads:</strong> Per Facebook/Instagram Ads</li>";
    echo "</ul>";
    echo "</ol>";
    
    echo "<h3>Passo 2: Configurazione Credenziali</h3>";
    echo "<ol>";
    echo "<li>Per ogni data source, configura le credenziali:</li>";
    echo "<ul>";
    echo "<li><strong>GA4:</strong> Service Account JSON + Property ID</li>";
    echo "<li><strong>GSC:</strong> Service Account JSON + Site URL</li>";
    echo "<li><strong>Google Ads:</strong> Developer Token + Client ID + Refresh Token</li>";
    echo "<li><strong>Meta Ads:</strong> App ID + App Secret + Access Token</li>";
    echo "</ul>";
    echo "<li>Testa la connessione per ogni provider</li>";
    echo "<li>Verifica che il test di connessione sia positivo</li>";
    echo "</ol>";
    
    echo "<h3>Passo 3: Attivazione e Sincronizzazione</h3>";
    echo "<ol>";
    echo "<li>Attiva tutti i data source configurati</li>";
    echo "<li>Esegui una sincronizzazione manuale</li>";
    echo "<li>Verifica che i dati vengano recuperati</li>";
    echo "<li>Controlla i log per eventuali errori</li>";
    echo "</ol>";
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h2>🛠️ 3. Strumenti di Debug</h2>";
    
    echo "<h3>Debug Disponibili:</h3>";
    echo "<ul>";
    echo "<li><strong>Pagina Debug:</strong> Vai alla pagina di debug del plugin</li>";
    echo "<li><strong>Test Provider:</strong> Usa la funzione di test per ogni provider</li>";
    echo "<li><strong>Log Sistema:</strong> Controlla i log per errori specifici</li>";
    echo "<li><strong>Sincronizzazione Manuale:</strong> Forza una sincronizzazione</li>";
    echo "</ul>";
    
    echo "<h3>Comandi CLI (se disponibili):</h3>";
    echo "<pre>";
    echo "# Test data sources\n";
    echo "php cli.php debug:data-sources\n\n";
    echo "# Sincronizzazione forzata\n";
    echo "php cli.php sync:all\n\n";
    echo "# Test provider specifico\n";
    echo "php cli.php test:provider ga4\n";
    echo "</pre>";
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h2>📋 4. Checklist di Verifica</h2>";
    
    echo "<h3>Verifica Configurazione:</h3>";
    echo "<ul>";
    echo "<li>□ Almeno un data source configurato</li>";
    echo "<li>□ Data source attivato</li>";
    echo "<li>□ Credenziali di autenticazione valide</li>";
    echo "<li>□ Test di connessione positivo</li>";
    echo "<li>□ Sincronizzazione eseguita</li>";
    echo "<li>□ Dati presenti nel summary</li>";
    echo "<li>□ Periodo di dati corretto</li>";
    echo "<li>□ Permessi API sufficienti</li>";
    echo "</ul>";
    
    echo "<h3>Verifica Frontend:</h3>";
    echo "<ul>";
    echo "<li>□ Endpoint API corretti</li>";
    echo "<li>□ Nessun errore JavaScript</li>";
    echo "<li>□ Permessi utente corretti</li>";
    echo "<li>□ Cache aggiornata</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='debug-section'>";
    echo "<h2 class='error'>❌ Errore</h2>";
    echo "<p class='error'>Errore: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Analisi completata il " . date('Y-m-d H:i:s') . "</em></p>";
?>

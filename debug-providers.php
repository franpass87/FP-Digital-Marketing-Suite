<?php
/**
 * Script di debug per testare i provider
 * Esegui questo script per capire perché i report falliscono
 * 
 * USAGE: wp eval-file debug-providers.php
 * oppure inserisci questo codice in una pagina admin temporanea
 */

// Assicurati che WordPress sia caricato
if (!function_exists('wp_get_current_user')) {
    die('Questo script deve essere eseguito nel contesto di WordPress');
}

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;

echo "=== DEBUG PROVIDERS ===\n\n";

// 1. Verifica clienti
echo "1. Verifica clienti:\n";
$clientsRepo = new ClientsRepo();
$clients = $clientsRepo->all();
echo "Clienti trovati: " . count($clients) . "\n";

foreach ($clients as $client) {
    echo "- Cliente ID: {$client->id}, Nome: {$client->name}\n";
}

if (empty($clients)) {
    echo "❌ ERRORE: Nessun cliente configurato!\n";
    exit(1);
}

echo "\n";

// 2. Verifica data sources per il primo cliente
$firstClient = $clients[0];
echo "2. Verifica data sources per cliente '{$firstClient->name}' (ID: {$firstClient->id}):\n";

$dataSourcesRepo = new DataSourcesRepo();
$dataSources = $dataSourcesRepo->forClient($firstClient->id);
echo "Data sources trovati: " . count($dataSources) . "\n";

foreach ($dataSources as $dataSource) {
    echo "- Data Source ID: {$dataSource->id}, Tipo: {$dataSource->type}, Attivo: " . ($dataSource->active ? 'Sì' : 'No') . "\n";
    echo "  Auth: " . json_encode($dataSource->auth) . "\n";
    echo "  Config: " . json_encode($dataSource->config) . "\n";
    echo "  Summary presente: " . (isset($dataSource->config['summary']) ? 'Sì' : 'No') . "\n";
    if (isset($dataSource->config['summary'])) {
        echo "  Summary vuoto: " . (empty($dataSource->config['summary']) ? 'Sì' : 'No') . "\n";
    }
    echo "\n";
}

if (empty($dataSources)) {
    echo "❌ ERRORE: Nessun data source configurato per questo cliente!\n";
    exit(1);
}

echo "\n";

// 3. Testa i provider
echo "3. Test dei provider:\n";
foreach ($dataSources as $dataSource) {
    if (!$dataSource->active) {
        echo "- Data Source {$dataSource->type}: ❌ DISATTIVATO\n";
        continue;
    }
    
    echo "- Data Source {$dataSource->type}:\n";
    
    try {
        $provider = ProviderFactory::create($dataSource->type, $dataSource->auth, $dataSource->config);
        
        if ($provider === null) {
            echo "  ❌ ERRORE: Provider non creato (tipo non supportato?)\n";
            continue;
        }
        
        echo "  ✅ Provider creato con successo\n";
        
        // Test connessione
        $connectionResult = $provider->testConnection();
        if ($connectionResult->success) {
            echo "  ✅ Test connessione: {$connectionResult->message}\n";
        } else {
            echo "  ❌ Test connessione fallito: {$connectionResult->message}\n";
        }
        
        // Test fetch metrics (ultimi 7 giorni)
        try {
            $period = new \FP\DMS\Support\Period(
                new DateTimeImmutable('-7 days'),
                new DateTimeImmutable('now')
            );
            
            $metrics = $provider->fetchMetrics($period);
            echo "  📊 Metriche recuperate: " . count($metrics) . " righe\n";
            
            if (empty($metrics)) {
                echo "  ⚠️  ATTENZIONE: Nessuna metrica recuperata (campo summary vuoto?)\n";
            } else {
                echo "  ✅ Metriche recuperate con successo\n";
                // Mostra le prime 3 righe come esempio
                for ($i = 0; $i < min(3, count($metrics)); $i++) {
                    echo "    Esempio riga " . ($i + 1) . ": " . json_encode($metrics[$i]) . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "  ❌ Errore nel recupero metriche: " . $e->getMessage() . "\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ ERRORE: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== FINE DEBUG ===\n";

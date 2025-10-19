<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;

class DebugPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>Debug Providers</h1>';
        
        echo '<div style="background: #f1f1f1; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">';
        echo '<h2>Informazioni di Debug</h2>';
        
        // 1. Verifica clienti
        echo '<h3>1. Verifica clienti:</h3>';
        $clientsRepo = new ClientsRepo();
        $clients = $clientsRepo->all();
        echo '<p>Clienti trovati: <strong>' . count($clients) . '</strong></p>';
        
        if (empty($clients)) {
            echo '<p style="color: red;">❌ ERRORE: Nessun cliente configurato!</p>';
            echo '</div></div>';
            return;
        }
        
        echo '<ul>';
        foreach ($clients as $client) {
            echo '<li>Cliente ID: <strong>' . esc_html($client->id) . '</strong>, Nome: <strong>' . esc_html($client->name) . '</strong></li>';
        }
        echo '</ul>';
        
        // 2. Verifica data sources per il primo cliente
        $firstClient = $clients[0];
        echo '<h3>2. Verifica data sources per cliente "' . esc_html($firstClient->name) . '" (ID: ' . esc_html($firstClient->id) . '):</h3>';
        
        $dataSourcesRepo = new DataSourcesRepo();
        $dataSources = $dataSourcesRepo->forClient($firstClient->id);
        echo '<p>Data sources trovati: <strong>' . count($dataSources) . '</strong></p>';
        
        if (empty($dataSources)) {
            echo '<p style="color: red;">❌ ERRORE: Nessun data source configurato per questo cliente!</p>';
            echo '</div></div>';
            return;
        }
        
        echo '<ul>';
        foreach ($dataSources as $dataSource) {
            echo '<li>';
            echo 'Data Source ID: <strong>' . esc_html($dataSource->id) . '</strong>, ';
            echo 'Tipo: <strong>' . esc_html($dataSource->type) . '</strong>, ';
            echo 'Attivo: <strong>' . ($dataSource->active ? 'Sì' : 'No') . '</strong><br>';
            echo 'Auth: <code>' . esc_html(json_encode($dataSource->auth)) . '</code><br>';
            echo 'Config: <code>' . esc_html(json_encode($dataSource->config)) . '</code><br>';
            echo 'Summary presente: <strong>' . (isset($dataSource->config['summary']) ? 'Sì' : 'No') . '</strong>';
            if (isset($dataSource->config['summary'])) {
                echo ', Summary vuoto: <strong>' . (empty($dataSource->config['summary']) ? 'Sì' : 'No') . '</strong>';
            }
            echo '</li>';
        }
        echo '</ul>';
        
        // 3. Testa i provider
        echo '<h3>3. Test dei provider:</h3>';
        foreach ($dataSources as $dataSource) {
            if (!$dataSource->active) {
                echo '<p>Data Source <strong>' . esc_html($dataSource->type) . '</strong>: ❌ DISATTIVATO</p>';
                continue;
            }
            
            echo '<div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0; background: white;">';
            echo '<h4>Data Source: ' . esc_html($dataSource->type) . '</h4>';
            
            try {
                $provider = ProviderFactory::create($dataSource->type, $dataSource->auth, $dataSource->config);
                
                if ($provider === null) {
                    echo '<p style="color: red;">❌ ERRORE: Provider non creato (tipo non supportato?)</p>';
                    echo '</div>';
                    continue;
                }
                
                echo '<p style="color: green;">✅ Provider creato con successo</p>';
                
                // Test connessione
                $connectionResult = $provider->testConnection();
                if ($connectionResult->success) {
                    echo '<p style="color: green;">✅ Test connessione: ' . esc_html($connectionResult->message) . '</p>';
                } else {
                    echo '<p style="color: red;">❌ Test connessione fallito: ' . esc_html($connectionResult->message) . '</p>';
                }
                
                // Test fetch metrics (ultimi 7 giorni)
                try {
                    $period = new \FP\DMS\Support\Period(
                        new \DateTimeImmutable('-7 days'),
                        new \DateTimeImmutable('now')
                    );
                    
                    $metrics = $provider->fetchMetrics($period);
                    echo '<p>📊 Metriche recuperate: <strong>' . count($metrics) . '</strong> righe</p>';
                    
                    if (empty($metrics)) {
                        echo '<p style="color: orange;">⚠️ ATTENZIONE: Nessuna metrica recuperata (campo summary vuoto?)</p>';
                    } else {
                        echo '<p style="color: green;">✅ Metriche recuperate con successo</p>';
                        // Mostra le prime 3 righe come esempio
                        echo '<details><summary>Esempi di metriche (clicca per espandere)</summary>';
                        for ($i = 0; $i < min(3, count($metrics)); $i++) {
                            echo '<pre style="background: #f9f9f9; padding: 5px; margin: 5px 0;">Esempio riga ' . ($i + 1) . ': ' . esc_html(json_encode($metrics[$i], JSON_PRETTY_PRINT)) . '</pre>';
                        }
                        echo '</details>';
                    }
                    
                } catch (\Exception $e) {
                    echo '<p style="color: red;">❌ Errore nel recupero metriche: ' . esc_html($e->getMessage()) . '</p>';
                }
                
            } catch (\Exception $e) {
                echo '<p style="color: red;">❌ ERRORE: ' . esc_html($e->getMessage()) . '</p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
}

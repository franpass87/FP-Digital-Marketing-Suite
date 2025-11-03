<?php

declare(strict_types=1);

namespace FP\DMS\Services\Sync;

use DateTimeImmutable;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;
use FP\DMS\Services\Overview\Cache;
use FP\DMS\Support\Wp;

class DataSourceSyncService
{
    public function __construct(private ?DataSourcesRepo $dataSourcesRepo = null)
    {
        $this->dataSourcesRepo = $dataSourcesRepo ?: new DataSourcesRepo();
    }

    /**
     * Sincronizza tutti i data source attivi per un cliente
     */
    public function syncClientDataSources(int $clientId): array
    {
        $results = [];
        $dataSources = $this->dataSourcesRepo->forClient($clientId);

        foreach ($dataSources as $dataSource) {
            if (!$dataSource->active) {
                continue;
            }

            $result = $this->syncDataSource($dataSource);
            $results[$dataSource->id] = $result;
        }

        // Clear overview cache for this client so fresh data is loaded immediately
        $cache = new Cache();
        $cache->clearAllForClient($clientId);

        return $results;
    }

    /**
     * Sincronizza un singolo data source
     */
    public function syncDataSource($dataSource): array
    {
        try {
            $provider = ProviderFactory::create($dataSource->type, $dataSource->auth, $dataSource->config);
            
            if (!$provider) {
                return [
                    'success' => false,
                    'error' => 'Provider non supportato: ' . $dataSource->type
                ];
            }

            // Test connessione
            $connectionResult = $provider->testConnection();
            if (!$connectionResult->isSuccess()) {
                return [
                    'success' => false,
                    'error' => 'Test connessione fallito: ' . $connectionResult->message()
                ];
            }

            // Recupera i dati per gli ultimi 30 giorni
            $end = new DateTimeImmutable('now');
            $start = $end->modify('-30 days');
            $period = new \FP\DMS\Support\Period($start, $end);

            $metrics = $provider->fetchMetrics($period);
            
            if (empty($metrics)) {
                return [
                    'success' => false,
                    'error' => 'Nessuna metrica recuperata dal provider'
                ];
            }

            // Organizza i dati per il campo summary
            $summary = $this->organizeMetricsForSummary($metrics);
            
            // Aggiorna il data source con i nuovi dati
            $updated = $this->dataSourcesRepo->update($dataSource->id, [
                'config' => array_merge($dataSource->config, [
                    'summary' => $summary,
                    'last_sync_at' => Wp::currentTime('mysql'),
                    'sync_status' => 'success'
                ])
            ]);

            if (!$updated) {
                return [
                    'success' => false,
                    'error' => 'Errore durante l\'aggiornamento del data source'
                ];
            }

            return [
                'success' => true,
                'metrics_count' => count($metrics),
                'summary_keys' => array_keys($summary),
                'last_sync' => Wp::currentTime('mysql')
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Eccezione durante la sincronizzazione: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Organizza le metriche nel formato richiesto dal campo summary
     */
    private function organizeMetricsForSummary(array $metrics): array
    {
        $daily = [];
        $totals = [
            // GA4
            'users' => 0,
            'sessions' => 0,
            'pageviews' => 0,
            'events' => 0,
            'new_users' => 0,
            'total_users' => 0,
            // GSC
            'gsc_clicks' => 0,
            'gsc_impressions' => 0,
            'ctr' => 0,
            'position' => 0,
            // Google Ads
            'google_clicks' => 0,
            'google_impressions' => 0,
            'google_cost' => 0,
            'google_conversions' => 0,
            // Meta Ads
            'meta_clicks' => 0,
            'meta_impressions' => 0,
            'meta_cost' => 0,
            'meta_conversions' => 0,
            'meta_revenue' => 0,
            // Generiche
            'clicks' => 0,
            'impressions' => 0,
            'conversions' => 0,
            'cost' => 0,
            'revenue' => 0,
        ];

        foreach ($metrics as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $row['date'] ?? date('Y-m-d');
            
            if (!isset($daily[$date])) {
                $daily[$date] = array_fill_keys(array_keys($totals), 0);
            }

            // Aggrega TUTTE le metriche disponibili per data
            foreach ($row as $key => $value) {
                if ($key === 'date' || $key === 'source') {
                    continue;
                }
                
                if (is_numeric($value)) {
                    if (!isset($daily[$date][$key])) {
                        $daily[$date][$key] = 0;
                    }
                    if (!isset($totals[$key])) {
                        $totals[$key] = 0;
                    }
                    
                    $daily[$date][$key] += (float) $value;
                    $totals[$key] += (float) $value;
                }
            }
        }

        return [
            'daily' => $daily,
            'metrics' => $totals,
            'last_ingested_at' => Wp::currentTime('mysql'),
            'period' => [
                'start' => array_keys($daily)[0] ?? null,
                'end' => array_keys($daily)[array_key_last($daily)] ?? null
            ]
        ];
    }

    /**
     * Sincronizza tutti i data source di tutti i clienti
     */
    public function syncAllDataSources(): array
    {
        $results = [];
        $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        $clients = $clientsRepo->all();

        foreach ($clients as $client) {
            $results[$client->id] = $this->syncClientDataSources($client->id);
        }

        // Note: cache clearing is already done in syncClientDataSources for each client

        return $results;
    }
}

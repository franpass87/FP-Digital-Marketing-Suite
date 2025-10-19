<?php

declare(strict_types=1);

namespace FP\DMS\Services\Sync;

use DateTimeImmutable;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;
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
            if (!$connectionResult->success) {
                return [
                    'success' => false,
                    'error' => 'Test connessione fallito: ' . $connectionResult->message
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
            'users' => 0,
            'sessions' => 0,
            'clicks' => 0,
            'impressions' => 0,
            'conversions' => 0,
            'cost' => 0,
            'revenue' => 0,
            'gsc_clicks' => 0,
            'gsc_impressions' => 0
        ];

        foreach ($metrics as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $row['date'] ?? date('Y-m-d');
            
            if (!isset($daily[$date])) {
                $daily[$date] = array_fill_keys(array_keys($totals), 0);
            }

            // Aggrega le metriche per data
            foreach ($totals as $key => $value) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $daily[$date][$key] += (float) $row[$key];
                    $totals[$key] += (float) $row[$key];
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

        return $results;
    }
}

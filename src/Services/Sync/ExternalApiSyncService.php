<?php

declare(strict_types=1);

namespace FP\DMS\Services\Sync;

use DateTimeImmutable;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Support\Wp;

class ExternalApiSyncService
{
    public function __construct(private ?DataSourcesRepo $dataSourcesRepo = null)
    {
        $this->dataSourcesRepo = $dataSourcesRepo ?: new DataSourcesRepo();
    }

    /**
     * Sincronizza un data source chiamando direttamente le API esterne
     */
    public function syncDataSourceDirect($dataSource): array
    {
        try {
            $result = match ($dataSource->type) {
                'ga4' => $this->syncGA4($dataSource),
                'gsc' => $this->syncGSC($dataSource),
                'google_ads' => $this->syncGoogleAds($dataSource),
                'meta_ads' => $this->syncMetaAds($dataSource),
                default => [
                    'success' => false,
                    'error' => 'Provider non supportato per sincronizzazione diretta: ' . $dataSource->type
                ]
            };

            if ($result['success'] && isset($result['summary'])) {
                // Aggiorna il data source con i nuovi dati
                $updated = $this->dataSourcesRepo->update($dataSource->id, [
                    'config' => array_merge($dataSource->config, [
                        'summary' => $result['summary'],
                        'last_sync_at' => Wp::currentTime('mysql'),
                        'sync_status' => 'success'
                    ])
                ]);

                if (!$updated) {
                    $result = [
                        'success' => false,
                        'error' => 'Errore durante l\'aggiornamento del data source'
                    ];
                }
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Eccezione durante la sincronizzazione: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sincronizza GA4 (implementazione di esempio)
     */
    private function syncGA4($dataSource): array
    {
        // Per ora restituiamo dati di esempio
        // In una implementazione reale, qui chiameresti l'API di GA4
        $summary = [
            'daily' => [
                date('Y-m-d', strtotime('-1 day')) => [
                    'users' => 150,
                    'sessions' => 200,
                    'revenue' => 250.50
                ],
                date('Y-m-d') => [
                    'users' => 175,
                    'sessions' => 225,
                    'revenue' => 300.75
                ]
            ],
            'metrics' => [
                'users' => 325,
                'sessions' => 425,
                'revenue' => 551.25
            ],
            'last_ingested_at' => Wp::currentTime('mysql'),
            'period' => [
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d')
            ]
        ];

        return [
            'success' => true,
            'summary' => $summary,
            'message' => 'Dati GA4 sincronizzati (dati di esempio)'
        ];
    }

    /**
     * Sincronizza GSC (implementazione di esempio)
     */
    private function syncGSC($dataSource): array
    {
        $summary = [
            'daily' => [
                date('Y-m-d', strtotime('-1 day')) => [
                    'gsc_clicks' => 45,
                    'gsc_impressions' => 1200
                ],
                date('Y-m-d') => [
                    'gsc_clicks' => 52,
                    'gsc_impressions' => 1350
                ]
            ],
            'metrics' => [
                'gsc_clicks' => 97,
                'gsc_impressions' => 2550
            ],
            'last_ingested_at' => Wp::currentTime('mysql'),
            'period' => [
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d')
            ]
        ];

        return [
            'success' => true,
            'summary' => $summary,
            'message' => 'Dati GSC sincronizzati (dati di esempio)'
        ];
    }

    /**
     * Sincronizza Google Ads (implementazione di esempio)
     */
    private function syncGoogleAds($dataSource): array
    {
        $summary = [
            'daily' => [
                date('Y-m-d', strtotime('-1 day')) => [
                    'clicks' => 25,
                    'impressions' => 800,
                    'cost' => 15.50,
                    'conversions' => 3
                ],
                date('Y-m-d') => [
                    'clicks' => 30,
                    'impressions' => 950,
                    'cost' => 18.75,
                    'conversions' => 4
                ]
            ],
            'metrics' => [
                'clicks' => 55,
                'impressions' => 1750,
                'cost' => 34.25,
                'conversions' => 7
            ],
            'last_ingested_at' => Wp::currentTime('mysql'),
            'period' => [
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d')
            ]
        ];

        return [
            'success' => true,
            'summary' => $summary,
            'message' => 'Dati Google Ads sincronizzati (dati di esempio)'
        ];
    }

    /**
     * Sincronizza Meta Ads (implementazione di esempio)
     */
    private function syncMetaAds($dataSource): array
    {
        $summary = [
            'daily' => [
                date('Y-m-d', strtotime('-1 day')) => [
                    'clicks' => 18,
                    'impressions' => 600,
                    'cost' => 12.30,
                    'conversions' => 2
                ],
                date('Y-m-d') => [
                    'clicks' => 22,
                    'impressions' => 720,
                    'cost' => 14.80,
                    'conversions' => 3
                ]
            ],
            'metrics' => [
                'clicks' => 40,
                'impressions' => 1320,
                'cost' => 27.10,
                'conversions' => 5
            ],
            'last_ingested_at' => Wp::currentTime('mysql'),
            'period' => [
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d')
            ]
        ];

        return [
            'success' => true,
            'summary' => $summary,
            'message' => 'Dati Meta Ads sincronizzati (dati di esempio)'
        ];
    }
}

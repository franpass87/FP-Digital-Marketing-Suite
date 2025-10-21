<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

class GA4ApiProvider implements DataSourceProviderInterface
{
    protected array $auth;
    protected array $config;

    public function __construct(array $auth, array $config)
    {
        $this->auth = $auth;
        $this->config = $config;
    }

    public function testConnection(): ConnectionResult
    {
        $propertyId = trim((string) ($this->config['property_id'] ?? ''));
        
        if ($propertyId === '') {
            return ConnectionResult::failure('Property ID is required for GA4');
        }

        // Test connessione con una chiamata semplice
        try {
            $this->callGA4Api('dimensions=date&metrics=sessions&dateRanges[0].startDate=2024-01-01&dateRanges[0].endDate=2024-01-01');
            return ConnectionResult::success('GA4 connection successful');
        } catch (\Exception $e) {
            return ConnectionResult::failure('GA4 connection failed: ' . $e->getMessage());
        }
    }

    public function fetchMetrics(Period $period): array
    {
        try {
            $startDate = $period->start->format('Y-m-d');
            $endDate = $period->end->format('Y-m-d');
            
            $params = [
                'dimensions' => 'date',
                'metrics' => 'sessions,users,totalRevenue',
                'dateRanges[0].startDate' => $startDate,
                'dateRanges[0].endDate' => $endDate,
                'limit' => 1000
            ];

            $data = $this->callGA4Api(http_build_query($params));
            
            if (!isset($data['rows']) || !is_array($data['rows'])) {
                return [];
            }

            $rows = [];
            foreach ($data['rows'] as $row) {
                if (!isset($row['dimensionValues'][0]['value'], $row['metricValues'])) {
                    continue;
                }

                $date = $row['dimensionValues'][0]['value'];
                $metrics = $row['metricValues'];

                $rows[] = [
                    'source' => 'ga4',
                    'date' => $date,
                    'users' => (int) ($metrics[1]['value'] ?? 0),
                    'sessions' => (int) ($metrics[0]['value'] ?? 0),
                    'revenue' => (float) ($metrics[2]['value'] ?? 0),
                ];
            }

            return $rows;

        } catch (\Exception $e) {
            error_log(sprintf('[GA4ApiProvider] Failed to fetch metrics: %s', $e->getMessage()));
            return [];
        }
    }

    public function fetchDimensions(Period $period): array
    {
        // Implementazione per dimensioni se necessario
        return [];
    }

    public function describe(): array
    {
        return [
            'name' => 'ga4',
            'label' => 'Google Analytics 4',
            'credentials' => ['service_account'],
            'config' => ['property_id'],
        ];
    }

    private function callGA4Api(string $params): array
    {
        $propertyId = $this->config['property_id'];
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";
        
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->buildReportRequest($params)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception('API error: HTTP ' . $httpCode . ' - ' . $response);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    private function buildReportRequest(string $params): array
    {
        parse_str($params, $parsed);
        
        return [
            'dimensions' => [['name' => 'date']],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'users'],
                ['name' => 'totalRevenue']
            ],
            'dateRanges' => [
                [
                    'startDate' => $parsed['dateRanges[0].startDate'] ?? date('Y-m-d', strtotime('-1 day')),
                    'endDate' => $parsed['dateRanges[0].endDate'] ?? date('Y-m-d')
                ]
            ]
        ];
    }

    private function getAccessToken(): string
    {
        // Implementazione semplificata - in produzione usa OAuth2 o Service Account
        $serviceAccount = $this->auth['service_account'] ?? '';
        
        if (empty($serviceAccount)) {
            throw new \Exception('Service account credentials not provided');
        }

        // Per ora restituiamo un token placeholder
        // In una implementazione reale, qui genereresti il JWT token
        return 'placeholder_token';
    }
}

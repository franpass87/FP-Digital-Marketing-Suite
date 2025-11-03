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

        // Test connessione con una chiamata semplice agli ultimi 7 giorni
        try {
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate = date('Y-m-d');
            $this->callGA4Api("dimensions=date&metrics=sessions&dateRanges[0].startDate={$startDate}&dateRanges[0].endDate={$endDate}");
            return ConnectionResult::success('GA4 connection successful - API responding');
        } catch (\Exception $e) {
            return ConnectionResult::failure('GA4 connection failed: ' . $e->getMessage());
        }
    }

    public function fetchMetrics(Period $period): array
    {
        // PRIMA: Prova a leggere i dati già salvati in config['summary']
        $summary = $this->config['summary'] ?? [];
        if (is_array($summary) && !empty($summary['daily'])) {
            $rows = [];
            foreach ($summary['daily'] as $date => $metrics) {
                if (!is_array($metrics)) {
                    continue;
                }
                
                $dateString = (string) $date;
                if ($dateString === 'total') {
                    continue;
                }
                
                // Filtra solo le date nel periodo richiesto
                if (!$this->isWithinPeriod($period, $dateString)) {
                    continue;
                }
                
                // Normalizza le chiavi per compatibilità con Assembler
                $mapped = [
                    'source' => 'ga4',
                    'date' => $dateString,
                    'users' => (float) ($metrics['users'] ?? $metrics['active_users'] ?? 0),
                    'sessions' => (float) ($metrics['sessions'] ?? 0),
                    'pageviews' => (float) ($metrics['pageviews'] ?? $metrics['screenPageViews'] ?? 0),
                    'events' => (float) ($metrics['events'] ?? $metrics['eventCount'] ?? 0),
                    'new_users' => (float) ($metrics['new_users'] ?? 0),
                    'total_users' => (float) ($metrics['total_users'] ?? 0),
                    'revenue' => (float) ($metrics['revenue'] ?? 0),
                ];
                
                $rows[] = $mapped;
            }
            
            // Se abbiamo dati nel periodo, restituiscili
            if (!empty($rows)) {
                return $rows;
            }
        }
        
        // ALTRIMENTI: Fai chiamata API
        
        try {
            $startDate = $period->start->format('Y-m-d');
            $endDate = $period->end->format('Y-m-d');
            
            $params = [
                'dimensions' => 'date',
                'metrics' => 'sessions,activeUsers,totalUsers,newUsers,screenPageViews,eventCount',
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

                $dateRaw = $row['dimensionValues'][0]['value'];
                $metrics = $row['metricValues'];
                
                // Normalizza formato data: 20251022 → 2025-10-22
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateRaw, $matches)) {
                    $date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                } else {
                    $date = $dateRaw;
                }

                $rows[] = [
                    'source' => 'ga4',
                    'date' => $date,
                    'sessions' => (int) ($metrics[0]['value'] ?? 0),
                    'users' => (int) ($metrics[1]['value'] ?? 0), // activeUsers
                    'total_users' => (int) ($metrics[2]['value'] ?? 0),
                    'new_users' => (int) ($metrics[3]['value'] ?? 0),
                    'pageviews' => (int) ($metrics[4]['value'] ?? 0),
                    'events' => (int) ($metrics[5]['value'] ?? 0),
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

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
                ['name' => 'activeUsers'],
                ['name' => 'totalUsers'],
                ['name' => 'newUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'eventCount']
            ],
            'dateRanges' => [
                [
                    'startDate' => $parsed['dateRanges[0].startDate'] ?? date('Y-m-d', strtotime('-7 days')),
                    'endDate' => $parsed['dateRanges[0].endDate'] ?? date('Y-m-d')
                ]
            ]
        ];
    }

    private function getAccessToken(): string
    {
        static $cachedToken = null;
        static $expiresAt = 0;
        
        // Usa token in cache se ancora valido
        if ($cachedToken && time() < $expiresAt) {
            return $cachedToken;
        }
        
        $serviceAccount = $this->auth['service_account'] ?? '';
        
        if (empty($serviceAccount)) {
            throw new \Exception('Service account credentials not provided');
        }

        // Decodifica il Service Account JSON
        $credentials = is_string($serviceAccount) 
            ? json_decode($serviceAccount, true) 
            : $serviceAccount;
        
        if (!is_array($credentials) || empty($credentials['private_key']) || empty($credentials['client_email'])) {
            throw new \Exception('Invalid service account JSON');
        }

        // Crea JWT token
        $now = time();
        $jwt = $this->createJWT([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], $credentials['private_key']);

        // Scambia JWT per access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to get access token: HTTP ' . $httpCode . ' - ' . $response);
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new \Exception('No access token in response');
        }

        // Cache token per 55 minuti (scade dopo 60)
        $cachedToken = $data['access_token'];
        $expiresAt = time() + 3300;

        return $cachedToken;
    }

    private function createJWT(array $payload, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        
        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header));
        $segments[] = $this->base64UrlEncode(json_encode($payload));
        
        $signingInput = implode('.', $segments);
        
        $signature = '';
        $success = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$success) {
            throw new \Exception('Failed to sign JWT');
        }
        
        $segments[] = $this->base64UrlEncode($signature);
        
        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function isWithinPeriod(Period $period, string $date): bool
    {
        if ($date === '') {
            return false;
        }

        try {
            $day = new \DateTimeImmutable($date);
        } catch (\Exception $e) {
            return false;
        }

        return $day >= $period->start && $day <= $period->end;
    }
}

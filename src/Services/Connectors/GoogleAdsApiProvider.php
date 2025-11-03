<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

class GoogleAdsApiProvider implements DataSourceProviderInterface
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
        $requiredAuth = ['developer_token', 'client_id', 'client_secret', 'refresh_token'];
        foreach ($requiredAuth as $key) {
            if (trim((string) ($this->auth[$key] ?? '')) === '') {
                return ConnectionResult::failure('Complete all Google Ads API credentials before testing the connection.');
            }
        }

        $customerId = trim((string) ($this->config['customer_id'] ?? ''));
        if ($customerId === '' || ! preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/', $customerId)) {
            return ConnectionResult::failure('Enter a valid Google Ads customer ID in the 000-000-0000 format.');
        }

        // Test connessione con chiamata API reale
        try {
            $accessToken = $this->getAccessToken();
            $this->callGoogleAdsApi('SELECT customer.id FROM customer LIMIT 1', 1);
            return ConnectionResult::success('Google Ads connection successful - API responding');
        } catch (\Exception $e) {
            return ConnectionResult::failure('Google Ads connection failed: ' . $e->getMessage());
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
                
                $rows[] = array_merge(
                    ['source' => 'google_ads', 'date' => $dateString],
                    $metrics
                );
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
            
            $query = "SELECT 
                segments.date,
                metrics.clicks,
                metrics.impressions,
                metrics.cost_micros,
                metrics.conversions,
                metrics.conversions_value
            FROM campaign
            WHERE segments.date BETWEEN '{$startDate}' AND '{$endDate}'
            ORDER BY segments.date";
            
            $data = $this->callGoogleAdsApi($query);
            
            if (!isset($data['results']) || !is_array($data['results'])) {
                return [];
            }

            $rows = [];
            foreach ($data['results'] as $row) {
                $date = $row['segments']['date'] ?? '';
                $metrics = $row['metrics'] ?? [];
                
                if ($date === '') {
                    continue;
                }
                
                $rows[] = [
                    'source' => 'google_ads',
                    'date' => $date,
                    'clicks' => (float) ($metrics['clicks'] ?? 0),
                    'impressions' => (float) ($metrics['impressions'] ?? 0),
                    'cost' => (float) ($metrics['costMicros'] ?? 0) / 1000000, // Convert from micros
                    'conversions' => (float) ($metrics['conversions'] ?? 0),
                    'revenue' => (float) ($metrics['conversionsValue'] ?? 0),
                ];
            }

            return $rows;

        } catch (\Exception $e) {
            error_log(sprintf('[GoogleAdsApiProvider] Failed to fetch metrics: %s', $e->getMessage()));
            return [];
        }
    }

    public function fetchDimensions(Period $period): array
    {
        return [];
    }

    public function describe(): array
    {
        return [
            'name' => 'google_ads',
            'label' => 'Google Ads',
            'credentials' => ['developer_token', 'client_id', 'client_secret', 'refresh_token'],
            'config' => ['customer_id', 'login_customer_id'],
        ];
    }

    private function callGoogleAdsApi(string $query, ?int $pageSize = null): array
    {
        $customerId = str_replace('-', '', $this->config['customer_id'] ?? '');
        $loginCustomerId = $this->config['login_customer_id'] ?? null;
        if ($loginCustomerId) {
            $loginCustomerId = str_replace('-', '', $loginCustomerId);
        }
        
        $url = "https://googleads.googleapis.com/v16/customers/{$customerId}/googleAds:searchStream";
        
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
            'developer-token: ' . ($this->auth['developer_token'] ?? ''),
        ];
        
        if ($loginCustomerId) {
            $headers[] = 'login-customer-id: ' . $loginCustomerId;
        }

        $body = json_encode([
            'query' => $query,
        ]);
        
        if ($pageSize !== null) {
            $body = json_encode([
                'query' => $query,
                'pageSize' => $pageSize,
            ]);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
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

    private function getAccessToken(): string
    {
        static $cachedToken = null;
        static $expiresAt = 0;
        
        // Usa token in cache se ancora valido
        if ($cachedToken && time() < $expiresAt) {
            return $cachedToken;
        }
        
        $clientId = $this->auth['client_id'] ?? '';
        $clientSecret = $this->auth['client_secret'] ?? '';
        $refreshToken = $this->auth['refresh_token'] ?? '';
        
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            throw new \Exception('OAuth credentials not provided');
        }

        // Scambia refresh token per access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
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


<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

class GSCApiProvider implements DataSourceProviderInterface
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
        $siteUrl = trim((string) ($this->config['site_url'] ?? ''));
        
        if ($siteUrl === '') {
            return ConnectionResult::failure('Site URL is required for Google Search Console');
        }

        // Test connessione con una chiamata semplice agli ultimi 7 giorni
        try {
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-7 days'));
            
            $this->callGSCApi($siteUrl, $startDate, $endDate);
            return ConnectionResult::success('GSC connection successful - API responding');
        } catch (\Exception $e) {
            return ConnectionResult::failure('GSC connection failed: ' . $e->getMessage());
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
                
                // Mappa le chiavi per compatibilità con Assembler
                $mapped = [
                    'source' => 'gsc',
                    'date' => $dateString,
                    'clicks' => (float) ($metrics['clicks'] ?? $metrics['gsc_clicks'] ?? 0),
                    'impressions' => (float) ($metrics['impressions'] ?? $metrics['gsc_impressions'] ?? 0),
                    'ctr' => (float) ($metrics['ctr'] ?? 0),
                    'position' => (float) ($metrics['position'] ?? 0),
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
            $siteUrl = trim((string) ($this->config['site_url'] ?? ''));
            if ($siteUrl === '') {
                return [];
            }

            $startDate = $period->start->format('Y-m-d');
            $endDate = $period->end->format('Y-m-d');
            
            $data = $this->callGSCApi($siteUrl, $startDate, $endDate);
            
            if (!isset($data['rows']) || !is_array($data['rows'])) {
                return [];
            }

            $rows = [];
            foreach ($data['rows'] as $row) {
                if (!isset($row['keys'][0])) {
                    continue;
                }

                $date = $row['keys'][0]; // Prima dimensione è la data
                
                // GSC restituisce CTR come decimale (es. 0.05 = 5%)
                // Position è già un float
                $rows[] = [
                    'source' => 'gsc',
                    'date' => $date,
                    'clicks' => (float) ($row['clicks'] ?? 0), // Float per compatibilità Normalizer
                    'impressions' => (float) ($row['impressions'] ?? 0), // Float per compatibilità Normalizer
                    'ctr' => (float) ($row['ctr'] ?? 0) * 100, // Converti in percentuale (0.05 → 5)
                    'position' => (float) ($row['position'] ?? 0),
                ];
            }

            return $rows;

        } catch (\Exception $e) {
            error_log(sprintf('[GSCApiProvider] Failed to fetch metrics: %s', $e->getMessage()));
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
            'name' => 'gsc',
            'label' => 'Google Search Console',
            'credentials' => ['service_account'],
            'config' => ['site_url'],
        ];
    }

    private function callGSCApi(string $siteUrl, string $startDate, string $endDate): array
    {
        // Encode site URL per l'API (doppio encoding per gestire https://)
        // https://example.com/ diventa https%3A%2F%2Fexample.com%2F
        $encodedSiteUrl = rawurlencode($siteUrl);
        $url = "https://searchconsole.googleapis.com/webmasters/v3/sites/{$encodedSiteUrl}/searchAnalytics/query";
        
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ];

        $body = json_encode([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['date'],
            'rowLimit' => 25000,
            'aggregationType' => 'auto',
        ]);

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
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
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


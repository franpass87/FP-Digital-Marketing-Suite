<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

class MetaAdsApiProvider implements DataSourceProviderInterface
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
        $token = trim((string) ($this->auth['access_token'] ?? ''));
        $accountId = trim((string) ($this->config['account_id'] ?? ''));

        if ($token === '') {
            return ConnectionResult::failure('Provide a Meta Ads access token with the required permissions.');
        }

        if ($accountId === '' || ! preg_match('/^act_[0-9]+$/', $accountId)) {
            return ConnectionResult::failure('Enter the ad account ID using the act_1234567890 format.');
        }

        // Test connessione con chiamata API reale
        try {
            $this->callMetaAdsApi($accountId, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), 1);
            return ConnectionResult::success('Meta Ads connection successful - API responding');
        } catch (\Exception $e) {
            return ConnectionResult::failure('Meta Ads connection failed: ' . $e->getMessage());
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
                    ['source' => 'meta_ads', 'date' => $dateString],
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
            $accountId = trim((string) ($this->config['account_id'] ?? ''));
            if ($accountId === '') {
                return [];
            }

            $startDate = $period->start->format('Y-m-d');
            $endDate = $period->end->format('Y-m-d');
            
            $data = $this->callMetaAdsApi($accountId, $startDate, $endDate);
            
            if (!isset($data['data']) || !is_array($data['data'])) {
                return [];
            }

            $rows = [];
            foreach ($data['data'] as $row) {
                $date = $row['date_start'] ?? '';
                
                if ($date === '') {
                    continue;
                }
                
                $rows[] = [
                    'source' => 'meta_ads',
                    'date' => $date,
                    'clicks' => (float) ($row['clicks'] ?? 0),
                    'impressions' => (float) ($row['impressions'] ?? 0),
                    'cost' => (float) ($row['spend'] ?? 0),
                    'conversions' => (float) ($row['actions'][0]['value'] ?? 0), // Conversioni totali
                    'revenue' => (float) ($row['action_values'][0]['value'] ?? 0), // Valore conversioni
                ];
            }

            return $rows;

        } catch (\Exception $e) {
            error_log(sprintf('[MetaAdsApiProvider] Failed to fetch metrics: %s', $e->getMessage()));
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
            'name' => 'meta_ads',
            'label' => 'Meta Ads',
            'credentials' => ['access_token'],
            'config' => ['account_id', 'pixel_id'],
        ];
    }

    private function callMetaAdsApi(string $accountId, string $startDate, string $endDate, ?int $limit = null): array
    {
        $fields = [
            'date_start',
            'date_stop',
            'clicks',
            'impressions',
            'spend',
            'actions',
            'action_values',
        ];
        
        $params = [
            'fields' => implode(',', $fields),
            'time_range' => json_encode([
                'since' => $startDate,
                'until' => $endDate,
            ]),
            'time_increment' => 1, // Daily breakdown
            'level' => 'account',
            'access_token' => $this->auth['access_token'] ?? '',
        ];
        
        if ($limit !== null) {
            $params['limit'] = $limit;
        }
        
        $url = "https://graph.facebook.com/v18.0/{$accountId}/insights?" . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
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


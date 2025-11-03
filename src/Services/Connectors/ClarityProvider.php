<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

use function __;

class ClarityProvider implements DataSourceProviderInterface
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
        $projectId = trim((string) ($this->config['project_id'] ?? ''));
        $apiKey = trim((string) ($this->auth['api_key'] ?? ''));

        if ($projectId === '' || $apiKey === '') {
            return ConnectionResult::failure(__('Provide both the project ID and API key to connect Microsoft Clarity.', 'fp-dms'));
        }

        return ConnectionResult::success(__('Credentials saved. Clarity data will sync on the next collection run.', 'fp-dms'));
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
                    ['source' => 'clarity', 'date' => $dateString],
                    $metrics
                );
            }
            
            // Se abbiamo dati nel periodo, restituiscili
            if (!empty($rows)) {
                return $rows;
            }
        }
        
        // ALTRIMENTI: Fai chiamata API Microsoft Clarity
        try {
            $projectId = trim((string) ($this->config['project_id'] ?? ''));
            $apiKey = trim((string) ($this->auth['api_key'] ?? ''));
            
            if ($projectId === '' || $apiKey === '') {
                error_log('[ClarityProvider] Missing project_id or api_key');
                return [];
            }

            $startDate = $period->start->format('Y-m-d');
            $endDate = $period->end->format('Y-m-d');
            
            $data = $this->callClarityApi($projectId, $apiKey, $startDate, $endDate);
            
            if (!isset($data['metrics']) || !is_array($data['metrics'])) {
                return [];
            }

            $rows = [];
            foreach ($data['metrics'] as $metric) {
                $date = $metric['date'] ?? '';
                
                if ($date === '' || !$this->isWithinPeriod($period, $date)) {
                    continue;
                }
                
                $rows[] = [
                    'source' => 'clarity',
                    'date' => $date,
                    'sessions' => (float) ($metric['sessions'] ?? 0),
                    'users' => (float) ($metric['users'] ?? 0),
                    'rage_clicks' => (float) ($metric['rage_clicks'] ?? 0),
                    'dead_clicks' => (float) ($metric['dead_clicks'] ?? 0),
                    'excessive_scrolling' => (float) ($metric['excessive_scrolling'] ?? 0),
                    'quick_backs' => (float) ($metric['quick_backs'] ?? 0),
                ];
            }

            return $rows;

        } catch (\Exception $e) {
            error_log(sprintf('[ClarityProvider] Failed to fetch metrics: %s', $e->getMessage()));
            return [];
        }
    }

    /**
     * Call Microsoft Clarity API to fetch metrics
     *
     * @param string $projectId
     * @param string $apiKey
     * @param string $startDate
     * @param string $endDate
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function callClarityApi(string $projectId, string $apiKey, string $startDate, string $endDate): array
    {
        // Microsoft Clarity API endpoint
        $url = sprintf(
            'https://www.clarity.ms/api/projects/%s/metrics?start=%s&end=%s',
            urlencode($projectId),
            urlencode($startDate),
            urlencode($endDate)
        );
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorMessage = sprintf('API error: HTTP %d', $httpCode);
            if ($response) {
                $errorData = json_decode($response, true);
                if (isset($errorData['error'])) {
                    $errorMessage .= ' - ' . $errorData['error'];
                } else {
                    $errorMessage .= ' - ' . substr($response, 0, 200);
                }
            }
            throw new \Exception($errorMessage);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data ?? [];
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

    public function fetchDimensions(Period $period): array
    {
        return [];
    }

    public function describe(): array
    {
        return [
            'name' => 'clarity',
            'label' => __('Microsoft Clarity', 'fp-dms'),
            'credentials' => ['api_key'],
            'config' => ['project_id', 'site_url', 'webhook_url'],
        ];
    }
}

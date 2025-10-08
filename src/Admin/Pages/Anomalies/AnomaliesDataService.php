<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Anomalies;

use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Support\I18n;
use function is_array;
use function is_numeric;

/**
 * Handles data retrieval and transformation for anomalies
 */
class AnomaliesDataService
{
    /**
     * Get clients map (id => name)
     *
     * @return array<int, string>
     */
    public static function getClientsMap(): array
    {
        $clientsRepo = new ClientsRepo();
        $clients = $clientsRepo->all();
        $map = [];
        
        foreach ($clients as $client) {
            $map[$client->id ?? 0] = $client->name;
        }
        
        return $map;
    }

    /**
     * Get recent anomalies
     *
     * @return array<int, \FP\DMS\Domain\Entities\Anomaly>
     */
    public static function getRecentAnomalies(int $clientId = 0, int $limit = 50): array
    {
        $repo = new AnomaliesRepo();
        
        if ($clientId > 0) {
            return $repo->recentForClient($clientId, $limit);
        }
        
        return $repo->recent($limit);
    }

    /**
     * Format anomaly data for table display
     *
     * @param \FP\DMS\Domain\Entities\Anomaly $anomaly
     * @param array<int, string> $clientsMap
     * @return array{
     *   detected_at: string,
     *   client: string,
     *   metric: string,
     *   severity: string,
     *   delta_percent: string,
     *   zscore: string,
     *   note: string,
     *   actions: string
     * }
     */
    public static function formatAnomalyForDisplay($anomaly, array $clientsMap): array
    {
        $payload = $anomaly->payload;
        $metric = isset($payload['metric']) ? (string) $payload['metric'] : $anomaly->type;
        
        $delta = isset($payload['delta_percent']) && is_numeric($payload['delta_percent'])
            ? round((float) $payload['delta_percent'], 2)
            : '—';
        
        $zscore = isset($payload['zscore']) && is_numeric($payload['zscore'])
            ? round((float) $payload['zscore'], 2)
            : '—';
        
        $clientName = $clientsMap[$anomaly->clientId] ?? I18n::__('Unknown');
        
        return [
            'detected_at' => $anomaly->detectedAt ?? '—',
            'client' => $clientName,
            'metric' => self::formatMetricName($metric),
            'severity' => self::formatSeverity($anomaly->severity),
            'delta_percent' => $delta !== '—' ? $delta . '%' : '—',
            'zscore' => (string) $zscore,
            'note' => $anomaly->note ?? '',
            'actions' => '', // Will be rendered separately
        ];
    }

    /**
     * Format metric name for display
     */
    private static function formatMetricName(string $metric): string
    {
        $formatted = \str_replace('_', ' ', $metric);
        return \ucwords($formatted);
    }

    /**
     * Format severity for display
     */
    private static function formatSeverity(string $severity): string
    {
        return match (\strtolower($severity)) {
            'critical', 'error' => I18n::__('Critical'),
            'warning' => I18n::__('Warning'),
            'notice' => I18n::__('Notice'),
            default => I18n::__('Info'),
        };
    }

    /**
     * Get severity badge class
     */
    public static function getSeverityBadgeClass(string $severity): string
    {
        return match (\strtolower($severity)) {
            'critical', 'error' => 'fpdms-badge-danger',
            'warning' => 'fpdms-badge-warning',
            'notice' => 'fpdms-badge-info',
            default => 'fpdms-badge-neutral',
        };
    }

    /**
     * Get anomaly policy configuration
     *
     * @return array<string, mixed>
     */
    public static function getPolicyConfig(int $clientId = 0): array
    {
        $options = \FP\DMS\Infra\Options::getGlobalSettings();
        $policies = $options['anomaly_detection']['policies'] ?? [];
        
        if ($clientId > 0 && isset($policies[$clientId])) {
            return $policies[$clientId];
        }
        
        return $policies['default'] ?? [
            'enabled' => true,
            'sensitivity' => 'medium',
            'metrics' => ['users', 'sessions', 'clicks', 'conversions'],
        ];
    }

    /**
     * Get available metrics for anomaly detection
     *
     * @return array<string, string>
     */
    public static function getAvailableMetrics(): array
    {
        return [
            'users' => I18n::__('Users'),
            'sessions' => I18n::__('Sessions'),
            'clicks' => I18n::__('Clicks'),
            'impressions' => I18n::__('Impressions'),
            'cost' => I18n::__('Cost'),
            'conversions' => I18n::__('Conversions'),
            'revenue' => I18n::__('Revenue'),
            'gsc_clicks' => I18n::__('GSC Clicks'),
            'gsc_impressions' => I18n::__('GSC Impressions'),
        ];
    }

    /**
     * Get sensitivity levels
     *
     * @return array<string, string>
     */
    public static function getSensitivityLevels(): array
    {
        return [
            'low' => I18n::__('Low (conservative)'),
            'medium' => I18n::__('Medium (balanced)'),
            'high' => I18n::__('High (sensitive)'),
        ];
    }
}
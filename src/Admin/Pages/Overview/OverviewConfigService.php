<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Overview;

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Infra\Options;
use FP\DMS\Support\UserPrefs;
use FP\DMS\Support\Wp;

use function __;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function esc_html__;
use function esc_url_raw;
use function is_array;
use function rest_url;
use function sort;
use function wp_create_nonce;

/**
 * Handles configuration and data preparation for the Overview page
 */
class OverviewConfigService
{
    /**
     * @var array<string, string>
     */
    public const KPI_LABELS = [
        'users' => 'Users',
        'sessions' => 'Sessions',
        'clicks' => 'Clicks',
        'impressions' => 'Impressions',
        'cost' => 'Cost',
        'conversions' => 'Conversions',
        'revenue' => 'Revenue',
        'gsc_clicks' => 'GSC Clicks',
        'gsc_impressions' => 'GSC Impressions',
    ];

    /**
     * @var string[]
     */
    public const TREND_METRICS = ['users', 'sessions', 'clicks', 'conversions'];

    /**
     * @var int[]
     */
    private const DEFAULT_REFRESH_INTERVALS = [60, 120];

    /**
     * Get all clients as options array
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function getClientOptions(): array
    {
        $repo = new ClientsRepo();
        $clients = $repo->all();

        return array_map(
            static fn(\FP\DMS\Domain\Entities\Client $client): array => [
                'id' => (int) ($client->id ?? 0),
                'name' => $client->name,
            ],
            $clients
        );
    }

    /**
     * Get available refresh intervals
     *
     * @return int[]
     */
    public static function getRefreshIntervals(): array
    {
        $settings = Options::getGlobalSettings();
        $configured = $settings['overview']['refresh_intervals'] ?? null;

        $intervals = [];

        if (is_array($configured)) {
            foreach ($configured as $value) {
                $interval = Wp::absInt($value);
                if ($interval < 30 || $interval > 3600) {
                    continue;
                }
                $intervals[] = $interval;
            }
        }

        if ($intervals === []) {
            $intervals = self::DEFAULT_REFRESH_INTERVALS;
        }

        $intervals = array_values(array_unique($intervals));
        $intervals = array_values(array_filter($intervals, static fn(int $seconds): bool => $seconds >= 30 && $seconds <= 3600));
        sort($intervals);

        return $intervals === [] ? self::DEFAULT_REFRESH_INTERVALS : $intervals;
    }

    /**
     * Build complete configuration for the JavaScript application
     *
     * @param array<int, array{id: int, name: string}> $clients
     * @return array<string, mixed>
     */
    public static function buildConfig(array $clients): array
    {
        $preferences = UserPrefs::getOverviewPreferences();
        $refreshIntervals = self::getRefreshIntervals();
        $defaultRefreshInterval = $refreshIntervals[0] ?? self::DEFAULT_REFRESH_INTERVALS[0];

        return [
            'nonce' => wp_create_nonce('wp_rest'),
            'endpoints' => [
                'summary' => esc_url_raw(rest_url('fpdms/v1/overview/summary')),
                'trend' => esc_url_raw(rest_url('fpdms/v1/overview/trend')),
                'status' => esc_url_raw(rest_url('fpdms/v1/overview/status')),
                'anomalies' => esc_url_raw(rest_url('fpdms/v1/overview/anomalies')),
            ],
            'actions' => [
                'run' => esc_url_raw(rest_url('fpdms/v1/overview/run')),
                'anomalies' => esc_url_raw(rest_url('fpdms/v1/overview/anomalies')),
            ],
            'clients' => $clients,
            'kpis' => self::KPI_LABELS,
            'trendMetrics' => self::TREND_METRICS,
            'preferences' => $preferences,
            'refreshIntervals' => $refreshIntervals,
            'defaultRefreshInterval' => $defaultRefreshInterval,
            'i18n' => self::getI18nStrings(),
        ];
    }

    /**
     * Get internationalized strings for the JavaScript application
     *
     * @return array<string, string>
     */
    private static function getI18nStrings(): array
    {
        return [
            'noData' => esc_html__('No data available.', 'fp-dms'),
            'loading' => esc_html__('Loading…', 'fp-dms'),
            'previous' => esc_html__('Previous period', 'fp-dms'),
            'sparklineFallback' => esc_html__('Trend data will appear soon.', 'fp-dms'),
            'anomalyAction' => esc_html__('Resolve / Note', 'fp-dms'),
            'actionError' => esc_html__('Action failed. Try again.', 'fp-dms'),
            'runPending' => esc_html__('Queuing report…', 'fp-dms'),
            'runQueued' => esc_html__('Report run queued.', 'fp-dms'),
            'anomalyRunning' => esc_html__('Evaluating anomalies…', 'fp-dms'),
            'anomalyComplete' => esc_html__('Anomaly evaluation found %d signals.', 'fp-dms'),
            'anomalyNone' => esc_html__('No anomalies detected in the last 30 days.', 'fp-dms'),
            'lastRefresh' => esc_html__('Last refresh at %s', 'fp-dms'),
            'lastRefreshNever' => esc_html__('Last refresh: never', 'fp-dms'),
            'autoRefreshInterval' => esc_html__('Auto-refresh interval', 'fp-dms'),
            'autoRefresh' => esc_html__('Auto-refresh', 'fp-dms'),
            'refreshing' => esc_html__('Refreshing…', 'fp-dms'),
            'errorGeneric' => esc_html__('Something went wrong. Please try again.', 'fp-dms'),
            'statusChecked' => esc_html__('Status checked at %s', 'fp-dms'),
            'statusUpdated' => esc_html__('Last data update: %s', 'fp-dms'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Support\UserPrefs;

use const FP_DMS_PLUGIN_FILE;
use const FP_DMS_VERSION;
use function add_action;
use function add_query_arg;
use function admin_url;
use function array_map;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function plugins_url;
use function rest_url;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;

class OverviewPage
{
    /**
     * @var array<string, string>
     */
    private const KPI_LABELS = [
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
    private const TREND_METRICS = ['users', 'sessions', 'clicks', 'conversions'];

    /**
     * @var int[]
     */
    private const REFRESH_INTERVALS = [60, 120];

    public static function registerAssetsHook(string $hook): void
    {
        add_action('admin_enqueue_scripts', static function (string $currentHook) use ($hook): void {
            if ($currentHook !== $hook) {
                return;
            }

            self::enqueueAssets();
        });
    }

    private static function enqueueAssets(): void
    {
        $styleUrl = plugins_url('assets/css/overview.css', FP_DMS_PLUGIN_FILE);
        $scriptUrl = plugins_url('assets/js/overview.js', FP_DMS_PLUGIN_FILE);

        wp_enqueue_style('fpdms-overview', $styleUrl, [], FP_DMS_VERSION);
        wp_enqueue_script('fpdms-overview', $scriptUrl, [], FP_DMS_VERSION, true);
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $clients = self::clientOptions();

        echo '<div class="wrap fpdms-overview-wrap" id="fpdms-overview-root">';
        echo '<h1>' . esc_html__('Overview', 'fp-dms') . '</h1>';

        if (empty($clients)) {
            echo '<p>' . esc_html__('No clients available yet. Add a client to view the dashboard.', 'fp-dms') . '</p>';
            echo '<p>';
            echo '<a class="button button-primary" href="' . esc_url(add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php'))) . '">';
            echo esc_html__('Add your first client', 'fp-dms');
            echo '</a>';
            echo '</p>';
            echo '</div>';

            return;
        }

        self::renderErrorBanner();
        self::renderFilters($clients);
        self::renderSummarySection();
        self::renderTrendSection();
        self::renderAnomaliesSection();
        self::renderStatusSection();
        self::renderJobsSection();
        self::renderConfig($clients);

        echo '</div>';
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private static function clientOptions(): array
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

    private static function renderErrorBanner(): void
    {
        echo '<div id="fpdms-overview-error" class="fpdms-overview-error" role="alert">';
        echo '<strong>' . esc_html__('Unable to load overview data.', 'fp-dms') . '</strong> ';
        echo '<span id="fpdms-overview-error-message">' . esc_html__('Retry in a moment.', 'fp-dms') . '</span>';
        echo '</div>';
    }

    /**
     * @param array<int, array{id: int, name: string}> $clients
     */
    private static function renderFilters(array $clients): void
    {
        $presets = [
            'last7' => esc_html__('Last 7 days', 'fp-dms'),
            'last14' => esc_html__('Last 14 days', 'fp-dms'),
            'last28' => esc_html__('Last 28 days', 'fp-dms'),
            'this_month' => esc_html__('This month', 'fp-dms'),
            'last_month' => esc_html__('Last month', 'fp-dms'),
            'custom' => esc_html__('Custom', 'fp-dms'),
        ];

        echo '<div class="fpdms-overview-controls" role="region" aria-label="' . esc_attr__('Overview filters', 'fp-dms') . '">';

        echo '<div class="fpdms-overview-field">';
        echo '<label for="fpdms-overview-client">' . esc_html__('Client', 'fp-dms') . '</label>';
        echo '<select id="fpdms-overview-client">';
        foreach ($clients as $client) {
            echo '<option value="' . esc_attr((string) $client['id']) . '">' . esc_html($client['name']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="fpdms-overview-field" style="flex:1;min-width:260px;">';
        echo '<span class="fpdms-label">' . esc_html__('Date range', 'fp-dms') . '</span>';
        echo '<div class="fpdms-overview-presets" role="group" aria-label="' . esc_attr__('Date presets', 'fp-dms') . '">';
        foreach ($presets as $key => $label) {
            echo '<button type="button" data-fpdms-preset="' . esc_attr($key) . '" aria-pressed="false">' . $label . '</button>';
        }
        echo '</div>';
        echo '<div class="fpdms-overview-custom" id="fpdms-overview-custom" hidden>';
        echo '<label>' . esc_html__('From', 'fp-dms') . '<input type="date" id="fpdms-overview-date-from"></label>';
        echo '<label>' . esc_html__('To', 'fp-dms') . '<input type="date" id="fpdms-overview-date-to"></label>';
        echo '</div>';
        echo '</div>';

        echo '<div class="fpdms-overview-refresh" aria-live="polite">';
        echo '<label for="fpdms-overview-refresh-toggle">';
        echo '<input type="checkbox" id="fpdms-overview-refresh-toggle">';
        echo '<span>' . esc_html__('Auto-refresh', 'fp-dms') . '</span>';
        echo '</label>';
        echo '<select id="fpdms-overview-refresh-interval" aria-label="' . esc_attr__('Auto-refresh interval', 'fp-dms') . '">';
        foreach (self::REFRESH_INTERVALS as $seconds) {
            echo '<option value="' . esc_attr((string) $seconds) . '">' . esc_html(sprintf(/* translators: %d is seconds */ __('Every %d seconds', 'fp-dms'), $seconds)) . '</option>';
        }
        echo '</select>';
        echo '<span class="fpdms-overview-refresh-note" id="fpdms-overview-last-refresh">' . esc_html__('Last refresh: never', 'fp-dms') . '</span>';
        echo '</div>';

        echo '</div>';
    }

    private static function renderSummarySection(): void
    {
        echo '<section class="fpdms-overview-section" aria-labelledby="fpdms-overview-kpis-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-kpis-heading">' . esc_html__('Key metrics', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-overview-period" id="fpdms-overview-period"><span id="fpdms-overview-period-label">' . esc_html__('Loading…', 'fp-dms') . '</span><span class="fpdms-overview-spinner">&#x27F3;</span></div>';
        echo '</header>';
        echo '<div class="fpdms-overview-kpis" id="fpdms-overview-kpis" aria-live="polite">';
        foreach (self::KPI_LABELS as $metric => $label) {
            self::renderKpiCard($metric, $label);
        }
        echo '</div>';
        echo '</section>';
    }

    private static function renderTrendSection(): void
    {
        echo '<section class="fpdms-overview-section" aria-labelledby="fpdms-overview-trends-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-trends-heading">' . esc_html__('Trend snapshots', 'fp-dms') . '</h2>';
        echo '<span class="fpdms-overview-period" id="fpdms-overview-trend-period">' . esc_html__('Daily values over the selected window.', 'fp-dms') . '</span>';
        echo '</header>';
        echo '<div class="fpdms-overview-trends" id="fpdms-overview-trends-grid">';
        foreach (self::TREND_METRICS as $metric) {
            $label = self::KPI_LABELS[$metric] ?? $metric;
            echo '<article class="fpdms-trend-card" data-metric="' . esc_attr($metric) . '">';
            echo '<h3>' . esc_html__(self::KPI_LABELS[$metric] ?? ucfirst($metric), 'fp-dms') . '</h3>';
            echo '<svg role="img" aria-label="' . esc_attr(sprintf(esc_html__('%s sparkline', 'fp-dms'), esc_html__(self::KPI_LABELS[$metric] ?? ucfirst($metric), 'fp-dms'))) . '" viewBox="0 0 100 40"></svg>';
            echo '<p class="fpdms-kpi-previous" data-role="trend-meta">' . esc_html__('Awaiting data…', 'fp-dms') . '</p>';
            echo '</article>';
        }
        echo '</div>';
        echo '</section>';
    }

    private static function renderAnomaliesSection(): void
    {
        echo '<section class="fpdms-overview-section" aria-labelledby="fpdms-overview-anomalies-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-anomalies-heading">' . esc_html__('Recent anomalies', 'fp-dms') . '</h2>';
        echo '<span class="fpdms-overview-period" id="fpdms-overview-anomalies-meta">' . esc_html__('Last 10 flagged signals.', 'fp-dms') . '</span>';
        echo '</header>';
        echo '<div class="fpdms-overview-anomalies">';
        echo '<table class="fpdms-anomalies-table" id="fpdms-overview-anomalies">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Severity', 'fp-dms') . '</th>';
        echo '<th scope="col">' . esc_html__('Metric', 'fp-dms') . '</th>';
        echo '<th scope="col">' . esc_html__('Change', 'fp-dms') . '</th>';
        echo '<th scope="col">' . esc_html__('Score', 'fp-dms') . '</th>';
        echo '<th scope="col">' . esc_html__('When', 'fp-dms') . '</th>';
        echo '<th scope="col">' . esc_html__('Actions', 'fp-dms') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        echo '<tr><td colspan="6">' . esc_html__('No anomalies for this range.', 'fp-dms') . '</td></tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</section>';
    }

    private static function renderStatusSection(): void
    {
        echo '<section class="fpdms-overview-section" aria-labelledby="fpdms-overview-status-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-status-heading">' . esc_html__('Data source status', 'fp-dms') . '</h2>';
        echo '<span class="fpdms-overview-period" id="fpdms-overview-status-meta">' . esc_html__('Connector health at a glance.', 'fp-dms') . '</span>';
        echo '<span class="fpdms-overview-period" id="fpdms-overview-status-updated" aria-live="polite"></span>';
        echo '</header>';
        echo '<div class="fpdms-status-list" id="fpdms-overview-status-list" aria-live="polite">';
        echo '<div class="fpdms-status-item">';
        echo '<span class="fpdms-status-label">' . esc_html__('Waiting for data…', 'fp-dms') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }

    private static function renderJobsSection(): void
    {
        echo '<section class="fpdms-overview-section" aria-labelledby="fpdms-overview-jobs-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-jobs-heading">' . esc_html__('Jobs & schedules', 'fp-dms') . '</h2>';
        echo '<span class="fpdms-overview-period">' . esc_html__('Upcoming runs and recently generated reports.', 'fp-dms') . '</span>';
        echo '</header>';
        echo '<p class="fpdms-jobs-placeholder" id="fpdms-overview-jobs">' . esc_html__('Scheduling details will appear here once configured.', 'fp-dms') . '</p>';
        echo '<div class="fpdms-overview-actions" role="group" aria-label="' . esc_attr__('Quick actions', 'fp-dms') . '">';
        echo '<button type="button" class="button button-primary" id="fpdms-overview-action-run">' . esc_html__('Run now', 'fp-dms') . '</button>';
        echo '<button type="button" class="button" id="fpdms-overview-action-anomalies">' . esc_html__('Evaluate anomalies (30 days)', 'fp-dms') . '</button>';
        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'fp-dms-templates'], admin_url('admin.php'))) . '">' . esc_html__('Open templates', 'fp-dms') . '</a>';
        echo '<span class="fpdms-overview-action-status" id="fpdms-overview-action-status" role="status" aria-live="polite"></span>';
        echo '</div>';
        echo '</section>';
    }

    private static function renderKpiCard(string $metric, string $label): void
    {
        echo '<article class="fpdms-kpi-card" data-metric="' . esc_attr($metric) . '">';
        echo '<div class="fpdms-kpi-label">' . esc_html__($label, 'fp-dms') . '</div>';
        echo '<div class="fpdms-kpi-value" data-role="value">--</div>';
        echo '<div class="fpdms-kpi-delta"><span data-role="delta" data-direction="flat">' . esc_html__('0.0%', 'fp-dms') . '</span><span class="fpdms-kpi-previous" data-role="previous">' . esc_html__('Prev: --', 'fp-dms') . '</span></div>';
        echo '<div class="fpdms-kpi-sparkline"><svg viewBox="0 0 100 40" role="img" aria-label="' . esc_attr(sprintf(esc_html__('%s trend', 'fp-dms'), esc_html__($label, 'fp-dms'))) . '"></svg></div>';
        echo '</article>';
    }

    /**
     * @param array<int, array{id: int, name: string}> $clients
     */
    private static function renderConfig(array $clients): void
    {
        $preferences = UserPrefs::getOverviewPreferences();

        $config = [
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
            'refreshIntervals' => self::REFRESH_INTERVALS,
            'defaultRefreshInterval' => self::REFRESH_INTERVALS[0] ?? 60,
            'i18n' => [
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
            ],
        ];

        echo '<script type="application/json" id="fpdms-overview-config">' . wp_json_encode($config) . '</script>';
    }


}

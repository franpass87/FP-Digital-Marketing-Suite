<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Support\UserPrefs;

use function add_query_arg;
use function admin_url;
use function array_map;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function rest_url;
use function wp_create_nonce;
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

        self::renderStyles();
        self::renderErrorBanner();
        self::renderFilters($clients);
        self::renderSummarySection();
        self::renderTrendSection();
        self::renderAnomaliesSection();
        self::renderStatusSection();
        self::renderJobsSection();
        self::renderConfig($clients);
        self::renderScript();

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

    private static function renderStyles(): void
    {
        echo '<style id="fpdms-overview-styles">';
        echo '.fpdms-overview-wrap{max-width:1200px;}';
        echo '.fpdms-overview-wrap.is-loading{opacity:.6;}';
        echo '.fpdms-overview-controls{display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px;padding:16px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;}';
        echo '.fpdms-overview-field{display:flex;flex-direction:column;gap:6px;min-width:200px;}';
        echo '.fpdms-overview-field select{min-width:220px;}';
        echo '.fpdms-overview-presets{display:flex;flex-wrap:wrap;gap:8px;}';
        echo '.fpdms-overview-presets button{background:#f6f7f7;border:1px solid #ccd0d4;border-radius:4px;padding:4px 10px;cursor:pointer;}';
        echo '.fpdms-overview-presets button.is-active{background:#2271b1;border-color:#2271b1;color:#fff;}';
        echo '.fpdms-overview-custom{display:flex;gap:12px;align-items:flex-end;}';
        echo '.fpdms-overview-refresh{display:flex;flex-direction:column;gap:6px;min-width:200px;}';
        echo '.fpdms-overview-refresh label{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1f2933;}';
        echo '.fpdms-overview-refresh select{min-width:160px;}';
        echo '.fpdms-overview-refresh-note{font-size:12px;color:#6b7280;}';
        echo '.fpdms-overview-custom label{display:flex;flex-direction:column;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#4b5563;}';
        echo '.fpdms-overview-section{margin-bottom:24px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px;}';
        echo '.fpdms-overview-section header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:12px;gap:8px;}';
        echo '.fpdms-overview-section h2{font-size:18px;margin:0;}';
        echo '.fpdms-overview-period{font-size:13px;color:#4b5563;}';
        echo '#fpdms-overview-status-updated{display:block;font-size:12px;color:#6b7280;}';
        echo '.fpdms-overview-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;}';
        echo '.fpdms-kpi-card{border:1px solid #d5d8dc;border-radius:6px;padding:12px;display:flex;flex-direction:column;gap:8px;min-height:150px;background:#fdfdfd;}';
        echo '.fpdms-kpi-label{font-size:13px;font-weight:600;color:#1f2933;}';
        echo '.fpdms-kpi-value{font-size:28px;font-weight:700;color:#111827;}';
        echo '.fpdms-kpi-delta{font-size:13px;display:flex;align-items:center;gap:6px;}';
        echo '.fpdms-kpi-delta span{padding:2px 6px;border-radius:999px;font-weight:600;}';
        echo '.fpdms-kpi-delta span[data-direction="up"]{background:#e6f4ea;color:#116149;}';
        echo '.fpdms-kpi-delta span[data-direction="down"]{background:#fde8e8;color:#9b1c1c;}';
        echo '.fpdms-kpi-delta span[data-direction="flat"]{background:#e4e7eb;color:#273444;}';
        echo '.fpdms-kpi-previous{font-size:12px;color:#6b7280;}';
        echo '.fpdms-kpi-sparkline{width:100%;height:48px;}';
        echo '.fpdms-kpi-sparkline svg{width:100%;height:100%;}';
        echo '.fpdms-overview-trends{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;}';
        echo '.fpdms-trend-card{border:1px solid #d5d8dc;border-radius:6px;padding:12px;background:#fdfdfd;}';
        echo '.fpdms-trend-card h3{margin:0 0 8px;font-size:15px;}';
        echo '.fpdms-trend-card svg{width:100%;height:80px;}';
        echo '.fpdms-anomalies-table{width:100%;border-collapse:collapse;}';
        echo '.fpdms-anomalies-table th,.fpdms-anomalies-table td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left;}';
        echo '.fpdms-anomaly-badge{display:inline-flex;align-items:center;padding:2px 6px;border-radius:999px;font-size:12px;font-weight:600;}';
        echo '.fpdms-anomaly-badge[data-variant="critical"]{background:#fde8e8;color:#9b1c1c;}';
        echo '.fpdms-anomaly-badge[data-variant="warning"]{background:#fff4ce;color:#924400;}';
        echo '.fpdms-anomaly-badge[data-variant="info"]{background:#e0f2ff;color:#0b69a3;}';
        echo '.fpdms-anomaly-badge[data-variant="neutral"]{background:#e4e7eb;color:#273444;}';
        echo '.fpdms-status-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}';
        echo '.fpdms-status-item{border:1px solid #d5d8dc;border-radius:6px;padding:12px;background:#fdfdfd;display:flex;flex-direction:column;gap:6px;}';
        echo '.fpdms-status-state{font-weight:700;}';
        echo '.fpdms-status-state[data-state="ok"]{color:#116149;}';
        echo '.fpdms-status-state[data-state="warning"]{color:#924400;}';
        echo '.fpdms-status-state[data-state="missing"]{color:#9b1c1c;}';
        echo '.fpdms-jobs-placeholder{font-size:13px;color:#4b5563;}';
        echo '.fpdms-overview-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px;}';
        echo '.fpdms-overview-actions .button{display:inline-flex;align-items:center;gap:6px;}';
        echo '.fpdms-overview-actions .button.is-busy{opacity:.6;pointer-events:none;}';
        echo '.fpdms-overview-action-status{min-height:20px;font-size:12px;color:#2563eb;transition:opacity .2s ease;opacity:0;}';
        echo '.fpdms-overview-action-status[data-status="success"]{color:#116149;}';
        echo '.fpdms-overview-action-status[data-status="error"]{color:#9b1c1c;}';
        echo '.fpdms-overview-action-status[data-status="info"]{color:#2563eb;}';
        echo '.fpdms-overview-action-status.is-visible{opacity:1;}';
        echo '.fpdms-overview-error{display:none;margin:0 0 16px;padding:12px;border-left:4px solid #d63638;background:#f8d7da;color:#58151c;border-radius:4px;}';
        echo '.fpdms-overview-error.is-visible{display:block;}';
        echo '.fpdms-overview-spinner{display:none;margin-left:8px;}';
        echo '.fpdms-overview-wrap.is-loading .fpdms-overview-spinner{display:inline-block;animation:fpdms-spin 1s linear infinite;}';
        echo '@keyframes fpdms-spin{from{transform:rotate(0);}to{transform:rotate(360deg);}}';
        echo '@media (prefers-color-scheme:dark){';
        echo '.fpdms-overview-wrap{color:#e5e7eb;}';
        echo '.fpdms-overview-controls,.fpdms-overview-section{background:#111827;border-color:#1f2937;}';
        echo '.fpdms-kpi-card,.fpdms-trend-card,.fpdms-status-item{background:#1f2937;border-color:#374151;}';
        echo '.fpdms-overview-presets button{background:#1f2937;border-color:#374151;color:#e5e7eb;}';
        echo '.fpdms-overview-presets button.is-active{background:#2563eb;border-color:#2563eb;color:#fff;}';
        echo '.fpdms-overview-refresh label{color:#e5e7eb;}';
        echo '.fpdms-overview-refresh-note{color:#9ca3af;}';
        echo '#fpdms-overview-status-updated{color:#9ca3af;}';
        echo '.fpdms-overview-error{background:#7f1d1d;color:#fee2e2;}';
        echo '.fpdms-overview-action-status{color:#60a5fa;}';
        echo '.fpdms-overview-action-status[data-status="success"]{color:#6ee7b7;}';
        echo '.fpdms-overview-action-status[data-status="error"]{color:#fca5a5;}';
        echo '}' ;
        echo '</style>';
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

    private static function renderScript(): void
    {
        echo '<script id="fpdms-overview-script">';
        ?>
(function(){
    const root = document.getElementById('fpdms-overview-root');
    const configEl = document.getElementById('fpdms-overview-config');
    if (!root || !configEl) {
        return;
    }

    let config = {};
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (error) {
        console.error('FPDMS overview: invalid config', error);
        return;
    }

    const clientSelect = document.getElementById('fpdms-overview-client');
    const presetButtons = Array.from(document.querySelectorAll('[data-fpdms-preset]'));
    const presetOptions = presetButtons.map((button) => button.getAttribute('data-fpdms-preset') || '');
    const customContainer = document.getElementById('fpdms-overview-custom');
    const dateFrom = document.getElementById('fpdms-overview-date-from');
    const dateTo = document.getElementById('fpdms-overview-date-to');
    const periodLabel = document.getElementById('fpdms-overview-period-label');
    const errorBox = document.getElementById('fpdms-overview-error');
    const errorMessage = document.getElementById('fpdms-overview-error-message');
    const summaryContainer = document.getElementById('fpdms-overview-kpis');
    const trendsContainer = document.getElementById('fpdms-overview-trends-grid');
    const anomaliesTable = document.querySelector('#fpdms-overview-anomalies tbody');
    const statusList = document.getElementById('fpdms-overview-status-list');
    const runButton = document.getElementById('fpdms-overview-action-run');
    const anomaliesButton = document.getElementById('fpdms-overview-action-anomalies');
    const actionStatus = document.getElementById('fpdms-overview-action-status');
    const refreshToggle = document.getElementById('fpdms-overview-refresh-toggle');
    const refreshSelect = document.getElementById('fpdms-overview-refresh-interval');
    const lastRefreshNote = document.getElementById('fpdms-overview-last-refresh');

    const refreshIntervals = Array.isArray(config.refreshIntervals)
        ? config.refreshIntervals
            .map((interval) => parseInt(interval, 10))
            .filter((interval) => !Number.isNaN(interval) && interval > 0)
        : [60, 120];

    const state = {
        clientId: clientSelect ? clientSelect.value : '',
        preset: 'last7',
        customFrom: '',
        customTo: '',
        autoRefresh: false,
        refreshInterval: config.defaultRefreshInterval || 60,
        lastRefresh: ''
    };

    let refreshTimer = null;

    function normalizePreset(value) {
        return presetOptions.includes(value) ? value : 'last7';
    }

    function clampInterval(value) {
        const fallback = parseInt(config.defaultRefreshInterval, 10) || refreshIntervals[0] || 60;
        const seconds = parseInt(value, 10);
        if (Number.isNaN(seconds) || seconds <= 0) {
            return fallback;
        }
        if (refreshIntervals.includes(seconds)) {
            return seconds;
        }
        const sorted = refreshIntervals.slice().sort((a, b) => Math.abs(a - seconds) - Math.abs(b - seconds));
        return sorted.length ? sorted[0] : fallback;
    }

    function formatTimestamp(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function resetLastRefreshLabel() {
        if (!lastRefreshNote) {
            return;
        }
        const formatted = formatTimestamp(state.lastRefresh);
        if (!formatted) {
            lastRefreshNote.textContent = config.i18n?.lastRefreshNever || 'Last refresh: never';
            return;
        }
        const template = config.i18n?.lastRefresh || 'Last refresh at %s';
        lastRefreshNote.textContent = template.replace('%s', formatted);
    }

    function showRefreshingLabel() {
        if (!lastRefreshNote) {
            return;
        }
        lastRefreshNote.textContent = config.i18n?.refreshing || 'Refreshing…';
    }

    function setLastRefresh(timestamp) {
        state.lastRefresh = timestamp || '';
        resetLastRefreshLabel();
    }

    function clearAutoRefreshTimer() {
        if (refreshTimer) {
            window.clearTimeout(refreshTimer);
            refreshTimer = null;
        }
    }

    function scheduleAutoRefresh() {
        clearAutoRefreshTimer();
        if (!state.autoRefresh) {
            return;
        }
        const interval = clampInterval(state.refreshInterval) * 1000;
        refreshTimer = window.setTimeout(() => {
            loadAll(true);
        }, interval);
    }

    function formatDate(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function computePresetRange(preset) {
        const today = new Date();
        let from;
        let to;

        switch (preset) {
            case 'last14':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 13);
                break;
            case 'last28':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 27);
                break;
            case 'last30':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 29);
                break;
            case 'this_month': {
                to = new Date(today);
                from = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            }
            case 'last_month': {
                const firstDayCurrent = new Date(today.getFullYear(), today.getMonth(), 1);
                to = new Date(firstDayCurrent);
                to.setDate(0);
                from = new Date(firstDayCurrent);
                from.setMonth(from.getMonth() - 1);
                break;
            }
            case 'last7':
            default:
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 6);
                break;
        }

        return {
            from: from ? formatDate(from) : '',
            to: to ? formatDate(to) : ''
        };
    }

    function computeRange() {
        if (state.preset === 'custom') {
            const from = state.customFrom ? new Date(state.customFrom + 'T00:00:00') : null;
            const to = state.customTo ? new Date(state.customTo + 'T00:00:00') : null;

            return {
                from: from ? formatDate(from) : '',
                to: to ? formatDate(to) : ''
            };
        }

        return computePresetRange(state.preset);
    }

    function setPreset(preset, options) {
        const opts = options || {};
        const shouldLoad = opts.load !== false;
        const preserveCustom = !!opts.preserveCustom;
        state.preset = preset;
        presetButtons.forEach((button) => {
            const isActive = button.getAttribute('data-fpdms-preset') === preset;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        if (customContainer) {
            customContainer.hidden = preset !== 'custom';
        }
        if (preset !== 'custom') {
            if (!preserveCustom) {
                state.customFrom = '';
                state.customTo = '';
                if (dateFrom) {
                    dateFrom.value = '';
                }
                if (dateTo) {
                    dateTo.value = '';
                }
            }
            if (shouldLoad) {
                loadAll();
            }
            return;
        }

        if (dateFrom) {
            dateFrom.value = state.customFrom || '';
        }
        if (dateTo) {
            dateTo.value = state.customTo || '';
        }

        if (state.customFrom && state.customTo) {
            if (shouldLoad) {
                loadAll();
            }
        }
    }

    function showError(message) {
        if (!errorBox || !errorMessage) {
            return;
        }
        const fallback = config.i18n?.errorGeneric || 'Error';
        errorMessage.textContent = message || fallback;
        errorBox.classList.add('is-visible');
    }

    function clearError() {
        if (!errorBox) {
            return;
        }
        errorBox.classList.remove('is-visible');
    }

    function setActionBusy(button, busy) {
        if (!button) {
            return;
        }
        button.classList.toggle('is-busy', !!busy);
        button.disabled = !!busy;
    }

    function showActionStatus(type, message) {
        if (!actionStatus) {
            return;
        }
        actionStatus.textContent = message || '';
        if (!message) {
            actionStatus.classList.remove('is-visible');
            actionStatus.removeAttribute('data-status');
            return;
        }
        actionStatus.classList.add('is-visible');
        actionStatus.setAttribute('data-status', type || 'info');
    }

    function formatCountMessage(template, count) {
        if (typeof template !== 'string') {
            return '';
        }
        return template.replace('%d', String(count));
    }

    function renderSparkline(svg, values) {
        if (!svg) {
            return;
        }
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }
        if (!Array.isArray(values) || values.length === 0) {
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', '4');
            text.setAttribute('y', '22');
            text.setAttribute('fill', '#9ca3af');
            text.textContent = config.i18n?.sparklineFallback || 'No data';
            svg.appendChild(text);
            return;
        }
        const max = Math.max.apply(null, values);
        const min = Math.min.apply(null, values);
        const range = max - min || 1;
        const height = 40;
        const width = 100;
        const points = values.map((value, index) => {
            const x = values.length === 1 ? width : (width / (values.length - 1)) * index;
            const normalized = (value - min) / range;
            const y = height - (normalized * 32 + 4);
            return { x, y };
        });
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        let d = '';
        points.forEach((point, index) => {
            d += (index === 0 ? 'M' : 'L') + point.x.toFixed(2) + ' ' + point.y.toFixed(2) + ' ';
        });
        path.setAttribute('d', d.trim());
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', '#2563eb');
        path.setAttribute('stroke-width', '2');
        svg.appendChild(path);
    }

    function updateSummary(data) {
        const summary = data && data.summary ? data.summary : data;
        if (!summary) {
            return;
        }
        const refreshedAt = summary.refreshed_at || (data && data.refreshed_at);
        if (refreshedAt) {
            setLastRefresh(refreshedAt);
        } else if (!state.lastRefresh) {
            setLastRefresh(new Date().toISOString());
        }
        if (summary.period && periodLabel) {
            const from = summary.period.from || '';
            const to = summary.period.to || '';
            periodLabel.textContent = from && to ? from + ' → ' + to : config.i18n?.loading || '';
        }
        if (!summaryContainer || !Array.isArray(summary.kpis)) {
            return;
        }
        const cards = Array.from(summaryContainer.querySelectorAll('.fpdms-kpi-card'));
        cards.forEach((card) => {
            const metric = card.getAttribute('data-metric');
            const kpi = summary.kpis.find((item) => item.metric === metric);
            const valueEl = card.querySelector('[data-role="value"]');
            const deltaEl = card.querySelector('[data-role="delta"]');
            const previousEl = card.querySelector('[data-role="previous"]');
            const sparklineSvg = card.querySelector('svg');
            if (!kpi) {
                if (valueEl) {
                    valueEl.textContent = '--';
                }
                if (deltaEl) {
                    deltaEl.textContent = '0%';
                    deltaEl.setAttribute('data-direction', 'flat');
                }
                if (previousEl) {
                    previousEl.textContent = config.i18n?.previous + ': --';
                }
                renderSparkline(sparklineSvg, []);
                return;
            }
            if (valueEl) {
                valueEl.textContent = kpi.formatted_value || String(kpi.value || '--');
            }
            if (deltaEl) {
                const delta = kpi.delta || {};
                const formatted = delta.formatted || '0%';
                const direction = delta.direction || 'flat';
                deltaEl.textContent = formatted;
                deltaEl.setAttribute('data-direction', direction);
            }
            if (previousEl) {
                const prev = kpi.formatted_previous || String(kpi.previous_value ?? '--');
                previousEl.textContent = (config.i18n?.previous || 'Previous') + ': ' + prev;
            }
            renderSparkline(sparklineSvg, Array.isArray(kpi.sparkline) ? kpi.sparkline : []);
        });
        updateTrends(summary);
    }

    function updateTrends(summary) {
        if (!trendsContainer || !summary || !Array.isArray(summary.kpis)) {
            return;
        }
        const kpiIndex = {};
        summary.kpis.forEach((kpi) => {
            if (kpi && kpi.metric) {
                kpiIndex[kpi.metric] = kpi;
            }
        });
        Array.from(trendsContainer.querySelectorAll('.fpdms-trend-card')).forEach((card) => {
            const metric = card.getAttribute('data-metric');
            const kpi = kpiIndex[metric];
            const svg = card.querySelector('svg');
            const meta = card.querySelector('[data-role="trend-meta"]');
            if (!kpi) {
                if (meta) {
                    meta.textContent = config.i18n?.sparklineFallback || 'No data';
                }
                renderSparkline(svg, []);
                return;
            }
            if (meta) {
                meta.textContent = (config.i18n?.previous || 'Previous') + ': ' + (kpi.formatted_previous || '--');
            }
            renderSparkline(svg, Array.isArray(kpi.sparkline) ? kpi.sparkline : []);
        });
    }

    function updateStatus(data) {
        if (!statusList) {
            return;
        }
        statusList.innerHTML = '';
        const statusUpdated = document.getElementById('fpdms-overview-status-updated');
        if (statusUpdated) {
            const formatted = data && data.checked_at ? formatTimestamp(data.checked_at) : '';
            if (formatted) {
                const template = config.i18n?.statusChecked || 'Status checked at %s';
                statusUpdated.textContent = template.replace('%s', formatted);
                statusUpdated.hidden = false;
            } else {
                statusUpdated.textContent = '';
                statusUpdated.hidden = true;
            }
        }
        const entries = data && Array.isArray(data.sources) ? data.sources : (Array.isArray(data) ? data : []);
        if (!entries.length) {
            const placeholder = document.createElement('div');
            placeholder.className = 'fpdms-status-item';
            placeholder.textContent = config.i18n?.noData || 'No data available.';
            statusList.appendChild(placeholder);
            return;
        }
        entries.forEach((entry) => {
            const item = document.createElement('div');
            item.className = 'fpdms-status-item';
            const label = document.createElement('span');
            label.className = 'fpdms-status-label';
            label.textContent = entry.label || entry.type || '';
            const state = document.createElement('span');
            state.className = 'fpdms-status-state';
            const stateValue = entry.state || 'ok';
            state.setAttribute('data-state', stateValue);
            const stateLabel = entry.state_label || (stateValue ? String(stateValue).toUpperCase() : '');
            state.textContent = stateLabel;
            const message = document.createElement('span');
            message.className = 'fpdms-status-message';
            message.textContent = entry.message || '';
            const updated = document.createElement('span');
            updated.className = 'fpdms-status-updated';
            if (entry.last_updated) {
                const template = config.i18n?.statusUpdated || 'Last data update: %s';
                updated.textContent = template.replace('%s', entry.last_updated);
            }
            item.appendChild(label);
            item.appendChild(state);
            if (entry.message) {
                item.appendChild(message);
            }
            if (entry.last_updated) {
                item.appendChild(updated);
            }
            statusList.appendChild(item);
        });
    }

    function updateAnomalies(data) {
        if (!anomaliesTable) {
            return;
        }
        anomaliesTable.innerHTML = '';
        const items = data && Array.isArray(data.anomalies) ? data.anomalies : (Array.isArray(data) ? data : []);
        if (!items.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.textContent = config.i18n?.noData || 'No data available.';
            row.appendChild(cell);
            anomaliesTable.appendChild(row);
            return;
        }
        items.slice(0, 10).forEach((item) => {
            const row = document.createElement('tr');
            const severity = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'fpdms-anomaly-badge';
            const variant = item.severity_variant || item.variant || 'neutral';
            badge.setAttribute('data-variant', variant);
            badge.textContent = item.severity_label || item.severity || variant;
            severity.appendChild(badge);
            const metric = document.createElement('td');
            metric.textContent = item.metric_label || item.metric || '';
            const change = document.createElement('td');
            change.textContent = item.delta_formatted || item.delta || '';
            const score = document.createElement('td');
            score.textContent = item.score !== undefined ? String(item.score) : '';
            const when = document.createElement('td');
            when.textContent = item.occurred_at || item.time || '';
            const actions = document.createElement('td');
            if (item.url) {
                const link = document.createElement('a');
                link.href = item.url;
                link.textContent = config.i18n?.anomalyAction || 'Resolve / Note';
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                actions.appendChild(link);
            } else {
                actions.textContent = config.i18n?.anomalyAction || 'Resolve / Note';
            }
            row.appendChild(severity);
            row.appendChild(metric);
            row.appendChild(change);
            row.appendChild(score);
            row.appendChild(when);
            row.appendChild(actions);
            anomaliesTable.appendChild(row);
        });
    }

    function request(url, params) {
        if (!url) {
            return Promise.resolve({});
        }
        const endpoint = new URL(url, window.location.origin);
        if (params) {
            Object.keys(params).forEach((key) => {
                if (params[key]) {
                    endpoint.searchParams.set(key, params[key]);
                }
            });
        }
        return fetch(endpoint.toString(), {
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': config.nonce || ''
            }
        }).then(async (response) => {
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const message = payload && payload.message ? payload.message : 'HTTP ' + response.status;
                throw new Error(message);
            }
            return response.json();
        });
    }

    function postRequest(url, payload) {
        if (!url) {
            return Promise.reject(new Error('Missing endpoint'));
        }

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce || ''
            },
            body: JSON.stringify(payload || {})
        }).then(async (response) => {
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const message = body && body.message ? body.message : 'HTTP ' + response.status;
                throw new Error(message);
            }

            return response.json();
        });
    }

    function loadAll(fromAuto) {
        if (!state.clientId) {
            return;
        }
        clearError();
        clearAutoRefreshTimer();
        const isAuto = !!fromAuto;
        if (!isAuto) {
            root.classList.add('is-loading');
        }
        showRefreshingLabel();
        const range = computeRange();
        const summaryParams = {
            client_id: state.clientId,
            preset: state.preset,
            auto_refresh: state.autoRefresh ? '1' : '0',
            refresh_interval: clampInterval(state.refreshInterval)
        };
        const anomaliesParams = { client_id: state.clientId };
        if (range.from) {
            summaryParams.from = range.from;
            anomaliesParams.from = range.from;
        }
        if (range.to) {
            summaryParams.to = range.to;
            anomaliesParams.to = range.to;
        }
        const tasks = [
            request(config.endpoints?.summary, summaryParams)
                .then((data) => {
                    updateSummary(data);
                    return data;
                })
                .catch((error) => {
                    console.error('FPDMS overview summary error', error);
                    showError(error.message);
                }),
            request(config.endpoints?.status, { client_id: state.clientId })
                .then(updateStatus)
                .catch((error) => {
                    console.error('FPDMS overview status error', error);
                    showError(error.message);
                }),
            request(config.endpoints?.anomalies, anomaliesParams)
                .then(updateAnomalies)
                .catch((error) => {
                    console.warn('FPDMS overview anomalies unavailable', error);
                })
        ];
        Promise.allSettled(tasks).then(() => {
            if (!isAuto) {
                root.classList.remove('is-loading');
            }
            resetLastRefreshLabel();
            scheduleAutoRefresh();
        });
    }

    const prefs = (config.preferences && typeof config.preferences === 'object') ? config.preferences : {};

    if (clientSelect) {
        const preferredClient = prefs.client_id ? String(prefs.client_id) : '';
        if (preferredClient) {
            const match = Array.from(clientSelect.options || []).find((option) => option.value === preferredClient);
            if (match) {
                clientSelect.value = preferredClient;
            }
        }
        state.clientId = clientSelect.value;
    }

    state.preset = normalizePreset(typeof prefs.preset === 'string' ? prefs.preset : state.preset);
    state.customFrom = typeof prefs.from === 'string' ? prefs.from : '';
    state.customTo = typeof prefs.to === 'string' ? prefs.to : '';
    state.autoRefresh = !!prefs.auto_refresh;
    const storedInterval = typeof prefs.refresh_interval === 'number'
        ? prefs.refresh_interval
        : parseInt(prefs.refresh_interval || state.refreshInterval, 10);
    state.refreshInterval = clampInterval(storedInterval);

    if (refreshToggle) {
        refreshToggle.checked = state.autoRefresh;
        refreshToggle.setAttribute('aria-label', config.i18n?.autoRefresh || 'Auto-refresh');
    }
    if (refreshSelect) {
        refreshSelect.value = String(clampInterval(state.refreshInterval));
        refreshSelect.disabled = !state.autoRefresh;
        refreshSelect.setAttribute('aria-label', config.i18n?.autoRefreshInterval || 'Auto-refresh interval');
    }

    resetLastRefreshLabel();
    setPreset(state.preset, { load: false, preserveCustom: true });

    if (clientSelect) {
        clientSelect.addEventListener('change', () => {
            state.clientId = clientSelect.value;
            loadAll();
        });
    }

    presetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const preset = button.getAttribute('data-fpdms-preset') || 'last7';
            setPreset(preset);
        });
    });

    if (dateFrom) {
        dateFrom.addEventListener('change', () => {
            state.customFrom = dateFrom.value;
            if (state.preset === 'custom' && state.customTo) {
                loadAll();
            }
        });
    }

    if (dateTo) {
        dateTo.addEventListener('change', () => {
            state.customTo = dateTo.value;
            if (state.preset === 'custom' && state.customFrom) {
                loadAll();
            }
        });
    }

    if (refreshToggle) {
        refreshToggle.addEventListener('change', () => {
            state.autoRefresh = refreshToggle.checked;
            if (refreshSelect) {
                refreshSelect.disabled = !state.autoRefresh;
            }
            if (!state.autoRefresh) {
                clearAutoRefreshTimer();
            }
            loadAll();
        });
    }

    if (refreshSelect) {
        refreshSelect.addEventListener('change', () => {
            const interval = clampInterval(parseInt(refreshSelect.value, 10));
            state.refreshInterval = interval;
            refreshSelect.value = String(interval);
            if (state.autoRefresh) {
                loadAll(true);
            } else {
                loadAll();
            }
        });
    }

    if (runButton) {
        runButton.addEventListener('click', () => {
            if (!state.clientId) {
                return;
            }

            setActionBusy(runButton, true);
            showActionStatus('info', config.i18n?.runPending || 'Queuing report…');

            const range = computeRange();
            const payload = { client_id: state.clientId, process: 'now' };
            if (range.from) {
                payload.from = range.from;
            }
            if (range.to) {
                payload.to = range.to;
            }

            postRequest((config.actions && config.actions.run) || (config.endpoints && config.endpoints.run), payload)
                .then(() => {
                    showActionStatus('success', config.i18n?.runQueued || 'Report run queued.');
                    loadAll();
                })
                .catch((error) => {
                    console.error('FPDMS overview run error', error);
                    showActionStatus('error', error.message || config.i18n?.actionError || 'Action failed. Try again.');
                })
                .finally(() => {
                    setActionBusy(runButton, false);
                });
        });
    }

    if (anomaliesButton) {
        anomaliesButton.addEventListener('click', () => {
            if (!state.clientId) {
                return;
            }

            setActionBusy(anomaliesButton, true);
            showActionStatus('info', config.i18n?.anomalyRunning || 'Evaluating anomalies…');

            const range = computePresetRange('last30');
            const payload = { client_id: state.clientId };
            if (range.from) {
                payload.from = range.from;
            }
            if (range.to) {
                payload.to = range.to;
            }

            postRequest((config.actions && config.actions.anomalies) || (config.endpoints && config.endpoints.anomalies), payload)
                .then((data) => {
                    const count = data && typeof data.count === 'number' ? data.count : 0;
                    if (data && Array.isArray(data.anomalies)) {
                        updateAnomalies(data);
                    }
                    if (count > 0) {
                        const message = formatCountMessage(config.i18n?.anomalyComplete || 'Anomaly evaluation found %d signals.', count);
                        showActionStatus('success', message);
                    } else {
                        showActionStatus('success', config.i18n?.anomalyNone || 'No anomalies detected in the last 30 days.');
                    }
                })
                .catch((error) => {
                    console.error('FPDMS overview anomaly evaluation error', error);
                    showActionStatus('error', error.message || config.i18n?.actionError || 'Action failed. Try again.');
                })
                .finally(() => {
                    setActionBusy(anomaliesButton, false);
                });
        });
    }

    // Initial load
    if (state.clientId) {
        loadAll();
    }
})();
<?php
        echo '</script>';
    }
}

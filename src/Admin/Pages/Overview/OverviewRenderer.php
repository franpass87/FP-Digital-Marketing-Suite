<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Overview;

use function __;
use function add_query_arg;
use function admin_url;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function sprintf;
use function ucfirst;

/**
 * Renders UI components for the Overview page
 */
class OverviewRenderer
{
    /**
     * Render error banner
     */
    public static function renderErrorBanner(): void
    {
        echo '<div id="fpdms-overview-error" class="fpdms-overview-error" role="alert">';
        echo '<strong>' . esc_html__('Unable to load overview data.', 'fp-dms') . '</strong> ';
        echo '<span id="fpdms-overview-error-message">' . esc_html__('Retry in a moment.', 'fp-dms') . '</span>';
        echo '</div>';
    }

    /**
     * Render filter controls
     *
     * @param array<int, array{id: int, name: string}> $clients
     * @param int[] $refreshIntervals
     */
    public static function renderFilters(array $clients, array $refreshIntervals): void
    {
        $presets = [
            'last7' => esc_html__('Last 7 days', 'fp-dms'),
            'last14' => esc_html__('Last 14 days', 'fp-dms'),
            'last28' => esc_html__('Last 28 days', 'fp-dms'),
            'last30' => esc_html__('Last 30 days', 'fp-dms'),
            'this_month' => esc_html__('This month', 'fp-dms'),
            'last_month' => esc_html__('Last month', 'fp-dms'),
            'custom' => esc_html__('Custom', 'fp-dms'),
        ];

        echo '<div class="fpdms-overview-controls" role="region" aria-label="' . esc_attr__('Overview filters', 'fp-dms') . '">';

        // Client selector
        echo '<div class="fpdms-overview-field">';
        echo '<label for="fpdms-overview-client">' . esc_html__('Client', 'fp-dms') . '</label>';
        echo '<select id="fpdms-overview-client">';
        foreach ($clients as $client) {
            echo '<option value="' . esc_attr((string) $client['id']) . '">' . esc_html($client['name']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Date presets
        echo '<div class="fpdms-overview-field" style="flex:1;min-width:260px;">';
        echo '<span class="fpdms-label">' . esc_html__('Date range', 'fp-dms') . '</span>';
        echo '<div class="fpdms-overview-presets" role="group" aria-label="' . esc_attr__('Date presets', 'fp-dms') . '">';
        foreach ($presets as $key => $label) {
            echo '<button type="button" data-fpdms-preset="' . esc_attr($key) . '" aria-pressed="false">' . $label . '</button>';
        }
        echo '</div>';

        // Custom date range
        echo '<div class="fpdms-overview-custom" id="fpdms-overview-custom" hidden>';
        echo '<label>' . esc_html__('From', 'fp-dms') . '<input type="date" id="fpdms-overview-date-from"></label>';
        echo '<label>' . esc_html__('To', 'fp-dms') . '<input type="date" id="fpdms-overview-date-to"></label>';
        echo '</div>';
        echo '</div>';

        // Auto-refresh controls
        echo '<div class="fpdms-overview-refresh" aria-live="polite">';
        echo '<label for="fpdms-overview-refresh-toggle">';
        echo '<input type="checkbox" id="fpdms-overview-refresh-toggle">';
        echo '<span>' . esc_html__('Auto-refresh', 'fp-dms') . '</span>';
        echo '</label>';
        echo '<select id="fpdms-overview-refresh-interval" aria-label="' . esc_attr__('Auto-refresh interval', 'fp-dms') . '">';
        foreach ($refreshIntervals as $seconds) {
            echo '<option value="' . esc_attr((string) $seconds) . '">' . esc_html(sprintf(/* translators: %d is seconds */ __('Every %d seconds', 'fp-dms'), $seconds)) . '</option>';
        }
        echo '</select>';
        echo '<span class="fpdms-overview-refresh-note" id="fpdms-overview-last-refresh">' . esc_html__('Last refresh: never', 'fp-dms') . '</span>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render summary section (KPIs)
     */
    public static function renderSummarySection(): void
    {
        echo '<section class="fpdms-section fpdms-overview-section" aria-labelledby="fpdms-overview-kpis-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-kpis-heading">' . esc_html__('Key metrics', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-overview-period" id="fpdms-overview-period"><span id="fpdms-overview-period-label">' . esc_html__('Loading…', 'fp-dms') . '</span><span class="fpdms-overview-spinner">&#x27F3;</span></div>';
        echo '</header>';
        echo '<div class="fpdms-overview-kpis" id="fpdms-overview-kpis" aria-live="polite">';
        foreach (OverviewConfigService::KPI_LABELS as $metric => $label) {
            self::renderKpiCard($metric, $label);
        }
        echo '</div>';
        echo '</section>';
    }

    /**
     * Render a single KPI card
     */
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
     * Render trend section
     */
    public static function renderTrendSection(): void
    {
        echo '<section class="fpdms-section fpdms-overview-section" aria-labelledby="fpdms-overview-trends-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-trends-heading">' . esc_html__('Trend snapshots', 'fp-dms') . '</h2>';
        echo '<span class="fpdms-overview-period" id="fpdms-overview-trend-period">' . esc_html__('Daily values over the selected window.', 'fp-dms') . '</span>';
        echo '</header>';
        echo '<div class="fpdms-overview-trends" id="fpdms-overview-trends-grid">';
        foreach (OverviewConfigService::TREND_METRICS as $metric) {
            $label = OverviewConfigService::KPI_LABELS[$metric] ?? ucfirst($metric);
            echo '<article class="fpdms-trend-card" data-metric="' . esc_attr($metric) . '">';
            echo '<h3>' . esc_html__($label, 'fp-dms') . '</h3>';
            echo '<svg role="img" aria-label="' . esc_attr(sprintf(esc_html__('%s sparkline', 'fp-dms'), esc_html__($label, 'fp-dms'))) . '" viewBox="0 0 100 40"></svg>';
            echo '<p class="fpdms-kpi-previous" data-role="trend-meta">' . esc_html__('Awaiting data…', 'fp-dms') . '</p>';
            echo '</article>';
        }
        echo '</div>';
        echo '</section>';
    }

    /**
     * Render anomalies section
     */
    public static function renderAnomaliesSection(): void
    {
        echo '<section class="fpdms-section fpdms-overview-section" aria-labelledby="fpdms-overview-anomalies-heading">';
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

    /**
     * Render data source status section
     */
    public static function renderStatusSection(): void
    {
        echo '<section class="fpdms-section fpdms-overview-section" aria-labelledby="fpdms-overview-status-heading">';
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

    /**
     * Render jobs and schedules section
     */
    public static function renderJobsSection(): void
    {
        echo '<section class="fpdms-section fpdms-overview-section" aria-labelledby="fpdms-overview-jobs-heading">';
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

    /**
     * Render configuration as JSON for JavaScript
     *
     * @param array<string, mixed> $config
     */
    public static function renderConfig(array $config): void
    {
        $json = \FP\DMS\Support\Wp::jsonEncode($config) ?: '[]';
        echo '<script type="application/json" id="fpdms-overview-config">' . $json . '</script>';
    }
}

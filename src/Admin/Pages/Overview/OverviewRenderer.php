<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Overview;

use FP\DMS\Admin\Pages\Shared\HelpIcon;

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

        // Sync Data Sources button
        echo '<div class="fpdms-overview-sync">';
        echo '<button type="button" id="fpdms-sync-datasources-overview" class="button button-primary" style="display:flex;align-items:center;gap:6px;">';
        echo '<span class="dashicons dashicons-update" style="margin-top:3px;"></span>';
        echo esc_html__('Sync Data Sources', 'fp-dms');
        echo '</button>';
        echo '<div id="fpdms-sync-feedback-overview" style="margin-left:12px;display:none;"></div>';
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
        echo '<header style="display:flex;justify-content:space-between;align-items:center;">';
        echo '<div>';
        echo '<h2 id="fpdms-overview-kpis-heading" style="margin:0;">' . esc_html__('Key metrics', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-overview-period" id="fpdms-overview-period"><span id="fpdms-overview-period-label">' . esc_html__('Loading…', 'fp-dms') . '</span><span class="fpdms-overview-spinner">&#x27F3;</span></div>';
        echo '</div>';
        echo '<button type="button" id="fpdms-customize-metrics" class="button" style="display:flex;align-items:center;gap:6px;">';
        echo '<span class="dashicons dashicons-admin-settings" style="margin-top:3px;"></span>';
        echo esc_html__('Customize Metrics', 'fp-dms');
        echo '</button>';
        echo '</header>';
        
        // Container principale con ID per compatibilità JavaScript
        echo '<div id="fpdms-overview-kpis">';
        
        // Sezione GA4 (Google Analytics 4)
        echo '<div class="fpdms-metrics-section" data-section="ga4">';
        echo '<h3 class="fpdms-metrics-section-title"><span class="dashicons dashicons-chart-area"></span>' . esc_html__('Google Analytics 4', 'fp-dms') . '</h3>';
        echo '<div class="fpdms-overview-kpis-grid" aria-live="polite">';
        $ga4Metrics = ['users', 'sessions', 'pageviews', 'events', 'new_users', 'total_users'];
        foreach ($ga4Metrics as $metric) {
            if (isset(OverviewConfigService::KPI_LABELS[$metric])) {
                self::renderKpiCard($metric, OverviewConfigService::KPI_LABELS[$metric]);
            }
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione GSC (Google Search Console)
        echo '<div class="fpdms-metrics-section" data-section="gsc" style="margin-top:32px;">';
        echo '<h3 class="fpdms-metrics-section-title"><span class="dashicons dashicons-search"></span>' . esc_html__('Google Search Console', 'fp-dms') . '</h3>';
        echo '<div class="fpdms-overview-kpis-grid" aria-live="polite">';
        $gscMetrics = ['gsc_clicks', 'gsc_impressions', 'ctr', 'position'];
        foreach ($gscMetrics as $metric) {
            if (isset(OverviewConfigService::KPI_LABELS[$metric])) {
                self::renderKpiCard($metric, OverviewConfigService::KPI_LABELS[$metric]);
            }
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione Google Ads
        echo '<div class="fpdms-metrics-section" data-section="google-ads" style="margin-top:32px;">';
        echo '<h3 class="fpdms-metrics-section-title"><span class="dashicons dashicons-google"></span>' . esc_html__('Google Ads', 'fp-dms') . '</h3>';
        echo '<div class="fpdms-overview-kpis-grid" aria-live="polite">';
        $googleAdsMetrics = ['google_clicks', 'google_impressions', 'google_cost', 'google_conversions'];
        foreach ($googleAdsMetrics as $metric) {
            if (isset(OverviewConfigService::KPI_LABELS[$metric])) {
                self::renderKpiCard($metric, OverviewConfigService::KPI_LABELS[$metric]);
            }
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione Meta Ads
        echo '<div class="fpdms-metrics-section" data-section="meta-ads" style="margin-top:32px;">';
        echo '<h3 class="fpdms-metrics-section-title"><span class="dashicons dashicons-facebook"></span>' . esc_html__('Meta Ads (Facebook/Instagram)', 'fp-dms') . '</h3>';
        echo '<div class="fpdms-overview-kpis-grid" aria-live="polite">';
        $metaAdsMetrics = ['meta_clicks', 'meta_impressions', 'meta_cost', 'meta_conversions', 'meta_revenue'];
        foreach ($metaAdsMetrics as $metric) {
            if (isset(OverviewConfigService::KPI_LABELS[$metric])) {
                self::renderKpiCard($metric, OverviewConfigService::KPI_LABELS[$metric]);
            }
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione Revenue Totale
        echo '<div class="fpdms-metrics-section" data-section="revenue" style="margin-top:32px;">';
        echo '<h3 class="fpdms-metrics-section-title"><span class="dashicons dashicons-money-alt"></span>' . esc_html__('Total Revenue', 'fp-dms') . '</h3>';
        echo '<div class="fpdms-overview-kpis-grid" aria-live="polite">';
        $revenueMetrics = ['revenue'];
        foreach ($revenueMetrics as $metric) {
            if (isset(OverviewConfigService::KPI_LABELS[$metric])) {
                self::renderKpiCard($metric, OverviewConfigService::KPI_LABELS[$metric]);
            }
        }
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // Fine #fpdms-overview-kpis
        
        // CSS per le sezioni
        echo '<style>
        .fpdms-metrics-section{margin-top:24px;}
        .fpdms-metrics-section:first-child{margin-top:0;}
        .fpdms-metrics-section-title{font-size:16px;font-weight:600;color:#333;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;padding-bottom:8px;border-bottom:2px solid #e0e0e0;}
        .fpdms-metrics-section-title .dashicons{color:#2271b1;font-size:20px;}
        .fpdms-overview-kpis-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;}
        </style>';
        
        // Modal per selezione metriche
        self::renderMetricsModal();
        
        echo '</section>';
    }

    /**
     * Render metrics customization modal
     */
    private static function renderMetricsModal(): void
    {
        echo '<div id="fpdms-metrics-modal" class="fpdms-modal" style="display:none;">';
        echo '<div class="fpdms-modal-overlay"></div>';
        echo '<div class="fpdms-modal-content">';
        echo '<div class="fpdms-modal-header">';
        echo '<h3>' . esc_html__('Customize Metrics', 'fp-dms') . '</h3>';
        echo '<button type="button" class="fpdms-modal-close" aria-label="' . esc_attr__('Close', 'fp-dms') . '">&times;</button>';
        echo '</div>';
        echo '<div class="fpdms-modal-body">';
        echo '<p class="description">' . esc_html__('Select which metrics you want to display in the Overview dashboard.', 'fp-dms') . '</p>';
        
        // Preset per tipo di business
        echo '<div class="fpdms-business-presets" style="margin-bottom:24px;padding:16px;background:#f8f9fa;border-radius:4px;">';
        echo '<label style="display:block;font-weight:600;margin-bottom:8px;">' . esc_html__('Quick Preset (Business Type)', 'fp-dms') . '</label>';
        echo '<select id="fpdms-business-preset" class="regular-text">';
        echo '<option value="">' . esc_html__('-- Select a preset --', 'fp-dms') . '</option>';
        echo '<option value="bnb">' . esc_html__('B&B / Affittacamere', 'fp-dms') . '</option>';
        echo '<option value="hotel">' . esc_html__('Hotel / Resort', 'fp-dms') . '</option>';
        echo '<option value="winery">' . esc_html__('Cantina / Azienda Vinicola', 'fp-dms') . '</option>';
        echo '<option value="restaurant">' . esc_html__('Ristorante / Agriturismo', 'fp-dms') . '</option>';
        echo '<option value="ecommerce">' . esc_html__('E-commerce / Shop Online', 'fp-dms') . '</option>';
        echo '<option value="leadgen">' . esc_html__('Lead Generation / Servizi', 'fp-dms') . '</option>';
        echo '<option value="tourism">' . esc_html__('Agenzia Viaggi / Tour Operator', 'fp-dms') . '</option>';
        echo '<option value="custom">' . esc_html__('Personalizzato (seleziona manualmente)', 'fp-dms') . '</option>';
        echo '</select>';
        echo '<p class="description" style="margin-top:8px;">' . esc_html__('Seleziona un preset per configurare automaticamente le metriche più rilevanti per il tuo tipo di attività.', 'fp-dms') . '</p>';
        echo '</div>';
        
        // Sezione GA4
        echo '<div class="fpdms-metrics-category">';
        echo '<h4><span class="dashicons dashicons-chart-area"></span>' . esc_html__('Google Analytics 4', 'fp-dms') . '</h4>';
        echo '<div class="fpdms-metrics-grid">';
        $ga4ModalMetrics = ['users' => 'Users', 'sessions' => 'Sessions', 'pageviews' => 'Pageviews', 'events' => 'Events', 'new_users' => 'New Users', 'total_users' => 'Total Users'];
        foreach ($ga4ModalMetrics as $metric => $label) {
            echo '<label class="fpdms-metric-option">';
            echo '<input type="checkbox" name="fpdms_visible_metrics[]" value="' . esc_attr($metric) . '" checked>';
            echo '<span>' . esc_html__($label, 'fp-dms') . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione GSC
        echo '<div class="fpdms-metrics-category">';
        echo '<h4><span class="dashicons dashicons-search"></span>' . esc_html__('Google Search Console', 'fp-dms') . '</h4>';
        echo '<div class="fpdms-metrics-grid">';
        $gscModalMetrics = ['gsc_clicks' => 'GSC Clicks', 'gsc_impressions' => 'GSC Impressions', 'ctr' => 'CTR (%)', 'position' => 'Avg Position'];
        foreach ($gscModalMetrics as $metric => $label) {
            echo '<label class="fpdms-metric-option">';
            echo '<input type="checkbox" name="fpdms_visible_metrics[]" value="' . esc_attr($metric) . '" checked>';
            echo '<span>' . esc_html__($label, 'fp-dms') . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione Google Ads
        echo '<div class="fpdms-metrics-category">';
        echo '<h4><span class="dashicons dashicons-google"></span>' . esc_html__('Google Ads', 'fp-dms') . '</h4>';
        echo '<div class="fpdms-metrics-grid">';
        $googleAdsModalMetrics = ['google_clicks' => 'Clicks', 'google_impressions' => 'Impressions', 'google_cost' => 'Cost', 'google_conversions' => 'Conversions'];
        foreach ($googleAdsModalMetrics as $metric => $label) {
            echo '<label class="fpdms-metric-option">';
            echo '<input type="checkbox" name="fpdms_visible_metrics[]" value="' . esc_attr($metric) . '" checked>';
            echo '<span>' . esc_html__($label, 'fp-dms') . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione Meta Ads
        echo '<div class="fpdms-metrics-category">';
        echo '<h4><span class="dashicons dashicons-facebook"></span>' . esc_html__('Meta Ads', 'fp-dms') . '</h4>';
        echo '<div class="fpdms-metrics-grid">';
        $metaAdsModalMetrics = ['meta_clicks' => 'Clicks', 'meta_impressions' => 'Impressions', 'meta_cost' => 'Cost', 'meta_conversions' => 'Conversions', 'meta_revenue' => 'Revenue'];
        foreach ($metaAdsModalMetrics as $metric => $label) {
            echo '<label class="fpdms-metric-option">';
            echo '<input type="checkbox" name="fpdms_visible_metrics[]" value="' . esc_attr($metric) . '" checked>';
            echo '<span>' . esc_html__($label, 'fp-dms') . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
        
        // Sezione Revenue Totale
        echo '<div class="fpdms-metrics-category">';
        echo '<h4><span class="dashicons dashicons-money-alt"></span>' . esc_html__('Total Revenue', 'fp-dms') . '</h4>';
        echo '<div class="fpdms-metrics-grid">';
        echo '<label class="fpdms-metric-option">';
        echo '<input type="checkbox" name="fpdms_visible_metrics[]" value="revenue" checked>';
        echo '<span>' . esc_html__('Revenue', 'fp-dms') . '</span>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '<div class="fpdms-modal-footer">';
        echo '<button type="button" class="button button-secondary fpdms-modal-cancel">' . esc_html__('Cancel', 'fp-dms') . '</button>';
        echo '<button type="button" class="button button-primary" id="fpdms-save-metrics">' . esc_html__('Save Selection', 'fp-dms') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // CSS inline per il modal
        echo '<style>
        .fpdms-modal{position:fixed;top:0;left:0;width:100%;height:100%;z-index:100000;display:flex;align-items:center;justify-content:center;}
        .fpdms-modal-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);cursor:pointer;}
        .fpdms-modal-content{position:relative;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.2);max-width:700px;width:90%;max-height:80vh;overflow:auto;z-index:1;}
        .fpdms-modal-header{padding:20px 24px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center;}
        .fpdms-modal-header h3{margin:0;font-size:18px;}
        .fpdms-modal-close{background:none;border:none;font-size:28px;line-height:1;cursor:pointer;color:#666;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:4px;}
        .fpdms-modal-close:hover{background:#f0f0f0;color:#000;}
        .fpdms-modal-body{padding:24px;}
        .fpdms-modal-footer{padding:16px 24px;border-top:1px solid #ddd;display:flex;justify-content:flex-end;gap:8px;}
        .fpdms-metrics-category{margin-bottom:24px;}
        .fpdms-metrics-category:last-child{margin-bottom:0;}
        .fpdms-metrics-category h4{margin:0 0 12px 0;font-size:15px;font-weight:600;color:#333;display:flex;align-items:center;gap:8px;padding-bottom:8px;border-bottom:1px solid #e0e0e0;}
        .fpdms-metrics-category h4 .dashicons{color:#2271b1;font-size:18px;}
        .fpdms-metrics-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;}
        .fpdms-metric-option{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #ddd;border-radius:4px;cursor:pointer;transition:all 0.2s;}
        .fpdms-metric-option:hover{background:#f5f5f5;border-color:#2271b1;}
        .fpdms-metric-option input{margin:0;}
        .fpdms-metric-option span{font-weight:500;font-size:14px;}
        </style>';
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
     * Render AI Insights section
     */
    public static function renderAIInsightsSection(): void
    {
        echo '<section class="fpdms-section fpdms-overview-section fpdms-ai-insights-section" aria-labelledby="fpdms-overview-ai-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-ai-heading">';
        echo '<span class="dashicons dashicons-lightbulb" style="color:#f0b429;"></span>';
        echo esc_html__('AI Insights', 'fp-dms');
        HelpIcon::render(HelpIcon::getCommonHelp('ai_insights'));
        echo '</h2>';
        echo '<span class="fpdms-overview-period" id="fpdms-overview-ai-meta">' . esc_html__('Interpretazione intelligente dei dati del periodo.', 'fp-dms') . '</span>';
        echo '</header>';
        
        echo '<div class="fpdms-ai-insights-container" id="fpdms-ai-insights-container">';
        
        // Loading state
        echo '<div class="fpdms-ai-insights-loading" id="fpdms-ai-insights-loading">';
        echo '<div class="fpdms-spinner"></div>';
        echo '<p>' . esc_html__('Sto analizzando i dati con l\'intelligenza artificiale...', 'fp-dms') . '</p>';
        echo '</div>';
        
        // Content area
        echo '<div class="fpdms-ai-insights-content" id="fpdms-ai-insights-content" style="display:none;">';
        echo '<div class="fpdms-ai-insight-card">';
        echo '<h3 class="fpdms-ai-insight-title">';
        echo '<span class="dashicons dashicons-chart-line"></span>';
        echo esc_html__('Analisi Performance', 'fp-dms');
        echo '</h3>';
        echo '<div class="fpdms-ai-insight-text" id="fpdms-ai-performance-analysis"></div>';
        echo '</div>';
        
        echo '<div class="fpdms-ai-insight-card">';
        echo '<h3 class="fpdms-ai-insight-title">';
        echo '<span class="dashicons dashicons-trending-up"></span>';
        echo esc_html__('Trend Rilevati', 'fp-dms');
        echo '</h3>';
        echo '<div class="fpdms-ai-insight-text" id="fpdms-ai-trend-analysis"></div>';
        echo '</div>';
        
        echo '<div class="fpdms-ai-insight-card">';
        echo '<h3 class="fpdms-ai-insight-title">';
        echo '<span class="dashicons dashicons-star-filled"></span>';
        echo esc_html__('Raccomandazioni', 'fp-dms');
        echo '</h3>';
        echo '<div class="fpdms-ai-insight-text" id="fpdms-ai-recommendations"></div>';
        echo '</div>';
        echo '</div>';
        
        // Error state
        echo '<div class="fpdms-ai-insights-error" id="fpdms-ai-insights-error" style="display:none;">';
        echo '<div class="fpdms-notice fpdms-notice-warning">';
        echo '<p>';
        echo '<strong>' . esc_html__('AI non disponibile', 'fp-dms') . '</strong><br>';
        echo esc_html__('Per utilizzare questa funzionalità, configura la tua API Key OpenAI nelle impostazioni.', 'fp-dms');
        echo '</p>';
        echo '<a href="' . esc_url(add_query_arg(['page' => 'fp-dms-settings'], admin_url('admin.php'))) . '" class="button button-primary">';
        echo esc_html__('Vai alle Impostazioni', 'fp-dms');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // .fpdms-ai-insights-container
        
        // Inline CSS
        echo '<style>
        .fpdms-ai-insights-section h2{display:flex;align-items:center;gap:8px;}
        .fpdms-ai-insights-container{min-height:200px;position:relative;}
        .fpdms-ai-insights-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;text-align:center;}
        .fpdms-spinner{border:3px solid #f3f3f3;border-top:3px solid #2271b1;border-radius:50%;width:40px;height:40px;animation:fpdms-spin 1s linear infinite;margin-bottom:16px;}
        @keyframes fpdms-spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        .fpdms-ai-insights-content{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;padding:4px;}
        .fpdms-ai-insight-card{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);transition:all 0.3s;}
        .fpdms-ai-insight-card:hover{box-shadow:0 4px 12px rgba(0,0,0,0.1);transform:translateY(-2px);}
        .fpdms-ai-insight-title{font-size:16px;font-weight:600;color:#1d2327;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;padding-bottom:12px;border-bottom:2px solid #f0f0f0;}
        .fpdms-ai-insight-title .dashicons{font-size:20px;color:#2271b1;}
        .fpdms-ai-insight-text{font-size:14px;line-height:1.7;color:#3c434a;}
        .fpdms-ai-insight-text p{margin:0 0 12px 0;}
        .fpdms-ai-insight-text p:last-child{margin-bottom:0;}
        .fpdms-ai-insight-text ul,.fpdms-ai-insight-text ol{margin:0 0 12px 0;padding-left:24px;}
        .fpdms-ai-insight-text li{margin-bottom:8px;}
        .fpdms-ai-insight-text strong{color:#1d2327;font-weight:600;}
        .fpdms-ai-insights-error{padding:20px;}
        .fpdms-notice{background:#fff;border-left:4px solid #d63638;padding:16px;border-radius:4px;}
        .fpdms-notice-warning{border-left-color:#f0b429;}
        .fpdms-notice p{margin:0 0 12px 0;}
        .fpdms-notice p:last-child{margin-bottom:0;}
        </style>';
        
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
     * Render reports section
     */
    public static function renderReportsSection(): void
    {
        echo '<section class="fpdms-section fpdms-overview-section" aria-labelledby="fpdms-overview-reports-heading">';
        echo '<header>';
        echo '<h2 id="fpdms-overview-reports-heading">' . esc_html__('Recent reports', 'fp-dms') . '</h2>';
        echo '<span class="fpdms-overview-period">' . esc_html__('View and download your latest reports.', 'fp-dms') . '</span>';
        echo '</header>';
        echo '<div id="fpdms-overview-reports-list" class="fpdms-reports-list">';
        echo '<p class="fpdms-reports-placeholder">' . esc_html__('No reports available yet.', 'fp-dms') . '</p>';
        echo '</div>';
        echo '<div id="fpdms-overview-report-viewer" class="fpdms-report-viewer" style="display: none;">';
        echo '<div class="fpdms-report-viewer-header">';
        echo '<h3 id="fpdms-report-viewer-title"></h3>';
        echo '<div class="fpdms-report-viewer-actions">';
        echo '<button type="button" class="button" id="fpdms-report-viewer-close">' . esc_html__('Close', 'fp-dms') . '</button>';
        echo '<button type="button" class="button button-primary" id="fpdms-report-viewer-download">' . esc_html__('Download PDF', 'fp-dms') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '<div id="fpdms-report-viewer-content" class="fpdms-report-viewer-content"></div>';
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

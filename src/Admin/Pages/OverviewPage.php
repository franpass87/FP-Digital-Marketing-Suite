<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Overview\OverviewConfigService;
use FP\DMS\Admin\Pages\Overview\OverviewRenderer;
use FP\DMS\Admin\Pages\Shared\Breadcrumbs;
use FP\DMS\Admin\Pages\Shared\HelpIcon;

use function add_action;
use function add_query_arg;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function esc_url;
use function plugins_url;
use function wp_enqueue_script;
use function wp_enqueue_style;

use const FP_DMS_PLUGIN_FILE;
use const FP_DMS_VERSION;

/**
 * Overview Page - Entry point and orchestration
 *
 * Delegates configuration and rendering to modular components:
 * - OverviewConfigService: Configuration and data preparation
 * - OverviewRenderer: UI rendering
 */
class OverviewPage
{
    /**
     * Register assets hook for the overview page
     */
    public static function registerAssetsHook(string $hook): void
    {
        add_action('admin_enqueue_scripts', static function (string $currentHook) use ($hook): void {
            if ($currentHook !== $hook) {
                return;
            }

            self::enqueueAssets();
        });
        
        // Add type="module" to overview.js script tag
        add_filter('script_loader_tag', static function($tag, $handle) {
            if ($handle === 'fpdms-overview') {
                $tag = str_replace(' src=', ' type="module" src=', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Enqueue CSS and JavaScript assets
     */
    private static function enqueueAssets(): void
    {
        $mainUrl = plugins_url('assets/css/main.css', FP_DMS_PLUGIN_FILE);
        $styleUrl = plugins_url('assets/css/overview.css', FP_DMS_PLUGIN_FILE);
        $modernStyleUrl = plugins_url('assets/css/overview-modern.css', FP_DMS_PLUGIN_FILE);
        $scriptUrl = plugins_url('assets/js/overview.js', FP_DMS_PLUGIN_FILE);
        $syncScriptUrl = plugins_url('assets/js/datasources-sync.js', FP_DMS_PLUGIN_FILE);

        wp_enqueue_style('fpdms-main', $mainUrl, [], FP_DMS_VERSION);
        wp_enqueue_style('fpdms-overview', $styleUrl, ['fpdms-main'], FP_DMS_VERSION);
        wp_enqueue_style('fpdms-overview-modern', $modernStyleUrl, ['fpdms-overview'], FP_DMS_VERSION);
        wp_enqueue_script('fpdms-overview', $scriptUrl, [], FP_DMS_VERSION, true);
        
        // Enqueue sync script for the sync button
        wp_enqueue_script(
            'fpdms-datasources-sync-overview',
            $syncScriptUrl,
            ['jquery'],
            FP_DMS_VERSION,
            true
        );
        
        wp_localize_script('fpdms-datasources-sync-overview', 'fpdmsSyncData', [
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('fpdms/v1/sync/datasources'),
        ]);
        
        // Enqueue metrics customizer script
        $customizerUrl = plugins_url('assets/js/overview-metrics-customizer.js', FP_DMS_PLUGIN_FILE);
        wp_enqueue_script(
            'fpdms-overview-metrics-customizer',
            $customizerUrl,
            [],
            FP_DMS_VERSION,
            true
        );
        
        // Enqueue KPI tooltips
        $kpiTooltipsUrl = plugins_url('assets/js/kpi-tooltips.js', FP_DMS_PLUGIN_FILE);
        wp_enqueue_script(
            'fpdms-kpi-tooltips',
            $kpiTooltipsUrl,
            [],
            FP_DMS_VERSION,
            true
        );
    }

    /**
     * Render the overview page
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $clients = OverviewConfigService::getClientOptions();

        echo '<div class="wrap fpdms-overview-wrap" id="fpdms-overview-root">';
        
        // Breadcrumbs
        Breadcrumbs::render(Breadcrumbs::getStandardItems('overview'));
        
        echo '<div class="fpdms-page-header" style="margin-bottom:24px;">';
        echo '<h1 style="margin:0;display:flex;align-items:center;">';
        echo '<span class="dashicons dashicons-chart-line" style="margin-right:12px;"></span>';
        echo esc_html__('Overview', 'fp-dms');
        HelpIcon::render(HelpIcon::getCommonHelp('overview'));
        echo '</h1>';
        echo '<p style="margin:8px 0 0 0;color:rgba(255,255,255,0.9);">' . esc_html__('Dashboard interattiva con metriche in tempo reale da tutte le tue sorgenti dati.', 'fp-dms') . '</p>';
        echo '</div>';

        // Empty state
        if (empty($clients)) {
            self::renderEmptyState();
            echo '</div>';
            return;
        }

        // Render sections using modular components
        $refreshIntervals = OverviewConfigService::getRefreshIntervals();

        OverviewRenderer::renderErrorBanner();
        OverviewRenderer::renderFilters($clients, $refreshIntervals);
        OverviewRenderer::renderSummarySection();
        OverviewRenderer::renderTrendSection();
        OverviewRenderer::renderAnomaliesSection();
        OverviewRenderer::renderAIInsightsSection();
        OverviewRenderer::renderStatusSection();
        OverviewRenderer::renderJobsSection();
        OverviewRenderer::renderReportsSection();

        // Render configuration for JavaScript
        $config = OverviewConfigService::buildConfig($clients);
        OverviewRenderer::renderConfig($config);

        echo '</div>';
    }

    /**
     * Render empty state when no clients are available
     */
    private static function renderEmptyState(): void
    {
        echo '<p>' . esc_html__('No clients available yet. Add a client to view the dashboard.', 'fp-dms') . '</p>';
        echo '<p>';
        echo '<a class="button button-primary" href="' . esc_url(add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php'))) . '">';
        echo esc_html__('Add your first client', 'fp-dms');
        echo '</a>';
        echo '</p>';
    }
}

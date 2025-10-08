<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Overview\OverviewConfigService;
use FP\DMS\Admin\Pages\Overview\OverviewRenderer;
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
    }

    /**
     * Enqueue CSS and JavaScript assets
     */
    private static function enqueueAssets(): void
    {
        $mainUrl = plugins_url('assets/css/main.css', FP_DMS_PLUGIN_FILE);
        $styleUrl = plugins_url('assets/css/overview.css', FP_DMS_PLUGIN_FILE);
        $scriptUrl = plugins_url('assets/js/overview.js', FP_DMS_PLUGIN_FILE);

        wp_enqueue_style('fpdms-main', $mainUrl, [], FP_DMS_VERSION);
        wp_enqueue_style('fpdms-overview', $styleUrl, ['fpdms-main'], FP_DMS_VERSION);
        wp_enqueue_script('fpdms-overview', $scriptUrl, [], FP_DMS_VERSION, true);
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
        echo '<h1>' . esc_html__('Overview', 'fp-dms') . '</h1>';

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
        OverviewRenderer::renderStatusSection();
        OverviewRenderer::renderJobsSection();

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
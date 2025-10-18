<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Dashboard\ComponentRenderer;
use FP\DMS\Admin\Pages\Dashboard\DashboardDataService;
use FP\DMS\Domain\Repos\SchedulesRepo;

use function add_action;
use function current_user_can;
use function esc_html__;
use function plugins_url;
use function wp_enqueue_style;

use const FP_DMS_PLUGIN_FILE;
use const FP_DMS_VERSION;

/**
 * Dashboard Page - Entry point and orchestration
 *
 * Delegates rendering and data retrieval to modular components:
 * - DashboardDataService: Data retrieval and transformation
 * - ComponentRenderer: UI rendering
 * - BadgeRenderer: Badge rendering
 * - DateFormatter: Date and time formatting
 */
class DashboardPage
{
    /**
     * Register assets hook for the dashboard page
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
     * Enqueue CSS assets
     */
    private static function enqueueAssets(): void
    {
        $styleUrl = plugins_url('assets/css/dashboard.css', FP_DMS_PLUGIN_FILE);
        wp_enqueue_style('fpdms-dashboard', $styleUrl, [], FP_DMS_VERSION);
    }

    /**
     * Render the dashboard page
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap fpdms-dashboard-wrap">';
        echo '<h1>' . esc_html__('Dashboard', 'fp-dms') . '</h1>';

        try {
            // Fetch data
            $clientNames = DashboardDataService::getClientDirectory();
            $stats = DashboardDataService::getStats();
            $recentReports = DashboardDataService::getRecentReports($clientNames, 5);
            $recentAnomalies = DashboardDataService::getRecentAnomalies($clientNames, 5);

            $scheduleRepo = new SchedulesRepo();
            $nextSchedule = $scheduleRepo->nextScheduledRun();

            // Render page intro
            echo '<p class="fpdms-dashboard-intro">' . esc_html__('Monitor the health of your reporting operations and jump into the areas that need attention.', 'fp-dms') . '</p>';

            // Render sections using modular components
            ComponentRenderer::renderSummary($stats);
            ComponentRenderer::renderScheduleCard($nextSchedule, $clientNames);
            ComponentRenderer::renderActivity($recentReports, $recentAnomalies);
            ComponentRenderer::renderQuickLinks();
        } catch (\Throwable $e) {
            self::renderError($e);
        }

        echo '</div>';
    }

    /**
     * Render error message
     */
    private static function renderError(\Throwable $e): void
    {
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . esc_html__('Dashboard Error', 'fp-dms') . '</strong></p>';
        echo '<p>' . esc_html__('Unable to load dashboard data. This usually happens if the plugin tables are not created.', 'fp-dms') . '</p>';
        echo '<p>' . esc_html__('Try deactivating and reactivating the plugin to recreate the database tables.', 'fp-dms') . '</p>';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<details style="margin-top: 10px;">';
            echo '<summary style="cursor: pointer;">' . esc_html__('Technical details (WP_DEBUG is enabled)', 'fp-dms') . '</summary>';
            echo '<pre style="background: #f0f0f0; padding: 10px; overflow: auto; max-height: 300px;">';
            echo esc_html($e->getMessage()) . "\n\n";
            echo esc_html($e->getTraceAsString());
            echo '</pre>';
            echo '</details>';
        }
        echo '</div>';
    }
}

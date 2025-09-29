<?php

declare(strict_types=1);

namespace FP\DMS\Admin;

use FP\DMS\Admin\Pages\AnomaliesPage;
use FP\DMS\Admin\Pages\ClientsPage;
use FP\DMS\Admin\Pages\DashboardPage;
use FP\DMS\Admin\Pages\DataSourcesPage;
use FP\DMS\Admin\Pages\HealthPage;
use FP\DMS\Admin\Pages\LogsPage;
use FP\DMS\Admin\Pages\SchedulesPage;
use FP\DMS\Admin\Pages\SettingsPage;
use FP\DMS\Admin\Pages\TemplatesPage;
use FP\DMS\Admin\Pages\QaPage;

class Menu
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register']);
    }

    public static function register(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $hook = add_menu_page(
            __('FP Suite', 'fp-dms'),
            __('FP Suite', 'fp-dms'),
            'manage_options',
            'fp-dms-dashboard',
            [DashboardPage::class, 'render'],
            'dashicons-chart-area',
            56
        );

        add_submenu_page('fp-dms-dashboard', __('Dashboard', 'fp-dms'), __('Dashboard', 'fp-dms'), 'manage_options', 'fp-dms-dashboard', [DashboardPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Clients', 'fp-dms'), __('Clients', 'fp-dms'), 'manage_options', 'fp-dms-clients', [ClientsPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Data Sources', 'fp-dms'), __('Data Sources', 'fp-dms'), 'manage_options', 'fp-dms-datasources', [DataSourcesPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Schedules', 'fp-dms'), __('Schedules', 'fp-dms'), 'manage_options', 'fp-dms-schedules', [SchedulesPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Templates', 'fp-dms'), __('Templates', 'fp-dms'), 'manage_options', 'fp-dms-templates', [TemplatesPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Settings', 'fp-dms'), __('Settings', 'fp-dms'), 'manage_options', 'fp-dms-settings', [SettingsPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Logs', 'fp-dms'), __('Logs', 'fp-dms'), 'manage_options', 'fp-dms-logs', [LogsPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Anomalies', 'fp-dms'), __('Anomalies', 'fp-dms'), 'manage_options', 'fp-dms-anomalies', [AnomaliesPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('Health', 'fp-dms'), __('Health', 'fp-dms'), 'manage_options', 'fp-dms-health', [HealthPage::class, 'render']);
        add_submenu_page('fp-dms-dashboard', __('QA Automation', 'fp-dms'), __('QA Automation', 'fp-dms'), 'manage_options', 'fp-dms-qa', [QaPage::class, 'render']);

        if ($hook) {
            add_action('load-' . $hook, [self::class, 'enqueue_assets']);
        }
    }

    public static function enqueue_assets(): void
    {
        // Placeholder for future admin assets.
    }
}

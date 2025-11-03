<?php

declare(strict_types=1);

namespace FP\DMS\Admin;

use FP\DMS\Admin\Pages\AnomaliesPage;
use FP\DMS\Admin\Pages\ClientsPage;
use FP\DMS\Admin\Pages\DashboardPage;
use FP\DMS\Admin\Pages\DataSourcesPage;
use FP\DMS\Admin\Pages\DebugPage;
use FP\DMS\Admin\Pages\HealthPage;
use FP\DMS\Admin\Pages\LogsPage;
use FP\DMS\Admin\Pages\OverviewPage;
use FP\DMS\Admin\Pages\ReportsPage;
use FP\DMS\Admin\Pages\SchedulesPage;
use FP\DMS\Admin\Pages\SettingsPage;
use FP\DMS\Admin\Pages\TemplatesPage;
use FP\DMS\Admin\Pages\QaPage;

class Menu
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueGlobalAssets']);
        add_action('admin_notices', [self::class, 'hideExternalNotices'], 0);
    }

    public static function register(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Main menu
        $hook = add_menu_page(
            __('FP Suite', 'fp-dms'),
            __('FP Suite', 'fp-dms'),
            'manage_options',
            'fp-dms-dashboard',
            [DashboardPage::class, 'render'],
            'dashicons-chart-area',
            56
        );

        // === MENU PRINCIPALE (7 voci) ===
        
        // 1. Dashboard
        $dashboardHook = add_submenu_page(
            'fp-dms-dashboard', 
            __('Dashboard', 'fp-dms'), 
            '📊 ' . __('Dashboard', 'fp-dms'), 
            'manage_options', 
            'fp-dms-dashboard', 
            [DashboardPage::class, 'render']
        );
        
        // 2. Overview
        $overviewHook = add_submenu_page(
            'fp-dms-dashboard', 
            __('Overview', 'fp-dms'), 
            '👁️ ' . __('Overview', 'fp-dms'), 
            'manage_options', 
            'fp-dms-overview', 
            [OverviewPage::class, 'render']
        );
        
        // 3. Clienti
        add_submenu_page(
            'fp-dms-dashboard', 
            __('Clienti', 'fp-dms'), 
            '👥 ' . __('Clienti', 'fp-dms'), 
            'manage_options', 
            'fp-dms-clients', 
            [ClientsPage::class, 'render']
        );
        
        // 4. Connessioni (Data Sources)
        $dataSourcesHook = add_submenu_page(
            'fp-dms-dashboard', 
            __('Connessioni', 'fp-dms'), 
            '📡 ' . __('Connessioni', 'fp-dms'), 
            'manage_options', 
            'fp-dms-datasources', 
            [DataSourcesPage::class, 'render']
        );
        
        // 5. Automazione (con sottomenu)
        add_submenu_page(
            'fp-dms-dashboard', 
            __('Automazione', 'fp-dms'), 
            '📅 ' . __('Automazione', 'fp-dms'), 
            'manage_options', 
            'fp-dms-schedules', 
            [SchedulesPage::class, 'render']
        );
        
        // 5a. Sottomenu: QA Automation
        add_submenu_page(
            'fp-dms-dashboard', 
            __('QA Automation', 'fp-dms'), 
            '&nbsp;&nbsp;&nbsp;↳ ' . __('QA Automation', 'fp-dms'), 
            'manage_options', 
            'fp-dms-qa', 
            [QaPage::class, 'render']
        );
        
        // 6. Report (con sottomenu)
        $reportsHook = add_submenu_page(
            'fp-dms-dashboard', 
            __('Report', 'fp-dms'), 
            '📄 ' . __('Report', 'fp-dms'), 
            'manage_options', 
            'fp-dms-reports', 
            [ReportsPage::class, 'render']
        );
        
        // 6a. Sottomenu: Template
        add_submenu_page(
            'fp-dms-dashboard', 
            __('Template', 'fp-dms'), 
            '&nbsp;&nbsp;&nbsp;↳ ' . __('Template', 'fp-dms'), 
            'manage_options', 
            'fp-dms-templates', 
            [TemplatesPage::class, 'render']
        );
        
        // 6b. Sottomenu: Anomalie
        add_submenu_page(
            'fp-dms-dashboard', 
            __('Anomalie', 'fp-dms'), 
            '&nbsp;&nbsp;&nbsp;↳ ' . __('Anomalie', 'fp-dms'), 
            'manage_options', 
            'fp-dms-anomalies', 
            [AnomaliesPage::class, 'render']
        );
        
        // 7. Impostazioni (con sottomenu)
        add_submenu_page(
            'fp-dms-dashboard', 
            __('Impostazioni', 'fp-dms'), 
            '⚙️ ' . __('Impostazioni', 'fp-dms'), 
            'manage_options', 
            'fp-dms-settings', 
            [SettingsPage::class, 'render']
        );
        
        // 7a. Sottomenu: System Health
        add_submenu_page(
            'fp-dms-dashboard', 
            __('System Health', 'fp-dms'), 
            '&nbsp;&nbsp;&nbsp;↳ ' . __('System Health', 'fp-dms'), 
            'manage_options', 
            'fp-dms-health', 
            [HealthPage::class, 'render']
        );
        
        // 7b. Sottomenu: Logs
        add_submenu_page(
            'fp-dms-dashboard', 
            __('Logs', 'fp-dms'), 
            '&nbsp;&nbsp;&nbsp;↳ ' . __('Logs', 'fp-dms'), 
            'manage_options', 
            'fp-dms-logs', 
            [LogsPage::class, 'render']
        );
        
        // 7c. Sottomenu: Debug (solo per sviluppatori)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                'fp-dms-dashboard', 
                __('Debug', 'fp-dms'), 
                '&nbsp;&nbsp;&nbsp;↳ ' . __('Debug', 'fp-dms'), 
                'manage_options', 
                'fp-dms-debug', 
                [DebugPage::class, 'render']
            );
            
            // Debug AI Settings
            add_submenu_page(
                'fp-dms-dashboard', 
                __('Debug AI', 'fp-dms'), 
                '&nbsp;&nbsp;&nbsp;↳ ' . __('Debug AI', 'fp-dms'), 
                'manage_options', 
                'fp-dms-debug-ai', 
                [self::class, 'renderDebugAI']
            );
        }

        if ($hook) {
            add_action('load-' . $hook, [self::class, 'enqueueAssets']);
            DashboardPage::registerAssetsHook($hook);
        }

        if (! empty($dashboardHook)) {
            DashboardPage::registerAssetsHook($dashboardHook);
        }

        if (! empty($overviewHook)) {
            OverviewPage::registerAssetsHook($overviewHook);
        }
        
        if (! empty($dataSourcesHook)) {
            DataSourcesPage::registerAssetsHook($dataSourcesHook);
        }
        
        if (! empty($reportsHook)) {
            ReportsPage::registerAssetsHook($reportsHook);
        }
    }

    public static function enqueueAssets(): void
    {
        // Placeholder for future admin assets.
    }
    
    /**
     * Enqueue global admin assets for all plugin pages
     */
    public static function enqueueGlobalAssets(string $hook): void
    {
        // Verifica se siamo in una pagina del plugin
        if (strpos($hook, 'fp-dms') === false) {
            return;
        }
        
        $version = defined('FP_DMS_VERSION') ? FP_DMS_VERSION : '0.1.1';
        $pluginUrl = plugin_dir_url(FP_DMS_PLUGIN_FILE);
        
        // CSS Design System Globale
        wp_enqueue_style(
            'fpdms-admin-modern',
            $pluginUrl . 'assets/css/admin-modern.css',
            [],
            $version
        );
        
        // Toast Notification System
        wp_enqueue_script(
            'fpdms-toast',
            $pluginUrl . 'assets/js/toast.js',
            [],
            $version,
            true
        );
    }

    /**
     * Hide admin notices from other plugins on FP-DMS pages
     * This prevents external notices from appearing in our plugin's header
     */
    public static function hideExternalNotices(): void
    {
        // Check if we're on a FP-DMS admin page
        $screen = get_current_screen();
        if (! $screen || strpos($screen->id, 'fp-dms') === false) {
            return;
        }

        // Remove all admin notices that are not from FP-DMS
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        
        // Re-add only FP-DMS specific notices
        add_action('admin_notices', function() {
            // Display only settings_errors for fpdms
            settings_errors('fpdms_settings');
        });
    }

    /**
     * Render Debug AI Settings page
     */
    public static function renderDebugAI(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accesso negato');
        }

        echo '<div class="wrap">';
        echo '<h1>🔍 Debug Impostazioni AI</h1>';
        
        // Check API Key
        $apiKey = get_option('fpdms_openai_api_key', '');
        $apiModel = get_option('fpdms_ai_model', '');
        
        echo '<div class="notice notice-info"><p><strong>Stato delle opzioni nel database:</strong></p></div>';
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Opzione</th><th>Valore</th><th>Stato</th></tr></thead>';
        echo '<tbody>';
        
        echo '<tr>';
        echo '<td><code>fpdms_openai_api_key</code></td>';
        echo '<td>' . (empty($apiKey) ? '<em style="color:#999;">VUOTA</em>' : '<code>' . substr($apiKey, 0, 10) . '...' . substr($apiKey, -4) . '</code>') . '</td>';
        echo '<td>' . (empty($apiKey) ? '❌ <span style="color:red;">NON CONFIGURATA</span>' : '✅ <span style="color:green;">CONFIGURATA</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><code>fpdms_ai_model</code></td>';
        echo '<td>' . (empty($apiModel) ? '<em style="color:#999;">VUOTO</em>' : '<code>' . esc_html($apiModel) . '</code>') . '</td>';
        echo '<td>' . (empty($apiModel) ? '❌ <span style="color:red;">NON CONFIGURATO</span>' : '✅ <span style="color:green;">CONFIGURATO</span>') . '</td>';
        echo '</tr>';
        
        echo '</tbody></table>';
        
        // Test using Options class
        echo '<h2>Test usando Options::get()</h2>';
        
        $apiKeyViaClass = \FP\DMS\Infra\Options::get('fpdms_openai_api_key', '');
        $apiModelViaClass = \FP\DMS\Infra\Options::get('fpdms_ai_model', '');
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Metodo</th><th>API Key</th><th>Model</th></tr></thead>';
        echo '<tbody>';
        echo '<tr>';
        echo '<td><code>Options::get()</code></td>';
        echo '<td>' . (empty($apiKeyViaClass) ? '❌ <span style="color:red;">VUOTA</span>' : '✅ <code>' . substr($apiKeyViaClass, 0, 10) . '...</code>') . '</td>';
        echo '<td>' . (empty($apiModelViaClass) ? '❌ <span style="color:red;">VUOTO</span>' : '✅ <code>' . esc_html($apiModelViaClass) . '</code>') . '</td>';
        echo '</tr>';
        echo '</tbody></table>';
        
        // Check AI Insights Service
        echo '<h2>Test AIInsightsService::hasOpenAIKey()</h2>';
        try {
            $service = new \FP\DMS\Services\Overview\AIInsightsService();
            
            // Use reflection to call private method
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('hasOpenAIKey');
            $method->setAccessible(true);
            $hasKey = $method->invoke($service);
            
            if ($hasKey) {
                echo '<div class="notice notice-success"><p><strong>✅ hasOpenAIKey() = TRUE</strong><br>La chiave è rilevata dal servizio AI!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p><strong>❌ hasOpenAIKey() = FALSE</strong><br>La chiave NON è rilevata dal servizio AI!</p></div>';
            }
        } catch (\Exception $e) {
            echo '<div class="notice notice-error"><p><strong>❌ Errore:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
        }
        
        // Action buttons
        echo '<hr>';
        echo '<p>';
        echo '<a href="' . admin_url('admin.php?page=fp-dms-settings') . '" class="button button-primary">← Vai alle Impostazioni</a> ';
        echo '<a href="' . admin_url('admin.php?page=fp-dms-overview') . '" class="button">Vai a Overview</a>';
        echo '</p>';
        
        echo '</div>';
    }
}

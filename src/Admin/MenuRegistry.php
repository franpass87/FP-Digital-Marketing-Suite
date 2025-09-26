<?php
/**
 * MenuRegistry centralizes the configuration and lifecycle of admin menus.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Setup\SettingsManager;

/**
 * Provides a single source of truth for the admin menu structure and helpers
 * to keep persisted state in sync with runtime changes.
 */
class MenuRegistry
{
    /**
     * Main menu slug used throughout the plugin.
     */
    public const MAIN_MENU_SLUG = 'fp-digital-marketing-dashboard';

    /**
     * Setup wizard slug for conditional menu rendering.
     */
    public const WIZARD_MENU_SLUG = 'fp-digital-marketing-onboarding';

    /**
     * Cached menu structure definition.
     *
     * @var array{
     *     main: array<string, mixed>,
     *     submenus: list<array<string, mixed>>
     * }
     */
    private array $menu_structure;

    public function __construct()
    {
        $this->menu_structure = $this->build_default_structure();

        if (SettingsManager::is_wizard_menu_enabled()) {
            $this->add_wizard_menu_to_structure();
        }

        $this->persist_menu_slugs();
    }

    /**
     * Returns the primary menu configuration.
     *
     * @return array<string, mixed>
     */
    public function get_main_menu(): array
    {
        return $this->menu_structure['main'];
    }

    /**
     * Returns the complete submenu collection in declaration order.
     *
     * @return list<array<string, mixed>>
     */
    public function get_submenus(): array
    {
        return $this->menu_structure['submenus'];
    }

    /**
     * Group submenus by the provided key (defaults to functional grouping).
     *
     * @param string $key
     * @return array<string, list<array<string, mixed>>>
     */
    public function group_submenus(string $key = 'group'): array
    {
        $grouped = [];

        foreach ($this->menu_structure['submenus'] as $submenu) {
            $group = $submenu[$key] ?? 'other';
            $grouped[$group][] = $submenu;
        }

        $ordered_groups = ['overview', 'analytics', 'campaigns', 'monitoring', 'administration', 'other'];
        $result = [];

        foreach ($ordered_groups as $group) {
            if (isset($grouped[$group])) {
                $result[$group] = $grouped[$group];
            }
        }

        // Preserve any custom groups that might have been registered elsewhere.
        foreach ($grouped as $group => $menus) {
            if (!isset($result[$group])) {
                $result[$group] = $menus;
            }
        }

        return $result;
    }

    /**
     * Ensure the wizard menu is part of the runtime structure and saved state.
     *
     * @return void
     */
    public function enable_wizard_menu(): void
    {
        SettingsManager::enable_wizard_menu(self::WIZARD_MENU_SLUG);

        $this->add_wizard_menu_to_structure();
        $this->persist_menu_slugs();
    }

    /**
     * Remove the wizard menu from runtime and saved state.
     *
     * @param string $status
     * @return void
     */
    public function disable_wizard_menu(string $status = 'completed'): void
    {
        SettingsManager::disable_wizard_menu(self::WIZARD_MENU_SLUG, $status);

        $this->remove_wizard_menu_from_structure();
        $this->persist_menu_slugs();
    }

    /**
     * Returns whether the wizard menu currently exists in the structure.
     */
    public function has_wizard_menu(): bool
    {
        foreach ($this->menu_structure['submenus'] as $submenu) {
            if (($submenu['menu_slug'] ?? '') === self::WIZARD_MENU_SLUG) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist registered menu slugs for use in other plugin subsystems.
     *
     * @return void
     */
    public function persist_menu_slugs(): void
    {
        $slugs = [];

        if (!empty($this->menu_structure['main']['menu_slug'])) {
            $slugs[] = (string) $this->menu_structure['main']['menu_slug'];
        }

        foreach ($this->menu_structure['submenus'] as $submenu) {
            if (!empty($submenu['menu_slug'])) {
                $slugs[] = (string) $submenu['menu_slug'];
            }
        }

        SettingsManager::set_registered_menu_slugs($slugs);
    }

    /**
     * Reset the structure to the base definition. Intended for tests.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->menu_structure = $this->build_default_structure();
        $this->persist_menu_slugs();
    }

    /**
     * Build the default menu definition used during construction.
     *
     * @return array{
     *     main: array<string, mixed>,
     *     submenus: list<array<string, mixed>>
     * }
     */
    private function build_default_structure(): array
    {
        return [
            'main' => [
                'page_title' => __('FP Digital Marketing Suite', 'fp-digital-marketing'),
                'menu_title' => __('FP Digital Marketing', 'fp-digital-marketing'),
                'capability' => Capabilities::VIEW_DASHBOARD,
                'menu_slug' => self::MAIN_MENU_SLUG,
                'callback' => 'Dashboard::render_dashboard_page',
                'icon' => 'dashicons-chart-area',
                'position' => 20,
            ],
            'submenus' => [
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Dashboard', 'fp-digital-marketing'),
                    'menu_title' => __('🏠 Dashboard', 'fp-digital-marketing'),
                    'capability' => Capabilities::VIEW_DASHBOARD,
                    'menu_slug' => self::MAIN_MENU_SLUG,
                    'callback' => 'Dashboard::render_dashboard_page',
                    'group' => 'overview',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Reports & Analytics', 'fp-digital-marketing'),
                    'menu_title' => __('📊 Reports', 'fp-digital-marketing'),
                    'capability' => Capabilities::EXPORT_REPORTS,
                    'menu_slug' => 'fp-digital-marketing-reports',
                    'callback' => 'Reports::render_reports_page',
                    'group' => 'analytics',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Eventi Conversione', 'fp-digital-marketing'),
                    'menu_title' => __('🎯 Eventi Conversione', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_CONVERSIONS,
                    'menu_slug' => 'fp-conversion-events',
                    'callback' => 'ConversionEventsAdmin::render_admin_page',
                    'group' => 'analytics',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Gestione Campagne UTM', 'fp-digital-marketing'),
                    'menu_title' => __('🚀 Campagne UTM', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_CAMPAIGNS,
                    'menu_slug' => 'fp-utm-campaign-manager',
                    'callback' => 'UTMCampaignManager::render_page',
                    'group' => 'campaigns',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Funnel Analysis', 'fp-digital-marketing'),
                    'menu_title' => __('📈 Funnel Analysis', 'fp-digital-marketing'),
                    'capability' => Capabilities::FUNNEL_ANALYSIS,
                    'menu_slug' => 'fp-digital-marketing-funnel-analysis',
                    'callback' => 'FunnelAnalysisAdmin::render_admin_page',
                    'group' => 'campaigns',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Segmentazione Audience', 'fp-digital-marketing'),
                    'menu_title' => __('👥 Segmentazione', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SEGMENTS,
                    'menu_slug' => 'fp-audience-segments',
                    'callback' => 'SegmentationAdmin::render_segmentation_page',
                    'group' => 'campaigns',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Alert e Notifiche', 'fp-digital-marketing'),
                    'menu_title' => __('🔔 Alert e Notifiche', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_ALERTS,
                    'menu_slug' => 'fp-digital-marketing-alerts',
                    'callback' => 'AlertingAdmin::display_admin_page',
                    'group' => 'monitoring',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Rilevazione Anomalie', 'fp-digital-marketing'),
                    'menu_title' => __('🔍 Rilevazione Anomalie', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_ALERTS,
                    'menu_slug' => 'fp-digital-marketing-anomalies',
                    'callback' => 'AnomalyDetectionAdmin::display_admin_page',
                    'group' => 'monitoring',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Cache Performance', 'fp-digital-marketing'),
                    'menu_title' => __('⚡ Cache Performance', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-digital-marketing-cache-performance',
                    'callback' => 'CachePerformance::render_performance_page',
                    'group' => 'monitoring',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Security Settings', 'fp-digital-marketing'),
                    'menu_title' => __('🔒 Security', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-digital-marketing-security',
                    'callback' => 'SecurityAdmin::render_security_page',
                    'group' => 'administration',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Connessioni Piattaforme', 'fp-digital-marketing'),
                    'menu_title' => __('🔗 Connessioni', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-platform-connections',
                    'callback' => 'PlatformConnections::render_connections_page',
                    'group' => 'administration',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('FP Digital Marketing Settings', 'fp-digital-marketing'),
                    'menu_title' => __('⚙️ Settings', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-digital-marketing-settings',
                    'callback' => 'Settings::render_settings_page',
                    'group' => 'administration',
                ],
            ],
        ];
    }

    /**
     * Append the wizard menu configuration if not already present.
     */
    private function add_wizard_menu_to_structure(): void
    {
        if ($this->has_wizard_menu()) {
            return;
        }

        $this->menu_structure['submenus'][] = [
            'parent_slug' => self::MAIN_MENU_SLUG,
            'page_title' => __('Setup Wizard', 'fp-digital-marketing'),
            'menu_title' => __('🚀 Setup Wizard', 'fp-digital-marketing'),
            'capability' => Capabilities::MANAGE_SETTINGS,
            'menu_slug' => self::WIZARD_MENU_SLUG,
            'callback' => 'OnboardingWizard::render_wizard_page',
            'group' => 'administration',
        ];
    }

    /**
     * Remove the wizard menu configuration from the structure.
     */
    private function remove_wizard_menu_from_structure(): void
    {
        $this->menu_structure['submenus'] = array_values(
            array_filter(
                $this->menu_structure['submenus'],
                static fn(array $submenu): bool => ($submenu['menu_slug'] ?? '') !== self::WIZARD_MENU_SLUG
            )
        );
    }
}


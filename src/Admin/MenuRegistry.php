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

    /**
     * Legacy slug redirect map keyed by deprecated slug.
     *
     * @var array<string, string>
     */
    private array $legacy_redirects = [];

    public function __construct()
    {
        $this->menu_structure = $this->build_default_structure();

        if (SettingsManager::is_wizard_menu_enabled()) {
            $this->add_wizard_menu_to_structure();
        }

        $this->persist_menu_slugs();
        $this->legacy_redirects = $this->build_legacy_redirect_map();
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
     * Returns the legacy redirect mapping for slug back-compatibility.
     *
     * @return array<string, string>
     */
    public function get_legacy_redirects(): array
    {
        return $this->legacy_redirects;
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

        $ordered_groups = [
            'overview',
            'analysis',
            'activation',
            'monitoring',
            'optimization',
            'configuration',
            'support',
            'other',
        ];
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
                'page_title' => __('FP Marketing Suite', 'fp-digital-marketing'),
                'menu_title' => __('FP Marketing Suite', 'fp-digital-marketing'),
                'capability' => Capabilities::VIEW_DASHBOARD,
                'menu_slug' => self::MAIN_MENU_SLUG,
                'callback' => 'Dashboard::render_dashboard_page',
                'icon' => 'dashicons-chart-area',
                'position' => 20,
            ],
            'submenus' => [
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Panoramica performance', 'fp-digital-marketing'),
                    'menu_title' => __('Panoramica performance', 'fp-digital-marketing'),
                    'capability' => Capabilities::VIEW_DASHBOARD,
                    'menu_slug' => self::MAIN_MENU_SLUG,
                    'callback' => 'Dashboard::render_dashboard_page',
                    'group' => 'overview',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Report performance', 'fp-digital-marketing'),
                    'menu_title' => __('Report performance', 'fp-digital-marketing'),
                    'capability' => Capabilities::VIEW_DASHBOARD,
                    'menu_slug' => 'fp-digital-marketing-reports',
                    'callback' => 'Reports::render_reports_page',
                    'group' => 'analysis',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Analisi funnel', 'fp-digital-marketing'),
                    'menu_title' => __('Analisi funnel', 'fp-digital-marketing'),
                    'capability' => Capabilities::FUNNEL_ANALYSIS,
                    'menu_slug' => 'fp-digital-marketing-funnel-analysis',
                    'callback' => 'FunnelAnalysisAdmin::render_admin_page',
                    'group' => 'analysis',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Segmenti audience', 'fp-digital-marketing'),
                    'menu_title' => __('Segmenti audience', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SEGMENTS,
                    'menu_slug' => 'fp-audience-segments',
                    'callback' => 'SegmentationAdmin::render_segmentation_page',
                    'group' => 'analysis',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Generatore campagne UTM', 'fp-digital-marketing'),
                    'menu_title' => __('Generatore campagne UTM', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_CAMPAIGNS,
                    'menu_slug' => 'fp-utm-campaign-manager',
                    'callback' => 'UTMCampaignManager::render_page',
                    'group' => 'activation',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Gestisci conversioni', 'fp-digital-marketing'),
                    'menu_title' => __('Gestisci conversioni', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_CONVERSIONS,
                    'menu_slug' => 'fp-conversion-events',
                    'callback' => 'ConversionEventsAdmin::render_admin_page',
                    'group' => 'activation',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Monitoraggio alert', 'fp-digital-marketing'),
                    'menu_title' => __('Monitoraggio alert', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_ALERTS,
                    'menu_slug' => 'fp-digital-marketing-alerts',
                    'callback' => 'AlertingAdmin::display_admin_page',
                    'group' => 'monitoring',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Anomalie e regole', 'fp-digital-marketing'),
                    'menu_title' => __('Anomalie e regole', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_ALERTS,
                    'menu_slug' => 'fp-digital-marketing-anomalies',
                    'callback' => 'AnomalyDetectionAdmin::display_admin_page',
                    'group' => 'monitoring',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Ottimizzazione prestazioni', 'fp-digital-marketing'),
                    'menu_title' => __('Ottimizzazione prestazioni', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-digital-marketing-cache-performance',
                    'callback' => 'CachePerformance::render_performance_page',
                    'group' => 'optimization',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Connessioni piattaforme', 'fp-digital-marketing'),
                    'menu_title' => __('Connessioni piattaforme', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-platform-connections',
                    'callback' => 'PlatformConnections::render_connections_page',
                    'group' => 'configuration',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Sicurezza dati', 'fp-digital-marketing'),
                    'menu_title' => __('Sicurezza dati', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-digital-marketing-security',
                    'callback' => 'SecurityAdmin::render_security_page',
                    'group' => 'configuration',
                ],
                [
                    'parent_slug' => self::MAIN_MENU_SLUG,
                    'page_title' => __('Impostazioni generali', 'fp-digital-marketing'),
                    'menu_title' => __('Impostazioni generali', 'fp-digital-marketing'),
                    'capability' => Capabilities::MANAGE_SETTINGS,
                    'menu_slug' => 'fp-digital-marketing-settings',
                    'callback' => 'Settings::render_settings_page',
                    'group' => 'configuration',
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
            'menu_title' => __('Configurazione guidata', 'fp-digital-marketing'),
            'capability' => Capabilities::MANAGE_SETTINGS,
            'menu_slug' => self::WIZARD_MENU_SLUG,
            'callback' => 'OnboardingWizard::render_wizard_page',
            'group' => 'support',
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

    /**
     * Build the redirect map for legacy menu slugs.
     *
     * @return array<string, string>
     */
    private function build_legacy_redirect_map(): array
    {
        $map = [
            'fp-digital-marketing-utm-campaigns' => 'fp-utm-campaign-manager',
            'fp-digital-marketing-conversion-events' => 'fp-conversion-events',
            'fp-digital-marketing-segments-old' => 'fp-audience-segments',
            'fp-digital-marketing-cache' => 'fp-digital-marketing-cache-performance',
            'fp-digital-marketing-security-old' => 'fp-digital-marketing-security',
        ];

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('fp_dms_admin_menu_legacy_redirects', $map);

            if (is_array($filtered)) {
                $map = $filtered;
            }
        }

        return $this->sanitize_redirect_map($map);
    }

    /**
     * Normalize redirect map entries to safe slug pairs.
     *
     * @param array<string, mixed> $map Raw redirect map.
     * @return array<string, string>
     */
    private function sanitize_redirect_map(array $map): array
    {
        $normalized = [];

        foreach ($map as $legacy => $target) {
            $legacy_slug = $this->sanitize_slug((string) $legacy);
            $target_slug = $this->sanitize_slug((string) $target);

            if ($legacy_slug === '' || $target_slug === '' || $legacy_slug === $target_slug) {
                continue;
            }

            $normalized[$legacy_slug] = $target_slug;
        }

        return $normalized;
    }

    /**
     * Provides a WordPress-compatible slug sanitization helper.
     */
    private function sanitize_slug(string $slug): string
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($slug);
        }

        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $slug));
    }
}


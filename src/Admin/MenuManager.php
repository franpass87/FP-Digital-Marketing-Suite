<?php
/**
 * Admin Menu Manager - Centralized menu registration and organization
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * MenuManager class for centralized admin menu management
 * 
 * This class rationalizes and organizes all admin menu items into logical groups,
 * eliminating redundancy and improving user experience.
 */
class MenuManager {

	/**
	 * Main menu slug
	 */
	private const MAIN_MENU_SLUG = 'fp-digital-marketing-dashboard';

	/**
	 * Menu structure configuration
	 *
	 * @var array
	 */
	private array $menu_structure = [];

	/**
	 * Admin class instances
	 *
	 * @var array
	 */
	private array $admin_instances = [];

	/**
	 * Constructor
	 * 
	 * @param array $admin_instances Pre-instantiated admin class instances
	 */
	public function __construct( array $admin_instances = [] ) {
		$this->admin_instances = $admin_instances;
		$this->define_menu_structure();
	}

	/**
	 * Initialize the menu manager
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_menus' ], 5 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Define the rationalized menu structure
	 *
	 * @return void
	 */
	private function define_menu_structure(): void {
		$this->menu_structure = [
			'main' => [
				'page_title' => __( 'FP Digital Marketing Suite', 'fp-digital-marketing' ),
				'menu_title' => __( 'FP Digital Marketing', 'fp-digital-marketing' ),
				'capability' => Capabilities::VIEW_DASHBOARD,
				'menu_slug' => self::MAIN_MENU_SLUG,
				'callback' => 'Dashboard::render_dashboard_page',
				'icon' => 'dashicons-chart-area',
				'position' => 20
			],
			'submenus' => [
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Dashboard', 'fp-digital-marketing' ),
					'menu_title' => __( '🏠 Dashboard', 'fp-digital-marketing' ),
					'capability' => Capabilities::VIEW_DASHBOARD,
					'menu_slug' => self::MAIN_MENU_SLUG,
					'callback' => 'Dashboard::render_dashboard_page',
					'group' => 'overview'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Analytics & Reports', 'fp-digital-marketing' ),
					'menu_title' => __( '📊 Analytics & Reports', 'fp-digital-marketing' ),
					'capability' => Capabilities::EXPORT_REPORTS,
					'menu_slug' => 'fp-digital-marketing-analytics',
					'callback' => 'Reports::render_reports_page',
					'group' => 'analytics'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Campaign Management', 'fp-digital-marketing' ),
					'menu_title' => __( '🚀 Campaign Management', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_CAMPAIGNS,
					'menu_slug' => 'fp-digital-marketing-campaigns',
					'callback' => 'UTMCampaignManager::render_campaigns_page',
					'group' => 'campaigns'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Funnel Analysis', 'fp-digital-marketing' ),
					'menu_title' => __( '🎯 Funnel Analysis', 'fp-digital-marketing' ),
					'capability' => Capabilities::VIEW_REPORTS,
					'menu_slug' => 'fp-digital-marketing-funnels',
					'callback' => 'FunnelAnalysisAdmin::render_funnel_page',
					'group' => 'campaigns'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Audience Segmentation', 'fp-digital-marketing' ),
					'menu_title' => __( '👥 Audience Segmentation', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_SEGMENTS,
					'menu_slug' => 'fp-digital-marketing-segments',
					'callback' => 'SegmentationAdmin::render_segmentation_page',
					'group' => 'campaigns'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Monitoring & Alerts', 'fp-digital-marketing' ),
					'menu_title' => __( '🔔 Monitoring & Alerts', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_ALERTS,
					'menu_slug' => 'fp-digital-marketing-monitoring',
					'callback' => 'AlertingAdmin::render_alerts_page',
					'group' => 'monitoring'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Anomaly Detection', 'fp-digital-marketing' ),
					'menu_title' => __( '🔍 Anomaly Detection', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_ALERTS,
					'menu_slug' => 'fp-digital-marketing-anomalies',
					'callback' => 'AnomalyDetectionAdmin::render_anomaly_page',
					'group' => 'monitoring'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Performance Cache', 'fp-digital-marketing' ),
					'menu_title' => __( '⚡ Performance', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_SETTINGS,
					'menu_slug' => 'fp-digital-marketing-performance',
					'callback' => 'CachePerformance::render_performance_page',
					'group' => 'monitoring'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Security Settings', 'fp-digital-marketing' ),
					'menu_title' => __( '🔒 Security', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_SETTINGS,
					'menu_slug' => 'fp-digital-marketing-security',
					'callback' => 'SecurityAdmin::render_security_page',
					'group' => 'administration'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Settings', 'fp-digital-marketing' ),
					'menu_title' => __( '⚙️ Settings', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_SETTINGS,
					'menu_slug' => 'fp-digital-marketing-settings',
					'callback' => 'Settings::render_settings_page',
					'group' => 'administration'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Setup Wizard', 'fp-digital-marketing' ),
					'menu_title' => __( '🛠️ Setup Wizard', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_SETTINGS,
					'menu_slug' => 'fp-digital-marketing-wizard',
					'callback' => 'OnboardingWizard::render_wizard_page',
					'group' => 'administration'
				]
			]
		];
	}

	/**
	 * Register all menus according to the rationalized structure
	 *
	 * @return void
	 */
	public function register_menus(): void {
		// Register main menu
		$main = $this->menu_structure['main'];
		add_menu_page(
			$main['page_title'],
			$main['menu_title'],
			$main['capability'],
			$main['menu_slug'],
			$this->get_callback( $main['callback'] ),
			$main['icon'],
			$main['position']
		);

		// Register submenus grouped by functionality
		$grouped_menus = $this->group_menus_by_functionality();

		foreach ( $grouped_menus as $group => $menus ) {
			foreach ( $menus as $menu ) {
				add_submenu_page(
					$menu['parent_slug'],
					$menu['page_title'],
					$menu['menu_title'],
					$menu['capability'],
					$menu['menu_slug'],
					$this->get_callback( $menu['callback'] )
				);
			}
		}
	}

	/**
	 * Group menus by functionality for better organization
	 *
	 * @return array
	 */
	private function group_menus_by_functionality(): array {
		$grouped = [];
		
		foreach ( $this->menu_structure['submenus'] as $menu ) {
			$group = $menu['group'] ?? 'other';
			if ( ! isset( $grouped[$group] ) ) {
				$grouped[$group] = [];
			}
			$grouped[$group][] = $menu;
		}

		// Return in logical order
		$ordered_groups = [ 'overview', 'analytics', 'campaigns', 'monitoring', 'administration', 'other' ];
		$result = [];
		
		foreach ( $ordered_groups as $group ) {
			if ( isset( $grouped[$group] ) ) {
				$result[$group] = $grouped[$group];
			}
		}

		return $result;
	}

	/**
	 * Get callback function for menu item
	 *
	 * @param string $callback_string
	 * @return callable|null
	 */
	private function get_callback( string $callback_string ): ?callable {
		if ( strpos( $callback_string, '::' ) !== false ) {
			[$class, $method] = explode( '::', $callback_string );
			
			// Use pre-instantiated admin instances
			if ( isset( $this->admin_instances[$class] ) && $this->admin_instances[$class] !== null ) {
				return [ $this->admin_instances[$class], $method ];
			}
			
			// Fallback: try to instantiate if not available
			$full_class = "\\FP\\DigitalMarketing\\Admin\\{$class}";
			
			if ( class_exists( $full_class ) ) {
				try {
					$this->admin_instances[$class] = new $full_class();
					return [ $this->admin_instances[$class], $method ];
				} catch ( \Throwable $e ) {
					if ( function_exists( 'error_log' ) ) {
						error_log( "FP Digital Marketing MenuManager: Failed to instantiate {$class} - " . $e->getMessage() );
					}
				}
			}
		}

		return [ $this, 'render_placeholder_page' ];
	}

	/**
	 * Render placeholder page for missing callbacks
	 *
	 * @return void
	 */
	public function render_placeholder_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'FP Digital Marketing Suite', 'fp-digital-marketing' ) . '</h1>';
		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'This page is being configured. Please check back soon.', 'fp-digital-marketing' );
		echo '</p></div>';
		echo '</div>';
	}

	/**
	 * Enqueue admin assets for menu styling
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		// Enqueue admin menu styles for all admin pages
		wp_enqueue_style(
			'fp-dms-admin-menu-rationalized',
			FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/admin-menu-rationalized.css',
			[],
			FP_DIGITAL_MARKETING_VERSION
		);
	}

	/**
	 * Get menu structure for debugging/documentation
	 *
	 * @return array
	 */
	public function get_menu_structure(): array {
		return $this->menu_structure;
	}

	/**
	 * Remove legacy menu items to prevent duplicates
	 * 
	 * This method should be called to clean up old menu registrations
	 *
	 * @return void
	 */
	public function remove_legacy_menus(): void {
		// List of legacy menu slugs to remove
		$legacy_slugs = [
			'fp-digital-marketing-reports',
			'fp-digital-marketing-alerts', 
			'fp-digital-marketing-anomalies',
			'fp-digital-marketing-utm-campaigns',
			'fp-digital-marketing-conversion-events',
			'fp-digital-marketing-segments-old',
			'fp-digital-marketing-cache',
			'fp-digital-marketing-security-old'
		];

		foreach ( $legacy_slugs as $slug ) {
			remove_submenu_page( self::MAIN_MENU_SLUG, $slug );
		}
	}
}
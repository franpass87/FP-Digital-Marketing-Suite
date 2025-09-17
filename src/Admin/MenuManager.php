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
         * Indicates whether the menu manager has been initialized.
         *
         * @var bool
         */
        private static bool $initialized = false;

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
                self::$initialized = true;
                add_action( 'admin_menu', [ $this, 'register_menus' ], 5 );
                add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
                add_action( 'admin_notices', [ $this, 'show_rationalization_notice' ] );
                add_action( 'wp_ajax_fp_dms_dismiss_menu_notice', [ $this, 'handle_dismiss_notice' ] );
        }

        /**
         * Returns the initialization state of the menu manager.
         */
        public static function is_initialized(): bool {
                return self::$initialized;
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
					'menu_slug' => 'fp-digital-marketing-reports',
					'callback' => 'Reports::render_reports_page',
					'group' => 'analytics'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Campaign Management', 'fp-digital-marketing' ),
					'menu_title' => __( '🚀 Campaign Management', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_CAMPAIGNS,
					'menu_slug' => 'fp-utm-campaign-manager',
					'callback' => 'UTMCampaignManager::render_page',
					'group' => 'campaigns'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Funnel Analysis', 'fp-digital-marketing' ),
					'menu_title' => __( '🎯 Funnel Analysis', 'fp-digital-marketing' ),
					'capability' => Capabilities::VIEW_REPORTS,
					'menu_slug' => 'fp-digital-marketing-funnel-analysis',
					'callback' => 'FunnelAnalysisAdmin::render_admin_page',
					'group' => 'campaigns'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Audience Segmentation', 'fp-digital-marketing' ),
					'menu_title' => __( '👥 Audience Segmentation', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_SEGMENTS,
					'menu_slug' => 'fp-audience-segments',
					'callback' => 'SegmentationAdmin::render_segmentation_page',
					'group' => 'campaigns'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Monitoring & Alerts', 'fp-digital-marketing' ),
					'menu_title' => __( '🔔 Monitoring & Alerts', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_ALERTS,
					'menu_slug' => 'fp-digital-marketing-alerts',
					'callback' => 'AlertingAdmin::display_admin_page',
					'group' => 'monitoring'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Anomaly Detection', 'fp-digital-marketing' ),
					'menu_title' => __( '🔍 Anomaly Detection', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_ALERTS,
					'menu_slug' => 'fp-digital-marketing-anomalies',
					'callback' => 'AnomalyDetectionAdmin::display_admin_page',
					'group' => 'monitoring'
				],
				[
					'parent_slug' => self::MAIN_MENU_SLUG,
					'page_title' => __( 'Performance Cache', 'fp-digital-marketing' ),
					'menu_title' => __( '⚡ Performance', 'fp-digital-marketing' ),
					'capability' => Capabilities::MANAGE_SETTINGS,
					'menu_slug' => 'fp-digital-marketing-cache-performance',
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
					'menu_slug' => 'fp-digital-marketing-onboarding',
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
				// Verify the method exists before returning
				if ( method_exists( $this->admin_instances[$class], $method ) ) {
					return [ $this->admin_instances[$class], $method ];
				}
			}
			
			// Fallback: try to instantiate if not available
			$full_class = "\\FP\\DigitalMarketing\\Admin\\{$class}";
			
			if ( class_exists( $full_class ) ) {
				try {
					$instance = new $full_class();
					if ( method_exists( $instance, $method ) ) {
						$this->admin_instances[$class] = $instance;
						return [ $instance, $method ];
					}
				} catch ( \Throwable $e ) {
					if ( function_exists( 'error_log' ) ) {
						error_log( "FP Digital Marketing MenuManager: Failed to instantiate {$class} - " . $e->getMessage() );
					}
				}
			}
			
			// Try to render with a safe fallback method
			return [ $this, 'render_admin_unavailable_page' ];
		}

		return [ $this, 'render_placeholder_page' ];
	}

	/**
	 * Render page for when admin module is unavailable but can show basic content
	 *
	 * @return void
	 */
	public function render_admin_unavailable_page(): void {
		$current_page = $_GET['page'] ?? '';
		$page_name = $this->get_page_name_from_slug( $current_page );
		
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $page_name ) . '</h1>';
		
		echo '<div class="notice notice-info"><p>';
		echo '<strong>' . esc_html__( 'Funzionalità in caricamento', 'fp-digital-marketing' ) . '</strong><br>';
		echo esc_html__( 'Questa funzionalità è attualmente in fase di inizializzazione. Si prega di aggiornare la pagina o tornare più tardi.', 'fp-digital-marketing' );
		echo '</p></div>';
		
		// Show basic navigation and setup steps
		echo '<div class="fp-admin-basic-content">';
		echo '<h2>' . esc_html__( 'Azioni disponibili', 'fp-digital-marketing' ) . '</h2>';
		
		echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">';
		
		// Dashboard card
		echo '<div style="border: 1px solid #ccd0d4; padding: 20px; background: #fff;">';
		echo '<h3>📊 ' . esc_html__( 'Dashboard Principale', 'fp-digital-marketing' ) . '</h3>';
		echo '<p>' . esc_html__( 'Visualizza panoramica completa delle metriche e KPI.', 'fp-digital-marketing' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Vai alla Dashboard', 'fp-digital-marketing' );
		echo '</a>';
		echo '</div>';
		
		// Settings card  
		echo '<div style="border: 1px solid #ccd0d4; padding: 20px; background: #fff;">';
		echo '<h3>⚙️ ' . esc_html__( 'Configurazione', 'fp-digital-marketing' ) . '</h3>';
		echo '<p>' . esc_html__( 'Configura le impostazioni del plugin e le connessioni.', 'fp-digital-marketing' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ) . '" class="button">';
		echo esc_html__( 'Impostazioni', 'fp-digital-marketing' );
		echo '</a>';
		echo '</div>';
		
		// Setup wizard card
		echo '<div style="border: 1px solid #ccd0d4; padding: 20px; background: #fff;">';
		echo '<h3>🛠️ ' . esc_html__( 'Setup Guidato', 'fp-digital-marketing' ) . '</h3>';
		echo '<p>' . esc_html__( 'Configura il plugin passo-passo con la procedura guidata.', 'fp-digital-marketing' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-onboarding' ) ) . '" class="button">';
		echo esc_html__( 'Avvia Setup', 'fp-digital-marketing' );
		echo '</a>';
		echo '</div>';
		
		echo '</div>'; // Close grid
		echo '</div>'; // Close content
		
		// Show debugging information for administrators
		if ( current_user_can( 'manage_options' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			echo '<div class="notice notice-info"><p>';
			echo '<strong>' . esc_html__( 'Info Debug (solo amministratori):', 'fp-digital-marketing' ) . '</strong><br>';
			echo sprintf( 
				esc_html__( 'Modulo admin per la pagina "%s" non disponibile. Verifica log degli errori per dettagli.', 'fp-digital-marketing' ),
				esc_html( $current_page )
			);
			echo '</p></div>';
		}
		
		echo '</div>';
	}

	/**
	 * Render placeholder page for missing callbacks
	 *
	 * @return void
	 */
	public function render_placeholder_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'FP Digital Marketing Suite', 'fp-digital-marketing' ) . '</h1>';
		echo '<div class="notice notice-warning"><p>';
		echo '<strong>' . esc_html__( 'Pagina in configurazione', 'fp-digital-marketing' ) . '</strong><br>';
		echo esc_html__( 'Questa pagina admin non è ancora completamente configurata. Se vedi questo messaggio, potrebbe esserci un problema con il caricamento del modulo amministrativo.', 'fp-digital-marketing' );
		echo '</p></div>';
		
		// Show debugging information for administrators
		if ( current_user_can( 'manage_options' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			echo '<div class="notice notice-info"><p>';
			echo '<strong>' . esc_html__( 'Informazioni di debug (solo per amministratori):', 'fp-digital-marketing' ) . '</strong><br>';
			echo esc_html__( 'Questa pagina placeholder viene mostrata quando il callback del menu non può essere risolto. Verifica che tutte le classi admin siano caricate correttamente.', 'fp-digital-marketing' );
			echo '</p></div>';
		}
		
		echo '<div style="margin-top: 20px;">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-dashboard' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Vai alla Dashboard', 'fp-digital-marketing' );
		echo '</a>';
		echo ' ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-digital-marketing-settings' ) ) . '" class="button">';
		echo esc_html__( 'Impostazioni Plugin', 'fp-digital-marketing' );
		echo '</a>';
		echo '</div>';
		
		echo '</div>';
	}

	/**
	 * Get page name from slug for display
	 *
	 * @param string $slug Page slug
	 * @return string Page display name
	 */
	private function get_page_name_from_slug( string $slug ): string {
		$page_names = [
			'fp-digital-marketing-dashboard' => __( 'Dashboard', 'fp-digital-marketing' ),
			'fp-digital-marketing-reports' => __( 'Analytics & Reports', 'fp-digital-marketing' ),
			'fp-utm-campaign-manager' => __( 'Campaign Management', 'fp-digital-marketing' ),
			'fp-digital-marketing-funnel-analysis' => __( 'Funnel Analysis', 'fp-digital-marketing' ),
			'fp-audience-segments' => __( 'Audience Segmentation', 'fp-digital-marketing' ),
			'fp-digital-marketing-alerts' => __( 'Monitoring & Alerts', 'fp-digital-marketing' ),
			'fp-digital-marketing-anomalies' => __( 'Anomaly Detection', 'fp-digital-marketing' ),
			'fp-digital-marketing-cache-performance' => __( 'Performance Cache', 'fp-digital-marketing' ),
			'fp-digital-marketing-security' => __( 'Security Settings', 'fp-digital-marketing' ),
			'fp-digital-marketing-settings' => __( 'Settings', 'fp-digital-marketing' ),
			'fp-digital-marketing-onboarding' => __( 'Setup Wizard', 'fp-digital-marketing' ),
		];
		
		return $page_names[$slug] ?? __( 'FP Digital Marketing', 'fp-digital-marketing' );
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
	 * Show admin notice about menu rationalization
	 *
	 * @return void
	 */
	public function show_rationalization_notice(): void {
		// Only show on FP Digital Marketing pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'fp-digital-marketing' ) === false ) {
			return;
		}

		// Only show to users with manage_options capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show notice only once per user
		$user_id = get_current_user_id();
		$notice_dismissed = get_user_meta( $user_id, 'fp_dms_menu_rationalization_notice_dismissed', true );
		
		if ( $notice_dismissed ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible" data-notice="fp-dms-menu-rationalization">';
		echo '<p><strong>' . esc_html__( 'FP Digital Marketing Suite', 'fp-digital-marketing' ) . '</strong> - ';
		echo esc_html__( 'The admin menu has been rationalized and reorganized for better user experience. All functionality remains accessible through the new logical grouping.', 'fp-digital-marketing' );
		echo '</p>';
		echo '</div>';

		// Add script to handle dismissible notice
		echo '<script>
		jQuery(document).ready(function($) {
			$(document).on("click", "[data-notice=\'fp-dms-menu-rationalization\'] .notice-dismiss", function() {
				$.post(ajaxurl, {
					action: "fp_dms_dismiss_menu_notice",
					nonce: "' . wp_create_nonce( 'fp_dms_dismiss_notice' ) . '"
				});
			});
		});
		</script>';
	}

	/**
	 * Handle AJAX request to dismiss menu rationalization notice
	 *
	 * @return void
	 */
	public function handle_dismiss_notice(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'fp_dms_dismiss_notice' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		// Mark notice as dismissed for current user
		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'fp_dms_menu_rationalization_notice_dismissed', true );

		wp_send_json_success();
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
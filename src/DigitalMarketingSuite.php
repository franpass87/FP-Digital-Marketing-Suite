<?php
/**
 * Main class for FP Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing;

use FP\DigitalMarketing\PostTypes\ClientePostType;
use FP\DigitalMarketing\Admin\ClienteMeta;
use FP\DigitalMarketing\Admin\SeoMeta;
use FP\DigitalMarketing\Admin\Settings;
use FP\DigitalMarketing\Admin\Reports;
use FP\DigitalMarketing\Admin\Dashboard;
use FP\DigitalMarketing\Admin\SecurityAdmin;
use FP\DigitalMarketing\Admin\CachePerformance;
use FP\DigitalMarketing\Admin\OnboardingWizard;
use FP\DigitalMarketing\Admin\AlertingAdmin;
use FP\DigitalMarketing\Admin\AnomalyDetectionAdmin;
use FP\DigitalMarketing\Admin\AnomalyRadar;
use FP\DigitalMarketing\Admin\UTMCampaignManager;
use FP\DigitalMarketing\Admin\ConversionEventsAdmin;
use FP\DigitalMarketing\Admin\SegmentationAdmin;
use FP\DigitalMarketing\Admin\FunnelAnalysisAdmin;
use FP\DigitalMarketing\Admin\MenuManager;
use FP\DigitalMarketing\Admin\PlatformConnections;
use FP\DigitalMarketing\Database\MetricsCacheTable;
use FP\DigitalMarketing\Database\AlertRulesTable;
use FP\DigitalMarketing\Database\AnomalyRulesTable;
use FP\DigitalMarketing\Database\DetectedAnomaliesTable;
use FP\DigitalMarketing\Database\UTMCampaignsTable;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Database\AudienceSegmentTable;
use FP\DigitalMarketing\Database\FunnelTable;
use FP\DigitalMarketing\Database\CustomerJourneyTable;
use FP\DigitalMarketing\Database\CustomReportsTable;
use FP\DigitalMarketing\Database\SocialSentimentTable;
use FP\DigitalMarketing\Helpers\ReportScheduler;
use FP\DigitalMarketing\Helpers\SyncEngine;
use FP\DigitalMarketing\Helpers\SegmentationEngine;
use FP\DigitalMarketing\API\SegmentationAPI;
use FP\DigitalMarketing\Helpers\SeoFrontendOutput;
use FP\DigitalMarketing\Helpers\FrontendTracking;
use FP\DigitalMarketing\Helpers\XmlSitemap;
use FP\DigitalMarketing\Helpers\SchemaGenerator;
use FP\DigitalMarketing\Helpers\FAQBlock;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\DashboardWidgets;
use FP\DigitalMarketing\Helpers\DataExporter;
use FP\DigitalMarketing\Helpers\EmailNotifications;
use FP\DigitalMarketing\Helpers\PerformanceCache;
use FP\DigitalMarketing\Helpers\RuntimeLogger;
use FP\DigitalMarketing\Helpers\URLShortener;
use FP\DigitalMarketing\Helpers\SiteHealth;
use FP\DigitalMarketing\Setup\SetupWizard;
use FP\DigitalMarketing\Setup\SettingsManager;

/**
 * Main application class
 */
class DigitalMarketingSuite {

		/**
		 * Option name used to persist the installed plugin version.
		 */
	private const VERSION_OPTION = 'fp_digital_marketing_version';

		/**
		 * Default plugin version used as a fallback when constants are missing.
		 */
        private const PLUGIN_VERSION = '1.2.0';

		/**
		 * Registry key used for the admin menu manager component.
		 */
	private const MENU_MANAGER_KEY = 'menu_manager';

		/**
		 * Supported execution context identifiers.
		 */
	private const CONTEXT_ADMIN    = 'admin';
	private const CONTEXT_FRONTEND = 'frontend';
	private const CONTEXT_CLI      = 'cli';
	private const CONTEXT_CRON     = 'cron';
	private const CONTEXT_ANY      = 'any';

		/**
		 * Default priority assigned to lifecycle definitions when none is provided.
		 */
	private const DEFAULT_PRIORITY = 10;

		/**
		 * Map of version-specific upgrade routines executed when the plugin updates.
		 *
		 * Each array key represents the target version and maps to a list of routine definitions.
		 * Supported keys:
		 * - callback: Callable that receives the previous and current version strings.
		 * - label: Optional human readable label for logging.
		 *
		 * @var array<string, array<int, array<string, mixed>>>
		 */
	private const UPGRADE_DEFINITIONS = [
                '1.1.0' => [
                        [
                                'callback' => [ SettingsManager::class, 'migrate_legacy_options' ],
                                'label'    => 'SettingsManager::migrate_legacy_options()',
                        ],
                ],
                '1.2.0' => [
                        [
                                'callback' => [ SettingsManager::class, 'upgrade_menu_state_schema' ],
                                'label'    => 'SettingsManager::upgrade_menu_state_schema()',
                        ],
                        [
                                'callback' => [ PerformanceCache::class, 'upgrade_cache_schema' ],
                                'label'    => 'PerformanceCache::upgrade_cache_schema()',
                        ],
                        [
                                'callback' => [ self::class, 'purge_runtime_cache_layers' ],
                                'label'    => 'DigitalMarketingSuite::purge_runtime_cache_layers()',
                        ],
                ],
	];

		/**
		 * Singleton instance reference.
		 *
		 * @var self|null
		 */
	private static ?self $instance = null;

		/**
		 * Application version
		 *
		 * @var string
		 */
	private string $version = '0.0.0';

		/**
		 * Indicates whether WordPress hooks were already registered.
		 *
		 * @var bool
		 */
	private bool $hooks_registered = false;

		/**
		 * Loaded component instances keyed by their registry name.
		 *
		 * @var array<string, object|null>
		 */
	private array $components = [];

		/**
		 * Cached component definitions.
		 *
		 * @var array<string, array<string, mixed>|class-string>|null
		 */
	private ?array $component_definition_cache = null;

		/**
		 * Cached static initializer definitions.
		 *
		 * @var array<int, array<string, string|null|int>>|null
		 */
	private static ?array $static_initializer_cache = null;

		/**
		 * Cached database table definitions.
		 *
		 * @var array<class-string, array<int, array<string, string|int>>>|null
		 */
	private static ?array $table_definition_cache = null;

		/**
		 * Cached upgrade definition map.
		 *
		 * @var array<string, array<int, array<string, mixed>>>|null
		 */
	private static ?array $upgrade_definition_cache = null;

		/**
		 * Indicates whether component instantiation has already occurred.
		 *
		 * @var bool
		 */
	private bool $components_instantiated = false;

		/**
		 * Tracks if the setup wizard has already been bootstrapped.
		 *
		 * @var bool
		 */
	private bool $setup_wizard_bootstrapped = false;

		/**
		 * Cached execution context flags keyed by context name.
		 *
		 * @var array<string, bool>
		 */
	private array $context_flags = [
		self::CONTEXT_ADMIN    => false,
		self::CONTEXT_FRONTEND => false,
		self::CONTEXT_CLI      => false,
		self::CONTEXT_CRON     => false,
	];

	/**
	 * Map of component properties to their class definitions and metadata.
	 *
	 * Supported keys:
	 * - class: Fully qualified class name of the component.
	 * - menu_label: Optional label exposed in the admin menu builder.
	 * - init: Optional instance method invoked during init. Defaults to "init". Use null to skip.
	 * - contexts: Optional string or list of contexts where the component should boot.
	 *             Supported values: "admin", "frontend", "cli", "cron", "any".
	 * - priority: Optional integer priority that controls instantiation order. Lower values run earlier.
	 *
	 * @var array<string, array<string, mixed>|class-string>
	 */
	private const COMPONENT_DEFINITIONS = [
		'cliente_post_type'       => [ 'class' => ClientePostType::class ],
		'cliente_meta'            => [
			'class'    => ClienteMeta::class,
			'contexts' => self::CONTEXT_ADMIN,
		],
		'seo_meta'                => [
			'class'    => SeoMeta::class,
			'contexts' => self::CONTEXT_ADMIN,
		],
		'settings'                => [
			'class'      => Settings::class,
			'menu_label' => 'Settings',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'reports'                 => [
			'class'      => Reports::class,
			'menu_label' => 'Reports',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'dashboard'               => [
			'class'      => Dashboard::class,
			'menu_label' => 'Dashboard',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'security_admin'          => [
			'class'      => SecurityAdmin::class,
			'menu_label' => 'SecurityAdmin',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'cache_performance'       => [
			'class'      => CachePerformance::class,
			'menu_label' => 'CachePerformance',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'onboarding_wizard'       => [
			'class'      => OnboardingWizard::class,
			'menu_label' => 'OnboardingWizard',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'alerting_admin'          => [
			'class'      => AlertingAdmin::class,
			'menu_label' => 'AlertingAdmin',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'anomaly_detection_admin' => [
			'class'      => AnomalyDetectionAdmin::class,
			'menu_label' => 'AnomalyDetectionAdmin',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'anomaly_radar'           => [
			'class'    => AnomalyRadar::class,
			'contexts' => self::CONTEXT_ADMIN,
		],
		'utm_campaign_manager'    => [
			'class'      => UTMCampaignManager::class,
			'menu_label' => 'UTMCampaignManager',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'conversion_events_admin' => [
			'class'      => ConversionEventsAdmin::class,
			'menu_label' => 'ConversionEventsAdmin',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'segmentation_admin'      => [
			'class'      => SegmentationAdmin::class,
			'menu_label' => 'SegmentationAdmin',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'funnel_analysis_admin'   => [
			'class'      => FunnelAnalysisAdmin::class,
			'menu_label' => 'FunnelAnalysisAdmin',
			'contexts'   => self::CONTEXT_ADMIN,
		],
		'platform_connections'    => [
			'class'      => PlatformConnections::class,
			'menu_label' => 'PlatformConnections',
			'contexts'   => self::CONTEXT_ADMIN,
		],
	];

	/**
	 * Static initializers executed during bootstrap.
	 *
	 * Supported keys:
	 * - class: Fully qualified class name owning the static method.
	 * - method: Static method name to call.
	 * - label: Optional label for logging context.
	 * - priority: Optional integer that controls execution order. Lower values run earlier.
	 *
	 * @var array<int, array<string, string|null|int>>
	 */
	private const STATIC_INITIALIZERS = [
		[
			'class'  => URLShortener::class,
			'method' => 'bootstrap',
			'label'  => 'URLShortener::bootstrap()',
		],
		[
			'class'  => Capabilities::class,
			'method' => 'init',
		],
		[
			'class'  => ReportScheduler::class,
			'method' => 'init',
		],
		[
			'class'  => SyncEngine::class,
			'method' => 'init',
		],
		[
			'class'  => SegmentationEngine::class,
			'method' => 'init',
		],
		[
			'class'  => SegmentationAPI::class,
			'method' => 'init',
		],
		[
			'class'  => SeoFrontendOutput::class,
			'method' => 'init',
		],
		[
			'class'  => FrontendTracking::class,
			'method' => 'init',
		],
		[
			'class'  => SchemaGenerator::class,
			'method' => 'init',
		],
		[
			'class'  => FAQBlock::class,
			'method' => 'init',
		],
		[
			'class'  => XmlSitemap::class,
			'method' => 'init',
		],
		[
			'class'  => XmlSitemap::class,
			'method' => 'init_robots_txt',
			'label'  => 'XmlSitemap::init_robots_txt()',
		],
		[
			'class'  => DashboardWidgets::class,
			'method' => 'init',
		],
		[
			'class'  => DataExporter::class,
			'method' => 'init',
		],
		[
			'class'  => EmailNotifications::class,
			'method' => 'init',
		],
		[
			'class'  => PerformanceCache::class,
			'method' => 'schedule_cache_warmup',
			'label'  => 'PerformanceCache::schedule_cache_warmup()',
		],
		[
			'class'  => SiteHealth::class,
			'method' => 'init',
		],
	];

	/**
	 * Database table definitions for setup and verification.
	 *
	 * Supported keys for each operation:
	 * - check: Optional method that verifies table existence.
	 * - create: Method that provisions the table when missing.
	 * - label: Optional label for logging context.
	 * - priority: Optional integer that controls execution order. Lower values run earlier.
	 *
	 * @var array<class-string, array<int, array<string, string|int>>>
	 */
	private const TABLE_DEFINITIONS = [
		MetricsCacheTable::class      => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
		AlertRulesTable::class        => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
		AnomalyRulesTable::class      => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
		DetectedAnomaliesTable::class => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
		UTMCampaignsTable::class      => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
		ConversionEventsTable::class  => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
		AudienceSegmentTable::class   => [
			[
				'check'  => 'segments_table_exists',
				'create' => 'create_segments_table',
				'label'  => 'AudienceSegmentTable::create_segments_table()',
			],
			[
				'check'  => 'membership_table_exists',
				'create' => 'create_membership_table',
				'label'  => 'AudienceSegmentTable::create_membership_table()',
			],
		],
		FunnelTable::class            => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
			[
				'check'  => 'stages_table_exists',
				'create' => 'create_stages_table',
				'label'  => 'FunnelTable::create_stages_table()',
			],
		],
		CustomerJourneyTable::class   => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
			[
				'check'  => 'sessions_table_exists',
				'create' => 'create_sessions_table',
				'label'  => 'CustomerJourneyTable::create_sessions_table()',
			],
		],
		CustomReportsTable::class     => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
		SocialSentimentTable::class   => [
			[
				'check'  => 'table_exists',
				'create' => 'create_table',
			],
		],
	];

		/**
		 * Constructor with error handling
		 */
	public function __construct() {
			$this->detect_execution_context();
			$this->version = self::get_current_version_string();
	}

		/**
		 * Retrieve the shared plugin instance.
		 *
		 * @return self
		 */
	public static function instance(): self {
		if ( null === self::$instance ) {
				self::$instance = new self();
		}

			return self::$instance;
	}

		/**
		 * Register WordPress lifecycle hooks once.
		 *
		 * @return void
		 */
	public function register_hooks(): void {
		if ( $this->hooks_registered || ! function_exists( 'add_action' ) ) {
				return;
		}

			add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
			$this->hooks_registered = true;
	}

		/**
		 * Detect and cache the current execution context flags.
		 *
		 * @return void
		 */
	private function detect_execution_context(): void {
			$is_cli   = defined( 'WP_CLI' ) && WP_CLI;
			$is_cron  = function_exists( 'wp_doing_cron' ) ? wp_doing_cron() : false;
			$is_admin = function_exists( 'is_admin' ) ? is_admin() : false;

			$this->context_flags = [
				self::CONTEXT_CLI      => $is_cli,
				self::CONTEXT_CRON     => $is_cron,
				self::CONTEXT_ADMIN    => $is_admin && ! $is_cli,
				self::CONTEXT_FRONTEND => ! $is_admin && ! $is_cli && ! $is_cron,
			];
	}

		/**
		 * Check whether the current request matches a given execution context.
		 *
		 * @param string $context Context name (admin, frontend, cli, cron, any).
		 *
		 * @return bool
		 */
	public function is_context( string $context ): bool {
			$normalized = strtolower( $context );

		if ( self::CONTEXT_ANY === $normalized ) {
				return true;
		}

			return $this->context_flags[ $normalized ] ?? false;
	}

		/**
		 * Refresh the cached execution context flags.
		 *
		 * @return void
		 */
	public function refresh_execution_context(): void {
			$this->detect_execution_context();
	}

		/**
		 * Retrieve the cached execution context flags.
		 *
		 * @return array<string, bool>
		 */
	public function get_context_flags(): array {
			return $this->context_flags;
	}

		/**
		 * Reset cached definition maps so late filters can take effect.
		 *
		 * @return void
		 */
	public function reset_definition_cache(): void {
			$this->component_definition_cache = null;
			self::$static_initializer_cache   = null;
			self::$table_definition_cache     = null;
			self::$upgrade_definition_cache   = null;
	}

		/**
		 * Rebuild the component registry using the latest definition maps.
		 *
		 * @param bool $reinitialize Optional. Whether to rerun component initializers after rebuilding.
		 *                           Defaults to false.
		 *
		 * @return void
		 */
	public function rebuild_component_registry( bool $reinitialize = false ): void {
			$this->reset_definition_cache();
			$this->components              = [];
			$this->components_instantiated = false;

			$this->instantiate_components();

		if ( $reinitialize ) {
				$this->initialize_components();
		}
	}

	/**
	 * Instantiate and wire plugin components safely.
	 *
	 * @return void
	 */
	private function instantiate_components(): void {
		if ( $this->components_instantiated ) {
			return;
		}

		foreach ( $this->get_component_definitions() as $property => $definition ) {
			if ( ! $this->should_boot_component( $definition ) ) {
				continue;
			}

			$class = is_array( $definition ) ? ( $definition['class'] ?? null ) : $definition;

			if ( ! is_string( $class ) ) {
				continue;
			}

			$this->instantiate_component( $property, $class );
		}

		$this->boot_menu_manager();
		$this->components_instantiated = true;
	}

	/**
	 * Instantiate a single component and capture initialization errors.
	 *
	 * @param string $property Property name to populate.
	 * @param string $class    Class to instantiate.
	 *
	 * @return void
	 */
	private function instantiate_component( string $property, string $class ): void {
		if ( ! class_exists( $class ) ) {
			return;
		}

		try {
			$this->set_component_instance( $property, new $class() );
		} catch ( \Throwable $e ) {
			$this->set_component_instance( $property, null );
			self::log_initialization_error( self::get_component_label( $class ), $e );
		}
	}

	/**
	 * Persist a component instance reference.
	 *
	 * @param string      $property Registry key.
	 * @param object|null $instance Component instance to store.
	 *
	 * @return void
	 */
	private function set_component_instance( string $property, ?object $instance ): void {
		if ( null === $instance ) {
			unset( $this->components[ $property ] );

			return;
		}

		$this->components[ $property ] = $instance;
	}

	/**
	 * Retrieve a component instance by its registry key.
	 *
	 * @param string $property Registry key.
	 *
	 * @return object|null
	 */
	private function get_component_instance( string $property ): ?object {
		$instance = $this->components[ $property ] ?? null;

		return is_object( $instance ) ? $instance : null;
	}

	/**
	 * Expose a component instance from the registry.
	 *
	 * @param string $property Registry key.
	 *
	 * @return object|null
	 */
	public function get_component( string $property ): ?object {
		return $this->get_component_instance( $property );
	}

	/**
	 * Build the admin menu manager from the available admin modules.
	 *
	 * @return void
	 */
	private function boot_menu_manager(): void {
		if ( ! $this->is_context( self::CONTEXT_ADMIN ) ) {
			return;
		}

		$admin_instances = [];

		foreach ( $this->get_component_definitions() as $property => $definition ) {
			$label = is_array( $definition ) ? ( $definition['menu_label'] ?? null ) : null;

			if ( ! $label ) {
				continue;
			}

			$instance = $this->get_component_instance( $property );

			if ( null !== $instance ) {
				$admin_instances[ $label ] = $instance;
			}
		}

		if ( empty( $admin_instances ) ) {
			$this->set_component_instance( self::MENU_MANAGER_KEY, null );

			return;
		}

		try {
			$this->set_component_instance( self::MENU_MANAGER_KEY, new MenuManager( $admin_instances ) );
		} catch ( \Throwable $e ) {
			$this->set_component_instance( self::MENU_MANAGER_KEY, null );
			self::log_initialization_error( self::get_component_label( MenuManager::class ), $e );
		}
	}

	/**
	 * Determine if the component should boot in the current execution context.
	 *
	 * @param array<string, mixed>|string $definition Component definition metadata.
	 *
	 * @return bool
	 */
	private function should_boot_component( $definition ): bool {
		if ( ! is_array( $definition ) ) {
			return true;
		}

		if ( empty( $definition['contexts'] ) ) {
			return true;
		}

		$contexts = (array) $definition['contexts'];

		foreach ( $contexts as $context ) {
			if ( $this->is_context( (string) $context ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieve component definitions with filters applied.
	 *
	 * @return array<string, array<string, mixed>|class-string>
	 */
	private function get_component_definitions(): array {
		if ( null === $this->component_definition_cache ) {
			$definitions = self::apply_filters_to_definitions(
				'fp_dms_component_definitions',
				self::COMPONENT_DEFINITIONS
			);

			$this->component_definition_cache = self::sort_definition_map_by_priority( $definitions );
		}

		return $this->component_definition_cache;
	}

	/**
	 * Retrieve static initializer definitions with filters applied.
	 *
	 * @return array<int, array<string, string|null|int>>
	 */
	private static function get_static_initializers_config(): array {
		if ( null === self::$static_initializer_cache ) {
			$initializers = self::apply_filters_to_definitions(
				'fp_dms_static_initializers',
				self::STATIC_INITIALIZERS
			);

			self::$static_initializer_cache = self::sort_definition_list_by_priority( $initializers );
		}

		return self::$static_initializer_cache;
	}

	/**
	 * Retrieve table definitions with filters applied.
	 *
	 * @return array<class-string, array<int, array<string, string|int>>>
	 */
	private static function get_table_definitions_config(): array {
		if ( null === self::$table_definition_cache ) {
				$definitions = self::apply_filters_to_definitions(
					'fp_dms_table_definitions',
					self::TABLE_DEFINITIONS
				);

			foreach ( $definitions as $class => $operations ) {
				if ( is_array( $operations ) ) {
						$definitions[ $class ] = self::sort_definition_list_by_priority( $operations );
				}
			}

				self::$table_definition_cache = $definitions;
		}

			return self::$table_definition_cache;
	}

		/**
		 * Retrieve upgrade definitions with filters applied and sorted by version.
		 *
		 * @return array<string, array<int, array<string, mixed>>>
		 */
	private static function get_upgrade_definitions(): array {
		if ( null === self::$upgrade_definition_cache ) {
				$definitions = self::apply_filters_to_definitions(
					'fp_dms_upgrade_definitions',
					self::UPGRADE_DEFINITIONS
				);

			if ( ! is_array( $definitions ) ) {
				$definitions = [];
			}

				uksort(
					$definitions,
					static function ( $a, $b ): int {
								return version_compare( (string) $a, (string) $b );
					}
				);

				self::$upgrade_definition_cache = $definitions;
		}

			return self::$upgrade_definition_cache;
	}

		/**
		 * Apply WordPress filters to a definitions array when available.
		 *
		 * @param string $hook        Filter hook name.
		 * @param array  $definitions Definitions to filter.
		 *
		 * @return array
		 */
	private static function apply_filters_to_definitions( string $hook, array $definitions ): array {
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( $hook, $definitions );

			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		}

		return $definitions;
	}

	/**
	 * Sort an associative definitions map by priority while preserving keys.
	 *
	 * @param array<string, mixed> $definitions Definitions keyed by identifier.
	 *
	 * @return array<string, mixed>
	 */
	private static function sort_definition_map_by_priority( array $definitions ): array {
		$indexed  = [];
		$position = 0;

		foreach ( $definitions as $key => $definition ) {
			$indexed[] = [
				'key'        => $key,
				'definition' => $definition,
				'priority'   => self::extract_priority( $definition ),
				'position'   => $position++,
			];
		}

		usort(
			$indexed,
			static function ( array $a, array $b ): int {
				if ( $a['priority'] === $b['priority'] ) {
					return $a['position'] <=> $b['position'];
				}

				return $a['priority'] <=> $b['priority'];
			}
		);

		$sorted = [];

		foreach ( $indexed as $item ) {
			$sorted[ $item['key'] ] = $item['definition'];
		}

		return $sorted;
	}

	/**
	 * Sort a list of definitions by priority while preserving order for ties.
	 *
	 * @param array<int, mixed> $definitions Indexed definitions array.
	 *
	 * @return array<int, mixed>
	 */
	private static function sort_definition_list_by_priority( array $definitions ): array {
		$indexed = [];

		foreach ( $definitions as $index => $definition ) {
			$indexed[] = [
				'definition' => $definition,
				'priority'   => self::extract_priority( $definition ),
				'position'   => $index,
			];
		}

		usort(
			$indexed,
			static function ( array $a, array $b ): int {
				if ( $a['priority'] === $b['priority'] ) {
					return $a['position'] <=> $b['position'];
				}

				return $a['priority'] <=> $b['priority'];
			}
		);

		return array_map(
			static fn ( array $item ) => $item['definition'],
			$indexed
		);
	}

	/**
	 * Extract the numeric priority from a definition.
	 *
	 * @param mixed $definition Definition entry that may include a priority.
	 *
	 * @return int
	 */
	private static function extract_priority( $definition ): int {
		if ( ! is_array( $definition ) || ! array_key_exists( 'priority', $definition ) ) {
			return self::DEFAULT_PRIORITY;
		}

		$value = $definition['priority'];

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		return self::DEFAULT_PRIORITY;
	}

		/**
		 * Provide a human readable component name for logging.
		 *
		 * @param string $class Fully qualified class name.
		 *
		 * @return string
		 */
	private static function get_component_label( string $class ): string {
			$parts = explode( '\\', $class );

			return (string) array_pop( $parts );
	}

		/**
		 * Provide a human readable label for an arbitrary callback definition.
		 *
		 * @param mixed $callback Callback definition to describe.
		 *
		 * @return string
		 */
	private static function describe_callback( $callback ): string {
		if ( is_string( $callback ) ) {
				return $callback;
		}

		if ( is_array( $callback ) ) {
				$target = $callback[0] ?? null;
				$method = $callback[1] ?? '';

			if ( is_object( $target ) ) {
					$target = get_class( $target );
			}

				$target = is_string( $target ) ? $target : 'callback';
				$method = is_string( $method ) && '' !== $method ? $method : 'call';

				return sprintf( '%s::%s()', $target, $method );
		}

			return 'callback';
	}

	/**
	 * Log initialization errors
	 *
	 * @param string     $component Component name
	 * @param \Throwable $error Error object
	 * @return void
	 */
	private static function log_initialization_error( string $component, \Throwable $error ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log(
					sprintf(
						'FP Digital Marketing: Failed to initialize %s - %s in %s:%d',
						$component,
						$error->getMessage(),
						$error->getFile(),
						$error->getLine()
					)
				);
		}
	}

		/**
		 * Get application version
		 *
		 * @return string The application version.
		 */
	public function get_version(): string {
			return $this->version;
	}

		/**
		 * Determine the plugin version string using the public constant when available.
		 *
		 * @return string
		 */
	private static function get_current_version_string(): string {
		if ( defined( 'FP_DIGITAL_MARKETING_VERSION' ) ) {
				return (string) FP_DIGITAL_MARKETING_VERSION;
		}

			return self::PLUGIN_VERSION;
	}

		/**
		 * Handle the plugins_loaded lifecycle.
		 *
		 * @return void
		 */
	public function on_plugins_loaded(): void {
		RuntimeLogger::boot();

		$this->detect_execution_context();

		if ( ! $this->is_wordpress_supported() ) {
				$this->add_admin_error_notice( __( 'FP Digital Marketing Suite requires WordPress 5.0 or higher.', 'fp-digital-marketing' ) );
				return;
		}

		if ( ! $this->is_php_supported() ) {
				$this->add_admin_error_notice( __( 'FP Digital Marketing Suite requires PHP 7.4 or higher.', 'fp-digital-marketing' ) );
				return;
		}

		try {
				$this->instantiate_components();
				$this->maybe_run_upgrade_routines();
				$this->init();
				$this->bootstrap_setup_wizard();
		} catch ( \Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
					error_log( 'FP Digital Marketing: Initialization error - ' . $e->getMessage() );
			}

				$this->add_admin_error_notice( __( 'FP Digital Marketing Suite failed to initialize. Check error logs for details.', 'fp-digital-marketing' ) );
		}
	}

		/**
		 * Check if the current WordPress version is supported.
		 *
		 * @return bool
		 */
	private function is_wordpress_supported(): bool {
			return version_compare( get_bloginfo( 'version' ), '5.0', '>=' );
	}

		/**
		 * Check if the current PHP version is supported.
		 *
		 * @return bool
		 */
	private function is_php_supported(): bool {
			return version_compare( PHP_VERSION, '7.4', '>=' );
	}

		/**
		 * Display an error notice in the WordPress admin area.
		 *
		 * @param string $message Notice message.
		 *
		 * @return void
		 */
	private function add_admin_error_notice( string $message ): void {
			add_action(
				'admin_notices',
				static function () use ( $message ) {
					if ( ! current_user_can( 'manage_options' ) ) {
							return;
					}

							echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
				}
			);
	}

		/**
		 * Initialize the setup wizard once.
		 *
		 * @return void
		 */
	private function bootstrap_setup_wizard(): void {
		if ( $this->setup_wizard_bootstrapped || ! $this->is_context( self::CONTEXT_ADMIN ) ) {
				return;
		}

		if ( ! class_exists( SetupWizard::class ) ) {
				return;
		}

			$has_activation_redirect = (bool) get_transient( 'fp_dms_activation_redirect' );
			$should_bootstrap        = $has_activation_redirect;

		if ( class_exists( SettingsManager::class ) ) {
				$should_bootstrap = $should_bootstrap
						|| ! SettingsManager::is_wizard_completed()
						|| SettingsManager::is_wizard_menu_enabled();
		} else {
				$should_bootstrap = true;
		}

		if ( ! $should_bootstrap ) {
				return;
		}

			$this->setup_wizard_bootstrapped = true;
			new SetupWizard();
	}

		/**
		 * Initialize the application with error handling
		 *
		 * @return void
		 */
	public function init(): void {
			// Load text domain for internationalization.
			$this->load_textdomain();

			$this->initialize_components();
			$this->run_static_initializers();

			$this->execute_safely( fn () => $this->schedule_cleanup_tasks(), 'schedule_cleanup_tasks()' );

			$this->ensure_database_tables();

			$this->execute_safely( static fn () => do_action( 'fp_digital_marketing_suite_init' ), 'fp_digital_marketing_suite_init action' );
	}

		/**
		 * Execute version-specific upgrade routines when the plugin version changes.
		 *
		 * @return void
		 */
        private function maybe_run_upgrade_routines(): void {
                if ( ! function_exists( 'get_option' ) && ( ! self::is_multisite_environment() || ! function_exists( 'get_site_option' ) ) ) {
                                return;
                }

			$stored_version   = self::get_stored_version();
			$previous_version = is_string( $stored_version ) && '' !== $stored_version ? $stored_version : '0.0.0';
			$current_version  = $this->get_version();

		if ( version_compare( $current_version, $previous_version, '=' ) ) {
				return;
		}

		foreach ( self::get_upgrade_definitions() as $version => $routines ) {
			if ( ! is_array( $routines ) ) {
					continue;
			}

			if ( version_compare( (string) $version, $previous_version, '<=' ) ) {
					continue;
			}

			if ( version_compare( (string) $version, $current_version, '>' ) ) {
					continue;
			}

			foreach ( $routines as $routine ) {
					$callback = $routine['callback'] ?? null;

				if ( ! is_callable( $callback ) ) {
						continue;
				}

					$label = is_string( $routine['label'] ?? null )
							? $routine['label']
							: self::describe_callback( $callback );

					$this->execute_safely(
						static function () use ( $callback, $previous_version, $current_version ): void {
									call_user_func( $callback, $previous_version, $current_version );
						},
						$label
					);
                }
        }

                        self::update_version_storage( $current_version, self::is_network_active() );

                if ( function_exists( 'do_action' ) ) {
                                $this->execute_safely(
                                        static function () use ( $previous_version, $current_version ): void {
								do_action( 'fp_dms_after_upgrade', $previous_version, $current_version );
					},
					'fp_dms_after_upgrade action'
				);
                }
        }

        /**
         * Flush runtime caches after an upgrade to avoid serving stale data.
         *
         * @param string $previous_version Previously installed version string.
         * @param string $current_version  Newly installed version string.
         * @return void
         */
        public static function purge_runtime_cache_layers( string $previous_version = '', string $current_version = '' ): void {
                if ( class_exists( PerformanceCache::class ) && method_exists( PerformanceCache::class, 'invalidate_all' ) ) {
                                self::execute_callback_safely(
                                        static fn () => PerformanceCache::invalidate_all(),
                                        'PerformanceCache::invalidate_all()'
                                );
                }

                self::execute_callback_safely(
                        static function (): void {
                                if ( function_exists( 'wp_cache_flush' ) ) {
                                        wp_cache_flush();
                                }
                        },
                        'wp_cache_flush()'
                );

                self::execute_callback_safely(
                        static function (): void {
                                if ( function_exists( 'wp_cache_flush_runtime' ) ) {
                                        wp_cache_flush_runtime();
                                }
                        },
                        'wp_cache_flush_runtime()'
                );

                self::execute_callback_safely(
                        static function (): void {
                                if ( function_exists( 'opcache_reset' ) ) {
                                        $opcache_enabled = ini_get( 'opcache.enable' );

                                        if ( false !== $opcache_enabled && '0' !== (string) $opcache_enabled ) {
                                                opcache_reset();
                                        }
                                }
                        },
                        'opcache_reset()'
                );
        }

		/**
		 * Invoke component initializers and the menu manager lifecycle hook.
		 *
		 * @return void
		 */
	private function initialize_components(): void {
		foreach ( $this->get_component_definitions() as $property => $definition ) {
				$method = 'init';

			if ( is_array( $definition ) ) {
				$method = $definition['init'] ?? 'init';
			}

			if ( null === $method ) {
					continue;
			}

				$this->invoke_component_method( $property, $method );
		}

			$this->invoke_component_method( self::MENU_MANAGER_KEY, 'init' );
	}

		/**
		 * Execute static lifecycle initializers in a safe, centralized loop.
		 *
		 * @return void
		 */
	private function run_static_initializers(): void {
		foreach ( self::get_static_initializers_config() as $initializer ) {
				$this->invoke_static_method(
					$initializer['class'],
					$initializer['method'],
					$initializer['label'] ?? null
				);
		}
	}

	/**
	 * Load plugin text domain for internationalization
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		\load_plugin_textdomain(
			'fp-digital-marketing',
			false,
			dirname( \plugin_basename( FP_DIGITAL_MARKETING_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Ensure all database tables exist with comprehensive error handling
	 *
	 * @return void
	 */
	private function ensure_database_tables(): void {
		if (
			! $this->is_context( self::CONTEXT_ADMIN )
			&& ! $this->is_context( self::CONTEXT_CLI )
			&& ! $this->is_context( self::CONTEXT_CRON )
		) {
			return;
		}

		foreach ( self::get_table_definitions_config() as $class => $operations ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			foreach ( $operations as $operation ) {
				$check  = $operation['check'] ?? null;
				$create = $operation['create'] ?? null;

				if ( null === $create || ! method_exists( $class, $create ) ) {
					continue;
				}

				$should_create = true;

				if ( $check && method_exists( $class, $check ) ) {
					$should_create = ! $class::$check();
				}

				if ( ! $should_create ) {
					continue;
				}

								$label = $operation['label'] ?? sprintf(
									'%s::%s()',
									self::get_component_label( $class ),
									$create
								);

				$this->execute_safely(
					static fn () => $class::$create(),
					$label
				);
			}
		}
	}

	/**
	 * Execute a component instance method safely.
	 *
	 * @param string $property Property that holds the instance.
	 * @param string $method   Method name to execute.
	 *
	 * @return void
	 */
	private function invoke_component_method( string $property, string $method ): void {
			$component = $this->get_component_instance( $property );

		if ( ! $component || ! method_exists( $component, $method ) ) {
				return;
		}

			$label = sprintf(
				'%s->%s()',
				self::get_component_label( get_class( $component ) ),
				$method
			);

			$this->execute_safely(
				static fn () => $component->{$method}(),
				$label
			);
	}

	/**
	 * Execute a static class method safely.
	 *
	 * @param string      $class  Fully qualified class name.
	 * @param string      $method Method to execute.
	 * @param string|null $label  Optional label for logging.
	 *
	 * @return void
	 */
	private function invoke_static_method( string $class, string $method, ?string $label = null ): void {
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return;
		}

				$context = $label ?? sprintf(
					'%s::%s()',
					self::get_component_label( $class ),
					$method
				);

				$this->execute_safely(
					static fn () => $class::$method(),
					$context
				);
	}

	/**
	 * Execute a callback while logging unexpected errors.
	 *
	 * @param callable $callback Callback to execute.
	 * @param string   $context  Context label for logging.
	 *
	 * @return void
	 */
	private function execute_safely( callable $callback, string $context ): void {
			self::execute_callback_safely( $callback, $context );
	}

		/**
		 * Execute a callback safely from static contexts.
		 *
		 * @param callable $callback Callback to execute.
		 * @param string   $context  Context label for logging.
		 *
		 * @return void
		 */
	private static function execute_callback_safely( callable $callback, string $context ): void {
		try {
				$callback();
		} catch ( \Throwable $e ) {
				self::log_initialization_error( $context, $e );
		}
	}

	/**
	 * Execute lifecycle callbacks across all relevant sites.
	 *
	 * @param bool     $network_wide Whether the hook was triggered network wide.
	 * @param callable $callback     Callback to run for each site.
	 *
	 * @return void
	 */
	private static function run_for_each_site( bool $network_wide, callable $callback ): void {
		if ( ! self::is_multisite_environment() || ! $network_wide ) {
			$callback();

			return;
		}

		if ( ! function_exists( 'get_sites' ) || ! function_exists( 'switch_to_blog' ) || ! function_exists( 'restore_current_blog' ) ) {
			$callback();

			return;
		}

		$site_ids = get_sites(
			[
				'fields' => 'ids',
			]
		);

		if ( empty( $site_ids ) ) {
			$callback();

			return;
		}

		$site_ids        = array_map( 'intval', $site_ids );
		$original_blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		foreach ( $site_ids as $site_id ) {
			$switched = switch_to_blog( (int) $site_id );

			if ( ! $switched ) {
				continue;
			}

			try {
				self::execute_callback_safely(
					static function () use ( $callback ): void {
						$callback();
					},
					sprintf( 'multisite lifecycle (site #%d)', (int) $site_id )
				);
			} finally {
				restore_current_blog();
			}
		}

		if ( function_exists( 'get_current_blog_id' ) && function_exists( 'switch_to_blog' ) && $original_blog_id && get_current_blog_id() !== $original_blog_id ) {
			switch_to_blog( $original_blog_id );
		}
	}

	/**
	 * Retrieve the stored plugin version with multisite awareness.
	 *
	 * @return string|null
	 */
	private static function get_stored_version(): ?string {
		$stored_version = null;

		if ( function_exists( 'get_option' ) ) {
			$stored_version = get_option( self::VERSION_OPTION, null );
		}

		if ( ( ! is_string( $stored_version ) || '' === $stored_version ) && self::is_multisite_environment() && function_exists( 'get_site_option' ) ) {
			$stored_version = get_site_option( self::VERSION_OPTION, null );
		}

		return is_string( $stored_version ) && '' !== $stored_version ? $stored_version : null;
	}

	/**
	 * Persist the detected plugin version to the appropriate option store.
	 *
	 * @param string $version      Version string to store.
	 * @param bool   $network_wide Whether the operation spans the entire network.
	 *
	 * @return void
	 */
	private static function update_version_storage( string $version, bool $network_wide ): void {
		self::execute_callback_safely(
			static function () use ( $version ): void {
				if ( function_exists( 'update_option' ) ) {
					update_option( self::VERSION_OPTION, $version, false );
				}
			},
			sprintf( 'update_option(%s)', self::VERSION_OPTION )
		);

		if ( $network_wide && self::is_multisite_environment() && function_exists( 'update_site_option' ) ) {
			self::execute_callback_safely(
				static function () use ( $version ): void {
					update_site_option( self::VERSION_OPTION, $version );
				},
				sprintf( 'update_site_option(%s)', self::VERSION_OPTION )
			);
		}
	}

	/**
	 * Determine whether the environment is running in multisite mode.
	 *
	 * @return bool
	 */
	private static function is_multisite_environment(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * Ensure core plugin helpers are loaded before multisite checks run.
	 *
	 * @return void
	 */
	private static function ensure_plugin_functions_loaded(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			return;
		}

		if ( function_exists( 'is_plugin_active_for_network' ) && function_exists( 'plugin_basename' ) ) {
			return;
		}

		$plugin_helper = ABSPATH . 'wp-admin/includes/plugin.php';

		if ( file_exists( $plugin_helper ) ) {
			require_once $plugin_helper;
		}
	}

	/**
	 * Detect whether the plugin is active across the entire network.
	 *
	 * @return bool
	 */
	private static function is_network_active(): bool {
		if ( ! self::is_multisite_environment() ) {
			return false;
		}

		if ( ! defined( 'FP_DIGITAL_MARKETING_PLUGIN_FILE' ) ) {
			return false;
		}

		self::ensure_plugin_functions_loaded();

		if ( function_exists( 'is_plugin_active_for_network' ) && function_exists( 'plugin_basename' ) ) {
			return is_plugin_active_for_network( plugin_basename( FP_DIGITAL_MARKETING_PLUGIN_FILE ) );
		}

		return false;
	}

		/**
		 * Schedule cleanup tasks with error handling
		 *
		 * @return void
		 */
	private function schedule_cleanup_tasks(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
				return;
		}

			// Schedule daily cleanup of export files.
		if ( ! wp_next_scheduled( 'fp_dms_cleanup_exports' ) ) {
				wp_schedule_event( time(), 'daily', 'fp_dms_cleanup_exports' );
		}

			// Hook cleanup actions with error handling.
			add_action(
				'fp_dms_cleanup_exports',
				static function (): void {
					if ( ! class_exists( DataExporter::class ) ) {
						return;
					}

						self::execute_callback_safely(
							static fn () => DataExporter::cleanup_old_exports(),
							'DataExporter::cleanup_old_exports()'
						);
				}
			);
	}

		/**
		 * Tasks executed on plugin activation.
		 *
		 * @return void
		 */
	public static function activate( bool $network_wide = false ): void {
		self::execute_callback_safely(
			static function (): void {
				if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) ) {
					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				}
			},
			'load WordPress upgrade helpers'
		);

		$version = self::get_current_version_string();

		self::run_for_each_site(
			$network_wide,
			static function () use ( $version, $network_wide ): void {
				foreach ( self::get_table_definitions_config() as $class => $operations ) {
					if ( ! class_exists( $class ) ) {
						continue;
					}

					foreach ( $operations as $operation ) {
						$method = $operation['create'] ?? null;

						if ( ! $method || ! method_exists( $class, $method ) ) {
							continue;
						}

						$label = $operation['label'] ?? sprintf(
							'%s::%s()',
							self::get_component_label( $class ),
							$method
						);

						self::execute_callback_safely(
							static fn () => $class::$method(),
							$label
						);
					}
				}

				if ( class_exists( Capabilities::class ) && method_exists( Capabilities::class, 'register_capabilities' ) ) {
					self::execute_callback_safely(
						static fn () => Capabilities::register_capabilities(),
						'Capabilities::register_capabilities()'
					);
				}

				if ( function_exists( 'flush_rewrite_rules' ) ) {
					self::execute_callback_safely(
						static fn () => flush_rewrite_rules(),
						'flush_rewrite_rules()'
					);
				}

				if ( function_exists( 'set_transient' ) ) {
					self::execute_callback_safely(
						static fn () => set_transient( 'fp_dms_activation_redirect', true, 30 ),
						'set_transient(fp_dms_activation_redirect)'
					);
				}

				self::update_version_storage( $version, $network_wide );
			}
		);
	}
	public static function deactivate( bool $network_wide = false ): void {
		self::run_for_each_site(
			$network_wide,
			static function (): void {
				$deactivation_callbacks = [
					Capabilities::class       => 'remove_capabilities',
					SyncEngine::class         => 'unschedule_sync',
					ReportScheduler::class    => 'unschedule_reports',
					PerformanceCache::class   => 'unschedule_cache_warmup',
					SegmentationEngine::class => 'unschedule_full_evaluation',
					EmailNotifications::class => 'unschedule_daily_digest',
				];

				foreach ( $deactivation_callbacks as $class => $method ) {
					if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
						continue;
					}

					self::execute_callback_safely(
						static fn () => $class::$method(),
						sprintf( '%s::%s()', self::get_component_label( $class ), $method )
					);
				}

				if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
					self::execute_callback_safely(
						static fn () => wp_clear_scheduled_hook( 'fp_dms_cleanup_exports' ),
						'wp_clear_scheduled_hook(fp_dms_cleanup_exports)'
					);

					self::execute_callback_safely(
						static fn () => wp_clear_scheduled_hook( 'fp_dms_cleanup_export_file' ),
						'wp_clear_scheduled_hook(fp_dms_cleanup_export_file)'
					);
				}

				if ( function_exists( 'flush_rewrite_rules' ) ) {
					self::execute_callback_safely(
						static fn () => flush_rewrite_rules(),
						'flush_rewrite_rules()'
					);
				}
			}
		);
	}

}

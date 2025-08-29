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
use FP\DigitalMarketing\Admin\Settings;
use FP\DigitalMarketing\Admin\Reports;
use FP\DigitalMarketing\Admin\SecurityAdmin;
use FP\DigitalMarketing\Admin\CachePerformance;
use FP\DigitalMarketing\Database\MetricsCacheTable;
use FP\DigitalMarketing\Helpers\ReportScheduler;
use FP\DigitalMarketing\Helpers\SyncEngine;

/**
 * Main application class
 */
class DigitalMarketingSuite {

	/**
	 * Application version
	 *
	 * @var string
	 */
	private string $version = '1.0.0';

	/**
	 * Cliente Post Type instance
	 *
	 * @var ClientePostType
	 */
	private ClientePostType $cliente_post_type;

	/**
	 * Cliente Meta instance
	 *
	 * @var ClienteMeta
	 */
	private ClienteMeta $cliente_meta;

	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Reports instance
	 *
	 * @var Reports
	 */
	private Reports $reports;

	/**
	 * Security Admin instance
	 *
	 * @var SecurityAdmin
	 */
	private SecurityAdmin $security_admin;

	/**
	 * Cache Performance instance
	 *
	 * @var CachePerformance
	 */
	private CachePerformance $cache_performance;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cliente_post_type = new ClientePostType();
		$this->cliente_meta = new ClienteMeta();
		$this->settings = new Settings();
		$this->reports = new Reports();
		$this->security_admin = new SecurityAdmin();
		$this->cache_performance = new CachePerformance();
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
	 * Initialize the application
	 *
	 * @return void
	 */
	public function init(): void {
		// Load text domain for internationalization.
		$this->load_textdomain();

		// Initialize components.
		$this->cliente_post_type->init();
		$this->cliente_meta->init();
		$this->settings->init();
		$this->reports->init();
		$this->security_admin->init();
		$this->cache_performance->init();

		// Initialize report scheduler.
		ReportScheduler::init();

		// Initialize sync engine.
		SyncEngine::init();

		// Ensure metrics cache table exists (in case of manual activation issues).
		$this->ensure_metrics_cache_table();

		// Hook for extensibility.
		do_action( 'fp_digital_marketing_suite_init' );
	}

	/**
	 * Load plugin text domain for internationalization
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'fp-digital-marketing',
			false,
			dirname( plugin_basename( FP_DIGITAL_MARKETING_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Ensure metrics cache table exists
	 *
	 * @return void
	 */
	private function ensure_metrics_cache_table(): void {
		if ( ! MetricsCacheTable::table_exists() ) {
			MetricsCacheTable::create_table();
		}
	}
}

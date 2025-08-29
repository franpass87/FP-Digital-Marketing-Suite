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
use FP\DigitalMarketing\Database\MetricsCacheTable;
use FP\DigitalMarketing\Database\AlertRulesTable;
use FP\DigitalMarketing\Helpers\ReportScheduler;
use FP\DigitalMarketing\Helpers\SyncEngine;
use FP\DigitalMarketing\Helpers\SeoFrontendOutput;
use FP\DigitalMarketing\Helpers\XmlSitemap;
use FP\DigitalMarketing\Helpers\SchemaGenerator;
use FP\DigitalMarketing\Helpers\Capabilities;

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
	 * SEO Meta instance
	 *
	 * @var SeoMeta
	 */
	private SeoMeta $seo_meta;

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
	 * Dashboard instance
	 *
	 * @var Dashboard
	 */
	private Dashboard $dashboard;

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
	 * Onboarding Wizard instance
	 *
	 * @var OnboardingWizard
	 */
	private OnboardingWizard $onboarding_wizard;

	/**
	 * Alerting Admin instance
	 *
	 * @var AlertingAdmin
	 */
	private AlertingAdmin $alerting_admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cliente_post_type = new ClientePostType();
		$this->cliente_meta = new ClienteMeta();
		$this->seo_meta = new SeoMeta();
		$this->settings = new Settings();
		$this->reports = new Reports();
		$this->dashboard = new Dashboard();
		$this->security_admin = new SecurityAdmin();
		$this->cache_performance = new CachePerformance();
		$this->onboarding_wizard = new OnboardingWizard();
		$this->alerting_admin = new AlertingAdmin();
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
		$this->seo_meta->init();
		$this->settings->init();
		$this->reports->init();
		$this->dashboard->init();
		$this->security_admin->init();
		$this->cache_performance->init();
		$this->onboarding_wizard->init();

		// Initialize capabilities system.
		Capabilities::init();

		// Initialize report scheduler.
		ReportScheduler::init();

		// Initialize sync engine.
		SyncEngine::init();

		// Initialize SEO frontend output.
		SeoFrontendOutput::init();

		// Initialize Schema.org structured data generator.
		SchemaGenerator::init();

		// Initialize XML sitemap.
		XmlSitemap::init();
		XmlSitemap::init_robots_txt();

		// Ensure database tables exist (in case of manual activation issues).
		$this->ensure_metrics_cache_table();
		$this->ensure_alert_rules_table();

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

	/**
	 * Ensure alert rules table exists
	 *
	 * @return void
	 */
	private function ensure_alert_rules_table(): void {
		if ( ! AlertRulesTable::table_exists() ) {
			AlertRulesTable::create_table();
		}
	}
}

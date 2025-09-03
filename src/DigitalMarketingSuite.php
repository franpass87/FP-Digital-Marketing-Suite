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
use FP\DigitalMarketing\Admin\UTMCampaignManager;
use FP\DigitalMarketing\Admin\ConversionEventsAdmin;
use FP\DigitalMarketing\Admin\SegmentationAdmin;
use FP\DigitalMarketing\Database\MetricsCacheTable;
use FP\DigitalMarketing\Database\AlertRulesTable;
use FP\DigitalMarketing\Database\AnomalyRulesTable;
use FP\DigitalMarketing\Database\DetectedAnomaliesTable;
use FP\DigitalMarketing\Database\UTMCampaignsTable;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Database\AudienceSegmentTable;
use FP\DigitalMarketing\Helpers\ReportScheduler;
use FP\DigitalMarketing\Helpers\SyncEngine;
use FP\DigitalMarketing\Helpers\SegmentationEngine;
use FP\DigitalMarketing\API\SegmentationAPI;
use FP\DigitalMarketing\Helpers\SeoFrontendOutput;
use FP\DigitalMarketing\Helpers\XmlSitemap;
use FP\DigitalMarketing\Helpers\SchemaGenerator;
use FP\DigitalMarketing\Helpers\FAQBlock;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\DashboardWidgets;

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
	 * Anomaly Detection Admin instance
	 *
	 * @var AnomalyDetectionAdmin
	 */
	private AnomalyDetectionAdmin $anomaly_detection_admin;

	/**
	 * UTM Campaign Manager instance
	 *
	 * @var UTMCampaignManager
	 */
	private UTMCampaignManager $utm_campaign_manager;

	/**
	 * Conversion Events Admin instance
	 *
	 * @var ConversionEventsAdmin
	 */
	private ConversionEventsAdmin $conversion_events_admin;

	/**
	 * Segmentation Admin instance
	 *
	 * @var SegmentationAdmin
	 */
	private SegmentationAdmin $segmentation_admin;

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
		$this->anomaly_detection_admin = new AnomalyDetectionAdmin();
		$this->utm_campaign_manager = new UTMCampaignManager();
		$this->conversion_events_admin = new ConversionEventsAdmin();
		$this->segmentation_admin = new SegmentationAdmin();
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
		$this->alerting_admin->init();
		$this->anomaly_detection_admin->init();
		$this->utm_campaign_manager->init();
		$this->conversion_events_admin->init();
		$this->segmentation_admin->init();

		// Initialize capabilities system.
		Capabilities::init();

		// Initialize report scheduler.
		ReportScheduler::init();

		// Initialize sync engine.
		SyncEngine::init();

		// Initialize segmentation engine.
		SegmentationEngine::init();

		// Initialize segmentation API.
		SegmentationAPI::init();

		// Initialize SEO frontend output.
		SeoFrontendOutput::init();

		// Initialize Schema.org structured data generator.
		SchemaGenerator::init();

		// Initialize FAQ block for Gutenberg.
		FAQBlock::init();

		// Initialize XML sitemap.
		XmlSitemap::init();
		XmlSitemap::init_robots_txt();

		// Initialize dashboard widgets.
		DashboardWidgets::init();

		// Ensure database tables exist (in case of manual activation issues).
		$this->ensure_metrics_cache_table();
		$this->ensure_alert_rules_table();
		$this->ensure_anomaly_rules_table();
		$this->ensure_detected_anomalies_table();
		$this->ensure_utm_campaigns_table();
		$this->ensure_conversion_events_table();
		$this->ensure_audience_segment_tables();

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

	/**
	 * Ensure anomaly rules table exists
	 *
	 * @return void
	 */
	private function ensure_anomaly_rules_table(): void {
		if ( ! AnomalyRulesTable::table_exists() ) {
			AnomalyRulesTable::create_table();
		}
	}

	/**
	 * Ensure detected anomalies table exists
	 *
	 * @return void
	 */
	private function ensure_detected_anomalies_table(): void {
		if ( ! DetectedAnomaliesTable::table_exists() ) {
			DetectedAnomaliesTable::create_table();
		}
	}

	/**
	 * Ensure UTM campaigns table exists
	 *
	 * @return void
	 */
	private function ensure_utm_campaigns_table(): void {
		if ( ! UTMCampaignsTable::table_exists() ) {
			UTMCampaignsTable::create_table();
		}
	}

	/**
	 * Ensure conversion events table exists
	 *
	 * @return void
	 */
	private function ensure_conversion_events_table(): void {
		if ( ! ConversionEventsTable::table_exists() ) {
			ConversionEventsTable::create_table();
		}
	}

	/**
	 * Ensure audience segment tables exist
	 *
	 * @return void
	 */
	private function ensure_audience_segment_tables(): void {
		if ( ! AudienceSegmentTable::segments_table_exists() ) {
			AudienceSegmentTable::create_segments_table();
		}
		
		if ( ! AudienceSegmentTable::membership_table_exists() ) {
			AudienceSegmentTable::create_membership_table();
		}
	}
}

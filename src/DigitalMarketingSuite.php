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
use FP\DigitalMarketing\Helpers\DataExporter;
use FP\DigitalMarketing\Helpers\EmailNotifications;

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
	 * @var ClientePostType|null
	 */
	private ?ClientePostType $cliente_post_type = null;

	/**
	 * Cliente Meta instance
	 *
	 * @var ClienteMeta|null
	 */
	private ?ClienteMeta $cliente_meta = null;

	/**
	 * SEO Meta instance
	 *
	 * @var SeoMeta|null
	 */
	private ?SeoMeta $seo_meta = null;

	/**
	 * Settings instance
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Reports instance
	 *
	 * @var Reports|null
	 */
	private ?Reports $reports = null;

	/**
	 * Dashboard instance
	 *
	 * @var Dashboard|null
	 */
	private ?Dashboard $dashboard = null;

	/**
	 * Security Admin instance
	 *
	 * @var SecurityAdmin|null
	 */
	private ?SecurityAdmin $security_admin = null;

	/**
	 * Cache Performance instance
	 *
	 * @var CachePerformance|null
	 */
	private ?CachePerformance $cache_performance = null;

	/**
	 * Onboarding Wizard instance
	 *
	 * @var OnboardingWizard|null
	 */
	private ?OnboardingWizard $onboarding_wizard = null;

	/**
	 * Alerting Admin instance
	 *
	 * @var AlertingAdmin|null
	 */
	private ?AlertingAdmin $alerting_admin = null;

	/**
	 * Anomaly Detection Admin instance
	 *
	 * @var AnomalyDetectionAdmin|null
	 */
	private ?AnomalyDetectionAdmin $anomaly_detection_admin = null;

	/**
	 * UTM Campaign Manager instance
	 *
	 * @var UTMCampaignManager|null
	 */
	private ?UTMCampaignManager $utm_campaign_manager = null;

	/**
	 * Conversion Events Admin instance
	 *
	 * @var ConversionEventsAdmin|null
	 */
	private ?ConversionEventsAdmin $conversion_events_admin = null;

	/**
	 * Segmentation Admin instance
	 *
	 * @var SegmentationAdmin|null
	 */
	private ?SegmentationAdmin $segmentation_admin = null;

	/**
	 * Constructor with error handling
	 */
	public function __construct() {
		// Initialize components with error handling to prevent WSOD
		try {
			$this->cliente_post_type = new ClientePostType();
		} catch ( \Throwable $e ) {
			$this->cliente_post_type = null;
			$this->log_initialization_error( 'ClientePostType', $e );
		}

		try {
			$this->cliente_meta = new ClienteMeta();
		} catch ( \Throwable $e ) {
			$this->cliente_meta = null;
			$this->log_initialization_error( 'ClienteMeta', $e );
		}

		try {
			$this->seo_meta = new SeoMeta();
		} catch ( \Throwable $e ) {
			$this->seo_meta = null;
			$this->log_initialization_error( 'SeoMeta', $e );
		}

		try {
			$this->settings = new Settings();
		} catch ( \Throwable $e ) {
			$this->settings = null;
			$this->log_initialization_error( 'Settings', $e );
		}

		try {
			$this->reports = new Reports();
		} catch ( \Throwable $e ) {
			$this->reports = null;
			$this->log_initialization_error( 'Reports', $e );
		}

		try {
			$this->dashboard = new Dashboard();
		} catch ( \Throwable $e ) {
			$this->dashboard = null;
			$this->log_initialization_error( 'Dashboard', $e );
		}

		try {
			$this->security_admin = new SecurityAdmin();
		} catch ( \Throwable $e ) {
			$this->security_admin = null;
			$this->log_initialization_error( 'SecurityAdmin', $e );
		}

		try {
			$this->cache_performance = new CachePerformance();
		} catch ( \Throwable $e ) {
			$this->cache_performance = null;
			$this->log_initialization_error( 'CachePerformance', $e );
		}

		try {
			$this->onboarding_wizard = new OnboardingWizard();
		} catch ( \Throwable $e ) {
			$this->onboarding_wizard = null;
			$this->log_initialization_error( 'OnboardingWizard', $e );
		}

		try {
			$this->alerting_admin = new AlertingAdmin();
		} catch ( \Throwable $e ) {
			$this->alerting_admin = null;
			$this->log_initialization_error( 'AlertingAdmin', $e );
		}

		try {
			$this->anomaly_detection_admin = new AnomalyDetectionAdmin();
		} catch ( \Throwable $e ) {
			$this->anomaly_detection_admin = null;
			$this->log_initialization_error( 'AnomalyDetectionAdmin', $e );
		}

		try {
			$this->utm_campaign_manager = new UTMCampaignManager();
		} catch ( \Throwable $e ) {
			$this->utm_campaign_manager = null;
			$this->log_initialization_error( 'UTMCampaignManager', $e );
		}

		try {
			$this->conversion_events_admin = new ConversionEventsAdmin();
		} catch ( \Throwable $e ) {
			$this->conversion_events_admin = null;
			$this->log_initialization_error( 'ConversionEventsAdmin', $e );
		}

		try {
			$this->segmentation_admin = new SegmentationAdmin();
		} catch ( \Throwable $e ) {
			$this->segmentation_admin = null;
			$this->log_initialization_error( 'SegmentationAdmin', $e );
		}
	}

	/**
	 * Log initialization errors
	 *
	 * @param string $component Component name
	 * @param \Throwable $error Error object
	 * @return void
	 */
	private function log_initialization_error( string $component, \Throwable $error ): void {
		if ( function_exists( 'error_log' ) ) {
			error_log( sprintf(
				'FP Digital Marketing: Failed to initialize %s - %s in %s:%d',
				$component,
				$error->getMessage(),
				$error->getFile(),
				$error->getLine()
			) );
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
	 * Initialize the application with error handling
	 *
	 * @return void
	 */
	public function init(): void {
		// Load text domain for internationalization.
		$this->load_textdomain();

		// Initialize components safely - check for null objects
		try {
			if ( $this->cliente_post_type !== null ) {
				$this->cliente_post_type->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'ClientePostType->init()', $e );
		}

		try {
			if ( $this->cliente_meta !== null ) {
				$this->cliente_meta->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'ClienteMeta->init()', $e );
		}

		try {
			if ( $this->seo_meta !== null ) {
				$this->seo_meta->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SeoMeta->init()', $e );
		}

		try {
			if ( $this->settings !== null ) {
				$this->settings->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'Settings->init()', $e );
		}

		try {
			if ( $this->reports !== null ) {
				$this->reports->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'Reports->init()', $e );
		}

		try {
			if ( $this->dashboard !== null ) {
				$this->dashboard->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'Dashboard->init()', $e );
		}

		try {
			if ( $this->security_admin !== null ) {
				$this->security_admin->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SecurityAdmin->init()', $e );
		}

		try {
			if ( $this->cache_performance !== null ) {
				$this->cache_performance->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'CachePerformance->init()', $e );
		}

		try {
			if ( $this->onboarding_wizard !== null ) {
				$this->onboarding_wizard->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'OnboardingWizard->init()', $e );
		}

		try {
			if ( $this->alerting_admin !== null ) {
				$this->alerting_admin->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'AlertingAdmin->init()', $e );
		}

		try {
			if ( $this->anomaly_detection_admin !== null ) {
				$this->anomaly_detection_admin->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'AnomalyDetectionAdmin->init()', $e );
		}

		try {
			if ( $this->utm_campaign_manager !== null ) {
				$this->utm_campaign_manager->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'UTMCampaignManager->init()', $e );
		}

		try {
			if ( $this->conversion_events_admin !== null ) {
				$this->conversion_events_admin->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'ConversionEventsAdmin->init()', $e );
		}

		try {
			if ( $this->segmentation_admin !== null ) {
				$this->segmentation_admin->init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SegmentationAdmin->init()', $e );
		}

		// Initialize static helper classes with error handling
		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\Capabilities' ) ) {
				Capabilities::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'Capabilities::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\ReportScheduler' ) ) {
				ReportScheduler::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'ReportScheduler::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\SyncEngine' ) ) {
				SyncEngine::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SyncEngine::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\SegmentationEngine' ) ) {
				SegmentationEngine::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SegmentationEngine::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\API\SegmentationAPI' ) ) {
				SegmentationAPI::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SegmentationAPI::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\SeoFrontendOutput' ) ) {
				SeoFrontendOutput::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SeoFrontendOutput::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\SchemaGenerator' ) ) {
				SchemaGenerator::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'SchemaGenerator::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\FAQBlock' ) ) {
				FAQBlock::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'FAQBlock::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\XmlSitemap' ) ) {
				XmlSitemap::init();
				XmlSitemap::init_robots_txt();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'XmlSitemap::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\DashboardWidgets' ) ) {
				DashboardWidgets::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'DashboardWidgets::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\DataExporter' ) ) {
				DataExporter::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'DataExporter::init()', $e );
		}

		try {
			if ( class_exists( '\FP\DigitalMarketing\Helpers\EmailNotifications' ) ) {
				EmailNotifications::init();
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'EmailNotifications::init()', $e );
		}

		// Schedule cleanup tasks with error handling
		try {
			$this->schedule_cleanup_tasks();
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'schedule_cleanup_tasks()', $e );
		}

		// Ensure database tables exist with comprehensive error handling
		$this->ensure_database_tables();

		// Hook for extensibility with error handling
		try {
			do_action( 'fp_digital_marketing_suite_init' );
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'fp_digital_marketing_suite_init action', $e );
		}
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
	 * Ensure all database tables exist with comprehensive error handling
	 *
	 * @return void
	 */
	private function ensure_database_tables(): void {
		$tables = [
			'MetricsCacheTable' => '\FP\DigitalMarketing\Database\MetricsCacheTable',
			'AlertRulesTable' => '\FP\DigitalMarketing\Database\AlertRulesTable',
			'AnomalyRulesTable' => '\FP\DigitalMarketing\Database\AnomalyRulesTable',
			'DetectedAnomaliesTable' => '\FP\DigitalMarketing\Database\DetectedAnomaliesTable',
			'UTMCampaignsTable' => '\FP\DigitalMarketing\Database\UTMCampaignsTable',
			'ConversionEventsTable' => '\FP\DigitalMarketing\Database\ConversionEventsTable',
			'AudienceSegmentTable' => '\FP\DigitalMarketing\Database\AudienceSegmentTable'
		];

		foreach ( $tables as $name => $class ) {
			try {
				if ( class_exists( $class ) ) {
					if ( $name === 'AudienceSegmentTable' ) {
						// Special handling for AudienceSegmentTable which has multiple tables
						if ( method_exists( $class, 'segments_table_exists' ) && ! $class::segments_table_exists() ) {
							$class::create_segments_table();
						}
						if ( method_exists( $class, 'membership_table_exists' ) && ! $class::membership_table_exists() ) {
							$class::create_membership_table();
						}
					} else {
						// Standard table creation
						if ( method_exists( $class, 'table_exists' ) && ! $class::table_exists() ) {
							$class::create_table();
						} elseif ( method_exists( $class, 'create_table' ) ) {
							// Fallback if table_exists doesn't exist
							$class::create_table();
						}
					}
				}
			} catch ( \Throwable $e ) {
				$this->log_initialization_error( "Database table creation for {$name}", $e );
			}
		}
	}

	/**
	 * Ensure metrics cache table exists
	 *
	 * @return void
	 */
	private function ensure_metrics_cache_table(): void {
		try {
			if ( class_exists( '\FP\DigitalMarketing\Database\MetricsCacheTable' ) ) {
				if ( ! MetricsCacheTable::table_exists() ) {
					MetricsCacheTable::create_table();
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'MetricsCacheTable creation', $e );
		}
	}

	/**
	 * Ensure alert rules table exists
	 *
	 * @return void
	 */
	private function ensure_alert_rules_table(): void {
		try {
			if ( class_exists( '\FP\DigitalMarketing\Database\AlertRulesTable' ) ) {
				if ( ! AlertRulesTable::table_exists() ) {
					AlertRulesTable::create_table();
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'AlertRulesTable creation', $e );
		}
	}

	/**
	 * Ensure anomaly rules table exists
	 *
	 * @return void
	 */
	private function ensure_anomaly_rules_table(): void {
		try {
			if ( class_exists( '\FP\DigitalMarketing\Database\AnomalyRulesTable' ) ) {
				if ( ! AnomalyRulesTable::table_exists() ) {
					AnomalyRulesTable::create_table();
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'AnomalyRulesTable creation', $e );
		}
	}

	/**
	 * Ensure detected anomalies table exists
	 *
	 * @return void
	 */
	private function ensure_detected_anomalies_table(): void {
		try {
			if ( class_exists( '\FP\DigitalMarketing\Database\DetectedAnomaliesTable' ) ) {
				if ( ! DetectedAnomaliesTable::table_exists() ) {
					DetectedAnomaliesTable::create_table();
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'DetectedAnomaliesTable creation', $e );
		}
	}

	/**
	 * Ensure UTM campaigns table exists
	 *
	 * @return void
	 */
	private function ensure_utm_campaigns_table(): void {
		try {
			if ( class_exists( '\FP\DigitalMarketing\Database\UTMCampaignsTable' ) ) {
				if ( ! UTMCampaignsTable::table_exists() ) {
					UTMCampaignsTable::create_table();
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'UTMCampaignsTable creation', $e );
		}
	}

	/**
	 * Ensure conversion events table exists
	 *
	 * @return void
	 */
	private function ensure_conversion_events_table(): void {
		try {
			if ( class_exists( '\FP\DigitalMarketing\Database\ConversionEventsTable' ) ) {
				if ( ! ConversionEventsTable::table_exists() ) {
					ConversionEventsTable::create_table();
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'ConversionEventsTable creation', $e );
		}
	}

	/**
	 * Ensure audience segment tables exist
	 *
	 * @return void
	 */
	private function ensure_audience_segment_tables(): void {
		try {
			if ( class_exists( '\FP\DigitalMarketing\Database\AudienceSegmentTable' ) ) {
				if ( ! AudienceSegmentTable::segments_table_exists() ) {
					AudienceSegmentTable::create_segments_table();
				}
				
				if ( ! AudienceSegmentTable::membership_table_exists() ) {
					AudienceSegmentTable::create_membership_table();
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'AudienceSegmentTable creation', $e );
		}
	}

	/**
	 * Schedule cleanup tasks with error handling
	 *
	 * @return void
	 */
	private function schedule_cleanup_tasks(): void {
		try {
			// Schedule daily cleanup of export files
			if ( ! wp_next_scheduled( 'fp_dms_cleanup_exports' ) ) {
				wp_schedule_event( time(), 'daily', 'fp_dms_cleanup_exports' );
			}

			// Hook cleanup actions with error handling
			add_action( 'fp_dms_cleanup_exports', function() {
				try {
					if ( class_exists( '\FP\DigitalMarketing\Helpers\DataExporter' ) ) {
						DataExporter::cleanup_old_exports();
					}
				} catch ( \Throwable $e ) {
					if ( function_exists( 'error_log' ) ) {
						error_log( 'FP Digital Marketing: Cleanup error - ' . $e->getMessage() );
					}
				}
			} );
		} catch ( \Throwable $e ) {
			$this->log_initialization_error( 'schedule_cleanup_tasks', $e );
		}
	}
}

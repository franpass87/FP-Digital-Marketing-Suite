<?php
/**
 * Microsoft Clarity Integration Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\MicrosoftClarity;
use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Helpers\DataSources;

/**
 * Integration test class for Microsoft Clarity
 */
class MicrosoftClarityIntegrationTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress functions if needed
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				return $default;
			}
		}

		if ( ! function_exists( 'update_option' ) ) {
			function update_option( $option, $value ) {
				return true;
			}
		}

		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = 'default' ) {
				return $text;
			}
		}

		if ( ! function_exists( 'esc_js' ) ) {
			function esc_js( $text ) {
				return addslashes( $text );
			}
		}

		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', false );
		}
	}

	/**
	 * Test integration with DataSources helper
	 */
	public function test_data_sources_integration() {
		$data_sources = DataSources::get_data_sources();

		$this->assertArrayHasKey( 'microsoft_clarity', $data_sources );

		$clarity_config = $data_sources['microsoft_clarity'];

		$this->assertEquals( 'microsoft_clarity', $clarity_config['id'] );
		$this->assertEquals( 'available', $clarity_config['status'] );
		$this->assertEquals( DataSources::TYPE_ANALYTICS, $clarity_config['type'] );

		// Test required credentials
		$this->assertArrayHasKey( 'required_credentials', $clarity_config );
		$this->assertContains( 'project_id', $clarity_config['required_credentials'] );

		// Test capabilities
		$this->assertArrayHasKey( 'capabilities', $clarity_config );
		$this->assertContains( 'user_behavior', $clarity_config['capabilities'] );
		$this->assertContains( 'session_recordings', $clarity_config['capabilities'] );
		$this->assertContains( 'heatmaps', $clarity_config['capabilities'] );

		// Test endpoints
		$this->assertArrayHasKey( 'endpoints', $clarity_config );
		$this->assertArrayHasKey( 'api', $clarity_config['endpoints'] );
	}

	/**
	 * Test data source availability check
	 */
	public function test_data_source_availability() {
		$is_available = DataSources::is_data_source_available( 'microsoft_clarity' );
		$this->assertTrue( $is_available );
	}

	/**
	 * Test getting specific data source configuration
	 */
	public function test_get_specific_data_source() {
		$clarity_config = DataSources::get_data_source( 'microsoft_clarity' );

		$this->assertIsArray( $clarity_config );
		$this->assertEquals( 'microsoft_clarity', $clarity_config['id'] );
	}

	/**
	 * Test metrics cache integration
	 */
	public function test_metrics_cache_integration() {
		// Skip this test if MetricsCache class is not available
		if ( ! class_exists( 'FP\DigitalMarketing\Models\MetricsCache' ) ) {
			$this->markTestSkipped( 'MetricsCache class not available' );
		}

		$project_id = 'testintegration123';
		$clarity    = new MicrosoftClarity( $project_id );

		$client_id  = 1;
		$start_date = '2024-01-01';
		$end_date   = '2024-01-07';

		// Fetch metrics (which should store in cache)
		$metrics = $clarity->fetch_metrics( $client_id, $start_date, $end_date );

		$this->assertIsArray( $metrics );
		$this->assertNotEmpty( $metrics );

		// Verify the metrics were processed correctly
		$this->assertGreaterThan( 0, $metrics['sessions'] );
		$this->assertGreaterThan( 0, $metrics['page_views'] );
	}

	/**
	 * Test filtering by analytics type
	 */
	public function test_filter_by_analytics_type() {
		$analytics_sources = DataSources::get_data_sources( DataSources::TYPE_ANALYTICS );

		$this->assertArrayHasKey( 'microsoft_clarity', $analytics_sources );
		$this->assertEquals( DataSources::TYPE_ANALYTICS, $analytics_sources['microsoft_clarity']['type'] );
	}

	/**
	 * Test that Microsoft Clarity is included in available sources
	 */
	public function test_included_in_available_sources() {
		$available_sources = DataSources::get_data_sources_by_status( 'available' );

		$this->assertArrayHasKey( 'microsoft_clarity', $available_sources );
	}

	/**
	 * Test metrics normalization structure
	 */
	public function test_metrics_normalization_structure() {
		$project_id = 'testnormalization123';
		$clarity    = new MicrosoftClarity( $project_id );

		$client_id  = 1;
		$start_date = '2024-01-01';
		$end_date   = '2024-01-01';

		$metrics = $clarity->fetch_metrics( $client_id, $start_date, $end_date );

		$this->assertIsArray( $metrics );

		// Test that all expected Clarity-specific metrics are present
		$expected_metrics = [
			'sessions',
			'page_views',
			'recordings_available',
			'heatmaps_generated',
			'rage_clicks',
			'dead_clicks',
			'scroll_depth_avg',
			'time_to_click_avg',
			'javascript_errors',
		];

		foreach ( $expected_metrics as $metric ) {
			$this->assertArrayHasKey( $metric, $metrics, "Missing metric: $metric" );
		}
	}

	/**
	 * Test JavaScript tracking script injection
	 */
	public function test_tracking_script_injection() {
		$project_id = 'trackingtest123';
		$clarity    = new MicrosoftClarity( $project_id );

		$script = $clarity->get_tracking_script();

		// Test that script contains proper Microsoft Clarity structure
		$this->assertStringContainsString( 'function(c,l,a,r,i,t,y)', $script );
		$this->assertStringContainsString( 'clarity.ms/tag/', $script );
		$this->assertStringContainsString( $project_id, $script );
		$this->assertStringContainsString( 'window, document, "clarity", "script"', $script );

		// Test proper escaping
		$this->assertStringNotContainsString( '<', $project_id );
		$this->assertStringNotContainsString( '>', $project_id );
	}

	/**
	 * Test error handling with invalid project ID
	 */
	public function test_error_handling_invalid_project_id() {
		$clarity = new MicrosoftClarity( '' );

		$client_id  = 1;
		$start_date = '2024-01-01';
		$end_date   = '2024-01-07';

		$metrics = $clarity->fetch_metrics( $client_id, $start_date, $end_date );

		$this->assertFalse( $metrics );
	}

	/**
	 * Test project status integration
	 */
	public function test_project_status_integration() {
		// Test with valid project ID
		$clarity_connected = new MicrosoftClarity( 'validproject123' );
		$status_connected  = $clarity_connected->get_project_status();

		$this->assertTrue( $status_connected['connected'] );
		$this->assertEquals( 'connected', $status_connected['class'] );

		// Test without project ID
		$clarity_disconnected = new MicrosoftClarity( '' );
		$status_disconnected  = $clarity_disconnected->get_project_status();

		$this->assertFalse( $status_disconnected['connected'] );
		$this->assertEquals( 'disconnected', $status_disconnected['class'] );
	}

	/**
	 * Test data source type constants
	 */
	public function test_data_source_type_constants() {
		$types = DataSources::get_data_source_types();

		$this->assertArrayHasKey( DataSources::TYPE_ANALYTICS, $types );
		$this->assertIsString( $types[ DataSources::TYPE_ANALYTICS ] );
	}

	/**
	 * Test metrics with different date ranges
	 */
	public function test_metrics_with_different_date_ranges() {
		$project_id = 'daterangetest123';
		$clarity    = new MicrosoftClarity( $project_id );

		$client_id = 1;

		// Test single day
		$metrics_single = $clarity->fetch_metrics( $client_id, '2024-01-01', '2024-01-01' );
		$this->assertEquals( 1, $metrics_single['period']['days'] );

		// Test week
		$metrics_week = $clarity->fetch_metrics( $client_id, '2024-01-01', '2024-01-07' );
		$this->assertEquals( 7, $metrics_week['period']['days'] );

		// Test month
		$metrics_month = $clarity->fetch_metrics( $client_id, '2024-01-01', '2024-01-31' );
		$this->assertEquals( 31, $metrics_month['period']['days'] );

		// Verify metrics scale with period length
		$this->assertGreaterThan( $metrics_single['sessions'], $metrics_week['sessions'] );
		$this->assertGreaterThan( $metrics_week['sessions'], $metrics_month['sessions'] );
	}

	/**
	 * Test source ID consistency
	 */
	public function test_source_id_consistency() {
		$data_source_config = DataSources::get_data_source( 'microsoft_clarity' );

		$this->assertEquals( MicrosoftClarity::SOURCE_ID, $data_source_config['id'] );
		$this->assertEquals( 'microsoft_clarity', MicrosoftClarity::SOURCE_ID );
	}
}

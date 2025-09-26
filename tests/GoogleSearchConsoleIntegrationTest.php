<?php
/**
 * Integration tests for GoogleSearchConsole
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\GoogleSearchConsole;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\DataSources;

/**
 * Test class for GoogleSearchConsole integration
 */
class GoogleSearchConsoleIntegrationTest extends TestCase {

	/**
	 * Test GSC is registered in DataSources
	 */
	public function test_gsc_registered_in_data_sources(): void {
		$data_sources = DataSources::get_data_sources();

		$this->assertArrayHasKey( 'google_search_console', $data_sources );

		$gsc_config = $data_sources['google_search_console'];
		$this->assertEquals( 'available', $gsc_config['status'] );
		$this->assertEquals( 'search', $gsc_config['type'] );
		$this->assertArrayHasKey( 'capabilities', $gsc_config );
		$this->assertContains( 'search_performance', $gsc_config['capabilities'] );
	}

	/**
	 * Test GSC metrics mapping in schema
	 */
	public function test_gsc_metrics_mapping(): void {
		$mappings = MetricsSchema::get_source_mappings();

		$this->assertArrayHasKey( 'google_search_console', $mappings );

		$gsc_mappings = $mappings['google_search_console'];
		$this->assertArrayHasKey( 'clicks', $gsc_mappings );
		$this->assertArrayHasKey( 'impressions', $gsc_mappings );
		$this->assertArrayHasKey( 'ctr', $gsc_mappings );
		$this->assertArrayHasKey( 'position', $gsc_mappings );

		// Test the mappings point to correct KPIs
		$this->assertEquals( MetricsSchema::KPI_ORGANIC_CLICKS, $gsc_mappings['clicks'] );
		$this->assertEquals( MetricsSchema::KPI_ORGANIC_IMPRESSIONS, $gsc_mappings['impressions'] );
		$this->assertEquals( MetricsSchema::KPI_CTR, $gsc_mappings['ctr'] );
		$this->assertEquals( MetricsSchema::KPI_AVG_POSITION, $gsc_mappings['position'] );
	}

	/**
	 * Test avg_position KPI definition
	 */
	public function test_avg_position_kpi_definition(): void {
		$kpi_definitions = MetricsSchema::get_kpi_definitions();

		$this->assertArrayHasKey( MetricsSchema::KPI_AVG_POSITION, $kpi_definitions );

		$avg_position_def = $kpi_definitions[ MetricsSchema::KPI_AVG_POSITION ];
		$this->assertEquals( 'search', $avg_position_def['category'] );
		$this->assertEquals( 'decimal', $avg_position_def['format'] );
		$this->assertEquals( 'avg', $avg_position_def['aggregation'] );
	}

	/**
	 * Test GSC normalization
	 */
	public function test_gsc_metric_normalization(): void {
		// Test click normalization
		$normalized = MetricsSchema::normalize_metric_name( 'google_search_console', 'clicks' );
		$this->assertEquals( MetricsSchema::KPI_ORGANIC_CLICKS, $normalized );

		// Test impressions normalization
		$normalized = MetricsSchema::normalize_metric_name( 'google_search_console', 'impressions' );
		$this->assertEquals( MetricsSchema::KPI_ORGANIC_IMPRESSIONS, $normalized );

		// Test CTR normalization
		$normalized = MetricsSchema::normalize_metric_name( 'google_search_console', 'ctr' );
		$this->assertEquals( MetricsSchema::KPI_CTR, $normalized );

		// Test position normalization
		$normalized = MetricsSchema::normalize_metric_name( 'google_search_console', 'position' );
		$this->assertEquals( MetricsSchema::KPI_AVG_POSITION, $normalized );
	}

	/**
	 * Test GSC with mock data integration
	 */
	public function test_gsc_mock_data_integration(): void {
		$gsc = new GoogleSearchConsole( 'https://example.com/' );

		// Test that properties method returns array (even when not connected)
		$properties = $gsc->get_properties();
		$this->assertIsArray( $properties );

		// Test validation method
		$valid = $gsc->validate_property( 'https://example.com/' );
		$this->assertIsBool( $valid );

		// Test site URL handling
		$this->assertEquals( 'https://example.com/', $gsc->get_site_url() );

		$gsc->set_site_url( 'sc-domain:example.com' );
		$this->assertEquals( 'sc-domain:example.com', $gsc->get_site_url() );
	}

	/**
	 * Test search-specific data sources filtering
	 */
	public function test_search_data_sources_filtering(): void {
		$search_sources = DataSources::get_data_sources( 'search' );

		$this->assertArrayHasKey( 'google_search_console', $search_sources );

		// Make sure only search sources are returned
		foreach ( $search_sources as $source ) {
			$this->assertEquals( 'search', $source['type'] );
		}
	}

	/**
	 * Test GSC source ID constant
	 */
	public function test_gsc_source_id_constant(): void {
		$this->assertEquals( 'google_search_console', GoogleSearchConsole::SOURCE_ID );

		// Verify it matches the DataSources registry
		$data_sources = DataSources::get_data_sources();
		$this->assertArrayHasKey( GoogleSearchConsole::SOURCE_ID, $data_sources );
	}
}

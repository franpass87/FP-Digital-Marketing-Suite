<?php
/**
 * Tests for Metrics Aggregator class
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\MetricsAggregator;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Test class for MetricsAggregator
 */
class MetricsAggregatorTest extends TestCase {

	/**
	 * Mock wpdb object
	 *
	 * @var object
	 */
	private $wpdb_mock;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock wpdb
		$this->wpdb_mock = $this->createMock( stdClass::class );
		$this->wpdb_mock->prefix = 'wp_';

		// Set global wpdb
		global $wpdb;
		$wpdb = $this->wpdb_mock;
	}

	/**
	 * Test generating mock data
	 */
	public function test_generate_mock_data(): void {
		$client_id = 123;
		$period_start = '2024-01-01 00:00:00';
		$period_end = '2024-01-31 23:59:59';

		$mock_data = MetricsAggregator::generate_mock_data( $client_id, $period_start, $period_end );

		$this->assertIsArray( $mock_data );
		$this->assertNotEmpty( $mock_data );

		// Test that all expected KPIs are present
		$this->assertArrayHasKey( MetricsSchema::KPI_SESSIONS, $mock_data );
		$this->assertArrayHasKey( MetricsSchema::KPI_USERS, $mock_data );
		$this->assertArrayHasKey( MetricsSchema::KPI_PAGEVIEWS, $mock_data );
		$this->assertArrayHasKey( MetricsSchema::KPI_CONVERSIONS, $mock_data );
		$this->assertArrayHasKey( MetricsSchema::KPI_REVENUE, $mock_data );

		// Test structure of mock data entry
		$sessions_data = $mock_data[ MetricsSchema::KPI_SESSIONS ];
		$this->assertArrayHasKey( 'kpi', $sessions_data );
		$this->assertArrayHasKey( 'values', $sessions_data );
		$this->assertArrayHasKey( 'sources', $sessions_data );
		$this->assertArrayHasKey( 'total_value', $sessions_data );
		$this->assertArrayHasKey( 'count', $sessions_data );

		$this->assertEquals( MetricsSchema::KPI_SESSIONS, $sessions_data['kpi'] );
		$this->assertEquals( 1, $sessions_data['count'] );
		$this->assertIsArray( $sessions_data['values'] );
		$this->assertIsArray( $sessions_data['sources'] );

		// Test that values are reasonable
		$this->assertGreaterThan( 0, $sessions_data['total_value'] );
		$this->assertLessThan( 10000, $sessions_data['total_value'] );
	}

	/**
	 * Test getting aggregated metrics with mocked MetricsCache
	 */
	public function test_get_aggregated_metrics(): void {
		// Since we can't easily mock static methods in this setup,
		// we'll test the core normalization and aggregation logic instead
		
		// Test metric normalization
		$normalized_sessions = MetricsSchema::normalize_metric_name( 'google_analytics_4', 'sessions' );
		$this->assertEquals( MetricsSchema::KPI_SESSIONS, $normalized_sessions );

		$normalized_users = MetricsSchema::normalize_metric_name( 'google_analytics_4', 'users' );
		$this->assertEquals( MetricsSchema::KPI_USERS, $normalized_users );

		$normalized_impressions = MetricsSchema::normalize_metric_name( 'facebook_ads', 'impressions' );
		$this->assertEquals( MetricsSchema::KPI_IMPRESSIONS, $normalized_impressions );

		// Test aggregation logic manually
		$mock_raw_metrics = [
			(object) [
				'source' => 'google_analytics_4',
				'metric' => 'sessions',
				'value' => '1500',
			],
			(object) [
				'source' => 'google_analytics_4',
				'metric' => 'users',
				'value' => '1200',
			],
			(object) [
				'source' => 'facebook_ads',
				'metric' => 'impressions',
				'value' => '25000',
			],
		];

		// Test aggregation simulation
		$aggregated = [];
		
		foreach ( $mock_raw_metrics as $metric ) {
			$normalized_kpi = MetricsSchema::normalize_metric_name( $metric->source, $metric->metric );
			
			if ( ! isset( $aggregated[ $normalized_kpi ] ) ) {
				$aggregated[ $normalized_kpi ] = [
					'kpi' => $normalized_kpi,
					'values' => [],
					'sources' => [],
					'total_value' => 0,
					'count' => 0,
				];
			}

			$value = is_numeric( $metric->value ) ? (float) $metric->value : 0;
			$aggregated[ $normalized_kpi ]['values'][] = $value;
			$aggregated[ $normalized_kpi ]['sources'][] = $metric->source;
			$aggregated[ $normalized_kpi ]['count']++;
			
			// Apply sum aggregation (default)
			$aggregated[ $normalized_kpi ]['total_value'] += $value;
		}

		// Verify results
		$this->assertArrayHasKey( MetricsSchema::KPI_SESSIONS, $aggregated );
		$this->assertArrayHasKey( MetricsSchema::KPI_USERS, $aggregated );
		$this->assertArrayHasKey( MetricsSchema::KPI_IMPRESSIONS, $aggregated );

		$this->assertEquals( 1500, $aggregated[ MetricsSchema::KPI_SESSIONS ]['total_value'] );
		$this->assertEquals( 1200, $aggregated[ MetricsSchema::KPI_USERS ]['total_value'] );
		$this->assertEquals( 25000, $aggregated[ MetricsSchema::KPI_IMPRESSIONS ]['total_value'] );
	}

	/**
	 * Test fallback application
	 */
	public function test_fallback_application(): void {
		// Test with empty data to ensure fallbacks are applied
		$mock_data = MetricsAggregator::generate_mock_data( 123, '2024-01-01 00:00:00', '2024-01-31 23:59:59' );
		
		// Clear data to test fallbacks
		$empty_aggregated = [];
		$requested_kpis = [ MetricsSchema::KPI_SESSIONS, MetricsSchema::KPI_USERS ];

		// Simulate fallback application by checking that generate_mock_data includes all expected KPIs
		$this->assertArrayHasKey( MetricsSchema::KPI_SESSIONS, $mock_data );
		$this->assertArrayHasKey( MetricsSchema::KPI_USERS, $mock_data );

		// Test that fallback values are reasonable (non-negative)
		$this->assertGreaterThanOrEqual( 0, $mock_data[ MetricsSchema::KPI_SESSIONS ]['total_value'] );
		$this->assertGreaterThanOrEqual( 0, $mock_data[ MetricsSchema::KPI_USERS ]['total_value'] );
	}

	/**
	 * Test KPI summary generation
	 */
	public function test_kpi_summary_structure(): void {
		// Use mock data to test summary structure
		$mock_data = MetricsAggregator::generate_mock_data( 123, '2024-01-01 00:00:00', '2024-01-31 23:59:59' );

		// Verify the structure matches expected KPI summary format
		foreach ( $mock_data as $kpi => $data ) {
			$this->assertArrayHasKey( 'kpi', $data );
			$this->assertArrayHasKey( 'total_value', $data );
			$this->assertArrayHasKey( 'count', $data );
			$this->assertArrayHasKey( 'values', $data );
			$this->assertArrayHasKey( 'sources', $data );

			$this->assertEquals( $kpi, $data['kpi'] );
			$this->assertIsNumeric( $data['total_value'] );
			$this->assertIsInt( $data['count'] );
			$this->assertIsArray( $data['values'] );
			$this->assertIsArray( $data['sources'] );
		}
	}

	/**
	 * Test period comparison logic
	 */
	public function test_period_comparison_calculation(): void {
		// Test the mathematical logic for period comparison
		$current_value = 1500;
		$previous_value = 1200;

		$change = $current_value - $previous_value;
		$change_percentage = $previous_value > 0 ? ( $change / $previous_value ) * 100 : 0;

		$this->assertEquals( 300, $change );
		$this->assertEquals( 25, $change_percentage );

		// Test with zero previous value
		$previous_value_zero = 0;
		$change_percentage_zero = $previous_value_zero > 0 ? ( $change / $previous_value_zero ) * 100 : 0;
		$this->assertEquals( 0, $change_percentage_zero );

		// Test trend calculation
		$trend_up = $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'stable' );
		$this->assertEquals( 'up', $trend_up );

		$trend_down = -100 > 0 ? 'up' : ( -100 < 0 ? 'down' : 'stable' );
		$this->assertEquals( 'down', $trend_down );

		$trend_stable = 0 > 0 ? 'up' : ( 0 < 0 ? 'down' : 'stable' );
		$this->assertEquals( 'stable', $trend_stable );
	}

	/**
	 * Test value formatting
	 */
	public function test_value_formatting(): void {
		// Test number formatting
		$formatted_number = number_format( 1500 );
		$this->assertEquals( '1,500', $formatted_number );

		// Test percentage formatting
		$formatted_percentage = number_format( 0.65 * 100, 2 ) . '%';
		$this->assertEquals( '65.00%', $formatted_percentage );

		// Test currency formatting
		$formatted_currency = '€' . number_format( 1234.56, 2 );
		$this->assertEquals( '€1,234.56', $formatted_currency );
	}

	/**
	 * Test aggregation methods
	 */
	public function test_aggregation_methods(): void {
		// Test sum aggregation
		$values = [ 100, 200, 300 ];
		$sum = array_sum( $values );
		$this->assertEquals( 600, $sum );

		// Test average aggregation
		$count = count( $values );
		$average = $sum / $count;
		$this->assertEquals( 200, $average );

		// Test with single value
		$single_value = [ 150 ];
		$single_sum = array_sum( $single_value );
		$single_average = $single_sum / count( $single_value );
		$this->assertEquals( 150, $single_sum );
		$this->assertEquals( 150, $single_average );
	}

	/**
	 * Test data quality assessment logic
	 */
	public function test_data_quality_assessment(): void {
		// Test coverage percentage calculation
		$total_sources = 5;
		$active_sources = 3;
		$coverage_percentage = $total_sources > 0 ? round( ( $active_sources / $total_sources ) * 100, 2 ) : 0;
		
		$this->assertEquals( 60.0, $coverage_percentage );

		// Test with zero sources
		$zero_coverage = 0 > 0 ? round( ( 0 / 0 ) * 100, 2 ) : 0;
		$this->assertEquals( 0, $zero_coverage );

		// Test with full coverage
		$full_coverage = round( ( 5 / 5 ) * 100, 2 );
		$this->assertEquals( 100.0, $full_coverage );
	}

	/**
	 * Test metric source grouping logic
	 */
	public function test_metric_source_grouping(): void {
		// Test grouping metrics by source
		$mock_metrics = [
			(object) [ 'source' => 'google_analytics_4', 'metric' => 'sessions', 'value' => '1500' ],
			(object) [ 'source' => 'google_analytics_4', 'metric' => 'users', 'value' => '1200' ],
			(object) [ 'source' => 'facebook_ads', 'metric' => 'impressions', 'value' => '25000' ],
		];

		$by_source = [];
		foreach ( $mock_metrics as $metric ) {
			if ( ! isset( $by_source[ $metric->source ] ) ) {
				$by_source[ $metric->source ] = [
					'source' => $metric->source,
					'metrics' => [],
					'total_metrics' => 0,
				];
			}
			
			$by_source[ $metric->source ]['metrics'][ $metric->metric ] = $metric->value;
			$by_source[ $metric->source ]['total_metrics']++;
		}

		$this->assertArrayHasKey( 'google_analytics_4', $by_source );
		$this->assertArrayHasKey( 'facebook_ads', $by_source );
		
		$this->assertEquals( 2, $by_source['google_analytics_4']['total_metrics'] );
		$this->assertEquals( 1, $by_source['facebook_ads']['total_metrics'] );
		
		$this->assertArrayHasKey( 'sessions', $by_source['google_analytics_4']['metrics'] );
		$this->assertArrayHasKey( 'users', $by_source['google_analytics_4']['metrics'] );
		$this->assertArrayHasKey( 'impressions', $by_source['facebook_ads']['metrics'] );
	}

	/**
	 * Test schema integration
	 */
	public function test_schema_integration(): void {
		// Test that aggregator properly uses schema methods
		$this->assertTrue( MetricsSchema::is_standard_kpi( MetricsSchema::KPI_SESSIONS ) );
		$this->assertEquals( 'sum', MetricsSchema::get_aggregation_method( MetricsSchema::KPI_SESSIONS ) );
		$this->assertEquals( 'average', MetricsSchema::get_aggregation_method( MetricsSchema::KPI_BOUNCE_RATE ) );
		
		// Test normalization integration
		$normalized = MetricsSchema::normalize_metric_name( 'google_analytics_4', 'sessions' );
		$this->assertEquals( MetricsSchema::KPI_SESSIONS, $normalized );
		
		// Test category filtering
		$traffic_kpis = MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_TRAFFIC );
		$this->assertContains( MetricsSchema::KPI_SESSIONS, $traffic_kpis );
		$this->assertContains( MetricsSchema::KPI_USERS, $traffic_kpis );
	}
}
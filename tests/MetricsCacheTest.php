<?php
/**
 * Tests for MetricsCache model
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Database\MetricsCacheTable;

/**
 * Test case for MetricsCache CRUD operations
 */
class MetricsCacheTest extends TestCase {

	/**
	 * Mock WordPress database object
	 *
	 * @var object
	 */
	private $wpdb_mock;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Create mock wpdb object
		$this->wpdb_mock = $this->createMock( stdClass::class );
		$this->wpdb_mock->prefix = 'wp_';
		$this->wpdb_mock->insert_id = 1;
		
		// Set global $wpdb for tests
		global $wpdb;
		$wpdb = $this->wpdb_mock;
	}

	/**
	 * Test table name generation
	 */
	public function test_get_table_name(): void {
		$expected = 'wp_fp_metrics_cache';
		$actual = MetricsCacheTable::get_table_name();
		
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test saving a metric record
	 */
	public function test_save_metric(): void {
		// Mock successful insert
		$this->wpdb_mock->method( 'insert' )->willReturn( 1 );
		
		$result = MetricsCache::save(
			123,                           // client_id
			'google_analytics_4',          // source
			'sessions',                    // metric
			'2024-01-01 00:00:00',        // period_start
			'2024-01-31 23:59:59',        // period_end
			'1500',                       // value
			[ 'device' => 'desktop' ]     // meta
		);
		
		$this->assertEquals( 1, $result );
	}

	/**
	 * Test saving metric with minimal data
	 */
	public function test_save_metric_minimal(): void {
		// Mock successful insert
		$this->wpdb_mock->method( 'insert' )->willReturn( 1 );
		
		$result = MetricsCache::save(
			456,                          // client_id
			'facebook_ads',               // source
			'impressions',                // metric
			'2024-02-01 00:00:00',       // period_start
			'2024-02-29 23:59:59',       // period_end
			'25000'                      // value
		);
		
		$this->assertEquals( 1, $result );
	}

	/**
	 * Test saving metric failure
	 */
	public function test_save_metric_failure(): void {
		// Mock failed insert
		$this->wpdb_mock->method( 'insert' )->willReturn( false );
		
		$result = MetricsCache::save(
			789,
			'google_ads',
			'clicks',
			'2024-03-01 00:00:00',
			'2024-03-31 23:59:59',
			'850'
		);
		
		$this->assertFalse( $result );
	}

	/**
	 * Test getting a metric record by ID
	 */
	public function test_get_metric(): void {
		// Mock successful get
		$mock_result = (object) [
			'id'           => 1,
			'client_id'    => 123,
			'source'       => 'google_analytics_4',
			'metric'       => 'sessions',
			'period_start' => '2024-01-01 00:00:00',
			'period_end'   => '2024-01-31 23:59:59',
			'value'        => '1500',
			'meta'         => '{"device":"desktop"}',
			'fetched_at'   => '2024-01-31 12:00:00',
		];
		
		$this->wpdb_mock->method( 'get_row' )->willReturn( $mock_result );
		$this->wpdb_mock->method( 'prepare' )->willReturn( 'SELECT * FROM wp_fp_metrics_cache WHERE id = 1' );
		
		$result = MetricsCache::get( 1 );
		
		$this->assertNotNull( $result );
		$this->assertEquals( 1, $result->id );
		$this->assertEquals( 'sessions', $result->metric );
		$this->assertEquals( [ 'device' => 'desktop' ], $result->meta );
	}

	/**
	 * Test getting non-existent metric record
	 */
	public function test_get_metric_not_found(): void {
		// Mock empty result
		$this->wpdb_mock->method( 'get_row' )->willReturn( null );
		$this->wpdb_mock->method( 'prepare' )->willReturn( 'SELECT * FROM wp_fp_metrics_cache WHERE id = 999' );
		
		$result = MetricsCache::get( 999 );
		
		$this->assertNull( $result );
	}

	/**
	 * Test getting multiple metrics with filters
	 */
	public function test_get_metrics_with_filters(): void {
		// Mock multiple results
		$mock_results = [
			(object) [
				'id'           => 1,
				'client_id'    => 123,
				'source'       => 'google_analytics_4',
				'metric'       => 'sessions',
				'period_start' => '2024-01-01 00:00:00',
				'period_end'   => '2024-01-31 23:59:59',
				'value'        => '1500',
				'meta'         => null,
				'fetched_at'   => '2024-01-31 12:00:00',
			],
			(object) [
				'id'           => 2,
				'client_id'    => 123,
				'source'       => 'google_analytics_4',
				'metric'       => 'pageviews',
				'period_start' => '2024-01-01 00:00:00',
				'period_end'   => '2024-01-31 23:59:59',
				'value'        => '4500',
				'meta'         => null,
				'fetched_at'   => '2024-01-31 12:00:00',
			],
		];
		
		$this->wpdb_mock->method( 'get_results' )->willReturn( $mock_results );
		$this->wpdb_mock->method( 'prepare' )->willReturn( 'SELECT * FROM wp_fp_metrics_cache WHERE client_id = 123 ORDER BY fetched_at DESC LIMIT 100 OFFSET 0' );
		
		$result = MetricsCache::get_metrics( [ 'client_id' => 123 ] );
		
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'sessions', $result[0]->metric );
		$this->assertEquals( 'pageviews', $result[1]->metric );
	}

	/**
	 * Test updating a metric record
	 */
	public function test_update_metric(): void {
		// Mock successful update
		$this->wpdb_mock->method( 'update' )->willReturn( 1 );
		
		$result = MetricsCache::update( 1, [
			'value' => '1800',
			'meta'  => [ 'device' => 'mobile' ],
		] );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test updating with invalid fields
	 */
	public function test_update_metric_invalid_fields(): void {
		$result = MetricsCache::update( 1, [
			'invalid_field' => 'test',
		] );
		
		$this->assertFalse( $result );
	}

	/**
	 * Test deleting a metric record
	 */
	public function test_delete_metric(): void {
		// Mock successful delete
		$this->wpdb_mock->method( 'delete' )->willReturn( 1 );
		
		$result = MetricsCache::delete( 1 );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test deleting by criteria
	 */
	public function test_delete_by_criteria(): void {
		// Mock successful bulk delete
		$this->wpdb_mock->method( 'delete' )->willReturn( 3 );
		
		$result = MetricsCache::delete_by_criteria( [
			'client_id' => 123,
			'source'    => 'google_analytics_4',
		] );
		
		$this->assertEquals( 3, $result );
	}

	/**
	 * Test counting records
	 */
	public function test_count_metrics(): void {
		// Mock count result
		$this->wpdb_mock->method( 'get_var' )->willReturn( '5' );
		$this->wpdb_mock->method( 'prepare' )->willReturn( 'SELECT COUNT(*) FROM wp_fp_metrics_cache WHERE client_id = 123' );
		
		$result = MetricsCache::count( [ 'client_id' => 123 ] );
		
		$this->assertEquals( 5, $result );
	}
}
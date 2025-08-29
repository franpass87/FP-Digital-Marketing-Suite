<?php
/**
 * Performance Cache Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Test cases for PerformanceCache functionality
 */
class PerformanceCacheTest extends TestCase {

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Clear any existing cache settings
		delete_option( 'fp_digital_marketing_cache_settings' );
		delete_option( 'fp_digital_marketing_benchmark_data' );
	}

	/**
	 * Clean up after tests
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();
		
		// Clean up test data
		delete_option( 'fp_digital_marketing_cache_settings' );
		delete_option( 'fp_digital_marketing_benchmark_data' );
	}

	/**
	 * Test default cache settings
	 *
	 * @return void
	 */
	public function testDefaultCacheSettings(): void {
		$settings = PerformanceCache::get_cache_settings();
		
		$this->assertTrue( $settings['enabled'] );
		$this->assertTrue( $settings['use_object_cache'] );
		$this->assertTrue( $settings['use_transients'] );
		$this->assertEquals( 900, $settings['default_ttl'] );
		$this->assertTrue( $settings['auto_invalidate'] );
		$this->assertTrue( $settings['benchmark_enabled'] );
	}

	/**
	 * Test cache settings update
	 *
	 * @return void
	 */
	public function testUpdateCacheSettings(): void {
		$new_settings = [
			'enabled' => false,
			'default_ttl' => 1800,
			'metrics_ttl' => 600,
		];
		
		$result = PerformanceCache::update_cache_settings( $new_settings );
		$this->assertTrue( $result );
		
		$updated_settings = PerformanceCache::get_cache_settings();
		$this->assertFalse( $updated_settings['enabled'] );
		$this->assertEquals( 1800, $updated_settings['default_ttl'] );
		$this->assertEquals( 600, $updated_settings['metrics_ttl'] );
		
		// Check that other settings remain defaults
		$this->assertTrue( $updated_settings['use_object_cache'] );
	}

	/**
	 * Test cache enabled check
	 *
	 * @return void
	 */
	public function testIsCacheEnabled(): void {
		// Default should be enabled
		$this->assertTrue( PerformanceCache::is_cache_enabled() );
		
		// Disable cache
		PerformanceCache::update_cache_settings( [ 'enabled' => false ] );
		$this->assertFalse( PerformanceCache::is_cache_enabled() );
		
		// Re-enable cache
		PerformanceCache::update_cache_settings( [ 'enabled' => true ] );
		$this->assertTrue( PerformanceCache::is_cache_enabled() );
	}

	/**
	 * Test cache key generation for metrics
	 *
	 * @return void
	 */
	public function testGenerateMetricsKey(): void {
		$params = [
			'client_id' => 123,
			'period_start' => '2024-01-01 00:00:00',
			'period_end' => '2024-01-31 23:59:59',
			'metrics' => [ 'sessions', 'pageviews' ],
		];
		
		$key1 = PerformanceCache::generate_metrics_key( $params );
		$this->assertIsString( $key1 );
		$this->assertStringStartsWith( 'metrics_', $key1 );
		
		// Same parameters should generate same key
		$key2 = PerformanceCache::generate_metrics_key( $params );
		$this->assertEquals( $key1, $key2 );
		
		// Different parameters should generate different key
		$params['client_id'] = 456;
		$key3 = PerformanceCache::generate_metrics_key( $params );
		$this->assertNotEquals( $key1, $key3 );
	}

	/**
	 * Test cache key generation for reports
	 *
	 * @return void
	 */
	public function testGenerateReportKey(): void {
		$key1 = PerformanceCache::generate_report_key( 123, 'monthly_summary' );
		$this->assertIsString( $key1 );
		$this->assertStringStartsWith( 'report_', $key1 );
		
		// Same parameters should generate same key
		$key2 = PerformanceCache::generate_report_key( 123, 'monthly_summary' );
		$this->assertEquals( $key1, $key2 );
		
		// Different client should generate different key
		$key3 = PerformanceCache::generate_report_key( 456, 'monthly_summary' );
		$this->assertNotEquals( $key1, $key3 );
		
		// Different report type should generate different key
		$key4 = PerformanceCache::generate_report_key( 123, 'weekly_summary' );
		$this->assertNotEquals( $key1, $key4 );
	}

	/**
	 * Test cached data retrieval with callback
	 *
	 * @return void
	 */
	public function testGetCachedWithCallback(): void {
		$cache_key = 'test_key';
		$cache_group = PerformanceCache::CACHE_GROUP_METRICS;
		$test_data = [ 'test' => 'data' ];
		
		$callback_called = false;
		$callback = function() use ( $test_data, &$callback_called ) {
			$callback_called = true;
			return $test_data;
		};
		
		// First call should execute callback
		$result = PerformanceCache::get_cached( $cache_key, $cache_group, $callback );
		$this->assertEquals( $test_data, $result );
		$this->assertTrue( $callback_called );
		
		// Reset callback flag
		$callback_called = false;
		
		// Second call should use cache (callback should not be called)
		$result2 = PerformanceCache::get_cached( $cache_key, $cache_group, $callback );
		$this->assertEquals( $test_data, $result2 );
		$this->assertFalse( $callback_called );
	}

	/**
	 * Test cache when disabled
	 *
	 * @return void
	 */
	public function testCacheWhenDisabled(): void {
		// Disable cache
		PerformanceCache::update_cache_settings( [ 'enabled' => false ] );
		
		$test_data = [ 'test' => 'data' ];
		$callback_count = 0;
		$callback = function() use ( $test_data, &$callback_count ) {
			$callback_count++;
			return $test_data;
		};
		
		// Both calls should execute callback since cache is disabled
		$result1 = PerformanceCache::get_cached( 'test_key', PerformanceCache::CACHE_GROUP_METRICS, $callback );
		$result2 = PerformanceCache::get_cached( 'test_key', PerformanceCache::CACHE_GROUP_METRICS, $callback );
		
		$this->assertEquals( $test_data, $result1 );
		$this->assertEquals( $test_data, $result2 );
		$this->assertEquals( 2, $callback_count );
	}

	/**
	 * Test cache deletion
	 *
	 * @return void
	 */
	public function testDeleteCached(): void {
		$cache_key = 'test_key';
		$cache_group = PerformanceCache::CACHE_GROUP_METRICS;
		$test_data = [ 'test' => 'data' ];
		
		// Set cache
		PerformanceCache::set_cached( $cache_key, $cache_group, $test_data );
		
		// Verify it's cached
		$callback_called = false;
		$callback = function() use ( &$callback_called ) {
			$callback_called = true;
			return [ 'new' => 'data' ];
		};
		
		$result = PerformanceCache::get_cached( $cache_key, $cache_group, $callback );
		$this->assertEquals( $test_data, $result );
		$this->assertFalse( $callback_called );
		
		// Delete cache
		PerformanceCache::delete_cached( $cache_key, $cache_group );
		
		// Now callback should be called
		$result2 = PerformanceCache::get_cached( $cache_key, $cache_group, $callback );
		$this->assertTrue( $callback_called );
		$this->assertEquals( [ 'new' => 'data' ], $result2 );
	}

	/**
	 * Test cache invalidation by group
	 *
	 * @return void
	 */
	public function testInvalidateGroup(): void {
		$group = PerformanceCache::CACHE_GROUP_METRICS;
		
		// Set some cache data
		PerformanceCache::set_cached( 'key1', $group, 'data1' );
		PerformanceCache::set_cached( 'key2', $group, 'data2' );
		PerformanceCache::set_cached( 'key3', PerformanceCache::CACHE_GROUP_REPORTS, 'data3' );
		
		// Invalidate metrics group
		$result = PerformanceCache::invalidate_group( $group );
		$this->assertTrue( $result );
		
		// Check that metrics cache is cleared but reports cache remains
		$callback_count = 0;
		$callback = function() use ( &$callback_count ) {
			$callback_count++;
			return 'new_data';
		};
		
		// These should call callback (cache invalidated)
		PerformanceCache::get_cached( 'key1', $group, $callback );
		PerformanceCache::get_cached( 'key2', $group, $callback );
		
		$this->assertEquals( 2, $callback_count );
		
		// This should not call callback (different group, cache still valid)
		$result = PerformanceCache::get_cached( 'key3', PerformanceCache::CACHE_GROUP_REPORTS, $callback );
		$this->assertEquals( 'data3', $result );
		$this->assertEquals( 2, $callback_count ); // Should remain 2
	}

	/**
	 * Test cache invalidation of all groups
	 *
	 * @return void
	 */
	public function testInvalidateAll(): void {
		// Set cache data in different groups
		PerformanceCache::set_cached( 'key1', PerformanceCache::CACHE_GROUP_METRICS, 'data1' );
		PerformanceCache::set_cached( 'key2', PerformanceCache::CACHE_GROUP_REPORTS, 'data2' );
		PerformanceCache::set_cached( 'key3', PerformanceCache::CACHE_GROUP_AGGREGATED, 'data3' );
		
		// Invalidate all cache
		$result = PerformanceCache::invalidate_all();
		$this->assertTrue( $result );
		
		// All cache should be invalidated
		$callback_count = 0;
		$callback = function() use ( &$callback_count ) {
			$callback_count++;
			return 'new_data';
		};
		
		PerformanceCache::get_cached( 'key1', PerformanceCache::CACHE_GROUP_METRICS, $callback );
		PerformanceCache::get_cached( 'key2', PerformanceCache::CACHE_GROUP_REPORTS, $callback );
		PerformanceCache::get_cached( 'key3', PerformanceCache::CACHE_GROUP_AGGREGATED, $callback );
		
		$this->assertEquals( 3, $callback_count );
	}

	/**
	 * Test cache statistics initialization
	 *
	 * @return void
	 */
	public function testCacheStatsInitialization(): void {
		$stats = PerformanceCache::get_cache_stats();
		
		$this->assertIsArray( $stats );
		$this->assertEquals( 0, $stats['total_requests'] );
		$this->assertEquals( 0, $stats['cache_hits'] );
		$this->assertEquals( 0, $stats['cache_misses'] );
		$this->assertEquals( 0, $stats['hit_ratio'] );
		$this->assertIsArray( $stats['groups'] );
	}

	/**
	 * Test clearing cache statistics
	 *
	 * @return void
	 */
	public function testClearStats(): void {
		// Set some benchmark data manually
		update_option( 'fp_digital_marketing_benchmark_data', [
			[
				'timestamp' => time(),
				'key' => 'test_key',
				'group' => 'test_group',
				'cache_hit' => true,
				'execution_time' => 0.1,
			]
		] );
		
		$result = PerformanceCache::clear_stats();
		$this->assertTrue( $result );
		
		$stats = PerformanceCache::get_cache_stats();
		$this->assertEquals( 0, $stats['total_requests'] );
	}
}
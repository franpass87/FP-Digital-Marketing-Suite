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
         * Mocked object cache storage.
         *
         * @var array<string, array<string, mixed>>
         */
        private $mock_object_cache = [];

        /**
         * Mocked transient storage.
         *
         * @var array<string, mixed>
         */
        private $mock_transients = [];

        /**
         * Mocked cache versions for object cache groups.
         *
         * @var array<string, int>
         */
        private $mock_cache_versions = [];

        /**
         * Previously registered WordPress mock functions.
         *
         * @var array<string, callable>
         */
        private $previous_wp_mock_functions = [];

        /**
         * Set up test environment
         *
         * @return void
         */
        protected function setUp(): void {
                parent::setUp();

                global $wp_mock_functions;
                global $wp_options;

                $this->mock_object_cache = [];
                $this->mock_transients = [];
                $this->mock_cache_versions = [];

                if ( isset( $wp_options ) && is_array( $wp_options ) ) {
                        foreach ( array_keys( $wp_options ) as $option_name ) {
                                if ( strpos( $option_name, '_transient_' ) === 0 ) {
                                        unset( $wp_options[ $option_name ] );
                                }
                        }
                }

                $existing_mocks = [];
                if ( isset( $wp_mock_functions ) && is_array( $wp_mock_functions ) ) {
                        $existing_mocks = $wp_mock_functions;
                }

                $this->previous_wp_mock_functions = $existing_mocks;
                $wp_mock_functions = $existing_mocks;

                $object_cache =& $this->mock_object_cache;
                $transients =& $this->mock_transients;
                $cache_versions =& $this->mock_cache_versions;

                $wp_mock_functions['wp_cache_get'] = function( $key, $group = '' ) use ( &$object_cache, &$cache_versions ) {
                        if ( 'cache_versions' === $group ) {
                                return $cache_versions[ $key ] ?? false;
                        }

                        if ( isset( $object_cache[ $group ][ $key ] ) ) {
                                $entry = $object_cache[ $group ][ $key ];
                                if ( is_array( $entry ) && array_key_exists( 'version', $entry ) && array_key_exists( 'data', $entry ) ) {
                                        $current_version = $cache_versions[ 'cache_version_' . $group ] ?? 0;
                                        if ( $entry['version'] === $current_version ) {
                                                return $entry['data'];
                                        }
                                        return false;
                                }
                                return $entry;
                        }
                        return false;
                };

                $wp_mock_functions['wp_cache_set'] = function( $key, $data, $group = '', $expire = 0 ) use ( &$object_cache, &$cache_versions ) {
                        if ( 'cache_versions' === $group ) {
                                $cache_versions[ $key ] = (int) $data;
                                return true;
                        }

                        if ( ! isset( $object_cache[ $group ] ) ) {
                                $object_cache[ $group ] = [];
                        }

                        $current_version = $cache_versions[ 'cache_version_' . $group ] ?? 0;

                        $object_cache[ $group ][ $key ] = [
                                'version' => $current_version,
                                'data'    => $data,
                        ];
                        return true;
                };

                $wp_mock_functions['wp_cache_delete'] = function( $key, $group = '' ) use ( &$object_cache, &$cache_versions ) {
                        if ( 'cache_versions' === $group ) {
                                if ( isset( $cache_versions[ $key ] ) ) {
                                        unset( $cache_versions[ $key ] );
                                        return true;
                                }
                                return false;
                        }

                        if ( isset( $object_cache[ $group ][ $key ] ) ) {
                                unset( $object_cache[ $group ][ $key ] );
                                return true;
                        }
                        return false;
                };

                $wp_mock_functions['get_transient'] = function( $transient ) use ( &$transients ) {
                        return $transients[ $transient ] ?? false;
                };

                $wp_mock_functions['set_transient'] = function( $transient, $value, $expiration = 0 ) use ( &$transients ) {
                        global $wp_options;

                        $transients[ $transient ] = $value;

                        if ( isset( $wp_options ) && is_array( $wp_options ) ) {
                                $wp_options[ '_transient_' . $transient ] = $value;
                        }

                        return true;
                };

                $wp_mock_functions['delete_transient'] = function( $transient ) use ( &$transients ) {
                        global $wp_options;

                        $deleted = false;

                        if ( isset( $transients[ $transient ] ) ) {
                                unset( $transients[ $transient ] );
                                $deleted = true;
                        }

                        if ( isset( $wp_options ) && is_array( $wp_options ) ) {
                                $option_key = '_transient_' . $transient;
                                if ( isset( $wp_options[ $option_key ] ) ) {
                                        unset( $wp_options[ $option_key ] );
                                        $deleted = true;
                                }
                        }

                        return $deleted;
                };

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
                global $wp_mock_functions;
                global $wp_options;

                $wp_mock_functions = $this->previous_wp_mock_functions;
                $this->previous_wp_mock_functions = [];
                $this->mock_object_cache = [];
                $this->mock_transients = [];
                $this->mock_cache_versions = [];

                if ( isset( $wp_options ) && is_array( $wp_options ) ) {
                        foreach ( array_keys( $wp_options ) as $option_name ) {
                                if ( strpos( $option_name, '_transient_' ) === 0 ) {
                                        unset( $wp_options[ $option_name ] );
                                }
                        }
                }

                // Clean up test data
                delete_option( 'fp_digital_marketing_cache_settings' );
                delete_option( 'fp_digital_marketing_benchmark_data' );

                parent::tearDown();
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
         * Test transients fallback when object cache is disabled
         *
         * @return void
         */
        public function testGetCachedUsesTransientsWhenObjectCacheDisabled(): void {
                PerformanceCache::update_cache_settings(
                        [
                                'use_object_cache' => false,
                                'use_transients' => true,
                                'enabled' => true,
                        ]
                );

                $cache_key = 'transient_only_key';
                $cache_group = PerformanceCache::CACHE_GROUP_METRICS;
                $expected_data = [ 'value' => 'generated' ];

                $callback_calls = 0;
                $callback = function() use ( $expected_data, &$callback_calls ) {
                        $callback_calls++;
                        return $expected_data;
                };

                $result = PerformanceCache::get_cached( $cache_key, $cache_group, $callback );
                $this->assertEquals( $expected_data, $result );
                $this->assertEquals( 1, $callback_calls );

                $transient_key = 'fp_dms_' . $cache_group . '_' . $cache_key;
                $this->assertArrayHasKey( $transient_key, $this->mock_transients );
                $this->assertEquals( $expected_data, $this->mock_transients[ $transient_key ] );

                $cached_result = PerformanceCache::get_cached( $cache_key, $cache_group, $callback );
                $this->assertEquals( $expected_data, $cached_result );
                $this->assertEquals( 1, $callback_calls );
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
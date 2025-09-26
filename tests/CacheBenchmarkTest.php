<?php
/**
 * Cache Benchmark Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\CacheBenchmark;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Test cases for CacheBenchmark functionality
 */
class CacheBenchmarkTest extends TestCase {

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Clear any existing benchmark data
		delete_option( 'fp_digital_marketing_benchmark_results' );
		delete_option( 'fp_digital_marketing_cache_settings' );
		delete_option( 'fp_digital_marketing_benchmark_data' );

		// Enable cache for testing
		PerformanceCache::update_cache_settings( [ 'enabled' => true ] );
	}

	/**
	 * Clean up after tests
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();

		// Clean up test data
		delete_option( 'fp_digital_marketing_benchmark_results' );
		delete_option( 'fp_digital_marketing_cache_settings' );
		delete_option( 'fp_digital_marketing_benchmark_data' );
	}

	/**
	 * Test performance benchmark basic functionality
	 *
	 * @return void
	 */
	public function testRunPerformanceBenchmarkBasic(): void {
		$results = CacheBenchmark::run_performance_benchmark( 3 );

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'test_info', $results );
		$this->assertArrayHasKey( 'without_cache', $results );
		$this->assertArrayHasKey( 'with_cache', $results );
		$this->assertArrayHasKey( 'performance_improvement', $results );
		$this->assertArrayHasKey( 'cache_hit_ratio', $results );

		// Check test info
		$this->assertEquals( 3, $results['test_info']['iterations'] );
		$this->assertArrayHasKey( 'timestamp', $results['test_info'] );
		$this->assertArrayHasKey( 'params', $results['test_info'] );

		// Check that we have the right number of measurements
		$this->assertCount( 3, $results['without_cache'] );
		$this->assertCount( 3, $results['with_cache'] );

		// Check that times are positive numbers
		foreach ( $results['without_cache'] as $time ) {
			$this->assertIsFloat( $time );
			$this->assertGreaterThan( 0, $time );
		}

		foreach ( $results['with_cache'] as $time ) {
			$this->assertIsFloat( $time );
			$this->assertGreaterThan( 0, $time );
		}

		// Check calculated metrics
		$this->assertIsFloat( $results['avg_without_cache'] );
		$this->assertIsFloat( $results['avg_with_cache'] );
		$this->assertIsFloat( $results['performance_improvement'] );
		$this->assertIsFloat( $results['cache_hit_ratio'] );
	}

	/**
	 * Test performance benchmark with custom parameters
	 *
	 * @return void
	 */
	public function testRunPerformanceBenchmarkWithCustomParams(): void {
		$custom_params = [
			'client_id'    => 456,
			'period_start' => '2024-02-01 00:00:00',
			'period_end'   => '2024-02-29 23:59:59',
			'metrics'      => [ 'clicks', 'impressions' ],
		];

		$results = CacheBenchmark::run_performance_benchmark( 2, $custom_params );

		$this->assertEquals( 456, $results['test_info']['params']['client_id'] );
		$this->assertEquals( '2024-02-01 00:00:00', $results['test_info']['params']['period_start'] );
		$this->assertEquals( [ 'clicks', 'impressions' ], $results['test_info']['params']['metrics'] );
		$this->assertCount( 2, $results['without_cache'] );
		$this->assertCount( 2, $results['with_cache'] );
	}

	/**
	 * Test load test basic functionality
	 *
	 * @return void
	 */
	public function testRunLoadTestBasic(): void {
		$results = CacheBenchmark::run_load_test( 2, 3 );

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'test_info', $results );
		$this->assertArrayHasKey( 'scenarios', $results );
		$this->assertArrayHasKey( 'overall_stats', $results );

		// Check test info
		$this->assertEquals( 2, $results['test_info']['concurrent_users'] );
		$this->assertEquals( 3, $results['test_info']['requests_per_user'] );
		$this->assertArrayHasKey( 'timestamp', $results['test_info'] );

		// Check scenarios
		$this->assertIsArray( $results['scenarios'] );
		$this->assertGreaterThan( 0, count( $results['scenarios'] ) );

		// Check each scenario has required fields
		foreach ( $results['scenarios'] as $scenario_name => $scenario_data ) {
			$this->assertIsString( $scenario_name );
			$this->assertArrayHasKey( 'scenario_name', $scenario_data );
			$this->assertArrayHasKey( 'request_times', $scenario_data );
			$this->assertArrayHasKey( 'avg_response_time', $scenario_data );
			$this->assertArrayHasKey( 'total_requests', $scenario_data );
			$this->assertArrayHasKey( 'error_rate', $scenario_data );

			$this->assertIsArray( $scenario_data['request_times'] );
			$this->assertIsFloat( $scenario_data['avg_response_time'] );
			$this->assertIsInt( $scenario_data['total_requests'] );
		}

		// Check overall stats
		$this->assertArrayHasKey( 'total_requests', $results['overall_stats'] );
		$this->assertArrayHasKey( 'avg_response_time', $results['overall_stats'] );
		$this->assertArrayHasKey( 'error_rate', $results['overall_stats'] );
		$this->assertArrayHasKey( 'cache_hit_ratio', $results['overall_stats'] );
	}

	/**
	 * Ensure load tests handle zero requests per user without errors.
	 *
	 * @return void
	 */
	public function testRunLoadTestWithZeroRequestsPerUser(): void {
		$results = CacheBenchmark::run_load_test( 2, 0 );

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'scenarios', $results );
		$this->assertArrayHasKey( 'overall_stats', $results );

		foreach ( $results['scenarios'] as $scenario_data ) {
			$this->assertSame( 0, $scenario_data['total_requests'] );
			$this->assertSame( 0.0, $scenario_data['avg_response_time'] );
			$this->assertSame( 0.0, $scenario_data['min_response_time'] );
			$this->assertSame( 0.0, $scenario_data['max_response_time'] );
			$this->assertSame( 0.0, $scenario_data['error_rate'] );
		}

		$this->assertSame( 0, $results['overall_stats']['total_requests'] );
		$this->assertSame( 0.0, $results['overall_stats']['avg_response_time'] );
		$this->assertSame( 0.0, $results['overall_stats']['min_response_time'] );
		$this->assertSame( 0.0, $results['overall_stats']['max_response_time'] );
		$this->assertSame( 0.0, $results['overall_stats']['error_rate'] );
		$this->assertSame( 0.0, $results['overall_stats']['cache_hit_ratio'] );
	}

	/**
	 * Ensure calculate_overall_stats gracefully handles empty scenario input.
	 *
	 * @return void
	 */
	public function testCalculateOverallStatsHandlesEmptyScenarios(): void {
		$reflection = new \ReflectionMethod( CacheBenchmark::class, 'calculate_overall_stats' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( null, [] );

		$this->assertIsArray( $result );
		$this->assertSame(
			[
				'total_requests'    => 0,
				'avg_response_time' => 0.0,
				'min_response_time' => 0.0,
				'max_response_time' => 0.0,
				'error_rate'        => 0.0,
				'cache_hit_ratio'   => 0.0,
			],
			$result
		);
	}

		/**
		 * Test memory usage test
		 *
		 * @return void
		 */
	public function testRunMemoryTest(): void {
		$results = CacheBenchmark::run_memory_test( 1 );

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'initial_memory', $results );
		$this->assertArrayHasKey( 'initial_peak', $results );
		$this->assertArrayHasKey( 'test_data_size', $results );
		$this->assertArrayHasKey( 'cache_memory_usage', $results );
		$this->assertArrayHasKey( 'final_memory', $results );
		$this->assertArrayHasKey( 'final_peak', $results );
		$this->assertArrayHasKey( 'memory_efficiency', $results );

		// Check that memory values are positive
		$this->assertGreaterThan( 0, $results['initial_memory'] );
		$this->assertGreaterThan( 0, $results['test_data_size'] );
		$this->assertIsFloat( $results['memory_efficiency'] );

		// Final memory should be greater than or equal to initial memory
		$this->assertGreaterThanOrEqual( $results['initial_memory'], $results['final_memory'] );
	}

	/**
	 * Test benchmark history storage and retrieval
	 *
	 * @return void
	 */
        /**
         * @group integration
         */
        public function testBenchmarkHistory(): void {
		// Initially should be empty
		$history = CacheBenchmark::get_benchmark_history();
		$this->assertIsArray( $history );
		$this->assertEmpty( $history );

		// Run a benchmark to generate history
		CacheBenchmark::run_performance_benchmark( 2 );

		// Check that history is stored
		$history = CacheBenchmark::get_benchmark_history();
		$this->assertCount( 1, $history );
		$this->assertArrayHasKey( 'test_info', $history[0] );
		$this->assertArrayHasKey( 'performance_improvement', $history[0] );

		// Run another benchmark
		CacheBenchmark::run_performance_benchmark( 2 );

		// Should have 2 entries now
		$history = CacheBenchmark::get_benchmark_history();
		$this->assertCount( 2, $history );

		// Test with limit
		$limited_history = CacheBenchmark::get_benchmark_history( 1 );
		$this->assertCount( 1, $limited_history );
	}

	/**
	 * Test clearing benchmark history
	 *
	 * @return void
	 */
        /**
         * @group integration
         */
        public function testClearBenchmarkHistory(): void {
		// Run a benchmark to generate history
		CacheBenchmark::run_performance_benchmark( 2 );

		// Verify history exists
		$history = CacheBenchmark::get_benchmark_history();
		$this->assertNotEmpty( $history );

		// Clear history
		$result = CacheBenchmark::clear_benchmark_history();
		$this->assertTrue( $result );

		// Verify history is empty
		$history = CacheBenchmark::get_benchmark_history();
		$this->assertEmpty( $history );
	}

	/**
	 * Test performance report generation
	 *
	 * @return void
	 */
        /**
         * @group integration
         */
        public function testGeneratePerformanceReport(): void {
		// Run a benchmark first to have some data
		CacheBenchmark::run_performance_benchmark( 2 );

		$report = CacheBenchmark::generate_performance_report();

		$this->assertIsArray( $report );
		$this->assertArrayHasKey( 'current_cache_stats', $report );
		$this->assertArrayHasKey( 'recent_benchmarks', $report );
		$this->assertArrayHasKey( 'recommendations', $report );
		$this->assertArrayHasKey( 'cache_health_score', $report );

		// Check cache stats structure
		$this->assertIsArray( $report['current_cache_stats'] );

		// Check recent benchmarks
		$this->assertIsArray( $report['recent_benchmarks'] );
		$this->assertNotEmpty( $report['recent_benchmarks'] );

		// Check recommendations
		$this->assertIsArray( $report['recommendations'] );

		// Check health score
		$this->assertIsInt( $report['cache_health_score'] );
		$this->assertGreaterThanOrEqual( 0, $report['cache_health_score'] );
		$this->assertLessThanOrEqual( 100, $report['cache_health_score'] );
	}

	/**
	 * Test load test with custom scenarios
	 *
	 * @return void
	 */
	public function testRunLoadTestWithCustomScenarios(): void {
		$custom_scenarios = [
			'test_scenario' => [
				'type'   => 'metrics',
				'params' => [
					'client_id'    => 789,
					'period_start' => '2024-01-01 00:00:00',
					'period_end'   => '2024-01-31 23:59:59',
				],
			],
		];

		$results = CacheBenchmark::run_load_test( 2, 2, $custom_scenarios );

		$this->assertArrayHasKey( 'test_scenario', $results['scenarios'] );
		$this->assertEquals( 'test_scenario', $results['scenarios']['test_scenario']['scenario_name'] );
		$this->assertEquals( 4, $results['scenarios']['test_scenario']['total_requests'] ); // 2 users * 2 requests
	}

	/**
	 * Test memory test with different data size multipliers
	 *
	 * @return void
	 */
	public function testRunMemoryTestWithDifferentSizes(): void {
		$results_small = CacheBenchmark::run_memory_test( 1 );
		$results_large = CacheBenchmark::run_memory_test( 2 );

		// Larger multiplier should result in larger test data size
		$this->assertGreaterThan( $results_small['test_data_size'], $results_large['test_data_size'] );

		// Both should have positive values
		$this->assertGreaterThan( 0, $results_small['test_data_size'] );
		$this->assertGreaterThan( 0, $results_large['test_data_size'] );
	}

	/**
	 * Test that benchmark results are properly timestamped
	 *
	 * @return void
	 */
	public function testBenchmarkTimestamps(): void {
		$before_time = time();

		$results = CacheBenchmark::run_performance_benchmark( 2 );

		$after_time = time();

		$timestamp = strtotime( $results['test_info']['timestamp'] );
		$this->assertGreaterThanOrEqual( $before_time, $timestamp );
		$this->assertLessThanOrEqual( $after_time, $timestamp );
	}
}

<?php
/**
 * Cache Benchmark Helper
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Helpers\MetricsAggregator;
use FP\DigitalMarketing\Helpers\PerformanceCache;
use Exception;

/**
 * Cache Benchmark class
 * 
 * This class provides benchmarking tools to measure cache performance
 * and conduct load testing for the performance caching layer.
 */
class CacheBenchmark {

	/**
	 * Benchmark results option name
	 */
	private const OPTION_BENCHMARK_RESULTS = 'fp_digital_marketing_benchmark_results';

	/**
	 * Run performance benchmark comparing cached vs non-cached queries
	 *
	 * @param int $iterations Number of test iterations
	 * @param array $test_params Test parameters
	 * @return array Benchmark results
	 */
	public static function run_performance_benchmark( int $iterations = 10, array $test_params = [] ): array {
		$defaults = [
			'client_id' => 123,
			'period_start' => date( 'Y-m-01 00:00:00' ),
			'period_end' => date( 'Y-m-t 23:59:59' ),
			'metrics' => [ 'sessions', 'pageviews', 'users' ],
		];
		$params = wp_parse_args( $test_params, $defaults );
		$iterations = max( 0, $iterations );

		$results = [
			'test_info' => [
				'iterations' => $iterations,
				'timestamp' => date( 'Y-m-d H:i:s' ),
				'params' => $params,
			],
			'without_cache' => [],
			'with_cache' => [],
			'performance_improvement' => 0,
			'cache_hit_ratio' => 0,
		];

		// Clear any existing cache for fair testing
		PerformanceCache::invalidate_all();

		// Test without cache
		$cache_enabled = PerformanceCache::is_cache_enabled();
		PerformanceCache::update_cache_settings( [ 'enabled' => false ] );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$start_time = microtime( true );
			
			$data = MetricsAggregator::get_aggregated_metrics(
				$params['client_id'],
				$params['period_start'],
				$params['period_end']
			);
			
			$end_time = microtime( true );
			$results['without_cache'][] = $end_time - $start_time;
		}

		// Test with cache enabled
		PerformanceCache::update_cache_settings( [ 'enabled' => true ] );
		PerformanceCache::invalidate_all();

		$cache_hits = 0;
		for ( $i = 0; $i < $iterations; $i++ ) {
			$start_time = microtime( true );
			
			$cache_key = PerformanceCache::generate_metrics_key( $params );
			$cached_data = PerformanceCache::get_cached(
				$cache_key,
				PerformanceCache::CACHE_GROUP_METRICS,
				function() use ( $params ) {
					return MetricsAggregator::get_aggregated_metrics(
						$params['client_id'],
						$params['period_start'],
						$params['period_end']
					);
				}
			);
			
			$end_time = microtime( true );
			$results['with_cache'][] = $end_time - $start_time;

			// After first iteration, subsequent should be cache hits
			if ( $i > 0 ) {
				$cache_hits++;
			}
		}

		// Restore original cache setting
		PerformanceCache::update_cache_settings( [ 'enabled' => $cache_enabled ] );

		// Calculate metrics
		$without_cache_count = count( $results['without_cache'] );
		$with_cache_count = count( $results['with_cache'] );
		$avg_without_cache = $without_cache_count > 0 ? array_sum( $results['without_cache'] ) / $without_cache_count : 0;
		$avg_with_cache = $with_cache_count > 0 ? array_sum( $results['with_cache'] ) / $with_cache_count : 0;

		$results['avg_without_cache'] = $avg_without_cache;
		$results['avg_with_cache'] = $avg_with_cache;
		$results['performance_improvement'] = $avg_without_cache > 0 ? ( ( $avg_without_cache - $avg_with_cache ) / $avg_without_cache ) * 100 : 0.0;
		$results['cache_hit_ratio'] = $iterations > 1 ? (float) ( ( $cache_hits / ( $iterations - 1 ) ) * 100 ) : 0.0;

		// Store results
		self::store_benchmark_results( $results );

		return $results;
	}

	/**
	 * Run load test simulation
	 *
	 * @param int $concurrent_users Number of simulated concurrent users
	 * @param int $requests_per_user Number of requests per user
	 * @param array $test_scenarios Test scenarios to run
	 * @return array Load test results
	 */
	public static function run_load_test( int $concurrent_users = 5, int $requests_per_user = 10, array $test_scenarios = [] ): array {
		if ( empty( $test_scenarios ) ) {
			$test_scenarios = self::get_default_test_scenarios();
		}

		$results = [
			'test_info' => [
				'concurrent_users' => $concurrent_users,
				'requests_per_user' => $requests_per_user,
				'total_requests' => $concurrent_users * $requests_per_user * count( $test_scenarios ),
				'timestamp' => date( 'Y-m-d H:i:s' ),
			],
			'scenarios' => [],
			'overall_stats' => [],
		];

		foreach ( $test_scenarios as $scenario_name => $scenario ) {
			$scenario_results = self::run_load_test_scenario(
				$scenario_name,
				$scenario,
				$concurrent_users,
				$requests_per_user
			);
			$results['scenarios'][ $scenario_name ] = $scenario_results;
		}

		// Calculate overall statistics
		$results['overall_stats'] = self::calculate_overall_stats( $results['scenarios'] );

		return $results;
	}

	/**
	 * Run memory usage test
	 *
	 * @param int $data_size_multiplier Multiplier for test data size
	 * @return array Memory usage results
	 */
	public static function run_memory_test( int $data_size_multiplier = 1 ): array {
		$initial_memory = memory_get_usage( true );
		$initial_peak = memory_get_peak_usage( true );

		$results = [
			'initial_memory' => $initial_memory,
			'initial_peak' => $initial_peak,
			'test_data_size' => 0,
			'cache_memory_usage' => 0,
			'final_memory' => 0,
			'final_peak' => 0,
			'memory_efficiency' => 0.0,
		];

		// Generate test data
		$test_data = self::generate_large_test_data( $data_size_multiplier );
		$results['test_data_size'] = strlen( serialize( $test_data ) );

		$before_cache = memory_get_usage( true );

		// Cache the test data
		for ( $i = 0; $i < 100; $i++ ) {
			$cache_key = "memory_test_{$i}";
			PerformanceCache::set_cached(
				$cache_key,
				PerformanceCache::CACHE_GROUP_METRICS,
				$test_data
			);
		}

		$after_cache = memory_get_usage( true );
		$results['cache_memory_usage'] = $after_cache - $before_cache;
		$results['final_memory'] = memory_get_usage( true );
		$results['final_peak'] = memory_get_peak_usage( true );

		// Calculate efficiency (lower is better)
		if ( $results['test_data_size'] > 0 ) {
			$results['memory_efficiency'] = (float) ( $results['cache_memory_usage'] / $results['test_data_size'] );
		}

		// Clean up
		for ( $i = 0; $i < 100; $i++ ) {
			$cache_key = "memory_test_{$i}";
			PerformanceCache::delete_cached( $cache_key, PerformanceCache::CACHE_GROUP_METRICS );
		}

		return $results;
	}

	/**
	 * Get benchmark history
	 *
	 * @param int $limit Number of results to retrieve
	 * @return array Historical benchmark results
	 */
	public static function get_benchmark_history( int $limit = 10 ): array {
		$results = get_option( self::OPTION_BENCHMARK_RESULTS, [] );
		
		// Sort by timestamp descending
		usort( $results, function( $a, $b ) {
			return strtotime( $b['test_info']['timestamp'] ) - strtotime( $a['test_info']['timestamp'] );
		});

		return array_slice( $results, 0, $limit );
	}

	/**
	 * Clear benchmark history
	 *
	 * @return bool Success status
	 */
	public static function clear_benchmark_history(): bool {
		return delete_option( self::OPTION_BENCHMARK_RESULTS );
	}

	/**
	 * Generate performance report
	 *
	 * @return array Performance report
	 */
	public static function generate_performance_report(): array {
		$cache_stats = PerformanceCache::get_cache_stats();
		$benchmark_history = self::get_benchmark_history( 5 );
		
		$report = [
			'current_cache_stats' => $cache_stats,
			'recent_benchmarks' => $benchmark_history,
			'recommendations' => self::generate_recommendations( $cache_stats, $benchmark_history ),
			'cache_health_score' => self::calculate_cache_health_score( $cache_stats ),
		];

		return $report;
	}

	/**
	 * Get default test scenarios for load testing
	 *
	 * @return array Default test scenarios
	 */
	private static function get_default_test_scenarios(): array {
		return [
			'metrics_query' => [
				'type' => 'metrics',
				'params' => [
					'client_id' => 123,
					'period_start' => date( 'Y-m-01 00:00:00' ),
					'period_end' => date( 'Y-m-t 23:59:59' ),
				],
			],
			'aggregated_query' => [
				'type' => 'aggregated',
				'params' => [
					'client_id' => 123,
					'period_start' => date( 'Y-m-01 00:00:00' ),
					'period_end' => date( 'Y-m-t 23:59:59' ),
					'kpis' => [ 'sessions', 'pageviews', 'users' ],
				],
			],
			'report_generation' => [
				'type' => 'report',
				'params' => [
					'client_id' => 123,
					'report_type' => 'monthly_summary',
				],
			],
		];
	}

	/**
	 * Run load test scenario
	 *
	 * @param string $scenario_name Scenario name
	 * @param array $scenario Scenario configuration
	 * @param int $concurrent_users Number of concurrent users
	 * @param int $requests_per_user Number of requests per user
	 * @return array Scenario results
	 */
	private static function run_load_test_scenario( string $scenario_name, array $scenario, int $concurrent_users, int $requests_per_user ): array {
		$results = [
			'scenario_name' => $scenario_name,
			'request_times' => [],
			'cache_hits' => 0,
			'cache_misses' => 0,
			'errors' => 0,
		];

		if ( $concurrent_users < 1 || $requests_per_user < 1 ) {
			$results['avg_response_time'] = 0.0;
			$results['min_response_time'] = 0.0;
			$results['max_response_time'] = 0.0;
			$results['total_requests'] = 0;
			$results['error_rate'] = 0.0;

			return $results;
		}

		for ( $user = 0; $user < $concurrent_users; $user++ ) {
			for ( $request = 0; $request < $requests_per_user; $request++ ) {
				$start_time = microtime( true );
				
				try {
					// Simulate the request based on scenario type
					switch ( $scenario['type'] ) {
						case 'metrics':
							$cache_key = PerformanceCache::generate_metrics_key( $scenario['params'] );
							$data = PerformanceCache::get_cached(
								$cache_key,
								PerformanceCache::CACHE_GROUP_METRICS,
								function() use ( $scenario ) {
									return MetricsCache::get_metrics( $scenario['params'] );
								}
							);
							break;
							
						case 'aggregated':
							$cache_key = PerformanceCache::generate_metrics_key( $scenario['params'] );
							$data = PerformanceCache::get_cached(
								$cache_key,
								PerformanceCache::CACHE_GROUP_AGGREGATED,
								function() use ( $scenario ) {
									return MetricsAggregator::get_aggregated_metrics(
										$scenario['params']['client_id'],
										$scenario['params']['period_start'],
										$scenario['params']['period_end'],
										$scenario['params']['kpis'] ?? []
									);
								}
							);
							break;
							
						case 'report':
							$cache_key = PerformanceCache::generate_report_key(
								$scenario['params']['client_id'],
								$scenario['params']['report_type']
							);
							$data = PerformanceCache::get_cached(
								$cache_key,
								PerformanceCache::CACHE_GROUP_REPORTS,
								function() use ( $scenario ) {
									// Simulate report generation
									return [ 'report_data' => 'simulated_data' ];
								}
							);
							break;
					}
					
					// Simulate cache hit/miss detection (simplified)
					if ( $user > 0 || $request > 0 ) {
						$results['cache_hits']++;
					} else {
						$results['cache_misses']++;
					}
					
				} catch ( Exception $e ) {
					$results['errors']++;
				}
				
				$end_time = microtime( true );
				$results['request_times'][] = $end_time - $start_time;
			}
		}

		// Calculate scenario statistics
		$results['total_requests'] = count( $results['request_times'] );

		if ( 0 === $results['total_requests'] ) {
			$results['avg_response_time'] = 0.0;
			$results['min_response_time'] = 0.0;
			$results['max_response_time'] = 0.0;
			$results['error_rate'] = 0.0;

			return $results;
		}

		$results['avg_response_time'] = array_sum( $results['request_times'] ) / $results['total_requests'];
		$results['min_response_time'] = min( $results['request_times'] );
		$results['max_response_time'] = max( $results['request_times'] );
		$results['error_rate'] = ( $results['errors'] / $results['total_requests'] ) * 100;

		return $results;
	}

	/**
	 * Calculate overall statistics from scenario results
	 *
	 * @param array $scenarios Scenario results
	 * @return array Overall statistics
	 */
	private static function calculate_overall_stats( array $scenarios ): array {
		$all_times = [];
		$total_requests = 0;
		$total_errors = 0;
		$total_cache_hits = 0;
		$total_cache_misses = 0;

		foreach ( $scenarios as $scenario ) {
			$all_times = array_merge( $all_times, $scenario['request_times'] );
			$total_requests += $scenario['total_requests'];
			$total_errors += $scenario['errors'];
			$total_cache_hits += $scenario['cache_hits'];
			$total_cache_misses += $scenario['cache_misses'];
		}

		if ( 0 === $total_requests || empty( $all_times ) ) {
			return [
				'total_requests' => 0,
				'avg_response_time' => 0.0,
				'min_response_time' => 0.0,
				'max_response_time' => 0.0,
				'error_rate' => 0.0,
				'cache_hit_ratio' => 0.0,
			];
		}

		$cache_lookups = $total_cache_hits + $total_cache_misses;
		$cache_hit_ratio = 0.0;

		if ( $cache_lookups > 0 ) {
			$cache_hit_ratio = ( $total_cache_hits / $cache_lookups ) * 100;
		}

		return [
			'total_requests' => $total_requests,
			'avg_response_time' => array_sum( $all_times ) / count( $all_times ),
			'min_response_time' => min( $all_times ),
			'max_response_time' => max( $all_times ),
			'error_rate' => ( $total_errors / $total_requests ) * 100,
			'cache_hit_ratio' => $cache_hit_ratio,
		];
	}

	/**
	 * Generate large test data for memory testing
	 *
	 * @param int $multiplier Size multiplier
	 * @return array Test data
	 */
	private static function generate_large_test_data( int $multiplier ): array {
		$base_size = 1000;
		$data = [];
		
		for ( $i = 0; $i < $base_size * $multiplier; $i++ ) {
			$data[] = [
				'id' => $i,
				'client_id' => rand( 1, 100 ),
				'source' => 'google_analytics_4',
				'metric' => 'sessions',
				'value' => rand( 100, 10000 ),
				'period_start' => date( 'Y-m-d H:i:s', time() - rand( 0, 86400 * 30 ) ),
				'period_end' => date( 'Y-m-d H:i:s', time() ),
				'meta' => [
					'device' => [ 'desktop', 'mobile', 'tablet' ][ rand( 0, 2 ) ],
					'region' => 'Italy',
					'additional_data' => str_repeat( 'x', 100 ),
				],
			];
		}
		
		return $data;
	}

	/**
	 * Store benchmark results
	 *
	 * @param array $results Benchmark results
	 * @return bool Success status
	 */
	private static function store_benchmark_results( array $results ): bool {
		$stored_results = get_option( self::OPTION_BENCHMARK_RESULTS, [] );
		
		// Keep only last 50 results to prevent option table bloat
		if ( count( $stored_results ) >= 50 ) {
			$stored_results = array_slice( $stored_results, -25, 25, true );
		}

		$stored_results[] = $results;
		
		return update_option( self::OPTION_BENCHMARK_RESULTS, $stored_results );
	}

	/**
	 * Generate performance recommendations
	 *
	 * @param array $cache_stats Cache statistics
	 * @param array $benchmark_history Benchmark history
	 * @return array Recommendations
	 */
	private static function generate_recommendations( array $cache_stats, array $benchmark_history ): array {
		$recommendations = [];

		// Check hit ratio
		if ( $cache_stats['hit_ratio'] < 50 ) {
			$recommendations[] = [
				'type' => 'warning',
				'message' => 'Cache hit ratio is below 50%. Consider increasing cache TTL or reviewing cache invalidation strategy.',
			];
		}

		// Check for performance trends
		if ( count( $benchmark_history ) >= 2 ) {
			$latest = $benchmark_history[0];
			$previous = $benchmark_history[1];
			
			if ( $latest['performance_improvement'] < $previous['performance_improvement'] ) {
				$recommendations[] = [
					'type' => 'info',
					'message' => 'Performance improvement has decreased. Monitor cache configuration and invalidation patterns.',
				];
			}
		}

		// General recommendations
		$recommendations[] = [
			'type' => 'tip',
			'message' => 'Regularly monitor cache statistics and adjust TTL values based on data update frequency.',
		];

		return $recommendations;
	}

	/**
	 * Calculate cache health score
	 *
	 * @param array $cache_stats Cache statistics
	 * @return int Health score (0-100)
	 */
	private static function calculate_cache_health_score( array $cache_stats ): int {
		$score = 0;

		// Hit ratio (40% of score)
		$hit_ratio_score = min( 40, ( $cache_stats['hit_ratio'] / 100 ) * 40 );
		$score += $hit_ratio_score;

		// Request volume (30% of score)
		$volume_score = min( 30, ( $cache_stats['total_requests'] / 1000 ) * 30 );
		$score += $volume_score;

		// Performance improvement (30% of score)
		if ( $cache_stats['performance_improvement'] > 0 ) {
			$performance_score = min( 30, ( $cache_stats['performance_improvement'] / 100 ) * 30 );
			$score += $performance_score;
		}

		return (int) round( $score );
	}
}
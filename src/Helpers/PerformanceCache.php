<?php
/**
 * Performance Cache Helper
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Performance Cache class
 * 
 * This class provides a comprehensive caching layer with WordPress transients
 * and object cache, including invalidation mechanisms and performance monitoring.
 */
class PerformanceCache {

	/**
	 * Cache group for metrics queries
	 */
	public const CACHE_GROUP_METRICS = 'fp_dms_metrics';

	/**
	 * Cache group for reports
	 */
	public const CACHE_GROUP_REPORTS = 'fp_dms_reports';

	/**
	 * Cache group for aggregated data
	 */
	public const CACHE_GROUP_AGGREGATED = 'fp_dms_aggregated';

	/**
	 * Default cache TTL (15 minutes)
	 */
	public const DEFAULT_TTL = 900;

	/**
	 * Short cache TTL (5 minutes)
	 */
	public const SHORT_TTL = 300;

	/**
	 * Long cache TTL (1 hour)
	 */
	public const LONG_TTL = 3600;

	/**
	 * Cache settings option name
	 */
	private const OPTION_CACHE_SETTINGS = 'fp_digital_marketing_cache_settings';

	/**
	 * Benchmark data option name
	 */
	private const OPTION_BENCHMARK_DATA = 'fp_digital_marketing_benchmark_data';

	/**
	 * Get cache settings
	 *
	 * @return array Cache configuration
	 */
	public static function get_cache_settings(): array {
		$defaults = [
			'enabled' => true,
			'use_object_cache' => true,
			'use_transients' => true,
			'default_ttl' => self::DEFAULT_TTL,
			'metrics_ttl' => self::DEFAULT_TTL,
			'reports_ttl' => self::LONG_TTL,
			'aggregated_ttl' => self::SHORT_TTL,
			'auto_invalidate' => true,
			'benchmark_enabled' => true,
		];

		$settings = get_option( self::OPTION_CACHE_SETTINGS, [] );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update cache settings
	 *
	 * @param array $settings Cache settings
	 * @return bool Success status
	 */
	public static function update_cache_settings( array $settings ): bool {
		$current = self::get_cache_settings();
		$updated = wp_parse_args( $settings, $current );
		
		return update_option( self::OPTION_CACHE_SETTINGS, $updated );
	}

	/**
	 * Check if caching is enabled
	 *
	 * @return bool True if caching is enabled
	 */
	public static function is_cache_enabled(): bool {
		$settings = self::get_cache_settings();
		return (bool) $settings['enabled'];
	}

	/**
	 * Get cached data with performance monitoring
	 *
	 * @param string $key Cache key
	 * @param string $group Cache group
	 * @param callable|null $callback Callback to generate data if not cached
	 * @param int|null $ttl Time to live in seconds
	 * @return mixed Cached or generated data
	 */
	public static function get_cached( string $key, string $group, ?callable $callback = null, ?int $ttl = null ) {
		if ( ! self::is_cache_enabled() ) {
			return $callback ? $callback() : null;
		}

		$settings = self::get_cache_settings();
		$ttl = $ttl ?? $settings['default_ttl'];

		// Start performance monitoring
		$start_time = microtime( true );
		$cache_hit = false;

		// Try object cache first
		$cached_data = null;
		if ( $settings['use_object_cache'] ) {
			$cached_data = wp_cache_get( $key, $group );
			if ( $cached_data !== false ) {
				$cache_hit = true;
			}
		}

		// Fallback to transients if object cache failed
		if ( $cached_data === false && $settings['use_transients'] ) {
			$transient_key = self::get_transient_key( $key, $group );
			$cached_data = get_transient( $transient_key );
			if ( $cached_data !== false ) {
				$cache_hit = true;
			}
		}

		// Generate data if not found in cache
		if ( $cached_data === false && $callback ) {
			$cached_data = $callback();
			
			// Store in cache
			self::set_cached( $key, $group, $cached_data, $ttl );
		}

		// Record performance metrics
		$end_time = microtime( true );
		self::record_performance_metric( $key, $group, $cache_hit, $end_time - $start_time );

		return $cached_data;
	}

	/**
	 * Set cached data
	 *
	 * @param string $key Cache key
	 * @param string $group Cache group
	 * @param mixed $data Data to cache
	 * @param int|null $ttl Time to live in seconds
	 * @return bool Success status
	 */
	public static function set_cached( string $key, string $group, $data, ?int $ttl = null ): bool {
		if ( ! self::is_cache_enabled() ) {
			return false;
		}

		$settings = self::get_cache_settings();
		$ttl = $ttl ?? $settings['default_ttl'];

		$success = true;

		// Store in object cache
		if ( $settings['use_object_cache'] ) {
			$success = wp_cache_set( $key, $data, $group, $ttl ) && $success;
		}

		// Store in transients
		if ( $settings['use_transients'] ) {
			$transient_key = self::get_transient_key( $key, $group );
			$success = set_transient( $transient_key, $data, $ttl ) && $success;
		}

		return $success;
	}

	/**
	 * Delete cached data
	 *
	 * @param string $key Cache key
	 * @param string $group Cache group
	 * @return bool Success status
	 */
	public static function delete_cached( string $key, string $group ): bool {
		$success = true;

		// Delete from object cache
		$success = wp_cache_delete( $key, $group ) && $success;

		// Delete from transients
		$transient_key = self::get_transient_key( $key, $group );
		$success = delete_transient( $transient_key ) && $success;

		return $success;
	}

	/**
	 * Invalidate cache by group
	 *
	 * @param string $group Cache group to invalidate
	 * @return bool Success status
	 */
	public static function invalidate_group( string $group ): bool {
		global $wpdb;

		$success = true;

		// For object cache, we'll use a group version increment
		$version_key = "cache_version_{$group}";
		$current_version = wp_cache_get( $version_key, 'cache_versions' );
		$new_version = $current_version ? $current_version + 1 : 1;
		wp_cache_set( $version_key, $new_version, 'cache_versions' );

		// For transients, delete all transients with the group prefix
		$transient_prefix = "fp_dms_{$group}_";
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . $transient_prefix . '%'
			)
		);

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient );
			delete_transient( $key );
		}

		return $success;
	}

	/**
	 * Invalidate all caches
	 *
	 * @return bool Success status
	 */
	public static function invalidate_all(): bool {
		$groups = [
			self::CACHE_GROUP_METRICS,
			self::CACHE_GROUP_REPORTS,
			self::CACHE_GROUP_AGGREGATED,
		];

		$success = true;
		foreach ( $groups as $group ) {
			$success = self::invalidate_group( $group ) && $success;
		}

		return $success;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics
	 */
	public static function get_cache_stats(): array {
		$benchmark_data = get_option( self::OPTION_BENCHMARK_DATA, [] );
		
		$stats = [
			'total_requests' => 0,
			'cache_hits' => 0,
			'cache_misses' => 0,
			'hit_ratio' => 0,
			'avg_query_time' => 0,
			'avg_cached_time' => 0,
			'performance_improvement' => 0,
			'groups' => [],
		];

		if ( empty( $benchmark_data ) ) {
			return $stats;
		}

		foreach ( $benchmark_data as $entry ) {
			$stats['total_requests']++;
			
			if ( $entry['cache_hit'] ) {
				$stats['cache_hits']++;
			} else {
				$stats['cache_misses']++;
			}

			$group = $entry['group'];
			if ( ! isset( $stats['groups'][ $group ] ) ) {
				$stats['groups'][ $group ] = [
					'requests' => 0,
					'hits' => 0,
					'avg_time' => 0,
				];
			}

			$stats['groups'][ $group ]['requests']++;
			if ( $entry['cache_hit'] ) {
				$stats['groups'][ $group ]['hits']++;
			}
		}

		// Calculate ratios and averages
		if ( $stats['total_requests'] > 0 ) {
			$stats['hit_ratio'] = ( $stats['cache_hits'] / $stats['total_requests'] ) * 100;
		}

		return $stats;
	}

	/**
	 * Clear cache statistics
	 *
	 * @return bool Success status
	 */
	public static function clear_stats(): bool {
		return delete_option( self::OPTION_BENCHMARK_DATA );
	}

	/**
	 * Generate cache key for metrics
	 *
	 * @param array $params Query parameters
	 * @return string Cache key
	 */
	public static function generate_metrics_key( array $params ): string {
		ksort( $params );
		return 'metrics_' . md5( serialize( $params ) );
	}

	/**
	 * Generate cache key for reports
	 *
	 * @param int $client_id Client ID
	 * @param string $report_type Report type
	 * @param array $params Additional parameters
	 * @return string Cache key
	 */
	public static function generate_report_key( int $client_id, string $report_type, array $params = [] ): string {
		$key_data = [
			'client_id' => $client_id,
			'type' => $report_type,
			'params' => $params,
		];
		ksort( $key_data );
		return 'report_' . md5( serialize( $key_data ) );
	}

	/**
	 * Get transient key with group prefix
	 *
	 * @param string $key Cache key
	 * @param string $group Cache group
	 * @return string Transient key
	 */
	private static function get_transient_key( string $key, string $group ): string {
		return "fp_dms_{$group}_{$key}";
	}

	/**
	 * Record performance metric
	 *
	 * @param string $key Cache key
	 * @param string $group Cache group
	 * @param bool $cache_hit Whether it was a cache hit
	 * @param float $execution_time Execution time in seconds
	 * @return void
	 */
	private static function record_performance_metric( string $key, string $group, bool $cache_hit, float $execution_time ): void {
		$settings = self::get_cache_settings();
		if ( ! $settings['benchmark_enabled'] ) {
			return;
		}

		$benchmark_data = get_option( self::OPTION_BENCHMARK_DATA, [] );
		
		// Keep only last 1000 entries to prevent option table bloat
		if ( count( $benchmark_data ) >= 1000 ) {
			$benchmark_data = array_slice( $benchmark_data, -500, 500, true );
		}

		$benchmark_data[] = [
			'timestamp' => time(),
			'key' => $key,
			'group' => $group,
			'cache_hit' => $cache_hit,
			'execution_time' => $execution_time,
		];

		update_option( self::OPTION_BENCHMARK_DATA, $benchmark_data );
	}
}
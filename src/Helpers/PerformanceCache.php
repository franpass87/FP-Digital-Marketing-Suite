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
         * Cache index option name used for pattern lookups.
         */
        private const OPTION_CACHE_INDEX = 'fp_digital_marketing_cache_index';

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
         * Wrapper for get_cached to maintain backwards compatibility.
         *
         * @param string        $key      Cache key
         * @param string        $group    Cache group
         * @param callable|null $callback Callback to generate data if not cached
         * @param int|null      $ttl      Time to live in seconds
         * @return mixed Cached or generated data
         */
        public static function get( string $key, string $group, ?callable $callback = null, ?int $ttl = null ) {
                return self::get_cached( $key, $group, $callback, $ttl );
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
               $cached_data = false;
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
                $ttl = (int) max( 0, $ttl );

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

                if ( $settings['use_object_cache'] || $settings['use_transients'] ) {
                        self::register_cache_key( $group, $key, $ttl );
                }

                return $success;
        }

        /**
         * Wrapper for set_cached to maintain backwards compatibility.
         *
         * @param string   $key   Cache key
         * @param string   $group Cache group
         * @param mixed    $data  Data to cache
         * @param int|null $ttl   Time to live in seconds
         * @return bool Success status
         */
        public static function set( string $key, string $group, $data, ?int $ttl = null ): bool {
                return self::set_cached( $key, $group, $data, $ttl );
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

                self::unregister_cache_key( $group, $key );

                return $success;
        }

        /**
         * Store cached data using a readable pattern-based key.
         *
         * @param string   $pattern Readable cache key or pattern without the group prefix.
         * @param string   $group   Cache group name.
         * @param mixed    $data    Data to cache.
         * @param int|null $ttl     Time to live in seconds.
         * @return bool Success status
         */
        public static function set_cache_by_pattern( string $pattern, string $group, $data, ?int $ttl = null ): bool {
                $key = self::sanitize_cache_key( $pattern );

                if ( '' === $key ) {
                        $key = 'cache_' . md5( $pattern );
                }

                return self::set_cached( $key, $group, $data, $ttl );
        }

        /**
         * Retrieve cached data using a pattern.
         *
         * When wildcards are provided the method returns an associative array of key/value pairs
         * matching the pattern. Without wildcards it behaves like {@see self::get_cached()}.
         *
         * @param string        $pattern  Cache key or wildcard pattern
         * @param string        $group    Cache group name
         * @param callable|null $callback Optional callback for cache misses (exact keys only)
         * @param int|null      $ttl      Optional TTL for callback-generated values
         * @return mixed Cached value, array of matches, or null when nothing is found
         */
        public static function get_cache_by_pattern( string $pattern, string $group, ?callable $callback = null, ?int $ttl = null ) {
                list( $normalized_pattern, $normalized_group ) = self::normalize_group_and_pattern( $pattern, $group );

                if ( '' === $normalized_pattern ) {
                        return $callback ? $callback() : null;
                }

                $has_wildcards = self::contains_wildcards( $normalized_pattern );

                if ( ! $has_wildcards ) {
                        $key = self::sanitize_cache_key( $normalized_pattern );
                        if ( '' === $key ) {
                                return $callback ? $callback() : null;
                        }

                        return self::get_cached( $key, $normalized_group, $callback, $ttl );
                }

                $matched_keys = self::find_keys_by_pattern( $normalized_group, $normalized_pattern );

                if ( empty( $matched_keys ) ) {
                        return [];
                }

                $results = [];
                foreach ( $matched_keys as $matched_key ) {
                        $value = wp_cache_get( $matched_key, $normalized_group );
                        if ( false === $value ) {
                                $value = get_transient( self::get_transient_key( $matched_key, $normalized_group ) );
                        }

                        if ( false !== $value ) {
                                $results[ $matched_key ] = $value;
                        }
                }

                return $results;
        }

        /**
         * Clear cache entries matching a wildcard pattern.
         *
         * @param string      $pattern Pattern supporting `*` and `?` wildcards
         * @param string|null $group   Cache group name. When omitted the group will be inferred when possible.
         * @return void
         */
        public static function clear_cache_by_pattern( string $pattern, ?string $group = null ): void {
                list( $normalized_pattern, $normalized_group ) = self::normalize_group_and_pattern( $pattern, $group );

                if ( '' === $normalized_pattern ) {
                        return;
                }

                $normalized_group = $normalized_group ?? self::infer_group_from_pattern( $normalized_pattern );

                if ( empty( $normalized_group ) ) {
                        return;
                }

                $matched_keys = self::find_keys_by_pattern( $normalized_group, $normalized_pattern );

                if ( empty( $matched_keys ) ) {
                        self::bump_cache_version( $normalized_group );
                        return;
                }

                foreach ( $matched_keys as $matched_key ) {
                        self::delete_cached( $matched_key, $normalized_group );
                }

                self::bump_cache_version( $normalized_group );
        }

	/**
	 * Invalidate cache by group
	 *
	 * @param string $group Cache group to invalidate
	 * @return bool Success status
	 */
        public static function invalidate_group( string $group ): bool {
                self::clear_cache_by_pattern( '*', $group );

                return true;
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

                $prefix_parts = [];

                $client_component = self::normalize_key_component( $params['client_id'] ?? 'global' );
                if ( '' === $client_component ) {
                        $client_component = 'global';
                }
                $prefix_parts[] = 'client_' . $client_component;

                if ( array_key_exists( 'method', $params ) ) {
                        $method_component = self::normalize_key_component( $params['method'] );
                        if ( '' === $method_component ) {
                                $method_component = 'default';
                        }
                        $prefix_parts[] = 'method_' . $method_component;
                }

                if ( array_key_exists( 'segment', $params ) ) {
                        $segment_component = self::normalize_key_component( $params['segment'] );
                        if ( '' === $segment_component ) {
                                $segment_component = 'all';
                        }
                        $prefix_parts[] = 'segment_' . $segment_component;
                }

                $prefix = implode( '_', $prefix_parts );

                return sprintf(
                        'metrics_%s_%s',
                        $prefix,
                        md5( serialize( $params ) )
                );
        }

        /**
         * Normalize a value for usage in cache key components.
         *
         * @param mixed $value Value to normalize
         * @return string Normalized value limited to alphanumeric characters
         */
        private static function normalize_key_component( $value ): string {
                if ( is_bool( $value ) ) {
                        $value = $value ? 'true' : 'false';
                } elseif ( is_array( $value ) || is_object( $value ) ) {
                        $encoded = json_encode( $value );
                        $value = false !== $encoded ? $encoded : '';
                }

                $value = strtolower( (string) $value );
                $value = preg_replace( '/[^a-z0-9]+/', '_', $value );

                return trim( (string) $value, '_' );
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
                $client_component = self::normalize_key_component( $client_id );
                if ( '' === $client_component ) {
                        $client_component = 'global';
                }

                $type_component = self::normalize_key_component( $report_type );
                if ( '' === $type_component ) {
                        $type_component = 'general';
                }

                $base_key = sprintf(
                        'report_client_%s_type_%s',
                        $client_component,
                        $type_component
                );

                if ( empty( $params ) ) {
                        return $base_key;
                }

                ksort( $params );

                return $base_key . '_' . md5( serialize( $params ) );
        }

        /**
         * Convert a wildcard pattern into a SQL LIKE expression.
         *
         * @param string $pattern Wildcard pattern supporting `*` and `?` tokens
         * @return string SQL LIKE compatible pattern
         */
        private static function wildcard_to_like( string $pattern ): string {
                return str_replace( [ '*', '?' ], [ '%', '_' ], $pattern );
        }

        /**
         * Normalize the pattern/group combination accounting for swapped parameters.
         *
         * @param string      $pattern Raw pattern or group identifier.
         * @param string|null $group   Cache group or pattern depending on call order.
         * @return array{0:string,1:?string} Normalized pattern and group.
         */
        private static function normalize_group_and_pattern( string $pattern, ?string $group ): array {
                $pattern_to_use = $pattern;
                $group_to_use = $group;

                if ( null !== $group && ! self::contains_wildcards( (string) $pattern ) && self::contains_wildcards( (string) $group ) ) {
                        $pattern_to_use = $group;
                        $group_to_use = $pattern;
                }

                return [ self::normalize_pattern( $pattern_to_use ), $group_to_use ];
        }

        /**
         * Determine if a pattern contains wildcard tokens.
         *
         * @param string $pattern Pattern string.
         * @return bool Whether wildcards are present.
         */
        private static function contains_wildcards( string $pattern ): bool {
                return strpbrk( $pattern, '*?' ) !== false;
        }

        /**
         * Sanitize cache keys used for storage.
         *
         * @param string $key Raw cache key.
         * @return string Sanitized key.
         */
        private static function sanitize_cache_key( string $key ): string {
                $key = trim( (string) $key );
                if ( '' === $key ) {
                        return '';
                }

                $key = preg_replace( '/\s+/', '_', $key );
                $key = preg_replace( '/[^A-Za-z0-9:_\-]+/', '_', $key );
                $key = preg_replace( '/_+/', '_', $key );

                return trim( (string) $key, '_' );
        }

        /**
         * Normalize patterns while preserving wildcard tokens.
         *
         * @param string $pattern Pattern string.
         * @return string Normalized pattern.
         */
        private static function normalize_pattern( string $pattern ): string {
                $pattern = trim( (string) $pattern );
                if ( '' === $pattern ) {
                        return '';
                }

                $pattern = preg_replace( '/\s+/', '_', $pattern );
                $pattern = preg_replace( '/[^A-Za-z0-9:_\-\*\?]+/', '_', $pattern );
                $pattern = preg_replace( '/_+/', '_', $pattern );

                return trim( (string) $pattern, '_' );
        }

        /**
         * Locate cache keys that match a pattern within a group.
         *
         * @param string $group   Cache group name.
         * @param string $pattern Normalized pattern string.
         * @return array<int,string> Matching cache keys.
         */
        private static function find_keys_by_pattern( string $group, string $pattern ): array {
                $keys = [];

                $index = self::load_cache_index();
                if ( isset( $index[ $group ] ) && is_array( $index[ $group ] ) ) {
                        foreach ( array_keys( $index[ $group ] ) as $cached_key ) {
                                if ( self::wildcard_match( $pattern, (string) $cached_key ) ) {
                                        $keys[ $cached_key ] = true;
                                }
                        }
                }

                foreach ( self::get_matching_transient_keys( $group, $pattern ) as $transient_key ) {
                        $keys[ $transient_key ] = true;
                }

                return array_keys( $keys );
        }

        /**
         * Retrieve transient identifiers matching the provided pattern.
         *
         * @param string $group   Cache group name.
         * @param string $pattern Normalized pattern string.
         * @return array<int,string> Matching cache keys (without prefix).
         */
        private static function get_matching_transient_keys( string $group, string $pattern ): array {
                global $wpdb;

                if ( '' === $group ) {
                        return [];
                }

                $prefixed_pattern = self::get_prefixed_pattern( $group, $pattern );
                $option_patterns = [
                        '_transient_' . $prefixed_pattern,
                        '_transient_timeout_' . $prefixed_pattern,
                        '_site_transient_' . $prefixed_pattern,
                        '_site_transient_timeout_' . $prefixed_pattern,
                ];

                $keys = [];
                foreach ( array_unique( $option_patterns ) as $option_pattern ) {
                        $option_names = [];

                        if (
                                is_object( $wpdb )
                                && method_exists( $wpdb, 'prepare' )
                                && method_exists( $wpdb, 'get_col' )
                                && isset( $wpdb->options )
                        ) {
                                $like         = self::wildcard_to_like( $option_pattern );
                                $option_names = (array) $wpdb->get_col(
                                        $wpdb->prepare(
                                                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                                                $like
                                        )
                                );
                        } else {
                                global $wp_options;

                                if ( isset( $wp_options ) && is_array( $wp_options ) ) {
                                        foreach ( array_keys( $wp_options ) as $option_name ) {
                                                if ( self::wildcard_match( $option_pattern, (string) $option_name ) ) {
                                                        $option_names[] = (string) $option_name;
                                                }
                                        }
                                }
                        }

                        foreach ( $option_names as $option_name ) {
                                $extracted = self::extract_transient_key( (string) $option_name, $group );
                                if ( null === $extracted ) {
                                        continue;
                                }

                                if ( self::wildcard_match( $pattern, $extracted ) ) {
                                        $keys[] = $extracted;
                                }
                        }
                }

                return array_values( array_unique( $keys ) );
        }

        /**
         * Build the transient pattern prefix for a group.
         *
         * @param string $group   Cache group name.
         * @param string $pattern Pattern string.
         * @return string Prefixed pattern for transient lookups.
         */
        private static function get_prefixed_pattern( string $group, string $pattern ): string {
                $prefix = 'fp_dms_' . $group . '_';

                if ( strpos( $pattern, $prefix ) === 0 ) {
                        return $pattern;
                }

                return $prefix . $pattern;
        }

        /**
         * Extract a cache key from an option entry.
         *
         * @param string $option_name Raw option name.
         * @param string $group       Cache group name.
         * @return string|null Cache key without prefix or null when not applicable.
         */
        private static function extract_transient_key( string $option_name, string $group ): ?string {
                $prefixes = [
                        '_transient_',
                        '_transient_timeout_',
                        '_site_transient_',
                        '_site_transient_timeout_',
                ];

                foreach ( $prefixes as $prefix ) {
                        if ( strpos( $option_name, $prefix ) === 0 ) {
                                $transient_name = substr( $option_name, strlen( $prefix ) );
                                $expected_prefix = 'fp_dms_' . $group . '_';

                                if ( strpos( $transient_name, $expected_prefix ) === 0 ) {
                                        return substr( $transient_name, strlen( $expected_prefix ) );
                                }

                                return null;
                        }
                }

                return null;
        }

        /**
         * Perform wildcard matching using regular expressions (case-insensitive).
         *
         * @param string $pattern Pattern string.
         * @param string $value   Value to test.
         * @return bool True when the value matches the pattern.
         */
        private static function wildcard_match( string $pattern, string $value ): bool {
                $regex = '/^' . str_replace( [ '\\*', '\\?' ], [ '.*', '.' ], preg_quote( strtolower( $pattern ), '/' ) ) . '$/';

                return (bool) preg_match( $regex, strtolower( $value ) );
        }

        /**
         * Load the cache index used for pattern lookups.
         *
         * @param bool $cleanup Whether to remove expired entries.
         * @return array<string,array<string,int>> Cache index grouped by cache group.
         */
        private static function load_cache_index( bool $cleanup = true ): array {
                $index = get_option( self::OPTION_CACHE_INDEX, [] );
                if ( ! is_array( $index ) ) {
                        $index = [];
                }

                if ( ! $cleanup ) {
                        return $index;
                }

                $changed = false;
                $now = time();

                foreach ( $index as $group => $keys ) {
                        if ( ! is_array( $keys ) ) {
                                unset( $index[ $group ] );
                                $changed = true;
                                continue;
                        }

                        foreach ( $keys as $key => $expires_at ) {
                                if ( is_int( $expires_at ) && $expires_at > 0 && $expires_at <= $now ) {
                                        unset( $index[ $group ][ $key ] );
                                        $changed = true;
                                }
                        }

                        if ( empty( $index[ $group ] ) ) {
                                unset( $index[ $group ] );
                                $changed = true;
                        }
                }

                if ( $changed ) {
                        self::save_cache_index( $index );
                }

                return $index;
        }

        /**
         * Persist the cache index.
         *
         * @param array<string,array<string,int>> $index Cache index data.
         * @return void
         */
        private static function save_cache_index( array $index ): void {
                update_option( self::OPTION_CACHE_INDEX, $index, false );
        }

        /**
         * Register a cache key for pattern-based operations.
         *
         * @param string $group Cache group name.
         * @param string $key   Cache key.
         * @param int    $ttl   Time to live in seconds.
         * @return void
         */
        private static function register_cache_key( string $group, string $key, int $ttl ): void {
                if ( '' === $group || '' === $key ) {
                        return;
                }

                $index = self::load_cache_index();

                if ( ! isset( $index[ $group ] ) || ! is_array( $index[ $group ] ) ) {
                        $index[ $group ] = [];
                }

                $expiration = $ttl > 0 ? time() + $ttl : 0;
                $index[ $group ][ $key ] = $expiration;

                if ( count( $index[ $group ] ) > 500 ) {
                        $index[ $group ] = array_slice( $index[ $group ], -500, null, true );
                }

                self::save_cache_index( $index );
        }

        /**
         * Unregister a cache key from the pattern index.
         *
         * @param string $group Cache group name.
         * @param string $key   Cache key.
         * @return void
         */
        private static function unregister_cache_key( string $group, string $key ): void {
                if ( '' === $group || '' === $key ) {
                        return;
                }

                $index = self::load_cache_index();

                if ( isset( $index[ $group ][ $key ] ) ) {
                        unset( $index[ $group ][ $key ] );

                        if ( empty( $index[ $group ] ) ) {
                                unset( $index[ $group ] );
                        }

                        self::save_cache_index( $index );
                }
        }

        /**
         * Attempt to infer the cache group from a pattern when one is not provided.
         *
         * @param string $pattern Normalized pattern string.
         * @return string|null Cache group name when identifiable.
         */
        private static function infer_group_from_pattern( string $pattern ): ?string {
                $index = self::load_cache_index( false );
                $known_groups = array_unique( array_merge( [
                        self::CACHE_GROUP_METRICS,
                        self::CACHE_GROUP_REPORTS,
                        self::CACHE_GROUP_AGGREGATED,
                ], array_keys( $index ) ) );

                foreach ( $known_groups as $candidate ) {
                        $prefix = 'fp_dms_' . $candidate . '_';
                        if ( strpos( $pattern, $prefix ) === 0 ) {
                                return $candidate;
                        }
                }

                return null;
        }

        /**
         * Increment the cache version for a group to invalidate object cache entries.
         *
         * @param string $group Cache group name.
         * @return void
         */
        private static function bump_cache_version( string $group ): void {
                if ( '' === $group ) {
                        return;
                }

                $version_key = "cache_version_{$group}";
                $current_version = wp_cache_get( $version_key, 'cache_versions' );
                $new_version = $current_version ? $current_version + 1 : 1;
                wp_cache_set( $version_key, $new_version, 'cache_versions' );
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

	/**
	 * Warm up cache with critical data
	 *
	 * @param array $warmup_keys List of cache keys to warm up
	 * @return array Results of warmup operation
	 */
	public static function warmup_cache( array $warmup_keys = [] ): array {
		$settings = self::get_cache_settings();
		if ( ! $settings['enabled'] ) {
			return [ 'status' => 'disabled', 'message' => 'Cache is disabled' ];
		}

		$results = [
			'status' => 'success',
			'warmed_keys' => 0,
			'failed_keys' => 0,
			'execution_time' => 0,
			'details' => []
		];

		$start_time = microtime( true );

		// Default warmup keys if none provided
		if ( empty( $warmup_keys ) ) {
			$warmup_keys = self::get_default_warmup_keys();
		}

		foreach ( $warmup_keys as $key_config ) {
			try {
				$key = $key_config['key'] ?? '';
				$group = $key_config['group'] ?? self::CACHE_GROUP_METRICS;
				$callback = $key_config['callback'] ?? null;
				$ttl = $key_config['ttl'] ?? $settings['default_ttl'];

				if ( empty( $key ) || ! $callback ) {
					$results['failed_keys']++;
					$results['details'][] = [
						'key' => $key,
						'status' => 'failed',
						'reason' => 'Invalid key or callback'
					];
					continue;
				}

				// Check if already cached
				$cached_data = wp_cache_get( $key, $group );
				if ( $cached_data !== false ) {
					$results['details'][] = [
						'key' => $key,
						'status' => 'skipped',
						'reason' => 'Already cached'
					];
					continue;
				}

				// Execute callback and cache result
				$data = call_user_func( $callback );
				if ( $data !== null ) {
					wp_cache_set( $key, $data, $group, $ttl );
					
					// Also set transient as fallback
					if ( $settings['use_transients'] ) {
						$transient_key = self::get_transient_key( $key, $group );
						set_transient( $transient_key, $data, $ttl );
					}

					$results['warmed_keys']++;
					$results['details'][] = [
						'key' => $key,
						'status' => 'warmed',
						'data_size' => strlen( serialize( $data ) )
					];
				} else {
					$results['failed_keys']++;
					$results['details'][] = [
						'key' => $key,
						'status' => 'failed',
						'reason' => 'Callback returned null'
					];
				}

			} catch ( \Exception $e ) {
				$results['failed_keys']++;
				$results['details'][] = [
					'key' => $key ?? 'unknown',
					'status' => 'failed',
					'reason' => $e->getMessage()
				];
			}
		}

		$results['execution_time'] = microtime( true ) - $start_time;

		// Update cache statistics
		self::update_cache_statistics( $results );

		return $results;
	}

	/**
	 * Get default cache keys for warmup
	 *
	 * @return array Default warmup configuration
	 */
	private static function get_default_warmup_keys(): array {
		return [
			[
				'key' => 'dashboard_summary',
				'group' => self::CACHE_GROUP_REPORTS,
				'callback' => function() {
					// Simulate dashboard summary data
					return [
						'total_clients' => wp_count_posts( 'cliente' )->publish ?? 0,
						'active_campaigns' => 0,
						'last_sync' => current_time( 'mysql' ),
					];
				},
				'ttl' => self::LONG_TTL
			],
			[
				'key' => 'analytics_overview',
				'group' => self::CACHE_GROUP_METRICS,
				'callback' => function() {
					// Simulate analytics overview data
					return [
						'sessions' => 1000,
						'users' => 750,
						'pageviews' => 2500,
						'bounce_rate' => 45.5,
					];
				},
				'ttl' => self::DEFAULT_TTL
			],
			[
				'key' => 'seo_performance',
				'group' => self::CACHE_GROUP_METRICS,
				'callback' => function() {
					// Simulate SEO performance data
					return [
						'avg_position' => 12.3,
						'total_clicks' => 450,
						'total_impressions' => 15000,
						'ctr' => 3.0,
					];
				},
				'ttl' => self::DEFAULT_TTL
			]
		];
	}

	/**
	 * Update cache statistics
	 *
	 * @param array $results Warmup results
	 * @return void
	 */
	private static function update_cache_statistics( array $results ): void {
		$stats = get_option( 'fp_digital_marketing_cache_stats', [
			'last_warmup' => 0,
			'total_warmups' => 0,
			'total_warmed_keys' => 0,
			'total_failed_keys' => 0,
		] );

		$stats['last_warmup'] = time();
		$stats['total_warmups']++;
		$stats['total_warmed_keys'] += $results['warmed_keys'];
		$stats['total_failed_keys'] += $results['failed_keys'];

		update_option( 'fp_digital_marketing_cache_stats', $stats );
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics
	 */
	public static function get_cache_statistics(): array {
		$stats = get_option( 'fp_digital_marketing_cache_stats', [
			'last_warmup' => 0,
			'total_warmups' => 0,
			'total_warmed_keys' => 0,
			'total_failed_keys' => 0,
		] );

		// Add current cache info
		$stats['object_cache_available'] = wp_using_ext_object_cache();
		$stats['transients_count'] = self::count_plugin_transients();
		
		return $stats;
	}

	/**
	 * Count plugin-related transients
	 *
	 * @return int Number of transients
	 */
	private static function count_plugin_transients(): int {
		global $wpdb;
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_fp_dms_%'
			)
		);

		return (int) $count;
	}

	/**
	 * Schedule automatic cache warmup
	 *
	 * @return void
	 */
	public static function schedule_cache_warmup(): void {
		if ( ! wp_next_scheduled( 'fp_dms_cache_warmup' ) ) {
			wp_schedule_event( time(), 'hourly', 'fp_dms_cache_warmup' );
		}
	}

	/**
	 * Unschedule automatic cache warmup
	 *
	 * @return void
	 */
	public static function unschedule_cache_warmup(): void {
		wp_clear_scheduled_hook( 'fp_dms_cache_warmup' );
	}
}
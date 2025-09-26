<?php
/**
 * Sync Log Model for Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * SyncLog class for tracking sync operations
 */
class SyncLog {

                /**
                 * Cache group used for sync log aggregates.
                 */
        private const CACHE_GROUP = PerformanceCache::CACHE_GROUP_AGGREGATED;

                /**
                 * Cache key prefix for sync log results.
                 */
        private const CACHE_KEY_PREFIX = 'sync_logs';

                /**
                 * Wildcard pattern used when purging cached sync log data.
                 */
        private const CACHE_PATTERN = 'sync_logs_*';

		/**
		 * Cached column definitions for the sync log table.
		 *
		 * @var array<string, array<int, string>>
		 */
	private static array $table_columns_cache = [];

	/**
	 * Create a new sync log entry
	 *
	 * @param array $data Log data
	 * @return int Log ID
	 */
        public static function create( array $data ): int {
                        $table_name = self::get_table_name();

                        $log_entry = array_merge(
				[
					'sync_type'    => 'automatic',
					'status'       => 'running',
					'message'      => '',
					'started_at'   => current_time( 'mysql' ),
					'completed_at' => null,
				],
				$data
			);

		// Check if custom table exists
                if ( $table_name && self::table_exists( $table_name ) ) {
                                global $wpdb;
                                $wpdb->insert( $table_name, $log_entry );
                                $insert_id = (int) $wpdb->insert_id;
                                self::invalidate_cache();

                                return $insert_id;
                } else {
                                // Fallback to options table
                                $logs = self::get_all_logs();

                                $log_entry['id'] = self::generate_fallback_log_id();
                                $logs[]          = $log_entry;

                        // Keep only last 100 logs
                        if ( count( $logs ) > 100 ) {
                                $logs = array_slice( $logs, -100 );
                        }

                        update_option( 'fp_dms_sync_logs', $logs );
                        self::invalidate_cache();

                        return $log_entry['id'];
                }
        }

	/**
	 * Update a sync log entry
	 *
	 * @param int   $id   Log ID
	 * @param array $data Data to update
	 * @return bool Success status
	 */
	public static function update( int $id, array $data ): bool {
			$table_name = self::get_table_name();

                if ( $table_name && self::table_exists( $table_name ) ) {
                                global $wpdb;
                                $columns = self::get_sync_log_table_columns( $table_name );

			if ( empty( $columns ) ) {
				return false;
			}

				$update_data = array_intersect_key( $data, array_flip( $columns ) );
				unset( $update_data['id'] );

			if ( isset( $update_data['started_at'] ) ) {
					$update_data['started_at'] = self::normalize_datetime_for_storage( $update_data['started_at'] );
			}

			if ( array_key_exists( 'completed_at', $update_data ) ) {
					$update_data['completed_at'] = self::normalize_datetime_for_storage( $update_data['completed_at'] );
			}

			if ( empty( $update_data ) ) {
					return false;
			}

				$formats = [];
			foreach ( array_keys( $update_data ) as $column ) {
					$formats[] = self::get_column_format( $column );
			}

                                $result = $wpdb->update(
                                        $table_name,
                                        $update_data,
                                        [ 'id' => $id ],
                                        $formats,
                                        [ '%d' ]
                                );

                                if ( false !== $result ) {
                                        self::invalidate_cache();
                                        return true;
                                }

                                return false;
                }

                        $logs = self::get_all_logs();

                foreach ( $logs as &$log ) {
                        if ( $log['id'] === $id ) {
                                        $log = array_merge( $log, $data );
                                        update_option( 'fp_dms_sync_logs', $logs );
                                        self::invalidate_cache();
                                        return true;
                        }
                }

			return false;
	}

		/**
		 * Generate a pseudo-random identifier for option based log storage.
		 *
		 * The WordPress function wp_rand() is not available in the test environment,
		 * so we fall back to PHP's random_int() implementation when needed.
		 *
		 * @return int Unique identifier.
		 */
	private static function generate_fallback_log_id(): int {
			$random_int = null;

		if ( function_exists( 'wp_rand' ) ) {
				$random_int = wp_rand( 1, 1000 );
		} else {
			try {
					$random_int = random_int( 1, 1000 );
			} catch ( \Exception $exception ) {
					$random_int = mt_rand( 1, 1000 );
			}
		}

			return time() + (int) $random_int;
	}

	/**
	 * Get all sync logs
	 *
	 * @param int $limit Number of logs to return
	 * @return array Array of log entries
	 */
        public static function get_all_logs( int $limit = 50 ): array {
                $limit     = max( 1, $limit );
                $cache_key = self::build_cache_key( 'all', [ $limit ] );

                $callback = static function () use ( $limit ): array {
                        return self::get_all_logs_uncached( $limit );
                };

                if ( ! self::is_cache_available() ) {
                        return $callback();
                }

                return PerformanceCache::get_cached(
                        $cache_key,
                        self::CACHE_GROUP,
                        $callback,
                        self::get_cache_ttl()
                );
        }

	/**
	 * Get sync logs by status
	 *
	 * @param string $status Log status
	 * @param int    $limit  Number of logs to return
	 * @return array Array of filtered log entries
	 */
        public static function get_logs_by_status( string $status, int $limit = 50 ): array {
                $status    = strtolower( trim( $status ) );
                $limit     = max( 1, $limit );
                $cache_key = self::build_cache_key( 'status', [ $status, $limit ] );

                $callback = static function () use ( $status, $limit ): array {
                        $logs = self::get_all_logs( max( $limit, 100 ) );

                        $filtered = array_filter(
                                $logs,
                                static function ( $log ) use ( $status ) {
                                        return isset( $log['status'] ) && strtolower( (string) $log['status'] ) === $status;
                                }
                        );

                        return array_slice( array_values( $filtered ), 0, $limit );
                };

                if ( ! self::is_cache_available() ) {
                        return $callback();
                }

                return PerformanceCache::get_cached(
                        $cache_key,
                        self::CACHE_GROUP,
                        $callback,
                        self::get_cache_ttl()
                );
        }

	/**
	 * Get error logs
	 *
	 * @param int $limit Number of logs to return
	 * @return array Array of error log entries
	 */
	public static function get_error_logs( int $limit = 20 ): array {
		return self::get_logs_by_status( 'error', $limit );
	}

	/**
	 * Check if custom table exists
	 *
	 * @param string $table_name Table name
	 * @return bool True if table exists
	 */
	private static function table_exists( string $table_name ): bool {
		if ( '' === $table_name ) {
				return false;
		}

			global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
				return false;
		}

			$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
			return $wpdb->get_var( $query ) === $table_name;
	}

		/**
		 * Resolve the sync logs table name when the database layer is available.
		 *
		 * @return string
		 */
	private static function get_table_name(): string {
			global $wpdb;

		if ( isset( $wpdb ) && is_object( $wpdb ) && property_exists( $wpdb, 'prefix' ) ) {
				return (string) $wpdb->prefix . 'fp_dms_sync_logs';
		}

			return '';
	}

		/**
		 * Get the column list for the sync log table.
		 *
		 * @param string $table_name Table name.
		 * @return array<int, string> Column names.
		 */
	private static function get_sync_log_table_columns( string $table_name ): array {
		if ( isset( self::$table_columns_cache[ $table_name ] ) ) {
				return self::$table_columns_cache[ $table_name ];
		}

			global $wpdb;

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
				return [];
		}

			$columns = [];
			$results = $wpdb->get_results( "DESCRIBE {$table_name}", ARRAY_A );

		if ( is_array( $results ) ) {
			foreach ( $results as $column ) {
				if ( isset( $column['Field'] ) ) {
					$columns[] = $column['Field'];
				}
			}
		}

			self::$table_columns_cache[ $table_name ] = $columns;

			return $columns;
	}

		/**
		 * Map column names to wpdb formats for updates.
		 *
		 * @param string $column Column name.
		 * @return string Format string.
		 */
	private static function get_column_format( string $column ): string {
		switch ( $column ) {
			case 'records_updated':
			case 'sources_count':
			case 'errors_count':
				return '%d';
			case 'duration':
				return '%f';
			default:
				return '%s';
		}
	}

		/**
		 * Normalize a datetime value for storage in the database.
		 *
		 * @param mixed $value Datetime value.
		 * @return string|null Normalized value.
		 */
	private static function normalize_datetime_for_storage( $value ): ?string {
		if ( $value instanceof \DateTimeInterface ) {
				return $value->format( 'Y-m-d H:i:s' );
		}

		if ( null === $value ) {
				return null;
		}

		if ( is_numeric( $value ) ) {
				$timestamp = (int) $value;

			if ( $timestamp <= 0 ) {
					return null;
			}

				return gmdate( 'Y-m-d H:i:s', $timestamp );
		}

			$value = trim( (string) $value );

		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
				return null;
		}

			return $value;
	}

		/**
		 * Normalize a log entry for consistent output.
		 *
		 * @param array $log Log entry.
		 * @return array Normalized log entry.
		 */
	private static function format_log_entry( array $log ): array {
		if ( isset( $log['id'] ) ) {
				$log['id'] = (int) $log['id'];
		}

		if ( isset( $log['started_at'] ) ) {
				$log['started_at'] = self::normalize_datetime_output( $log['started_at'], false );
		}

		if ( array_key_exists( 'completed_at', $log ) ) {
				$log['completed_at'] = self::normalize_datetime_output( $log['completed_at'], true );
		}

			return $log;
	}

		/**
		 * Normalize datetime values when returning log data.
		 *
		 * @param mixed $value              Raw value.
		 * @param bool  $allow_null_default Whether to treat empty values as null.
		 * @return string|null Normalized value.
		 */
	private static function normalize_datetime_output( $value, bool $allow_null_default = true ): ?string {
		if ( $value instanceof \DateTimeInterface ) {
				$value = $value->format( 'Y-m-d H:i:s' );
		}

		if ( null === $value ) {
				return null;
		}

		if ( is_numeric( $value ) ) {
				$timestamp = (int) $value;

			if ( $timestamp <= 0 ) {
					return null;
			}

				return gmdate( 'Y-m-d H:i:s', $timestamp );
		}

			$value = (string) $value;

		if ( '' === $value ) {
				return null;
		}

		if ( $allow_null_default && '0000-00-00 00:00:00' === $value ) {
				return null;
		}

			return $value;
	}

	/**
	 * Clear old logs
	 *
	 * @param int $days Keep logs from last N days
	 * @return int Number of logs removed
	 */
	public static function cleanup_old_logs( int $days = 30 ): int {
			$table_name = self::get_table_name();

                if ( $table_name && self::table_exists( $table_name ) ) {
                                global $wpdb;
                                // Use custom table
                                $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
                                $deleted     = $wpdb->query(
                                        $wpdb->prepare(
                                                "DELETE FROM {$table_name} WHERE started_at < %s",
                                                $cutoff_date
                                        )
                                );
                        $deleted = $deleted ?: 0;
                        if ( $deleted > 0 ) {
                                self::invalidate_cache();
                        }

                        return $deleted;
                } else {
                                // Use options table fallback
                                $logs           = get_option( 'fp_dms_sync_logs', [] );
                                $cutoff_time    = strtotime( "-{$days} days" );
                                $original_count = count( $logs );

				$logs = array_filter(
					$logs,
					static function ( $log ) use ( $cutoff_time ) {
							$started_at = $log['started_at'] ?? null;
							$timestamp  = $started_at ? strtotime( (string) $started_at ) : 0;

							return $timestamp > $cutoff_time;
					}
				);

                                update_option( 'fp_dms_sync_logs', array_values( $logs ) );

                                if ( $original_count !== count( $logs ) ) {
                                        self::invalidate_cache();
                                }

                                return $original_count - count( $logs );
                }
        }

	/**
	 * Get sync statistics
	 *
	 * @param int $days Number of days to analyze
	 * @return array Statistics array
	 */
        public static function get_sync_stats( int $days = 7 ): array {
                $days      = max( 1, $days );
                $cache_key = self::build_cache_key( 'stats', [ $days ] );

                $callback = static function () use ( $days ): array {
                        $logs        = self::get_all_logs( 200 );
                        $cutoff_time = strtotime( "-{$days} days" );

                        $recent_logs = array_filter(
                                $logs,
                                static function ( $log ) use ( $cutoff_time ) {
                                        $started_at = $log['started_at'] ?? null;
                                        return $started_at && strtotime( (string) $started_at ) > $cutoff_time;
                                }
                        );

                        $stats = [
                                'total_syncs'          => count( $recent_logs ),
                                'successful_syncs'     => 0,
                                'failed_syncs'         => 0,
                                'warning_syncs'        => 0,
                                'last_sync'            => null,
                                'last_successful_sync' => null,
                                'error_rate'           => 0,
                        ];

                        foreach ( $recent_logs as $log ) {
                                $status      = strtolower( (string) ( $log['status'] ?? '' ) );
                                $started_at  = $log['started_at'] ?? null;
                                $completed_at = $log['completed_at'] ?? null;

                                switch ( $status ) {
                                        case 'success':
                                                ++$stats['successful_syncs'];
                                                if ( $completed_at && ( ! $stats['last_successful_sync'] || strtotime( (string) $completed_at ) > strtotime( (string) $stats['last_successful_sync'] ) ) ) {
                                                        $stats['last_successful_sync'] = $completed_at;
                                                }
                                                break;
                                        case 'error':
                                                ++$stats['failed_syncs'];
                                                break;
                                        case 'warning':
                                                ++$stats['warning_syncs'];
                                                break;
                                }

                                if ( $started_at && ( ! $stats['last_sync'] || strtotime( (string) $started_at ) > strtotime( (string) $stats['last_sync'] ) ) ) {
                                        $stats['last_sync'] = $started_at;
                                }
                        }

                        if ( $stats['total_syncs'] > 0 ) {
                                $stats['error_rate'] = round( ( $stats['failed_syncs'] / $stats['total_syncs'] ) * 100, 1 );
                        }

                        return $stats;
                };

                if ( ! self::is_cache_available() ) {
                        return $callback();
                }

                return PerformanceCache::get_cached(
                        $cache_key,
                        self::CACHE_GROUP,
                        $callback,
                        self::get_cache_ttl()
                );
        }

                /**
                 * Determine whether the performance cache can be used.
                 *
                 * @return bool
                 */
        private static function is_cache_available(): bool {
                return class_exists( PerformanceCache::class ) && PerformanceCache::is_cache_enabled();
        }

                /**
                 * Build a normalized cache key for sync log data.
                 *
                 * @param string $type   Cache entry type.
                 * @param array  $params Additional parameters.
                 * @return string
                 */
        private static function build_cache_key( string $type, array $params = [] ): string {
                $parts = [ self::CACHE_KEY_PREFIX, $type ];

                foreach ( $params as $param ) {
                        if ( is_scalar( $param ) || null === $param ) {
                                $parts[] = preg_replace( '/[^a-z0-9\-]+/i', '-', (string) $param );
                        } else {
                                $encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $param ) : json_encode( $param );
                                $parts[] = substr( md5( (string) $encoded ), 0, 12 );
                        }
                }

                return strtolower( implode( '_', array_filter( $parts ) ) );
        }

                /**
                 * Retrieve uncached logs from the database or fallback storage.
                 *
                 * @param int $limit Maximum number of logs to return.
                 * @return array
                 */
        private static function get_all_logs_uncached( int $limit ): array {
                $table_name = self::get_table_name();

                if ( $table_name && self::table_exists( $table_name ) ) {
                        global $wpdb;

                        $logs = $wpdb->get_results(
                                $wpdb->prepare(
                                        "SELECT * FROM {$table_name} ORDER BY started_at DESC LIMIT %d",
                                        $limit
                                ),
                                ARRAY_A
                        );

                        if ( empty( $logs ) ) {
                                return [];
                        }

                        return array_map( [ __CLASS__, 'format_log_entry' ], $logs );
                }

                $logs = get_option( 'fp_dms_sync_logs', [] );

                if ( empty( $logs ) ) {
                        return [];
                }

                usort(
                        $logs,
                        static function ( $a, $b ) {
                                $a_time = isset( $a['started_at'] ) ? strtotime( (string) $a['started_at'] ) : 0;
                                $b_time = isset( $b['started_at'] ) ? strtotime( (string) $b['started_at'] ) : 0;

                                return $b_time <=> $a_time;
                        }
                );

                return array_map( [ __CLASS__, 'format_log_entry' ], array_slice( $logs, 0, $limit ) );
        }

                /**
                 * Get cache TTL for sync log entries.
                 *
                 * @return int
                 */
        private static function get_cache_ttl(): int {
                $settings = PerformanceCache::get_cache_settings();

                return isset( $settings['aggregated_ttl'] )
                        ? (int) $settings['aggregated_ttl']
                        : PerformanceCache::SHORT_TTL;
        }

                /**
                 * Invalidate sync log caches.
                 *
                 * @return void
                 */
        private static function invalidate_cache(): void {
                if ( ! class_exists( PerformanceCache::class ) ) {
                        return;
                }

                PerformanceCache::clear_cache_by_pattern( self::CACHE_PATTERN, self::CACHE_GROUP );
        }
}

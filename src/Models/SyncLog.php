<?php
/**
 * Sync Log Model for Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

/**
 * SyncLog class for tracking sync operations
 */
class SyncLog {

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
		global $wpdb;
		
		// Try to use custom table if it exists, otherwise fall back to options
		$table_name = $wpdb->prefix . 'fp_dms_sync_logs';
		
		$log_entry = array_merge( [
			'sync_type' => 'automatic',
			'status' => 'running',
			'message' => '',
			'started_at' => current_time( 'mysql' ),
			'completed_at' => null,
		], $data );
		
		// Check if custom table exists
                if ( self::table_exists( $table_name ) ) {
                        $wpdb->insert( $table_name, $log_entry );
                        return $wpdb->insert_id;
                } else {
                        // Fallback to options table
                        $logs = self::get_all_logs();

                        $log_entry['id'] = self::generate_fallback_log_id();
                        $logs[] = $log_entry;
			
			// Keep only last 100 logs
			if ( count( $logs ) > 100 ) {
				$logs = array_slice( $logs, -100 );
			}
			
			update_option( 'fp_dms_sync_logs', $logs );
			
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
                global $wpdb;

                $table_name = isset( $wpdb ) ? $wpdb->prefix . 'fp_dms_sync_logs' : '';

                if ( $table_name && self::table_exists( $table_name ) ) {
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

                        return false !== $result;
                }

                $logs = self::get_all_logs();

                foreach ( $logs as &$log ) {
                        if ( $log['id'] === $id ) {
                                $log = array_merge( $log, $data );
                                update_option( 'fp_dms_sync_logs', $logs );
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
                global $wpdb;

                $table_name = isset( $wpdb ) ? $wpdb->prefix . 'fp_dms_sync_logs' : '';

                if ( $table_name && self::table_exists( $table_name ) ) {
                        $limit = max( 1, $limit );

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

                // Sort by started_at desc
                usort( $logs, function( $a, $b ) {
                        return strtotime( $b['started_at'] ) - strtotime( $a['started_at'] );
                } );

                return array_map( [ __CLASS__, 'format_log_entry' ], array_slice( $logs, 0, $limit ) );
        }

	/**
	 * Get sync logs by status
	 *
	 * @param string $status Log status
	 * @param int    $limit  Number of logs to return
	 * @return array Array of filtered log entries
	 */
	public static function get_logs_by_status( string $status, int $limit = 50 ): array {
		$logs = self::get_all_logs();
		
		$filtered = array_filter( $logs, function( $log ) use ( $status ) {
			return $log['status'] === $status;
		} );
		
		return array_slice( $filtered, 0, $limit );
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
                global $wpdb;

                if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
                        return false;
                }

                $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
                return $wpdb->get_var( $query ) === $table_name;
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
		global $wpdb;
		$table_name = $wpdb->prefix . 'fp_dms_sync_logs';
		
		if ( self::table_exists( $table_name ) ) {
			// Use custom table
			$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			$deleted = $wpdb->query( 
				$wpdb->prepare( 
					"DELETE FROM {$table_name} WHERE started_at < %s", 
					$cutoff_date 
				) 
			);
			return $deleted ?: 0;
		} else {
			// Use options table fallback
			$logs = self::get_all_logs();
			$cutoff_time = strtotime( "-{$days} days" );
			$original_count = count( $logs );
			
			$logs = array_filter( $logs, function( $log ) use ( $cutoff_time ) {
				return strtotime( $log['started_at'] ) > $cutoff_time;
			} );
			
			update_option( 'fp_dms_sync_logs', array_values( $logs ) );
			
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
		$logs = self::get_all_logs();
		$cutoff_time = strtotime( "-{$days} days" );
		
		$recent_logs = array_filter( $logs, function( $log ) use ( $cutoff_time ) {
			return strtotime( $log['started_at'] ) > $cutoff_time;
		} );
		
		$stats = [
			'total_syncs' => count( $recent_logs ),
			'successful_syncs' => 0,
			'failed_syncs' => 0,
			'warning_syncs' => 0,
			'last_sync' => null,
			'last_successful_sync' => null,
			'error_rate' => 0,
		];
		
		foreach ( $recent_logs as $log ) {
			switch ( $log['status'] ) {
				case 'success':
					$stats['successful_syncs']++;
					if ( ! $stats['last_successful_sync'] || strtotime( $log['completed_at'] ) > strtotime( $stats['last_successful_sync'] ) ) {
						$stats['last_successful_sync'] = $log['completed_at'];
					}
					break;
				case 'error':
					$stats['failed_syncs']++;
					break;
				case 'warning':
					$stats['warning_syncs']++;
					break;
			}
			
			if ( ! $stats['last_sync'] || strtotime( $log['started_at'] ) > strtotime( $stats['last_sync'] ) ) {
				$stats['last_sync'] = $log['started_at'];
			}
		}
		
		if ( $stats['total_syncs'] > 0 ) {
			$stats['error_rate'] = round( ( $stats['failed_syncs'] / $stats['total_syncs'] ) * 100, 1 );
		}
		
		return $stats;
	}
}

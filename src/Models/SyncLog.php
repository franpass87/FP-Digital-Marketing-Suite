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
			$log_entry['id'] = time() + wp_rand( 1, 1000 ); // Simple ID generation
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
	 * Get all sync logs
	 *
	 * @param int $limit Number of logs to return
	 * @return array Array of log entries
	 */
	public static function get_all_logs( int $limit = 50 ): array {
		$logs = get_option( 'fp_dms_sync_logs', [] );
		
		// Sort by started_at desc
		usort( $logs, function( $a, $b ) {
			return strtotime( $b['started_at'] ) - strtotime( $a['started_at'] );
		} );
		
		return array_slice( $logs, 0, $limit );
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
		
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		return $wpdb->get_var( $query ) === $table_name;
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
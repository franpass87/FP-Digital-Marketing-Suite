<?php
/**
 * Sync Engine for Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Models\SyncLog;
use FP\DigitalMarketing\Helpers\AlertEngine;

/**
 * SyncEngine class for periodic data source synchronization
 */
class SyncEngine {

	/**
	 * Cron hook name for sync operations
	 */
	private const CRON_HOOK = 'fp_dms_sync_data_sources';

	/**
	 * Default sync frequency in seconds (1 hour)
	 */
	private const DEFAULT_SYNC_FREQUENCY = 3600;

	/**
	 * Initialize sync engine
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'schedule_sync' ] );
		add_action( self::CRON_HOOK, [ self::class, 'run_sync' ] );
	}

	/**
	 * Schedule sync if not already scheduled
	 *
	 * @return void
	 */
	public static function schedule_sync(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$frequency = self::get_sync_frequency();
			
			// Schedule hourly sync (demo requirement)
			wp_schedule_event( 
				time() + $frequency, 
				'hourly', 
				self::CRON_HOOK 
			);
		}
	}

	/**
	 * Unschedule sync operations
	 *
	 * @return void
	 */
	public static function unschedule_sync(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run the main sync operation
	 *
	 * @return void
	 */
	public static function run_sync(): void {
		$sync_start = microtime( true );
		$sync_id = self::log_sync_start();
		
		try {
			// Check if sync is enabled
			if ( ! self::is_sync_enabled() ) {
				self::log_sync_complete( $sync_id, 'skipped', 'Sync is disabled in settings' );
				return;
			}

			$results = self::sync_all_data_sources();
			
			$sync_end = microtime( true );
			$duration = round( $sync_end - $sync_start, 2 );
			
			$message = sprintf(
				'Sync completed. Sources: %d, Records updated: %d, Errors: %d, Duration: %ss',
				$results['sources_count'],
				$results['records_updated'],
				$results['errors_count'],
				$duration
			);

			self::log_sync_complete( $sync_id, $results['errors_count'] > 0 ? 'warning' : 'success', $message );

			// Check alert rules and anomaly detection after successful sync
			if ( $results['errors_count'] === 0 ) {
				try {
					// Run combined monitoring (threshold alerts + anomaly detection)
					$monitoring_results = AlertEngine::check_all_monitoring();
					
					// Log monitoring results if any alerts were triggered
					if ( $monitoring_results['total_triggered'] > 0 ) {
						error_log( sprintf(
							'FP DMS Monitoring Check: %d threshold alerts, %d anomalies detected, %d total notifications sent',
							$monitoring_results['threshold_alerts']['triggered'],
							$monitoring_results['anomaly_detection']['anomalies_detected'],
							$monitoring_results['total_notifications']
						) );
					}
				} catch ( \Exception $e ) {
					error_log( 'FP DMS Monitoring Check Error: ' . $e->getMessage() );
				}
			}

		} catch ( \Exception $e ) {
			$sync_end = microtime( true );
			$duration = round( $sync_end - $sync_start, 2 );
			
			$error_message = sprintf(
				'Sync failed: %s (Duration: %ss)',
				$e->getMessage(),
				$duration
			);
			
			self::log_sync_complete( $sync_id, 'error', $error_message );
			error_log( 'FP DMS Sync Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Sync all connected data sources
	 *
	 * @return array Sync results summary
	 */
	private static function sync_all_data_sources(): array {
		$sources = DataSources::get_data_sources();
		$available_sources = DataSources::get_data_sources_by_status( 'available' );
		
		$results = [
			'sources_count' => 0,
			'records_updated' => 0,
			'errors_count' => 0,
		];

		// Get all clients with active sync
		$clients = self::get_sync_enabled_clients();

		foreach ( $clients as $client ) {
			foreach ( $available_sources as $source ) {
				try {
					$source_results = self::sync_data_source( $client->ID, $source );
					$results['sources_count']++;
					$results['records_updated'] += $source_results['records_updated'];
				} catch ( \Exception $e ) {
					$results['errors_count']++;
					error_log( sprintf(
						'FP DMS Sync Error for client %d, source %s: %s',
						$client->ID,
						$source['id'],
						$e->getMessage()
					) );
				}
			}
		}

		return $results;
	}

	/**
	 * Sync data for a specific source and client
	 *
	 * @param int   $client_id Client ID
	 * @param array $source    Data source configuration
	 * @return array Sync results for this source
	 */
	private static function sync_data_source( int $client_id, array $source ): array {
		$results = [
			'records_updated' => 0,
		];

		// For demo purposes, we'll generate sample data
		// In a real implementation, this would call the actual APIs
		switch ( $source['id'] ) {
			case 'google_analytics_4':
				$results['records_updated'] += self::sync_ga4_data( $client_id );
				break;
			case 'google_search_console':
				$results['records_updated'] += self::sync_gsc_data( $client_id );
				break;
			case 'facebook_ads':
				$results['records_updated'] += self::sync_facebook_data( $client_id );
				break;
			default:
				// For other sources, generate demo data
				$results['records_updated'] += self::sync_demo_data( $client_id, $source['id'] );
				break;
		}

		return $results;
	}

	/**
	 * Sync Google Analytics 4 data for a client
	 *
	 * @param int $client_id Client ID
	 * @return int Number of records updated
	 */
	private static function sync_ga4_data( int $client_id ): int {
		$records = 0;
		
		// Generate demo GA4 metrics for the last 30 days
		$end_date = current_time( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		
		$metrics = [
			'sessions' => rand( 1000, 5000 ),
			'users' => rand( 800, 4000 ),
			'pageviews' => rand( 2000, 10000 ),
			'bounce_rate' => round( rand( 20, 80 ) / 100, 2 ),
			'avg_session_duration' => rand( 60, 300 ),
		];

		foreach ( $metrics as $metric => $value ) {
			$existing = MetricsCache::get_metrics( [
				'client_id' => $client_id,
				'source' => 'google_analytics_4',
				'metric' => $metric,
				'period_start' => $start_date . ' 00:00:00',
				'period_end' => $end_date . ' 23:59:59',
			] );

			if ( empty( $existing ) ) {
				MetricsCache::save(
					$client_id,
					'google_analytics_4',
					$metric,
					$start_date . ' 00:00:00',
					$end_date . ' 23:59:59',
					$value,
					[ 'sync_type' => 'automatic', 'sync_timestamp' => current_time( 'mysql' ) ]
				);
				$records++;
			} else {
				// Update existing record with new value
				MetricsCache::update( $existing[0]->id, [
					'value' => (string) $value,
					'meta' => [
						'sync_type' => 'automatic',
						'sync_timestamp' => current_time( 'mysql' ),
						'updated' => true,
					],
					'fetched_at' => current_time( 'mysql' ),
				] );
				$records++;
			}
		}

		return $records;
	}

	/**
	 * Sync Google Search Console data for a client
	 *
	 * @param int $client_id Client ID
	 * @return int Number of records updated
	 */
	private static function sync_gsc_data( int $client_id ): int {
		$records = 0;
		
		$end_date = current_time( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		
		$metrics = [
			'clicks' => rand( 500, 2000 ),
			'impressions' => rand( 5000, 20000 ),
			'ctr' => round( rand( 5, 15 ) / 100, 3 ),
			'position' => round( rand( 10, 50 ), 1 ),
		];

		foreach ( $metrics as $metric => $value ) {
			MetricsCache::save(
				$client_id,
				'google_search_console',
				$metric,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$value,
				[ 'sync_type' => 'automatic', 'sync_timestamp' => current_time( 'mysql' ) ]
			);
			$records++;
		}

		return $records;
	}

	/**
	 * Sync Facebook Ads data for a client
	 *
	 * @param int $client_id Client ID
	 * @return int Number of records updated
	 */
	private static function sync_facebook_data( int $client_id ): int {
		$records = 0;
		
		$end_date = current_time( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		
		$metrics = [
			'reach' => rand( 10000, 50000 ),
			'impressions' => rand( 15000, 75000 ),
			'clicks' => rand( 300, 1500 ),
			'spend' => round( rand( 100, 1000 ), 2 ),
			'cpm' => round( rand( 5, 25 ), 2 ),
		];

		foreach ( $metrics as $metric => $value ) {
			MetricsCache::save(
				$client_id,
				'facebook_ads',
				$metric,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$value,
				[ 'sync_type' => 'automatic', 'sync_timestamp' => current_time( 'mysql' ) ]
			);
			$records++;
		}

		return $records;
	}

	/**
	 * Sync demo data for other sources
	 *
	 * @param int    $client_id Client ID
	 * @param string $source_id Source identifier
	 * @return int Number of records updated
	 */
	private static function sync_demo_data( int $client_id, string $source_id ): int {
		$end_date = current_time( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		
		// Generic demo metric
		MetricsCache::save(
			$client_id,
			$source_id,
			'demo_metric',
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59',
			rand( 100, 1000 ),
			[ 'sync_type' => 'automatic', 'sync_timestamp' => current_time( 'mysql' ) ]
		);

		return 1;
	}

	/**
	 * Get clients with sync enabled
	 *
	 * @return array Array of client post objects
	 */
	private static function get_sync_enabled_clients(): array {
		return get_posts( [
			'post_type'      => 'cliente',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => '_fp_auto_sync',
					'value'   => '1',
					'compare' => '=',
				],
			],
		] );
	}

	/**
	 * Check if sync is enabled globally
	 *
	 * @return bool True if sync is enabled
	 */
	public static function is_sync_enabled(): bool {
		$settings = get_option( 'fp_digital_marketing_sync_settings', [] );
		return ! empty( $settings['enable_sync'] );
	}

	/**
	 * Get sync frequency from settings
	 *
	 * @return int Sync frequency in seconds
	 */
	public static function get_sync_frequency(): int {
		$settings = get_option( 'fp_digital_marketing_sync_settings', [] );
		$frequency = $settings['sync_frequency'] ?? 'hourly';
		
		switch ( $frequency ) {
			case 'every_15_minutes':
				return 900;
			case 'every_30_minutes':
				return 1800;
			case 'hourly':
				return 3600;
			case 'twice_daily':
				return 43200;
			case 'daily':
				return 86400;
			default:
				return self::DEFAULT_SYNC_FREQUENCY;
		}
	}

	/**
	 * Check if sync is scheduled
	 *
	 * @return bool True if scheduled
	 */
	public static function is_scheduled(): bool {
		return (bool) wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get next scheduled sync time
	 *
	 * @return string|null Next scheduled time or null if not scheduled
	 */
	public static function get_next_scheduled_time(): ?string {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			return date( 'Y-m-d H:i:s', $timestamp );
		}
		return null;
	}

	/**
	 * Manually trigger sync for all data sources
	 *
	 * @return array Sync results
	 */
	public static function trigger_manual_sync(): array {
		$sync_start = microtime( true );
		
		try {
			$results = self::sync_all_data_sources();
			$sync_end = microtime( true );
			$results['duration'] = round( $sync_end - $sync_start, 2 );
			$results['status'] = 'success';
			
			// Log manual sync
			self::log_sync_start( 'manual' );
			
			return $results;
			
		} catch ( \Exception $e ) {
			return [
				'status' => 'error',
				'message' => $e->getMessage(),
				'sources_count' => 0,
				'records_updated' => 0,
				'errors_count' => 1,
			];
		}
	}

	/**
	 * Log sync operation start
	 *
	 * @param string $type Sync type (automatic|manual)
	 * @return int Sync log ID
	 */
	private static function log_sync_start( string $type = 'automatic' ): int {
		if ( class_exists( 'FP\DigitalMarketing\Models\SyncLog' ) ) {
			return SyncLog::create( [
				'sync_type' => $type,
				'status' => 'running',
				'started_at' => current_time( 'mysql' ),
			] );
		}
		
		// Fallback to error_log if SyncLog is not available
		error_log( sprintf(
			'FP DMS Sync Started - Type: %s, Time: %s',
			$type,
			current_time( 'mysql' )
		) );
		
		return 0;
	}

	/**
	 * Log sync operation completion
	 *
	 * @param int    $sync_id Sync log ID
	 * @param string $status  Final status
	 * @param string $message Completion message
	 * @return void
	 */
	private static function log_sync_complete( int $sync_id, string $status, string $message ): void {
		if ( class_exists( 'FP\DigitalMarketing\Models\SyncLog' ) && $sync_id > 0 ) {
			SyncLog::update( $sync_id, [
				'status' => $status,
				'message' => $message,
				'completed_at' => current_time( 'mysql' ),
			] );
		}
		
		// Always log to error_log for now
		error_log( sprintf(
			'FP DMS Sync Completed - Status: %s, Message: %s, Time: %s',
			$status,
			$message,
			current_time( 'mysql' )
		) );
	}
}
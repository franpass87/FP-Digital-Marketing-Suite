<?php
/**
 * Report Scheduler for Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * ReportScheduler class for scheduling automatic report generation
 */
class ReportScheduler {

	/**
	 * Cron hook name for reports
	 */
	private const CRON_HOOK = 'fp_dms_generate_reports';

	/**
	 * Initialize scheduler
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'schedule_reports' ] );
		add_action( self::CRON_HOOK, [ self::class, 'generate_scheduled_reports' ] );
	}

	/**
	 * Schedule reports if not already scheduled
	 *
	 * @return void
	 */
	public static function schedule_reports(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Schedule daily at 8:00 AM
			wp_schedule_event( 
				strtotime( 'tomorrow 08:00' ), 
				'daily', 
				self::CRON_HOOK 
			);
		}
	}

	/**
	 * Unschedule all reports
	 *
	 * @return void
	 */
	public static function unschedule_reports(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Generate scheduled reports for all clients
	 *
	 * @return void
	 */
	public static function generate_scheduled_reports(): void {
		// Get all cliente posts
		$clientes = get_posts( [
			'post_type'      => 'cliente',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => '_fp_auto_reports',
					'value'   => '1',
					'compare' => '=',
				],
			],
		] );

		foreach ( $clientes as $cliente ) {
			self::generate_client_report( $cliente->ID );
		}

		// Log the scheduled generation
		error_log( sprintf( 
			'FP DMS: Generated %d scheduled reports at %s', 
			count( $clientes ), 
			current_time( 'mysql' ) 
		) );
	}

	/**
	 * Generate report for a specific client
	 *
	 * @param int $client_id Client ID
	 * @return bool Success status
	 */
	public static function generate_client_report( int $client_id ): bool {
		try {
			$report_data = ReportGenerator::generate_demo_report_data( $client_id );
			$html_content = ReportGenerator::generate_html_report( $report_data );
			$pdf_content = ReportGenerator::generate_pdf_report( $report_data );

			// Save report to uploads directory
			$upload_dir = wp_upload_dir();
			$reports_dir = $upload_dir['basedir'] . '/fp-dms-reports';
			
			if ( ! file_exists( $reports_dir ) ) {
				wp_mkdir_p( $reports_dir );
			}

			$filename = sprintf( 
				'report-%d-%s.pdf', 
				$client_id, 
				date( 'Y-m-d' ) 
			);
			$filepath = $reports_dir . '/' . $filename;

			$result = file_put_contents( $filepath, $pdf_content );

			if ( $result !== false ) {
				// Save report metadata
				update_post_meta( $client_id, '_fp_last_report_generated', current_time( 'mysql' ) );
				update_post_meta( $client_id, '_fp_last_report_file', $filename );
				
				return true;
			}

			return false;

		} catch ( Exception $e ) {
			error_log( 'FP DMS Report Generation Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if reports are scheduled
	 *
	 * @return bool True if scheduled
	 */
	public static function is_scheduled(): bool {
		return (bool) wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get next scheduled time
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
	 * Manually trigger report generation for all clients
	 *
	 * @return int Number of reports generated
	 */
	public static function trigger_manual_generation(): int {
		$clientes = get_posts( [
			'post_type'      => 'cliente',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		] );

		$count = 0;
		foreach ( $clientes as $cliente ) {
			if ( self::generate_client_report( $cliente->ID ) ) {
				$count++;
			}
		}

		return $count;
	}
}
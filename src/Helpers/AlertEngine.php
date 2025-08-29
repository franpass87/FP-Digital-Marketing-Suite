<?php
/**
 * Alert Engine for Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Models\AlertRule;
use FP\DigitalMarketing\Helpers\MetricsAggregator;
use FP\DigitalMarketing\Helpers\MetricsSchema;

/**
 * AlertEngine class for evaluating alert rules and triggering notifications
 */
class AlertEngine {

	/**
	 * Check all active alert rules and trigger notifications
	 *
	 * @return array Array with check results
	 */
	public static function check_all_rules(): array {
		$results = [
			'checked' => 0,
			'triggered' => 0,
			'errors' => 0,
			'notifications_sent' => 0,
		];

		$active_rules = AlertRule::get_active_rules();
		
		foreach ( $active_rules as $rule ) {
			$results['checked']++;
			
			try {
				$check_result = self::check_rule( $rule );
				
				if ( $check_result['triggered'] ) {
					$results['triggered']++;
					
					// Record the trigger
					AlertRule::record_trigger( (int) $rule->id );
					
					// Send notifications
					$notification_result = self::send_notifications( $rule, $check_result );
					
					if ( $notification_result['success'] ) {
						$results['notifications_sent']++;
					}
				}
			} catch ( Exception $e ) {
				$results['errors']++;
				error_log( 'FP Digital Marketing Alert Error: ' . $e->getMessage() );
			}
		}

		// Log the check results
		self::log_check_results( $results );

		return $results;
	}

	/**
	 * Check a single alert rule
	 *
	 * @param object $rule Alert rule object
	 * @return array Check result with triggered status and details
	 */
	public static function check_rule( object $rule ): array {
		$result = [
			'triggered' => false,
			'current_value' => null,
			'threshold_value' => $rule->threshold_value,
			'condition' => $rule->condition,
			'metric' => $rule->metric,
		];

		// Get current metric value for the last 24 hours
		$end_date = current_time( 'mysql' );
		$start_date = date( 'Y-m-d H:i:s', strtotime( '-24 hours', strtotime( $end_date ) ) );

		// Get the metric value using MetricsAggregator
		$metrics = MetricsAggregator::get_metrics(
			(int) $rule->client_id,
			$start_date,
			$end_date,
			[ $rule->metric ]
		);

		if ( ! isset( $metrics[ $rule->metric ] ) ) {
			// No data available for this metric
			return $result;
		}

		$current_value = $metrics[ $rule->metric ]['total_value'];
		$result['current_value'] = $current_value;

		// Evaluate the condition
		$result['triggered'] = self::evaluate_condition( 
			$current_value, 
			$rule->condition, 
			(float) $rule->threshold_value 
		);

		return $result;
	}

	/**
	 * Evaluate a condition
	 *
	 * @param float  $current_value   Current metric value
	 * @param string $condition       Condition operator
	 * @param float  $threshold_value Threshold value
	 * @return bool True if condition is met, false otherwise
	 */
	private static function evaluate_condition( float $current_value, string $condition, float $threshold_value ): bool {
		switch ( $condition ) {
			case AlertRule::CONDITION_GREATER_THAN:
				return $current_value > $threshold_value;
			case AlertRule::CONDITION_LESS_THAN:
				return $current_value < $threshold_value;
			case AlertRule::CONDITION_GREATER_EQUAL:
				return $current_value >= $threshold_value;
			case AlertRule::CONDITION_LESS_EQUAL:
				return $current_value <= $threshold_value;
			case AlertRule::CONDITION_EQUAL:
				return abs( $current_value - $threshold_value ) < 0.001; // Float comparison
			case AlertRule::CONDITION_NOT_EQUAL:
				return abs( $current_value - $threshold_value ) >= 0.001; // Float comparison
			default:
				return false;
		}
	}

	/**
	 * Send notifications for a triggered rule
	 *
	 * @param object $rule   Alert rule object
	 * @param array  $result Check result
	 * @return array Notification result
	 */
	private static function send_notifications( object $rule, array $result ): array {
		$notification_result = [
			'success' => false,
			'admin_notice_sent' => false,
			'email_sent' => false,
			'errors' => [],
		];

		// Send admin notice
		if ( $rule->notification_admin_notice ) {
			$notification_result['admin_notice_sent'] = self::send_admin_notice( $rule, $result );
		}

		// Send email notification
		if ( ! empty( $rule->notification_email ) ) {
			$notification_result['email_sent'] = self::send_email_notification( $rule, $result );
		}

		$notification_result['success'] = $notification_result['admin_notice_sent'] || $notification_result['email_sent'];

		return $notification_result;
	}

	/**
	 * Send admin notice for triggered alert
	 *
	 * @param object $rule   Alert rule object
	 * @param array  $result Check result
	 * @return bool True on success
	 */
	private static function send_admin_notice( object $rule, array $result ): bool {
		// Store admin notice in transient for display
		$notice_key = 'fp_dms_alert_' . $rule->id . '_' . time();
		
		$notice_data = [
			'rule_name' => $rule->name,
			'metric' => $rule->metric,
			'current_value' => $result['current_value'],
			'condition' => $rule->condition,
			'threshold_value' => $rule->threshold_value,
			'client_id' => $rule->client_id,
			'triggered_at' => current_time( 'mysql' ),
		];

		return set_transient( $notice_key, $notice_data, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * Send email notification for triggered alert
	 *
	 * @param object $rule   Alert rule object
	 * @param array  $result Check result
	 * @return bool True on success
	 */
	private static function send_email_notification( object $rule, array $result ): bool {
		$to = $rule->notification_email;
		
		// Get client name
		$client_name = get_the_title( $rule->client_id ) ?: __( 'Client sconosciuto', 'fp-digital-marketing' );
		
		// Get metric definition for display name
		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		$metric_name = $kpi_definitions[ $rule->metric ]['name'] ?? $rule->metric;

		$subject = sprintf(
			__( '[%s] Alert: %s', 'fp-digital-marketing' ),
			get_bloginfo( 'name' ),
			$rule->name
		);

		$formatted_value = self::format_metric_value( $result['current_value'], $rule->metric );
		$formatted_threshold = self::format_metric_value( $rule->threshold_value, $rule->metric );

		$message = sprintf(
			__( "Alert attivato per il cliente: %s\n\nRegola: %s\nMetrica: %s\nValore attuale: %s\nCondizione: %s %s\nSoglia: %s\n\nData/ora: %s\n\nPer maggiori dettagli, accedi al pannello di amministrazione.", 'fp-digital-marketing' ),
			$client_name,
			$rule->name,
			$metric_name,
			$formatted_value,
			$rule->condition,
			$formatted_threshold,
			$formatted_threshold,
			current_time( 'mysql' )
		);

		$headers = [
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Format metric value for display
	 *
	 * @param float  $value  Metric value
	 * @param string $metric Metric name
	 * @return string Formatted value
	 */
	private static function format_metric_value( float $value, string $metric ): string {
		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		$format = $kpi_definitions[ $metric ]['format'] ?? 'number';

		switch ( $format ) {
			case 'percentage':
				return number_format( $value * 100, 2 ) . '%';
			case 'currency':
				return '€' . number_format( $value, 2 );
			case 'number':
			default:
				return number_format( $value );
		}
	}

	/**
	 * Get pending admin notices for alerts
	 *
	 * @return array Array of notice data
	 */
	public static function get_pending_admin_notices(): array {
		global $wpdb;

		$notices = [];
		
		// Get all alert-related transients
		$transients = $wpdb->get_results(
			"SELECT option_name, option_value 
			FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_fp_dms_alert_%'
			ORDER BY option_id DESC"
		);

		foreach ( $transients as $transient ) {
			$notice_data = maybe_unserialize( $transient->option_value );
			if ( is_array( $notice_data ) ) {
				$notice_key = str_replace( '_transient_', '', $transient->option_name );
				$notices[ $notice_key ] = $notice_data;
			}
		}

		return $notices;
	}

	/**
	 * Clear a specific admin notice
	 *
	 * @param string $notice_key Notice key
	 * @return bool True on success
	 */
	public static function clear_admin_notice( string $notice_key ): bool {
		return delete_transient( $notice_key );
	}

	/**
	 * Clear all pending admin notices
	 *
	 * @return int Number of notices cleared
	 */
	public static function clear_all_admin_notices(): int {
		global $wpdb;

		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_fp_dms_alert_%' 
			OR option_name LIKE '_transient_timeout_fp_dms_alert_%'"
		);

		return (int) $deleted;
	}

	/**
	 * Log alert check results
	 *
	 * @param array $results Check results
	 * @return void
	 */
	private static function log_check_results( array $results ): void {
		$log_entry = [
			'timestamp' => current_time( 'c' ),
			'type' => 'alert_check',
			'results' => $results,
		];

		error_log( 'FP Digital Marketing Alert Check: ' . wp_json_encode( $log_entry ) );

		// Store in database for admin review (keep last 50 entries)
		$alert_logs = get_option( 'fp_dms_alert_logs', [] );
		
		if ( count( $alert_logs ) >= 50 ) {
			$alert_logs = array_slice( $alert_logs, -49 );
		}
		
		$alert_logs[] = $log_entry;
		update_option( 'fp_dms_alert_logs', $alert_logs, false );
	}

	/**
	 * Get alert check logs
	 *
	 * @param int $limit Number of logs to retrieve
	 * @return array Array of log entries
	 */
	public static function get_alert_logs( int $limit = 20 ): array {
		$logs = get_option( 'fp_dms_alert_logs', [] );
		
		// Return most recent logs first
		$logs = array_reverse( $logs );
		
		if ( $limit > 0 ) {
			$logs = array_slice( $logs, 0, $limit );
		}

		return $logs;
	}
}
<?php
/**
 * Detected Anomaly Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\DetectedAnomaliesTable;

/**
 * DetectedAnomaly class for managing detected anomaly records
 */
class DetectedAnomaly {

	/**
	 * Create a new detected anomaly record
	 *
	 * @param int $client_id Client ID
	 * @param string $metric Metric name
	 * @param string $detection_method Detection method used
	 * @param array $analysis_result Anomaly analysis result
	 * @param int|null $rule_id Related rule ID (optional)
	 * @return int|false Anomaly ID on success, false on failure
	 */
	public static function create( 
		int $client_id, 
		string $metric, 
		string $detection_method, 
		array $analysis_result,
		?int $rule_id = null 
	) {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		// Extract data from analysis result
		$current_value = $analysis_result['current_value'] ?? 0.0;
		$expected_value = null;
		$z_score = null;
		$confidence_level = 'unknown';
		$severity = 'unknown';
		$deviation_type = 'unknown';

		// Extract method-specific data
		if ( $detection_method === 'z_score' && isset( $analysis_result['z_score_analysis'] ) ) {
			$z_data = $analysis_result['z_score_analysis'];
			$expected_value = $z_data['mean'] ?? null;
			$z_score = $z_data['z_score'] ?? null;
			$confidence_level = $z_data['confidence'] ?? 'unknown';
			$deviation_type = $z_data['deviation_type'] ?? 'unknown';
		} elseif ( $detection_method === 'moving_average' && isset( $analysis_result['moving_average_analysis'] ) ) {
			$ma_data = $analysis_result['moving_average_analysis'];
			$expected_value = $ma_data['moving_average'] ?? null;
			$severity = $ma_data['severity'] ?? 'unknown';
		} elseif ( $detection_method === 'combined' ) {
			$confidence_level = $analysis_result['combined_confidence'] ?? 'unknown';
			if ( isset( $analysis_result['z_score_analysis']['mean'] ) ) {
				$expected_value = $analysis_result['z_score_analysis']['mean'];
			} elseif ( isset( $analysis_result['moving_average_analysis']['moving_average'] ) ) {
				$expected_value = $analysis_result['moving_average_analysis']['moving_average'];
			}
		}

		// Prepare data for insertion
		$data = [
			'client_id' => $client_id,
			'rule_id' => $rule_id,
			'metric' => sanitize_text_field( $metric ),
			'detection_method' => sanitize_text_field( $detection_method ),
			'current_value' => (float) $current_value,
			'expected_value' => $expected_value ? (float) $expected_value : null,
			'z_score' => $z_score ? (float) $z_score : null,
			'confidence_level' => sanitize_text_field( $confidence_level ),
			'severity' => sanitize_text_field( $severity ),
			'deviation_type' => sanitize_text_field( $deviation_type ),
			'analysis_data' => wp_json_encode( $analysis_result ),
		];

		$formats = [
			'%d', // client_id
			'%d', // rule_id
			'%s', // metric
			'%s', // detection_method
			'%f', // current_value
			'%f', // expected_value
			'%f', // z_score
			'%s', // confidence_level
			'%s', // severity
			'%s', // deviation_type
			'%s', // analysis_data
		];

		$result = $wpdb->insert( $table_name, $data, $formats );

		return $result !== false ? $wpdb->insert_id : false;
	}

	/**
	 * Get detected anomaly by ID
	 *
	 * @param int $anomaly_id Anomaly ID
	 * @return object|null Anomaly object or null if not found
	 */
	public static function get_by_id( int $anomaly_id ): ?object {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		$anomaly = $wpdb->get_row( 
			$wpdb->prepare( 
				"SELECT * FROM $table_name WHERE id = %d", 
				$anomaly_id 
			) 
		);

		if ( $anomaly && $anomaly->analysis_data ) {
			$anomaly->analysis_data = json_decode( $anomaly->analysis_data, true );
		}

		return $anomaly ?: null;
	}

	/**
	 * Get recent anomalies with optional filters
	 *
	 * @param array $filters Optional filters (client_id, metric, severity, days_back, limit)
	 * @return array Array of anomaly objects
	 */
	public static function get_recent_anomalies( array $filters = [] ): array {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		$where_clauses = [];
		$where_values = [];

		if ( isset( $filters['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $filters['client_id'];
		}

		if ( isset( $filters['metric'] ) ) {
			$where_clauses[] = 'metric = %s';
			$where_values[] = $filters['metric'];
		}

		if ( isset( $filters['severity'] ) ) {
			$where_clauses[] = 'severity = %s';
			$where_values[] = $filters['severity'];
		}

		if ( isset( $filters['acknowledged'] ) ) {
			$where_clauses[] = 'acknowledged = %d';
			$where_values[] = $filters['acknowledged'] ? 1 : 0;
		}

		// Default to last 7 days if not specified
		$days_back = $filters['days_back'] ?? 7;
		$where_clauses[] = 'detected_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
		$where_values[] = $days_back;

		$sql = "SELECT * FROM $table_name";
		
		if ( ! empty( $where_clauses ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}
		
		$sql .= ' ORDER BY detected_at DESC';

		// Add limit if specified
		$limit = $filters['limit'] ?? 50;
		$sql .= $wpdb->prepare( ' LIMIT %d', $limit );

		if ( ! empty( $where_values ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
		} else {
			$results = $wpdb->get_results( $sql );
		}

		// Decode analysis_data for each result
		foreach ( $results as $anomaly ) {
			if ( $anomaly->analysis_data ) {
				$anomaly->analysis_data = json_decode( $anomaly->analysis_data, true );
			}
		}

		return $results ?: [];
	}

	/**
	 * Get anomaly statistics
	 *
	 * @param array $filters Optional filters (client_id, days_back)
	 * @return array Statistics array
	 */
	public static function get_statistics( array $filters = [] ): array {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		$where_clauses = [];
		$where_values = [];

		if ( isset( $filters['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $filters['client_id'];
		}

		// Default to last 30 days if not specified
		$days_back = $filters['days_back'] ?? 30;
		$where_clauses[] = 'detected_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
		$where_values[] = $days_back;

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$sql = "SELECT 
			COUNT(*) as total_anomalies,
			COUNT(DISTINCT metric) as affected_metrics,
			COUNT(CASE WHEN acknowledged = 1 THEN 1 END) as acknowledged_count,
			COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_count,
			COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_count,
			COUNT(CASE WHEN severity = 'medium' THEN 1 END) as medium_count,
			COUNT(CASE WHEN severity = 'low' THEN 1 END) as low_count
		FROM $table_name $where_sql";

		if ( ! empty( $where_values ) ) {
			$stats = $wpdb->get_row( $wpdb->prepare( $sql, ...$where_values ) );
		} else {
			$stats = $wpdb->get_row( $sql );
		}

		return [
			'total_anomalies' => (int) $stats->total_anomalies,
			'affected_metrics' => (int) $stats->affected_metrics,
			'acknowledged_count' => (int) $stats->acknowledged_count,
			'unacknowledged_count' => (int) $stats->total_anomalies - (int) $stats->acknowledged_count,
			'severity_distribution' => [
				'critical' => (int) $stats->critical_count,
				'high' => (int) $stats->high_count,
				'medium' => (int) $stats->medium_count,
				'low' => (int) $stats->low_count,
			],
		];
	}

	/**
	 * Acknowledge an anomaly
	 *
	 * @param int $anomaly_id Anomaly ID
	 * @param int $user_id User ID who acknowledged
	 * @return bool True on success, false on failure
	 */
	public static function acknowledge( int $anomaly_id, int $user_id ): bool {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		$result = $wpdb->update(
			$table_name,
			[
				'acknowledged' => 1,
				'acknowledged_by' => $user_id,
				'acknowledged_at' => current_time( 'mysql' ),
			],
			[ 'id' => $anomaly_id ],
			[ '%d', '%d', '%s' ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Mark notification as sent
	 *
	 * @param int $anomaly_id Anomaly ID
	 * @return bool True on success, false on failure
	 */
	public static function mark_notification_sent( int $anomaly_id ): bool {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		$result = $wpdb->update(
			$table_name,
			[ 'notification_sent' => 1 ],
			[ 'id' => $anomaly_id ],
			[ '%d' ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete old anomaly records
	 *
	 * @param int $days_to_keep Number of days to keep (default 90)
	 * @return int Number of records deleted
	 */
	public static function cleanup_old_records( int $days_to_keep = 90 ): int {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		$result = $wpdb->query( 
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE detected_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days_to_keep
			)
		);

		return (int) $result;
	}

	/**
	 * Get anomalies by metric for trending analysis
	 *
	 * @param int $client_id Client ID
	 * @param string $metric Metric name
	 * @param int $days_back Number of days to look back
	 * @return array Array of anomalies grouped by date
	 */
	public static function get_metric_trend( int $client_id, string $metric, int $days_back = 30 ): array {
		global $wpdb;

		$table_name = DetectedAnomaliesTable::get_table_name();

		$sql = "SELECT 
			DATE(detected_at) as date,
			COUNT(*) as anomaly_count,
			AVG(current_value) as avg_value,
			severity
		FROM $table_name 
		WHERE client_id = %d 
			AND metric = %s 
			AND detected_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
		GROUP BY DATE(detected_at), severity
		ORDER BY date DESC";

		$results = $wpdb->get_results( 
			$wpdb->prepare( $sql, $client_id, $metric, $days_back ) 
		);

		return $results ?: [];
	}
}
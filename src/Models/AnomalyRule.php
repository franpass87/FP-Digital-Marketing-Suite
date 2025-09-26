<?php
/**
 * Anomaly Rule Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\AnomalyRulesTable;

/**
 * AnomalyRule class for managing anomaly detection rules
 */
class AnomalyRule {

	/**
	 * Detection method constants
	 */
	public const METHOD_Z_SCORE        = 'z_score';
	public const METHOD_MOVING_AVERAGE = 'moving_average';
	public const METHOD_COMBINED       = 'combined';

	/**
	 * Create a new anomaly rule
	 *
	 * @param int    $client_id Client ID
	 * @param string $name Rule name
	 * @param string $description Rule description
	 * @param string $metric Metric to monitor
	 * @param string $detection_method Detection method
	 * @param array  $parameters Detection parameters
	 * @param string $notification_email Email for notifications
	 * @param bool   $notification_admin_notice Enable admin notices
	 * @param bool   $is_active Active status
	 * @return int|false Rule ID on success, false on failure
	 */
	public static function create(
		int $client_id,
		string $name,
		string $description,
		string $metric,
		string $detection_method,
		array $parameters = [],
		string $notification_email = '',
		bool $notification_admin_notice = true,
		bool $is_active = true
	) {
		global $wpdb;

		$table_name = AnomalyRulesTable::get_table_name();

		// Validate detection method
		if ( ! self::is_valid_detection_method( $detection_method ) ) {
			return false;
		}

		// Sanitize and prepare data
		$data = [
			'client_id'                 => $client_id,
			'name'                      => sanitize_text_field( $name ),
			'description'               => sanitize_textarea_field( $description ),
			'metric'                    => sanitize_text_field( $metric ),
			'detection_method'          => sanitize_text_field( $detection_method ),
			'z_score_threshold'         => isset( $parameters['z_score_threshold'] ) ? (float) $parameters['z_score_threshold'] : 2.0,
			'band_deviations'           => isset( $parameters['band_deviations'] ) ? (float) $parameters['band_deviations'] : 2.0,
			'window_size'               => isset( $parameters['window_size'] ) ? (int) $parameters['window_size'] : 7,
			'historical_days'           => isset( $parameters['historical_days'] ) ? (int) $parameters['historical_days'] : 30,
			'notification_email'        => sanitize_email( $notification_email ),
			'notification_admin_notice' => $notification_admin_notice ? 1 : 0,
			'is_active'                 => $is_active ? 1 : 0,
		];

		$formats = [
			'%d', // client_id
			'%s', // name
			'%s', // description
			'%s', // metric
			'%s', // detection_method
			'%f', // z_score_threshold
			'%f', // band_deviations
			'%d', // window_size
			'%d', // historical_days
			'%s', // notification_email
			'%d', // notification_admin_notice
			'%d', // is_active
		];

		$result = $wpdb->insert( $table_name, $data, $formats );

		return $result !== false ? $wpdb->insert_id : false;
	}

	/**
	 * Get anomaly rule by ID
	 *
	 * @param int $rule_id Rule ID
	 * @return object|null Rule object or null if not found
	 */
	public static function get_by_id( int $rule_id ): ?object {
		global $wpdb;

		$table_name = AnomalyRulesTable::get_table_name();

		$rule = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$rule_id
			)
		);

		return $rule ?: null;
	}

	/**
	 * Get all active anomaly rules
	 *
	 * @param int|null $client_id Optional client ID filter
	 * @return array Array of rule objects
	 */
	public static function get_active_rules( ?int $client_id = null ): array {
		global $wpdb;

		$table_name = AnomalyRulesTable::get_table_name();

		$sql    = "SELECT * FROM $table_name WHERE is_active = 1 AND (silence_until IS NULL OR silence_until < NOW())";
		$params = [];

		if ( $client_id !== null ) {
			$sql     .= ' AND client_id = %d';
			$params[] = $client_id;
		}

		$sql .= ' ORDER BY created_at DESC';

		if ( ! empty( $params ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		} else {
			$results = $wpdb->get_results( $sql );
		}

		return $results ?: [];
	}

	/**
	 * Get all anomaly rules with optional filters
	 *
	 * @param array $filters Optional filters (client_id, metric, is_active)
	 * @return array Array of rule objects
	 */
	public static function get_rules( array $filters = [] ): array {
		global $wpdb;

		$table_name = AnomalyRulesTable::get_table_name();

		$where_clauses = [];
		$where_values  = [];

		if ( isset( $filters['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[]  = $filters['client_id'];
		}

		if ( isset( $filters['metric'] ) ) {
			$where_clauses[] = 'metric = %s';
			$where_values[]  = $filters['metric'];
		}

		if ( isset( $filters['is_active'] ) ) {
			$where_clauses[] = 'is_active = %d';
			$where_values[]  = $filters['is_active'] ? 1 : 0;
		}

		$sql = "SELECT * FROM $table_name";

		if ( ! empty( $where_clauses ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		$sql .= ' ORDER BY created_at DESC';

		if ( ! empty( $where_values ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
		} else {
			$results = $wpdb->get_results( $sql );
		}

		return $results ?: [];
	}

	/**
	 * Update an existing anomaly rule
	 *
	 * @param int   $rule_id Rule ID to update
	 * @param array $data Data to update
	 * @return bool True on success, false on failure
	 */
	public static function update( int $rule_id, array $data ): bool {
		global $wpdb;

		$table_name = AnomalyRulesTable::get_table_name();

		// Define allowed fields for update
		$allowed_fields = [
			'name'                      => '%s',
			'description'               => '%s',
			'metric'                    => '%s',
			'detection_method'          => '%s',
			'z_score_threshold'         => '%f',
			'band_deviations'           => '%f',
			'window_size'               => '%d',
			'historical_days'           => '%d',
			'notification_email'        => '%s',
			'notification_admin_notice' => '%d',
			'is_active'                 => '%d',
			'silence_until'             => '%s',
		];

		$update_data = [];
		$formats     = [];

		foreach ( $data as $field => $value ) {
			if ( isset( $allowed_fields[ $field ] ) ) {
				switch ( $field ) {
					case 'name':
					case 'description':
					case 'metric':
					case 'detection_method':
						$update_data[ $field ] = sanitize_text_field( $value );
						break;
					case 'notification_email':
						$update_data[ $field ] = sanitize_email( $value );
						break;
					case 'z_score_threshold':
					case 'band_deviations':
						$update_data[ $field ] = (float) $value;
						break;
					case 'window_size':
					case 'historical_days':
						$update_data[ $field ] = (int) $value;
						break;
					case 'notification_admin_notice':
					case 'is_active':
						$update_data[ $field ] = $value ? 1 : 0;
						break;
					case 'silence_until':
						$update_data[ $field ] = $value; // Expecting mysql datetime format
						break;
				}
				$formats[] = $allowed_fields[ $field ];
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			[ 'id' => $rule_id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete an anomaly rule
	 *
	 * @param int $rule_id Rule ID to delete
	 * @return bool True on success, false on failure
	 */
	public static function delete( int $rule_id ): bool {
		global $wpdb;

		$table_name = AnomalyRulesTable::get_table_name();

		$result = $wpdb->delete(
			$table_name,
			[ 'id' => $rule_id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Record rule trigger
	 *
	 * @param int $rule_id Rule ID
	 * @return bool True on success, false on failure
	 */
	public static function record_trigger( int $rule_id ): bool {
		global $wpdb;

		$table_name = AnomalyRulesTable::get_table_name();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name 
				SET last_triggered = NOW(), 
					triggered_count = triggered_count + 1 
				WHERE id = %d",
				$rule_id
			)
		);

		return $result !== false;
	}

	/**
	 * Silence rule for specified duration
	 *
	 * @param int $rule_id Rule ID
	 * @param int $hours Hours to silence (default 24)
	 * @return bool True on success, false on failure
	 */
	public static function silence_rule( int $rule_id, int $hours = 24 ): bool {
		$silence_until = date( 'Y-m-d H:i:s', strtotime( "+{$hours} hours" ) );

		return self::update( $rule_id, [ 'silence_until' => $silence_until ] );
	}

	/**
	 * Unsilence rule
	 *
	 * @param int $rule_id Rule ID
	 * @return bool True on success, false on failure
	 */
	public static function unsilence_rule( int $rule_id ): bool {
		return self::update( $rule_id, [ 'silence_until' => null ] );
	}

	/**
	 * Validate detection method
	 *
	 * @param string $method Detection method to validate
	 * @return bool True if valid, false otherwise
	 */
	public static function is_valid_detection_method( string $method ): bool {
		$valid_methods = [
			self::METHOD_Z_SCORE,
			self::METHOD_MOVING_AVERAGE,
			self::METHOD_COMBINED,
		];

		return in_array( $method, $valid_methods, true );
	}

	/**
	 * Get available detection methods
	 *
	 * @return array Array of method => label pairs
	 */
	public static function get_detection_methods(): array {
		return [
			self::METHOD_Z_SCORE        => __( 'Z-Score Analysis', 'fp-digital-marketing' ),
			self::METHOD_MOVING_AVERAGE => __( 'Moving Average Bands', 'fp-digital-marketing' ),
			self::METHOD_COMBINED       => __( 'Combined Analysis', 'fp-digital-marketing' ),
		];
	}
}

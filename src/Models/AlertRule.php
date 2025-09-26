<?php
/**
 * Alert Rule Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\AlertRulesTable;

/**
 * Alert Rule class for CRUD operations
 *
 * This class provides CRUD functionality for the wp_fp_alert_rules table
 * to store and retrieve alert rule definitions.
 */
class AlertRule {

	/**
	 * Available condition operators
	 */
	public const CONDITION_GREATER_THAN  = '>';
	public const CONDITION_LESS_THAN     = '<';
	public const CONDITION_GREATER_EQUAL = '>=';
	public const CONDITION_LESS_EQUAL    = '<=';
	public const CONDITION_EQUAL         = '=';
	public const CONDITION_NOT_EQUAL     = '!=';

	/**
	 * Save an alert rule to the database
	 *
	 * @param int    $client_id          Client ID from the cliente post type
	 * @param string $name               Rule name
	 * @param string $metric             Metric name (normalized KPI)
	 * @param string $condition          Condition operator
	 * @param float  $threshold_value    Threshold value to compare against
	 * @param string $description        Optional rule description
	 * @param string $notification_email Optional email for notifications
	 * @param bool   $notification_admin_notice Whether to show admin notices
	 * @param bool   $is_active          Whether the rule is active
	 * @return int|false ID of the inserted record on success, false on failure
	 */
	public static function save(
		int $client_id,
		string $name,
		string $metric,
		string $condition,
		float $threshold_value,
		string $description = '',
		string $notification_email = '',
		bool $notification_admin_notice = true,
		bool $is_active = true
	): int|false {
		global $wpdb;

		$table_name = AlertRulesTable::get_table_name();

		$data = [
			'client_id'                 => $client_id,
			'name'                      => sanitize_text_field( $name ),
			'description'               => sanitize_textarea_field( $description ),
			'metric'                    => sanitize_text_field( $metric ),
			'condition'                 => sanitize_text_field( $condition ),
			'threshold_value'           => $threshold_value,
			'notification_email'        => sanitize_email( $notification_email ),
			'notification_admin_notice' => $notification_admin_notice ? 1 : 0,
			'is_active'                 => $is_active ? 1 : 0,
		];

		$formats = [
			'%d', // client_id
			'%s', // name
			'%s', // description
			'%s', // metric
			'%s', // condition
			'%f', // threshold_value
			'%s', // notification_email
			'%d', // notification_admin_notice
			'%d', // is_active
		];

		$result = $wpdb->insert( $table_name, $data, $formats );

		return $result !== false ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing alert rule
	 *
	 * @param int   $rule_id            Rule ID to update
	 * @param array $data               Data to update
	 * @return bool True on success, false on failure
	 */
	public static function update( int $rule_id, array $data ): bool {
		global $wpdb;

		$table_name = AlertRulesTable::get_table_name();

		// Sanitize and format data
		$update_data = [];
		$formats     = [];

		$allowed_fields = [
			'name'                      => '%s',
			'description'               => '%s',
			'metric'                    => '%s',
			'condition'                 => '%s',
			'threshold_value'           => '%f',
			'notification_email'        => '%s',
			'notification_admin_notice' => '%d',
			'is_active'                 => '%d',
		];

		foreach ( $data as $field => $value ) {
			if ( array_key_exists( $field, $allowed_fields ) ) {
				switch ( $field ) {
					case 'name':
					case 'metric':
					case 'condition':
						$update_data[ $field ] = sanitize_text_field( $value );
						break;
					case 'description':
						$update_data[ $field ] = sanitize_textarea_field( $value );
						break;
					case 'notification_email':
						$update_data[ $field ] = sanitize_email( $value );
						break;
					case 'threshold_value':
						$update_data[ $field ] = (float) $value;
						break;
					case 'notification_admin_notice':
					case 'is_active':
						$update_data[ $field ] = $value ? 1 : 0;
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
	 * Update rule trigger information
	 *
	 * @param int $rule_id Rule ID
	 * @return bool True on success, false on failure
	 */
	public static function record_trigger( int $rule_id ): bool {
		global $wpdb;

		$table_name = AlertRulesTable::get_table_name();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name 
			SET last_triggered = %s, triggered_count = triggered_count + 1 
			WHERE id = %d",
				current_time( 'mysql' ),
				$rule_id
			)
		);

		return $result !== false;
	}

	/**
	 * Get alert rule by ID
	 *
	 * @param int $rule_id Rule ID
	 * @return object|null Rule object or null if not found
	 */
	public static function get_by_id( int $rule_id ): ?object {
		global $wpdb;

		$table_name = AlertRulesTable::get_table_name();

		$rule = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$rule_id
			)
		);

		return $rule ?: null;
	}

	/**
	 * Get all alert rules for a client
	 *
	 * @param int  $client_id Client ID
	 * @param bool $active_only Whether to return only active rules
	 * @return array Array of rule objects
	 */
	public static function get_by_client( int $client_id, bool $active_only = false ): array {
		global $wpdb;

		$table_name = AlertRulesTable::get_table_name();

		$where_clause = $wpdb->prepare( 'WHERE client_id = %d', $client_id );

		if ( $active_only ) {
			$where_clause .= ' AND is_active = 1';
		}

		$rules = $wpdb->get_results(
			"SELECT * FROM $table_name $where_clause ORDER BY created_at DESC"
		);

		return $rules ?: [];
	}

	/**
	 * Get all active alert rules
	 *
	 * @return array Array of rule objects
	 */
	public static function get_active_rules(): array {
		global $wpdb;

		$table_name = AlertRulesTable::get_table_name();

		$rules = $wpdb->get_results(
			"SELECT * FROM $table_name WHERE is_active = 1 ORDER BY client_id, metric"
		);

		return $rules ?: [];
	}

	/**
	 * Delete an alert rule
	 *
	 * @param int $rule_id Rule ID
	 * @return bool True on success, false on failure
	 */
	public static function delete( int $rule_id ): bool {
		global $wpdb;

		$table_name = AlertRulesTable::get_table_name();

		$result = $wpdb->delete(
			$table_name,
			[ 'id' => $rule_id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get available condition operators
	 *
	 * @return array Array of condition operators with labels
	 */
	public static function get_condition_operators(): array {
		return [
			self::CONDITION_GREATER_THAN  => __( 'Maggiore di (>)', 'fp-digital-marketing' ),
			self::CONDITION_LESS_THAN     => __( 'Minore di (<)', 'fp-digital-marketing' ),
			self::CONDITION_GREATER_EQUAL => __( 'Maggiore o uguale (>=)', 'fp-digital-marketing' ),
			self::CONDITION_LESS_EQUAL    => __( 'Minore o uguale (<=)', 'fp-digital-marketing' ),
			self::CONDITION_EQUAL         => __( 'Uguale (=)', 'fp-digital-marketing' ),
			self::CONDITION_NOT_EQUAL     => __( 'Diverso (!=)', 'fp-digital-marketing' ),
		];
	}

	/**
	 * Validate condition operator
	 *
	 * @param string $condition Condition to validate
	 * @return bool True if valid, false otherwise
	 */
	public static function is_valid_condition( string $condition ): bool {
		$valid_conditions = [
			self::CONDITION_GREATER_THAN,
			self::CONDITION_LESS_THAN,
			self::CONDITION_GREATER_EQUAL,
			self::CONDITION_LESS_EQUAL,
			self::CONDITION_EQUAL,
			self::CONDITION_NOT_EQUAL,
		];

		return in_array( $condition, $valid_conditions, true );
	}
}

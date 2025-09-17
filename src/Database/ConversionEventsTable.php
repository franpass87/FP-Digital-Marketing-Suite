<?php
/**
 * Conversion Events Table
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * Conversion Events Table class
 * 
 * Manages the database table for storing conversion events and goals.
 */
class ConversionEventsTable {

	/**
	 * Table name
	 */
	public const TABLE_NAME = 'fp_conversion_events';

	/**
	 * Get full table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the conversion events table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id varchar(255) NOT NULL,
			event_type varchar(100) NOT NULL,
			event_name varchar(255) NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			source varchar(100) NOT NULL,
			source_event_id varchar(255) DEFAULT NULL,
			user_id varchar(255) DEFAULT NULL,
			session_id varchar(255) DEFAULT NULL,
			utm_source varchar(255) DEFAULT NULL,
			utm_medium varchar(255) DEFAULT NULL,
			utm_campaign varchar(255) DEFAULT NULL,
			utm_term varchar(255) DEFAULT NULL,
			utm_content varchar(255) DEFAULT NULL,
			event_value decimal(10,2) DEFAULT 0.00,
			currency varchar(3) DEFAULT 'EUR',
			event_attributes longtext DEFAULT NULL,
			page_url varchar(500) DEFAULT NULL,
			referrer_url varchar(500) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			is_duplicate tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL,
			processed_at datetime DEFAULT NULL,
			INDEX idx_event_id (event_id),
			INDEX idx_event_type (event_type),
			INDEX idx_client_id (client_id),
			INDEX idx_source (source),
			INDEX idx_created_at (created_at),
			INDEX idx_utm_campaign (utm_campaign),
			INDEX idx_duplicate (is_duplicate),
			UNIQUE KEY unique_event (event_id, source),
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
	}

	/**
	 * Check if table exists
	 *
	 * @return bool True if table exists
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		return $result === $table_name;
	}

	/**
	 * Drop the table (for uninstall)
	 *
	 * @return bool True on success
	 */
	public static function drop_table(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$result = $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		return $result !== false;
	}

	/**
	 * Insert a new conversion event
	 *
	 * @param array $event_data Event data array
	 * @return int|false Event ID on success, false on failure
	 */
	public static function insert_event( array $event_data ): int|false {
		global $wpdb;

		$table_name = self::get_table_name();

		// Prepare data with defaults
		$data = wp_parse_args( $event_data, [
			'event_value' => 0.00,
			'currency' => 'EUR',
			'is_duplicate' => 0,
			'created_at' => current_time( 'mysql' ),
		] );

		// Serialize attributes if it's an array
		if ( isset( $data['event_attributes'] ) && is_array( $data['event_attributes'] ) ) {
			$data['event_attributes'] = wp_json_encode( $data['event_attributes'] );
		}

		$result = $wpdb->insert( $table_name, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing conversion event
	 *
	 * @param int   $event_id Event ID
	 * @param array $event_data Event data to update
	 * @return bool True on success, false on failure
	 */
	public static function update_event( int $event_id, array $event_data ): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		// Serialize attributes if it's an array
		if ( isset( $event_data['event_attributes'] ) && is_array( $event_data['event_attributes'] ) ) {
			$event_data['event_attributes'] = wp_json_encode( $event_data['event_attributes'] );
		}

		$result = $wpdb->update( 
			$table_name, 
			$event_data, 
			[ 'id' => $event_id ],
			null,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get conversion events with filtering
	 *
	 * @param array $criteria Filter criteria
	 * @param int   $limit Results limit
	 * @param int   $offset Results offset
	 * @return array Array of event objects
	 */
	public static function get_events( array $criteria = [], int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table_name = self::get_table_name();
		$where_clauses = [];
		$where_values = [];

		// Build WHERE clause based on criteria
		if ( isset( $criteria['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $criteria['client_id'];
		}

		if ( isset( $criteria['event_type'] ) ) {
			if ( is_array( $criteria['event_type'] ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $criteria['event_type'] ), '%s' ) );
				$where_clauses[] = "event_type IN ($placeholders)";
				$where_values = array_merge( $where_values, $criteria['event_type'] );
			} else {
				$where_clauses[] = 'event_type = %s';
				$where_values[] = $criteria['event_type'];
			}
		}

		if ( isset( $criteria['user_id'] ) ) {
			if ( is_array( $criteria['user_id'] ) ) {
				$user_ids = array_map(
					static function ( $user_id ) {
						return sanitize_text_field( (string) $user_id );
					},
					$criteria['user_id']
				);

				if ( empty( $user_ids ) ) {
					$where_clauses[] = '1=0';
				} else {
					$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%s' ) );
					$where_clauses[] = "user_id IN ($placeholders)";
					$where_values = array_merge( $where_values, $user_ids );
				}
			} else {
				$where_clauses[] = 'user_id = %s';
				$where_values[] = sanitize_text_field( (string) $criteria['user_id'] );
			}
		}

		if ( isset( $criteria['source'] ) ) {
			if ( is_array( $criteria['source'] ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $criteria['source'] ), '%s' ) );
				$where_clauses[] = "source IN ($placeholders)";
				$where_values = array_merge( $where_values, $criteria['source'] );
			} else {
				$where_clauses[] = 'source = %s';
				$where_values[] = $criteria['source'];
			}
		}

		if ( isset( $criteria['period_start'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[] = $criteria['period_start'];
		}

		if ( isset( $criteria['period_end'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[] = $criteria['period_end'];
		}

		if ( isset( $criteria['utm_campaign'] ) ) {
			$where_clauses[] = 'utm_campaign = %s';
			$where_values[] = $criteria['utm_campaign'];
		}

		if ( isset( $criteria['exclude_duplicates'] ) && $criteria['exclude_duplicates'] ) {
			$where_clauses[] = 'is_duplicate = 0';
		}

		// Build the query
		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$sql = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$where_values[] = $limit;
		$where_values[] = $offset;

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Unserialize attributes
		foreach ( $results as &$result ) {
			if ( ! empty( $result['event_attributes'] ) ) {
				$result['event_attributes'] = json_decode( $result['event_attributes'], true );
			}
		}

		return $results;
	}

	/**
	 * Get event count with filtering
	 *
	 * @param array $criteria Filter criteria
	 * @return int Total count
	 */
	public static function get_events_count( array $criteria = [] ): int {
		global $wpdb;

		$table_name = self::get_table_name();
		$where_clauses = [];
		$where_values = [];

		// Build WHERE clause (same as get_events)
		if ( isset( $criteria['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $criteria['client_id'];
		}

		if ( isset( $criteria['event_type'] ) ) {
			if ( is_array( $criteria['event_type'] ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $criteria['event_type'] ), '%s' ) );
				$where_clauses[] = "event_type IN ($placeholders)";
				$where_values = array_merge( $where_values, $criteria['event_type'] );
			} else {
				$where_clauses[] = 'event_type = %s';
				$where_values[] = $criteria['event_type'];
			}
		}

		if ( isset( $criteria['user_id'] ) ) {
			if ( is_array( $criteria['user_id'] ) ) {
				$user_ids = array_map(
					static function ( $user_id ) {
						return sanitize_text_field( (string) $user_id );
					},
					$criteria['user_id']
				);

				if ( empty( $user_ids ) ) {
					$where_clauses[] = '1=0';
				} else {
					$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%s' ) );
					$where_clauses[] = "user_id IN ($placeholders)";
					$where_values = array_merge( $where_values, $user_ids );
				}
			} else {
				$where_clauses[] = 'user_id = %s';
				$where_values[] = sanitize_text_field( (string) $criteria['user_id'] );
			}
		}

		if ( isset( $criteria['source'] ) ) {
			if ( is_array( $criteria['source'] ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $criteria['source'] ), '%s' ) );
				$where_clauses[] = "source IN ($placeholders)";
				$where_values = array_merge( $where_values, $criteria['source'] );
			} else {
				$where_clauses[] = 'source = %s';
				$where_values[] = $criteria['source'];
			}
		}

		if ( isset( $criteria['period_start'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[] = $criteria['period_start'];
		}

		if ( isset( $criteria['period_end'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[] = $criteria['period_end'];
		}

		if ( isset( $criteria['utm_campaign'] ) ) {
			$where_clauses[] = 'utm_campaign = %s';
			$where_values[] = $criteria['utm_campaign'];
		}

		if ( isset( $criteria['exclude_duplicates'] ) && $criteria['exclude_duplicates'] ) {
			$where_clauses[] = 'is_duplicate = 0';
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$sql = "SELECT COUNT(*) FROM $table_name $where_sql";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Mark event as duplicate
	 *
	 * @param int $event_id Event ID
	 * @return bool True on success
	 */
	public static function mark_as_duplicate( int $event_id ): bool {
		return self::update_event( $event_id, [ 'is_duplicate' => 1 ] );
	}

	/**
	 * Delete an event
	 *
	 * @param int $event_id Event ID
	 * @return bool True on success
	 */
	public static function delete_event( int $event_id ): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$result = $wpdb->delete( $table_name, [ 'id' => $event_id ], [ '%d' ] );

		return $result !== false;
	}
}
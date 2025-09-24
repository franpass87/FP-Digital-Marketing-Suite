<?php
/**
 * Audience Segment Table
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

use FP\DigitalMarketing\Database\DatabaseUtils;

/**
 * Audience Segment Table class
 * 
 * Manages the database table for storing audience segments and their membership.
 */
class AudienceSegmentTable {

	/**
	 * Segments table name
	 */
	public const SEGMENTS_TABLE_NAME = 'fp_audience_segments';

	/**
	 * Segment membership table name
	 */
	public const MEMBERSHIP_TABLE_NAME = 'fp_segment_membership';

	/**
	 * Get full segments table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
        public static function get_segments_table_name(): string {
                global $wpdb;
                return DatabaseUtils::resolve_table_name( $wpdb, self::SEGMENTS_TABLE_NAME );
        }

	/**
	 * Get full membership table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
        public static function get_membership_table_name(): string {
                global $wpdb;
                return DatabaseUtils::resolve_table_name( $wpdb, self::MEMBERSHIP_TABLE_NAME );
        }

	/**
	 * Create the audience segments table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_segments_table(): bool {
		global $wpdb;

		$table_name = self::get_segments_table_name();
                $charset_collate = DatabaseUtils::get_charset_collate( $wpdb );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			client_id bigint(20) unsigned NOT NULL,
			rules longtext NOT NULL,
			is_active tinyint(1) DEFAULT 1,
			last_evaluated_at datetime DEFAULT NULL,
			member_count int unsigned DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			INDEX idx_client_id (client_id),
			INDEX idx_active (is_active),
			INDEX idx_last_evaluated (last_evaluated_at),
			PRIMARY KEY (id)
		) $charset_collate;";

                return DatabaseUtils::run_schema_delta( $sql, $wpdb );
	}

	/**
	 * Create the segment membership table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_membership_table(): bool {
		global $wpdb;

		$table_name = self::get_membership_table_name();
                $charset_collate = DatabaseUtils::get_charset_collate( $wpdb );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			segment_id bigint(20) unsigned NOT NULL,
			user_id varchar(255) NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			added_at datetime NOT NULL,
			last_matched_at datetime NOT NULL,
			INDEX idx_segment_id (segment_id),
			INDEX idx_user_id (user_id),
			INDEX idx_client_id (client_id),
			INDEX idx_added_at (added_at),
			UNIQUE KEY unique_membership (segment_id, user_id),
			PRIMARY KEY (id)
		) $charset_collate;";

                return DatabaseUtils::run_schema_delta( $sql, $wpdb );
	}

	/**
	 * Check if segments table exists
	 *
	 * @return bool True if table exists
	 */
	public static function segments_table_exists(): bool {
		global $wpdb;
		$table_name = self::get_segments_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		return $result === $table_name;
	}

	/**
	 * Check if membership table exists
	 *
	 * @return bool True if table exists
	 */
	public static function membership_table_exists(): bool {
		global $wpdb;
		$table_name = self::get_membership_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		return $result === $table_name;
	}

	/**
	 * Drop the segments table (for uninstall)
	 *
	 * @return bool True on success
	 */
	public static function drop_segments_table(): bool {
		global $wpdb;
		$table_name = self::get_segments_table_name();
		$result = $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		return $result !== false;
	}

	/**
	 * Drop the membership table (for uninstall)
	 *
	 * @return bool True on success
	 */
	public static function drop_membership_table(): bool {
		global $wpdb;
		$table_name = self::get_membership_table_name();
		$result = $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		return $result !== false;
	}

	/**
	 * Insert a new audience segment
	 *
	 * @param array $segment_data Segment data array
	 * @return int|false Segment ID on success, false on failure
	 */
	public static function insert_segment( array $segment_data ): int|false {
		global $wpdb;

		$table_name = self::get_segments_table_name();

		// Prepare data with defaults
		$data = wp_parse_args( $segment_data, [
			'description' => '',
			'is_active' => 1,
			'member_count' => 0,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		] );

		// Serialize rules if it's an array
		if ( isset( $data['rules'] ) && is_array( $data['rules'] ) ) {
			$data['rules'] = wp_json_encode( $data['rules'] );
		}

		$result = $wpdb->insert( $table_name, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing audience segment
	 *
	 * @param int   $segment_id Segment ID
	 * @param array $segment_data Segment data to update
	 * @return bool True on success, false on failure
	 */
	public static function update_segment( int $segment_id, array $segment_data ): bool {
		global $wpdb;

		$table_name = self::get_segments_table_name();

		// Serialize rules if it's an array
		if ( isset( $segment_data['rules'] ) && is_array( $segment_data['rules'] ) ) {
			$segment_data['rules'] = wp_json_encode( $segment_data['rules'] );
		}

		$result = $wpdb->update( 
			$table_name, 
			$segment_data, 
			[ 'id' => $segment_id ],
			null,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get audience segments with filtering
	 *
	 * @param array $criteria Filter criteria
	 * @param int   $limit Results limit
	 * @param int   $offset Results offset
	 * @return array Array of segment objects
	 */
	public static function get_segments( array $criteria = [], int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table_name = self::get_segments_table_name();
		$where_clauses = [];
		$where_values = [];

		// Build WHERE clause based on criteria
		if ( isset( $criteria['id'] ) ) {
			$where_clauses[] = 'id = %d';
			$where_values[] = $criteria['id'];
		}

		if ( isset( $criteria['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $criteria['client_id'];
		}

		if ( isset( $criteria['is_active'] ) ) {
			$where_clauses[] = 'is_active = %d';
			$where_values[] = $criteria['is_active'];
		}

		if ( isset( $criteria['name_like'] ) ) {
			$where_clauses[] = 'name LIKE %s';
			$where_values[] = '%' . $wpdb->esc_like( $criteria['name_like'] ) . '%';
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

		// Unserialize rules
		foreach ( $results as &$result ) {
			if ( ! empty( $result['rules'] ) ) {
				$result['rules'] = json_decode( $result['rules'], true );
			}
		}

		return $results;
	}

	/**
	 * Get segment count with filtering
	 *
	 * @param array $criteria Filter criteria
	 * @return int Total count
	 */
	public static function get_segments_count( array $criteria = [] ): int {
		global $wpdb;

		$table_name = self::get_segments_table_name();
		$where_clauses = [];
		$where_values = [];

		// Build WHERE clause (same as get_segments)
		if ( isset( $criteria['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $criteria['client_id'];
		}

		if ( isset( $criteria['is_active'] ) ) {
			$where_clauses[] = 'is_active = %d';
			$where_values[] = $criteria['is_active'];
		}

		if ( isset( $criteria['name_like'] ) ) {
			$where_clauses[] = 'name LIKE %s';
			$where_values[] = '%' . $wpdb->esc_like( $criteria['name_like'] ) . '%';
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$sql = "SELECT COUNT(*) FROM $table_name $where_sql";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Delete a segment
	 *
	 * @param int $segment_id Segment ID
	 * @return bool True on success
	 */
	public static function delete_segment( int $segment_id ): bool {
		global $wpdb;

		// First delete all membership records for this segment
		self::clear_segment_membership( $segment_id );

		// Then delete the segment itself
		$table_name = self::get_segments_table_name();
		$result = $wpdb->delete( $table_name, [ 'id' => $segment_id ], [ '%d' ] );

		return $result !== false;
	}

	/**
	 * Add user to segment
	 *
	 * @param int    $segment_id Segment ID
	 * @param string $user_id User ID
	 * @param int    $client_id Client ID
	 * @return bool True on success
	 */
	public static function add_user_to_segment( int $segment_id, string $user_id, int $client_id ): bool {
		global $wpdb;

		$table_name = self::get_membership_table_name();
		$current_time = current_time( 'mysql' );

		$data = [
			'segment_id' => $segment_id,
			'user_id' => $user_id,
			'client_id' => $client_id,
			'added_at' => $current_time,
			'last_matched_at' => $current_time,
		];

		$result = $wpdb->replace( $table_name, $data );

		return $result !== false;
	}

	/**
	 * Remove user from segment
	 *
	 * @param int    $segment_id Segment ID
	 * @param string $user_id User ID
	 * @return bool True on success
	 */
	public static function remove_user_from_segment( int $segment_id, string $user_id ): bool {
		global $wpdb;

		$table_name = self::get_membership_table_name();
		$result = $wpdb->delete( 
			$table_name, 
			[ 
				'segment_id' => $segment_id,
				'user_id' => $user_id 
			], 
			[ '%d', '%s' ] 
		);

		return $result !== false;
	}

	/**
	 * Clear all membership for a segment
	 *
	 * @param int $segment_id Segment ID
	 * @return bool True on success
	 */
	public static function clear_segment_membership( int $segment_id ): bool {
		global $wpdb;

		$table_name = self::get_membership_table_name();
		$result = $wpdb->delete( $table_name, [ 'segment_id' => $segment_id ], [ '%d' ] );

		return $result !== false;
	}

	/**
	 * Get segment members
	 *
	 * @param int $segment_id Segment ID
	 * @param int $limit Results limit
	 * @param int $offset Results offset
	 * @return array Array of member data
	 */
	public static function get_segment_members( int $segment_id, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table_name = self::get_membership_table_name();
		$sql = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE segment_id = %d ORDER BY added_at DESC LIMIT %d OFFSET %d",
			$segment_id,
			$limit,
			$offset
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get segment member count
	 *
	 * @param int $segment_id Segment ID
	 * @return int Member count
	 */
	public static function get_segment_member_count( int $segment_id ): int {
		global $wpdb;

		$table_name = self::get_membership_table_name();
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE segment_id = %d",
			$segment_id
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Update member count cache for a segment
	 *
	 * @param int $segment_id Segment ID
	 * @return bool True on success
	 */
	public static function update_member_count_cache( int $segment_id ): bool {
		$count = self::get_segment_member_count( $segment_id );
		
		return self::update_segment( $segment_id, [
			'member_count' => $count,
			'last_evaluated_at' => current_time( 'mysql' ),
		] );
	}
}
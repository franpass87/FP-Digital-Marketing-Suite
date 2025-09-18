<?php
/**
 * Funnel Table Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * Funnel table management class
 * 
 * Handles creation and management of the funnels table for storing
 * funnel definitions and stages.
 */
class FunnelTable {

	/**
	 * Table name for funnels
	 *
	 * @var string
	 */
	private static string $table_name = 'fp_dms_funnels';

	/**
	 * Table name for funnel stages
	 *
	 * @var string
	 */
	private static string $stages_table_name = 'fp_dms_funnel_stages';

	/**
	 * Get the full table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}

	/**
	 * Get the full stages table name with WordPress prefix
	 *
	 * @return string Full stages table name
	 */
	public static function get_stages_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$stages_table_name;
	}

	/**
	 * Check if the funnels table exists
	 *
	 * @return bool True if table exists
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( 
			"SHOW TABLES LIKE %s", 
			$table_name 
		) );
		return $result === $table_name;
	}

	/**
	 * Check if the funnel stages table exists
	 *
	 * @return bool True if table exists
	 */
	public static function stages_table_exists(): bool {
		global $wpdb;
		$table_name = self::get_stages_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( 
			"SHOW TABLES LIKE %s", 
			$table_name 
		) );
		return $result === $table_name;
	}

	/**
	 * Create the funnels table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			client_id bigint(20) unsigned NOT NULL,
			status enum('active','inactive','draft') DEFAULT 'draft',
			conversion_window_days int(11) DEFAULT 30,
			attribution_model enum('first_click','last_click','linear','time_decay') DEFAULT 'last_click',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_client_id (client_id),
			KEY idx_status (status),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Create the funnel stages table
	 *
	 * @return bool True on success, false on failure
	 */
        public static function create_stages_table(): bool {
                global $wpdb;

                $table_name = self::get_stages_table_name();
                $charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			funnel_id bigint(20) unsigned NOT NULL,
			stage_order int(11) NOT NULL,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			event_type varchar(100) NOT NULL,
			event_conditions longtext DEFAULT NULL,
			required_attributes longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_funnel_id (funnel_id),
			KEY idx_stage_order (stage_order),
			UNIQUE KEY idx_funnel_stage_order (funnel_id, stage_order),
			CONSTRAINT fk_funnel_stages_funnel FOREIGN KEY (funnel_id) REFERENCES $table_name (id) ON DELETE CASCADE
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

                return self::stages_table_exists();
        }

        /**
         * Drop the funnels table.
         *
         * @return bool True on success, false on failure
         */
        public static function drop_table(): bool {
                global $wpdb;

                $table_name = self::get_table_name();
                $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

                return $result !== false;
        }

        /**
         * Drop the funnel stages table.
         *
         * @return bool True on success, false on failure
         */
        public static function drop_stages_table(): bool {
                global $wpdb;

                $table_name = self::get_stages_table_name();
                $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

                return $result !== false;
        }

	/**
	 * Insert a new funnel
	 *
	 * @param array $data Funnel data
	 * @return int|false Funnel ID on success, false on failure
	 */
	public static function insert_funnel( array $data ) {
		global $wpdb;

		$table_name = self::get_table_name();
		
		$defaults = [
			'status' => 'draft',
			'conversion_window_days' => 30,
			'attribution_model' => 'last_click',
		];
		
		$data = wp_parse_args( $data, $defaults );
		
		$result = $wpdb->insert(
			$table_name,
			[
				'name' => sanitize_text_field( $data['name'] ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'client_id' => (int) $data['client_id'],
				'status' => sanitize_text_field( $data['status'] ),
				'conversion_window_days' => (int) $data['conversion_window_days'],
				'attribution_model' => sanitize_text_field( $data['attribution_model'] ),
			],
			[ '%s', '%s', '%d', '%s', '%d', '%s' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Insert a funnel stage
	 *
	 * @param array $data Stage data
	 * @return int|false Stage ID on success, false on failure
	 */
	public static function insert_stage( array $data ) {
		global $wpdb;

		$table_name = self::get_stages_table_name();
		
		$result = $wpdb->insert(
			$table_name,
			[
				'funnel_id' => (int) $data['funnel_id'],
				'stage_order' => (int) $data['stage_order'],
				'name' => sanitize_text_field( $data['name'] ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'event_type' => sanitize_text_field( $data['event_type'] ),
				'event_conditions' => maybe_serialize( $data['event_conditions'] ?? [] ),
				'required_attributes' => maybe_serialize( $data['required_attributes'] ?? [] ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get funnel by ID
	 *
	 * @param int $funnel_id Funnel ID
	 * @return array|null Funnel data or null if not found
	 */
	public static function get_funnel( int $funnel_id ): ?array {
		global $wpdb;

		$table_name = self::get_table_name();
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$funnel_id
		), ARRAY_A );

		return $result ?: null;
	}

	/**
	 * Get funnel stages
	 *
	 * @param int $funnel_id Funnel ID
	 * @return array Array of stages
	 */
	public static function get_funnel_stages( int $funnel_id ): array {
		global $wpdb;

		$table_name = self::get_stages_table_name();
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE funnel_id = %d ORDER BY stage_order ASC",
			$funnel_id
		), ARRAY_A );

		// Unserialize conditions and attributes
		foreach ( $results as &$stage ) {
			$stage['event_conditions'] = maybe_unserialize( $stage['event_conditions'] );
			$stage['required_attributes'] = maybe_unserialize( $stage['required_attributes'] );
		}

		return $results ?: [];
	}

	/**
	 * Get funnels for a client
	 *
	 * @param int $client_id Client ID
	 * @param string $status Filter by status (optional)
	 * @return array Array of funnels
	 */
	public static function get_client_funnels( int $client_id, string $status = '' ): array {
		global $wpdb;

		$table_name = self::get_table_name();
		$where_clause = 'WHERE client_id = %d';
		$params = [ $client_id ];

		if ( ! empty( $status ) ) {
			$where_clause .= ' AND status = %s';
			$params[] = $status;
		}

		$sql = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC";
		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Update funnel
	 *
	 * @param int   $funnel_id Funnel ID
	 * @param array $data Update data
	 * @return bool True on success, false on failure
	 */
	public static function update_funnel( int $funnel_id, array $data ): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		
		$update_data = [];
		$format = [];

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
			$format[] = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$format[] = '%s';
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
			$format[] = '%s';
		}

		if ( isset( $data['conversion_window_days'] ) ) {
			$update_data['conversion_window_days'] = (int) $data['conversion_window_days'];
			$format[] = '%d';
		}

		if ( isset( $data['attribution_model'] ) ) {
			$update_data['attribution_model'] = sanitize_text_field( $data['attribution_model'] );
			$format[] = '%s';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			[ 'id' => $funnel_id ],
			$format,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete funnel and its stages
	 *
	 * @param int $funnel_id Funnel ID
	 * @return bool True on success, false on failure
	 */
	public static function delete_funnel( int $funnel_id ): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$stages_table_name = self::get_stages_table_name();

		// Delete stages first (if foreign key constraint fails)
		$wpdb->delete( $stages_table_name, [ 'funnel_id' => $funnel_id ], [ '%d' ] );

		// Delete funnel
		$result = $wpdb->delete( $table_name, [ 'id' => $funnel_id ], [ '%d' ] );

		return $result !== false;
	}

	/**
	 * Delete funnel stage
	 *
	 * @param int $stage_id Stage ID
	 * @return bool True on success, false on failure
	 */
	public static function delete_stage( int $stage_id ): bool {
		global $wpdb;

		$table_name = self::get_stages_table_name();
		$result = $wpdb->delete( $table_name, [ 'id' => $stage_id ], [ '%d' ] );

		return $result !== false;
	}

	/**
	 * Count total number of funnels
	 *
	 * @return int
	 */
	public static function count_funnels(): int {
		global $wpdb;

		$table_name = self::get_table_name();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );

		return (int) $count;
	}

	/**
	 * Count active funnels
	 *
	 * @return int
	 */
	public static function count_active_funnels(): int {
		global $wpdb;

		$table_name = self::get_table_name();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}` WHERE status = 'active'" );

		return (int) $count;
	}

	/**
	 * Get all funnels
	 *
	 * @return array
	 */
        public static function get_all_funnels(): array {
                global $wpdb;

                $table_name = self::get_table_name();
                $results = $wpdb->get_results( "SELECT * FROM `{$table_name}` ORDER BY created_at DESC", ARRAY_A );

		return $results ?: [];
	}
}
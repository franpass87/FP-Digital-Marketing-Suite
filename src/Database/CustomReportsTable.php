<?php
/**
 * Custom Reports Table Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * Handles database operations for custom reports configuration
 */
class CustomReportsTable {

        /**
         * Table name
         */
        public const TABLE_NAME = 'fp_dms_custom_reports';

        /**
         * Allowed columns for ordering queries.
         *
         * @var string[]
         */
        private const ORDERABLE_COLUMNS = [
                'id',
                'client_id',
                'report_name',
                'report_description',
                'time_period',
                'selected_kpis',
                'report_frequency',
                'email_recipients',
                'last_generated',
                'auto_send',
                'status',
                'created_at',
                'updated_at',
        ];

        /**
         * Sanitize ORDER BY parameters for SQL queries.
         *
         * @param string $order_by Column to order by.
         * @param string $order_direction Order direction (ASC/DESC).
         * @param string $default_order_by Default column when invalid column provided.
         * @param string $default_order_direction Default direction when invalid direction provided.
         * @return array{0:string,1:string}
         */
        private static function sanitize_order_parameters( string $order_by, string $order_direction, string $default_order_by, string $default_order_direction ): array {
                $default_order_by = strtolower( $default_order_by );
                if ( ! in_array( $default_order_by, self::ORDERABLE_COLUMNS, true ) ) {
                        $default_order_by = 'created_at';
                }

                $order_by = strtolower( $order_by );
                if ( ! in_array( $order_by, self::ORDERABLE_COLUMNS, true ) ) {
                        $order_by = $default_order_by;
                }

                $allowed_directions = ['ASC', 'DESC'];

                $default_order_direction = strtoupper( $default_order_direction );
                if ( ! in_array( $default_order_direction, $allowed_directions, true ) ) {
                        $default_order_direction = 'DESC';
                }

                $order_direction = strtoupper( $order_direction );
                if ( ! in_array( $order_direction, $allowed_directions, true ) ) {
                        $order_direction = $default_order_direction;
                }

                return [ $order_by, $order_direction ];
        }

        /**
         * Get the full table name with WordPress prefix
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Check if table exists
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		return $wpdb->get_var( $query ) === $table_name;
	}

	/**
	 * Create the custom reports table
	 *
	 * @return bool
	 */
        public static function create_table(): bool {
                global $wpdb;

                $table_name = self::get_table_name();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			client_id bigint(20) NOT NULL,
			report_name varchar(255) NOT NULL,
			report_description text,
			time_period varchar(50) NOT NULL DEFAULT '30_days',
			selected_kpis longtext,
			report_frequency varchar(50) DEFAULT 'manual',
			email_recipients longtext,
			last_generated datetime,
			auto_send tinyint(1) DEFAULT 0,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_client_id (client_id),
			INDEX idx_status (status),
			INDEX idx_time_period (time_period)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

                return true;
        }

        /**
         * Drop the custom reports table (uninstall helper).
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
	 * Insert a new custom report configuration
	 *
	 * @param array $data Report configuration data
	 * @return int|false Report ID on success, false on failure
	 */
	public static function insert_report( array $data ) {
		global $wpdb;

		$defaults = [
			'report_name' => '',
			'report_description' => '',
			'time_period' => '30_days',
			'selected_kpis' => wp_json_encode( [] ),
			'report_frequency' => 'manual',
			'email_recipients' => wp_json_encode( [] ),
			'auto_send' => 0,
			'status' => 'active',
		];

		$data = wp_parse_args( $data, $defaults );

		// Ensure JSON fields are properly encoded
		if ( is_array( $data['selected_kpis'] ) ) {
			$data['selected_kpis'] = wp_json_encode( $data['selected_kpis'] );
		}
		if ( is_array( $data['email_recipients'] ) ) {
			$data['email_recipients'] = wp_json_encode( $data['email_recipients'] );
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			$data,
			[
				'%d', // client_id
				'%s', // report_name
				'%s', // report_description
				'%s', // time_period
				'%s', // selected_kpis
				'%s', // report_frequency
				'%s', // email_recipients
				'%d', // auto_send
				'%s', // status
			]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get custom reports for a client
	 *
	 * @param int $client_id Client ID
	 * @param array $args Optional query arguments
	 * @return array
	 */
	public static function get_client_reports( int $client_id, array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status' => 'active',
			'limit' => 50,
			'offset' => 0,
			'order_by' => 'created_at',
			'order' => 'DESC',
		];

                $args = wp_parse_args( $args, $defaults );

                [ $order_by, $order_direction ] = self::sanitize_order_parameters(
                        (string) $args['order_by'],
                        (string) $args['order'],
                        $defaults['order_by'],
                        $defaults['order']
                );

                $where_clauses = ['client_id = %d'];
		$where_values = [$client_id];

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_sql = implode( ' AND ', $where_clauses );
                $order_sql = sprintf( 'ORDER BY %s %s', $order_by, $order_direction );
                $limit_sql = sprintf( 'LIMIT %d OFFSET %d', max( 0, (int) $args['limit'] ), max( 0, (int) $args['offset'] ) );

		$query = $wpdb->prepare(
			"SELECT * FROM " . self::get_table_name() . " WHERE {$where_sql} {$order_sql} {$limit_sql}",
			...$where_values
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Decode JSON fields
		foreach ( $results as &$result ) {
			$result['selected_kpis'] = json_decode( $result['selected_kpis'], true ) ?: [];
			$result['email_recipients'] = json_decode( $result['email_recipients'], true ) ?: [];
		}

		return $results;
	}

	/**
	 * Get all custom reports
	 *
	 * @param array $args Optional query arguments
	 * @return array
	 */
	public static function get_all_reports( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status' => null,
			'limit' => 100,
			'offset' => 0,
			'order_by' => 'created_at',
			'order' => 'DESC',
		];

                $args = wp_parse_args( $args, $defaults );

                [ $order_by, $order_direction ] = self::sanitize_order_parameters(
                        (string) $args['order_by'],
                        (string) $args['order'],
                        $defaults['order_by'],
                        $defaults['order']
                );

                $where_clauses = [];
		$where_values = [];

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_sql = empty( $where_clauses ) ? '' : 'WHERE ' . implode( ' AND ', $where_clauses );
                $order_sql = sprintf( 'ORDER BY %s %s', $order_by, $order_direction );
                $limit_sql = sprintf( 'LIMIT %d OFFSET %d', max( 0, (int) $args['limit'] ), max( 0, (int) $args['offset'] ) );

		$query = "SELECT * FROM " . self::get_table_name() . " {$where_sql} {$order_sql} {$limit_sql}";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, ...$where_values );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Decode JSON fields
		foreach ( $results as &$result ) {
			$result['selected_kpis'] = json_decode( $result['selected_kpis'], true ) ?: [];
			$result['email_recipients'] = json_decode( $result['email_recipients'], true ) ?: [];
		}

		return $results;
	}

	/**
	 * Get a single custom report by ID
	 *
	 * @param int $report_id Report ID
	 * @return array|null
	 */
	public static function get_report( int $report_id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
				$report_id
			),
			ARRAY_A
		);

		if ( $result ) {
			$result['selected_kpis'] = json_decode( $result['selected_kpis'], true ) ?: [];
			$result['email_recipients'] = json_decode( $result['email_recipients'], true ) ?: [];
		}

		return $result;
	}

	/**
	 * Update a custom report
	 *
	 * @param int $report_id Report ID
	 * @param array $data Update data
	 * @return bool
	 */
	public static function update_report( int $report_id, array $data ): bool {
		global $wpdb;

		// Ensure JSON fields are properly encoded
		if ( isset( $data['selected_kpis'] ) && is_array( $data['selected_kpis'] ) ) {
			$data['selected_kpis'] = wp_json_encode( $data['selected_kpis'] );
		}
		if ( isset( $data['email_recipients'] ) && is_array( $data['email_recipients'] ) ) {
			$data['email_recipients'] = wp_json_encode( $data['email_recipients'] );
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$data,
			['id' => $report_id],
			null,
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Delete a custom report
	 *
	 * @param int $report_id Report ID
	 * @return bool
	 */
	public static function delete_report( int $report_id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			self::get_table_name(),
			['id' => $report_id],
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Get reports that need to be generated automatically
	 *
	 * @return array
	 */
	public static function get_scheduled_reports(): array {
		global $wpdb;

		$query = "
			SELECT * FROM " . self::get_table_name() . " 
			WHERE status = 'active' 
			AND report_frequency != 'manual' 
			AND auto_send = 1
			ORDER BY last_generated ASC
		";

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Decode JSON fields
		foreach ( $results as &$result ) {
			$result['selected_kpis'] = json_decode( $result['selected_kpis'], true ) ?: [];
			$result['email_recipients'] = json_decode( $result['email_recipients'], true ) ?: [];
		}

		return $results;
	}

	/**
	 * Mark report as generated
	 *
	 * @param int $report_id Report ID
	 * @return bool
	 */
	public static function mark_as_generated( int $report_id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			self::get_table_name(),
			['last_generated' => current_time( 'mysql' )],
			['id' => $report_id],
			['%s'],
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Get available time periods
	 *
	 * @return array
	 */
	public static function get_available_time_periods(): array {
		return [
			'7_days' => __( 'Ultimi 7 giorni', 'fp-digital-marketing' ),
			'14_days' => __( 'Ultimi 14 giorni', 'fp-digital-marketing' ),
			'30_days' => __( 'Ultimi 30 giorni', 'fp-digital-marketing' ),
			'90_days' => __( 'Ultimi 90 giorni', 'fp-digital-marketing' ),
			'6_months' => __( 'Ultimi 6 mesi', 'fp-digital-marketing' ),
			'12_months' => __( 'Ultimo anno', 'fp-digital-marketing' ),
			'custom' => __( 'Periodo personalizzato', 'fp-digital-marketing' ),
		];
	}

	/**
	 * Get available report frequencies
	 *
	 * @return array
	 */
	public static function get_available_frequencies(): array {
		return [
			'manual' => __( 'Manuale', 'fp-digital-marketing' ),
			'daily' => __( 'Giornaliero', 'fp-digital-marketing' ),
			'weekly' => __( 'Settimanale', 'fp-digital-marketing' ),
			'monthly' => __( 'Mensile', 'fp-digital-marketing' ),
			'quarterly' => __( 'Trimestrale', 'fp-digital-marketing' ),
		];
	}

	/**
	 * Calculate date range from time period
	 *
	 * @param string $time_period Time period string
	 * @param string|null $custom_start Custom start date (for custom period)
	 * @param string|null $custom_end Custom end date (for custom period)
	 * @return array Array with 'start' and 'end' dates
	 */
	public static function calculate_date_range( string $time_period, ?string $custom_start = null, ?string $custom_end = null ): array {
		$end_date = current_time( 'Y-m-d' );
		
		switch ( $time_period ) {
			case '7_days':
				$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
				break;
			case '14_days':
				$start_date = date( 'Y-m-d', strtotime( '-14 days' ) );
				break;
			case '30_days':
				$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
				break;
			case '90_days':
				$start_date = date( 'Y-m-d', strtotime( '-90 days' ) );
				break;
			case '6_months':
				$start_date = date( 'Y-m-d', strtotime( '-6 months' ) );
				break;
			case '12_months':
				$start_date = date( 'Y-m-d', strtotime( '-12 months' ) );
				break;
			case 'custom':
				$start_date = $custom_start ?: date( 'Y-m-d', strtotime( '-30 days' ) );
				$end_date = $custom_end ?: current_time( 'Y-m-d' );
				break;
			default:
				$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}

		return [
			'start' => $start_date,
			'end' => $end_date,
		];
	}
}
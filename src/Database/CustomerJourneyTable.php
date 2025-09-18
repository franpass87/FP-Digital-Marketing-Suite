<?php
/**
 * Customer Journey Table Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

/**
 * Customer Journey table management class
 * 
 * Handles creation and management of the customer journey tracking table
 * for storing user journey events and sessions.
 */
class CustomerJourneyTable {

	/**
	 * Table name for journey events
	 *
	 * @var string
	 */
	private static string $table_name = 'fp_dms_customer_journeys';

	/**
	 * Table name for journey sessions
	 *
	 * @var string
	 */
	private static string $sessions_table_name = 'fp_dms_journey_sessions';

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
	 * Get the full sessions table name with WordPress prefix
	 *
	 * @return string Full sessions table name
	 */
	public static function get_sessions_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$sessions_table_name;
	}

	/**
	 * Check if the journey events table exists
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
	 * Check if the journey sessions table exists
	 *
	 * @return bool True if table exists
	 */
	public static function sessions_table_exists(): bool {
		global $wpdb;
		$table_name = self::get_sessions_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( 
			"SHOW TABLES LIKE %s", 
			$table_name 
		) );
		return $result === $table_name;
	}

	/**
	 * Create the customer journey events table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			user_id varchar(255) DEFAULT NULL,
			session_id varchar(255) NOT NULL,
			event_type varchar(100) NOT NULL,
			event_name varchar(255) NOT NULL,
			page_url text DEFAULT NULL,
			referrer_url text DEFAULT NULL,
			utm_source varchar(255) DEFAULT NULL,
			utm_medium varchar(255) DEFAULT NULL,
			utm_campaign varchar(255) DEFAULT NULL,
			utm_term varchar(255) DEFAULT NULL,
			utm_content varchar(255) DEFAULT NULL,
			device_type varchar(50) DEFAULT NULL,
			browser varchar(100) DEFAULT NULL,
			operating_system varchar(100) DEFAULT NULL,
			country varchar(100) DEFAULT NULL,
			region varchar(100) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			event_value decimal(10,2) DEFAULT 0.00,
			currency varchar(3) DEFAULT 'EUR',
			custom_attributes longtext DEFAULT NULL,
			timestamp datetime NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_client_id (client_id),
			KEY idx_user_id (user_id),
			KEY idx_session_id (session_id),
			KEY idx_event_type (event_type),
			KEY idx_timestamp (timestamp),
			KEY idx_utm_source (utm_source),
			KEY idx_utm_campaign (utm_campaign)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Create the journey sessions table
	 *
	 * @return bool True on success, false on failure
	 */
        public static function create_sessions_table(): bool {
                global $wpdb;

                $table_name = self::get_sessions_table_name();
                $charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			session_id varchar(255) NOT NULL,
			user_id varchar(255) DEFAULT NULL,
			first_event_timestamp datetime NOT NULL,
			last_event_timestamp datetime NOT NULL,
			total_events int(11) DEFAULT 1,
			total_pageviews int(11) DEFAULT 0,
			total_value decimal(10,2) DEFAULT 0.00,
			currency varchar(3) DEFAULT 'EUR',
			entry_page text DEFAULT NULL,
			exit_page text DEFAULT NULL,
			acquisition_source varchar(255) DEFAULT NULL,
			acquisition_medium varchar(255) DEFAULT NULL,
			acquisition_campaign varchar(255) DEFAULT NULL,
			device_type varchar(50) DEFAULT NULL,
			browser varchar(100) DEFAULT NULL,
			operating_system varchar(100) DEFAULT NULL,
			country varchar(100) DEFAULT NULL,
			region varchar(100) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			converted boolean DEFAULT FALSE,
			conversion_value decimal(10,2) DEFAULT 0.00,
			session_duration_seconds int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_session_id (session_id),
			KEY idx_client_id (client_id),
			KEY idx_user_id (user_id),
			KEY idx_first_event_timestamp (first_event_timestamp),
			KEY idx_acquisition_source (acquisition_source),
			KEY idx_converted (converted)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

                return self::sessions_table_exists();
        }

        /**
         * Drop the customer journeys events table.
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
         * Drop the customer journey sessions table.
         *
         * @return bool True on success, false on failure
         */
        public static function drop_sessions_table(): bool {
                global $wpdb;

                $table_name = self::get_sessions_table_name();
                $result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

                return $result !== false;
        }

	/**
	 * Insert a journey event
	 *
	 * @param array $data Event data
	 * @return int|false Event ID on success, false on failure
	 */
	public static function insert_event( array $data ) {
		global $wpdb;

		$table_name = self::get_table_name();
		
		$result = $wpdb->insert(
			$table_name,
			[
				'client_id' => (int) $data['client_id'],
				'user_id' => sanitize_text_field( $data['user_id'] ?? '' ) ?: null,
				'session_id' => sanitize_text_field( $data['session_id'] ),
				'event_type' => sanitize_text_field( $data['event_type'] ),
				'event_name' => sanitize_text_field( $data['event_name'] ),
				'page_url' => esc_url_raw( $data['page_url'] ?? '' ) ?: null,
				'referrer_url' => esc_url_raw( $data['referrer_url'] ?? '' ) ?: null,
				'utm_source' => sanitize_text_field( $data['utm_source'] ?? '' ) ?: null,
				'utm_medium' => sanitize_text_field( $data['utm_medium'] ?? '' ) ?: null,
				'utm_campaign' => sanitize_text_field( $data['utm_campaign'] ?? '' ) ?: null,
				'utm_term' => sanitize_text_field( $data['utm_term'] ?? '' ) ?: null,
				'utm_content' => sanitize_text_field( $data['utm_content'] ?? '' ) ?: null,
				'device_type' => sanitize_text_field( $data['device_type'] ?? '' ) ?: null,
				'browser' => sanitize_text_field( $data['browser'] ?? '' ) ?: null,
				'operating_system' => sanitize_text_field( $data['operating_system'] ?? '' ) ?: null,
				'country' => sanitize_text_field( $data['country'] ?? '' ) ?: null,
				'region' => sanitize_text_field( $data['region'] ?? '' ) ?: null,
				'city' => sanitize_text_field( $data['city'] ?? '' ) ?: null,
				'event_value' => isset( $data['event_value'] ) ? (float) $data['event_value'] : 0.00,
				'currency' => sanitize_text_field( $data['currency'] ?? 'EUR' ),
				'custom_attributes' => maybe_serialize( $data['custom_attributes'] ?? [] ),
				'timestamp' => $data['timestamp'] ?? current_time( 'mysql' ),
			],
			[
				'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s'
			]
		);

		if ( $result ) {
			// Update or create session record
			self::update_session_from_event( $data );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update session record based on new event
	 *
	 * @param array $event_data Event data
	 * @return void
	 */
	private static function update_session_from_event( array $event_data ): void {
		global $wpdb;

		$sessions_table = self::get_sessions_table_name();
		$session_id = sanitize_text_field( $event_data['session_id'] );
		$timestamp = $event_data['timestamp'] ?? current_time( 'mysql' );

		// Check if session exists
		$existing_session = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $sessions_table WHERE session_id = %s",
			$session_id
		), ARRAY_A );

		if ( $existing_session ) {
			// Update existing session
			$update_data = [
				'last_event_timestamp' => $timestamp,
				'total_events' => (int) $existing_session['total_events'] + 1,
			];

			if ( $event_data['event_type'] === 'pageview' ) {
				$update_data['total_pageviews'] = (int) $existing_session['total_pageviews'] + 1;
				$update_data['exit_page'] = esc_url_raw( $event_data['page_url'] ?? '' );
			}

			if ( isset( $event_data['event_value'] ) && $event_data['event_value'] > 0 ) {
				$update_data['total_value'] = (float) $existing_session['total_value'] + (float) $event_data['event_value'];
			}

			// Calculate session duration
			$first_timestamp = strtotime( $existing_session['first_event_timestamp'] );
			$last_timestamp = strtotime( $timestamp );
			$update_data['session_duration_seconds'] = max( 0, $last_timestamp - $first_timestamp );

			$wpdb->update(
				$sessions_table,
				$update_data,
				[ 'session_id' => $session_id ],
				[ '%s', '%d', '%d', '%s', '%f', '%d' ],
				[ '%s' ]
			);
		} else {
			// Create new session
			$session_data = [
				'client_id' => (int) $event_data['client_id'],
				'session_id' => $session_id,
				'user_id' => sanitize_text_field( $event_data['user_id'] ?? '' ) ?: null,
				'first_event_timestamp' => $timestamp,
				'last_event_timestamp' => $timestamp,
				'total_events' => 1,
				'total_pageviews' => $event_data['event_type'] === 'pageview' ? 1 : 0,
				'total_value' => isset( $event_data['event_value'] ) ? (float) $event_data['event_value'] : 0.00,
				'currency' => sanitize_text_field( $event_data['currency'] ?? 'EUR' ),
				'entry_page' => esc_url_raw( $event_data['page_url'] ?? '' ) ?: null,
				'exit_page' => esc_url_raw( $event_data['page_url'] ?? '' ) ?: null,
				'acquisition_source' => sanitize_text_field( $event_data['utm_source'] ?? '' ) ?: null,
				'acquisition_medium' => sanitize_text_field( $event_data['utm_medium'] ?? '' ) ?: null,
				'acquisition_campaign' => sanitize_text_field( $event_data['utm_campaign'] ?? '' ) ?: null,
				'device_type' => sanitize_text_field( $event_data['device_type'] ?? '' ) ?: null,
				'browser' => sanitize_text_field( $event_data['browser'] ?? '' ) ?: null,
				'operating_system' => sanitize_text_field( $event_data['operating_system'] ?? '' ) ?: null,
				'country' => sanitize_text_field( $event_data['country'] ?? '' ) ?: null,
				'region' => sanitize_text_field( $event_data['region'] ?? '' ) ?: null,
				'city' => sanitize_text_field( $event_data['city'] ?? '' ) ?: null,
			];

			$wpdb->insert(
				$sessions_table,
				$session_data,
				[
					'%d', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s',
					'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
				]
			);
		}
	}

	/**
	 * Get journey events for a user/session
	 *
	 * @param array $filters Filters array
	 * @param int   $limit   Limit results
	 * @param int   $offset  Offset for pagination
	 * @return array Array of journey events
	 */
	public static function get_journey_events( array $filters = [], int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table_name = self::get_table_name();
		$where_conditions = [ '1=1' ];
		$where_values = [];

		if ( ! empty( $filters['client_id'] ) ) {
			$where_conditions[] = 'client_id = %d';
			$where_values[] = (int) $filters['client_id'];
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where_conditions[] = 'user_id = %s';
			$where_values[] = $filters['user_id'];
		}

		if ( ! empty( $filters['session_id'] ) ) {
			$where_conditions[] = 'session_id = %s';
			$where_values[] = $filters['session_id'];
		}

		if ( ! empty( $filters['event_type'] ) ) {
			$where_conditions[] = 'event_type = %s';
			$where_values[] = $filters['event_type'];
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where_conditions[] = 'timestamp >= %s';
			$where_values[] = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where_conditions[] = 'timestamp <= %s';
			$where_values[] = $filters['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );
		$limit_clause = $limit > 0 ? $wpdb->prepare( 'LIMIT %d OFFSET %d', $limit, $offset ) : '';

		$sql = "SELECT * FROM $table_name WHERE $where_clause ORDER BY timestamp ASC $limit_clause";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, ...$where_values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Unserialize custom attributes
		foreach ( $results as &$event ) {
			$event['custom_attributes'] = maybe_unserialize( $event['custom_attributes'] );
		}

		return $results ?: [];
	}

	/**
	 * Get journey sessions
	 *
	 * @param array $filters Filters array
	 * @param int   $limit   Limit results
	 * @param int   $offset  Offset for pagination
	 * @return array Array of journey sessions
	 */
	public static function get_journey_sessions( array $filters = [], int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table_name = self::get_sessions_table_name();
		$where_conditions = [ '1=1' ];
		$where_values = [];

		if ( ! empty( $filters['client_id'] ) ) {
			$where_conditions[] = 'client_id = %d';
			$where_values[] = (int) $filters['client_id'];
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where_conditions[] = 'user_id = %s';
			$where_values[] = $filters['user_id'];
		}

		if ( ! empty( $filters['converted'] ) ) {
			$where_conditions[] = 'converted = %d';
			$where_values[] = $filters['converted'] === 'yes' ? 1 : 0;
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where_conditions[] = 'first_event_timestamp >= %s';
			$where_values[] = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where_conditions[] = 'first_event_timestamp <= %s';
			$where_values[] = $filters['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );
		$limit_clause = $limit > 0 ? $wpdb->prepare( 'LIMIT %d OFFSET %d', $limit, $offset ) : '';

		$sql = "SELECT * FROM $table_name WHERE $where_clause ORDER BY first_event_timestamp DESC $limit_clause";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, ...$where_values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Mark session as converted
	 *
	 * @param string $session_id Session ID
	 * @param float  $conversion_value Conversion value
	 * @return bool True on success, false on failure
	 */
	public static function mark_session_converted( string $session_id, float $conversion_value = 0.00 ): bool {
		global $wpdb;

		$table_name = self::get_sessions_table_name();

		$result = $wpdb->update(
			$table_name,
			[
				'converted' => 1,
				'conversion_value' => $conversion_value,
			],
			[ 'session_id' => $session_id ],
			[ '%d', '%f' ],
			[ '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Get conversion funnel data
	 *
	 * @param array $funnel_steps Array of event types for funnel steps
	 * @param array $filters Additional filters
	 * @return array Funnel conversion data
	 */
	public static function get_funnel_conversion_data( array $funnel_steps, array $filters = [] ): array {
		global $wpdb;

		$events_table = self::get_table_name();
		$sessions_table = self::get_sessions_table_name();

		$where_conditions = [ '1=1' ];
		$where_values = [];

		if ( ! empty( $filters['client_id'] ) ) {
			$where_conditions[] = 'e.client_id = %d';
			$where_values[] = (int) $filters['client_id'];
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where_conditions[] = 'e.timestamp >= %s';
			$where_values[] = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where_conditions[] = 'e.timestamp <= %s';
			$where_values[] = $filters['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );
		$funnel_data = [];

		foreach ( $funnel_steps as $index => $step_event_type ) {
			$step_name = "step_" . ($index + 1);
			
			// Count unique sessions that completed this step
			$sql = "
				SELECT COUNT(DISTINCT e.session_id) as count
				FROM $events_table e
				WHERE e.event_type = %s AND $where_clause
			";

			$step_values = array_merge( [ $step_event_type ], $where_values );
			$step_count = $wpdb->get_var( $wpdb->prepare( $sql, ...$step_values ) );

			$funnel_data[] = [
				'step' => $index + 1,
				'event_type' => $step_event_type,
				'sessions' => (int) $step_count,
				'conversion_rate' => $index === 0 ? 100 : 0, // Will be calculated later
			];
		}

		// Calculate conversion rates
		$first_step_count = $funnel_data[0]['sessions'] ?? 1;
		foreach ( $funnel_data as &$step ) {
			if ( $step['step'] > 1 ) {
				$step['conversion_rate'] = $first_step_count > 0 
					? round( ($step['sessions'] / $first_step_count) * 100, 2 )
					: 0;
			}
		}

		return $funnel_data;
	}

	/**
	 * Get recent customer journeys
	 *
	 * @param int $limit Number of recent journeys to retrieve
	 * @return array Array of recent customer journey objects
	 */
	public static function get_recent_journeys( int $limit = 10 ): array {
		global $wpdb;

		$sessions_table = self::get_sessions_table_name();
		
		$sql = $wpdb->prepare(
			"SELECT * FROM {$sessions_table} 
			 ORDER BY first_event_timestamp DESC 
			 LIMIT %d",
			$limit
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );
		$journeys = [];

		foreach ( $results as $session_data ) {
			// Create simplified journey objects from session data
			$journeys[] = (object) [
				'id' => $session_data['id'],
				'session_id' => $session_data['session_id'],
				'created_at' => $session_data['first_event_timestamp'],
				'touchpoints' => [], // Would be populated from events table
			];
		}

		return $journeys;
	}

	/**
	 * Count total sessions
	 *
	 * @return int
	 */
	public static function count_total_sessions(): int {
		global $wpdb;

		$sessions_table = self::get_sessions_table_name();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$sessions_table}" );

		return (int) $count;
	}
}
<?php
/**
 * Conversion Events Table
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Database;

use FP\DigitalMarketing\Database\DatabaseUtils;

/**
 * Conversion Events Table class
 *
 * Manages the database table for storing conversion events and goals.
 */
class ConversionEventsTable {

		/**
		 * Option key used when the database table is not available.
		 */
	private const OPTION_KEY = 'fp_dms_conversion_events';

		/**
		 * Cached flag indicating if the wpdb connection is usable.
		 *
		 * @var bool|null
		 */
	private static ?bool $use_option_storage = null;

		/**
		 * Table name
		 */
	public const TABLE_NAME = 'fp_conversion_events';

		/**
		 * Determine if the conversion events table is using option storage.
		 *
		 * @return bool
		 */
	public static function is_using_option_storage(): bool {
			return self::using_option_storage();
	}

	/**
	 * Get full table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
	public static function get_table_name(): string {
			global $wpdb;
			return DatabaseUtils::resolve_table_name( $wpdb, self::TABLE_NAME );
	}

	/**
	 * Create the conversion events table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		if ( self::using_option_storage() ) {
				// Ensure the option storage bucket exists so that site health checks
				// can verify the "table" availability even before any conversion
				// has been recorded.
			if ( null === get_option( self::OPTION_KEY, null ) ) {
				update_option( self::OPTION_KEY, [], false );
			}

				return true;
		}

			global $wpdb;

			$table_name      = self::get_table_name();
			$charset_collate = DatabaseUtils::get_charset_collate( $wpdb );

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

			return DatabaseUtils::run_schema_delta( $sql, $wpdb );
	}

	/**
	 * Check if table exists
	 *
	 * @return bool True if table exists
	 */
	public static function table_exists(): bool {
		if ( self::using_option_storage() ) {
				$records = get_option( self::OPTION_KEY, null );

			if ( null === $records ) {
				update_option( self::OPTION_KEY, [], false );
				$records = [];
			}

				return is_array( $records );
		}

			global $wpdb;
			$table_name = self::get_table_name();

		if ( ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
				return false;
		}

			$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			return $result === $table_name;
	}

		/**
		 * Determine if the physical database table exists regardless of fallback mode.
		 *
		 * The plugin can fall back to option storage in limited environments, but the
		 * Site Health checks should still verify the original database table so that
		 * administrators can address missing schema issues.
		 *
		 * @return bool True when the physical table is present.
		 */
	public static function database_table_exists(): bool {
			global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
				return false;
		}

			$table_name = self::get_table_name();
			$result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

			return $result === $table_name;
	}

		/**
		 * Provide a human readable storage identifier for health checks and diagnostics.
		 *
		 * When the plugin cannot rely on a proper wpdb instance it stores conversion
		 * events inside the options table. Site health checks need to reference the
		 * correct storage backend to avoid reporting false positives.
		 *
		 * @return string Storage identifier (table name or option key).
		 */
	public static function get_storage_identifier(): string {
			$table_name = self::get_table_name();

		if ( self::using_option_storage() ) {
				return $table_name . ' (option storage)';
		}

			return $table_name;
	}

	/**
	 * Drop the table (for uninstall)
	 *
	 * @return bool True on success
	 */
	public static function drop_table(): bool {
		if ( self::using_option_storage() ) {
				delete_option( self::OPTION_KEY );
				return true;
		}

			global $wpdb;
			$table_name = self::get_table_name();

		if ( ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

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
			$data = wp_parse_args(
				$event_data,
				[
					'event_value'  => 0.00,
					'currency'     => 'EUR',
					'is_duplicate' => 0,
					'created_at'   => current_time( 'mysql' ),
					'event_name'   => '',
					'processed_at' => null,
				]
			);

		if ( self::using_option_storage() ) {
				return self::insert_event_into_option_storage( $data );
		}

			global $wpdb;

			$table_name = self::get_table_name();

		if ( isset( $data['event_attributes'] ) && is_array( $data['event_attributes'] ) ) {
				$data['event_attributes'] = wp_json_encode( $data['event_attributes'] );
		}

		if ( ! method_exists( $wpdb, 'insert' ) ) {
			return false;
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
		if ( self::using_option_storage() ) {
				return self::update_option_storage_event( $event_id, $event_data );
		}

			global $wpdb;

			$table_name = self::get_table_name();

		if ( isset( $event_data['event_attributes'] ) && is_array( $event_data['event_attributes'] ) ) {
				$event_data['event_attributes'] = wp_json_encode( $event_data['event_attributes'] );
		}

		if ( ! method_exists( $wpdb, 'update' ) ) {
			return false;
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
		 * Get a single conversion event by ID
		 *
		 * @param int $event_id Event ID
		 * @return array|null Event data array or null if not found
		 */
	public static function get_event_by_id( int $event_id ): ?array {
		if ( self::using_option_storage() ) {
			foreach ( self::get_option_events() as $event ) {
				if ( (int) $event['id'] === $event_id ) {
						return $event;
				}
			}

				return null;
		}

			global $wpdb;

		if ( ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_row' ) ) {
			return null;
		}

			$table_name = self::get_table_name();
			$sql        = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d LIMIT 1",
				$event_id
			);

			$result = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! $result ) {
				return null;
		}

		if ( isset( $result['event_attributes'] ) && is_string( $result['event_attributes'] ) && '' !== $result['event_attributes'] ) {
				$decoded_attributes = json_decode( $result['event_attributes'], true );

			if ( is_array( $decoded_attributes ) ) {
					$result['event_attributes'] = $decoded_attributes;
			}
		}

			return $result;
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
		if ( self::using_option_storage() ) {
				return self::get_events_from_option_storage( $criteria, $limit, $offset );
		}

			global $wpdb;

		if ( ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return [];
		}

			$table_name    = self::get_table_name();
			$where_clauses = [];
			$where_values  = [];

		if ( isset( $criteria['client_id'] ) ) {
				$where_clauses[] = 'client_id = %d';
				$where_values[]  = $criteria['client_id'];
		}

		if ( isset( $criteria['event_type'] ) ) {
			if ( is_array( $criteria['event_type'] ) ) {
					$placeholders    = implode( ',', array_fill( 0, count( $criteria['event_type'] ), '%s' ) );
					$where_clauses[] = "event_type IN ($placeholders)";
					$where_values    = array_merge( $where_values, $criteria['event_type'] );
			} else {
					$where_clauses[] = 'event_type = %s';
					$where_values[]  = $criteria['event_type'];
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
					$placeholders    = implode( ',', array_fill( 0, count( $user_ids ), '%s' ) );
					$where_clauses[] = "user_id IN ($placeholders)";
					$where_values    = array_merge( $where_values, $user_ids );
				}
			} else {
					$where_clauses[] = 'user_id = %s';
					$where_values[]  = sanitize_text_field( (string) $criteria['user_id'] );
			}
		}

		if ( isset( $criteria['source'] ) ) {
			if ( is_array( $criteria['source'] ) ) {
					$placeholders    = implode( ',', array_fill( 0, count( $criteria['source'] ), '%s' ) );
					$where_clauses[] = "source IN ($placeholders)";
					$where_values    = array_merge( $where_values, $criteria['source'] );
			} else {
					$where_clauses[] = 'source = %s';
					$where_values[]  = $criteria['source'];
			}
		}

		if ( isset( $criteria['event_id'] ) ) {
				$where_clauses[] = 'event_id = %s';
				$where_values[]  = $criteria['event_id'];
		}

		if ( isset( $criteria['source_event_id'] ) ) {
				$where_clauses[] = 'source_event_id = %s';
				$where_values[]  = $criteria['source_event_id'];
		}

		if ( isset( $criteria['period_start'] ) ) {
				$where_clauses[] = 'created_at >= %s';
				$where_values[]  = $criteria['period_start'];
		}

		if ( isset( $criteria['period_end'] ) ) {
				$where_clauses[] = 'created_at <= %s';
				$where_values[]  = $criteria['period_end'];
		}

		if ( isset( $criteria['utm_campaign'] ) ) {
				$where_clauses[] = 'utm_campaign = %s';
				$where_values[]  = $criteria['utm_campaign'];
		}

		if ( isset( $criteria['exclude_duplicates'] ) && $criteria['exclude_duplicates'] ) {
				$where_clauses[] = 'is_duplicate = 0';
		}

			$where_sql      = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
			$sql            = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
			$where_values[] = $limit;
			$where_values[] = $offset;

		if ( ! empty( $where_values ) ) {
				$sql = $wpdb->prepare( $sql, ...$where_values );
		}

			$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $results ) || empty( $results ) ) {
				return [];
		}

		foreach ( $results as &$result ) {
			if ( ! empty( $result['event_attributes'] ) ) {
					$decoded_attributes = json_decode( $result['event_attributes'], true );

				if ( is_array( $decoded_attributes ) ) {
					$result['event_attributes'] = $decoded_attributes;
				}
			}
		}
			unset( $result );

			return $results;
	}

	/**
	 * Get event count with filtering
	 *
	 * @param array $criteria Filter criteria
	 * @return int Total count
	 */
	public static function get_events_count( array $criteria = [] ): int {
		if ( self::using_option_storage() ) {
				return count( self::get_events_from_option_storage( $criteria, PHP_INT_MAX, 0 ) );
		}

			global $wpdb;

		if ( ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return 0;
		}

			$table_name    = self::get_table_name();
			$where_clauses = [];
			$where_values  = [];

		if ( isset( $criteria['client_id'] ) ) {
				$where_clauses[] = 'client_id = %d';
				$where_values[]  = $criteria['client_id'];
		}

		if ( isset( $criteria['event_type'] ) ) {
			if ( is_array( $criteria['event_type'] ) ) {
					$placeholders    = implode( ',', array_fill( 0, count( $criteria['event_type'] ), '%s' ) );
					$where_clauses[] = "event_type IN ($placeholders)";
					$where_values    = array_merge( $where_values, $criteria['event_type'] );
			} else {
					$where_clauses[] = 'event_type = %s';
					$where_values[]  = $criteria['event_type'];
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
					$placeholders    = implode( ',', array_fill( 0, count( $user_ids ), '%s' ) );
					$where_clauses[] = "user_id IN ($placeholders)";
					$where_values    = array_merge( $where_values, $user_ids );
				}
			} else {
					$where_clauses[] = 'user_id = %s';
					$where_values[]  = sanitize_text_field( (string) $criteria['user_id'] );
			}
		}

		if ( isset( $criteria['source'] ) ) {
			if ( is_array( $criteria['source'] ) ) {
					$placeholders    = implode( ',', array_fill( 0, count( $criteria['source'] ), '%s' ) );
					$where_clauses[] = "source IN ($placeholders)";
					$where_values    = array_merge( $where_values, $criteria['source'] );
			} else {
					$where_clauses[] = 'source = %s';
					$where_values[]  = $criteria['source'];
			}
		}

		if ( isset( $criteria['event_id'] ) ) {
				$where_clauses[] = 'event_id = %s';
				$where_values[]  = $criteria['event_id'];
		}

		if ( isset( $criteria['source_event_id'] ) ) {
				$where_clauses[] = 'source_event_id = %s';
				$where_values[]  = $criteria['source_event_id'];
		}

		if ( isset( $criteria['period_start'] ) ) {
				$where_clauses[] = 'created_at >= %s';
				$where_values[]  = $criteria['period_start'];
		}

		if ( isset( $criteria['period_end'] ) ) {
				$where_clauses[] = 'created_at <= %s';
				$where_values[]  = $criteria['period_end'];
		}

		if ( isset( $criteria['utm_campaign'] ) ) {
				$where_clauses[] = 'utm_campaign = %s';
				$where_values[]  = $criteria['utm_campaign'];
		}

		if ( isset( $criteria['exclude_duplicates'] ) && $criteria['exclude_duplicates'] ) {
				$where_clauses[] = 'is_duplicate = 0';
		}

			$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
			$sql       = "SELECT COUNT(*) FROM $table_name $where_sql";

		if ( ! empty( $where_values ) ) {
				$sql = $wpdb->prepare( $sql, ...$where_values );
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
		if ( self::using_option_storage() ) {
				$events         = self::get_option_events();
				$original_count = count( $events );

				$events = array_filter(
					$events,
					static function ( $event ) use ( $event_id ) {
								return (int) $event['id'] !== $event_id;
					}
				);

				self::store_option_events( array_values( $events ) );

				return $original_count !== count( $events );
		}

			global $wpdb;

		if ( ! method_exists( $wpdb, 'delete' ) ) {
			return false;
		}

			$table_name = self::get_table_name();
			$result     = $wpdb->delete( $table_name, [ 'id' => $event_id ], [ '%d' ] );

			return $result !== false;
	}

	/**
	 * Determine whether option storage must be used for persistence.
	 *
	 * @return bool
	 */
	private static function using_option_storage(): bool {
		if ( null === self::$use_option_storage ) {
			global $wpdb;

			$usable_wpdb = is_object( $wpdb )
				&& method_exists( $wpdb, 'prepare' )
				&& method_exists( $wpdb, 'get_results' )
				&& method_exists( $wpdb, 'get_var' )
				&& method_exists( $wpdb, 'insert' )
				&& method_exists( $wpdb, 'update' )
				&& method_exists( $wpdb, 'delete' )
				&& method_exists( $wpdb, 'query' );

			if ( $usable_wpdb && property_exists( $wpdb, 'is_mock' ) && true === $wpdb->is_mock ) {
				$usable_wpdb = false;
			}

			self::$use_option_storage = ! $usable_wpdb;
		}

		return (bool) self::$use_option_storage;
	}

	/**
	 * Retrieve the option stored events.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_option_events(): array {
		$records = get_option( self::OPTION_KEY, [] );

		return is_array( $records ) ? $records : [];
	}

	/**
	 * Persist option stored events.
	 *
	 * @param array<int, array<string, mixed>> $events Events to store.
	 */
	private static function store_option_events( array $events ): void {
		update_option( self::OPTION_KEY, array_values( $events ), false );
	}

	/**
	 * Insert an event into option storage.
	 *
	 * @param array $event_data Normalised event payload.
	 * @return int|false Event identifier.
	 */
	private static function insert_event_into_option_storage( array $event_data ) {
		$events = self::get_option_events();

		$next_id = 1;
		foreach ( $events as $event ) {
			$next_id = max( $next_id, (int) ( $event['id'] ?? 0 ) + 1 );
		}

		if ( isset( $event_data['event_attributes'] ) && is_string( $event_data['event_attributes'] ) ) {
			$decoded = json_decode( $event_data['event_attributes'], true );

			if ( is_array( $decoded ) ) {
				$event_data['event_attributes'] = $decoded;
			}
		}

		$event_data['id'] = $next_id;
		$events[]         = self::normalise_option_event( $event_data );

		self::store_option_events( $events );

		return $next_id;
	}

	/**
	 * Update an event stored in the option fallback.
	 *
	 * @param int   $event_id   Event identifier.
	 * @param array $event_data Data to merge.
	 * @return bool
	 */
	private static function update_option_storage_event( int $event_id, array $event_data ): bool {
		$events  = self::get_option_events();
		$updated = false;

		foreach ( $events as &$event ) {
			if ( (int) $event['id'] !== $event_id ) {
				continue;
			}

			$event   = array_merge( $event, self::normalise_option_event( $event_data, false ) );
			$updated = true;
			break;
		}
		unset( $event );

		if ( $updated ) {
			self::store_option_events( $events );
		}

		return $updated;
	}

	/**
	 * Fetch events from option storage applying the supported filters.
	 *
	 * @param array $criteria Filter arguments.
	 * @param int   $limit    Results limit.
	 * @param int   $offset   Results offset.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_events_from_option_storage( array $criteria, int $limit, int $offset ): array {
		$events = self::get_option_events();

		$filtered = array_filter(
			$events,
			static function ( array $event ) use ( $criteria ) {
				if ( isset( $criteria['client_id'] ) && (int) $event['client_id'] !== (int) $criteria['client_id'] ) {
					return false;
				}

				if ( isset( $criteria['event_type'] ) ) {
					$types = is_array( $criteria['event_type'] ) ? $criteria['event_type'] : [ $criteria['event_type'] ];

					if ( ! in_array( $event['event_type'], $types, true ) ) {
						return false;
					}
				}

				if ( isset( $criteria['user_id'] ) ) {
					$users = is_array( $criteria['user_id'] ) ? $criteria['user_id'] : [ $criteria['user_id'] ];
					$users = array_map( 'strval', $users );

					if ( ! in_array( (string) $event['user_id'], $users, true ) ) {
						return false;
					}
				}

				if ( isset( $criteria['source'] ) ) {
					$sources = is_array( $criteria['source'] ) ? $criteria['source'] : [ $criteria['source'] ];

					if ( ! in_array( $event['source'], $sources, true ) ) {
						return false;
					}
				}

				if ( isset( $criteria['period_start'] ) && strtotime( $event['created_at'] ) < strtotime( (string) $criteria['period_start'] ) ) {
					return false;
				}

				if ( isset( $criteria['period_end'] ) && strtotime( $event['created_at'] ) > strtotime( (string) $criteria['period_end'] ) ) {
					return false;
				}

				if ( isset( $criteria['utm_campaign'] ) && ( $event['utm_campaign'] ?? null ) !== $criteria['utm_campaign'] ) {
					return false;
				}

				if ( isset( $criteria['exclude_duplicates'] ) && $criteria['exclude_duplicates'] && ! empty( $event['is_duplicate'] ) ) {
					return false;
				}

				if ( isset( $criteria['event_id'] ) && (string) $event['event_id'] !== (string) $criteria['event_id'] ) {
					return false;
				}

				if ( isset( $criteria['source_event_id'] ) ) {
					$event_source_id = $event['source_event_id'] ?? null;

					if ( (string) $event_source_id !== (string) $criteria['source_event_id'] ) {
						return false;
					}
				}

				return true;
			}
		);

		usort(
			$filtered,
			static function ( array $a, array $b ) {
				return strtotime( $b['created_at'] ) <=> strtotime( $a['created_at'] );
			}
		);

		$paged = array_slice( $filtered, max( 0, $offset ), max( 0, $limit ) );

		return array_map( [ __CLASS__, 'normalise_option_event' ], $paged );
	}

	/**
	 * Normalise option storage data to match database structure expectations.
	 *
	 * @param array $event_data Raw event data.
	 * @param bool  $include_defaults Whether to include default values.
	 * @return array Normalised data.
	 */
	private static function normalise_option_event( array $event_data, bool $include_defaults = true ): array {
		$defaults = [];

		if ( $include_defaults ) {
			$defaults = [
				'event_value'  => 0.00,
				'currency'     => 'EUR',
				'is_duplicate' => 0,
				'event_name'   => '',
				'utm_source'   => null,
				'utm_medium'   => null,
				'utm_campaign' => null,
				'utm_term'     => null,
				'utm_content'  => null,
				'page_url'     => null,
				'referrer_url' => null,
				'ip_address'   => null,
				'user_agent'   => null,
				'processed_at' => null,
			];
		}

		$payload = array_merge( $defaults, $event_data );

		if ( isset( $payload['event_attributes'] ) && is_string( $payload['event_attributes'] ) ) {
			$decoded = json_decode( $payload['event_attributes'], true );

			if ( is_array( $decoded ) ) {
				$payload['event_attributes'] = $decoded;
			}
		}

		if ( $include_defaults ) {
			if ( ! isset( $payload['created_at'] ) || '' === (string) $payload['created_at'] ) {
				$payload['created_at'] = current_time( 'mysql' );
			}
		} elseif ( array_key_exists( 'created_at', $payload ) && '' === (string) $payload['created_at'] ) {
			$payload['created_at'] = current_time( 'mysql' );
		}

		return $payload;
	}
}

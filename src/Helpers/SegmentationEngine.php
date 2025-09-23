<?php
/**
 * Segmentation Engine
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Models\AudienceSegment;
use FP\DigitalMarketing\Models\ConversionEvent;
use FP\DigitalMarketing\Database\AudienceSegmentTable;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use DateTime;
use Exception;

/**
 * Segmentation Engine class
 * 
 * Core logic for evaluating audience segments and managing dynamic user/lead segmentation.
 */
class SegmentationEngine {

	/**
	 * Rule types
	 */
	public const RULE_TYPE_EVENT = 'event';
	public const RULE_TYPE_UTM = 'utm';
	public const RULE_TYPE_DEVICE = 'device';
	public const RULE_TYPE_GEOGRAPHY = 'geography';
	public const RULE_TYPE_BEHAVIOR = 'behavior';
	public const RULE_TYPE_VALUE = 'value';

	/**
	 * Operators
	 */
	public const OP_EQUALS = 'equals';
	public const OP_NOT_EQUALS = 'not_equals';
	public const OP_CONTAINS = 'contains';
	public const OP_NOT_CONTAINS = 'not_contains';
	public const OP_GREATER_THAN = 'greater_than';
	public const OP_LESS_THAN = 'less_than';
	public const OP_IN_LAST_DAYS = 'in_last_days';
	public const OP_NOT_IN_LAST_DAYS = 'not_in_last_days';

	/**
	 * Initialize hooks for incremental evaluation
	 *
	 * @return void
	 */
	public static function init(): void {
		// Hook into conversion event processing for incremental updates
		add_action( 'fp_dms_conversion_event_processed', [ self::class, 'evaluate_segments_for_event' ], 10, 1 );
		
		// Schedule periodic full re-evaluation
		add_action( 'wp', [ self::class, 'schedule_full_evaluation' ] );
		add_action( 'fp_dms_evaluate_all_segments', [ self::class, 'evaluate_all_segments' ] );
	}

	/**
	 * Schedule full segment evaluation if not already scheduled
	 *
	 * @return void
	 */
	public static function schedule_full_evaluation(): void {
		if ( ! wp_next_scheduled( 'fp_dms_evaluate_all_segments' ) ) {
			wp_schedule_event( time(), 'hourly', 'fp_dms_evaluate_all_segments' );
		}
	}

	/**
	 * Evaluate segments when a new conversion event is processed
	 *
	 * @param ConversionEvent $event The processed conversion event
	 * @return void
	 */
	public static function evaluate_segments_for_event( ConversionEvent $event ): void {
		$segments = AudienceSegmentTable::get_segments( [
			'client_id' => $event->get_client_id(),
			'is_active' => 1,
		] );

		foreach ( $segments as $segment_data ) {
			$segment = new AudienceSegment( $segment_data );
			self::evaluate_user_against_segment( $event->get_user_id(), $segment );
		}
	}

	/**
	 * Evaluate all active segments (full re-evaluation)
	 *
	 * @return void
	 */
	public static function evaluate_all_segments(): void {
		$segments = AudienceSegmentTable::get_segments( [ 'is_active' => 1 ], 100, 0 );

		foreach ( $segments as $segment_data ) {
			$segment = new AudienceSegment( $segment_data );
			self::evaluate_segment( $segment );
		}
	}

	/**
	 * Evaluate a specific segment against all users
	 *
	 * @param AudienceSegment $segment The segment to evaluate
	 * @return array Evaluation results with member count and errors
	 */
	public static function evaluate_segment( AudienceSegment $segment ): array {
		$results = [
			'member_count' => 0,
			'new_members' => 0,
			'removed_members' => 0,
			'errors' => [],
		];

		try {
			// Clear existing membership for fresh evaluation
			AudienceSegmentTable::clear_segment_membership( $segment->get_id() );

			// Get all unique users for this client
			$users = self::get_unique_users_for_client( $segment->get_client_id() );

			foreach ( $users as $user_id ) {
				if ( self::evaluate_user_against_segment( $user_id, $segment ) ) {
					AudienceSegmentTable::add_user_to_segment( 
						$segment->get_id(), 
						$user_id, 
						$segment->get_client_id() 
					);
					$results['member_count']++;
					$results['new_members']++;
				}
			}

			// Update segment cache
			AudienceSegmentTable::update_member_count_cache( $segment->get_id() );

		} catch ( Exception $e ) {
			$results['errors'][] = $e->getMessage();
		}

		return $results;
	}

	/**
	 * Evaluate a specific user against a segment
	 *
	 * @param string          $user_id The user ID to evaluate
	 * @param AudienceSegment $segment The segment to evaluate against
	 * @return bool True if user matches segment criteria
	 */
	public static function evaluate_user_against_segment( string $user_id, AudienceSegment $segment ): bool {
		if ( ! $user_id || ! $segment->is_active() ) {
			return false;
		}

		$rules = $segment->get_rules();
		if ( empty( $rules ) ) {
			return false;
		}

		// Get user's conversion events
		$user_events = ConversionEventsTable::get_events( [
			'client_id' => $segment->get_client_id(),
			'user_id' => $user_id,
			'exclude_duplicates' => true,
		], 1000, 0 );

		// Default to AND logic between rules (all must match)
		$logic = $rules['logic'] ?? 'AND';
		$rule_conditions = $rules['conditions'] ?? [];

		if ( empty( $rule_conditions ) ) {
			return false;
		}

		$matches = [];
		foreach ( $rule_conditions as $condition ) {
			$matches[] = self::evaluate_condition( $condition, $user_events, $user_id );
		}

		// Apply logic
		if ( $logic === 'OR' ) {
			return in_array( true, $matches, true );
		} else {
			return ! in_array( false, $matches, true );
		}
	}

	/**
	 * Evaluate a single condition against user data
	 *
	 * @param array  $condition The condition to evaluate
	 * @param array  $user_events User's conversion events
	 * @param string $user_id User ID
	 * @return bool True if condition matches
	 */
	private static function evaluate_condition( array $condition, array $user_events, string $user_id ): bool {
		$type = $condition['type'] ?? '';
		$field = $condition['field'] ?? '';
		$operator = $condition['operator'] ?? '';
		$value = $condition['value'] ?? '';

		switch ( $type ) {
			case self::RULE_TYPE_EVENT:
				return self::evaluate_event_condition( $condition, $user_events );

			case self::RULE_TYPE_UTM:
				return self::evaluate_utm_condition( $condition, $user_events );

			case self::RULE_TYPE_DEVICE:
				return self::evaluate_device_condition( $condition, $user_events );

			case self::RULE_TYPE_GEOGRAPHY:
				return self::evaluate_geography_condition( $condition, $user_events );

			case self::RULE_TYPE_BEHAVIOR:
				return self::evaluate_behavior_condition( $condition, $user_events );

			case self::RULE_TYPE_VALUE:
				return self::evaluate_value_condition( $condition, $user_events );

			default:
				return false;
		}
	}

	/**
	 * Evaluate event-based condition
	 *
	 * @param array $condition The condition
	 * @param array $user_events User events
	 * @return bool True if condition matches
	 */
	private static function evaluate_event_condition( array $condition, array $user_events ): bool {
		$event_type = $condition['field'] ?? '';
		$operator = $condition['operator'] ?? '';
		$value = $condition['value'] ?? '';

		$matching_events = array_filter( $user_events, function( $event ) use ( $event_type ) {
			return $event['event_type'] === $event_type;
		} );

		$count = count( $matching_events );

		switch ( $operator ) {
			case self::OP_EQUALS:
				return $count == intval( $value );
			case self::OP_GREATER_THAN:
				return $count > intval( $value );
			case self::OP_LESS_THAN:
				return $count < intval( $value );
			case self::OP_IN_LAST_DAYS:
				return self::has_events_in_last_days( $matching_events, intval( $value ) );
			case self::OP_NOT_IN_LAST_DAYS:
				return ! self::has_events_in_last_days( $matching_events, intval( $value ) );
			default:
				return $count > 0;
		}
	}

	/**
	 * Evaluate UTM-based condition
	 *
	 * @param array $condition The condition
	 * @param array $user_events User events
	 * @return bool True if condition matches
	 */
	private static function evaluate_utm_condition( array $condition, array $user_events ): bool {
		$utm_field = $condition['field'] ?? ''; // utm_source, utm_medium, etc.
		$operator = $condition['operator'] ?? '';
		$value = $condition['value'] ?? '';

		foreach ( $user_events as $event ) {
			$event_value = $event[ $utm_field ] ?? '';
			
			if ( self::compare_values( $event_value, $value, $operator ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate device-based condition
	 *
	 * @param array $condition The condition
	 * @param array $user_events User events
	 * @return bool True if condition matches
	 */
	private static function evaluate_device_condition( array $condition, array $user_events ): bool {
		$operator = $condition['operator'] ?? '';
		$value = $condition['value'] ?? ''; // mobile, desktop, tablet

		foreach ( $user_events as $event ) {
			$user_agent = $event['user_agent'] ?? '';
			$device_type = self::detect_device_type( $user_agent );
			
			if ( self::compare_values( $device_type, $value, $operator ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate geography-based condition
	 *
	 * @param array $condition The condition
	 * @param array $user_events User events
	 * @return bool True if condition matches
	 */
	private static function evaluate_geography_condition( array $condition, array $user_events ): bool {
		$operator = $condition['operator'] ?? '';
		$value = $condition['value'] ?? ''; // country code or region

		foreach ( $user_events as $event ) {
			$ip_address = $event['ip_address'] ?? '';
			$country = self::get_country_from_ip( $ip_address );
			
			if ( self::compare_values( $country, $value, $operator ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate behavior-based condition
	 *
	 * @param array $condition The condition
	 * @param array $user_events User events
	 * @return bool True if condition matches
	 */
	private static function evaluate_behavior_condition( array $condition, array $user_events ): bool {
		$behavior_type = $condition['field'] ?? ''; // visit_frequency, session_duration, etc.
		$operator = $condition['operator'] ?? '';
		$value = $condition['value'] ?? '';

                switch ( $behavior_type ) {
                        case 'visit_frequency':
                                $session_ids = array_filter(
                                        array_column( $user_events, 'session_id' ),
                                        static function ( $session_id ) {
                                                return null !== $session_id && '' !== $session_id;
                                        }
                                );

                                if ( empty( $session_ids ) ) {
                                        return false;
                                }

                                $unique_sessions = count( array_unique( $session_ids ) );
                                return self::compare_numeric_values( $unique_sessions, floatval( $value ), $operator );

                        case 'total_events':
                                if ( empty( $user_events ) ) {
                                        return false;
                                }

                                $total_events = count( $user_events );
                                return self::compare_numeric_values( $total_events, floatval( $value ), $operator );

                        case 'recency':
                                $event_dates = array_filter(
                                        array_column( $user_events, 'created_at' ),
                                        static function ( $event_date ) {
                                                return null !== $event_date && '' !== $event_date;
                                        }
                                );

                                if ( empty( $event_dates ) ) {
                                        return false;
                                }

                                $latest_event = max( $event_dates );
                                $days_since = self::days_since_date( $latest_event );
                                return self::compare_numeric_values( $days_since, floatval( $value ), $operator );

                        default:
				return false;
		}
	}

	/**
	 * Evaluate value-based condition
	 *
	 * @param array $condition The condition
	 * @param array $user_events User events
	 * @return bool True if condition matches
	 */
	private static function evaluate_value_condition( array $condition, array $user_events ): bool {
		$operator = $condition['operator'] ?? '';
		$value = floatval( $condition['value'] ?? 0 );

		$total_value = array_sum( array_column( $user_events, 'event_value' ) );

		return self::compare_numeric_values( $total_value, $value, $operator );
	}

	/**
	 * Compare two values using an operator
	 *
	 * @param mixed  $actual_value The actual value
	 * @param mixed  $expected_value The expected value
	 * @param string $operator The comparison operator
	 * @return bool True if comparison matches
	 */
	private static function compare_values( $actual_value, $expected_value, string $operator ): bool {
		switch ( $operator ) {
			case self::OP_EQUALS:
				return $actual_value === $expected_value;
			case self::OP_NOT_EQUALS:
				return $actual_value !== $expected_value;
			case self::OP_CONTAINS:
				return stripos( $actual_value, $expected_value ) !== false;
			case self::OP_NOT_CONTAINS:
				return stripos( $actual_value, $expected_value ) === false;
			default:
				return false;
		}
	}

	/**
	 * Compare numeric values using an operator
	 *
	 * @param float  $actual_value The actual value
	 * @param float  $expected_value The expected value
	 * @param string $operator The comparison operator
	 * @return bool True if comparison matches
	 */
	private static function compare_numeric_values( float $actual_value, float $expected_value, string $operator ): bool {
		switch ( $operator ) {
			case self::OP_EQUALS:
				return $actual_value == $expected_value;
			case self::OP_NOT_EQUALS:
				return $actual_value != $expected_value;
			case self::OP_GREATER_THAN:
				return $actual_value > $expected_value;
			case self::OP_LESS_THAN:
				return $actual_value < $expected_value;
			default:
				return false;
		}
	}

	/**
	 * Check if events occurred in the last N days
	 *
	 * @param array $events Events to check
	 * @param int   $days Number of days
	 * @return bool True if any events in timeframe
	 */
	private static function has_events_in_last_days( array $events, int $days ): bool {
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		foreach ( $events as $event ) {
			if ( $event['created_at'] >= $cutoff_date ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect device type from user agent
	 *
	 * @param string $user_agent User agent string
	 * @return string Device type (mobile, tablet, desktop)
	 */
	private static function detect_device_type( string $user_agent ): string {
		if ( empty( $user_agent ) ) {
			return 'desktop';
		}

		// Simple device detection
		if ( preg_match( '/Mobile|Android|iPhone|iPad|iPod|BlackBerry|Opera Mini/i', $user_agent ) ) {
			if ( preg_match( '/iPad|Tablet/i', $user_agent ) ) {
				return 'tablet';
			}
			return 'mobile';
		}

		return 'desktop';
	}

	/**
	 * Get country from IP address (simplified implementation)
	 *
	 * @param string $ip_address IP address
	 * @return string Country code or 'unknown'
	 */
	private static function get_country_from_ip( string $ip_address ): string {
		// Handle local/private IPs
		if ( empty( $ip_address ) || $ip_address === '127.0.0.1' || ! filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return 'unknown';
		}

		// Check cache first
		$cache_key = 'fp_ip_country_' . md5( $ip_address );
		$cached_result = get_transient( $cache_key );
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Try to use a free IP geolocation service
		$country = self::fetch_country_from_external_service( $ip_address );
		
		// Cache result for 24 hours
		if ( $country !== 'unknown' ) {
			set_transient( $cache_key, $country, 24 * HOUR_IN_SECONDS );
		}

		return $country;
	}

	/**
	 * Fetch country from external IP geolocation service
	 *
	 * @param string $ip_address IP address
	 * @return string Country code or 'unknown'
	 */
	private static function fetch_country_from_external_service( string $ip_address ): string {
		// Try multiple free services as fallbacks
		$services = [
			'http://ip-api.com/json/' . $ip_address . '?fields=countryCode',
			'https://ipapi.co/' . $ip_address . '/country_code/',
		];

		foreach ( $services as $url ) {
			$response = wp_remote_get( $url, [
				'timeout' => 5,
				'user-agent' => 'FP Digital Marketing Suite/1.0',
			] );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$body = wp_remote_retrieve_body( $response );
				
				if ( strpos( $url, 'ip-api.com' ) !== false ) {
					$data = json_decode( $body, true );
					if ( isset( $data['countryCode'] ) && ! empty( $data['countryCode'] ) ) {
						return strtoupper( $data['countryCode'] );
					}
				} else {
					$country_code = trim( $body );
					if ( strlen( $country_code ) === 2 && ctype_alpha( $country_code ) ) {
						return strtoupper( $country_code );
					}
				}
			}
		}

		// Fallback based on site locale if available
		$locale = get_locale();
		if ( $locale === 'it_IT' ) {
			return 'IT';
		} elseif ( strpos( $locale, 'en_' ) === 0 ) {
			return 'US';
		}

		return 'unknown';
	}

	/**
	 * Calculate days since a date
	 *
	 * @param string $date Date string
	 * @return int Days since date
	 */
	private static function days_since_date( string $date ): int {
		$date_obj = new DateTime( $date );
		$now = new DateTime();
		$diff = $now->diff( $date_obj );

		return $diff->days;
	}

	/**
	 * Get unique users for a client
	 *
	 * @param int $client_id Client ID
	 * @return array Array of unique user IDs
	 */
       public static function get_unique_users_for_client( int $client_id ): array {
		global $wpdb;

		$table_name = ConversionEventsTable::get_table_name();
		$sql = $wpdb->prepare(
			"SELECT DISTINCT user_id FROM $table_name WHERE client_id = %d AND user_id IS NOT NULL AND user_id != ''",
			$client_id
		);

		$results = $wpdb->get_col( $sql );

		return array_filter( $results );
	}

	/**
	 * Get available rule types
	 *
	 * @return array Rule types with labels
	 */
	public static function get_rule_types(): array {
		return [
			self::RULE_TYPE_EVENT => __( 'Eventi', 'fp-digital-marketing' ),
			self::RULE_TYPE_UTM => __( 'Sorgenti UTM', 'fp-digital-marketing' ),
			self::RULE_TYPE_DEVICE => __( 'Dispositivo', 'fp-digital-marketing' ),
			self::RULE_TYPE_GEOGRAPHY => __( 'Geografia', 'fp-digital-marketing' ),
			self::RULE_TYPE_BEHAVIOR => __( 'Comportamento', 'fp-digital-marketing' ),
			self::RULE_TYPE_VALUE => __( 'Valore', 'fp-digital-marketing' ),
		];
	}

	/**
	 * Get available operators
	 *
	 * @return array Operators with labels
	 */
	public static function get_operators(): array {
		return [
			self::OP_EQUALS => __( 'Uguale a', 'fp-digital-marketing' ),
			self::OP_NOT_EQUALS => __( 'Diverso da', 'fp-digital-marketing' ),
			self::OP_CONTAINS => __( 'Contiene', 'fp-digital-marketing' ),
			self::OP_NOT_CONTAINS => __( 'Non contiene', 'fp-digital-marketing' ),
			self::OP_GREATER_THAN => __( 'Maggiore di', 'fp-digital-marketing' ),
			self::OP_LESS_THAN => __( 'Minore di', 'fp-digital-marketing' ),
			self::OP_IN_LAST_DAYS => __( 'Negli ultimi N giorni', 'fp-digital-marketing' ),
			self::OP_NOT_IN_LAST_DAYS => __( 'Non negli ultimi N giorni', 'fp-digital-marketing' ),
		];
	}
}
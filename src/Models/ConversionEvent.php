<?php
/**
 * Conversion Event Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Conversion Event model class
 * 
 * Represents a single conversion event with all associated data and behavior.
 */
class ConversionEvent {

	/**
	 * Event ID
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Unique event identifier
	 *
	 * @var string
	 */
	private string $event_id;

	/**
	 * Event type (signup, purchase, lead_submit, etc.)
	 *
	 * @var string
	 */
	private string $event_type;

	/**
	 * Human-readable event name
	 *
	 * @var string
	 */
	private string $event_name;

	/**
	 * Client ID
	 *
	 * @var int
	 */
	private int $client_id;

	/**
	 * Source system (ga4, facebook_ads, etc.)
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Original event ID from source system
	 *
	 * @var string|null
	 */
	private ?string $source_event_id = null;

	/**
	 * User identifier
	 *
	 * @var string|null
	 */
	private ?string $user_id = null;

	/**
	 * Session identifier
	 *
	 * @var string|null
	 */
	private ?string $session_id = null;

	/**
	 * UTM source
	 *
	 * @var string|null
	 */
	private ?string $utm_source = null;

	/**
	 * UTM medium
	 *
	 * @var string|null
	 */
	private ?string $utm_medium = null;

	/**
	 * UTM campaign
	 *
	 * @var string|null
	 */
	private ?string $utm_campaign = null;

	/**
	 * UTM term
	 *
	 * @var string|null
	 */
	private ?string $utm_term = null;

	/**
	 * UTM content
	 *
	 * @var string|null
	 */
	private ?string $utm_content = null;

	/**
	 * Event value (e.g., purchase amount)
	 *
	 * @var float
	 */
	private float $event_value = 0.0;

	/**
	 * Currency code
	 *
	 * @var string
	 */
	private string $currency = 'EUR';

	/**
	 * Additional event attributes as array
	 *
	 * @var array
	 */
	private array $event_attributes = [];

	/**
	 * Page URL where event occurred
	 *
	 * @var string|null
	 */
	private ?string $page_url = null;

	/**
	 * Referrer URL
	 *
	 * @var string|null
	 */
	private ?string $referrer_url = null;

	/**
	 * IP address
	 *
	 * @var string|null
	 */
	private ?string $ip_address = null;

	/**
	 * User agent
	 *
	 * @var string|null
	 */
	private ?string $user_agent = null;

	/**
	 * Whether this event is marked as duplicate
	 *
	 * @var bool
	 */
	private bool $is_duplicate = false;

	/**
	 * Creation timestamp
	 *
	 * @var string|null
	 */
	private ?string $created_at = null;

	/**
	 * Processing timestamp
	 *
	 * @var string|null
	 */
	private ?string $processed_at = null;

	/**
	 * Constructor
	 *
	 * @param array $data Event data array
	 */
	public function __construct( array $data = [] ) {
		if ( ! empty( $data ) ) {
			$this->populate_from_array( $data );
		}
	}

	/**
	 * Create a new event from array data
	 *
	 * @param array $data Event data
	 * @return self New ConversionEvent instance
	 */
	public static function create_from_array( array $data ): self {
		return new self( $data );
	}

        /**
         * Load event from database by ID
         *
         * @param int $event_id Event ID
         * @return self|null ConversionEvent instance or null if not found
         */
        public static function load_by_id( int $event_id ): ?self {
                $event_data = ConversionEventsTable::get_event_by_id( $event_id );

                if ( null === $event_data ) {
                        return null;
                }

                return new self( $event_data );
        }

	/**
	 * Load event by unique event ID
	 *
	 * @param string $event_id Unique event ID
	 * @param string $source Source system
	 * @return self|null ConversionEvent instance or null if not found
	 */
        public static function load_by_event_id( string $event_id, string $source ): ?self {
                global $wpdb;

                $table_name = ConversionEventsTable::get_table_name();
                $sql = $wpdb->prepare(
                        "SELECT * FROM $table_name WHERE event_id = %s AND source = %s LIMIT 1",
                        $event_id,
                        $source
                );

                $result = $wpdb->get_row( $sql, ARRAY_A );

                if ( $result ) {
                        return new self( $result );
                }

                return null;
        }

        /**
         * Load event by source event ID
         *
         * @param string $source_event_id Source event ID from the originating system
         * @param string $source          Source system identifier
         * @return self|null ConversionEvent instance or null if not found
         */
        public static function load_by_source_event_id( string $source_event_id, string $source ): ?self {
                global $wpdb;

                $source_event_id = trim( $source_event_id );

                if ( '' === $source_event_id ) {
                        return null;
                }

                $table_name = ConversionEventsTable::get_table_name();
                $sql = $wpdb->prepare(
                        "SELECT * FROM $table_name WHERE source_event_id = %s AND source = %s LIMIT 1",
                        $source_event_id,
                        $source
                );

                $result = $wpdb->get_row( $sql, ARRAY_A );

                if ( $result ) {
                        return new self( $result );
                }

                return null;
        }

        /**
         * Populate object from array data
         *
         * @param array $data Event data array
	 * @return void
	 */
	private function populate_from_array( array $data ): void {
		$this->id = isset( $data['id'] ) ? (int) $data['id'] : null;
		$this->event_id = sanitize_text_field( $data['event_id'] ?? '' );
		$this->event_type = sanitize_text_field( $data['event_type'] ?? '' );
		$this->event_name = sanitize_text_field( $data['event_name'] ?? '' );
		$this->client_id = isset( $data['client_id'] ) ? (int) $data['client_id'] : 0;
		$this->source = sanitize_text_field( $data['source'] ?? '' );
		$this->source_event_id = isset( $data['source_event_id'] ) ? sanitize_text_field( $data['source_event_id'] ) : null;
		$this->user_id = isset( $data['user_id'] ) ? sanitize_text_field( $data['user_id'] ) : null;
		$this->session_id = isset( $data['session_id'] ) ? sanitize_text_field( $data['session_id'] ) : null;
		$this->utm_source = isset( $data['utm_source'] ) ? sanitize_text_field( $data['utm_source'] ) : null;
		$this->utm_medium = isset( $data['utm_medium'] ) ? sanitize_text_field( $data['utm_medium'] ) : null;
		$this->utm_campaign = isset( $data['utm_campaign'] ) ? sanitize_text_field( $data['utm_campaign'] ) : null;
		$this->utm_term = isset( $data['utm_term'] ) ? sanitize_text_field( $data['utm_term'] ) : null;
		$this->utm_content = isset( $data['utm_content'] ) ? sanitize_text_field( $data['utm_content'] ) : null;
		$this->event_value = isset( $data['event_value'] ) ? (float) $data['event_value'] : 0.0;
		$this->currency = sanitize_text_field( $data['currency'] ?? 'EUR' );
		$this->event_attributes = isset( $data['event_attributes'] ) && is_array( $data['event_attributes'] ) 
			? $data['event_attributes'] 
			: ( isset( $data['event_attributes'] ) ? json_decode( $data['event_attributes'], true ) ?: [] : [] );
		$this->page_url = isset( $data['page_url'] ) ? esc_url_raw( $data['page_url'] ) : null;
		$this->referrer_url = isset( $data['referrer_url'] ) ? esc_url_raw( $data['referrer_url'] ) : null;
		$this->ip_address = isset( $data['ip_address'] ) ? sanitize_text_field( $data['ip_address'] ) : null;
		$this->user_agent = isset( $data['user_agent'] ) ? sanitize_text_field( $data['user_agent'] ) : null;
		$this->is_duplicate = isset( $data['is_duplicate'] ) ? (bool) $data['is_duplicate'] : false;
		$this->created_at = $data['created_at'] ?? null;
		$this->processed_at = $data['processed_at'] ?? null;
	}

	/**
	 * Save event to database
	 *
	 * @return bool True on success, false on failure
	 */
	public function save(): bool {
		$data = $this->to_array();
		unset( $data['id'] ); // Don't include ID in insert/update data

                $result = false;

                if ( $this->id ) {
                        // Update existing event
                        $result = ConversionEventsTable::update_event( $this->id, $data );
                } else {
                        // Insert new event
                        $insert_id = ConversionEventsTable::insert_event( $data );
                        if ( $insert_id ) {
                                $this->id = $insert_id;
                                $result = true;
                        }
                }

                if ( $result ) {
                        $this->invalidate_cache();
                }

                return (bool) $result;
        }

	/**
	 * Delete event from database
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete(): bool {
		if ( ! $this->id ) {
			return false;
		}

                $result = ConversionEventsTable::delete_event( $this->id );

                if ( $result ) {
                        $this->invalidate_cache();
                }

                return $result;
        }

	/**
	 * Mark this event as duplicate
	 *
	 * @return bool True on success
	 */
	public function mark_as_duplicate(): bool {
		$this->is_duplicate = true;
		
		if ( $this->id ) {
			return ConversionEventsTable::mark_as_duplicate( $this->id );
		}

		return false;
	}

	/**
	 * Generate unique event ID if not set
	 *
	 * @return void
	 */
	public function generate_event_id(): void {
		if ( empty( $this->event_id ) ) {
			$this->event_id = uniqid( $this->event_type . '_', true );
		}
	}

	/**
	 * Set event attributes
	 *
	 * @param array $attributes Event attributes array
	 * @return void
	 */
	public function set_attributes( array $attributes ): void {
		$this->event_attributes = $attributes;
	}

	/**
	 * Get specific attribute value
	 *
	 * @param string $key Attribute key
	 * @param mixed  $default Default value if key not found
	 * @return mixed Attribute value
	 */
	public function get_attribute( string $key, $default = null ) {
		return $this->event_attributes[ $key ] ?? $default;
	}

	/**
	 * Set specific attribute value
	 *
	 * @param string $key Attribute key
	 * @param mixed  $value Attribute value
	 * @return void
	 */
        public function set_attribute( string $key, $value ): void {
                $this->event_attributes[ $key ] = $value;
        }

        /**
         * Clear cached metrics related to this conversion event.
         *
         * @return void
         */
        private function invalidate_cache(): void {
                $client_component = $this->client_id > 0 ? (string) $this->client_id : 'global';

                $patterns = [
                        PerformanceCache::CACHE_GROUP_METRICS => sprintf( 'metrics_client_%s_*', $client_component ),
                        PerformanceCache::CACHE_GROUP_AGGREGATED => sprintf( 'metrics_client_%s_*', $client_component ),
                        PerformanceCache::CACHE_GROUP_REPORTS => sprintf( 'report_client_%s_*', $client_component ),
                ];

                foreach ( $patterns as $group => $pattern ) {
                        PerformanceCache::clear_cache_by_pattern( $pattern, $group );
                }
        }

        /**
         * Convert to array representation
         *
	 * @return array Event data as array
	 */
	public function to_array(): array {
		return [
			'id' => $this->id,
			'event_id' => $this->event_id,
			'event_type' => $this->event_type,
			'event_name' => $this->event_name,
			'client_id' => $this->client_id,
			'source' => $this->source,
			'source_event_id' => $this->source_event_id,
			'user_id' => $this->user_id,
			'session_id' => $this->session_id,
			'utm_source' => $this->utm_source,
			'utm_medium' => $this->utm_medium,
			'utm_campaign' => $this->utm_campaign,
			'utm_term' => $this->utm_term,
			'utm_content' => $this->utm_content,
			'event_value' => $this->event_value,
			'currency' => $this->currency,
			'event_attributes' => $this->event_attributes,
			'page_url' => $this->page_url,
			'referrer_url' => $this->referrer_url,
			'ip_address' => $this->ip_address,
			'user_agent' => $this->user_agent,
			'is_duplicate' => $this->is_duplicate,
			'created_at' => $this->created_at,
			'processed_at' => $this->processed_at,
		];
	}

	// Getters
	public function get_id(): ?int { return $this->id; }
	public function get_event_id(): string { return $this->event_id; }
	public function get_event_type(): string { return $this->event_type; }
	public function get_event_name(): string { return $this->event_name; }
	public function get_client_id(): int { return $this->client_id; }
	public function get_source(): string { return $this->source; }
	public function get_source_event_id(): ?string { return $this->source_event_id; }
	public function get_user_id(): ?string { return $this->user_id; }
	public function get_session_id(): ?string { return $this->session_id; }
	public function get_utm_source(): ?string { return $this->utm_source; }
	public function get_utm_medium(): ?string { return $this->utm_medium; }
	public function get_utm_campaign(): ?string { return $this->utm_campaign; }
	public function get_utm_term(): ?string { return $this->utm_term; }
	public function get_utm_content(): ?string { return $this->utm_content; }
	public function get_event_value(): float { return $this->event_value; }
	public function get_currency(): string { return $this->currency; }
	public function get_event_attributes(): array { return $this->event_attributes; }
	public function get_page_url(): ?string { return $this->page_url; }
	public function get_referrer_url(): ?string { return $this->referrer_url; }
	public function get_ip_address(): ?string { return $this->ip_address; }
	public function get_user_agent(): ?string { return $this->user_agent; }
	public function is_duplicate(): bool { return $this->is_duplicate; }
	public function get_created_at(): ?string { return $this->created_at; }
	public function get_processed_at(): ?string { return $this->processed_at; }

	// Setters
	public function set_event_id( string $event_id ): void { $this->event_id = $event_id; }
	public function set_event_type( string $event_type ): void { $this->event_type = $event_type; }
	public function set_event_name( string $event_name ): void { $this->event_name = $event_name; }
	public function set_client_id( int $client_id ): void { $this->client_id = $client_id; }
	public function set_source( string $source ): void { $this->source = $source; }
	public function set_source_event_id( ?string $source_event_id ): void { $this->source_event_id = $source_event_id; }
	public function set_user_id( ?string $user_id ): void { $this->user_id = $user_id; }
	public function set_session_id( ?string $session_id ): void { $this->session_id = $session_id; }
	public function set_utm_source( ?string $utm_source ): void { $this->utm_source = $utm_source; }
	public function set_utm_medium( ?string $utm_medium ): void { $this->utm_medium = $utm_medium; }
	public function set_utm_campaign( ?string $utm_campaign ): void { $this->utm_campaign = $utm_campaign; }
	public function set_utm_term( ?string $utm_term ): void { $this->utm_term = $utm_term; }
	public function set_utm_content( ?string $utm_content ): void { $this->utm_content = $utm_content; }
	public function set_event_value( float $event_value ): void { $this->event_value = $event_value; }
	public function set_currency( string $currency ): void { $this->currency = $currency; }
	public function set_page_url( ?string $page_url ): void { $this->page_url = $page_url; }
	public function set_referrer_url( ?string $referrer_url ): void { $this->referrer_url = $referrer_url; }
	public function set_ip_address( ?string $ip_address ): void { $this->ip_address = $ip_address; }
	public function set_user_agent( ?string $user_agent ): void { $this->user_agent = $user_agent; }
	public function set_processed_at( ?string $processed_at ): void { $this->processed_at = $processed_at; }

	/**
	 * Get events for export with filtering criteria
	 *
	 * @param array $criteria Export criteria
	 * @return array Events data
	 */
	public static function get_events_for_export( array $criteria = [] ): array {
		global $wpdb;
		
		$table_name = ConversionEventsTable::get_table_name();
		
		// Build WHERE clause based on criteria
		$where_conditions = [ '1=1' ];
		$where_values = [];
		
		if ( ! empty( $criteria['client_id'] ) ) {
			$where_conditions[] = 'client_id = %d';
			$where_values[] = (int) $criteria['client_id'];
		}
		
		if ( ! empty( $criteria['event_type'] ) ) {
			$where_conditions[] = 'event_type = %s';
			$where_values[] = $criteria['event_type'];
		}
		
                if ( ! empty( $criteria['start_date'] ) ) {
                        $where_conditions[] = 'created_at >= %s';
                        $where_values[] = $criteria['start_date'] . ' 00:00:00';
                }

		if ( ! empty( $criteria['end_date'] ) ) {
			$where_conditions[] = 'created_at <= %s';
			$where_values[] = $criteria['end_date'] . ' 23:59:59';
		}
		
		$where_clause = implode( ' AND ', $where_conditions );
		
		// Build and execute query
                $query = "SELECT
                        id,
                        event_id,
                        event_name,
                        event_type,
                        client_id,
                        source,
                        source_event_id,
                        event_value,
                        currency,
                        is_duplicate,
                        created_at,
                        processed_at
                        FROM {$table_name}
                        WHERE {$where_clause}
                        ORDER BY created_at DESC
                        LIMIT 1000";

                if ( ! empty( $where_values ) ) {
                        $query = $wpdb->prepare( $query, ...$where_values );
                }

                $results = $wpdb->get_results( $query, ARRAY_A );

                if ( empty( $results ) ) {
                        return [];
                }

                return array_map(
                        static function ( array $event ): array {
                                $event['is_duplicate'] = isset( $event['is_duplicate'] ) ? (int) $event['is_duplicate'] : 0;

                                return $event;
                        },
                        $results
                );
        }
}

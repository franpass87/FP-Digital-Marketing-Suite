<?php
/**
 * Funnel Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\FunnelTable;
use FP\DigitalMarketing\Database\CustomerJourneyTable;

/**
 * Funnel model class
 * 
 * Represents a conversion funnel with stages and analytics capabilities.
 */
class Funnel {

	/**
	 * Funnel ID
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Funnel name
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Funnel description
	 *
	 * @var string|null
	 */
	private ?string $description = null;

	/**
	 * Client ID
	 *
	 * @var int
	 */
	private int $client_id;

	/**
	 * Funnel status
	 *
	 * @var string
	 */
	private string $status = 'draft';

	/**
	 * Conversion window in days
	 *
	 * @var int
	 */
	private int $conversion_window_days = 30;

	/**
	 * Attribution model
	 *
	 * @var string
	 */
	private string $attribution_model = 'last_click';

	/**
	 * Funnel stages
	 *
	 * @var array
	 */
	private array $stages = [];

	/**
	 * Creation timestamp
	 *
	 * @var string|null
	 */
	private ?string $created_at = null;

	/**
	 * Update timestamp
	 *
	 * @var string|null
	 */
	private ?string $updated_at = null;

	/**
	 * Constructor
	 *
	 * @param array $data Funnel data array
	 */
	public function __construct( array $data = [] ) {
		if ( ! empty( $data ) ) {
			$this->populate_from_array( $data );
		}
	}

	/**
	 * Create funnel from array data
	 *
	 * @param array $data Funnel data
	 * @return self New Funnel instance
	 */
	public static function create_from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * Load funnel from database by ID
	 *
	 * @param int $funnel_id Funnel ID
	 * @return self|null Funnel instance or null if not found
	 */
	public static function load_by_id( int $funnel_id ): ?self {
		$funnel_data = FunnelTable::get_funnel( $funnel_id );
		
		if ( ! $funnel_data ) {
			return null;
		}

		$funnel = new self( $funnel_data );
		$funnel->load_stages();
		
		return $funnel;
	}

	/**
	 * Get funnels for a client
	 *
	 * @param int    $client_id Client ID
	 * @param string $status Status filter
	 * @return array Array of Funnel instances
	 */
	public static function get_client_funnels( int $client_id, string $status = '' ): array {
		$funnels_data = FunnelTable::get_client_funnels( $client_id, $status );
		$funnels = [];

		foreach ( $funnels_data as $funnel_data ) {
			$funnel = new self( $funnel_data );
			$funnel->load_stages();
			$funnels[] = $funnel;
		}

		return $funnels;
	}

	/**
	 * Populate object from array data
	 *
	 * @param array $data Funnel data array
	 * @return void
	 */
	private function populate_from_array( array $data ): void {
		$this->id = isset( $data['id'] ) ? (int) $data['id'] : null;
		$this->name = sanitize_text_field( $data['name'] ?? '' );
		$this->description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null;
		$this->client_id = isset( $data['client_id'] ) ? (int) $data['client_id'] : 0;
		$this->status = sanitize_text_field( $data['status'] ?? 'draft' );
		$this->conversion_window_days = isset( $data['conversion_window_days'] ) ? (int) $data['conversion_window_days'] : 30;
		$this->attribution_model = sanitize_text_field( $data['attribution_model'] ?? 'last_click' );
		$this->created_at = $data['created_at'] ?? null;
		$this->updated_at = $data['updated_at'] ?? null;
	}

	/**
	 * Load stages for this funnel
	 *
	 * @return void
	 */
	private function load_stages(): void {
		if ( $this->id ) {
			$this->stages = FunnelTable::get_funnel_stages( $this->id );
		}
	}

	/**
	 * Save funnel to database
	 *
	 * @return bool True on success, false on failure
	 */
	public function save(): bool {
		$data = [
			'name' => $this->name,
			'description' => $this->description,
			'client_id' => $this->client_id,
			'status' => $this->status,
			'conversion_window_days' => $this->conversion_window_days,
			'attribution_model' => $this->attribution_model,
		];

		if ( $this->id ) {
			// Update existing funnel
			return FunnelTable::update_funnel( $this->id, $data );
		} else {
			// Insert new funnel
			$insert_id = FunnelTable::insert_funnel( $data );
			if ( $insert_id ) {
				$this->id = $insert_id;
				return true;
			}
			return false;
		}
	}

	/**
	 * Delete funnel from database
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete(): bool {
		if ( ! $this->id ) {
			return false;
		}

		return FunnelTable::delete_funnel( $this->id );
	}

	/**
	 * Add stage to funnel
	 *
	 * @param array $stage_data Stage data
	 * @return bool True on success, false on failure
	 */
	public function add_stage( array $stage_data ): bool {
		if ( ! $this->id ) {
			return false;
		}

		$stage_data['funnel_id'] = $this->id;
		$stage_data['stage_order'] = count( $this->stages ) + 1;

		$stage_id = FunnelTable::insert_stage( $stage_data );
		
		if ( $stage_id ) {
			$this->load_stages(); // Reload stages
			return true;
		}

		return false;
	}

	/**
	 * Remove stage from funnel
	 *
	 * @param int $stage_id Stage ID
	 * @return bool True on success, false on failure
	 */
	public function remove_stage( int $stage_id ): bool {
		$result = FunnelTable::delete_stage( $stage_id );
		
		if ( $result ) {
			$this->load_stages(); // Reload stages
		}

		return $result;
	}

	/**
	 * Get funnel conversion analysis
	 *
	 * @param array $filters Analysis filters
	 * @return array Conversion analysis data
	 */
	public function get_conversion_analysis( array $filters = [] ): array {
		if ( empty( $this->stages ) ) {
			return [];
		}

		// Extract event types from stages
		$funnel_steps = array_map( function( $stage ) {
			return $stage['event_type'];
		}, $this->stages );

		// Add client_id to filters
		$filters['client_id'] = $this->client_id;

		// Get conversion data from database
		$conversion_data = CustomerJourneyTable::get_funnel_conversion_data( $funnel_steps, $filters );

		// Enrich with stage information
		foreach ( $conversion_data as &$step_data ) {
			$stage_index = $step_data['step'] - 1;
			if ( isset( $this->stages[ $stage_index ] ) ) {
				$step_data['stage_name'] = $this->stages[ $stage_index ]['name'];
				$step_data['stage_description'] = $this->stages[ $stage_index ]['description'];
			}
		}

		return $conversion_data;
	}

	/**
	 * Calculate drop-off rates between stages
	 *
	 * @param array $filters Analysis filters
	 * @return array Drop-off data
	 */
	public function get_dropoff_analysis( array $filters = [] ): array {
		$conversion_data = $this->get_conversion_analysis( $filters );
		$dropoff_data = [];

		for ( $i = 0; $i < count( $conversion_data ) - 1; $i++ ) {
			$current_step = $conversion_data[ $i ];
			$next_step = $conversion_data[ $i + 1 ];

			$dropoff_count = $current_step['sessions'] - $next_step['sessions'];
			$dropoff_rate = $current_step['sessions'] > 0 
				? round( ($dropoff_count / $current_step['sessions']) * 100, 2 )
				: 0;

			$dropoff_data[] = [
				'from_step' => $current_step['step'],
				'to_step' => $next_step['step'],
				'from_stage_name' => $current_step['stage_name'] ?? '',
				'to_stage_name' => $next_step['stage_name'] ?? '',
				'dropoff_sessions' => $dropoff_count,
				'dropoff_rate' => $dropoff_rate,
			];
		}

		return $dropoff_data;
	}

	/**
	 * Get time-to-conversion analysis
	 *
	 * @param array $filters Analysis filters
	 * @return array Time analysis data
	 */
	public function get_time_analysis( array $filters = [] ): array {
		global $wpdb;

		if ( empty( $this->stages ) ) {
			return [];
		}

		$events_table = CustomerJourneyTable::get_table_name();
		$first_stage_event = $this->stages[0]['event_type'];
		$last_stage_event = end( $this->stages )['event_type'];

		$where_conditions = [ 'client_id = %d' ];
		$where_values = [ $this->client_id ];

		if ( ! empty( $filters['start_date'] ) ) {
			$where_conditions[] = 'timestamp >= %s';
			$where_values[] = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where_conditions[] = 'timestamp <= %s';
			$where_values[] = $filters['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Query to get time between first and last stage for each session
		$sql = "
			SELECT 
				e1.session_id,
				e1.timestamp as first_event,
				e2.timestamp as last_event,
				TIMESTAMPDIFF(HOUR, e1.timestamp, e2.timestamp) as hours_to_convert
			FROM $events_table e1
			INNER JOIN $events_table e2 ON e1.session_id = e2.session_id
			WHERE e1.event_type = %s 
			AND e2.event_type = %s
			AND e1.timestamp <= e2.timestamp
			AND e1.$where_clause
			AND e2.$where_clause
			ORDER BY hours_to_convert ASC
		";

		$query_values = array_merge( [ $first_stage_event, $last_stage_event ], $where_values, $where_values );
		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_values ), ARRAY_A );

		// Analyze conversion times
		$conversion_times = array_column( $results, 'hours_to_convert' );
		
		if ( empty( $conversion_times ) ) {
			return [
				'avg_hours_to_convert' => 0,
				'median_hours_to_convert' => 0,
				'min_hours_to_convert' => 0,
				'max_hours_to_convert' => 0,
				'total_conversions' => 0,
			];
		}

		sort( $conversion_times );
		$count = count( $conversion_times );
		$median_index = intval( $count / 2 );

		return [
			'avg_hours_to_convert' => round( array_sum( $conversion_times ) / $count, 2 ),
			'median_hours_to_convert' => $count % 2 === 0 
				? ($conversion_times[ $median_index - 1 ] + $conversion_times[ $median_index ]) / 2
				: $conversion_times[ $median_index ],
			'min_hours_to_convert' => min( $conversion_times ),
			'max_hours_to_convert' => max( $conversion_times ),
			'total_conversions' => $count,
		];
	}

	/**
	 * Convert to array representation
	 *
	 * @return array Funnel data as array
	 */
	public function to_array(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'client_id' => $this->client_id,
			'status' => $this->status,
			'conversion_window_days' => $this->conversion_window_days,
			'attribution_model' => $this->attribution_model,
			'stages' => $this->stages,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}

	// Getters
	public function get_id(): ?int { return $this->id; }
	public function get_name(): string { return $this->name; }
	public function get_description(): ?string { return $this->description; }
	public function get_client_id(): int { return $this->client_id; }
	public function get_status(): string { return $this->status; }
	public function get_conversion_window_days(): int { return $this->conversion_window_days; }
	public function get_attribution_model(): string { return $this->attribution_model; }
	public function get_stages(): array { return $this->stages; }
	public function get_created_at(): ?string { return $this->created_at; }
	public function get_updated_at(): ?string { return $this->updated_at; }

	// Setters
	public function set_name( string $name ): void { $this->name = $name; }
	public function set_description( ?string $description ): void { $this->description = $description; }
	public function set_client_id( int $client_id ): void { $this->client_id = $client_id; }
	public function set_status( string $status ): void { $this->status = $status; }
	public function set_conversion_window_days( int $days ): void { $this->conversion_window_days = $days; }
	public function set_attribution_model( string $model ): void { $this->attribution_model = $model; }

	/**
	 * Get available attribution models
	 *
	 * @return array Attribution models
	 */
	public static function get_attribution_models(): array {
		return [
			'first_click' => __( 'First Click', 'fp-digital-marketing' ),
			'last_click' => __( 'Last Click', 'fp-digital-marketing' ),
			'linear' => __( 'Linear', 'fp-digital-marketing' ),
			'time_decay' => __( 'Time Decay', 'fp-digital-marketing' ),
		];
	}

	/**
	 * Get available statuses
	 *
	 * @return array Funnel statuses
	 */
	public static function get_statuses(): array {
		return [
			'draft' => __( 'Draft', 'fp-digital-marketing' ),
			'active' => __( 'Active', 'fp-digital-marketing' ),
			'inactive' => __( 'Inactive', 'fp-digital-marketing' ),
		];
	}
}
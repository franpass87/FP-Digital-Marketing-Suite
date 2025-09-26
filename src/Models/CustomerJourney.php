<?php
/**
 * Customer Journey Model
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\CustomerJourneyTable;

/**
 * Customer Journey model class
 *
 * Represents a customer journey session with events and analytics capabilities.
 */
class CustomerJourney {

	/**
	 * Session ID
	 *
	 * @var string
	 */
	private string $session_id;

	/**
	 * Client ID
	 *
	 * @var int
	 */
	private int $client_id;

	/**
	 * User ID
	 *
	 * @var string|null
	 */
	private ?string $user_id = null;

	/**
	 * Journey events
	 *
	 * @var array
	 */
	private array $events = [];

	/**
	 * Session data
	 *
	 * @var array|null
	 */
	private ?array $session_data = null;

	/**
	 * Constructor
	 *
	 * @param string      $session_id Session ID
	 * @param int         $client_id  Client ID
	 * @param string|null $user_id User ID (optional)
	 */
	public function __construct( string $session_id, int $client_id, ?string $user_id = null ) {
		$this->session_id = $session_id;
		$this->client_id  = $client_id;
		$this->user_id    = $user_id;
	}

	/**
	 * Load journey from database
	 *
	 * @param string $session_id Session ID
	 * @param int    $client_id  Client ID
	 * @return self|null CustomerJourney instance or null if not found
	 */
	public static function load_by_session( string $session_id, int $client_id ): ?self {
		// Get session data
		$sessions = CustomerJourneyTable::get_journey_sessions(
			[
				'client_id' => $client_id,
			]
		);

		$session_data = null;
		foreach ( $sessions as $session ) {
			if ( $session['session_id'] === $session_id ) {
				$session_data = $session;
				break;
			}
		}

		if ( ! $session_data ) {
			return null;
		}

		$journey               = new self( $session_id, $client_id, $session_data['user_id'] );
		$journey->session_data = $session_data;
		$journey->load_events();

		return $journey;
	}

	/**
	 * Get journeys for a user
	 *
	 * @param string $user_id   User ID
	 * @param int    $client_id Client ID
	 * @return array Array of CustomerJourney instances
	 */
	public static function get_user_journeys( string $user_id, int $client_id ): array {
		$sessions = CustomerJourneyTable::get_journey_sessions(
			[
				'client_id' => $client_id,
				'user_id'   => $user_id,
			]
		);

		$journeys = [];
		foreach ( $sessions as $session ) {
			$journey               = new self( $session['session_id'], $client_id, $user_id );
			$journey->session_data = $session;
			$journey->load_events();
			$journeys[] = $journey;
		}

		return $journeys;
	}

	/**
	 * Load events for this journey
	 *
	 * @return void
	 */
	private function load_events(): void {
		$this->events = CustomerJourneyTable::get_journey_events(
			[
				'client_id'  => $this->client_id,
				'session_id' => $this->session_id,
			]
		);
	}

	/**
	 * Add event to journey
	 *
	 * @param array $event_data Event data
	 * @return bool True on success, false on failure
	 */
	public function add_event( array $event_data ): bool {
		$event_data['client_id']  = $this->client_id;
		$event_data['session_id'] = $this->session_id;

		if ( $this->user_id ) {
			$event_data['user_id'] = $this->user_id;
		}

		$event_id = CustomerJourneyTable::insert_event( $event_data );

		if ( $event_id ) {
			$this->load_events(); // Reload events
			return true;
		}

		return false;
	}

	/**
	 * Mark journey as converted
	 *
	 * @param float $conversion_value Conversion value
	 * @return bool True on success, false on failure
	 */
	public function mark_converted( float $conversion_value = 0.00 ): bool {
		return CustomerJourneyTable::mark_session_converted( $this->session_id, $conversion_value );
	}

	/**
	 * Get journey duration in seconds
	 *
	 * @return int Duration in seconds
	 */
	public function get_duration_seconds(): int {
		if ( empty( $this->events ) ) {
			return 0;
		}

		$first_event = reset( $this->events );
		$last_event  = end( $this->events );

		$first_timestamp = strtotime( $first_event['timestamp'] );
		$last_timestamp  = strtotime( $last_event['timestamp'] );

		return max( 0, $last_timestamp - $first_timestamp );
	}

	/**
	 * Get journey path as array of event types
	 *
	 * @return array Journey path
	 */
	public function get_journey_path(): array {
		$path = [];
		foreach ( $this->events as $event ) {
			$path[] = [
				'event_type' => $event['event_type'],
				'event_name' => $event['event_name'],
				'timestamp'  => $event['timestamp'],
				'page_url'   => $event['page_url'],
			];
		}
		return $path;
	}

	/**
	 * Get touchpoints summary
	 *
	 * @return array Touchpoints data
	 */
	public function get_touchpoints(): array {
		$touchpoints = [];

		foreach ( $this->events as $event ) {
			$source   = $event['utm_source'] ?: 'direct';
			$medium   = $event['utm_medium'] ?: 'none';
			$campaign = $event['utm_campaign'] ?: 'none';

			$touchpoint_key = $source . '/' . $medium;

			if ( ! isset( $touchpoints[ $touchpoint_key ] ) ) {
				$touchpoints[ $touchpoint_key ] = [
					'source'      => $source,
					'medium'      => $medium,
					'campaign'    => $campaign,
					'first_touch' => $event['timestamp'],
					'last_touch'  => $event['timestamp'],
					'touch_count' => 0,
					'total_value' => 0.00,
				];
			}

			++$touchpoints[ $touchpoint_key ]['touch_count'];
			$touchpoints[ $touchpoint_key ]['total_value'] += (float) $event['event_value'];
			$touchpoints[ $touchpoint_key ]['last_touch']   = $event['timestamp'];
		}

		return array_values( $touchpoints );
	}

	/**
	 * Get conversion attribution
	 *
	 * @param string $attribution_model Attribution model
	 * @return array Attribution data
	 */
	public function get_conversion_attribution( string $attribution_model = 'last_click' ): array {
		$touchpoints = $this->get_touchpoints();

		if ( empty( $touchpoints ) ) {
			return [];
		}

		switch ( $attribution_model ) {
			case 'first_click':
				return [ reset( $touchpoints ) ];

			case 'last_click':
				return [ end( $touchpoints ) ];

			case 'linear':
				// Distribute attribution equally
				$attribution_weight = 1.0 / count( $touchpoints );
				foreach ( $touchpoints as &$touchpoint ) {
					$touchpoint['attribution_weight'] = $attribution_weight;
				}
				return $touchpoints;

			case 'time_decay':
				// Give more weight to recent touchpoints
				$total_weights = 0;
				$half_life     = 7; // 7 days half-life

				foreach ( $touchpoints as &$touchpoint ) {
					$days_ago                         = ( time() - strtotime( $touchpoint['last_touch'] ) ) / 86400;
					$weight                           = pow( 0.5, $days_ago / $half_life );
					$touchpoint['attribution_weight'] = $weight;
					$total_weights                   += $weight;
				}

				// Normalize weights
				if ( $total_weights > 0 ) {
					foreach ( $touchpoints as &$touchpoint ) {
						$touchpoint['attribution_weight'] /= $total_weights;
					}
				}

				return $touchpoints;

			default:
				return $touchpoints;
		}
	}

	/**
	 * Get event funnel progress
	 *
	 * @param array $funnel_events Array of event types representing funnel stages
	 * @return array Funnel progress data
	 */
	public function get_funnel_progress( array $funnel_events ): array {
		$progress         = [];
		$completed_stages = [];

		foreach ( $funnel_events as $index => $required_event ) {
			$stage_completed = false;
			$completion_time = null;

			foreach ( $this->events as $event ) {
				if ( $event['event_type'] === $required_event ) {
					$stage_completed    = true;
					$completion_time    = $event['timestamp'];
					$completed_stages[] = $index + 1;
					break;
				}
			}

			$progress[] = [
				'stage'           => $index + 1,
				'event_type'      => $required_event,
				'completed'       => $stage_completed,
				'completion_time' => $completion_time,
			];

			// Stop if stage not completed (for strict funnel analysis)
			if ( ! $stage_completed ) {
				break;
			}
		}

		return [
			'stages'                 => $progress,
			'completed_stages'       => $completed_stages,
			'funnel_completion_rate' => count( $completed_stages ) / count( $funnel_events ),
			'furthest_stage'         => max( $completed_stages ) ?? 0,
		];
	}

	/**
	 * Get journey statistics
	 *
	 * @return array Journey statistics
	 */
	public function get_statistics(): array {
		$stats = [
			'total_events'     => count( $this->events ),
			'unique_pages'     => 0,
			'total_value'      => 0.00,
			'pageviews'        => 0,
			'conversions'      => 0,
			'duration_seconds' => $this->get_duration_seconds(),
			'bounce'           => false,
		];

		$unique_pages = [];

		foreach ( $this->events as $event ) {
			if ( $event['page_url'] ) {
				$unique_pages[ $event['page_url'] ] = true;
			}

			$stats['total_value'] += (float) $event['event_value'];

			if ( $event['event_type'] === 'pageview' ) {
				++$stats['pageviews'];
			}

			if ( in_array( $event['event_type'], [ 'purchase', 'conversion', 'lead_submit' ], true ) ) {
				++$stats['conversions'];
			}
		}

		$stats['unique_pages'] = count( $unique_pages );
		$stats['bounce']       = $stats['pageviews'] <= 1;

		return $stats;
	}

	/**
	 * Convert to array representation
	 *
	 * @return array Journey data as array
	 */
	public function to_array(): array {
		return [
			'session_id'   => $this->session_id,
			'client_id'    => $this->client_id,
			'user_id'      => $this->user_id,
			'events'       => $this->events,
			'session_data' => $this->session_data,
			'statistics'   => $this->get_statistics(),
			'journey_path' => $this->get_journey_path(),
			'touchpoints'  => $this->get_touchpoints(),
		];
	}

	// Getters
	public function get_session_id(): string {
		return $this->session_id; }
	public function get_client_id(): int {
		return $this->client_id; }
	public function get_user_id(): ?string {
		return $this->user_id; }
	public function get_events(): array {
		return $this->events; }
	public function get_session_data(): ?array {
		return $this->session_data; }

	// Setters
	public function set_user_id( ?string $user_id ): void {
		$this->user_id = $user_id; }

	/**
	 * Get journey segments based on behavior
	 *
	 * @return array Behavior segments
	 */
	public function get_behavior_segments(): array {
		$stats    = $this->get_statistics();
		$segments = [];

		// Engagement level
		if ( $stats['pageviews'] >= 10 ) {
			$segments[] = 'high_engagement';
		} elseif ( $stats['pageviews'] >= 3 ) {
			$segments[] = 'medium_engagement';
		} else {
			$segments[] = 'low_engagement';
		}

		// Value level
		if ( $stats['total_value'] >= 100 ) {
			$segments[] = 'high_value';
		} elseif ( $stats['total_value'] > 0 ) {
			$segments[] = 'medium_value';
		} else {
			$segments[] = 'no_value';
		}

		// Conversion status
		if ( $stats['conversions'] > 0 ) {
			$segments[] = 'converter';
		} else {
			$segments[] = 'non_converter';
		}

		// Bounce status
		if ( $stats['bounce'] ) {
			$segments[] = 'bouncer';
		}

		// Duration-based
		if ( $stats['duration_seconds'] >= 1800 ) { // 30 minutes
			$segments[] = 'long_session';
		} elseif ( $stats['duration_seconds'] >= 300 ) { // 5 minutes
			$segments[] = 'medium_session';
		} else {
			$segments[] = 'short_session';
		}

		return $segments;
	}

	/**
	 * Generate journey summary text
	 *
	 * @return string Journey summary
	 */
	public function get_summary(): string {
		$stats       = $this->get_statistics();
		$touchpoints = $this->get_touchpoints();

		$acquisition_source = 'direct';
		if ( ! empty( $touchpoints ) ) {
			$acquisition_source = $touchpoints[0]['source'] . '/' . $touchpoints[0]['medium'];
		}

		$duration_minutes = round( $stats['duration_seconds'] / 60, 1 );

		$summary = sprintf(
			__( 'User visited %1$d pages over %2$s minutes, acquired via %3$s. ', 'fp-digital-marketing' ),
			$stats['pageviews'],
			$duration_minutes,
			$acquisition_source
		);

		if ( $stats['conversions'] > 0 ) {
			$summary .= sprintf(
				__( 'Completed %1$d conversions worth %2$s %3$s.', 'fp-digital-marketing' ),
				$stats['conversions'],
				number_format( $stats['total_value'], 2 ),
				$this->session_data['currency'] ?? 'EUR'
			);
		} else {
			$summary .= __( 'No conversions completed.', 'fp-digital-marketing' );
		}

		return $summary;
	}
}

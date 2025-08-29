<?php
/**
 * Conversion Event Manager
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Models\ConversionEvent;
use FP\DigitalMarketing\Database\ConversionEventsTable;
use FP\DigitalMarketing\Helpers\ConversionEventRegistry;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Conversion Event Manager class
 * 
 * Provides high-level methods for managing conversion events, including
 * ingestion, deduplication, and querying with aggregation support.
 */
class ConversionEventManager {

	/**
	 * Ingest event from external source
	 *
	 * @param string $source Source identifier
	 * @param array  $source_data Raw event data from source
	 * @param int    $client_id Client ID
	 * @return ConversionEvent|false ConversionEvent object or false on failure
	 */
	public static function ingest_event( string $source, array $source_data, int $client_id ) {
		// Normalize event data using registry
		$event_data = ConversionEventRegistry::create_event_from_source( $source, $source_data, $client_id );

		// Check for duplicates
		$duplicate_check = self::check_for_duplicate( $event_data );
		if ( $duplicate_check ) {
			// Mark original as duplicate if this is a better quality event
			if ( self::is_better_quality_event( $event_data, $duplicate_check->to_array() ) ) {
				$duplicate_check->mark_as_duplicate();
			} else {
				// Mark this event as duplicate
				$event_data['is_duplicate'] = true;
			}
		}

		// Create and save event
		$event = new ConversionEvent( $event_data );
		$event->generate_event_id();

		if ( $event->save() ) {
			// Clear relevant caches
			self::clear_related_caches( $client_id, $event_data['event_type'] );
			
			return $event;
		}

		return false;
	}

	/**
	 * Bulk ingest events from external source
	 *
	 * @param string $source Source identifier
	 * @param array  $events_data Array of raw event data
	 * @param int    $client_id Client ID
	 * @return array Results with success/failure counts
	 */
	public static function bulk_ingest_events( string $source, array $events_data, int $client_id ): array {
		$results = [
			'total' => count( $events_data ),
			'success' => 0,
			'duplicates' => 0,
			'failed' => 0,
			'errors' => [],
		];

		foreach ( $events_data as $source_data ) {
			try {
				$event = self::ingest_event( $source, $source_data, $client_id );
				
				if ( $event ) {
					if ( $event->is_duplicate() ) {
						$results['duplicates']++;
					} else {
						$results['success']++;
					}
				} else {
					$results['failed']++;
				}
			} catch ( \Exception $e ) {
				$results['failed']++;
				$results['errors'][] = $e->getMessage();
			}
		}

		return $results;
	}

	/**
	 * Check for duplicate events
	 *
	 * @param array $event_data Event data to check
	 * @return ConversionEvent|null Existing event if duplicate found
	 */
	private static function check_for_duplicate( array $event_data ): ?ConversionEvent {
		// Check by source event ID first
		if ( ! empty( $event_data['source_event_id'] ) ) {
			$existing = ConversionEvent::load_by_event_id( $event_data['source_event_id'], $event_data['source'] );
			if ( $existing ) {
				return $existing;
			}
		}

		// Check by multiple criteria for potential duplicates
		$criteria = [
			'client_id' => $event_data['client_id'],
			'event_type' => $event_data['event_type'],
			'source' => $event_data['source'],
		];

		// Add user identification if available
		if ( ! empty( $event_data['user_id'] ) ) {
			$criteria['user_id'] = $event_data['user_id'];
		}

		// Check within a time window (5 minutes)
		if ( ! empty( $event_data['created_at'] ) ) {
			$timestamp = strtotime( $event_data['created_at'] );
			$criteria['period_start'] = date( 'Y-m-d H:i:s', $timestamp - 300 ); // 5 minutes before
			$criteria['period_end'] = date( 'Y-m-d H:i:s', $timestamp + 300 );   // 5 minutes after
		}

		$potential_duplicates = ConversionEventsTable::get_events( $criteria, 10, 0 );

		// Check each potential duplicate for similarity
		foreach ( $potential_duplicates as $duplicate_data ) {
			if ( self::are_events_similar( $event_data, $duplicate_data ) ) {
				return new ConversionEvent( $duplicate_data );
			}
		}

		return null;
	}

	/**
	 * Determine if two events are similar (potential duplicates)
	 *
	 * @param array $event1 First event data
	 * @param array $event2 Second event data
	 * @return bool True if events are similar
	 */
	private static function are_events_similar( array $event1, array $event2 ): bool {
		// Same event type and source
		if ( $event1['event_type'] !== $event2['event_type'] || $event1['source'] !== $event2['source'] ) {
			return false;
		}

		// Same user if available
		if ( ! empty( $event1['user_id'] ) && ! empty( $event2['user_id'] ) ) {
			if ( $event1['user_id'] === $event2['user_id'] ) {
				return true;
			}
		}

		// Same session if available
		if ( ! empty( $event1['session_id'] ) && ! empty( $event2['session_id'] ) ) {
			if ( $event1['session_id'] === $event2['session_id'] ) {
				return true;
			}
		}

		// Similar timestamp and same IP
		if ( ! empty( $event1['ip_address'] ) && ! empty( $event2['ip_address'] ) ) {
			if ( $event1['ip_address'] === $event2['ip_address'] ) {
				$time1 = strtotime( $event1['created_at'] ?? 'now' );
				$time2 = strtotime( $event2['created_at'] ?? 'now' );
				if ( abs( $time1 - $time2 ) < 60 ) { // Within 1 minute
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine if an event is better quality than another
	 *
	 * @param array $event1 First event data
	 * @param array $event2 Second event data
	 * @return bool True if event1 is better quality
	 */
	private static function is_better_quality_event( array $event1, array $event2 ): bool {
		// Prefer events with more complete data
		$score1 = self::calculate_event_quality_score( $event1 );
		$score2 = self::calculate_event_quality_score( $event2 );

		return $score1 > $score2;
	}

	/**
	 * Calculate quality score for an event
	 *
	 * @param array $event_data Event data
	 * @return int Quality score
	 */
	private static function calculate_event_quality_score( array $event_data ): int {
		$score = 0;

		// Base fields
		$base_fields = [ 'event_value', 'user_id', 'session_id', 'page_url' ];
		foreach ( $base_fields as $field ) {
			if ( ! empty( $event_data[ $field ] ) ) {
				$score += 10;
			}
		}

		// UTM parameters
		$utm_fields = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
		foreach ( $utm_fields as $field ) {
			if ( ! empty( $event_data[ $field ] ) ) {
				$score += 5;
			}
		}

		// Event attributes
		if ( ! empty( $event_data['event_attributes'] ) ) {
			$attributes = is_array( $event_data['event_attributes'] ) 
				? $event_data['event_attributes'] 
				: json_decode( $event_data['event_attributes'], true );
			$score += count( $attributes ) * 2;
		}

		return $score;
	}

	/**
	 * Get conversion events with filtering and aggregation
	 *
	 * @param array $criteria Filter criteria
	 * @param array $options Query options
	 * @return array Query results with events and metadata
	 */
	public static function query_events( array $criteria = [], array $options = [] ): array {
		// Set defaults
		$options = wp_parse_args( $options, [
			'limit' => 50,
			'offset' => 0,
			'exclude_duplicates' => true,
			'include_summary' => false,
			'group_by' => null,
			'sort_by' => 'created_at',
			'sort_order' => 'DESC',
		] );

		// Add exclude duplicates to criteria if requested
		if ( $options['exclude_duplicates'] ) {
			$criteria['exclude_duplicates'] = true;
		}

		// Get events
		$events = ConversionEventsTable::get_events( $criteria, $options['limit'], $options['offset'] );
		$total_count = ConversionEventsTable::get_events_count( $criteria );

		$results = [
			'events' => $events,
			'total_count' => $total_count,
			'page_count' => ceil( $total_count / $options['limit'] ),
			'current_page' => floor( $options['offset'] / $options['limit'] ) + 1,
		];

		// Add summary if requested
		if ( $options['include_summary'] ) {
			$results['summary'] = self::get_events_summary( $criteria );
		}

		// Add grouping if requested
		if ( $options['group_by'] ) {
			$results['grouped'] = self::group_events_by_field( $events, $options['group_by'] );
		}

		return $results;
	}

	/**
	 * Get events summary statistics
	 *
	 * @param array $criteria Filter criteria
	 * @return array Summary statistics
	 */
	public static function get_events_summary( array $criteria = [] ): array {
		global $wpdb;

		$table_name = ConversionEventsTable::get_table_name();
		$where_clauses = [];
		$where_values = [];

		// Build WHERE clause
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

		if ( isset( $criteria['period_start'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[] = $criteria['period_start'];
		}

		if ( isset( $criteria['period_end'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[] = $criteria['period_end'];
		}

		if ( isset( $criteria['exclude_duplicates'] ) && $criteria['exclude_duplicates'] ) {
			$where_clauses[] = 'is_duplicate = 0';
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Get summary statistics
		$sql = "SELECT 
			COUNT(*) as total_events,
			COUNT(DISTINCT event_type) as unique_event_types,
			COUNT(DISTINCT source) as unique_sources,
			SUM(event_value) as total_value,
			AVG(event_value) as avg_value,
			SUM(CASE WHEN is_duplicate = 1 THEN 1 ELSE 0 END) as duplicate_count
			FROM $table_name $where_sql";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		$summary = $wpdb->get_row( $sql, ARRAY_A );

		// Get breakdown by event type
		$type_sql = "SELECT event_type, COUNT(*) as count, SUM(event_value) as total_value 
			FROM $table_name $where_sql 
			GROUP BY event_type 
			ORDER BY count DESC";

		if ( ! empty( $where_values ) ) {
			$type_sql = $wpdb->prepare( $type_sql, $where_values );
		}

		$breakdown = $wpdb->get_results( $type_sql, ARRAY_A );

		return [
			'total_events' => (int) $summary['total_events'],
			'unique_event_types' => (int) $summary['unique_event_types'],
			'unique_sources' => (int) $summary['unique_sources'],
			'total_value' => (float) $summary['total_value'],
			'avg_value' => (float) $summary['avg_value'],
			'duplicate_count' => (int) $summary['duplicate_count'],
			'breakdown_by_type' => $breakdown,
		];
	}

	/**
	 * Group events by a specific field
	 *
	 * @param array  $events Events array
	 * @param string $field Field to group by
	 * @return array Grouped events
	 */
	private static function group_events_by_field( array $events, string $field ): array {
		$grouped = [];

		foreach ( $events as $event ) {
			$group_key = $event[ $field ] ?? 'unknown';
			if ( ! isset( $grouped[ $group_key ] ) ) {
				$grouped[ $group_key ] = [
					'events' => [],
					'count' => 0,
					'total_value' => 0.0,
				];
			}

			$grouped[ $group_key ]['events'][] = $event;
			$grouped[ $group_key ]['count']++;
			$grouped[ $group_key ]['total_value'] += (float) $event['event_value'];
		}

		return $grouped;
	}

	/**
	 * Clear related caches when events are modified
	 *
	 * @param int    $client_id Client ID
	 * @param string $event_type Event type
	 * @return void
	 */
	private static function clear_related_caches( int $client_id, string $event_type ): void {
		// Clear conversion-related performance caches
		$cache_patterns = [
			"metrics_aggregated_client_{$client_id}_*",
			"conversion_events_client_{$client_id}_*",
			"conversion_summary_{$client_id}_*",
		];

		foreach ( $cache_patterns as $pattern ) {
			PerformanceCache::clear_cache_by_pattern( $pattern );
		}
	}

	/**
	 * Get conversion funnel analysis
	 *
	 * @param int   $client_id Client ID
	 * @param array $funnel_steps Array of event types in funnel order
	 * @param array $criteria Additional filter criteria
	 * @return array Funnel analysis data
	 */
	public static function get_conversion_funnel( int $client_id, array $funnel_steps, array $criteria = [] ): array {
		$criteria['client_id'] = $client_id;
		$criteria['exclude_duplicates'] = true;

		$funnel_data = [];

		foreach ( $funnel_steps as $step_index => $event_type ) {
			$step_criteria = array_merge( $criteria, [ 'event_type' => $event_type ] );
			$step_summary = self::get_events_summary( $step_criteria );

			$funnel_data[] = [
				'step' => $step_index + 1,
				'event_type' => $event_type,
				'event_name' => ConversionEventRegistry::get_event_type_name( $event_type ),
				'count' => $step_summary['total_events'],
				'total_value' => $step_summary['total_value'],
				'conversion_rate' => $step_index === 0 ? 100.0 : 
					( $funnel_data[0]['count'] > 0 ? ( $step_summary['total_events'] / $funnel_data[0]['count'] ) * 100 : 0 ),
				'drop_off_rate' => $step_index === 0 ? 0.0 : 
					( $funnel_data[ $step_index - 1 ]['count'] > 0 ? 
						( 1 - ( $step_summary['total_events'] / $funnel_data[ $step_index - 1 ]['count'] ) ) * 100 : 0 ),
			];
		}

		return $funnel_data;
	}

	/**
	 * Get top converting UTM campaigns
	 *
	 * @param int   $client_id Client ID
	 * @param array $criteria Additional filter criteria
	 * @param int   $limit Result limit
	 * @return array Top campaigns with conversion data
	 */
	public static function get_top_converting_campaigns( int $client_id, array $criteria = [], int $limit = 10 ): array {
		global $wpdb;

		$table_name = ConversionEventsTable::get_table_name();
		$where_clauses = [ 'client_id = %d', 'utm_campaign IS NOT NULL', 'utm_campaign != ""', 'is_duplicate = 0' ];
		$where_values = [ $client_id ];

		// Add additional criteria
		if ( isset( $criteria['event_type'] ) ) {
			$where_clauses[] = 'event_type = %s';
			$where_values[] = $criteria['event_type'];
		}

		if ( isset( $criteria['period_start'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[] = $criteria['period_start'];
		}

		if ( isset( $criteria['period_end'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[] = $criteria['period_end'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		$sql = "SELECT 
			utm_campaign,
			utm_source,
			utm_medium,
			COUNT(*) as conversion_count,
			SUM(event_value) as total_value,
			AVG(event_value) as avg_value
			FROM $table_name 
			$where_sql
			GROUP BY utm_campaign, utm_source, utm_medium
			ORDER BY conversion_count DESC
			LIMIT %d";

		$where_values[] = $limit;
		$sql = $wpdb->prepare( $sql, $where_values );

		return $wpdb->get_results( $sql, ARRAY_A );
	}
}
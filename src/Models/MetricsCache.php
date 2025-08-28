<?php
/**
 * Metrics Cache CRUD Operations
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\MetricsCacheTable;

/**
 * Metrics Cache class for CRUD operations
 * 
 * This class provides CRUD functionality for the wp_fp_metrics_cache table
 * to store and retrieve normalized metrics data from various data sources.
 */
class MetricsCache {

	/**
	 * Save a metric record to the cache
	 *
	 * @param int    $client_id     Client ID from the cliente post type
	 * @param string $source        Data source identifier (e.g., 'google_analytics_4')
	 * @param string $metric        Metric name (e.g., 'sessions', 'pageviews')
	 * @param string $period_start  Start of the period (Y-m-d H:i:s format)
	 * @param string $period_end    End of the period (Y-m-d H:i:s format)
	 * @param mixed  $value         Metric value (will be converted to string)
	 * @param array  $meta          Optional metadata as associative array
	 * @return int|false ID of the inserted record on success, false on failure
	 */
	public static function save( int $client_id, string $source, string $metric, string $period_start, string $period_end, $value, array $meta = [] ): int|false {
		global $wpdb;

		$table_name = MetricsCacheTable::get_table_name();

		$data = [
			'client_id'    => $client_id,
			'source'       => sanitize_text_field( $source ),
			'metric'       => sanitize_text_field( $metric ),
			'period_start' => sanitize_text_field( $period_start ),
			'period_end'   => sanitize_text_field( $period_end ),
			'value'        => (string) $value,
			'meta'         => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			'fetched_at'   => current_time( 'mysql' ),
		];

		$formats = [
			'%d', // client_id
			'%s', // source
			'%s', // metric
			'%s', // period_start
			'%s', // period_end
			'%s', // value
			'%s', // meta
			'%s', // fetched_at
		];

		$result = $wpdb->insert( $table_name, $data, $formats );

		return $result !== false ? $wpdb->insert_id : false;
	}

	/**
	 * Get a metric record by ID
	 *
	 * @param int $id Record ID
	 * @return object|null Metric record object or null if not found
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		$table_name = MetricsCacheTable::get_table_name();

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$id
			)
		);

		if ( $result && ! empty( $result->meta ) ) {
			$result->meta = json_decode( $result->meta, true );
		}

		return $result ?: null;
	}

	/**
	 * Get multiple metric records with optional filters
	 *
	 * @param array $args Query arguments
	 * @return array Array of metric record objects
	 */
	public static function get_metrics( array $args = [] ): array {
		global $wpdb;

		$table_name = MetricsCacheTable::get_table_name();

		$defaults = [
			'client_id'    => null,
			'source'       => null,
			'metric'       => null,
			'period_start' => null,
			'period_end'   => null,
			'limit'        => 100,
			'offset'       => 0,
			'order_by'     => 'fetched_at',
			'order'        => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = [];
		$where_values = [];

		if ( ! is_null( $args['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $args['client_id'];
		}

		if ( ! is_null( $args['source'] ) ) {
			$where_clauses[] = 'source = %s';
			$where_values[] = $args['source'];
		}

		if ( ! is_null( $args['metric'] ) ) {
			$where_clauses[] = 'metric = %s';
			$where_values[] = $args['metric'];
		}

		if ( ! is_null( $args['period_start'] ) ) {
			$where_clauses[] = 'period_start >= %s';
			$where_values[] = $args['period_start'];
		}

		if ( ! is_null( $args['period_end'] ) ) {
			$where_clauses[] = 'period_end <= %s';
			$where_values[] = $args['period_end'];
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$order_by = sanitize_sql_orderby( $args['order_by'] );
		$order = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $args['order'] ) : 'DESC';

		$sql = "SELECT * FROM $table_name $where_sql ORDER BY $order_by $order LIMIT %d OFFSET %d";

		$where_values[] = (int) $args['limit'];
		$where_values[] = (int) $args['offset'];

		if ( ! empty( $where_values ) ) {
			$prepared_sql = $wpdb->prepare( $sql, ...$where_values );
		} else {
			$prepared_sql = $sql;
		}

		$results = $wpdb->get_results( $prepared_sql );

		// Decode JSON meta for each result
		foreach ( $results as $result ) {
			if ( ! empty( $result->meta ) ) {
				$result->meta = json_decode( $result->meta, true );
			}
		}

		return $results ?: [];
	}

	/**
	 * Update a metric record
	 *
	 * @param int   $id   Record ID
	 * @param array $data Data to update
	 * @return bool True on success, false on failure
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$table_name = MetricsCacheTable::get_table_name();

		// Only allow updating specific fields
		$allowed_fields = [ 'value', 'meta', 'fetched_at' ];
		$update_data = [];
		$formats = [];

		foreach ( $data as $field => $value ) {
			if ( in_array( $field, $allowed_fields, true ) ) {
				if ( 'meta' === $field && is_array( $value ) ) {
					$update_data[ $field ] = wp_json_encode( $value );
				} else {
					$update_data[ $field ] = $value;
				}
				$formats[] = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a metric record
	 *
	 * @param int $id Record ID
	 * @return bool True on success, false on failure
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$table_name = MetricsCacheTable::get_table_name();

		$result = $wpdb->delete(
			$table_name,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete multiple records by criteria
	 *
	 * @param array $criteria Deletion criteria
	 * @return int Number of deleted records
	 */
	public static function delete_by_criteria( array $criteria ): int {
		global $wpdb;

		$table_name = MetricsCacheTable::get_table_name();

		$allowed_fields = [ 'client_id', 'source', 'metric' ];
		$where_data = [];
		$formats = [];

		foreach ( $criteria as $field => $value ) {
			if ( in_array( $field, $allowed_fields, true ) ) {
				$where_data[ $field ] = $value;
				$formats[] = is_int( $value ) ? '%d' : '%s';
			}
		}

		if ( empty( $where_data ) ) {
			return 0;
		}

		$result = $wpdb->delete( $table_name, $where_data, $formats );

		return $result !== false ? (int) $result : 0;
	}

	/**
	 * Count records with optional filters
	 *
	 * @param array $args Query arguments
	 * @return int Number of records
	 */
	public static function count( array $args = [] ): int {
		global $wpdb;

		$table_name = MetricsCacheTable::get_table_name();

		$where_clauses = [];
		$where_values = [];

		if ( ! empty( $args['client_id'] ) ) {
			$where_clauses[] = 'client_id = %d';
			$where_values[] = $args['client_id'];
		}

		if ( ! empty( $args['source'] ) ) {
			$where_clauses[] = 'source = %s';
			$where_values[] = $args['source'];
		}

		if ( ! empty( $args['metric'] ) ) {
			$where_clauses[] = 'metric = %s';
			$where_values[] = $args['metric'];
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$sql = "SELECT COUNT(*) FROM $table_name $where_sql";

		if ( ! empty( $where_values ) ) {
			$result = $wpdb->get_var( $wpdb->prepare( $sql, ...$where_values ) );
		} else {
			$result = $wpdb->get_var( $sql );
		}

		return (int) $result;
	}
}
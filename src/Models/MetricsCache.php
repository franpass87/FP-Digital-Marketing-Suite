<?php
/**
 * Metrics Cache CRUD Operations with option storage fallback.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Database\MetricsCacheTable;

/**
 * Metrics Cache class for CRUD operations.
 */
class MetricsCache {

        /**
         * Columns that can be safely used for ORDER BY clauses.
         *
         * @var string[]
         */
        private const ORDERABLE_COLUMNS = [
                'fetched_at',
                'period_start',
                'period_end',
                'client_id',
                'metric',
                'source',
        ];

        /**
         * Option key for fallback storage when the database table is unavailable.
         */
        private const FALLBACK_OPTION_KEY = 'fp_dms_metrics_cache_records';

        /**
         * Cached flag for whether option storage should be used.
         *
         * @var bool|null
         */
        private static ?bool $use_option_storage = null;

        /**
         * Save a metric record to the cache.
         *
         * @param int    $client_id    Client ID from the cliente post type.
         * @param string $source       Data source identifier (e.g., 'google_analytics_4').
         * @param string $metric       Metric name (e.g., 'sessions', 'pageviews').
         * @param string $period_start Start of the period (Y-m-d H:i:s format).
         * @param string $period_end   End of the period (Y-m-d H:i:s format).
         * @param mixed  $value        Metric value (will be converted to string).
         * @param array  $meta         Optional metadata as associative array.
         *
         * @return int|false ID of the inserted record on success, false on failure.
         */
        public static function save( int $client_id, string $source, string $metric, string $period_start, string $period_end, $value, array $meta = [] ): int|false {
                if ( ! self::using_option_storage() && self::is_database_available() ) {
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

                        $formats = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

                        $result = $wpdb->insert( $table_name, $data, $formats );

                        if ( false !== $result ) {
                                return (int) $wpdb->insert_id;
                        }

                        return false;
                }

                return self::save_to_option_storage( $client_id, $source, $metric, $period_start, $period_end, $value, $meta );
        }

        /**
         * Get a metric record by ID.
         *
         * @param int $id Record ID.
         *
         * @return object|null Metric record object or null if not found.
         */
        public static function get( int $id ): ?object {
                if ( ! self::using_option_storage() && self::is_database_available() ) {
                        global $wpdb;

                        $table_name = MetricsCacheTable::get_table_name();

                        $result = $wpdb->get_row(
                                $wpdb->prepare(
                                        "SELECT * FROM {$table_name} WHERE id = %d",
                                        $id
                                )
                        );

                        if ( $result ) {
                                if ( ! empty( $result->meta ) ) {
                                        $result->meta = json_decode( $result->meta, true );
                                }

                                return $result;
                        }
                }

                foreach ( self::get_option_records() as $record ) {
                        if ( (int) ( $record['id'] ?? 0 ) === $id ) {
                                return self::convert_record_to_object( $record );
                        }
                }

                return null;
        }

        /**
         * Get multiple metric records with optional filters.
         *
         * @param array $args Query arguments.
         *
         * @return array<int, object> Array of metric record objects.
         */
        public static function get_metrics( array $args = [] ): array {
                $results = [];

                if ( ! self::using_option_storage() && self::is_database_available() ) {
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

                        [ $order_by, $order_direction ] = self::sanitize_order_parameters(
                                (string) $args['order_by'],
                                (string) $args['order'],
                                $defaults['order_by'],
                                $defaults['order']
                        );

                        $where_clauses = [];
                        $where_values  = [];

                        self::add_filter_clause( 'client_id', $args['client_id'], $where_clauses, $where_values, true );
                        self::add_filter_clause( 'source', $args['source'], $where_clauses, $where_values );
                        self::add_filter_clause( 'metric', $args['metric'], $where_clauses, $where_values );

                        if ( null !== $args['period_start'] ) {
                                $where_clauses[] = 'period_start >= %s';
                                $where_values[]  = $args['period_start'];
                        }

                        if ( null !== $args['period_end'] ) {
                                $where_clauses[] = 'period_end <= %s';
                                $where_values[]  = $args['period_end'];
                        }

                        $where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

                        $limit  = max( 0, (int) $args['limit'] );
                        $offset = max( 0, (int) $args['offset'] );

                        $sql = sprintf(
                                'SELECT * FROM %s %s ORDER BY %s %s LIMIT %%d OFFSET %%d',
                                $table_name,
                                $where_sql,
                                $order_by,
                                $order_direction
                        );

                        $where_values[] = $limit;
                        $where_values[] = $offset;

                        $prepared_sql = $wpdb->prepare( $sql, ...$where_values );
                        $results      = $wpdb->get_results( $prepared_sql ) ?: [];

                        foreach ( $results as $result ) {
                                if ( ! empty( $result->meta ) ) {
                                        $result->meta = json_decode( $result->meta, true );
                                }
                        }
                }

                if ( empty( $results ) ) {
                        return self::get_metrics_from_option_storage( $args );
                }

                return $results;
        }

        /**
         * Update a metric record.
         *
         * @param int   $id   Record ID.
         * @param array $data Data to update.
         *
         * @return bool True on success, false on failure.
         */
        public static function update( int $id, array $data ): bool {
                $updated = false;

                if ( ! self::using_option_storage() && self::is_database_available() ) {
                        global $wpdb;

                        $table_name = MetricsCacheTable::get_table_name();
                        $allowed_fields = [ 'value', 'meta', 'fetched_at' ];
                        $update_data    = [];
                        $formats        = [];

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

                        if ( ! empty( $update_data ) ) {
                                $result = $wpdb->update(
                                        $table_name,
                                        $update_data,
                                        [ 'id' => $id ],
                                        $formats,
                                        [ '%d' ]
                                );

                                if ( false !== $result ) {
                                        $updated = true;
                                }
                        }
                }

                if ( ! $updated ) {
                        return self::update_option_record( $id, $data );
                }

                return true;
        }

        /**
         * Delete a metric record.
         *
         * @param int $id Record ID.
         *
         * @return bool True on success, false on failure.
         */
        public static function delete( int $id ): bool {
                $deleted = false;

                if ( ! self::using_option_storage() && self::is_database_available() ) {
                        global $wpdb;

                        $table_name = MetricsCacheTable::get_table_name();
                        $result     = $wpdb->delete( $table_name, [ 'id' => $id ], [ '%d' ] );

                        if ( false !== $result ) {
                                $deleted = true;
                        }
                }

                if ( ! $deleted ) {
                        return self::delete_option_record( $id );
                }

                return true;
        }

        /**
         * Delete multiple records by criteria.
         *
         * @param array $criteria Deletion criteria.
         *
         * @return int Number of deleted records.
         */
        public static function delete_by_criteria( array $criteria ): int {
                $deleted = 0;

                if ( ! self::using_option_storage() && self::is_database_available() ) {
                        global $wpdb;

                        $table_name = MetricsCacheTable::get_table_name();

                        $allowed_fields = [ 'client_id', 'source', 'metric' ];
                        $where_data     = [];
                        $formats        = [];

                        foreach ( $criteria as $field => $value ) {
                                if ( in_array( $field, $allowed_fields, true ) ) {
                                        $where_data[ $field ] = $value;
                                        $formats[]            = is_int( $value ) ? '%d' : '%s';
                                }
                        }

                        if ( ! empty( $where_data ) ) {
                                $result = $wpdb->delete( $table_name, $where_data, $formats );
                                if ( false !== $result ) {
                                        $deleted = (int) $result;
                                }
                        }
                }

                $deleted_option = self::delete_option_records_by_criteria( $criteria );

                return $deleted + $deleted_option;
        }

        /**
         * Count records with optional filters.
         *
         * @param array $args Query arguments.
         *
         * @return int Number of records.
         */
        public static function count( array $args = [] ): int {
                if ( ! self::using_option_storage() && self::is_database_available() ) {
                        global $wpdb;

                        $table_name = MetricsCacheTable::get_table_name();

                        $defaults = [
                                'client_id' => null,
                                'source'    => null,
                                'metric'    => null,
                        ];

                        $args = wp_parse_args( $args, $defaults );

                        $where_clauses = [];
                        $where_values  = [];

                        self::add_filter_clause( 'client_id', $args['client_id'], $where_clauses, $where_values, true );
                        self::add_filter_clause( 'source', $args['source'], $where_clauses, $where_values );
                        self::add_filter_clause( 'metric', $args['metric'], $where_clauses, $where_values );

                        $where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
                        $sql       = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";

                        if ( ! empty( $where_values ) ) {
                                $result = $wpdb->get_var( $wpdb->prepare( $sql, ...$where_values ) );
                        } else {
                                $result = $wpdb->get_var( $sql );
                        }

                        if ( null !== $result ) {
                                return (int) $result;
                        }
                }

                return self::count_option_records( $args );
        }

        /**
         * Add a sanitized filter clause to the WHERE portion of a query.
         *
         * @param string $column        Column name to filter.
         * @param mixed  $value         Filter value or list of values.
         * @param array  $where_clauses Reference to the WHERE clause array.
         * @param array  $where_values  Reference to the prepared value array.
         * @param bool   $is_numeric    Whether the column expects numeric values.
         */
        private static function add_filter_clause( string $column, $value, array &$where_clauses, array &$where_values, bool $is_numeric = false ): void {
                if ( null === $value ) {
                        return;
                }

                $values = is_array( $value ) ? $value : [ $value ];
                $sanitized_values = [];

                foreach ( $values as $single_value ) {
                        if ( null === $single_value ) {
                                continue;
                        }

                        $original_value  = (string) $single_value;
                        $normalized_value = trim( $original_value );

                        if ( $is_numeric ) {
                                if ( '' === $normalized_value || ! is_numeric( $normalized_value ) ) {
                                        continue;
                                }

                                $sanitized_value = abs( (int) $normalized_value );
                        } else {
                                $sanitized_value = sanitize_text_field( $normalized_value );

                                if ( '' === $sanitized_value && '0' !== $normalized_value ) {
                                        continue;
                                }
                        }

                        $sanitized_values[] = $is_numeric ? (int) $sanitized_value : $sanitized_value;
                }

                if ( empty( $sanitized_values ) ) {
                        return;
                }

                $placeholder = $is_numeric ? '%d' : '%s';

                if ( count( $sanitized_values ) > 1 || is_array( $value ) ) {
                        $placeholders   = implode( ', ', array_fill( 0, count( $sanitized_values ), $placeholder ) );
                        $where_clauses[] = sprintf( '%s IN (%s)', $column, $placeholders );
                } else {
                        $where_clauses[] = sprintf( '%s = %s', $column, $placeholder );
                }

                foreach ( $sanitized_values as $sanitized_value ) {
                        $where_values[] = $sanitized_value;
                }
        }

        /**
         * Sanitize ORDER BY parameters to ensure only whitelisted columns are used.
         *
         * @param string $order_by                Requested column.
         * @param string $order_direction         Requested direction (ASC/DESC).
         * @param string $default_order_by        Default column fallback.
         * @param string $default_order_direction Default direction fallback.
         *
         * @return array{0:string,1:string}
         */
        private static function sanitize_order_parameters( string $order_by, string $order_direction, string $default_order_by, string $default_order_direction ): array {
                $allowed_columns = self::ORDERABLE_COLUMNS;

                $default_order_by = strtolower( $default_order_by );
                if ( ! in_array( $default_order_by, $allowed_columns, true ) ) {
                        $default_order_by = 'fetched_at';
                }

                $order_by = strtolower( trim( $order_by ) );
                if ( ! in_array( $order_by, $allowed_columns, true ) ) {
                        $order_by = $default_order_by;
                }

                $allowed_directions = [ 'ASC', 'DESC' ];
                $order_direction    = strtoupper( trim( $order_direction ) );
                if ( ! in_array( $order_direction, $allowed_directions, true ) ) {
                        $order_direction = strtoupper( $default_order_direction );
                        if ( ! in_array( $order_direction, $allowed_directions, true ) ) {
                                $order_direction = 'DESC';
                        }
                }

                return [ $order_by, $order_direction ];
        }

        /**
         * Check whether option based storage is currently active.
         *
         * @return bool
         */
        private static function using_option_storage(): bool {
                if ( null === self::$use_option_storage ) {
                        self::$use_option_storage = self::is_database_available() ? false : true;
                }

                return true === self::$use_option_storage;
        }

        /**
         * Determine if a wpdb instance is available for database operations.
         *
         * @return bool
         */
        private static function is_database_available(): bool {
                global $wpdb;

                return isset( $wpdb )
                        && is_object( $wpdb )
                        && method_exists( $wpdb, 'insert' )
                        && method_exists( $wpdb, 'prepare' )
                        && method_exists( $wpdb, 'delete' )
                        && method_exists( $wpdb, 'update' );
        }

        /**
         * Retrieve all option stored records.
         *
         * @return array<int, array<string, mixed>>
         */
        private static function get_option_records(): array {
                $records = get_option( self::FALLBACK_OPTION_KEY, [] );

                return is_array( $records ) ? $records : [];
        }

        /**
         * Persist the provided records array to the option storage.
         *
         * @param array<int, array<string, mixed>> $records Records to save.
         */
        private static function update_option_records( array $records ): void {
                update_option( self::FALLBACK_OPTION_KEY, array_values( $records ), false );
        }

        /**
         * Persist a metric into option storage.
         *
         * @return int
         */
        private static function save_to_option_storage( int $client_id, string $source, string $metric, string $period_start, string $period_end, $value, array $meta ): int {
                $records = self::get_option_records();

                $new_id = 1;
                foreach ( $records as $record ) {
                        $new_id = max( $new_id, (int) ( $record['id'] ?? 0 ) + 1 );
                }

                $records[] = [
                        'id'           => $new_id,
                        'client_id'    => (int) $client_id,
                        'source'       => sanitize_text_field( $source ),
                        'metric'       => sanitize_text_field( $metric ),
                        'period_start' => sanitize_text_field( $period_start ),
                        'period_end'   => sanitize_text_field( $period_end ),
                        'value'        => (string) $value,
                        'meta'         => is_array( $meta ) ? $meta : [],
                        'fetched_at'   => current_time( 'mysql' ),
                ];

                self::update_option_records( $records );

                return $new_id;
        }

        /**
         * Convert a stored record into an object similar to wpdb output.
         *
         * @param array<string, mixed> $record Record data.
         */
        private static function convert_record_to_object( array $record ): object {
                $normalized = [
                        'id'           => (int) ( $record['id'] ?? 0 ),
                        'client_id'    => (int) ( $record['client_id'] ?? 0 ),
                        'source'       => (string) ( $record['source'] ?? '' ),
                        'metric'       => (string) ( $record['metric'] ?? '' ),
                        'period_start' => (string) ( $record['period_start'] ?? '' ),
                        'period_end'   => (string) ( $record['period_end'] ?? '' ),
                        'value'        => (string) ( $record['value'] ?? '0' ),
                        'meta'         => is_array( $record['meta'] ?? null ) ? $record['meta'] : [],
                        'fetched_at'   => (string) ( $record['fetched_at'] ?? '' ),
                ];

                return (object) $normalized;
        }

        /**
         * Retrieve metrics using option storage.
         *
         * @param array $args Query arguments.
         *
         * @return array<int, object>
         */
        private static function get_metrics_from_option_storage( array $args ): array {
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

                [ $order_by, $order_direction ] = self::sanitize_order_parameters(
                        (string) $args['order_by'],
                        (string) $args['order'],
                        $defaults['order_by'],
                        $defaults['order']
                );

                $records = self::get_option_records();

                $filtered = array_filter(
                        $records,
                        function ( $record ) use ( $args ) {
                                if ( null !== $args['client_id'] ) {
                                        $client_ids = is_array( $args['client_id'] ) ? $args['client_id'] : [ $args['client_id'] ];
                                        $client_ids = array_map( 'intval', $client_ids );

                                        if ( ! in_array( (int) ( $record['client_id'] ?? 0 ), $client_ids, true ) ) {
                                                return false;
                                        }
                                }

                                if ( null !== $args['source'] ) {
                                        $sources = is_array( $args['source'] ) ? $args['source'] : [ $args['source'] ];
                                        $sources = array_map( 'strval', $sources );

                                        if ( ! in_array( (string) ( $record['source'] ?? '' ), $sources, true ) ) {
                                                return false;
                                        }
                                }

                                if ( null !== $args['metric'] ) {
                                        $metrics = is_array( $args['metric'] ) ? $args['metric'] : [ $args['metric'] ];
                                        $metrics = array_map( 'strval', $metrics );

                                        if ( ! in_array( (string) ( $record['metric'] ?? '' ), $metrics, true ) ) {
                                                return false;
                                        }
                                }

                                if ( null !== $args['period_start'] ) {
                                        $record_start = strtotime( (string) ( $record['period_start'] ?? '' ) );
                                        if ( $record_start < strtotime( (string) $args['period_start'] ) ) {
                                                return false;
                                        }
                                }

                                if ( null !== $args['period_end'] ) {
                                        $record_end = strtotime( (string) ( $record['period_end'] ?? '' ) );
                                        if ( $record_end > strtotime( (string) $args['period_end'] ) ) {
                                                return false;
                                        }
                                }

                                return true;
                        }
                );

                usort(
                        $filtered,
                        function ( $a, $b ) use ( $order_by, $order_direction ) {
                                $value_a = $a[ $order_by ] ?? null;
                                $value_b = $b[ $order_by ] ?? null;

                                if ( $value_a === $value_b ) {
                                        return 0;
                                }

                                $comparison = ( $value_a <=> $value_b );

                                return 'ASC' === $order_direction ? $comparison : -$comparison;
                        }
                );

                $limit  = max( 0, (int) $args['limit'] );
                $offset = max( 0, (int) $args['offset'] );

                if ( 0 === $limit ) {
                        $slice = array_slice( $filtered, $offset );
                } else {
                        $slice = array_slice( $filtered, $offset, $limit );
                }

                return array_map( [ self::class, 'convert_record_to_object' ], $slice );
        }

        /**
         * Update an option stored record.
         */
        private static function update_option_record( int $id, array $data ): bool {
                $records = self::get_option_records();

                foreach ( $records as $index => $record ) {
                        if ( (int) ( $record['id'] ?? 0 ) !== $id ) {
                                continue;
                        }

                        if ( array_key_exists( 'value', $data ) ) {
                                $records[ $index ]['value'] = (string) $data['value'];
                        }

                        if ( array_key_exists( 'meta', $data ) ) {
                                $records[ $index ]['meta'] = is_array( $data['meta'] ) ? $data['meta'] : [];
                        }

                        if ( array_key_exists( 'fetched_at', $data ) ) {
                                $records[ $index ]['fetched_at'] = (string) $data['fetched_at'];
                        }

                        self::update_option_records( $records );

                        return true;
                }

                return false;
        }

        /**
         * Delete a single option record.
         */
        private static function delete_option_record( int $id ): bool {
                $records        = self::get_option_records();
                $original_count = count( $records );

                $records = array_filter(
                        $records,
                        static function ( $record ) use ( $id ) {
                                return (int) ( $record['id'] ?? 0 ) !== $id;
                        }
                );

                if ( count( $records ) === $original_count ) {
                        return false;
                }

                self::update_option_records( $records );

                return true;
        }

        /**
         * Delete records matching the provided criteria from option storage.
         */
        private static function delete_option_records_by_criteria( array $criteria ): int {
                $allowed_fields = [ 'client_id', 'source', 'metric' ];
                $records        = self::get_option_records();
                $original_count = count( $records );

                $records = array_filter(
                        $records,
                        static function ( $record ) use ( $criteria, $allowed_fields ) {
                                foreach ( $criteria as $field => $value ) {
                                        if ( ! in_array( $field, $allowed_fields, true ) ) {
                                                continue;
                                        }

                                        $values = is_array( $value ) ? $value : [ $value ];

                                        if ( 'client_id' === $field ) {
                                                $values      = array_map( 'intval', $values );
                                                $record_value = (int) ( $record[ $field ] ?? 0 );
                                        } else {
                                                $values      = array_map( 'strval', $values );
                                                $record_value = (string) ( $record[ $field ] ?? '' );
                                        }

                                        if ( ! in_array( $record_value, $values, true ) ) {
                                                return true; // keep record, it does not match all criteria.
                                        }
                                }

                                return false; // remove record when all criteria match.
                        }
                );

                $deleted = $original_count - count( $records );

                if ( $deleted > 0 ) {
                                self::update_option_records( $records );
                }

                return $deleted;
        }

        /**
         * Count option stored records using the provided filters.
         */
        private static function count_option_records( array $args ): int {
                $records = self::get_metrics_from_option_storage( array_merge( $args, [
                        'limit'  => 0,
                        'offset' => 0,
                ] ) );

                return count( $records );
        }
}

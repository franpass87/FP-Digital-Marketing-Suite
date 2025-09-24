<?php
/**
 * Conversions export helpers.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Tools\Exports;

use DateTimeInterface;

/**
 * Utility helpers for building sanitized conversion exports.
 */
class ConversionsExporter {

        /**
         * Columns included in the conversion export.
         */
        private const EXPORT_COLUMNS = [
                'id',
                'event_id',
                'event_type',
                'event_name',
                'client_id',
                'source',
                'source_event_id',
                'user_id',
                'session_id',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'event_value',
                'currency',
                'event_attributes',
                'page_url',
                'referrer_url',
                'ip_address',
                'user_agent',
                'is_duplicate',
                'created_at',
                'processed_at',
        ];

        /**
         * Retrieve export headers.
         *
         * @return array<string> Header labels matching table column names.
         */
        public static function get_headers(): array {
                return self::EXPORT_COLUMNS;
        }

        /**
         * Build a sanitized export row from raw event data.
         *
         * @param array $event Event data as retrieved from the repository.
         * @return array<string> Row values aligned with {@see self::EXPORT_COLUMNS}.
         */
        public static function build_row( array $event ): array {
                $row = [];

                foreach ( self::EXPORT_COLUMNS as $column ) {
                        $row[] = self::prepare_value( $column, $event[ $column ] ?? null );
                }

                return $row;
        }

        /**
         * Build multiple export rows at once.
         *
         * @param array $events Raw events.
         * @return array<int, array<string>> Prepared rows.
         */
        public static function build_rows( array $events ): array {
                $rows = [];

                foreach ( $events as $event ) {
                        if ( ! is_array( $event ) ) {
                                continue;
                        }

                        $rows[] = self::build_row( $event );
                }

                return $rows;
        }

        /**
         * Prepare individual cell values depending on the column.
         *
         * @param string     $column Column name.
         * @param mixed|null $value  Raw value.
         * @return string Sanitized string representation.
         */
        private static function prepare_value( string $column, $value ): string {
                switch ( $column ) {
                        case 'event_attributes':
                                return self::normalize_attributes( $value );

                        case 'created_at':
                        case 'processed_at':
                                return self::normalize_datetime( $value );

                        case 'event_value':
                                return self::normalize_number( $value );

                        case 'is_duplicate':
                                return (string) ( (int) (bool) $value );

                        case 'source_event_id':
                                return self::normalize_source_event_id( $value );

                        default:
                                return self::cast_to_string( $value );
                }
        }

        /**
         * Normalize event attribute payloads.
         *
         * @param mixed $value Raw attributes value.
         * @return string JSON-encoded representation or empty string.
         */
        private static function normalize_attributes( $value ): string {
                if ( null === $value || '' === $value ) {
                        return '';
                }

                if ( is_string( $value ) ) {
                        return $value;
                }

                if ( is_array( $value ) || is_object( $value ) ) {
                        $encoder = function_exists( 'wp_json_encode' ) ? 'wp_json_encode' : 'json_encode';
                        $encoded = $encoder( $value );

                        return is_string( $encoded ) ? $encoded : '';
                }

                return self::cast_to_string( $value );
        }

        /**
         * Normalize datetime values to the MySQL format.
         *
         * @param mixed $value Raw datetime value.
         * @return string Normalized datetime or empty string.
         */
        private static function normalize_datetime( $value ): string {
                if ( $value instanceof DateTimeInterface ) {
                        return $value->format( 'Y-m-d H:i:s' );
                }

                if ( is_int( $value ) ) {
                        return $value > 0 ? gmdate( 'Y-m-d H:i:s', $value ) : '';
                }

                if ( is_string( $value ) ) {
                        $trimmed = trim( $value );
                        if ( '' === $trimmed ) {
                                return '';
                        }

                        if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $trimmed ) ) {
                                return $trimmed;
                        }

                        if ( ctype_digit( $trimmed ) ) {
                                $timestamp = (int) $trimmed;
                                return $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
                        }

                        $timestamp = strtotime( $trimmed );
                        if ( false !== $timestamp ) {
                                return gmdate( 'Y-m-d H:i:s', $timestamp );
                        }

                        return '';
                }

                if ( is_numeric( $value ) ) {
                        $timestamp = (int) $value;
                        return $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
                }

                return '';
        }

        /**
         * Normalize numeric values.
         *
         * @param mixed $value Raw numeric value.
         * @return string Numeric string.
         */
        private static function normalize_number( $value ): string {
                if ( is_numeric( $value ) ) {
                        return (string) (float) $value;
                }

                return '0';
        }

        /**
         * Ensure the source event identifier does not contain duplicates.
         *
         * @param mixed $value Raw identifier value.
         * @return string De-duplicated identifier.
         */
        private static function normalize_source_event_id( $value ): string {
                if ( null === $value ) {
                        return '';
                }

                if ( is_array( $value ) ) {
                        $collected = [];
                        array_walk_recursive(
                                $value,
                                static function ( $item ) use ( &$collected ): void {
                                        if ( null === $item ) {
                                                return;
                                        }

                                        $item = (string) $item;
                                        if ( '' === $item ) {
                                                return;
                                        }

                                        $collected[] = $item;
                                }
                        );

                        if ( empty( $collected ) ) {
                                return '';
                        }

                        $value = implode( ',', array_unique( $collected ) );
                }

                if ( $value instanceof DateTimeInterface ) {
                        return $value->format( 'Y-m-d H:i:s' );
                }

                $string = self::cast_to_string( $value );
                if ( '' === $string ) {
                        return '';
                }

                $parts = array_filter(
                        array_map( 'trim', explode( ',', $string ) ),
                        static function ( string $part ): bool {
                                return '' !== $part;
                        }
                );

                if ( empty( $parts ) ) {
                        return '';
                }

                $unique = array_values( array_unique( $parts ) );

                return implode( ',', $unique );
        }

        /**
         * Cast generic values to string.
         *
         * @param mixed $value Value to cast.
         * @return string String representation.
         */
        private static function cast_to_string( $value ): string {
                if ( null === $value ) {
                        return '';
                }

                if ( $value instanceof DateTimeInterface ) {
                        return $value->format( 'Y-m-d H:i:s' );
                }

                if ( is_bool( $value ) ) {
                        return $value ? '1' : '0';
                }

                if ( is_scalar( $value ) ) {
                        return (string) $value;
                }

                if ( is_array( $value ) || is_object( $value ) ) {
                        $encoder = function_exists( 'wp_json_encode' ) ? 'wp_json_encode' : 'json_encode';
                        $encoded = $encoder( $value );

                        return is_string( $encoded ) ? $encoded : '';
                }

                return '';
        }
}

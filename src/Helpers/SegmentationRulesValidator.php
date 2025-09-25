<?php
/**
 * Segmentation rules validation helper.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use WP_Error;

/**
 * Provides validation and sanitization for audience segmentation rules payloads.
 */
class SegmentationRulesValidator {

        /**
         * Maximum number of conditions allowed in a single rules payload.
         */
        private const MAX_CONDITIONS = 25;

        /**
         * Maximum length for string based rule values.
         */
        private const MAX_VALUE_LENGTH = 200;

        /**
         * Maximum lookback window (in days) accepted for time based operators.
         */
        private const MAX_LOOKBACK_DAYS = 365;

        /**
         * Allowed logical operators for rule groups.
         */
        private const ALLOWED_LOGIC = [ 'AND', 'OR' ];

        /**
         * Mapping between rule types and the supported fields.
         *
         * @var array<string, array<string, string>>
         */
        private const RULE_TYPE_FIELDS = [
                'event' => [
                        'signup'        => 'signup',
                        'purchase'      => 'purchase',
                        'lead_submit'   => 'lead_submit',
                        'contact_form'  => 'contact_form',
                        'download'      => 'download',
                        'subscribe'     => 'subscribe',
                        'video_watch'   => 'video_watch',
                ],
                'utm' => [
                        'utm_source'   => 'utm_source',
                        'utm_medium'   => 'utm_medium',
                        'utm_campaign' => 'utm_campaign',
                        'utm_term'     => 'utm_term',
                        'utm_content'  => 'utm_content',
                ],
                'device' => [
                        'device_type' => 'device_type',
                ],
                'geography' => [
                        'country' => 'country',
                ],
                'behavior' => [
                        'visit_frequency' => 'visit_frequency',
                        'total_events'    => 'total_events',
                        'recency'         => 'recency',
                ],
                'value' => [
                        'total_value' => 'total_value',
                ],
        ];

        /**
         * Operators supported by the segmentation engine.
         */
        private const ALLOWED_OPERATORS = [
                SegmentationEngine::OP_EQUALS,
                SegmentationEngine::OP_NOT_EQUALS,
                SegmentationEngine::OP_CONTAINS,
                SegmentationEngine::OP_NOT_CONTAINS,
                SegmentationEngine::OP_GREATER_THAN,
                SegmentationEngine::OP_LESS_THAN,
                SegmentationEngine::OP_IN_LAST_DAYS,
                SegmentationEngine::OP_NOT_IN_LAST_DAYS,
        ];

        /**
         * Operators that require numeric values.
         */
        private const NUMERIC_OPERATORS = [
                SegmentationEngine::OP_GREATER_THAN,
                SegmentationEngine::OP_LESS_THAN,
                SegmentationEngine::OP_IN_LAST_DAYS,
                SegmentationEngine::OP_NOT_IN_LAST_DAYS,
        ];

        /**
         * Validate and sanitize a rules payload.
         *
         * @param mixed $rules Raw rules data provided by the client.
         * @return array|WP_Error Normalized rules array on success or WP_Error when validation fails.
         */
        public static function validate( $rules ) {
                if ( $rules instanceof \stdClass ) {
                        $rules = (array) $rules;
                }

                if ( ! is_array( $rules ) ) {
                        return new WP_Error(
                                'invalid_rules',
                                __( 'Invalid segment rules payload.', 'fp-digital-marketing' ),
                                [ 'status' => 400 ]
                        );
                }

                $logic = isset( $rules['logic'] ) ? strtoupper( sanitize_text_field( (string) $rules['logic'] ) ) : 'AND';
                if ( ! in_array( $logic, self::ALLOWED_LOGIC, true ) ) {
                        return new WP_Error(
                                'invalid_rule_logic',
                                __( 'Invalid logical operator supplied for segment rules.', 'fp-digital-marketing' ),
                                [ 'status' => 400 ]
                        );
                }

                $conditions = $rules['conditions'] ?? [];
                if ( $conditions instanceof \stdClass ) {
                        $conditions = (array) $conditions;
                }

                if ( ! is_array( $conditions ) ) {
                        return new WP_Error(
                                'invalid_rule_conditions',
                                __( 'Segment rule conditions must be provided as an array.', 'fp-digital-marketing' ),
                                [ 'status' => 400 ]
                        );
                }

                if ( empty( $conditions ) ) {
                        return new WP_Error(
                                'missing_rule_conditions',
                                __( 'At least one segment rule condition is required.', 'fp-digital-marketing' ),
                                [ 'status' => 400 ]
                        );
                }

                if ( count( $conditions ) > self::MAX_CONDITIONS ) {
                        return new WP_Error(
                                'too_many_conditions',
                                __( 'Too many segment rule conditions supplied.', 'fp-digital-marketing' ),
                                [ 'status' => 400 ]
                        );
                }

                $normalized_conditions = [];

                foreach ( $conditions as $condition ) {
                        if ( $condition instanceof \stdClass ) {
                                $condition = (array) $condition;
                        }

                        if ( ! is_array( $condition ) ) {
                                return new WP_Error(
                                        'invalid_rule_condition',
                                        __( 'Each segment rule condition must be an object.', 'fp-digital-marketing' ),
                                        [ 'status' => 400 ]
                                );
                        }

                        $type = sanitize_key( $condition['type'] ?? '' );
                        if ( '' === $type || ! array_key_exists( $type, self::RULE_TYPE_FIELDS ) ) {
                                return new WP_Error(
                                        'invalid_rule_type',
                                        __( 'One or more segment rules contain an unsupported type.', 'fp-digital-marketing' ),
                                        [ 'status' => 400 ]
                                );
                        }

                        $field = sanitize_key( $condition['field'] ?? '' );
                        if ( '' === $field || ! array_key_exists( $field, self::RULE_TYPE_FIELDS[ $type ] ) ) {
                                return new WP_Error(
                                        'invalid_rule_field',
                                        __( 'One or more segment rules contain an unsupported field.', 'fp-digital-marketing' ),
                                        [ 'status' => 400 ]
                                );
                        }

                        $operator = sanitize_key( $condition['operator'] ?? '' );
                        if ( '' === $operator || ! in_array( $operator, self::ALLOWED_OPERATORS, true ) ) {
                                return new WP_Error(
                                        'invalid_rule_operator',
                                        __( 'One or more segment rules contain an unsupported operator.', 'fp-digital-marketing' ),
                                        [ 'status' => 400 ]
                                );
                        }

                        $value = $condition['value'] ?? '';
                        $sanitized_value = self::sanitize_condition_value( $value, $operator, $type, $field );

                        if ( $sanitized_value instanceof WP_Error ) {
                                return $sanitized_value;
                        }

                        $normalized_conditions[] = [
                                'type'     => $type,
                                'field'    => $field,
                                'operator' => $operator,
                                'value'    => $sanitized_value,
                        ];
                }

                return [
                        'logic'      => $logic,
                        'conditions' => $normalized_conditions,
                ];
        }

        /**
         * Sanitize a condition value for the provided operator.
         *
         * @param mixed  $value    Raw value from the request.
         * @param string $operator Operator used for the comparison.
         * @param string $type     Rule type.
         * @param string $field    Rule field.
         * @return string|int|float|WP_Error Sanitized value or WP_Error when invalid.
         */
        private static function sanitize_condition_value( $value, string $operator, string $type, string $field ) {
                if ( in_array( $operator, self::NUMERIC_OPERATORS, true ) ) {
                        if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
                                return new WP_Error(
                                        'invalid_rule_value',
                                        __( 'Numeric operators require a numeric value.', 'fp-digital-marketing' ),
                                        [ 'status' => 400 ]
                                );
                        }

                        $number = (float) $value;

                        if ( SegmentationEngine::OP_IN_LAST_DAYS === $operator || SegmentationEngine::OP_NOT_IN_LAST_DAYS === $operator ) {
                                $days = (int) round( $number );

                                if ( $days <= 0 || $days > self::MAX_LOOKBACK_DAYS ) {
                                        return new WP_Error(
                                                'invalid_rule_value',
                                                __( 'The selected time window is outside of the supported range.', 'fp-digital-marketing' ),
                                                [ 'status' => 400 ]
                                        );
                                }

                                return $days;
                        }

                        if ( $number < 0 ) {
                                return new WP_Error(
                                        'invalid_rule_value',
                                        __( 'Numeric rule values must be zero or greater.', 'fp-digital-marketing' ),
                                        [ 'status' => 400 ]
                                );
                        }

                        return $number;
                }

                $string_value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
                if ( '' === $string_value ) {
                        return new WP_Error(
                                'invalid_rule_value',
                                __( 'A value is required for each segment rule condition.', 'fp-digital-marketing' ),
                                [ 'status' => 400 ]
                        );
                }

                if ( mb_strlen( $string_value ) > self::MAX_VALUE_LENGTH ) {
                        $string_value = mb_substr( $string_value, 0, self::MAX_VALUE_LENGTH );
                }

                if ( 'geography' === $type && 'country' === $field ) {
                        $string_value = strtoupper( substr( $string_value, 0, 2 ) );
                }

                return $string_value;
        }
}

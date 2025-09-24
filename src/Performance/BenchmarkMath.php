<?php
/**
 * Benchmark Math utilities.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Performance;

/**
 * Helper providing safe mathematical helpers for benchmark calculations.
 */
class BenchmarkMath {

	/**
	 * Safely divide two numbers, avoiding division by zero.
	 *
	 * @param mixed $numerator   Numerator value.
	 * @param mixed $denominator Denominator value.
	 * @param float $default     Default value when division is not possible.
	 * @return float Result of the division or the provided default.
	 */
	public static function safe_divide( $numerator, $denominator, float $default = 0.0 ): float {
		if ( ! is_numeric( $numerator ) || ! is_numeric( $denominator ) ) {
			return $default;
		}

		$denominator = (float) $denominator;

		if ( 0.0 === $denominator ) {
			return $default;
		}

		return (float) $numerator / $denominator;
	}

	/**
	 * Calculate the average of a list of values safely.
	 *
	 * @param array $values  Values to average.
	 * @param float $default Default value when no numeric values are present.
	 * @return float Average value.
	 */
	public static function average( array $values, float $default = 0.0 ): float {
		$numeric_values = self::filter_numeric_values( $values );

		if ( empty( $numeric_values ) ) {
			return $default;
		}

		$sum = array_sum( $numeric_values );

		return self::safe_divide( $sum, count( $numeric_values ), $default );
	}

	/**
	 * Calculate a percentage safely.
	 *
	 * @param mixed $portion Portion value.
	 * @param mixed $total   Total value.
	 * @param float $default Default percentage when calculation is not possible.
	 * @return float Percentage value.
	 */
	public static function safe_percentage( $portion, $total, float $default = 0.0 ): float {
		return self::safe_divide( $portion, $total, $default ) * 100;
	}

	/**
	 * Get the minimum numeric value from the array.
	 *
	 * @param array $values  Values to inspect.
	 * @param float $default Default value when no numeric values are present.
	 * @return float Minimum value.
	 */
	public static function min( array $values, float $default = 0.0 ): float {
		$numeric_values = self::filter_numeric_values( $values );

		if ( empty( $numeric_values ) ) {
			return $default;
		}

		return (float) min( $numeric_values );
	}

	/**
	 * Get the maximum numeric value from the array.
	 *
	 * @param array $values  Values to inspect.
	 * @param float $default Default value when no numeric values are present.
	 * @return float Maximum value.
	 */
	public static function max( array $values, float $default = 0.0 ): float {
		$numeric_values = self::filter_numeric_values( $values );

		if ( empty( $numeric_values ) ) {
			return $default;
		}

		return (float) max( $numeric_values );
	}

	/**
	 * Normalize an array ensuring only numeric values are retained.
	 *
	 * @param array $values Values to filter.
	 * @return array<float> Numeric values.
	 */
	private static function filter_numeric_values( array $values ): array {
		$numeric_values = [];

		foreach ( $values as $value ) {
			if ( is_numeric( $value ) ) {
				$numeric_values[] = (float) $value;
			}
		}

		return $numeric_values;
	}
}

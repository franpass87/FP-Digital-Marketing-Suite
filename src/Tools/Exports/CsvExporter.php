<?php
/**
 * CSV Export utilities.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Tools\Exports;

/**
 * Helper class providing safe CSV export helpers.
 */
class CsvExporter {

	/**
	 * Sanitize a single CSV value to avoid injection issues.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string Sanitized string representation.
	 */
	public static function sanitize_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$encoder = function_exists( 'wp_json_encode' ) ? 'wp_json_encode' : 'json_encode';
			$encoded = $encoder( $value );
			$value = false !== $encoded ? $encoded : '';
		} elseif ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		} elseif ( null === $value ) {
			$value = '';
		}

		$value = (string) $value;

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$value = wp_strip_all_tags( $value );
		} else {
			$value = strip_tags( $value );
		}

		$value = str_replace( [ "\r", "\n", "\t" ], ' ', $value );
		$value = trim( $value );

		if ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@' ], true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}

	/**
	 * Sanitize an entire CSV row.
	 *
	 * @param array $row Row values.
	 * @return array Sanitized values.
	 */
	public static function sanitize_row( array $row ): array {
		return array_map( [ __CLASS__, 'sanitize_value' ], $row );
	}

	/**
	 * Write a sanitized row to a CSV handle.
	 *
	 * @param resource $handle    Output handle.
	 * @param array    $row       Row values.
	 * @param string   $separator Field separator.
	 * @return void
	 */
	public static function write_row( $handle, array $row, string $separator = ',' ): void {
		if ( ! is_resource( $handle ) ) {
			return;
		}

                $enclosure = '"';
                $escape = '\\';

                fputcsv( $handle, self::sanitize_row( $row ), $separator, $enclosure, $escape );
        }

	/**
	 * Convert a row to a CSV string.
	 *
	 * @param array  $row       Row values.
	 * @param string $separator Field separator.
	 * @return string CSV string without trailing newline.
	 */
	public static function row_to_csv( array $row, string $separator = ',' ): string {
		$temp = fopen( 'php://temp', 'r+' );
		if ( false === $temp ) {
			return '';
		}

		self::write_row( $temp, $row, $separator );
		rewind( $temp );

		$line = stream_get_contents( $temp );
		fclose( $temp );

		if ( false === $line ) {
			return '';
		}

		return rtrim( $line, "\r\n" );
	}
}

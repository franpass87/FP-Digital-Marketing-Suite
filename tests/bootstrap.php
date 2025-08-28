<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package FP_Digital_Marketing_Suite
 */

// Define constants for WordPress if not already defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Load WordPress test environment if available, otherwise create minimal environment.
if ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	require_once '/tmp/wordpress-tests-lib/includes/bootstrap.php';
} else {
	// Create minimal WordPress environment for testing
	if ( ! defined( 'WPINC' ) ) {
		define( 'WPINC', 'wp-includes' );
	}
	
	// Mock WordPress functions for testing
	if ( ! function_exists( 'wp_parse_args' ) ) {
		function wp_parse_args( $args, $defaults = '' ) {
			if ( is_object( $args ) ) {
				$parsed_args = get_object_vars( $args );
			} elseif ( is_array( $args ) ) {
				$parsed_args =& $args;
			} else {
				wp_parse_str( $args, $parsed_args );
			}

			if ( is_array( $defaults ) ) {
				return array_merge( $defaults, $parsed_args );
			}
			return $parsed_args;
		}
	}

	if ( ! function_exists( 'wp_parse_str' ) ) {
		function wp_parse_str( $string, &$array ) {
			parse_str( $string, $array );
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) {
			return trim( strip_tags( $str ) );
		}
	}

	if ( ! function_exists( 'sanitize_sql_orderby' ) ) {
		function sanitize_sql_orderby( $orderby ) {
			$orderby_array = explode( ',', $orderby );
			$orderby_array = array_map( 'trim', $orderby_array );
			$orderby_array = array_filter( $orderby_array, function( $column ) {
				return preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $column );
			});
			return implode( ', ', $orderby_array );
		}
	}

	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $data, $options = 0, $depth = 512 ) {
			return json_encode( $data, $options, $depth );
		}
	}

	if ( ! function_exists( 'current_time' ) ) {
		function current_time( $type ) {
			return date( 'Y-m-d H:i:s' );
		}
	}

	// Mock global $wpdb for testing
	global $wpdb;
	$wpdb = new stdClass();
	$wpdb->prefix = 'wp_';
}

// Load the plugin's autoloader.
require_once dirname( __DIR__ ) . '/fp-digital-marketing-suite.php';
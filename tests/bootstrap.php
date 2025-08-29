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

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'plugin_dir_path' ) ) {
		function plugin_dir_path( $file ) {
			return dirname( $file ) . '/';
		}
	}

	if ( ! function_exists( 'plugin_dir_url' ) ) {
		function plugin_dir_url( $file ) {
			return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
		}
	}

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
			// Mock implementation - do nothing
		}
	}

	if ( ! function_exists( 'register_activation_hook' ) ) {
		function register_activation_hook( $file, $callback ) {
			// Mock implementation - do nothing
		}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
			// Mock implementation - do nothing
		}
	}

	if ( ! function_exists( 'register_deactivation_hook' ) ) {
		function register_deactivation_hook( $file, $callback ) {
			// Mock implementation - do nothing
		}
	}

	// Mock additional WordPress functions needed for SEO tests
	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post_id = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_post'] ) ) {
				return $wp_mock_functions['get_post']( $post_id );
			}
			// If it's already a post object, return it
			if ( is_object( $post_id ) && isset( $post_id->ID ) ) {
				return $post_id;
			}
			return null;
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $post_id, $key = '', $single = false ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_post_meta'] ) ) {
				return $wp_mock_functions['get_post_meta']( $post_id, $key, $single );
			}
			return $single ? '' : [];
		}
	}

	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post = 0 ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_the_title'] ) ) {
				return $wp_mock_functions['get_the_title']( $post );
			}
			return '';
		}
	}

	if ( ! function_exists( 'get_the_excerpt' ) ) {
		function get_the_excerpt( $post = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_the_excerpt'] ) ) {
				return $wp_mock_functions['get_the_excerpt']( $post );
			}
			return '';
		}
	}

	if ( ! function_exists( 'get_bloginfo' ) ) {
		function get_bloginfo( $show = '' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_bloginfo'] ) ) {
				return $wp_mock_functions['get_bloginfo']( $show );
			}
			return '';
		}
	}

	if ( ! function_exists( 'is_front_page' ) ) {
		function is_front_page() {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['is_front_page'] ) ) {
				return $wp_mock_functions['is_front_page']();
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		function wp_strip_all_tags( $string, $remove_breaks = false ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_strip_all_tags'] ) ) {
				return $wp_mock_functions['wp_strip_all_tags']( $string );
			}
			return strip_tags( $string );
		}
	}

	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $post = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_post_type'] ) ) {
				return $wp_mock_functions['get_post_type']( $post );
			}
			return 'post';
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $option, $default = false ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_option'] ) ) {
				return $wp_mock_functions['get_option']( $option, $default );
			}
			return $default;
		}
	}

	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink( $post = 0 ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_permalink'] ) ) {
				return $wp_mock_functions['get_permalink']( $post );
			}
			return '';
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['esc_url'] ) ) {
				return $wp_mock_functions['esc_url']( $url );
			}
			return $url;
		}
	}

	if ( ! function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( $url, $component = -1 ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_parse_url'] ) ) {
				return $wp_mock_functions['wp_parse_url']( $url, $component );
			}
			return parse_url( $url, $component );
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $text, $domain = 'default' ) {
			return htmlspecialchars( $text );
		}
	}

	if ( ! function_exists( 'strip_shortcodes' ) ) {
		function strip_shortcodes( $content ) {
			return $content;
		}
	}

	if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
		function get_post_thumbnail_id( $post = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_post_thumbnail_id'] ) ) {
				return $wp_mock_functions['get_post_thumbnail_id']( $post );
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
		function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_get_attachment_image_url'] ) ) {
				return $wp_mock_functions['wp_get_attachment_image_url']( $attachment_id, $size );
			}
			return false;
		}
	}

	// Mock global $wpdb for testing
	global $wpdb;
	$wpdb = new stdClass();
	$wpdb->prefix = 'wp_';
}

// Load the plugin's autoloader.
require_once dirname( __DIR__ ) . '/fp-digital-marketing-suite.php';
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

if ( ! defined( 'ARRAY_A' ) ) {
        define( 'ARRAY_A', 'ARRAY_A' );
}

// Load WordPress test environment if available, otherwise create minimal environment.
if ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	require_once '/tmp/wordpress-tests-lib/includes/bootstrap.php';
} else {
        // Create minimal WordPress environment for testing
        if ( ! defined( 'WPINC' ) ) {
                define( 'WPINC', 'wp-includes' );
        }

        // Simple in-memory options store for option-related functions.
        global $wp_options;
        $wp_options = [];

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
                        global $wp_mock_functions, $wp_options;
                        if ( isset( $wp_mock_functions['get_option'] ) ) {
                                return $wp_mock_functions['get_option']( $option, $default );
                        }
                        return $wp_options[ $option ] ?? $default;
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

	// Mock additional functions for XmlSitemap tests
	if ( ! function_exists( 'home_url' ) ) {
		function home_url( $path = '', $scheme = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['home_url'] ) ) {
				return $wp_mock_functions['home_url']( $path );
			}
			return 'https://example.com' . $path;
		}
	}

	if ( ! function_exists( 'get_posts' ) ) {
		function get_posts( $args = array() ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_posts'] ) ) {
				return $wp_mock_functions['get_posts']( $args );
			}
			return [];
		}
	}

	if ( ! function_exists( 'get_post_types' ) ) {
		function get_post_types( $args = array(), $output = 'names' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_post_types'] ) ) {
				return $wp_mock_functions['get_post_types']( $args, $output );
			}
			return [];
		}
	}

	if ( ! function_exists( 'get_post_type_object' ) ) {
		function get_post_type_object( $post_type ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_post_type_object'] ) ) {
				return $wp_mock_functions['get_post_type_object']( $post_type );
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_count_posts' ) ) {
		function wp_count_posts( $type = 'post', $perm = '' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_count_posts'] ) ) {
				return $wp_mock_functions['wp_count_posts']( $type );
			}
			return (object) [ 'publish' => 0 ];
		}
	}

	if ( ! function_exists( 'get_post_modified_time' ) ) {
		function get_post_modified_time( $format, $gmt = false, $post = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_post_modified_time'] ) ) {
				return $wp_mock_functions['get_post_modified_time']( $format, $gmt, $post );
			}
			return gmdate( $format );
		}
	}

	if ( ! function_exists( 'esc_xml' ) ) {
		function esc_xml( $text ) {
			return htmlspecialchars( $text, ENT_XML1, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'wp_cache_get' ) ) {
		function wp_cache_get( $key, $group = '' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_cache_get'] ) ) {
				return $wp_mock_functions['wp_cache_get']( $key, $group );
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_cache_set' ) ) {
		function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_cache_set'] ) ) {
				return $wp_mock_functions['wp_cache_set']( $key, $data, $group, $expire );
			}
			return true;
		}
	}

	if ( ! function_exists( 'wp_cache_delete' ) ) {
		function wp_cache_delete( $key, $group = '' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_cache_delete'] ) ) {
				return $wp_mock_functions['wp_cache_delete']( $key, $group );
			}
			return true;
		}
	}

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $transient ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_transient'] ) ) {
				return $wp_mock_functions['get_transient']( $transient );
			}
			return false;
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $transient, $value, $expiration = 0 ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['set_transient'] ) ) {
				return $wp_mock_functions['set_transient']( $transient, $value, $expiration );
			}
			return true;
		}
	}

	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $transient ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['delete_transient'] ) ) {
				return $wp_mock_functions['delete_transient']( $transient );
			}
			return true;
		}
	}

        if ( ! function_exists( 'update_option' ) ) {
                function update_option( $option, $value, $autoload = null ) {
                        global $wp_mock_functions, $wp_options;
                        if ( isset( $wp_mock_functions['update_option'] ) ) {
                                return $wp_mock_functions['update_option']( $option, $value );
                        }
                        $wp_options[ $option ] = $value;
                        return true;
                }
        }

        if ( ! function_exists( 'delete_option' ) ) {
                function delete_option( $option ) {
                        global $wp_mock_functions, $wp_options;
                        if ( isset( $wp_mock_functions['delete_option'] ) ) {
                                return $wp_mock_functions['delete_option']( $option );
                        }
                        unset( $wp_options[ $option ] );
                        return true;
                }
        }

        if ( ! function_exists( 'sanitize_textarea_field' ) ) {
                function sanitize_textarea_field( $str ) {
                        return trim( strip_tags( $str ) );
                }
        }

        if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
                function wp_using_ext_object_cache() {
                        return false;
                }
        }

        if ( ! function_exists( 'is_category' ) ) {
                function is_category() {
                        return false;
                }
        }

        if ( ! function_exists( 'is_tag' ) ) {
                function is_tag() {
                        return false;
                }
        }

        if ( ! function_exists( 'is_tax' ) ) {
                function is_tax() {
                        return false;
                }
        }

        if ( ! function_exists( 'is_single' ) ) {
                function is_single() {
                        return false;
                }
        }

        if ( ! function_exists( 'is_page' ) ) {
                function is_page() {
                        return false;
                }
        }

        if ( ! function_exists( 'is_author' ) ) {
                function is_author() {
                        return false;
                }
        }

        if ( ! function_exists( 'is_archive' ) ) {
                function is_archive() {
                        return false;
                }
        }

        if ( ! function_exists( 'wp_next_scheduled' ) ) {
                function wp_next_scheduled( $hook ) {
                        return false;
                }
        }

	// Schema generator and hooks functions
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook_name, $value, ...$args ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['apply_filters'] ) ) {
				return $wp_mock_functions['apply_filters']( $hook_name, $value, ...$args );
			}
			return $value;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $hook_name, ...$args ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['do_action'] ) ) {
				return $wp_mock_functions['do_action']( $hook_name, ...$args );
			}
		}
	}

	if ( ! function_exists( 'is_singular' ) ) {
		function is_singular( $post_types = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['is_singular'] ) ) {
				return $wp_mock_functions['is_singular']( $post_types );
			}
			return false;
		}
	}

	if ( ! function_exists( 'is_home' ) ) {
		function is_home() {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['is_home'] ) ) {
				return $wp_mock_functions['is_home']();
			}
			return false;
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

	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( $user_id ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_userdata'] ) ) {
				return $wp_mock_functions['get_userdata']( $user_id );
			}
			return false;
		}
	}

	if ( ! function_exists( 'get_author_posts_url' ) ) {
		function get_author_posts_url( $author_id ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_author_posts_url'] ) ) {
				return $wp_mock_functions['get_author_posts_url']( $author_id );
			}
			return '';
		}
	}

	if ( ! function_exists( 'get_the_category' ) ) {
		function get_the_category( $post_id = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_the_category'] ) ) {
				return $wp_mock_functions['get_the_category']( $post_id );
			}
			return [];
		}
	}

	if ( ! function_exists( 'get_the_tags' ) ) {
		function get_the_tags( $post_id = null ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['get_the_tags'] ) ) {
				return $wp_mock_functions['get_the_tags']( $post_id );
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
		function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_get_attachment_image_src'] ) ) {
				return $wp_mock_functions['wp_get_attachment_image_src']( $attachment_id, $size );
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
		function wp_get_attachment_metadata( $attachment_id ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['wp_get_attachment_metadata'] ) ) {
				return $wp_mock_functions['wp_get_attachment_metadata']( $attachment_id );
			}
			return [];
		}
	}

	if ( ! function_exists( 'has_blocks' ) ) {
		function has_blocks( $content ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['has_blocks'] ) ) {
				return $wp_mock_functions['has_blocks']( $content );
			}
			return false;
		}
	}

	if ( ! function_exists( 'parse_blocks' ) ) {
		function parse_blocks( $content ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['parse_blocks'] ) ) {
				return $wp_mock_functions['parse_blocks']( $content );
			}
			return [];
		}
	}

	if ( ! function_exists( 'has_shortcode' ) ) {
		function has_shortcode( $content, $tag ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['has_shortcode'] ) ) {
				return $wp_mock_functions['has_shortcode']( $content, $tag );
			}
			return false;
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
			global $wp_mock_functions;
			if ( isset( $wp_mock_functions['update_post_meta'] ) ) {
				return $wp_mock_functions['update_post_meta']( $post_id, $meta_key, $meta_value, $prev_value );
			}
			return true;
		}
	}

	if ( ! function_exists( 'str_contains' ) ) {
		function str_contains( $haystack, $needle ) {
			return strpos( $haystack, $needle ) !== false;
		}
	}

        // Mock global $wpdb for testing with minimal methods used across the test suite.
        class WPDB_Mock {
                /**
                 * Table prefix.
                 *
                 * @var string
                 */
                public $prefix = 'wp_';

                /**
                 * ID of the last inserted row.
                 *
                 * @var int
                 */
                public $insert_id = 0;

                /**
                 * Options table name.
                 *
                 * @var string
                 */
                public $options = 'wp_options';

                /**
                 * Simple query preparer that performs basic sprintf replacement.
                 *
                 * @param string $query SQL query with placeholders.
                 * @param mixed  ...$args Values to substitute into the query.
                 * @return string Prepared query.
                 */
                public function prepare( $query, ...$args ) {
                        if ( empty( $args ) ) {
                                return $query;
                        }
                        $args = array_map(
                                function ( $arg ) {
                                        return is_numeric( $arg ) ? $arg : "'" . addslashes( $arg ) . "'";
                                },
                                $args
                        );
                        // Replace WordPress style placeholders (%s, %d) using vsprintf.
                        return vsprintf( $query, $args );
                }

                public function get_results( $query ) { // phpcs:ignore WordPress.DB
                        return [];
                }

                public function get_var( $query ) { // phpcs:ignore WordPress.DB
                        return null;
                }

                public function get_row( $query ) { // phpcs:ignore WordPress.DB
                        return null;
                }

                public function get_col( $query ) { // phpcs:ignore WordPress.DB
                        global $wp_options;

                        if (
                                isset( $wp_options )
                                && is_array( $wp_options )
                                && strpos( $query, 'FROM ' . $this->options ) !== false
                                && preg_match( "/LIKE\s+'([^']+)'/i", $query, $matches )
                        ) {
                                $like_pattern = $matches[1];
                                $prefix = rtrim( $like_pattern, '%' );
                                $results = [];

                                foreach ( array_keys( $wp_options ) as $option_name ) {
                                        if ( strpos( $option_name, $prefix ) === 0 ) {
                                                $results[] = $option_name;
                                        }
                                }

                                return $results;
                        }

                        return [];
                }

                public function get_charset_collate() { // phpcs:ignore WordPress.DB
                        return '';
                }

                public function query( $query ) { // phpcs:ignore WordPress.DB
                        return 0;
                }

                public function insert( $table, $data, $format = null ) { // phpcs:ignore WordPress.DB
                        return 1;
                }

                public function update( $table, $data, $where ) { // phpcs:ignore WordPress.DB
                        return 1;
                }

                public function delete( $table, $where ) { // phpcs:ignore WordPress.DB
                        return 1;
                }
        }

        global $wpdb;
        $wpdb = new WPDB_Mock();
}

// Load the plugin's autoloader and initialization.
require_once dirname( __DIR__ ) . '/fp-digital-marketing-suite.php';
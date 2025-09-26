<?php

namespace {

	if ( ! defined( 'WP_CLI' ) ) {
		define( 'WP_CLI', true );
	}

	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			public static function log( $message ) {
				// No-op for tests.
			}

			public static function success( $message ) {
				// No-op for tests.
			}

			public static function error( $message ) {
				throw new \RuntimeException( $message );
			}

			public static function add_command( $name, $callable ) {
				// No-op for tests.
			}
		}
	}
}

namespace WP_CLI\Utils {
	function format_items( $format, $items, $fields ) {
		// No-op for tests.
	}
}

namespace {

	use FP\DigitalMarketing\Helpers\AdminOptimizations;
	use PHPUnit\Framework\TestCase;

	require_once __DIR__ . '/../cli-tools.php';

	final class CacheClearingFallbackTest extends TestCase {
		/**
		 * In-memory representation of the WordPress object cache.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		private $object_cache = [];

		/**
		 * Previously registered mock functions.
		 *
		 * @var array<string, callable>
		 */
		private $previous_wp_mock_functions = [];

		/**
		 * Backup of WordPress options for isolation.
		 *
		 * @var array<string, mixed>
		 */
		private $previous_wp_options = [];

		/**
		 * Setup test environment.
		 */
		protected function setUp(): void {
			parent::setUp();

			global $wp_mock_functions, $wp_options, $wpdb;

			$this->previous_wp_options = $wp_options ?? [];
			$wp_options                = [];

			$existing_mocks                   = $wp_mock_functions ?? [];
			$this->previous_wp_mock_functions = $existing_mocks;
			$wp_mock_functions                = $existing_mocks;

			$object_cache =& $this->object_cache;

			$wp_mock_functions['wp_cache_get'] = static function ( $key, $group = '' ) use ( &$object_cache ) {
				return $object_cache[ $group ][ $key ] ?? false;
			};

			$wp_mock_functions['wp_cache_set'] = static function ( $key, $value, $group = '', $expire = 0 ) use ( &$object_cache ) {
				if ( ! isset( $object_cache[ $group ] ) ) {
					$object_cache[ $group ] = [];
				}

				$object_cache[ $group ][ $key ] = $value;
				return true;
			};

			$wp_mock_functions['wp_cache_delete'] = static function ( $key, $group = '' ) use ( &$object_cache ) {
				if ( isset( $object_cache[ $group ][ $key ] ) ) {
					unset( $object_cache[ $group ][ $key ] );
					return true;
				}

				return false;
			};

			$wp_mock_functions['delete_transient'] = static function ( $transient ) {
				global $wp_options;

				$option_key = '_transient_' . $transient;
				if ( isset( $wp_options[ $option_key ] ) ) {
					unset( $wp_options[ $option_key ] );
					return true;
				}

				return false;
			};

			$wpdb = new class() {
				/**
				 * Options table name.
				 *
				 * @var string
				 */
				public $options = 'wp_options';

				/**
				 * Execute a query against the mocked options table.
				 *
				 * @param string $query SQL query string.
				 * @return int Number of rows affected.
				 */
				public function query( $query ) {
					global $wp_options;

					if ( preg_match( "/DELETE FROM {$this->options} WHERE option_name LIKE '([^']+)'/", $query, $matches ) ) {
						$prefix = rtrim( $matches[1], '%' );
						$count  = 0;

						foreach ( array_keys( $wp_options ) as $option_name ) {
							if ( strpos( $option_name, $prefix ) === 0 ) {
								unset( $wp_options[ $option_name ] );
								++$count;
							}
						}

						return $count;
					}

					return 0;
				}

				/**
				 * Prepare a SQL query.
				 *
				 * @param string $query Query template.
				 * @param mixed  $value Value to substitute.
				 * @return string Prepared query string.
				 */
				public function prepare( $query, $value ) {
					return str_replace( '%s', "'" . $value . "'", $query );
				}

				/**
				 * Retrieve the first column from the results of a query.
				 *
				 * @param string $query SQL query string.
				 * @return array<int, string> Matching option names.
				 */
				public function get_col( $query ) {
					global $wp_options;

					if ( preg_match( "/WHERE option_name LIKE '([^']+)'/", $query, $matches ) ) {
						$prefix  = rtrim( $matches[1], '%' );
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
			};
		}

		/**
		 * Clean up after each test.
		 */
		protected function tearDown(): void {
			global $wp_mock_functions, $wp_options, $wpdb;

			$wp_mock_functions  = $this->previous_wp_mock_functions;
			$wp_options         = $this->previous_wp_options;
			$wpdb               = null;
			$this->object_cache = [];

			parent::tearDown();
		}

		public function test_admin_clear_performance_cache_removes_cached_recommendations(): void {
			$admin = new AdminOptimizations();

			wp_cache_set( 'performance_recommendations', [ 'sample' ], 'fp_dms_optimizations' );
			$this->assertSame( [ 'sample' ], wp_cache_get( 'performance_recommendations', 'fp_dms_optimizations' ) );

			$admin->clear_performance_cache();

			$this->assertFalse( wp_cache_get( 'performance_recommendations', 'fp_dms_optimizations' ) );
		}

		public function test_cli_cache_clear_methods_flush_without_group_support(): void {
			global $wp_options;

			$wp_options['fp_analytics_cache_summary']              = 'cached';
			$wp_options['fp_seo_cache_summary']                    = 'cached';
			$wp_options['fp_performance_cache_summary']            = 'cached';
			$wp_options['_transient_fp_dms_fp_performance_sample'] = 'cached';

			wp_cache_set( 'analytics_overview', 'cached', 'fp_analytics' );
			wp_cache_set( 'analytics_top_pages', 'cached', 'fp_analytics' );
			wp_cache_set( 'seo_overview', 'cached', 'fp_seo' );
			wp_cache_set( 'seo_keywords', 'cached', 'fp_seo' );
			wp_cache_set( 'performance_overview', 'cached', 'fp_performance' );
			wp_cache_set( 'performance_metrics', 'cached', 'fp_performance' );
			wp_cache_set( 'performance_recommendations', 'cached', 'fp_dms_optimizations' );

			$commands = new \FP_CLI_Commands();

			$analytics = new \ReflectionMethod( \FP_CLI_Commands::class, 'clear_analytics_cache' );
			$analytics->setAccessible( true );
			$seo = new \ReflectionMethod( \FP_CLI_Commands::class, 'clear_seo_cache' );
			$seo->setAccessible( true );
			$performance = new \ReflectionMethod( \FP_CLI_Commands::class, 'clear_performance_cache' );
			$performance->setAccessible( true );

			$this->assertSame( 1, $analytics->invoke( $commands ) );
			$this->assertSame( 1, $seo->invoke( $commands ) );
			$this->assertSame( 1, $performance->invoke( $commands ) );

			$this->assertArrayNotHasKey( 'fp_analytics_cache_summary', $wp_options );
			$this->assertArrayNotHasKey( 'fp_seo_cache_summary', $wp_options );
			$this->assertArrayNotHasKey( 'fp_performance_cache_summary', $wp_options );
			$this->assertArrayNotHasKey( '_transient_fp_dms_fp_performance_sample', $wp_options );

			$this->assertSame( [], $this->object_cache['fp_analytics'] ?? [] );
			$this->assertSame( [], $this->object_cache['fp_seo'] ?? [] );
			$this->assertSame( [], $this->object_cache['fp_performance'] ?? [] );
			$this->assertFalse( wp_cache_get( 'performance_recommendations', 'fp_dms_optimizations' ) );
		}
	}

}

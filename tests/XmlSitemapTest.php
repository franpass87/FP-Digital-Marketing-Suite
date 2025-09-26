<?php
/**
 * XML Sitemap Test
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\XmlSitemap;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Test class for XML Sitemap functionality
 */
class XmlSitemapTest extends TestCase {

	/**
	 * Test setup
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Include the class files.
		require_once __DIR__ . '/bootstrap.php';

		// Load classes in correct order
		if ( ! class_exists( '\\FP\\DigitalMarketing\\Helpers\\PerformanceCache' ) ) {
			require_once __DIR__ . '/../src/Helpers/PerformanceCache.php';
		}

		if ( ! class_exists( '\\FP\\DigitalMarketing\\Helpers\\SeoMetadata' ) ) {
			require_once __DIR__ . '/../src/Helpers/SeoMetadata.php';
		}

		if ( ! class_exists( '\\FP\\DigitalMarketing\\Helpers\\XmlSitemap' ) ) {
			require_once __DIR__ . '/../src/Helpers/XmlSitemap.php';
		}
	}

	/**
	 * Test sitemap settings defaults
	 *
	 * @return void
	 */
	public function test_get_default_settings(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['get_option'] = function ( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_sitemap_settings' ) {
				return false; // No settings saved yet
			}
			return $default;
		};

		// Use reflection to access private method
		$reflection = new ReflectionClass( XmlSitemap::class );
		$method     = $reflection->getMethod( 'get_settings' );
		$method->setAccessible( true );

		$settings = $method->invoke( null );

		// Test default values
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enabled_post_types', $settings );
		$this->assertArrayHasKey( 'ping_search_engines', $settings );
		$this->assertArrayHasKey( 'exclude_noindex', $settings );

		$this->assertEquals( [ 'post', 'page' ], $settings['enabled_post_types'] );
		$this->assertTrue( $settings['ping_search_engines'] );
		$this->assertTrue( $settings['exclude_noindex'] );
	}

	/**
	 * Test sitemap index generation
	 *
	 * @return void
	 */
	public function test_generate_sitemap_index(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['home_url'] = function ( $path = '' ) {
			return 'https://example.com' . $path;
		};

		$wp_mock_functions['get_option'] = function ( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_sitemap_settings' ) {
				return [
					'enabled_post_types'  => [ 'post', 'page' ],
					'ping_search_engines' => true,
					'exclude_noindex'     => true,
				];
			}
			return $default;
		};

		// Mock post type object
		$wp_mock_functions['get_post_type_object'] = function ( $post_type ) {
			return (object) [ 'public' => true ];
		};

		// Mock post counts
		$wp_mock_functions['wp_count_posts'] = function ( $post_type ) {
			return (object) [ 'publish' => 150 ]; // Simulate 150 published posts
		};

		// Mock recent posts for lastmod
		$wp_mock_functions['get_posts'] = function ( $args ) {
			if ( isset( $args['numberposts'] ) && $args['numberposts'] === 1 ) {
				// For lastmod query
				return [
					(object) [
						'ID'                => 1,
						'post_modified_gmt' => '2024-01-15 10:30:00',
					],
				];
			}
			return [];
		};

		$wp_mock_functions['get_post_modified_time'] = function ( $format, $gmt, $post ) {
			return '2024-01-15T10:30:00+00:00';
		};

		// Mock cache functions
		$wp_mock_functions['wp_cache_get']  = function () {
			return false;
		};
		$wp_mock_functions['get_transient'] = function () {
			return false;
		};

		$sitemap_index = XmlSitemap::generate_sitemap_index();

		// Verify XML structure
		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $sitemap_index );
		$this->assertStringContainsString( '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $sitemap_index );
		$this->assertStringContainsString( '<sitemap>', $sitemap_index );
		$this->assertStringContainsString( '<loc>https://example.com/sitemap-post.xml</loc>', $sitemap_index );
		$this->assertStringContainsString( '<loc>https://example.com/sitemap-page.xml</loc>', $sitemap_index );
		$this->assertStringContainsString( '<lastmod>2024-01-15T10:30:00+00:00</lastmod>', $sitemap_index );
		$this->assertStringContainsString( '</sitemapindex>', $sitemap_index );
	}

	/**
	 * Test individual sitemap generation
	 *
	 * @return void
	 */
	public function test_generate_individual_sitemap(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['get_option'] = function ( $option, $default = false ) {
			if ( $option === 'fp_digital_marketing_sitemap_settings' ) {
				return [
					'enabled_post_types'  => [ 'post', 'page' ],
					'ping_search_engines' => true,
					'exclude_noindex'     => true,
				];
			}
			return $default;
		};

		// Mock post type object
		$wp_mock_functions['get_post_type_object'] = function ( $post_type ) {
			return (object) [ 'public' => true ];
		};

		// Mock posts for sitemap
		$wp_mock_functions['get_posts'] = function ( $args ) {
			return [
				(object) [
					'ID'                => 1,
					'post_title'        => 'Test Post 1',
					'post_modified_gmt' => '2024-01-15 10:30:00',
				],
				(object) [
					'ID'                => 2,
					'post_title'        => 'Test Post 2',
					'post_modified_gmt' => '2024-01-14 15:45:00',
				],
			];
		};

		$wp_mock_functions['get_permalink'] = function ( $post ) {
			return 'https://example.com/post-' . $post->ID . '/';
		};

		$wp_mock_functions['get_post_modified_time'] = function ( $format, $gmt, $post ) {
			$dates = [
				1 => '2024-01-15T10:30:00+00:00',
				2 => '2024-01-14T15:45:00+00:00',
			];
			return $dates[ $post->ID ] ?? '2024-01-01T00:00:00+00:00';
		};

		$wp_mock_functions['get_post_meta'] = function ( $post_id, $key, $single = false ) {
			// No custom SEO robots meta
			return $single ? '' : [];
		};

		// Mock SeoMetadata::get_robots to avoid noindex filtering in tests
		if ( ! class_exists( '\\FP\\DigitalMarketing\\Helpers\\SeoMetadata' ) ) {
			// Create a simple mock class
			eval(
				'
			namespace FP\\DigitalMarketing\\Helpers {
				class SeoMetadata {
					public static function get_robots( $post ) {
						return "index, follow";
					}
				}
			}
			'
			);
		}

		// Mock cache functions
		$wp_mock_functions['wp_cache_get']  = function () {
			return false;
		};
		$wp_mock_functions['get_transient'] = function () {
			return false;
		};

		$sitemap = XmlSitemap::generate_sitemap( 'post', 1 );

		// Verify XML structure
		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $sitemap );
		$this->assertStringContainsString( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $sitemap );
		$this->assertStringContainsString( '<url>', $sitemap );
		$this->assertStringContainsString( '<loc>https://example.com/post-1/</loc>', $sitemap );
		$this->assertStringContainsString( '<loc>https://example.com/post-2/</loc>', $sitemap );
		$this->assertStringContainsString( '<lastmod>2024-01-15T10:30:00+00:00</lastmod>', $sitemap );
		$this->assertStringContainsString( '<changefreq>weekly</changefreq>', $sitemap );
		$this->assertStringContainsString( '<priority>0.6</priority>', $sitemap );
		$this->assertStringContainsString( '</urlset>', $sitemap );
	}

	/**
	 * Test available post types filtering
	 *
	 * @return void
	 */
	public function test_get_available_post_types(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['get_post_types'] = function ( $args, $output ) {
			return [
				'post'        => (object) [
					'name'   => 'post',
					'label'  => 'Posts',
					'public' => true,
				],
				'page'        => (object) [
					'name'   => 'page',
					'label'  => 'Pages',
					'public' => true,
				],
				'attachment'  => (object) [
					'name'   => 'attachment',
					'label'  => 'Media',
					'public' => true,
				],
				'custom_post' => (object) [
					'name'   => 'custom_post',
					'label'  => 'Custom Posts',
					'public' => true,
				],
			];
		};

		$post_types = XmlSitemap::get_available_post_types();

		// Should exclude attachment
		$this->assertIsArray( $post_types );
		$this->assertArrayHasKey( 'post', $post_types );
		$this->assertArrayHasKey( 'page', $post_types );
		$this->assertArrayHasKey( 'custom_post', $post_types );
		$this->assertArrayNotHasKey( 'attachment', $post_types );
	}

	/**
	 * Test change frequency assignment
	 *
	 * @return void
	 */
	public function test_get_change_frequency(): void {
		// Use reflection to access private method
		$reflection = new ReflectionClass( XmlSitemap::class );
		$method     = $reflection->getMethod( 'get_change_frequency' );
		$method->setAccessible( true );

		// Test predefined frequencies
		$this->assertEquals( 'weekly', $method->invoke( null, 'post' ) );
		$this->assertEquals( 'monthly', $method->invoke( null, 'page' ) );
		$this->assertEquals( 'weekly', $method->invoke( null, 'product' ) );
		$this->assertEquals( 'monthly', $method->invoke( null, 'unknown_type' ) );
	}

	/**
	 * Test priority assignment
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		// Use reflection to access private method
		$reflection = new ReflectionClass( XmlSitemap::class );
		$method     = $reflection->getMethod( 'get_priority' );
		$method->setAccessible( true );

		// Mock post objects
		$home_post    = (object) [ 'ID' => 5 ];
		$regular_post = (object) [ 'ID' => 10 ];

		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['get_option'] = function ( $option ) {
			if ( $option === 'page_on_front' ) {
				return 5; // Mock homepage ID
			}
			return false;
		};

		// Test homepage priority
		$this->assertEquals( '1.0', $method->invoke( null, 'page', $home_post ) );

		// Test regular post priorities
		$this->assertEquals( '0.8', $method->invoke( null, 'page', $regular_post ) );
		$this->assertEquals( '0.6', $method->invoke( null, 'post', $regular_post ) );
		$this->assertEquals( '0.7', $method->invoke( null, 'product', $regular_post ) );
		$this->assertEquals( '0.5', $method->invoke( null, 'unknown_type', $regular_post ) );
	}

	/**
	 * Test settings update
	 *
	 * @return void
	 */
	public function test_update_settings(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$saved_options = [];

		$wp_mock_functions['get_option'] = function ( $option, $default = false ) use ( &$saved_options ) {
			return $saved_options[ $option ] ?? $default;
		};

		$wp_mock_functions['update_option'] = function ( $option, $value ) use ( &$saved_options ) {
			$saved_options[ $option ] = $value;
			return true;
		};

		// Mock cache invalidation
		$wp_mock_functions['wp_cache_delete'] = function () {
			return true;
		};

		$new_settings = [
			'enabled_post_types'  => [ 'post' ],
			'ping_search_engines' => false,
			'exclude_noindex'     => false,
		];

		$result = XmlSitemap::update_settings( $new_settings );

		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'fp_digital_marketing_sitemap_settings', $saved_options );

		$saved_settings = $saved_options['fp_digital_marketing_sitemap_settings'];
		$this->assertEquals( [ 'post' ], $saved_settings['enabled_post_types'] );
		$this->assertFalse( $saved_settings['ping_search_engines'] );
		$this->assertFalse( $saved_settings['exclude_noindex'] );
	}

	/**
	 * Test empty sitemap generation
	 *
	 * @return void
	 */
	public function test_build_empty_sitemap(): void {
		// Use reflection to access private method
		$reflection = new ReflectionClass( XmlSitemap::class );
		$method     = $reflection->getMethod( 'build_empty_sitemap' );
		$method->setAccessible( true );

		$empty_sitemap = $method->invoke( null );

		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $empty_sitemap );
		$this->assertStringContainsString( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $empty_sitemap );
		$this->assertStringContainsString( '</urlset>', $empty_sitemap );
		$this->assertStringNotContainsString( '<url>', $empty_sitemap );
	}

	/**
	 * Test robots.txt integration
	 *
	 * @return void
	 */
	public function test_add_sitemap_to_robots(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['home_url'] = function ( $path = '' ) {
			return 'https://example.com' . $path;
		};

		$original_output = "User-agent: *\nDisallow: /wp-admin/\n";
		$modified_output = XmlSitemap::add_sitemap_to_robots( $original_output, true );

		$this->assertStringContainsString( $original_output, $modified_output );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap.xml', $modified_output );

		// Test with non-public site
		$non_public_output = XmlSitemap::add_sitemap_to_robots( $original_output, false );
		$this->assertEquals( $original_output, $non_public_output );
	}

	/**
	 * Test URL count calculation
	 *
	 * @return void
	 */
	public function test_get_post_type_url_count(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['wp_count_posts'] = function ( $post_type ) {
			$counts = [
				'post' => (object) [ 'publish' => 250 ],
				'page' => (object) [ 'publish' => 15 ],
			];
			return $counts[ $post_type ] ?? (object) [ 'publish' => 0 ];
		};

		// Use reflection to access private method
		$reflection = new ReflectionClass( XmlSitemap::class );
		$method     = $reflection->getMethod( 'get_post_type_url_count' );
		$method->setAccessible( true );

		$this->assertEquals( 250, $method->invoke( null, 'post' ) );
		$this->assertEquals( 15, $method->invoke( null, 'page' ) );
		$this->assertEquals( 0, $method->invoke( null, 'nonexistent' ) );
	}

	/**
	 * Test post type eligibility check
	 *
	 * @return void
	 */
	public function test_is_post_type_eligible(): void {
		// Mock WordPress functions
		global $wp_mock_functions;
		$wp_mock_functions = [];

		$wp_mock_functions['get_post_type_object'] = function ( $post_type ) {
			$post_types = [
				'post'         => (object) [ 'public' => true ],
				'page'         => (object) [ 'public' => true ],
				'private_post' => (object) [ 'public' => false ],
			];
			return $post_types[ $post_type ] ?? false;
		};

		// Use reflection to access private method
		$reflection = new ReflectionClass( XmlSitemap::class );
		$method     = $reflection->getMethod( 'is_post_type_eligible' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, 'post' ) );
		$this->assertTrue( $method->invoke( null, 'page' ) );
		$this->assertFalse( $method->invoke( null, 'private_post' ) );
		$this->assertFalse( $method->invoke( null, 'nonexistent' ) );
	}
}

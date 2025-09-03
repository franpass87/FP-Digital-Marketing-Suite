<?php
/**
 * Dashboard Widgets Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\DashboardWidgets;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Test cases for Dashboard Widgets functionality
 */
class DashboardWidgetsTest extends TestCase {

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions if needed
		if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
			function wp_add_dashboard_widget( $widget_id, $widget_name, $callback ) {
				// Mock implementation
				return true;
			}
		}

		if ( ! function_exists( 'admin_url' ) ) {
			function admin_url( $path ) {
				return 'http://example.com/wp-admin/' . $path;
			}
		}

		if ( ! function_exists( 'home_url' ) ) {
			function home_url( $path ) {
				return 'http://example.com' . $path;
			}
		}

		if ( ! function_exists( 'esc_url' ) ) {
			function esc_url( $url ) {
				return $url;
			}
		}

		if ( ! function_exists( 'esc_html' ) ) {
			function esc_html( $text ) {
				return htmlspecialchars( $text );
			}
		}

		if ( ! function_exists( 'esc_html_e' ) ) {
			function esc_html_e( $text, $domain = 'default' ) {
				echo htmlspecialchars( $text );
			}
		}

		if ( ! function_exists( 'esc_html__' ) ) {
			function esc_html__( $text, $domain = 'default' ) {
				return htmlspecialchars( $text );
			}
		}

		if ( ! function_exists( 'number_format' ) && ! function_exists( 'number_format' ) ) {
			// number_format is a PHP function, so this should be available
		}
	}

	/**
	 * Test dashboard widgets initialization
	 *
	 * @return void
	 */
	public function test_dashboard_widgets_init(): void {
		// Test that init method exists and is callable
		$this->assertTrue( method_exists( DashboardWidgets::class, 'init' ) );
		$this->assertTrue( is_callable( [ DashboardWidgets::class, 'init' ] ) );
	}

	/**
	 * Test cache warmup functionality
	 *
	 * @return void
	 */
	public function test_cache_warmup_functionality(): void {
		// Test that warmup_cache method exists
		$this->assertTrue( method_exists( PerformanceCache::class, 'warmup_cache' ) );
		
		// Test warmup with empty keys
		$result = PerformanceCache::warmup_cache( [] );
		
		// Should return proper structure
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'warmed_keys', $result );
		$this->assertArrayHasKey( 'failed_keys', $result );
		$this->assertArrayHasKey( 'execution_time', $result );
	}

	/**
	 * Test cache statistics
	 *
	 * @return void
	 */
	public function test_cache_statistics(): void {
		// Test that get_cache_statistics method exists
		$this->assertTrue( method_exists( PerformanceCache::class, 'get_cache_statistics' ) );
		
		$stats = PerformanceCache::get_cache_statistics();
		
		// Should return proper structure
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'last_warmup', $stats );
		$this->assertArrayHasKey( 'total_warmups', $stats );
		$this->assertArrayHasKey( 'total_warmed_keys', $stats );
		$this->assertArrayHasKey( 'total_failed_keys', $stats );
	}

	/**
	 * Test performance data generation
	 *
	 * @return void
	 */
	public function test_performance_data_generation(): void {
		// Use reflection to access private method
		$reflection = new ReflectionClass( DashboardWidgets::class );
		$method = $reflection->getMethod( 'get_performance_data' );
		$method->setAccessible( true );
		
		$data = $method->invoke( null );
		
		// Should return proper structure
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'sessions', $data );
		$this->assertArrayHasKey( 'users', $data );
		$this->assertArrayHasKey( 'pageviews', $data );
		$this->assertArrayHasKey( 'bounce_rate', $data );
		$this->assertArrayHasKey( 'last_updated', $data );
		
		// Validate data types
		$this->assertIsInt( $data['sessions'] );
		$this->assertIsInt( $data['users'] );
		$this->assertIsInt( $data['pageviews'] );
		$this->assertIsFloat( $data['bounce_rate'] );
		$this->assertIsInt( $data['last_updated'] );
		
		// Validate reasonable ranges
		$this->assertGreaterThan( 0, $data['sessions'] );
		$this->assertGreaterThan( 0, $data['users'] );
		$this->assertGreaterThan( 0, $data['pageviews'] );
		$this->assertGreaterThan( 0, $data['bounce_rate'] );
		$this->assertLessThan( 100, $data['bounce_rate'] );
	}

	/**
	 * Test widget rendering methods exist
	 *
	 * @return void
	 */
	public function test_widget_rendering_methods(): void {
		$methods = [
			'render_performance_widget',
			'render_quick_actions_widget',
			'render_cache_status_widget'
		];

		foreach ( $methods as $method ) {
			$this->assertTrue( 
				method_exists( DashboardWidgets::class, $method ),
				"Method {$method} should exist"
			);
			$this->assertTrue( 
				is_callable( [ DashboardWidgets::class, $method ] ),
				"Method {$method} should be callable"
			);
		}
	}

	/**
	 * Test cache warmup with custom keys
	 *
	 * @return void
	 */
	public function test_cache_warmup_with_custom_keys(): void {
		$warmup_keys = [
			[
				'key' => 'test_key',
				'group' => 'test_group',
				'callback' => function() {
					return [ 'test' => 'data' ];
				},
				'ttl' => 300
			]
		];

		$result = PerformanceCache::warmup_cache( $warmup_keys );

		$this->assertIsArray( $result );
		$this->assertEquals( 'success', $result['status'] );
		$this->assertIsInt( $result['warmed_keys'] );
		$this->assertIsInt( $result['failed_keys'] );
		$this->assertIsFloat( $result['execution_time'] );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertIsArray( $result['details'] );
	}

	/**
	 * Test cache warmup with invalid callback
	 *
	 * @return void
	 */
	public function test_cache_warmup_with_invalid_callback(): void {
		$warmup_keys = [
			[
				'key' => 'invalid_key',
				'group' => 'test_group',
				'callback' => null, // Invalid callback
				'ttl' => 300
			]
		];

		$result = PerformanceCache::warmup_cache( $warmup_keys );

		$this->assertIsArray( $result );
		$this->assertEquals( 'success', $result['status'] );
		$this->assertEquals( 0, $result['warmed_keys'] );
		$this->assertEquals( 1, $result['failed_keys'] );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertCount( 1, $result['details'] );
		$this->assertEquals( 'failed', $result['details'][0]['status'] );
		$this->assertEquals( 'Invalid key or callback', $result['details'][0]['reason'] );
	}
}
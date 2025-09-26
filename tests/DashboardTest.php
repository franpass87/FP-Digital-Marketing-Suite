<?php
/**
 * Dashboard Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Admin\Dashboard;

if ( ! function_exists( 'add_action' ) ) {
        function add_action( ...$args ): void { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
        }
}

if ( ! function_exists( 'current_user_can' ) ) {
        function current_user_can( $capability ): bool { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                return true;
        }
}

if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
        function wp_add_dashboard_widget( ...$args ): void { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
        }
}

if ( ! function_exists( 'esc_html' ) ) {
        function esc_html( $text ) { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                return $text;
        }
}

if ( ! function_exists( 'esc_html_e' ) ) {
        function esc_html_e( $text, $domain = null ): void { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                echo esc_html( $text );
        }
}

if ( ! function_exists( 'esc_html__' ) ) {
        function esc_html__( $text, $domain = null ) { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                return esc_html( $text );
        }
}

if ( ! function_exists( '__' ) ) {
        function __( $text, $domain = null ) { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                return $text;
        }
}

if ( ! function_exists( 'esc_attr' ) ) {
        function esc_attr( $text ) { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                return $text;
        }
}

if ( ! function_exists( 'esc_url' ) ) {
        function esc_url( $url ) { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                return $url;
        }
}

if ( ! function_exists( 'admin_url' ) ) {
        function admin_url( $path = '' ) { // phpcs:ignore Squiz.Functions.GlobalFunction.Found
                return $path;
        }
}

if ( ! class_exists( 'DashboardTest_FailingAdminOptimizations', false ) ) {
        class DashboardTest_FailingAdminOptimizations {
                public function __construct() {
                        throw new \RuntimeException( 'Simulated AdminOptimizations failure' );
                }

                public function init(): void {
                }

                public function get_performance_widget_data(): array {
                        return [];
                }
        }
}

/**
 * Test class for Dashboard
 */
class DashboardTest extends TestCase {

	/**
	 * Test dashboard initialization
	 */
	public function test_dashboard_init() {
		$dashboard = new Dashboard();
		
		// Test that the dashboard can be instantiated
		$this->assertInstanceOf( Dashboard::class, $dashboard );
	}

	/**
	 * Test dashboard menu registration
	 */
	public function test_add_admin_menu() {
		$dashboard = new Dashboard();
		
		// Mock admin_menu action
		$this->assertIsCallable( [ $dashboard, 'add_admin_menu' ] );
	}

	/**
	 * Test AJAX handlers exist
	 */
	public function test_ajax_handlers_exist() {
		$dashboard = new Dashboard();
		
		// Test AJAX handler methods exist
		$this->assertIsCallable( [ $dashboard, 'handle_ajax_dashboard_data' ] );
		$this->assertIsCallable( [ $dashboard, 'handle_ajax_chart_data' ] );
	}

	/**
	 * Test render dashboard page method
	 */
	public function test_render_dashboard_page() {
		$dashboard = new Dashboard();
		
		// Test that the render method exists
		$this->assertIsCallable( [ $dashboard, 'render_dashboard_page' ] );
	}

	/**
	 * Test asset enqueue method
	 */
        public function test_enqueue_dashboard_assets() {
                $dashboard = new Dashboard();

                // Test that the enqueue method exists
                $this->assertIsCallable( [ $dashboard, 'enqueue_dashboard_assets' ] );
        }

        /**
         * Ensure the performance widget handles missing optimizations gracefully.
         */
        public function test_render_performance_widget_handles_missing_optimizations() {
                $dashboard = new Dashboard();

                if ( ! class_exists( 'FP\\DigitalMarketing\\Helpers\\AdminOptimizations', false ) ) {
                        class_alias(
                                DashboardTest_FailingAdminOptimizations::class,
                                'FP\\DigitalMarketing\\Helpers\\AdminOptimizations'
                        );

                        // Simulate initialization failure - should be caught internally.
                        $dashboard->init();
                }

                $reflection = new \ReflectionProperty( Dashboard::class, 'optimizations' );
                $reflection->setAccessible( true );
                $reflection->setValue( $dashboard, null );

                ob_start();
                $dashboard->render_performance_dashboard_widget();
                $output = ob_get_clean();

                $this->assertStringContainsString( 'Performance data temporarily unavailable', $output );
        }
}


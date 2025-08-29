<?php
/**
 * Dashboard Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Admin\Dashboard;

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
}
<?php
/**
 * Test for AnomalyRadar functionality
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Admin\AnomalyRadar;

/**
 * Test case for AnomalyRadar
 */
class AnomalyRadarTest extends TestCase {

	/**
	 * Test AnomalyRadar can be instantiated
	 *
	 * @return void
	 */
	public function test_anomaly_radar_can_be_instantiated(): void {
		$radar = new AnomalyRadar();
		$this->assertInstanceOf( AnomalyRadar::class, $radar );
	}

	/**
	 * Test page slug constant
	 *
	 * @return void
	 */
	public function test_page_slug_constant_exists(): void {
		$this->assertEquals( 'fp-anomaly-radar', AnomalyRadar::PAGE_SLUG );
	}

	/**
	 * Test that init method can be called without errors
	 *
	 * @return void
	 */
	public function test_init_method_callable(): void {
		$radar = new AnomalyRadar();
		
		// Mock WordPress functions
		if ( ! function_exists( 'add_action' ) ) {
			function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
				// Mock implementation for testing
				return true;
			}
		}

		$this->assertTrue( method_exists( $radar, 'init' ) );
		
		// This should not throw any errors
		$radar->init();
		$this->assertTrue( true ); // If we get here, no exceptions were thrown
	}

	/**
	 * Test that required methods exist
	 *
	 * @return void
	 */
	public function test_required_methods_exist(): void {
		$radar = new AnomalyRadar();
		
		$required_methods = [
			'init',
			'add_client_submenu',
			'display_radar_page',
			'enqueue_scripts'
		];

		foreach ( $required_methods as $method ) {
			$this->assertTrue( 
				method_exists( $radar, $method ), 
				"Method '{$method}' should exist in AnomalyRadar class"
			);
		}
	}
}
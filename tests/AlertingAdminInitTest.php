<?php
/**
 * Test for AlertingAdmin init method
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Admin\AlertingAdmin;
use FP\DigitalMarketing\Admin\AnomalyDetectionAdmin;

/**
 * Test that AlertingAdmin and AnomalyDetectionAdmin have init methods
 */
class AlertingAdminInitTest extends TestCase {

	/**
	 * Test that AlertingAdmin has an init method
	 */
	public function test_alerting_admin_has_init_method(): void {
		$admin = new AlertingAdmin();

		// This should not throw an exception
		$this->assertTrue( method_exists( $admin, 'init' ) );

		// The init method should be callable
		$this->assertTrue( is_callable( [ $admin, 'init' ] ) );
	}

	/**
	 * Test that AnomalyDetectionAdmin has an init method
	 */
	public function test_anomaly_detection_admin_has_init_method(): void {
		$admin = new AnomalyDetectionAdmin();

		// This should not throw an exception
		$this->assertTrue( method_exists( $admin, 'init' ) );

		// The init method should be callable
		$this->assertTrue( is_callable( [ $admin, 'init' ] ) );
	}

	/**
	 * Test that both admin classes can be initialized without errors
	 */
	public function test_admin_classes_can_be_initialized(): void {
		$alerting_admin = new AlertingAdmin();
		$anomaly_admin  = new AnomalyDetectionAdmin();

		// These should not throw exceptions
		// Note: We can't actually call init() in tests because it registers WordPress hooks
		// and we're not in a WordPress environment
		$this->assertInstanceOf( AlertingAdmin::class, $alerting_admin );
		$this->assertInstanceOf( AnomalyDetectionAdmin::class, $anomaly_admin );
	}
}

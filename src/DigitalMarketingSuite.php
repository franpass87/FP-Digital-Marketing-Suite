<?php
/**
 * Main class for FP Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing;

/**
 * Main application class
 */
class DigitalMarketingSuite {

	/**
	 * Application version
	 *
	 * @var string
	 */
	private string $version = '1.0.0';

	/**
	 * Get application version
	 *
	 * @return string The application version.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Initialize the application
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialization logic will go here.
		do_action( 'fp_digital_marketing_suite_init' );
	}
}

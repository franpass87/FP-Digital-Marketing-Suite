<?php
/**
 * Main class for FP Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing;

use FP\DigitalMarketing\PostTypes\ClientePostType;
use FP\DigitalMarketing\Admin\ClienteMeta;
use FP\DigitalMarketing\Admin\Settings;

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
	 * Cliente Post Type instance
	 *
	 * @var ClientePostType
	 */
	private ClientePostType $cliente_post_type;

	/**
	 * Cliente Meta instance
	 *
	 * @var ClienteMeta
	 */
	private ClienteMeta $cliente_meta;

	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cliente_post_type = new ClientePostType();
		$this->cliente_meta = new ClienteMeta();
		$this->settings = new Settings();
	}

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
		// Initialize components.
		$this->cliente_post_type->init();
		$this->cliente_meta->init();
		$this->settings->init();

		// Hook for extensibility.
		do_action( 'fp_digital_marketing_suite_init' );
	}
}

<?php
/**
 * Plugin Name: FP Digital Marketing Suite
 * Plugin URI: https://github.com/franpass87/FP-Digital-Marketing-Suite
 * Description: A comprehensive digital marketing toolkit with advanced client metadata management.
 * Version: 1.3.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * License: MIT
 * Text Domain: fp-digital-marketing
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package FP_Digital_Marketing_Suite
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'FP_DIGITAL_MARKETING_VERSION', '1.3.0' );
define( 'FP_DIGITAL_MARKETING_PLUGIN_FILE', __FILE__ );
define( 'FP_DIGITAL_MARKETING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FP_DIGITAL_MARKETING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader when available.
$fp_dms_autoloader = FP_DIGITAL_MARKETING_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $fp_dms_autoloader ) ) {
        require $fp_dms_autoloader;
} else {
        add_action( 'admin_notices', static function () {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                echo '<div class="notice notice-error"><p>';
                echo esc_html__( 'FP Digital Marketing Suite could not locate the Composer autoloader. Run "composer install" to finish the setup.', 'fp-digital-marketing' );
                echo '</p></div>';
        } );

        return;
}

/**
 * Retrieve the plugin singleton instance.
 *
 * @return \FP\DigitalMarketing\DigitalMarketingSuite
 */
function fp_dms(): \FP\DigitalMarketing\DigitalMarketingSuite {
        return \FP\DigitalMarketing\DigitalMarketingSuite::instance();
}

// Register lifecycle hooks using the main class methods.
fp_dms()->register_hooks();
register_activation_hook( __FILE__, array( '\FP\DigitalMarketing\DigitalMarketingSuite', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\FP\DigitalMarketing\DigitalMarketingSuite', 'deactivate' ) );

/**
 * Global helper function to get data sources
 *
 * This function provides easy access to the data sources registry.
 * It can be used throughout the application to retrieve available
 * data sources for integration with various marketing platforms.
 *
 * @param string $type Optional. Filter by data source type.
 * @return array Array of registered data sources.
 */
function fp_dms_get_data_sources( string $type = '' ): array {
        return \FP\DigitalMarketing\Helpers\DataSources::get_data_sources( $type );
}

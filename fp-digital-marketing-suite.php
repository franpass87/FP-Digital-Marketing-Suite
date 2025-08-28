<?php
/**
 * Plugin Name: FP Digital Marketing Suite
 * Plugin URI: https://github.com/franpass87/FP-Digital-Marketing-Suite
 * Description: A comprehensive digital marketing toolkit with advanced client metadata management.
 * Version: 1.0.0
 * Author: Francesco Passarelli
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
define( 'FP_DIGITAL_MARKETING_VERSION', '1.0.0' );
define( 'FP_DIGITAL_MARKETING_PLUGIN_FILE', __FILE__ );
define( 'FP_DIGITAL_MARKETING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FP_DIGITAL_MARKETING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader.
spl_autoload_register( function ( string $class ) {
	$prefix = 'FP\\DigitalMarketing\\';
	$base_dir = FP_DIGITAL_MARKETING_PLUGIN_DIR . 'src/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );

	// Handle the main class (no additional namespace beyond FP\DigitalMarketing).
	if ( strpos( $relative_class, '\\' ) === false ) {
		$file = $base_dir . $relative_class . '.php';
	} else {
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
	}

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Initialize the plugin.
add_action( 'plugins_loaded', function () {
	$fp_digital_marketing = new \FP\DigitalMarketing\DigitalMarketingSuite();
	$fp_digital_marketing->init();
} );

// Activation hook.
register_activation_hook( __FILE__, function () {
	// Flush rewrite rules to ensure custom post types work correctly.
	flush_rewrite_rules();
} );

// Deactivation hook.
register_deactivation_hook( __FILE__, function () {
	// Flush rewrite rules on deactivation.
	flush_rewrite_rules();
} );
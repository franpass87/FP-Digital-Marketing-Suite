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

// Autoloader with error handling.
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

	if ( file_exists( $file ) && is_readable( $file ) ) {
		try {
			require $file;
		} catch ( \ParseError $e ) {
			// Log the error but don't cause WSOD
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing: Parse error in ' . $file . ': ' . $e->getMessage() );
			}
		} catch ( \Error $e ) {
			// Log fatal errors but continue
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing: Fatal error loading ' . $file . ': ' . $e->getMessage() );
			}
		}
	}
} );

// Initialize the plugin with error handling.
add_action( 'plugins_loaded', function () {
	// Check WordPress version compatibility
	if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'FP Digital Marketing Suite requires WordPress 5.0 or higher.', 'fp-digital-marketing' );
			echo '</p></div>';
		} );
		return;
	}

	// Check PHP version compatibility
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'FP Digital Marketing Suite requires PHP 7.4 or higher.', 'fp-digital-marketing' );
			echo '</p></div>';
		} );
		return;
	}

	try {
		$fp_digital_marketing = new \FP\DigitalMarketing\DigitalMarketingSuite();
		$fp_digital_marketing->init();
		
		// Initialize setup wizard for admin users
		if ( is_admin() && class_exists( '\FP\DigitalMarketing\Setup\SetupWizard' ) ) {
			new \FP\DigitalMarketing\Setup\SetupWizard();
		}
	} catch ( \Error $e ) {
		// Log the error but show user-friendly message
		if ( function_exists( 'error_log' ) ) {
			error_log( 'FP Digital Marketing: Initialization error - ' . $e->getMessage() );
		}
		
		add_action( 'admin_notices', function() use ( $e ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'FP Digital Marketing Suite failed to initialize. Check error logs for details.', 'fp-digital-marketing' );
				echo '</p></div>';
			}
		} );
	} catch ( \Exception $e ) {
		// Log the exception
		if ( function_exists( 'error_log' ) ) {
			error_log( 'FP Digital Marketing: Initialization exception - ' . $e->getMessage() );
		}
		
		add_action( 'admin_notices', function() use ( $e ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'FP Digital Marketing Suite encountered an error during initialization.', 'fp-digital-marketing' );
				echo '</p></div>';
			}
		} );
	}
} );

// Activation hook with error handling.
register_activation_hook( __FILE__, function () {
	try {
		// Check if required functions exist
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		// Create custom database tables with error handling.
		if ( class_exists( '\FP\DigitalMarketing\Database\MetricsCacheTable' ) ) {
			\FP\DigitalMarketing\Database\MetricsCacheTable::create_table();
		}
		
		if ( class_exists( '\FP\DigitalMarketing\Database\AnomalyRulesTable' ) ) {
			\FP\DigitalMarketing\Database\AnomalyRulesTable::create_table();
		}
		
		if ( class_exists( '\FP\DigitalMarketing\Database\DetectedAnomaliesTable' ) ) {
			\FP\DigitalMarketing\Database\DetectedAnomaliesTable::create_table();
		}
		
		// Register custom capabilities with error handling.
		if ( class_exists( '\FP\DigitalMarketing\Helpers\Capabilities' ) ) {
			\FP\DigitalMarketing\Helpers\Capabilities::register_capabilities();
		}
		
		// Flush rewrite rules to ensure custom post types work correctly.
		flush_rewrite_rules();
		
		// Set activation redirect flag for setup wizard
		set_transient( 'fp_dms_activation_redirect', true, 30 );
		
		// Initialize setup wizard
		if ( class_exists( '\FP\DigitalMarketing\Setup\SetupWizard' ) ) {
			new \FP\DigitalMarketing\Setup\SetupWizard();
		}
		
	} catch ( \Error $e ) {
		// Log activation errors
		if ( function_exists( 'error_log' ) ) {
			error_log( 'FP Digital Marketing: Activation error - ' . $e->getMessage() );
		}
		// Don't prevent activation, just log the error
	} catch ( \Exception $e ) {
		if ( function_exists( 'error_log' ) ) {
			error_log( 'FP Digital Marketing: Activation exception - ' . $e->getMessage() );
		}
	}
} );

// Deactivation hook with error handling.
register_deactivation_hook( __FILE__, function () {
	try {
		// Remove custom capabilities.
		if ( class_exists( '\FP\DigitalMarketing\Helpers\Capabilities' ) ) {
			\FP\DigitalMarketing\Helpers\Capabilities::remove_capabilities();
		}
		
		// Flush rewrite rules on deactivation.
		flush_rewrite_rules();
		
	} catch ( \Error $e ) {
		// Log deactivation errors but don't prevent deactivation
		if ( function_exists( 'error_log' ) ) {
			error_log( 'FP Digital Marketing: Deactivation error - ' . $e->getMessage() );
		}
	} catch ( \Exception $e ) {
		if ( function_exists( 'error_log' ) ) {
			error_log( 'FP Digital Marketing: Deactivation exception - ' . $e->getMessage() );
		}
	}
} );

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
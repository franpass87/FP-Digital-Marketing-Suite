<?php
/**
 * PHPStan bootstrap file for WordPress environment
 * 
 * This file helps PHPStan understand WordPress-specific functions and constants
 */

// Load Composer autoloader so that WordPress stub functions/classes are available.
require_once __DIR__ . '/vendor/autoload.php';

$wordpress_stubs = __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
if (file_exists($wordpress_stubs)) {
    require_once $wordpress_stubs;
}

$woocommerce_stubs = __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php';
if (file_exists($woocommerce_stubs)) {
    require_once $woocommerce_stubs;
}

if (!defined('FP_DIGITAL_MARKETING_VERSION')) {
    define('FP_DIGITAL_MARKETING_VERSION', '1.2.0');
}

if (!defined('FP_DIGITAL_MARKETING_PLUGIN_URL')) {
    define('FP_DIGITAL_MARKETING_PLUGIN_URL', 'https://example.com/wp-content/plugins/fp-digital-marketing-suite/');
}

if (!defined('FP_DIGITAL_MARKETING_PLUGIN_DIR')) {
    define('FP_DIGITAL_MARKETING_PLUGIN_DIR', __DIR__ . '/');
}

if (!defined('FP_DIGITAL_MARKETING_PLUGIN_FILE')) {
    define('FP_DIGITAL_MARKETING_PLUGIN_FILE', __FILE__);
}

// Define common WordPress constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// Define WordPress version
if (!defined('WP_VERSION')) {
    define('WP_VERSION', '6.0');
}

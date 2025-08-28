<?php
/**
 * PHPStan bootstrap file for WordPress environment
 * 
 * This file helps PHPStan understand WordPress-specific functions and constants
 */

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
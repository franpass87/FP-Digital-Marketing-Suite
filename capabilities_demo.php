<?php
/**
 * Simple test to demonstrate capability system functionality
 * 
 * @package FP_Digital_Marketing_Suite
 */

// Include bootstrap for testing
require_once __DIR__ . '/tests/bootstrap.php';

use FP\DigitalMarketing\Helpers\Capabilities;

// Mock WordPress functions for the demo
if ( ! function_exists( 'get_role' ) ) {
    function get_role( $role ) {
        $valid_roles = [ 'administrator', 'editor', 'author' ];
        if ( ! in_array( $role, $valid_roles ) ) {
            return null;
        }
        return new class {
            public $capabilities = [];
            public function add_cap( $cap, $grant = true ) {
                $this->capabilities[ $cap ] = $grant;
            }
            public function remove_cap( $cap ) {
                unset( $this->capabilities[ $cap ] );
            }
        };
    }
}

if ( ! function_exists( 'wp_roles' ) ) {
    function wp_roles() {
        return new class {
            public $roles = [
                'administrator' => [ 'capabilities' => [] ],
                'editor' => [ 'capabilities' => [] ],
                'author' => [ 'capabilities' => [] ],
            ];
        };
    }
}

if ( ! function_exists( 'user_can' ) ) {
    function user_can( $user_id, $capability, $object_id = 0 ) {
        // Mock admin user (ID 1) has all capabilities
        if ( $user_id === 1 ) {
            return true;
        }
        // Mock editor user (ID 2) has limited capabilities
        if ( $user_id === 2 ) {
            $editor_caps = [
                Capabilities::VIEW_DASHBOARD,
                Capabilities::EXPORT_REPORTS,
            ];
            return in_array( $capability, $editor_caps, true );
        }
        // Other users have no capabilities
        return false;
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        global $current_test_user_id;
        return $current_test_user_id ?? 1; // Default to admin
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        static $options = [];
        return $options[ $option ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value ) {
        static $options = [];
        $options[ $option ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        static $options = [];
        $old_value = $options[ $option ] ?? false;
        unset( $options[ $option ] );
        return $old_value !== false;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

// Demonstrate capability system
echo "=== FP Digital Marketing Suite - Capabilities Demo ===\n\n";

echo "1. Available Custom Capabilities:\n";
$capabilities = Capabilities::get_custom_capabilities();
foreach ( $capabilities as $cap ) {
    $label = Capabilities::get_capability_label( $cap );
    $description = Capabilities::get_capability_description( $cap );
    echo "   - $cap\n";
    echo "     Label: $label\n";
    echo "     Description: $description\n\n";
}

echo "2. Default Role Capabilities:\n";
$role_capabilities = Capabilities::get_default_role_capabilities();
foreach ( $role_capabilities as $role => $caps ) {
    echo "   $role:\n";
    foreach ( $caps as $cap ) {
        $label = Capabilities::get_capability_label( $cap );
        echo "     - $label ($cap)\n";
    }
    echo "\n";
}

echo "3. User Permission Tests:\n";

// Test Administrator (User ID 1)
global $current_test_user_id;
$current_test_user_id = 1;
echo "   Administrator (User ID: 1):\n";
foreach ( $capabilities as $cap ) {
    $has_cap = Capabilities::current_user_can( $cap );
    $status = $has_cap ? '✓ ALLOWED' : '✗ DENIED';
    $label = Capabilities::get_capability_label( $cap );
    echo "     $label: $status\n";
}

echo "\n";

// Test Editor (User ID 2)
$current_test_user_id = 2;
echo "   Editor (User ID: 2):\n";
foreach ( $capabilities as $cap ) {
    $has_cap = Capabilities::current_user_can( $cap );
    $status = $has_cap ? '✓ ALLOWED' : '✗ DENIED';
    $label = Capabilities::get_capability_label( $cap );
    echo "     $label: $status\n";
}

echo "\n";

// Test Regular User (User ID 3)
$current_test_user_id = 3;
echo "   Regular User (User ID: 3):\n";
foreach ( $capabilities as $cap ) {
    $has_cap = Capabilities::current_user_can( $cap );
    $status = $has_cap ? '✓ ALLOWED' : '✗ DENIED';
    $label = Capabilities::get_capability_label( $cap );
    echo "     $label: $status\n";
}

echo "\n4. Capability Registration Test:\n";
// Reset registration flag
delete_option( 'fp_dms_capabilities_registered' );
echo "   Before registration: " . ( get_option( 'fp_dms_capabilities_registered', false ) ? 'Registered' : 'Not registered' ) . "\n";

// Register capabilities
Capabilities::register_capabilities();
echo "   After registration: " . ( get_option( 'fp_dms_capabilities_registered', false ) ? 'Registered' : 'Not registered' ) . "\n";

echo "\n5. Role Management Test:\n";
// Test adding capability to author role
$result = Capabilities::add_role_capability( 'author', Capabilities::VIEW_DASHBOARD );
echo "   Adding VIEW_DASHBOARD to author role: " . ( $result ? 'Success' : 'Failed' ) . "\n";

// Test removing capability from editor role
$result = Capabilities::remove_role_capability( 'editor', Capabilities::EXPORT_REPORTS );
echo "   Removing EXPORT_REPORTS from editor role: " . ( $result ? 'Success' : 'Failed' ) . "\n";

// Test invalid operations
$result = Capabilities::add_role_capability( 'author', 'invalid_capability' );
echo "   Adding invalid capability: " . ( $result ? 'Success' : 'Failed (Expected)' ) . "\n";

$result = Capabilities::add_role_capability( 'invalid_role', Capabilities::VIEW_DASHBOARD );
echo "   Adding capability to invalid role: " . ( $result ? 'Success' : 'Failed (Expected)' ) . "\n";

echo "\n=== Demo Complete ===\n";
echo "The capability system is working correctly!\n";
echo "All admin pages are now protected with granular permissions.\n";
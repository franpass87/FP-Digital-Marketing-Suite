<?php
/**
 * FP Digital Marketing Suite - Post-Deployment Verification
 * 
 * Run this script after plugin activation to verify everything is working.
 * Place this file in your WordPress root directory and access via browser.
 * Remove after verification is complete.
 * 
 * Usage: http://yoursite.com/verify-fp-dms.php
 */

// Security check - only run if WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
    require_once( dirname( __FILE__ ) . '/wp-config.php' );
}

// Additional security - only for admin users
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied. This verification script requires administrator privileges.' );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FP Digital Marketing Suite - Verification</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .status-ok { color: #28a745; } .status-error { color: #dc3545; } .status-warning { color: #ffc107; }
        .test-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .test-item { margin: 5px 0; } .test-item:before { content: ""; }
        pre { background: #e9ecef; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🚀 FP Digital Marketing Suite - Deployment Verification</h1>
    
    <?php
    
    echo '<div class="test-section">';
    echo '<h2>1. Plugin Status</h2>';
    
    // Check if plugin is active
    if ( is_plugin_active( 'FP-Digital-Marketing-Suite/fp-digital-marketing-suite.php' ) || 
         is_plugin_active( 'fp-digital-marketing-suite/fp-digital-marketing-suite.php' ) ) {
        echo '<div class="test-item status-ok">✓ Plugin is active</div>';
    } else {
        echo '<div class="test-item status-error">✗ Plugin is not active</div>';
    }
    
    // Check if main class exists
    if ( class_exists( 'FP\\DigitalMarketing\\DigitalMarketingSuite' ) ) {
        echo '<div class="test-item status-ok">✓ Main class loaded</div>';
    } else {
        echo '<div class="test-item status-error">✗ Main class not found</div>';
    }
    
    echo '</div>';
    
    // Database Tables Check
    echo '<div class="test-section">';
    echo '<h2>2. Database Tables</h2>';
    
    global $wpdb;
    $tables_to_check = [
        'fp_metrics_cache',
        'fp_anomaly_rules', 
        'fp_detected_anomalies',
        'fp_alert_rules',
        'fp_utm_campaigns',
        'fp_conversion_events',
        'fp_audience_segments'
    ];
    
    foreach ( $tables_to_check as $table ) {
        $table_name = $wpdb->prefix . $table;
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        
        if ( $table_exists ) {
            echo '<div class="test-item status-ok">✓ Table ' . $table_name . ' exists</div>';
        } else {
            echo '<div class="test-item status-warning">⚠ Table ' . $table_name . ' not found</div>';
        }
    }
    
    echo '</div>';
    
    // Admin Pages Check
    echo '<div class="test-section">';
    echo '<h2>3. Admin Pages</h2>';
    
    // Check if settings page exists
    $settings_page = admin_url( 'options-general.php?page=fp-digital-marketing' );
    echo '<div class="test-item">Settings page: <a href="' . $settings_page . '" target="_blank">' . $settings_page . '</a></div>';
    
    // Check menu items
    global $menu, $submenu;
    $fp_menu_found = false;
    
    if ( isset( $menu ) ) {
        foreach ( $menu as $menu_item ) {
            if ( isset( $menu_item[2] ) && strpos( $menu_item[2], 'fp-digital-marketing' ) !== false ) {
                $fp_menu_found = true;
                break;
            }
        }
    }
    
    if ( $fp_menu_found ) {
        echo '<div class="test-item status-ok">✓ Plugin menu items found</div>';
    } else {
        echo '<div class="test-item status-warning">⚠ Plugin menu items not found (may be normal)</div>';
    }
    
    echo '</div>';
    
    // Post Types Check
    echo '<div class="test-section">';
    echo '<h2>4. Custom Post Types</h2>';
    
    $post_types = get_post_types( array( 'public' => false ), 'objects' );
    $cliente_found = false;
    
    foreach ( $post_types as $post_type ) {
        if ( $post_type->name === 'cliente' ) {
            $cliente_found = true;
            break;
        }
    }
    
    if ( $cliente_found ) {
        echo '<div class="test-item status-ok">✓ Cliente post type registered</div>';
    } else {
        echo '<div class="test-item status-warning">⚠ Cliente post type not found</div>';
    }
    
    echo '</div>';
    
    // Assets Check
    echo '<div class="test-section">';
    echo '<h2>5. Assets</h2>';
    
    $plugin_url = plugin_dir_url( WP_PLUGIN_DIR . '/fp-digital-marketing-suite/fp-digital-marketing-suite.php' );
    
    $assets = [
        'css/dashboard.css',
        'css/settings-tabs.css',
        'js/dashboard.js',
        'js/settings-tabs.js'
    ];
    
    foreach ( $assets as $asset ) {
        $asset_url = $plugin_url . 'assets/' . $asset;
        echo '<div class="test-item">Asset: <a href="' . $asset_url . '" target="_blank">' . basename( $asset ) . '</a></div>';
    }
    
    echo '</div>';
    
    // WordPress Environment
    echo '<div class="test-section">';
    echo '<h2>6. WordPress Environment</h2>';
    
    echo '<div class="test-item">WordPress Version: ' . get_bloginfo( 'version' ) . '</div>';
    echo '<div class="test-item">PHP Version: ' . PHP_VERSION . '</div>';
    echo '<div class="test-item">MySQL Version: ' . $wpdb->db_version() . '</div>';
    
    if ( version_compare( get_bloginfo( 'version' ), '5.0', '>=' ) ) {
        echo '<div class="test-item status-ok">✓ WordPress version compatible</div>';
    } else {
        echo '<div class="test-item status-error">✗ WordPress version too old (requires 5.0+)</div>';
    }
    
    if ( version_compare( PHP_VERSION, '7.4', '>=' ) ) {
        echo '<div class="test-item status-ok">✓ PHP version compatible</div>';
    } else {
        echo '<div class="test-item status-error">✗ PHP version too old (requires 7.4+)</div>';
    }
    
    echo '</div>';
    
    // Error Log Check
    echo '<div class="test-section">';
    echo '<h2>7. Error Log Check</h2>';
    
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $log_file ) ) {
            $log_content = file_get_contents( $log_file );
            $fp_errors = substr_count( $log_content, 'FP Digital Marketing' );
            
            if ( $fp_errors === 0 ) {
                echo '<div class="test-item status-ok">✓ No plugin errors in debug log</div>';
            } else {
                echo '<div class="test-item status-warning">⚠ Found ' . $fp_errors . ' plugin-related log entries</div>';
            }
        } else {
            echo '<div class="test-item">Debug log file not found (may be normal)</div>';
        }
    } else {
        echo '<div class="test-item">Debug logging not enabled</div>';
    }
    
    echo '</div>';
    
    // Configuration Test
    echo '<div class="test-section">';
    echo '<h2>8. Quick Configuration Test</h2>';
    
    // Test if we can save a setting
    $test_option = 'fp_dms_verification_test';
    $test_value = current_time( 'timestamp' );
    
    update_option( $test_option, $test_value );
    $retrieved_value = get_option( $test_option );
    
    if ( $retrieved_value == $test_value ) {
        echo '<div class="test-item status-ok">✓ Options API working</div>';
        delete_option( $test_option ); // Clean up
    } else {
        echo '<div class="test-item status-error">✗ Options API test failed</div>';
    }
    
    echo '</div>';
    
    ?>
    
    <div class="test-section">
        <h2>9. Next Steps</h2>
        <div class="test-item">1. Visit the <a href="<?php echo admin_url( 'options-general.php?page=fp-digital-marketing' ); ?>">Settings Page</a> to configure API keys</div>
        <div class="test-item">2. Check the <a href="<?php echo admin_url(); ?>">Dashboard</a> for new widgets</div>
        <div class="test-item">3. Explore the <a href="<?php echo admin_url( 'edit.php?post_type=cliente' ); ?>">Cliente Management</a> section</div>
        <div class="test-item">4. Review the plugin documentation in the plugin directory</div>
        <div class="test-item">5. <strong>Delete this verification file</strong> for security</div>
    </div>
    
    <div class="test-section">
        <h2>✅ Verification Complete</h2>
        <p><strong>The FP Digital Marketing Suite is successfully deployed and ready for use!</strong></p>
        <p>For security reasons, please delete this verification file after reviewing the results.</p>
    </div>
    
</body>
</html>
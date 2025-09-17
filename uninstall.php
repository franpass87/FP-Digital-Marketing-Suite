<?php
/**
 * Plugin uninstall script
 * 
 * This file is called when the plugin is deleted from WordPress admin.
 * It handles cleanup of database tables, options, and user meta.
 * 
 * @package FP_Digital_Marketing_Suite
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Security check
if ( ! current_user_can( 'activate_plugins' ) ) {
    return;
}

/**
 * Cleanup database tables
 */
function fp_dms_cleanup_database_tables() {
    global $wpdb;

    $table_classes = array(
        '\\FP\\DigitalMarketing\\Database\\MetricsCacheTable' => __DIR__ . '/src/Database/MetricsCacheTable.php',
        '\\FP\\DigitalMarketing\\Database\\AlertRulesTable' => __DIR__ . '/src/Database/AlertRulesTable.php',
        '\\FP\\DigitalMarketing\\Database\\AnomalyRulesTable' => __DIR__ . '/src/Database/AnomalyRulesTable.php',
        '\\FP\\DigitalMarketing\\Database\\DetectedAnomaliesTable' => __DIR__ . '/src/Database/DetectedAnomaliesTable.php',
    );

    foreach ( $table_classes as $class => $path ) {
        if ( ! class_exists( $class ) && file_exists( $path ) ) {
            require_once $path;
        }

        if ( class_exists( $class ) && method_exists( $class, 'drop_table' ) ) {
            $class::drop_table();
        }
    }

    // List of custom tables to remove
    $tables = array(
        $wpdb->prefix . 'fp_dms_clients',
        $wpdb->prefix . 'fp_dms_analytics_data',
        $wpdb->prefix . 'fp_dms_campaigns',
        $wpdb->prefix . 'fp_dms_conversion_events',
        $wpdb->prefix . 'fp_dms_audience_segments',
        $wpdb->prefix . 'fp_dms_alerts',
        $wpdb->prefix . 'fp_dms_performance_metrics'
    );

    // Drop each table
    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }
}

/**
 * Cleanup WordPress options
 */
function fp_dms_cleanup_options() {
    // Plugin settings
    delete_option( 'fp_dms_settings' );
    delete_option( 'fp_dms_ga4_settings' );
    delete_option( 'fp_dms_google_ads_settings' );
    delete_option( 'fp_dms_gsc_settings' );
    delete_option( 'fp_dms_clarity_settings' );
    delete_option( 'fp_dms_seo_settings' );
    delete_option( 'fp_dms_performance_settings' );
    delete_option( 'fp_dms_alert_settings' );
    delete_option( 'fp_dms_email_settings' );
    
    // Plugin status and version
    delete_option( 'fp_dms_version' );
    delete_option( 'fp_dms_activation_time' );
    delete_option( 'fp_dms_setup_completed' );
    
    // Cache and temporary data
    delete_option( 'fp_dms_cache_settings' );
    delete_option( 'fp_dms_performance_cache' );
    delete_option( 'fp_dms_analytics_cache' );
    
    // API keys and tokens (security cleanup)
    delete_option( 'fp_dms_ga4_credentials' );
    delete_option( 'fp_dms_google_ads_credentials' );
    delete_option( 'fp_dms_gsc_credentials' );
    
    // Cleanup any transients
    delete_transient( 'fp_dms_ga4_data' );
    delete_transient( 'fp_dms_google_ads_data' );
    delete_transient( 'fp_dms_gsc_data' );
    delete_transient( 'fp_dms_clarity_data' );
}

/**
 * Cleanup user meta
 */
function fp_dms_cleanup_user_meta() {
    global $wpdb;
    
    // Remove user meta related to the plugin
    $wpdb->delete(
        $wpdb->usermeta,
        array(
            'meta_key' => 'fp_dms_dashboard_settings'
        )
    );
    
    $wpdb->delete(
        $wpdb->usermeta,
        array(
            'meta_key' => 'fp_dms_user_preferences'
        )
    );
    
    $wpdb->delete(
        $wpdb->usermeta,
        array(
            'meta_key' => 'fp_dms_onboarding_completed'
        )
    );
}

/**
 * Cleanup post meta and custom posts
 */
function fp_dms_cleanup_posts() {
    global $wpdb;
    
    // Get all Cliente posts
    $cliente_posts = get_posts( array(
        'post_type' => 'cliente',
        'numberposts' => -1,
        'post_status' => 'any'
    ) );
    
    // Delete Cliente posts and their meta
    foreach ( $cliente_posts as $post ) {
        wp_delete_post( $post->ID, true );
    }
    
    // Clean up any remaining post meta
    $wpdb->delete(
        $wpdb->postmeta,
        array(
            'meta_key' => 'fp_dms_client_data'
        )
    );
}

/**
 * Cleanup scheduled events
 */
function fp_dms_cleanup_scheduled_events() {
    // Remove scheduled cron events
    wp_clear_scheduled_hook( 'fp_dms_sync_analytics_data' );
    wp_clear_scheduled_hook( 'fp_dms_check_performance_metrics' );
    wp_clear_scheduled_hook( 'fp_dms_send_alert_emails' );
    wp_clear_scheduled_hook( 'fp_dms_cleanup_old_data' );
    wp_clear_scheduled_hook( 'fp_dms_refresh_cache' );
}

/**
 * Cleanup uploaded files
 */
function fp_dms_cleanup_files() {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/fp-dms/';
    
    if ( is_dir( $plugin_upload_dir ) ) {
        // Remove all files in the plugin upload directory
        $files = glob( $plugin_upload_dir . '*' );
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                unlink( $file );
            }
        }
        // Remove the directory
        rmdir( $plugin_upload_dir );
    }
}

/**
 * Log uninstall activity
 */
function fp_dms_log_uninstall() {
    // Log to WordPress debug log if enabled
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        error_log( 'FP Digital Marketing Suite: Plugin uninstalled and cleaned up successfully.' );
    }
}

// Main cleanup process
try {
    // Perform cleanup operations
    fp_dms_cleanup_database_tables();
    fp_dms_cleanup_options();
    fp_dms_cleanup_user_meta();
    fp_dms_cleanup_posts();
    fp_dms_cleanup_scheduled_events();
    fp_dms_cleanup_files();
    fp_dms_log_uninstall();
    
    // Clear any remaining cache
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
    
} catch ( Exception $e ) {
    // Log error if cleanup fails
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        error_log( 'FP Digital Marketing Suite uninstall error: ' . $e->getMessage() );
    }
}

// Final security check - ensure we're in uninstall context
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
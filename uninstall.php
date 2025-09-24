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

    $tables = array(
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\ConversionEventsTable',
            'file'    => __DIR__ . '/src/Database/ConversionEventsTable.php',
            'methods' => array( 'drop_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\AudienceSegmentTable',
            'file'    => __DIR__ . '/src/Database/AudienceSegmentTable.php',
            'methods' => array( 'drop_segments_table', 'drop_membership_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\UTMCampaignsTable',
            'file'    => __DIR__ . '/src/Database/UTMCampaignsTable.php',
            'methods' => array( 'drop_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\FunnelTable',
            'file'    => __DIR__ . '/src/Database/FunnelTable.php',
            'methods' => array( 'drop_table', 'drop_stages_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\CustomerJourneyTable',
            'file'    => __DIR__ . '/src/Database/CustomerJourneyTable.php',
            'methods' => array( 'drop_table', 'drop_sessions_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\CustomReportsTable',
            'file'    => __DIR__ . '/src/Database/CustomReportsTable.php',
            'methods' => array( 'drop_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\SocialSentimentTable',
            'file'    => __DIR__ . '/src/Database/SocialSentimentTable.php',
            'methods' => array( 'drop_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\MetricsCacheTable',
            'file'    => __DIR__ . '/src/Database/MetricsCacheTable.php',
            'methods' => array( 'drop_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\AlertRulesTable',
            'file'    => __DIR__ . '/src/Database/AlertRulesTable.php',
            'methods' => array( 'drop_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\AnomalyRulesTable',
            'file'    => __DIR__ . '/src/Database/AnomalyRulesTable.php',
            'methods' => array( 'drop_table' ),
        ),
        array(
            'class'   => '\\FP\\DigitalMarketing\\Database\\DetectedAnomaliesTable',
            'file'    => __DIR__ . '/src/Database/DetectedAnomaliesTable.php',
            'methods' => array( 'drop_table' ),
        ),
    );

    foreach ( $tables as $definition ) {
        $class = $definition['class'];

        if ( ! class_exists( $class ) && isset( $definition['file'] ) && file_exists( $definition['file'] ) ) {
            require_once $definition['file'];
        }

        if ( ! class_exists( $class ) ) {
            continue;
        }

        foreach ( $definition['methods'] as $method ) {
            if ( method_exists( $class, $method ) ) {
                call_user_func( array( $class, $method ) );
            }
        }
    }

    // Drop any legacy tables that may still exist from older versions.
    if ( isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
        $legacy_tables = array(
            $wpdb->prefix . 'fp_dms_clients',
            $wpdb->prefix . 'fp_dms_analytics_data',
            $wpdb->prefix . 'fp_dms_campaigns',
            $wpdb->prefix . 'fp_dms_alerts',
            $wpdb->prefix . 'fp_dms_performance_metrics',
        );

        foreach ( $legacy_tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }
}

/**
 * Cleanup WordPress options
 */
function fp_dms_cleanup_options() {
    global $wpdb;

    $option_names = array(
        // Core settings stored through the Settings API.
        'fp_digital_marketing_settings',
        'fp_digital_marketing_api_keys',
        'fp_digital_marketing_sync_settings',
        'fp_digital_marketing_cache_settings',
        'fp_digital_marketing_seo_settings',
        'fp_digital_marketing_sitemap_settings',
        'fp_digital_marketing_schema_settings',
        'fp_digital_marketing_email_settings',
        'fp_digital_marketing_demo_option',
        'fp_digital_marketing_report_config',
        'fp_digital_marketing_user_feedback',
        'fp_digital_marketing_menu_state',
        'fp_digital_marketing_wizard_progress',
        'fp_digital_marketing_wizard_completed',

        // Performance cache metrics.
        'fp_digital_marketing_benchmark_data',
        'fp_digital_marketing_benchmark_results',
        'fp_digital_marketing_cache_stats',

        // Setup wizard and onboarding flags.
        'fp_dms_setup_completed',
        'fp_dms_setup_completed_time',
        'fp_dms_analytics_settings',
        'fp_dms_seo_settings',
        'fp_dms_performance_settings',

        // Logs and monitoring data.
        'fp_dms_security_logs',
        'fp_dms_alert_logs',
        'fp_dms_anomaly_logs',
        'fp_dms_report_logs',
        'fp_dms_sync_logs',
        'fp_dms_performance_metrics',
        'fp_dms_enable_performance_monitoring',

        // OAuth tokens and integration state.
        'fp_digital_marketing_google_oauth_tokens',
        'fp_digital_marketing_google_oauth_settings',
        'fp_digital_marketing_oauth_state',
        'fp_dms_google_oauth_tokens',
        'fp_dms_google_oauth_settings',
        'fp_dms_oauth_state',

        // Capability registration cache.
        'fp_dms_capabilities_registered',

        // Migration/compatibility settings that may be present.
        'fp_dms_analytics_ga4_measurement_id',
        'fp_dms_analytics_ua_tracking_id',
        'fp_dms_analytics_track_users',
        'fp_dms_gsc_property_url',
        'fp_dms_adsense_client_id',
        'fp_dms_crux_api_key',
    );

    foreach ( $option_names as $option ) {
        delete_option( $option );
    }

    // Remove transient-based state used across subsystems.
    $transient_names = array(
        'fp_dms_activation_redirect',
        'fp_dms_security_audit',
    );

    foreach ( $transient_names as $transient ) {
        delete_transient( $transient );
    }

    if ( isset( $wpdb ) && property_exists( $wpdb, 'options' ) ) {
        // Remove dynamically named transients generated by the cache, export and alert subsystems.
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_dms_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fp_dms_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_digital_marketing_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fp_digital_marketing_%'"
        );
    }

    // Clean up legacy options from early releases if they still exist.
    $legacy_options = array(
        'fp_dms_version',
        'fp_dms_activation_time',
        'fp_dms_cache_settings',
        'fp_dms_performance_cache',
        'fp_dms_analytics_cache',
        'fp_dms_ga4_credentials',
        'fp_dms_google_ads_credentials',
        'fp_dms_gsc_credentials',
    );

    foreach ( $legacy_options as $legacy_option ) {
        delete_option( $legacy_option );
    }
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
    $hooks = array(
        // Current scheduled events.
        'fp_dms_sync_data_sources',
        'fp_dms_generate_reports',
        'fp_dms_daily_digest',
        'fp_dms_cache_warmup',
        'fp_dms_evaluate_all_segments',
        'fp_dms_cleanup_exports',
        'fp_dms_cleanup_export_file',

        // Legacy hooks kept for backwards compatibility.
        'fp_dms_sync_analytics_data',
        'fp_dms_check_performance_metrics',
        'fp_dms_send_alert_emails',
        'fp_dms_cleanup_old_data',
        'fp_dms_refresh_cache',
    );

    foreach ( $hooks as $hook ) {
        wp_clear_scheduled_hook( $hook );
    }
}

/**
 * Recursively delete a directory and its contents.
 *
 * @param string $directory Absolute path to the directory.
 * @return void
 */
function fp_dms_delete_directory( $directory ) {
    if ( ! is_dir( $directory ) ) {
        return;
    }

    $items = scandir( $directory );
    if ( false === $items ) {
        return;
    }

    foreach ( $items as $item ) {
        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        $path = $directory . '/' . $item;

        if ( is_dir( $path ) ) {
            fp_dms_delete_directory( $path );
            continue;
        }

        if ( function_exists( 'wp_delete_file' ) ) {
            wp_delete_file( $path );
        } else {
            @unlink( $path );
        }
    }

    @rmdir( $directory );
}

/**
 * Cleanup uploaded files
 */
function fp_dms_cleanup_files() {
    $upload_dir = wp_upload_dir();
    if ( empty( $upload_dir['basedir'] ) ) {
        return;
    }

    $directories = array(
        $upload_dir['basedir'] . '/fp-dms-exports',
        $upload_dir['basedir'] . '/fp-dms-reports',
        $upload_dir['basedir'] . '/fp-dms', // Legacy directory from early releases.
    );

    foreach ( $directories as $directory ) {
        if ( is_dir( $directory ) ) {
            fp_dms_delete_directory( $directory );
        }
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
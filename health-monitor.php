<?php
/**
 * Health Check and Monitoring Tools
 * 
 * Provides comprehensive system health monitoring for the FP Digital Marketing Suite
 * 
 * @package FP_Digital_Marketing_Suite
 * @subpackage Tools
 * @since 1.0.0
 */

class FP_Health_Monitor {
    
    /**
     * Run comprehensive health check
     * 
     * @return array Health check results
     */
    public static function run_health_check() {
        $results = array(
            'overall_status' => 'healthy',
            'timestamp' => current_time('mysql'),
            'checks' => array()
        );
        
        // Database connectivity check
        $results['checks']['database'] = self::check_database();
        
        // Plugin dependencies check
        $results['checks']['dependencies'] = self::check_dependencies();
        
        // Memory usage check
        $results['checks']['memory'] = self::check_memory();
        
        // Cache system check
        $results['checks']['cache'] = self::check_cache();
        
        // API connectivity check
        $results['checks']['api_connections'] = self::check_api_connections();
        
        // Performance metrics
        $results['checks']['performance'] = self::check_performance();
        
        // Security checks
        $results['checks']['security'] = self::check_security();
        
        // Determine overall status
        $results['overall_status'] = self::determine_overall_status($results['checks']);
        
        return $results;
    }
    
    /**
     * Check database connectivity and health
     */
    private static function check_database() {
        global $wpdb;
        
        $check = array(
            'status' => 'healthy',
            'message' => 'Database connection successful',
            'details' => array()
        );
        
        try {
            // Test database connection
            $wpdb->get_var("SELECT 1");
            
            // Check custom tables
            $tables = array(
                $wpdb->prefix . 'fp_clients',
                $wpdb->prefix . 'fp_analytics_data',
                $wpdb->prefix . 'fp_seo_data',
                $wpdb->prefix . 'fp_alerts',
                $wpdb->prefix . 'fp_performance_metrics',
                $wpdb->prefix . 'fp_utm_campaigns',
                $wpdb->prefix . 'fp_conversion_events'
            );
            
            foreach ($tables as $table) {
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                $check['details'][$table] = $exists ? 'exists' : 'missing';
                
                if (!$exists) {
                    $check['status'] = 'warning';
                    $check['message'] = 'Some database tables are missing';
                }
            }
            
        } catch (Exception $e) {
            $check['status'] = 'critical';
            $check['message'] = 'Database connection failed: ' . $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Check plugin dependencies
     */
    private static function check_dependencies() {
        $check = array(
            'status' => 'healthy',
            'message' => 'All dependencies satisfied',
            'details' => array()
        );
        
        // Check PHP version
        $php_version = PHP_VERSION;
        $min_php = '7.4.0';
        $check['details']['php_version'] = $php_version;
        
        if (version_compare($php_version, $min_php, '<')) {
            $check['status'] = 'critical';
            $check['message'] = "PHP version {$php_version} is below minimum {$min_php}";
        }
        
        // Check WordPress version
        $wp_version = get_bloginfo('version');
        $min_wp = '5.8.0';
        $check['details']['wordpress_version'] = $wp_version;
        
        if (version_compare($wp_version, $min_wp, '<')) {
            $check['status'] = 'warning';
            $check['message'] = "WordPress version {$wp_version} is below recommended {$min_wp}";
        }
        
        // Check required PHP extensions
        $required_extensions = array('curl', 'json', 'mbstring', 'openssl', 'zip');
        foreach ($required_extensions as $ext) {
            $check['details']['ext_' . $ext] = extension_loaded($ext) ? 'loaded' : 'missing';
            
            if (!extension_loaded($ext)) {
                $check['status'] = 'critical';
                $check['message'] = "Required PHP extension '{$ext}' is missing";
            }
        }
        
        return $check;
    }
    
    /**
     * Check memory usage
     */
    private static function check_memory() {
        $check = array(
            'status' => 'healthy',
            'message' => 'Memory usage within normal limits',
            'details' => array()
        );
        
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        $check['details']['memory_limit'] = $memory_limit;
        $check['details']['current_usage'] = size_format($memory_usage);
        $check['details']['peak_usage'] = size_format($memory_peak);
        
        // Convert memory limit to bytes for comparison
        $limit_bytes = wp_convert_hr_to_bytes($memory_limit);
        $usage_percentage = ($memory_peak / $limit_bytes) * 100;
        
        $check['details']['usage_percentage'] = round($usage_percentage, 2) . '%';
        
        if ($usage_percentage > 90) {
            $check['status'] = 'critical';
            $check['message'] = 'Memory usage is critically high';
        } elseif ($usage_percentage > 75) {
            $check['status'] = 'warning';
            $check['message'] = 'Memory usage is high';
        }
        
        return $check;
    }
    
    /**
     * Check cache system
     */
    private static function check_cache() {
        $check = array(
            'status' => 'healthy',
            'message' => 'Cache system operational',
            'details' => array()
        );
        
        // Test WordPress object cache
        $test_key = 'fp_health_check_' . time();
        $test_value = 'test_value';
        
        wp_cache_set($test_key, $test_value, 'fp_health');
        $retrieved = wp_cache_get($test_key, 'fp_health');
        
        $check['details']['object_cache'] = ($retrieved === $test_value) ? 'working' : 'failed';
        
        if ($retrieved !== $test_value) {
            $check['status'] = 'warning';
            $check['message'] = 'Object cache is not working properly';
        }
        
        // Check if persistent cache is available
        $check['details']['persistent_cache'] = wp_using_ext_object_cache() ? 'enabled' : 'disabled';
        
        return $check;
    }
    
    /**
     * Check API connections
     */
    private static function check_api_connections() {
        $check = array(
            'status' => 'healthy',
            'message' => 'API connections functional',
            'details' => array()
        );
        
        $apis = array(
            'google_analytics' => 'https://www.googleapis.com/analytics/v3/metadata/ga/columns',
            'google_ads' => 'https://googleads.googleapis.com/v13/customers:listAccessibleCustomers',
            'search_console' => 'https://www.googleapis.com/webmasters/v3/sites'
        );
        
        foreach ($apis as $api => $url) {
            $response = wp_remote_get($url, array('timeout' => 10));
            
            if (is_wp_error($response)) {
                $check['details'][$api] = 'connection_failed';
                $check['status'] = 'warning';
                $check['message'] = 'Some API connections failed';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $check['details'][$api] = ($code >= 200 && $code < 500) ? 'reachable' : 'unreachable';
            }
        }
        
        return $check;
    }
    
    /**
     * Check performance metrics
     */
    private static function check_performance() {
        $check = array(
            'status' => 'healthy',
            'message' => 'Performance within acceptable range',
            'details' => array()
        );
        
        // Measure database query time
        $start_time = microtime(true);
        global $wpdb;
        $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        $db_time = microtime(true) - $start_time;
        
        $check['details']['db_query_time'] = round($db_time * 1000, 2) . 'ms';
        
        if ($db_time > 0.5) {
            $check['status'] = 'warning';
            $check['message'] = 'Database queries are slow';
        }
        
        // Check plugin loading time
        $check['details']['plugin_load_time'] = 'N/A';
        
        return $check;
    }
    
    /**
     * Check security status
     */
    private static function check_security() {
        $check = array(
            'status' => 'healthy',
            'message' => 'Security checks passed',
            'details' => array()
        );
        
        // Check if debug mode is enabled in production
        $check['details']['debug_mode'] = WP_DEBUG ? 'enabled' : 'disabled';
        
        if (WP_DEBUG && !WP_DEBUG_LOG) {
            $check['status'] = 'warning';
            $check['message'] = 'Debug mode is enabled without logging';
        }
        
        // Check file permissions
        $upload_dir = wp_upload_dir();
        $check['details']['uploads_writable'] = is_writable($upload_dir['basedir']) ? 'yes' : 'no';
        
        // Check SSL
        $check['details']['ssl_enabled'] = is_ssl() ? 'yes' : 'no';
        
        return $check;
    }
    
    /**
     * Determine overall status from individual checks
     */
    private static function determine_overall_status($checks) {
        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                return 'critical';
            }
        }
        
        foreach ($checks as $check) {
            if ($check['status'] === 'warning') {
                return 'warning';
            }
        }
        
        return 'healthy';
    }
    
    /**
     * Get formatted health check report
     */
    public static function get_formatted_report() {
        $results = self::run_health_check();
        
        $report = "FP Digital Marketing Suite - Health Check Report\n";
        $report .= "======================================================\n";
        $report .= "Overall Status: " . strtoupper($results['overall_status']) . "\n";
        $report .= "Timestamp: " . $results['timestamp'] . "\n\n";
        
        foreach ($results['checks'] as $category => $check) {
            $report .= strtoupper(str_replace('_', ' ', $category)) . "\n";
            $report .= "Status: " . strtoupper($check['status']) . "\n";
            $report .= "Message: " . $check['message'] . "\n";
            
            if (!empty($check['details'])) {
                $report .= "Details:\n";
                foreach ($check['details'] as $key => $value) {
                    $report .= "  - " . str_replace('_', ' ', $key) . ": " . $value . "\n";
                }
            }
            $report .= "\n";
        }
        
        return $report;
    }
}

// AJAX handler for health check
add_action('wp_ajax_fp_health_check', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $results = FP_Health_Monitor::run_health_check();
    wp_send_json($results);
});

// CLI command for health check
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('fp health-check', function() {
        $report = FP_Health_Monitor::get_formatted_report();
        WP_CLI::log($report);
    });
}
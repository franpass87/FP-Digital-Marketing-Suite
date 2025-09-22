<?php
/**
 * CLI Management Tools
 * 
 * Command line interface for FP Digital Marketing Suite management
 * 
 * @package FP_Digital_Marketing_Suite
 * @subpackage Tools
 * @since 1.0.0
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * FP Digital Marketing Suite CLI Commands
 */
class FP_CLI_Commands {
    
    /**
     * Display plugin status and configuration
     * 
     * ## EXAMPLES
     * 
     *     wp fp status
     *     wp fp status --format=json
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function status($args, $assoc_args) {
        $status = array(
            'plugin_version' => defined('FP_DIGITAL_MARKETING_VERSION') ? FP_DIGITAL_MARKETING_VERSION : 'Unknown',
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'active' => is_plugin_active('fp-digital-marketing-suite/fp-digital-marketing-suite.php'),
            'database_tables' => $this->check_database_tables(),
            'settings' => $this->get_plugin_settings(),
            'integrations' => $this->check_integrations()
        );
        
        $format = $assoc_args['format'] ?? 'table';
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($status, JSON_PRETTY_PRINT));
        } else {
            WP_CLI::log("FP Digital Marketing Suite Status");
            WP_CLI::log("====================================");
            WP_CLI::log("Plugin Version: " . $status['plugin_version']);
            WP_CLI::log("WordPress Version: " . $status['wordpress_version']);
            WP_CLI::log("PHP Version: " . $status['php_version']);
            WP_CLI::log("Active: " . ($status['active'] ? 'Yes' : 'No'));
            WP_CLI::log("Database Tables: " . $status['database_tables']['total'] . " (" . $status['database_tables']['existing'] . " existing)");
            WP_CLI::log("Configured Integrations: " . count(array_filter($status['integrations'])));
        }
    }
    
    /**
     * Setup or configure integrations
     * 
     * ## OPTIONS
     * 
     * <integration>
     * : The integration to configure (ga4, google-ads, search-console, clarity)
     * 
     * [--api-key=<key>]
     * : API key for the integration
     * 
     * [--property-id=<id>]
     * : Property ID for the integration
     * 
     * ## EXAMPLES
     * 
     *     wp fp setup ga4 --property-id=GA_MEASUREMENT_ID
     *     wp fp setup google-ads --api-key=YOUR_API_KEY
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function setup($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify an integration to setup");
        }
        
        $integration = $args[0];
        
        switch ($integration) {
            case 'ga4':
                $this->setup_ga4($assoc_args);
                break;
            case 'google-ads':
                $this->setup_google_ads($assoc_args);
                break;
            case 'search-console':
                $this->setup_search_console($assoc_args);
                break;
            case 'clarity':
                $this->setup_clarity($assoc_args);
                break;
            default:
                WP_CLI::error("Unknown integration: {$integration}");
        }
    }
    
    /**
     * Import data from other plugins
     * 
     * ## OPTIONS
     * 
     * <source>
     * : Source plugin (yoast, rankmath, aioseo, monsterinsights, site-kit, jetpack)
     * 
     * [--dry-run]
     * : Show what would be imported without actually importing
     * 
     * ## EXAMPLES
     * 
     *     wp fp import yoast
     *     wp fp import monsterinsights --dry-run
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function import($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify a source plugin to import from");
        }
        
        $source = $args[0];
        $dry_run = isset($assoc_args['dry-run']);
        
        // Include migration tools
        require_once plugin_dir_path(__FILE__) . 'src/Tools/MigrationTools.php';

        if (!class_exists('\\FP\\DigitalMarketing\\Tools\\MigrationTools')) {
            WP_CLI::error("Migration tools not available");
        }

        // Instantiate the namespaced MigrationTools class.
        $migration = new \FP\DigitalMarketing\Tools\MigrationTools();
        
        try {
            switch ($source) {
                case 'yoast':
                    $results = $migration->import_from_yoast($dry_run);
                    break;
                case 'rankmath':
                    $results = $migration->import_from_rankmath($dry_run);
                    break;
                case 'aioseo':
                    $results = $migration->import_from_aioseo($dry_run);
                    break;
                case 'monsterinsights':
                    $results = $migration->import_from_monsterinsights($dry_run);
                    break;
                case 'site-kit':
                    $results = $migration->import_from_site_kit($dry_run);
                    break;
                case 'jetpack':
                    $results = $migration->import_from_jetpack($dry_run);
                    break;
                default:
                    WP_CLI::error("Unknown source plugin: {$source}");
            }
            
            if ($dry_run) {
                WP_CLI::log("Dry run completed. Found:");
            } else {
                WP_CLI::success("Import completed:");
            }
            
            foreach ($results as $type => $count) {
                WP_CLI::log("- {$type}: {$count}");
            }
            
        } catch (Exception $e) {
            WP_CLI::error("Import failed: " . $e->getMessage());
        }
    }
    
    /**
     * Clear cached data
     * 
     * ## OPTIONS
     * 
     * [<type>]
     * : Type of cache to clear (analytics, seo, performance, all)
     * 
     * ## EXAMPLES
     * 
     *     wp fp cache clear
     *     wp fp cache clear analytics
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function cache($args, $assoc_args) {
        $type = $args[0] ?? 'all';
        
        $cleared = 0;
        
        switch ($type) {
            case 'analytics':
                $cleared += $this->clear_analytics_cache();
                break;
            case 'seo':
                $cleared += $this->clear_seo_cache();
                break;
            case 'performance':
                $cleared += $this->clear_performance_cache();
                break;
            case 'all':
            default:
                $cleared += $this->clear_analytics_cache();
                $cleared += $this->clear_seo_cache();
                $cleared += $this->clear_performance_cache();
                $cleared += $this->clear_general_cache();
                break;
        }
        
        WP_CLI::success("Cleared {$cleared} cache entries");
    }
    
    /**
     * Generate reports
     * 
     * ## OPTIONS
     * 
     * <type>
     * : Report type (analytics, seo, performance, overview)
     * 
     * [--format=<format>]
     * : Output format (table, json, csv)
     * 
     * [--period=<period>]
     * : Time period (7d, 30d, 90d, 1y)
     * 
     * ## EXAMPLES
     * 
     *     wp fp report analytics --period=30d
     *     wp fp report overview --format=json
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function report($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify a report type");
        }
        
        $type = $args[0];
        $format = $assoc_args['format'] ?? 'table';
        $period = $assoc_args['period'] ?? '30d';
        
        switch ($type) {
            case 'analytics':
                $this->generate_analytics_report($format, $period);
                break;
            case 'seo':
                $this->generate_seo_report($format, $period);
                break;
            case 'performance':
                $this->generate_performance_report($format, $period);
                break;
            case 'overview':
                $this->generate_overview_report($format, $period);
                break;
            default:
                WP_CLI::error("Unknown report type: {$type}");
        }
    }
    
    /**
     * Optimize plugin performance
     * 
     * ## OPTIONS
     * 
     * [--rebuild-cache]
     * : Rebuild all caches
     * 
     * [--optimize-db]
     * : Optimize database tables
     * 
     * ## EXAMPLES
     * 
     *     wp fp optimize
     *     wp fp optimize --rebuild-cache --optimize-db
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function optimize($args, $assoc_args) {
        $operations = 0;
        
        if (isset($assoc_args['rebuild-cache'])) {
            WP_CLI::log("Rebuilding caches...");
            $this->rebuild_all_caches();
            $operations++;
        }
        
        if (isset($assoc_args['optimize-db'])) {
            WP_CLI::log("Optimizing database tables...");
            $this->optimize_database_tables();
            $operations++;
        }
        
        if ($operations === 0) {
            WP_CLI::log("Running general optimization...");
            $this->run_general_optimization();
        }
        
        WP_CLI::success("Optimization completed");
    }
    
    // Helper methods
    
    private function check_database_tables() {
        global $wpdb;
        
        $tables = array(
            'fp_clients',
            'fp_analytics_data',
            'fp_seo_data',
            'fp_alerts',
            'fp_performance_metrics',
            'fp_utm_campaigns',
            'fp_conversion_events'
        );
        
        $existing = 0;
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if ($exists) {
                $existing++;
            }
        }
        
        return array(
            'total' => count($tables),
            'existing' => $existing
        );
    }
    
    private function get_plugin_settings() {
        return array(
            'ga4_configured' => !empty(get_option('fp_google_analytics_settings')),
            'google_ads_configured' => !empty(get_option('fp_google_ads_settings')),
            'search_console_configured' => !empty(get_option('fp_search_console_settings')),
            'seo_configured' => !empty(get_option('fp_seo_settings'))
        );
    }
    
    private function check_integrations() {
        $ga4_settings = get_option('fp_google_analytics_settings', array());
        $ads_settings = get_option('fp_google_ads_settings', array());
        $gsc_settings = get_option('fp_search_console_settings', array());
        $clarity_settings = get_option('fp_clarity_settings', array());
        
        return array(
            'google_analytics' => !empty($ga4_settings['measurement_id']),
            'google_ads' => !empty($ads_settings['customer_id']),
            'search_console' => !empty($gsc_settings['site_url']),
            'clarity' => !empty($clarity_settings['project_id'])
        );
    }
    
    private function setup_ga4($assoc_args) {
        if (empty($assoc_args['property-id'])) {
            WP_CLI::error("Please provide --property-id for GA4 setup");
        }
        
        $settings = array(
            'measurement_id' => $assoc_args['property-id'],
            'enabled' => true,
            'configured_at' => current_time('mysql')
        );
        
        update_option('fp_google_analytics_settings', $settings);
        WP_CLI::success("GA4 configured with property ID: " . $assoc_args['property-id']);
    }
    
    private function setup_google_ads($assoc_args) {
        if (empty($assoc_args['api-key'])) {
            WP_CLI::error("Please provide --api-key for Google Ads setup");
        }
        
        $settings = array(
            'api_key' => $assoc_args['api-key'],
            'customer_id' => $assoc_args['customer-id'] ?? '',
            'enabled' => true,
            'configured_at' => current_time('mysql')
        );
        
        update_option('fp_google_ads_settings', $settings);
        WP_CLI::success("Google Ads configured");
    }
    
    private function setup_search_console($assoc_args) {
        $site_url = get_site_url();
        
        $settings = array(
            'site_url' => $site_url,
            'enabled' => true,
            'configured_at' => current_time('mysql')
        );
        
        update_option('fp_search_console_settings', $settings);
        WP_CLI::success("Search Console configured for: " . $site_url);
    }
    
    private function setup_clarity($assoc_args) {
        if (empty($assoc_args['property-id'])) {
            WP_CLI::error("Please provide --property-id for Clarity setup");
        }
        
        $settings = array(
            'project_id' => $assoc_args['property-id'],
            'enabled' => true,
            'configured_at' => current_time('mysql')
        );
        
        update_option('fp_clarity_settings', $settings);
        WP_CLI::success("Microsoft Clarity configured with project ID: " . $assoc_args['property-id']);
    }
    
    private function clear_analytics_cache() {
        global $wpdb;

        $count = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fp_analytics_cache_%'");
        $this->flush_cache_group('fp_analytics', [
            'analytics_overview',
            'analytics_top_pages',
            'analytics_events',
        ]);

        return $count;
    }

    private function clear_seo_cache() {
        global $wpdb;

        $count = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fp_seo_cache_%'");
        $this->flush_cache_group('fp_seo', [
            'seo_overview',
            'seo_keywords',
            'seo_audit',
        ]);

        return $count;
    }

    private function clear_performance_cache() {
        global $wpdb;

        $count = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fp_performance_cache_%'");
        $this->flush_cache_group('fp_performance', [
            'performance_overview',
            'performance_metrics',
            'performance_scores',
        ]);

        if ( class_exists('\\FP\\DigitalMarketing\\Helpers\\AdminOptimizations') ) {
            $admin_optimizations = new \FP\DigitalMarketing\Helpers\AdminOptimizations();
            $admin_optimizations->clear_performance_cache();
        }

        return $count;
    }

    private function clear_general_cache() {
        wp_cache_flush();
        return 1;
    }

    private function flush_cache_group($group, array $known_keys = []) {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($group);
            return;
        }

        if (class_exists('\\FP\\DigitalMarketing\\Helpers\\PerformanceCache')
            && method_exists('\\FP\\DigitalMarketing\\Helpers\\PerformanceCache', 'invalidate_group')
        ) {
            \FP\DigitalMarketing\Helpers\PerformanceCache::invalidate_group($group);
        }

        foreach ($known_keys as $key) {
            wp_cache_delete($key, $group);
        }

        if (empty($known_keys) && function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    private function generate_analytics_report($format, $period) {
        // Mock analytics data for CLI report
        $data = array(
            array('Metric' => 'Sessions', 'Value' => '1,245', 'Change' => '+12%'),
            array('Metric' => 'Page Views', 'Value' => '3,672', 'Change' => '+8%'),
            array('Metric' => 'Bounce Rate', 'Value' => '42%', 'Change' => '-5%'),
            array('Metric' => 'Avg. Session Duration', 'Value' => '2:34', 'Change' => '+15%')
        );
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($data, JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items('table', $data, array('Metric', 'Value', 'Change'));
        }
    }
    
    private function generate_seo_report($format, $period) {
        $data = array(
            array('Metric' => 'Organic Traffic', 'Value' => '892', 'Change' => '+18%'),
            array('Metric' => 'Keywords Ranking', 'Value' => '156', 'Change' => '+7%'),
            array('Metric' => 'Avg. Position', 'Value' => '12.3', 'Change' => '-2.1'),
            array('Metric' => 'Click-through Rate', 'Value' => '3.2%', 'Change' => '+0.5%')
        );
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($data, JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items('table', $data, array('Metric', 'Value', 'Change'));
        }
    }
    
    private function generate_performance_report($format, $period) {
        $data = array(
            array('Metric' => 'Page Load Time', 'Value' => '1.8s', 'Status' => 'Good'),
            array('Metric' => 'LCP', 'Value' => '2.1s', 'Status' => 'Good'),
            array('Metric' => 'FID', 'Value' => '45ms', 'Status' => 'Good'),
            array('Metric' => 'CLS', 'Value' => '0.08', 'Status' => 'Good')
        );
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($data, JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items('table', $data, array('Metric', 'Value', 'Status'));
        }
    }
    
    private function generate_overview_report($format, $period) {
        $overview = array(
            'period' => $period,
            'analytics' => array(
                'sessions' => 1245,
                'pageviews' => 3672,
                'bounce_rate' => 42
            ),
            'seo' => array(
                'organic_traffic' => 892,
                'keywords' => 156,
                'avg_position' => 12.3
            ),
            'performance' => array(
                'load_time' => 1.8,
                'lcp' => 2.1,
                'fid' => 45,
                'cls' => 0.08
            )
        );
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($overview, JSON_PRETTY_PRINT));
        } else {
            WP_CLI::log("FP Digital Marketing Suite - Overview Report ({$period})");
            WP_CLI::log("=====================================");
            WP_CLI::log("Analytics: {$overview['analytics']['sessions']} sessions, {$overview['analytics']['pageviews']} pageviews");
            WP_CLI::log("SEO: {$overview['seo']['organic_traffic']} organic traffic, {$overview['seo']['keywords']} keywords");
            WP_CLI::log("Performance: {$overview['performance']['load_time']}s load time, {$overview['performance']['lcp']}s LCP");
        }
    }
    
    private function rebuild_all_caches() {
        // Clear existing caches
        $this->clear_analytics_cache();
        $this->clear_seo_cache();
        $this->clear_performance_cache();
        
        // Trigger cache rebuild (mock implementation)
        do_action('fp_rebuild_caches');
    }
    
    private function optimize_database_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'fp_analytics_data',
            $wpdb->prefix . 'fp_seo_data',
            $wpdb->prefix . 'fp_performance_metrics'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }
    }
    
    private function run_general_optimization() {
        // Clear transients
        $this->clear_analytics_cache();
        $this->clear_seo_cache();
        $this->clear_performance_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear object cache
        wp_cache_flush();
    }
}

// Register CLI commands
WP_CLI::add_command('fp', 'FP_CLI_Commands');
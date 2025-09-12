<?php
/**
 * Settings Export/Import Tools
 * 
 * Configuration management and portability tools
 * 
 * @package FP_Digital_Marketing_Suite
 * @subpackage Tools
 * @since 1.0.0
 */

class FP_Settings_Manager {
    
    /**
     * Export all plugin settings
     * 
     * @param array $export_options What to export
     * @return array Exported settings
     */
    public static function export_settings($export_options = array()) {
        $default_options = array(
            'plugin_settings' => true,
            'integration_configs' => true,
            'seo_settings' => true,
            'performance_settings' => true,
            'alert_settings' => true,
            'custom_data' => false,
            'include_api_keys' => false
        );
        
        $export_options = array_merge($default_options, $export_options);
        
        $export_data = array(
            'export_timestamp' => current_time('mysql'),
            'plugin_version' => defined('FP_DIGITAL_MARKETING_VERSION') ? FP_DIGITAL_MARKETING_VERSION : '1.0.0',
            'wordpress_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'export_options' => $export_options,
            'settings' => array()
        );
        
        if ($export_options['plugin_settings']) {
            $export_data['settings']['plugin'] = self::export_plugin_settings($export_options['include_api_keys']);
        }
        
        if ($export_options['integration_configs']) {
            $export_data['settings']['integrations'] = self::export_integration_settings($export_options['include_api_keys']);
        }
        
        if ($export_options['seo_settings']) {
            $export_data['settings']['seo'] = self::export_seo_settings();
        }
        
        if ($export_options['performance_settings']) {
            $export_data['settings']['performance'] = self::export_performance_settings();
        }
        
        if ($export_options['alert_settings']) {
            $export_data['settings']['alerts'] = self::export_alert_settings();
        }
        
        if ($export_options['custom_data']) {
            $export_data['custom_data'] = self::export_custom_data();
        }
        
        return $export_data;
    }
    
    /**
     * Export plugin settings
     */
    private static function export_plugin_settings($include_api_keys = false) {
        $settings = get_option('fp_digital_marketing_settings', array());
        
        if (!$include_api_keys) {
            // Remove sensitive data
            $sensitive_keys = array('api_key', 'secret_key', 'access_token', 'refresh_token');
            foreach ($sensitive_keys as $key) {
                if (isset($settings[$key])) {
                    $settings[$key] = '[HIDDEN]';
                }
            }
        }
        
        return $settings;
    }
    
    /**
     * Export integration settings
     */
    private static function export_integration_settings($include_api_keys = false) {
        $integrations = array(
            'google_analytics' => get_option('fp_google_analytics_settings', array()),
            'google_ads' => get_option('fp_google_ads_settings', array()),
            'search_console' => get_option('fp_search_console_settings', array()),
            'clarity' => get_option('fp_clarity_settings', array())
        );
        
        if (!$include_api_keys) {
            foreach ($integrations as $integration => &$settings) {
                $sensitive_keys = array('api_key', 'client_secret', 'access_token', 'refresh_token');
                foreach ($sensitive_keys as $key) {
                    if (isset($settings[$key])) {
                        $settings[$key] = '[HIDDEN]';
                    }
                }
            }
        }
        
        return $integrations;
    }
    
    /**
     * Export SEO settings
     */
    private static function export_seo_settings() {
        return array(
            'seo_settings' => get_option('fp_seo_settings', array()),
            'sitemap_settings' => get_option('fp_sitemap_settings', array()),
            'schema_settings' => get_option('fp_schema_settings', array())
        );
    }
    
    /**
     * Export performance settings
     */
    private static function export_performance_settings() {
        return array(
            'performance_settings' => get_option('fp_performance_settings', array()),
            'cache_settings' => get_option('fp_cache_settings', array()),
            'optimization_settings' => get_option('fp_optimization_settings', array())
        );
    }
    
    /**
     * Export alert settings
     */
    private static function export_alert_settings() {
        return array(
            'alert_settings' => get_option('fp_alert_settings', array()),
            'notification_settings' => get_option('fp_notification_settings', array()),
            'threshold_settings' => get_option('fp_threshold_settings', array())
        );
    }
    
    /**
     * Export custom data
     */
    private static function export_custom_data() {
        global $wpdb;
        
        $custom_data = array();
        
        // Export UTM campaigns
        $utm_campaigns = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}fp_utm_campaigns ORDER BY id",
            ARRAY_A
        );
        $custom_data['utm_campaigns'] = $utm_campaigns;
        
        // Export conversion events
        $conversion_events = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}fp_conversion_events ORDER BY id",
            ARRAY_A
        );
        $custom_data['conversion_events'] = $conversion_events;
        
        return $custom_data;
    }
    
    /**
     * Import settings from exported data
     * 
     * @param array $import_data Exported settings data
     * @param array $import_options What to import
     * @return array Import results
     */
    public static function import_settings($import_data, $import_options = array()) {
        $default_options = array(
            'plugin_settings' => true,
            'integration_configs' => true,
            'seo_settings' => true,
            'performance_settings' => true,
            'alert_settings' => true,
            'custom_data' => false,
            'overwrite_existing' => true
        );
        
        $import_options = array_merge($default_options, $import_options);
        
        $results = array(
            'success' => false,
            'message' => '',
            'imported' => array(),
            'skipped' => array(),
            'errors' => array()
        );
        
        // Validate import data
        if (!self::validate_import_data($import_data)) {
            $results['message'] = 'Invalid import data format';
            return $results;
        }
        
        try {
            if ($import_options['plugin_settings'] && isset($import_data['settings']['plugin'])) {
                self::import_plugin_settings($import_data['settings']['plugin'], $import_options['overwrite_existing']);
                $results['imported'][] = 'plugin_settings';
            }
            
            if ($import_options['integration_configs'] && isset($import_data['settings']['integrations'])) {
                self::import_integration_settings($import_data['settings']['integrations'], $import_options['overwrite_existing']);
                $results['imported'][] = 'integration_configs';
            }
            
            if ($import_options['seo_settings'] && isset($import_data['settings']['seo'])) {
                self::import_seo_settings($import_data['settings']['seo'], $import_options['overwrite_existing']);
                $results['imported'][] = 'seo_settings';
            }
            
            if ($import_options['performance_settings'] && isset($import_data['settings']['performance'])) {
                self::import_performance_settings($import_data['settings']['performance'], $import_options['overwrite_existing']);
                $results['imported'][] = 'performance_settings';
            }
            
            if ($import_options['alert_settings'] && isset($import_data['settings']['alerts'])) {
                self::import_alert_settings($import_data['settings']['alerts'], $import_options['overwrite_existing']);
                $results['imported'][] = 'alert_settings';
            }
            
            if ($import_options['custom_data'] && isset($import_data['custom_data'])) {
                self::import_custom_data($import_data['custom_data'], $import_options['overwrite_existing']);
                $results['imported'][] = 'custom_data';
            }
            
            $results['success'] = true;
            $results['message'] = 'Settings imported successfully';
            
        } catch (Exception $e) {
            $results['message'] = 'Import failed: ' . $e->getMessage();
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Validate import data structure
     */
    private static function validate_import_data($import_data) {
        $required_fields = array('export_timestamp', 'plugin_version', 'settings');
        
        foreach ($required_fields as $field) {
            if (!isset($import_data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Import plugin settings
     */
    private static function import_plugin_settings($settings, $overwrite = true) {
        if ($overwrite || !get_option('fp_digital_marketing_settings')) {
            update_option('fp_digital_marketing_settings', $settings);
        }
    }
    
    /**
     * Import integration settings
     */
    private static function import_integration_settings($integrations, $overwrite = true) {
        $option_mapping = array(
            'google_analytics' => 'fp_google_analytics_settings',
            'google_ads' => 'fp_google_ads_settings',
            'search_console' => 'fp_search_console_settings',
            'clarity' => 'fp_clarity_settings'
        );
        
        foreach ($integrations as $integration => $settings) {
            if (isset($option_mapping[$integration])) {
                $option_name = $option_mapping[$integration];
                
                if ($overwrite || !get_option($option_name)) {
                    update_option($option_name, $settings);
                }
            }
        }
    }
    
    /**
     * Import SEO settings
     */
    private static function import_seo_settings($seo_settings, $overwrite = true) {
        $option_mapping = array(
            'seo_settings' => 'fp_seo_settings',
            'sitemap_settings' => 'fp_sitemap_settings',
            'schema_settings' => 'fp_schema_settings'
        );
        
        foreach ($seo_settings as $setting_type => $settings) {
            if (isset($option_mapping[$setting_type])) {
                $option_name = $option_mapping[$setting_type];
                
                if ($overwrite || !get_option($option_name)) {
                    update_option($option_name, $settings);
                }
            }
        }
    }
    
    /**
     * Import performance settings
     */
    private static function import_performance_settings($performance_settings, $overwrite = true) {
        $option_mapping = array(
            'performance_settings' => 'fp_performance_settings',
            'cache_settings' => 'fp_cache_settings',
            'optimization_settings' => 'fp_optimization_settings'
        );
        
        foreach ($performance_settings as $setting_type => $settings) {
            if (isset($option_mapping[$setting_type])) {
                $option_name = $option_mapping[$setting_type];
                
                if ($overwrite || !get_option($option_name)) {
                    update_option($option_name, $settings);
                }
            }
        }
    }
    
    /**
     * Import alert settings
     */
    private static function import_alert_settings($alert_settings, $overwrite = true) {
        $option_mapping = array(
            'alert_settings' => 'fp_alert_settings',
            'notification_settings' => 'fp_notification_settings',
            'threshold_settings' => 'fp_threshold_settings'
        );
        
        foreach ($alert_settings as $setting_type => $settings) {
            if (isset($option_mapping[$setting_type])) {
                $option_name = $option_mapping[$setting_type];
                
                if ($overwrite || !get_option($option_name)) {
                    update_option($option_name, $settings);
                }
            }
        }
    }
    
    /**
     * Import custom data
     */
    private static function import_custom_data($custom_data, $overwrite = true) {
        global $wpdb;
        
        // Import UTM campaigns
        if (isset($custom_data['utm_campaigns'])) {
            $table_name = $wpdb->prefix . 'fp_utm_campaigns';
            
            if ($overwrite) {
                $wpdb->query("TRUNCATE TABLE {$table_name}");
            }
            
            foreach ($custom_data['utm_campaigns'] as $campaign) {
                unset($campaign['id']); // Remove ID to avoid conflicts
                $wpdb->insert($table_name, $campaign);
            }
        }
        
        // Import conversion events
        if (isset($custom_data['conversion_events'])) {
            $table_name = $wpdb->prefix . 'fp_conversion_events';
            
            if ($overwrite) {
                $wpdb->query("TRUNCATE TABLE {$table_name}");
            }
            
            foreach ($custom_data['conversion_events'] as $event) {
                unset($event['id']); // Remove ID to avoid conflicts
                $wpdb->insert($table_name, $event);
            }
        }
    }
    
    /**
     * Create settings template for new installations
     * 
     * @param string $template_type Type of template (basic, agency, enterprise)
     * @return array Template settings
     */
    public static function create_settings_template($template_type = 'basic') {
        $templates = array(
            'basic' => self::get_basic_template(),
            'agency' => self::get_agency_template(),
            'enterprise' => self::get_enterprise_template()
        );
        
        return $templates[$template_type] ?? $templates['basic'];
    }
    
    /**
     * Basic template settings
     */
    private static function get_basic_template() {
        return array(
            'plugin_settings' => array(
                'enable_analytics' => true,
                'enable_seo' => true,
                'enable_performance' => true,
                'enable_alerts' => false
            ),
            'integrations' => array(
                'google_analytics' => array('enabled' => false),
                'google_ads' => array('enabled' => false),
                'search_console' => array('enabled' => false),
                'clarity' => array('enabled' => false)
            ),
            'seo_settings' => array(
                'enable_meta_optimization' => true,
                'enable_sitemap' => true,
                'enable_schema' => false
            ),
            'performance_settings' => array(
                'enable_monitoring' => true,
                'enable_optimization' => false,
                'cache_duration' => 3600
            )
        );
    }
    
    /**
     * Agency template settings
     */
    private static function get_agency_template() {
        return array(
            'plugin_settings' => array(
                'enable_analytics' => true,
                'enable_seo' => true,
                'enable_performance' => true,
                'enable_alerts' => true,
                'white_label_mode' => true
            ),
            'integrations' => array(
                'google_analytics' => array('enabled' => true),
                'google_ads' => array('enabled' => true),
                'search_console' => array('enabled' => true),
                'clarity' => array('enabled' => false)
            ),
            'seo_settings' => array(
                'enable_meta_optimization' => true,
                'enable_sitemap' => true,
                'enable_schema' => true,
                'enable_breadcrumbs' => true
            ),
            'performance_settings' => array(
                'enable_monitoring' => true,
                'enable_optimization' => true,
                'cache_duration' => 1800,
                'enable_core_web_vitals' => true
            ),
            'alert_settings' => array(
                'enable_email_alerts' => true,
                'alert_frequency' => 'daily',
                'performance_thresholds' => true
            )
        );
    }
    
    /**
     * Enterprise template settings
     */
    private static function get_enterprise_template() {
        return array(
            'plugin_settings' => array(
                'enable_analytics' => true,
                'enable_seo' => true,
                'enable_performance' => true,
                'enable_alerts' => true,
                'white_label_mode' => true,
                'enable_multisite' => true
            ),
            'integrations' => array(
                'google_analytics' => array('enabled' => true),
                'google_ads' => array('enabled' => true),
                'search_console' => array('enabled' => true),
                'clarity' => array('enabled' => true)
            ),
            'seo_settings' => array(
                'enable_meta_optimization' => true,
                'enable_sitemap' => true,
                'enable_schema' => true,
                'enable_breadcrumbs' => true,
                'enable_advanced_seo' => true
            ),
            'performance_settings' => array(
                'enable_monitoring' => true,
                'enable_optimization' => true,
                'cache_duration' => 900,
                'enable_core_web_vitals' => true,
                'enable_database_optimization' => true
            ),
            'alert_settings' => array(
                'enable_email_alerts' => true,
                'enable_slack_alerts' => true,
                'alert_frequency' => 'real-time',
                'performance_thresholds' => true,
                'security_alerts' => true
            )
        );
    }
}

// AJAX handlers for settings export/import
add_action('wp_ajax_fp_export_settings', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $export_options = array(
        'include_api_keys' => $_POST['include_api_keys'] === 'true',
        'custom_data' => $_POST['custom_data'] === 'true'
    );
    
    $settings = FP_Settings_Manager::export_settings($export_options);
    
    // Create download file
    $filename = 'fp-settings-' . date('Y-m-d-H-i-s') . '.json';
    $json_data = wp_json_encode($settings, JSON_PRETTY_PRINT);
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json_data));
    
    echo $json_data;
    exit;
});

add_action('wp_ajax_fp_import_settings', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!isset($_FILES['settings_file'])) {
        wp_send_json_error('No file uploaded');
    }
    
    $file_content = file_get_contents($_FILES['settings_file']['tmp_name']);
    $import_data = json_decode($file_content, true);
    
    if (!$import_data) {
        wp_send_json_error('Invalid file format');
    }
    
    $import_options = array(
        'overwrite_existing' => $_POST['overwrite_existing'] === 'true'
    );
    
    $results = FP_Settings_Manager::import_settings($import_data, $import_options);
    
    if ($results['success']) {
        wp_send_json_success($results);
    } else {
        wp_send_json_error($results);
    }
});

// CLI commands for settings management
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('fp settings export', function($args, $assoc_args) {
        $export_options = array(
            'include_api_keys' => isset($assoc_args['include-api-keys']),
            'custom_data' => isset($assoc_args['include-data'])
        );
        
        $settings = FP_Settings_Manager::export_settings($export_options);
        $filename = $assoc_args['file'] ?? 'fp-settings-export.json';
        
        $json_data = wp_json_encode($settings, JSON_PRETTY_PRINT);
        file_put_contents($filename, $json_data);
        
        WP_CLI::success("Settings exported to: {$filename}");
    });
    
    WP_CLI::add_command('fp settings import', function($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify settings file to import");
        }
        
        $file_path = $args[0];
        
        if (!file_exists($file_path)) {
            WP_CLI::error("File not found: {$file_path}");
        }
        
        $file_content = file_get_contents($file_path);
        $import_data = json_decode($file_content, true);
        
        if (!$import_data) {
            WP_CLI::error("Invalid file format");
        }
        
        $import_options = array(
            'overwrite_existing' => !isset($assoc_args['no-overwrite'])
        );
        
        $results = FP_Settings_Manager::import_settings($import_data, $import_options);
        
        if ($results['success']) {
            WP_CLI::success($results['message']);
            WP_CLI::log("Imported: " . implode(', ', $results['imported']));
        } else {
            WP_CLI::error($results['message']);
        }
    });
    
    WP_CLI::add_command('fp settings template', function($args, $assoc_args) {
        $template_type = $args[0] ?? 'basic';
        $output_file = $assoc_args['output'] ?? "fp-template-{$template_type}.json";
        
        $template = FP_Settings_Manager::create_settings_template($template_type);
        
        $json_data = wp_json_encode($template, JSON_PRETTY_PRINT);
        file_put_contents($output_file, $json_data);
        
        WP_CLI::success("Template created: {$output_file}");
    });
}
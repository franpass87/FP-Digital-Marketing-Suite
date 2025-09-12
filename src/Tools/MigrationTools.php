<?php
/**
 * Migration Tools for FP Digital Marketing Suite
 *
 * Provides tools for migrating data from other marketing platforms
 * and WordPress plugins to FP Digital Marketing Suite.
 *
 * @package FP\DigitalMarketing\Tools
 * @version 1.0.0
 */

namespace FP\DigitalMarketing\Tools;

use FP\DigitalMarketing\Client\ClientManager;
use FP\DigitalMarketing\Analytics\GoogleAnalytics4;
use FP\DigitalMarketing\Helpers\MetricsAggregator;

/**
 * Migration Tools Class
 */
class MigrationTools {
    
    /**
     * Supported migration sources
     */
    const SUPPORTED_SOURCES = [
        'google_analytics_ua' => 'Google Analytics Universal Analytics',
        'yoast_seo' => 'Yoast SEO',
        'rankmath' => 'RankMath SEO',
        'all_in_one_seo' => 'All in One SEO',
        'monster_insights' => 'MonsterInsights',
        'google_site_kit' => 'Google Site Kit',
        'csv_import' => 'CSV File Import',
        'jetpack' => 'Jetpack Analytics'
    ];
    
    /**
     * Migration status
     */
    private $migration_log = [];
    
    /**
     * Get available migration sources
     */
    public function get_available_sources(): array {
        $available = [];
        
        foreach (self::SUPPORTED_SOURCES as $key => $name) {
            $available[$key] = [
                'name' => $name,
                'available' => $this->check_source_availability($key),
                'description' => $this->get_source_description($key)
            ];
        }
        
        return $available;
    }
    
    /**
     * Check if migration source is available
     */
    private function check_source_availability(string $source): bool {
        switch ($source) {
            case 'yoast_seo':
                return class_exists('WPSEO_Options');
                
            case 'rankmath':
                return class_exists('RankMath');
                
            case 'all_in_one_seo':
                return class_exists('AIOSEO\Plugin\AIOSEO');
                
            case 'monster_insights':
                return class_exists('MonsterInsights');
                
            case 'google_site_kit':
                return class_exists('Google\Site_Kit\Plugin');
                
            case 'jetpack':
                return class_exists('Jetpack');
                
            case 'google_analytics_ua':
            case 'csv_import':
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Get source description
     */
    private function get_source_description(string $source): string {
        $descriptions = [
            'google_analytics_ua' => 'Migrate historical data from Google Analytics Universal Analytics',
            'yoast_seo' => 'Import SEO settings, meta data, and configuration from Yoast SEO',
            'rankmath' => 'Import SEO settings, meta data, and configuration from RankMath',
            'all_in_one_seo' => 'Import SEO settings and meta data from All in One SEO',
            'monster_insights' => 'Import Google Analytics configuration and settings',
            'google_site_kit' => 'Import Google services configuration and data',
            'csv_import' => 'Import client data and metrics from CSV files',
            'jetpack' => 'Import analytics configuration and site statistics'
        ];
        
        return $descriptions[$source] ?? 'Migration from ' . $source;
    }
    
    /**
     * Start migration process
     */
    public function start_migration(string $source, array $options = []): array {
        $this->log("Starting migration from {$source}");
        
        if (!$this->check_source_availability($source)) {
            return $this->error("Migration source {$source} is not available");
        }
        
        switch ($source) {
            case 'yoast_seo':
                return $this->migrate_from_yoast($options);
                
            case 'rankmath':
                return $this->migrate_from_rankmath($options);
                
            case 'all_in_one_seo':
                return $this->migrate_from_aioseo($options);
                
            case 'monster_insights':
                return $this->migrate_from_monster_insights($options);
                
            case 'google_site_kit':
                return $this->migrate_from_site_kit($options);
                
            case 'google_analytics_ua':
                return $this->migrate_from_ga_ua($options);
                
            case 'csv_import':
                return $this->migrate_from_csv($options);
                
            case 'jetpack':
                return $this->migrate_from_jetpack($options);
                
            default:
                return $this->error("Unsupported migration source: {$source}");
        }
    }
    
    /**
     * Migrate from Yoast SEO
     */
    private function migrate_from_yoast(array $options): array {
        $this->log("Migrating from Yoast SEO");
        
        $migrated = [
            'posts' => 0,
            'settings' => 0,
            'redirects' => 0
        ];
        
        try {
            // Migrate global SEO settings
            $yoast_options = get_option('wpseo');
            if ($yoast_options) {
                $fp_settings = [];
                
                // Map Yoast settings to FP DMS settings
                if (isset($yoast_options['company_name'])) {
                    $fp_settings['company_name'] = $yoast_options['company_name'];
                }
                
                if (isset($yoast_options['company_logo'])) {
                    $fp_settings['company_logo'] = $yoast_options['company_logo'];
                }
                
                if (isset($yoast_options['website_name'])) {
                    $fp_settings['site_name'] = $yoast_options['website_name'];
                }
                
                // Save migrated settings
                foreach ($fp_settings as $key => $value) {
                    update_option("fp_dms_seo_{$key}", $value);
                }
                
                $migrated['settings'] = count($fp_settings);
                $this->log("Migrated {$migrated['settings']} SEO settings");
            }
            
            // Migrate post meta data
            $posts = get_posts([
                'post_type' => 'any',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_yoast_wpseo_title',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            foreach ($posts as $post) {
                $yoast_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
                $yoast_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
                $yoast_keywords = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
                
                if ($yoast_title) {
                    update_post_meta($post->ID, '_fp_dms_seo_title', $yoast_title);
                }
                
                if ($yoast_desc) {
                    update_post_meta($post->ID, '_fp_dms_seo_description', $yoast_desc);
                }
                
                if ($yoast_keywords) {
                    update_post_meta($post->ID, '_fp_dms_seo_keywords', $yoast_keywords);
                }
                
                $migrated['posts']++;
            }
            
            $this->log("Migrated SEO data for {$migrated['posts']} posts");
            
            return $this->success($migrated);
            
        } catch (Exception $e) {
            return $this->error("Yoast migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate from RankMath
     */
    private function migrate_from_rankmath(array $options): array {
        $this->log("Migrating from RankMath");
        
        $migrated = [
            'posts' => 0,
            'settings' => 0
        ];
        
        try {
            // Migrate RankMath settings
            $rm_options = get_option('rank_math_options_general');
            if ($rm_options) {
                $fp_settings = [];
                
                if (isset($rm_options['company_name'])) {
                    $fp_settings['company_name'] = $rm_options['company_name'];
                }
                
                if (isset($rm_options['company_logo'])) {
                    $fp_settings['company_logo'] = $rm_options['company_logo'];
                }
                
                foreach ($fp_settings as $key => $value) {
                    update_option("fp_dms_seo_{$key}", $value);
                }
                
                $migrated['settings'] = count($fp_settings);
            }
            
            // Migrate post meta
            $posts = get_posts([
                'post_type' => 'any',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'rank_math_title',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            foreach ($posts as $post) {
                $rm_title = get_post_meta($post->ID, 'rank_math_title', true);
                $rm_desc = get_post_meta($post->ID, 'rank_math_description', true);
                $rm_keywords = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
                
                if ($rm_title) {
                    update_post_meta($post->ID, '_fp_dms_seo_title', $rm_title);
                }
                
                if ($rm_desc) {
                    update_post_meta($post->ID, '_fp_dms_seo_description', $rm_desc);
                }
                
                if ($rm_keywords) {
                    update_post_meta($post->ID, '_fp_dms_seo_keywords', $rm_keywords);
                }
                
                $migrated['posts']++;
            }
            
            return $this->success($migrated);
            
        } catch (Exception $e) {
            return $this->error("RankMath migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate from All in One SEO
     */
    private function migrate_from_aioseo(array $options): array {
        $this->log("Migrating from All in One SEO");
        
        $migrated = [
            'posts' => 0,
            'settings' => 0
        ];
        
        try {
            // Migrate AIOSEO settings
            $aioseo_options = get_option('aioseo_options');
            if ($aioseo_options && is_array($aioseo_options)) {
                $fp_settings = [];
                
                if (isset($aioseo_options['general']['schema']['organization']['name'])) {
                    $fp_settings['company_name'] = $aioseo_options['general']['schema']['organization']['name'];
                }
                
                if (isset($aioseo_options['general']['schema']['organization']['logo'])) {
                    $fp_settings['company_logo'] = $aioseo_options['general']['schema']['organization']['logo'];
                }
                
                foreach ($fp_settings as $key => $value) {
                    update_option("fp_dms_seo_{$key}", $value);
                }
                
                $migrated['settings'] = count($fp_settings);
            }
            
            // Migrate post meta
            $posts = get_posts([
                'post_type' => 'any',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_aioseo_title',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            foreach ($posts as $post) {
                $aioseo_title = get_post_meta($post->ID, '_aioseo_title', true);
                $aioseo_desc = get_post_meta($post->ID, '_aioseo_description', true);
                $aioseo_keywords = get_post_meta($post->ID, '_aioseo_keywords', true);
                
                if ($aioseo_title) {
                    update_post_meta($post->ID, '_fp_dms_seo_title', $aioseo_title);
                }
                
                if ($aioseo_desc) {
                    update_post_meta($post->ID, '_fp_dms_seo_description', $aioseo_desc);
                }
                
                if ($aioseo_keywords) {
                    update_post_meta($post->ID, '_fp_dms_seo_keywords', $aioseo_keywords);
                }
                
                $migrated['posts']++;
            }
            
            return $this->success($migrated);
            
        } catch (Exception $e) {
            return $this->error("All in One SEO migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate from MonsterInsights
     */
    private function migrate_from_monster_insights(array $options): array {
        $this->log("Migrating from MonsterInsights");
        
        try {
            $mi_settings = get_option('monsterinsights_settings');
            $migrated_settings = 0;
            
            if ($mi_settings && is_array($mi_settings)) {
                // Migrate Google Analytics settings
                if (isset($mi_settings['analytics_profile'])) {
                    update_option('fp_dms_analytics_ga4_measurement_id', $mi_settings['analytics_profile']);
                    $migrated_settings++;
                }
                
                if (isset($mi_settings['manual_ua_code'])) {
                    update_option('fp_dms_analytics_ua_tracking_id', $mi_settings['manual_ua_code']);
                    $migrated_settings++;
                }
                
                // Migrate tracking settings
                if (isset($mi_settings['track_user'])) {
                    update_option('fp_dms_analytics_track_users', $mi_settings['track_user']);
                    $migrated_settings++;
                }
            }
            
            return $this->success(['settings' => $migrated_settings]);
            
        } catch (Exception $e) {
            return $this->error("MonsterInsights migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate from Google Site Kit
     */
    private function migrate_from_site_kit(array $options): array {
        $this->log("Migrating from Google Site Kit");
        
        try {
            $migrated_settings = 0;
            
            // Migrate Analytics settings
            $analytics_settings = get_option('googlesitekit_analytics_settings');
            if ($analytics_settings && isset($analytics_settings['trackingID'])) {
                update_option('fp_dms_analytics_ga4_measurement_id', $analytics_settings['trackingID']);
                $migrated_settings++;
            }
            
            // Migrate Search Console settings
            $search_console_settings = get_option('googlesitekit_search-console_settings');
            if ($search_console_settings && isset($search_console_settings['propertyID'])) {
                update_option('fp_dms_gsc_property_url', $search_console_settings['propertyID']);
                $migrated_settings++;
            }
            
            // Migrate AdSense settings
            $adsense_settings = get_option('googlesitekit_adsense_settings');
            if ($adsense_settings && isset($adsense_settings['clientID'])) {
                update_option('fp_dms_adsense_client_id', $adsense_settings['clientID']);
                $migrated_settings++;
            }
            
            return $this->success(['settings' => $migrated_settings]);
            
        } catch (Exception $e) {
            return $this->error("Google Site Kit migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate from Google Analytics UA
     */
    private function migrate_from_ga_ua(array $options): array {
        $this->log("Migrating from Google Analytics Universal Analytics");
        
        if (!isset($options['ua_property_id']) || !isset($options['date_range'])) {
            return $this->error("UA Property ID and date range are required");
        }
        
        try {
            // This would require Google Analytics Reporting API
            // For now, provide structure for future implementation
            $migrated_data = [
                'sessions' => 0,
                'users' => 0,
                'pageviews' => 0,
                'date_range' => $options['date_range']
            ];
            
            // Note: GA UA API migration requires Google Analytics Reporting API integration
            // This placeholder provides structure for future GA UA historical data migration
            $this->log("GA UA migration placeholder - ready for Google Analytics Reporting API integration");
            
            return $this->success($migrated_data);
            
        } catch (Exception $e) {
            return $this->error("GA UA migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate from CSV files
     */
    private function migrate_from_csv(array $options): array {
        $this->log("Migrating from CSV file");
        
        if (!isset($options['csv_file']) || !file_exists($options['csv_file'])) {
            return $this->error("CSV file not found");
        }
        
        try {
            $csv_data = array_map('str_getcsv', file($options['csv_file']));
            $headers = array_shift($csv_data);
            
            $migrated = [
                'clients' => 0,
                'metrics' => 0
            ];
            
            foreach ($csv_data as $row) {
                $data = array_combine($headers, $row);
                
                // Create client if data contains client information
                if (isset($data['client_name']) && isset($data['client_email'])) {
                    $client_id = ClientManager::create_client([
                        'name' => $data['client_name'],
                        'email' => $data['client_email'],
                        'website' => $data['client_website'] ?? '',
                        'industry' => $data['client_industry'] ?? ''
                    ]);
                    
                    if ($client_id) {
                        $migrated['clients']++;
                        
                        // Add metrics if available
                        $metrics = ['sessions', 'users', 'pageviews', 'revenue'];
                        foreach ($metrics as $metric) {
                            if (isset($data[$metric]) && is_numeric($data[$metric])) {
                                MetricsAggregator::store_metric([
                                    'client_id' => $client_id,
                                    'kpi' => $metric,
                                    'value' => floatval($data[$metric]),
                                    'source' => 'csv_import',
                                    'source_type' => 'import',
                                    'category' => 'traffic'
                                ]);
                                $migrated['metrics']++;
                            }
                        }
                    }
                }
            }
            
            return $this->success($migrated);
            
        } catch (Exception $e) {
            return $this->error("CSV migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate from Jetpack
     */
    private function migrate_from_jetpack(array $options): array {
        $this->log("Migrating from Jetpack");
        
        try {
            $jetpack_options = get_option('jetpack_options');
            $migrated_settings = 0;
            
            if ($jetpack_options && is_array($jetpack_options)) {
                // Migrate Google Analytics code if set
                if (isset($jetpack_options['google_analytics_tracking_id'])) {
                    update_option('fp_dms_analytics_ga4_measurement_id', $jetpack_options['google_analytics_tracking_id']);
                    $migrated_settings++;
                }
            }
            
            return $this->success(['settings' => $migrated_settings]);
            
        } catch (Exception $e) {
            return $this->error("Jetpack migration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get migration status
     */
    public function get_migration_status(): array {
        return [
            'log' => $this->migration_log,
            'timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Clear migration log
     */
    public function clear_migration_log(): void {
        $this->migration_log = [];
    }
    
    /**
     * Export current FP DMS data for backup
     */
    public function export_fp_dms_data(): array {
        try {
            $export_data = [
                'clients' => ClientManager::get_all_clients(),
                'settings' => $this->get_all_fp_dms_settings(),
                'metrics' => $this->get_recent_metrics(),
                'export_timestamp' => current_time('mysql'),
                'version' => get_option('fp_dms_version', '1.0.0')
            ];
            
            return $this->success($export_data);
            
        } catch (Exception $e) {
            return $this->error("Export failed: " . $e->getMessage());
        }
    }
    
    /**
     * Import FP DMS data from backup
     */
    public function import_fp_dms_data(array $data): array {
        try {
            $imported = [
                'clients' => 0,
                'settings' => 0,
                'metrics' => 0
            ];
            
            // Import clients
            if (isset($data['clients']) && is_array($data['clients'])) {
                foreach ($data['clients'] as $client) {
                    $client_id = ClientManager::create_client($client);
                    if ($client_id) {
                        $imported['clients']++;
                    }
                }
            }
            
            // Import settings
            if (isset($data['settings']) && is_array($data['settings'])) {
                foreach ($data['settings'] as $key => $value) {
                    update_option($key, $value);
                    $imported['settings']++;
                }
            }
            
            // Import metrics
            if (isset($data['metrics']) && is_array($data['metrics'])) {
                foreach ($data['metrics'] as $metric) {
                    MetricsAggregator::store_metric($metric);
                    $imported['metrics']++;
                }
            }
            
            return $this->success($imported);
            
        } catch (Exception $e) {
            return $this->error("Import failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get all FP DMS settings
     */
    private function get_all_fp_dms_settings(): array {
        global $wpdb;
        
        $settings = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE 'fp_dms_%'",
            ARRAY_A
        );
        
        $settings_array = [];
        foreach ($settings as $setting) {
            $settings_array[$setting['option_name']] = maybe_unserialize($setting['option_value']);
        }
        
        return $settings_array;
    }
    
    /**
     * Get recent metrics for export
     */
    private function get_recent_metrics(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_dms_metrics';
        
        // Get metrics from last 90 days
        $metrics = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT client_id, kpi, value, source, source_type, category, date_recorded 
                 FROM {$table_name} 
                 WHERE date_recorded >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                 ORDER BY date_recorded DESC
                 LIMIT 1000"
            ),
            ARRAY_A
        );
        
        return $metrics ?: [];
    }
    
    /**
     * Log migration message
     */
    private function log(string $message): void {
        $this->migration_log[] = [
            'timestamp' => current_time('mysql'),
            'message' => $message
        ];
    }
    
    /**
     * Return success response
     */
    private function success(array $data): array {
        return [
            'success' => true,
            'data' => $data,
            'log' => $this->migration_log
        ];
    }
    
    /**
     * Return error response
     */
    private function error(string $message): array {
        $this->log("ERROR: {$message}");
        
        return [
            'success' => false,
            'error' => $message,
            'log' => $this->migration_log
        ];
    }
}
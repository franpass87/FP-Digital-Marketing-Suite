<?php
/**
 * Plugin Compatibility Checker
 * 
 * Detect potential conflicts with other WordPress plugins
 * 
 * @package FP_Digital_Marketing_Suite
 * @subpackage Tools
 * @since 1.0.0
 */

class FP_Plugin_Compatibility_Checker {
    
    /**
     * Known plugin conflicts and compatibility issues
     */
    private static $known_conflicts = array(
        'seo_plugins' => array(
            'wordpress-seo/wp-seo.php' => array(
                'name' => 'Yoast SEO',
                'conflict_level' => 'medium',
                'issues' => array(
                    'Meta tag duplication',
                    'Sitemap conflicts',
                    'Schema markup overlap'
                ),
                'solutions' => array(
                    'Disable FP SEO features if Yoast is primary',
                    'Use migration tool to import Yoast data',
                    'Configure exclusion rules'
                )
            ),
            'seo-by-rank-math/rank-math.php' => array(
                'name' => 'Rank Math SEO',
                'conflict_level' => 'medium',
                'issues' => array(
                    'Schema conflicts',
                    'Meta optimization overlap',
                    'Breadcrumb duplication'
                ),
                'solutions' => array(
                    'Migrate from Rank Math using built-in tools',
                    'Disable conflicting modules',
                    'Use Rank Math API integration'
                )
            ),
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => array(
                'name' => 'All in One SEO',
                'conflict_level' => 'low',
                'issues' => array(
                    'Meta tag conflicts'
                ),
                'solutions' => array(
                    'Import AIOSEO settings',
                    'Disable AIOSEO meta features'
                )
            )
        ),
        'analytics_plugins' => array(
            'google-analytics-for-wordpress/googleanalytics.php' => array(
                'name' => 'MonsterInsights',
                'conflict_level' => 'high',
                'issues' => array(
                    'Duplicate tracking codes',
                    'Event tracking conflicts',
                    'Performance impact'
                ),
                'solutions' => array(
                    'Use migration tool to import settings',
                    'Disable MonsterInsights tracking',
                    'Configure event exclusions'
                )
            ),
            'google-site-kit/google-site-kit.php' => array(
                'name' => 'Google Site Kit',
                'conflict_level' => 'medium',
                'issues' => array(
                    'API quota conflicts',
                    'Duplicate data requests',
                    'Authentication conflicts'
                ),
                'solutions' => array(
                    'Import Site Kit configuration',
                    'Use shared authentication',
                    'Configure API rate limiting'
                )
            )
        ),
        'caching_plugins' => array(
            'wp-rocket/wp-rocket.php' => array(
                'name' => 'WP Rocket',
                'conflict_level' => 'low',
                'issues' => array(
                    'Cache conflicts with dynamic content',
                    'JavaScript optimization issues'
                ),
                'solutions' => array(
                    'Add exclusion rules for FP scripts',
                    'Configure cache compatibility mode'
                )
            ),
            'w3-total-cache/w3-total-cache.php' => array(
                'name' => 'W3 Total Cache',
                'conflict_level' => 'medium',
                'issues' => array(
                    'Database cache conflicts',
                    'Object cache issues'
                ),
                'solutions' => array(
                    'Configure cache exclusions',
                    'Use compatible cache groups'
                )
            ),
            'wp-super-cache/wp-cache.php' => array(
                'name' => 'WP Super Cache',
                'conflict_level' => 'low',
                'issues' => array(
                    'Static cache conflicts with analytics'
                ),
                'solutions' => array(
                    'Exclude analytics pages from cache',
                    'Use dynamic content handling'
                )
            )
        ),
        'security_plugins' => array(
            'wordfence/wordfence.php' => array(
                'name' => 'Wordfence Security',
                'conflict_level' => 'low',
                'issues' => array(
                    'Firewall blocking API requests',
                    'Scan false positives'
                ),
                'solutions' => array(
                    'Whitelist FP API endpoints',
                    'Configure scan exclusions'
                )
            ),
            'better-wp-security/better-wp-security.php' => array(
                'name' => 'iThemes Security',
                'conflict_level' => 'low',
                'issues' => array(
                    'File change detection alerts',
                    'Login security conflicts'
                ),
                'solutions' => array(
                    'Add file exclusions',
                    'Configure authentication bypass'
                )
            )
        ),
        'performance_plugins' => array(
            'autoptimize/autoptimize.php' => array(
                'name' => 'Autoptimize',
                'conflict_level' => 'medium',
                'issues' => array(
                    'Script optimization breaking analytics',
                    'CSS optimization affecting admin styles'
                ),
                'solutions' => array(
                    'Exclude FP scripts from optimization',
                    'Use defer loading for analytics'
                )
            ),
            'wp-optimize/wp-optimize.php' => array(
                'name' => 'WP-Optimize',
                'conflict_level' => 'low',
                'issues' => array(
                    'Database optimization conflicts',
                    'Cache clearing issues'
                ),
                'solutions' => array(
                    'Exclude FP tables from optimization',
                    'Coordinate cache clearing'
                )
            )
        )
    );
    
    /**
     * Run comprehensive compatibility check
     * 
     * @return array Compatibility report
     */
    public static function run_compatibility_check() {
        $report = array(
            'overall_status' => 'compatible',
            'timestamp' => current_time('mysql'),
            'conflicts' => array(),
            'warnings' => array(),
            'recommendations' => array(),
            'active_plugins' => get_option('active_plugins', array())
        );
        
        $active_plugins = get_option('active_plugins', array());
        
        // Check each category of known conflicts
        foreach (self::$known_conflicts as $category => $plugins) {
            foreach ($plugins as $plugin_path => $plugin_info) {
                if (in_array($plugin_path, $active_plugins)) {
                    $conflict = array(
                        'category' => $category,
                        'plugin_path' => $plugin_path,
                        'plugin_name' => $plugin_info['name'],
                        'conflict_level' => $plugin_info['conflict_level'],
                        'issues' => $plugin_info['issues'],
                        'solutions' => $plugin_info['solutions'],
                        'detected_issues' => self::detect_specific_conflicts($plugin_path)
                    );
                    
                    if ($plugin_info['conflict_level'] === 'high') {
                        $report['conflicts'][] = $conflict;
                        $report['overall_status'] = 'conflicts_detected';
                    } elseif ($plugin_info['conflict_level'] === 'medium') {
                        $report['warnings'][] = $conflict;
                        if ($report['overall_status'] === 'compatible') {
                            $report['overall_status'] = 'warnings_present';
                        }
                    } else {
                        $report['recommendations'][] = $conflict;
                    }
                }
            }
        }
        
        // Check for unknown plugin conflicts
        $unknown_conflicts = self::detect_unknown_conflicts($active_plugins);
        if (!empty($unknown_conflicts)) {
            $report['unknown_conflicts'] = $unknown_conflicts;
        }
        
        // Add general recommendations
        $report['general_recommendations'] = self::get_general_recommendations();
        
        return $report;
    }
    
    /**
     * Detect specific conflicts for a known plugin
     */
    private static function detect_specific_conflicts($plugin_path) {
        $detected = array();
        
        switch ($plugin_path) {
            case 'wordpress-seo/wp-seo.php':
                // Check for Yoast-specific conflicts
                if (class_exists('WPSEO_Options')) {
                    $yoast_options = WPSEO_Options::get_instance();
                    if ($yoast_options->get('enable_xml_sitemap')) {
                        $detected[] = 'XML Sitemap enabled in Yoast';
                    }
                    if ($yoast_options->get('opengraph')) {
                        $detected[] = 'OpenGraph enabled in Yoast';
                    }
                }
                break;
                
            case 'google-analytics-for-wordpress/googleanalytics.php':
                // Check for MonsterInsights conflicts
                if (class_exists('MonsterInsights')) {
                    $mi_settings = get_option('monsterinsights_settings', array());
                    if (!empty($mi_settings['manual_ua_code'])) {
                        $detected[] = 'Analytics tracking code configured';
                    }
                }
                break;
                
            case 'wp-rocket/wp-rocket.php':
                // Check WP Rocket configuration
                if (function_exists('get_rocket_option')) {
                    if (get_rocket_option('minify_js')) {
                        $detected[] = 'JavaScript minification enabled';
                    }
                    if (get_rocket_option('defer_all_js')) {
                        $detected[] = 'JavaScript defer enabled';
                    }
                }
                break;
        }
        
        return $detected;
    }
    
    /**
     * Detect unknown plugin conflicts
     */
    private static function detect_unknown_conflicts($active_plugins) {
        $unknown_conflicts = array();
        
        foreach ($active_plugins as $plugin_path) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path, false, false);
            
            // Check for potential SEO plugin conflicts
            if (self::is_likely_seo_plugin($plugin_data)) {
                $unknown_conflicts[] = array(
                    'plugin_path' => $plugin_path,
                    'plugin_name' => $plugin_data['Name'],
                    'conflict_type' => 'seo',
                    'reason' => 'Plugin appears to be an SEO tool'
                );
            }
            
            // Check for analytics plugin conflicts
            if (self::is_likely_analytics_plugin($plugin_data)) {
                $unknown_conflicts[] = array(
                    'plugin_path' => $plugin_path,
                    'plugin_name' => $plugin_data['Name'],
                    'conflict_type' => 'analytics',
                    'reason' => 'Plugin appears to be an analytics tool'
                );
            }
        }
        
        return $unknown_conflicts;
    }
    
    /**
     * Check if plugin is likely an SEO plugin
     */
    private static function is_likely_seo_plugin($plugin_data) {
        $seo_keywords = array('seo', 'search engine', 'meta', 'sitemap', 'schema', 'breadcrumb');
        $text_to_check = strtolower($plugin_data['Name'] . ' ' . $plugin_data['Description']);
        
        foreach ($seo_keywords as $keyword) {
            if (strpos($text_to_check, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if plugin is likely an analytics plugin
     */
    private static function is_likely_analytics_plugin($plugin_data) {
        $analytics_keywords = array('analytics', 'tracking', 'google analytics', 'statistics', 'stats');
        $text_to_check = strtolower($plugin_data['Name'] . ' ' . $plugin_data['Description']);
        
        foreach ($analytics_keywords as $keyword) {
            if (strpos($text_to_check, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get general compatibility recommendations
     */
    private static function get_general_recommendations() {
        return array(
            'Use FP migration tools to import settings from other plugins',
            'Test thoroughly in staging environment before disabling other plugins',
            'Monitor performance after making changes',
            'Keep backup of current configuration',
            'Review plugin load order for optimal performance',
            'Configure cache exclusions for dynamic content',
            'Set up proper error monitoring',
            'Document any custom configurations'
        );
    }
    
    /**
     * Generate compatibility report for specific plugin
     */
    public static function check_single_plugin($plugin_path) {
        $active_plugins = get_option('active_plugins', array());
        
        if (!in_array($plugin_path, $active_plugins)) {
            return array(
                'status' => 'not_active',
                'message' => 'Plugin is not currently active'
            );
        }
        
        // Check in known conflicts
        foreach (self::$known_conflicts as $category => $plugins) {
            if (isset($plugins[$plugin_path])) {
                $plugin_info = $plugins[$plugin_path];
                return array(
                    'status' => 'known_plugin',
                    'category' => $category,
                    'conflict_level' => $plugin_info['conflict_level'],
                    'issues' => $plugin_info['issues'],
                    'solutions' => $plugin_info['solutions'],
                    'detected_issues' => self::detect_specific_conflicts($plugin_path)
                );
            }
        }
        
        // Check if unknown plugin with potential conflicts
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path, false, false);
        
        $potential_conflicts = array();
        
        if (self::is_likely_seo_plugin($plugin_data)) {
            $potential_conflicts[] = 'SEO functionality overlap';
        }
        
        if (self::is_likely_analytics_plugin($plugin_data)) {
            $potential_conflicts[] = 'Analytics tracking conflicts';
        }
        
        if (!empty($potential_conflicts)) {
            return array(
                'status' => 'potential_conflict',
                'plugin_name' => $plugin_data['Name'],
                'potential_issues' => $potential_conflicts,
                'recommendation' => 'Monitor for conflicts and test thoroughly'
            );
        }
        
        return array(
            'status' => 'compatible',
            'message' => 'No known conflicts detected'
        );
    }
    
    /**
     * Get formatted compatibility report
     */
    public static function get_formatted_report() {
        $report = self::run_compatibility_check();
        
        $output = "FP Digital Marketing Suite - Plugin Compatibility Report\n";
        $output .= "=========================================================\n";
        $output .= "Overall Status: " . strtoupper($report['overall_status']) . "\n";
        $output .= "Report Generated: " . $report['timestamp'] . "\n\n";
        
        if (!empty($report['conflicts'])) {
            $output .= "HIGH PRIORITY CONFLICTS (" . count($report['conflicts']) . ")\n";
            $output .= "=====================================\n";
            foreach ($report['conflicts'] as $conflict) {
                $output .= "Plugin: " . $conflict['plugin_name'] . "\n";
                $output .= "Issues:\n";
                foreach ($conflict['issues'] as $issue) {
                    $output .= "  - " . $issue . "\n";
                }
                $output .= "Solutions:\n";
                foreach ($conflict['solutions'] as $solution) {
                    $output .= "  * " . $solution . "\n";
                }
                $output .= "\n";
            }
        }
        
        if (!empty($report['warnings'])) {
            $output .= "WARNINGS (" . count($report['warnings']) . ")\n";
            $output .= "=====================================\n";
            foreach ($report['warnings'] as $warning) {
                $output .= "Plugin: " . $warning['plugin_name'] . "\n";
                $output .= "Level: " . strtoupper($warning['conflict_level']) . "\n";
                $output .= "Issues: " . implode(', ', $warning['issues']) . "\n\n";
            }
        }
        
        if (!empty($report['recommendations'])) {
            $output .= "RECOMMENDATIONS (" . count($report['recommendations']) . ")\n";
            $output .= "=====================================\n";
            foreach ($report['recommendations'] as $rec) {
                $output .= "Plugin: " . $rec['plugin_name'] . "\n";
                $output .= "Recommendation: " . implode(', ', $rec['solutions']) . "\n\n";
            }
        }
        
        if (!empty($report['general_recommendations'])) {
            $output .= "GENERAL RECOMMENDATIONS\n";
            $output .= "=====================================\n";
            foreach ($report['general_recommendations'] as $rec) {
                $output .= "- " . $rec . "\n";
            }
        }
        
        return $output;
    }
}

// AJAX handler for compatibility check
add_action('wp_ajax_fp_compatibility_check', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $report = FP_Plugin_Compatibility_Checker::run_compatibility_check();
    wp_send_json_success($report);
});

// CLI command for compatibility check
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('fp compatibility', function($args, $assoc_args) {
        if (!empty($args[0])) {
            // Check specific plugin
            $plugin_path = $args[0];
            $result = FP_Plugin_Compatibility_Checker::check_single_plugin($plugin_path);
            
            WP_CLI::log("Compatibility check for: {$plugin_path}");
            WP_CLI::log("Status: " . $result['status']);
            
            if (isset($result['issues'])) {
                WP_CLI::log("Issues: " . implode(', ', $result['issues']));
            }
            
            if (isset($result['solutions'])) {
                WP_CLI::log("Solutions: " . implode(', ', $result['solutions']));
            }
        } else {
            // Full compatibility report
            $format = $assoc_args['format'] ?? 'text';
            
            if ($format === 'json') {
                $report = FP_Plugin_Compatibility_Checker::run_compatibility_check();
                WP_CLI::log(json_encode($report, JSON_PRETTY_PRINT));
            } else {
                $report = FP_Plugin_Compatibility_Checker::get_formatted_report();
                WP_CLI::log($report);
            }
        }
    });
}
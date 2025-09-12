<?php
/**
 * Backup and Recovery System
 * 
 * Comprehensive backup and recovery tools for FP Digital Marketing Suite
 * 
 * @package FP_Digital_Marketing_Suite
 * @subpackage Tools
 * @since 1.0.0
 */

class FP_Backup_Recovery {
    
    /**
     * Create full backup of plugin data
     * 
     * @param string $backup_type Type of backup (full, settings, data)
     * @return array Backup results
     */
    public static function create_backup($backup_type = 'full') {
        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'version' => FP_DIGITAL_MARKETING_VERSION,
            'backup_type' => $backup_type,
            'data' => array()
        );
        
        switch ($backup_type) {
            case 'settings':
                $backup_data['data'] = self::backup_settings();
                break;
            case 'data':
                $backup_data['data'] = self::backup_database_data();
                break;
            case 'full':
            default:
                $backup_data['data']['settings'] = self::backup_settings();
                $backup_data['data']['database'] = self::backup_database_data();
                $backup_data['data']['uploads'] = self::backup_uploads();
                break;
        }
        
        return $backup_data;
    }
    
    /**
     * Backup plugin settings
     */
    private static function backup_settings() {
        $settings = array();
        
        // Get all plugin options
        $options = array(
            'fp_digital_marketing_settings',
            'fp_google_analytics_settings',
            'fp_google_ads_settings',
            'fp_search_console_settings',
            'fp_seo_settings',
            'fp_performance_settings',
            'fp_alert_settings',
            'fp_utm_settings'
        );
        
        foreach ($options as $option) {
            $value = get_option($option);
            if ($value !== false) {
                $settings[$option] = $value;
            }
        }
        
        return $settings;
    }
    
    /**
     * Backup database data
     */
    private static function backup_database_data() {
        global $wpdb;
        
        $data = array();
        
        $tables = array(
            'fp_clients',
            'fp_analytics_data',
            'fp_seo_data',
            'fp_alerts',
            'fp_performance_metrics',
            'fp_utm_campaigns',
            'fp_conversion_events'
        );
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            
            // Check if table exists
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if (!$exists) {
                continue;
            }
            
            // Get table structure
            $structure = $wpdb->get_results("DESCRIBE {$table_name}");
            
            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
            
            $data[$table] = array(
                'structure' => $structure,
                'data' => $rows,
                'count' => count($rows)
            );
        }
        
        return $data;
    }
    
    /**
     * Backup uploaded files
     */
    private static function backup_uploads() {
        $upload_dir = wp_upload_dir();
        $fp_uploads_dir = $upload_dir['basedir'] . '/fp-digital-marketing/';
        
        $files = array();
        
        if (is_dir($fp_uploads_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fp_uploads_dir)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relative_path = str_replace($fp_uploads_dir, '', $file->getPathname());
                    $files[$relative_path] = array(
                        'size' => $file->getSize(),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        'content' => base64_encode(file_get_contents($file->getPathname()))
                    );
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Save backup to file
     * 
     * @param array $backup_data Backup data
     * @param string $filename Optional filename
     * @return string|false Backup file path or false on failure
     */
    public static function save_backup($backup_data, $filename = null) {
        if (!$filename) {
            $filename = 'fp-backup-' . date('Y-m-d-H-i-s') . '.json';
        }
        
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/fp-backups/';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . $filename;
        
        // Save backup data
        $json_data = wp_json_encode($backup_data, JSON_PRETTY_PRINT);
        $saved = file_put_contents($backup_file, $json_data);
        
        if ($saved === false) {
            return false;
        }
        
        // Create checksum
        $checksum = md5($json_data);
        file_put_contents($backup_file . '.checksum', $checksum);
        
        return $backup_file;
    }
    
    /**
     * List available backups
     * 
     * @return array List of backup files
     */
    public static function list_backups() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/fp-backups/';
        
        $backups = array();
        
        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . '*.json');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $checksum_file = $file . '.checksum';
                
                $backups[] = array(
                    'filename' => $filename,
                    'path' => $file,
                    'size' => filesize($file),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'has_checksum' => file_exists($checksum_file),
                    'integrity' => self::verify_backup_integrity($file)
                );
            }
            
            // Sort by creation date (newest first)
            usort($backups, function($a, $b) {
                return strcmp($b['created'], $a['created']);
            });
        }
        
        return $backups;
    }
    
    /**
     * Verify backup integrity
     * 
     * @param string $backup_file Path to backup file
     * @return bool True if integrity check passes
     */
    public static function verify_backup_integrity($backup_file) {
        $checksum_file = $backup_file . '.checksum';
        
        if (!file_exists($checksum_file)) {
            return false;
        }
        
        $expected_checksum = trim(file_get_contents($checksum_file));
        $actual_checksum = md5_file($backup_file);
        
        return $expected_checksum === $actual_checksum;
    }
    
    /**
     * Restore from backup
     * 
     * @param string $backup_file Path to backup file
     * @param array $restore_options What to restore
     * @return array Restoration results
     */
    public static function restore_backup($backup_file, $restore_options = array()) {
        $results = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );
        
        // Verify backup integrity
        if (!self::verify_backup_integrity($backup_file)) {
            $results['message'] = 'Backup integrity check failed';
            return $results;
        }
        
        // Load backup data
        $backup_json = file_get_contents($backup_file);
        $backup_data = json_decode($backup_json, true);
        
        if (!$backup_data) {
            $results['message'] = 'Failed to parse backup data';
            return $results;
        }
        
        $default_options = array(
            'restore_settings' => true,
            'restore_database' => true,
            'restore_uploads' => true
        );
        
        $restore_options = array_merge($default_options, $restore_options);
        
        try {
            // Restore settings
            if ($restore_options['restore_settings'] && isset($backup_data['data']['settings'])) {
                $results['details']['settings'] = self::restore_settings($backup_data['data']['settings']);
            }
            
            // Restore database data
            if ($restore_options['restore_database'] && isset($backup_data['data']['database'])) {
                $results['details']['database'] = self::restore_database_data($backup_data['data']['database']);
            }
            
            // Restore uploads
            if ($restore_options['restore_uploads'] && isset($backup_data['data']['uploads'])) {
                $results['details']['uploads'] = self::restore_uploads($backup_data['data']['uploads']);
            }
            
            $results['success'] = true;
            $results['message'] = 'Backup restored successfully';
            
        } catch (Exception $e) {
            $results['message'] = 'Restoration failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Restore settings
     */
    private static function restore_settings($settings) {
        $restored = 0;
        
        foreach ($settings as $option_name => $option_value) {
            if (update_option($option_name, $option_value)) {
                $restored++;
            }
        }
        
        return array(
            'total' => count($settings),
            'restored' => $restored
        );
    }
    
    /**
     * Restore database data
     */
    private static function restore_database_data($database_data) {
        global $wpdb;
        
        $results = array();
        
        foreach ($database_data as $table => $table_data) {
            $table_name = $wpdb->prefix . $table;
            
            // Clear existing data
            $wpdb->query("TRUNCATE TABLE {$table_name}");
            
            $inserted = 0;
            
            foreach ($table_data['data'] as $row) {
                $result = $wpdb->insert($table_name, $row);
                if ($result !== false) {
                    $inserted++;
                }
            }
            
            $results[$table] = array(
                'total' => count($table_data['data']),
                'inserted' => $inserted
            );
        }
        
        return $results;
    }
    
    /**
     * Restore uploads
     */
    private static function restore_uploads($uploads_data) {
        $upload_dir = wp_upload_dir();
        $fp_uploads_dir = $upload_dir['basedir'] . '/fp-digital-marketing/';
        
        // Create directory if it doesn't exist
        if (!is_dir($fp_uploads_dir)) {
            wp_mkdir_p($fp_uploads_dir);
        }
        
        $restored = 0;
        
        foreach ($uploads_data as $relative_path => $file_data) {
            $full_path = $fp_uploads_dir . $relative_path;
            $dir = dirname($full_path);
            
            // Create directory if needed
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            
            // Restore file content
            $content = base64_decode($file_data['content']);
            if (file_put_contents($full_path, $content) !== false) {
                $restored++;
            }
        }
        
        return array(
            'total' => count($uploads_data),
            'restored' => $restored
        );
    }
    
    /**
     * Delete old backups
     * 
     * @param int $keep_count Number of backups to keep
     * @return int Number of backups deleted
     */
    public static function cleanup_old_backups($keep_count = 10) {
        $backups = self::list_backups();
        $deleted = 0;
        
        if (count($backups) > $keep_count) {
            $to_delete = array_slice($backups, $keep_count);
            
            foreach ($to_delete as $backup) {
                if (unlink($backup['path'])) {
                    $deleted++;
                    
                    // Also delete checksum file
                    $checksum_file = $backup['path'] . '.checksum';
                    if (file_exists($checksum_file)) {
                        unlink($checksum_file);
                    }
                }
            }
        }
        
        return $deleted;
    }
}

// AJAX handlers for backup/restore
add_action('wp_ajax_fp_create_backup', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $backup_type = sanitize_text_field($_POST['backup_type'] ?? 'full');
    $backup_data = FP_Backup_Recovery::create_backup($backup_type);
    $backup_file = FP_Backup_Recovery::save_backup($backup_data);
    
    if ($backup_file) {
        wp_send_json_success(array(
            'message' => 'Backup created successfully',
            'file' => basename($backup_file)
        ));
    } else {
        wp_send_json_error('Failed to create backup');
    }
});

add_action('wp_ajax_fp_list_backups', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $backups = FP_Backup_Recovery::list_backups();
    wp_send_json_success($backups);
});

// CLI commands for backup/restore
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('fp backup create', function($args, $assoc_args) {
        $type = $assoc_args['type'] ?? 'full';
        $backup_data = FP_Backup_Recovery::create_backup($type);
        $backup_file = FP_Backup_Recovery::save_backup($backup_data);
        
        if ($backup_file) {
            WP_CLI::success("Backup created: " . basename($backup_file));
        } else {
            WP_CLI::error("Failed to create backup");
        }
    });
    
    WP_CLI::add_command('fp backup list', function() {
        $backups = FP_Backup_Recovery::list_backups();
        
        if (empty($backups)) {
            WP_CLI::log("No backups found");
            return;
        }
        
        $table = array();
        foreach ($backups as $backup) {
            $table[] = array(
                'Filename' => $backup['filename'],
                'Size' => size_format($backup['size']),
                'Created' => $backup['created'],
                'Integrity' => $backup['integrity'] ? 'OK' : 'FAILED'
            );
        }
        
        WP_CLI\Utils\format_items('table', $table, array('Filename', 'Size', 'Created', 'Integrity'));
    });
    
    WP_CLI::add_command('fp backup restore', function($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify backup filename");
        }
        
        $upload_dir = wp_upload_dir();
        $backup_file = $upload_dir['basedir'] . '/fp-backups/' . $args[0];
        
        if (!file_exists($backup_file)) {
            WP_CLI::error("Backup file not found: " . $args[0]);
        }
        
        $results = FP_Backup_Recovery::restore_backup($backup_file);
        
        if ($results['success']) {
            WP_CLI::success($results['message']);
        } else {
            WP_CLI::error($results['message']);
        }
    });
}
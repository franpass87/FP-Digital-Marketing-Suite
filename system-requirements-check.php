<?php
/**
 * System Requirements Checker for FP Digital Marketing Suite
 *
 * This script checks system requirements and compatibility before deployment.
 * Run this script to ensure your environment meets all requirements.
 *
 * @package FP\DigitalMarketing\Tools
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('FP_DMS_SYSTEM_CHECK')) {
    define('FP_DMS_SYSTEM_CHECK', true);
}

/**
 * System Requirements Checker
 */
class FP_DMS_SystemChecker {
    
    /**
     * Minimum requirements
     */
    const REQUIREMENTS = [
        'php_version' => '7.4.0',
        'wordpress_version' => '5.0.0',
        'mysql_version' => '5.6.0',
        'memory_limit' => 128, // MB
        'max_execution_time' => 30, // seconds
        'upload_max_filesize' => 2 // MB
    ];
    
    /**
     * Required PHP extensions
     */
    const REQUIRED_EXTENSIONS = [
        'curl',
        'json',
        'mbstring',
        'mysqli',
        'openssl',
        'zip'
    ];
    
    /**
     * Optional but recommended extensions
     */
    const RECOMMENDED_EXTENSIONS = [
        'gd',
        'imagick',
        'redis',
        'memcached'
    ];
    
    /**
     * WordPress constants that should be defined
     */
    const RECOMMENDED_CONSTANTS = [
        'WP_DEBUG' => false,
        'WP_DEBUG_LOG' => true,
        'SCRIPT_DEBUG' => false,
        'WP_CRON_LOCK_TIMEOUT' => 60
    ];
    
    /**
     * Check results
     */
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    /**
     * Run all system checks
     */
    public function run_checks(): array {
        echo "🔍 FP Digital Marketing Suite - System Requirements Check\n";
        echo "=====================================================\n\n";
        
        $this->check_php_version();
        $this->check_wordpress_version();
        $this->check_database_version();
        $this->check_memory_limit();
        $this->check_execution_time();
        $this->check_upload_size();
        $this->check_php_extensions();
        $this->check_wordpress_constants();
        $this->check_file_permissions();
        $this->check_htaccess();
        $this->check_ssl();
        $this->check_server_software();
        $this->check_composer_dependencies();
        
        $this->display_results();
        
        return [
            'status' => empty($this->errors) ? 'pass' : 'fail',
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'results' => $this->results
        ];
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version(): void {
        $current = PHP_VERSION;
        $required = self::REQUIREMENTS['php_version'];
        
        if (version_compare($current, $required, '>=')) {
            $this->add_result('✅', 'PHP Version', "PHP {$current} (Required: {$required}+)");
        } else {
            $this->add_error('❌', 'PHP Version', "PHP {$current} is too old. Required: {$required}+");
        }
        
        // Check for PHP 8+ for better performance
        if (version_compare($current, '8.0.0', '>=')) {
            $this->add_result('🚀', 'PHP Performance', 'PHP 8+ detected - Excellent performance!');
        }
    }
    
    /**
     * Check WordPress version
     */
    private function check_wordpress_version(): void {
        if (defined('ABSPATH')) {
            global $wp_version;
            $current = $wp_version;
            $required = self::REQUIREMENTS['wordpress_version'];
            
            if (version_compare($current, $required, '>=')) {
                $this->add_result('✅', 'WordPress Version', "WordPress {$current} (Required: {$required}+)");
            } else {
                $this->add_error('❌', 'WordPress Version', "WordPress {$current} is too old. Required: {$required}+");
            }
        } else {
            $this->add_warning('⚠️', 'WordPress Version', 'WordPress not detected - ensure this runs in WordPress environment');
        }
    }
    
    /**
     * Check database version
     */
    private function check_database_version(): void {
        if (defined('ABSPATH')) {
            global $wpdb;
            $version = $wpdb->get_var('SELECT VERSION()');
            $required = self::REQUIREMENTS['mysql_version'];
            
            if (version_compare($version, $required, '>=')) {
                $this->add_result('✅', 'MySQL Version', "MySQL {$version} (Required: {$required}+)");
            } else {
                $this->add_error('❌', 'MySQL Version', "MySQL {$version} is too old. Required: {$required}+");
            }
        } else {
            $this->add_warning('⚠️', 'Database Version', 'Database version check skipped - not in WordPress environment');
        }
    }
    
    /**
     * Check memory limit
     */
    private function check_memory_limit(): void {
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $required_bytes = self::REQUIREMENTS['memory_limit'] * 1024 * 1024;
        
        if ($memory_bytes >= $required_bytes) {
            $this->add_result('✅', 'Memory Limit', "Memory limit: {$memory_limit} (Required: " . self::REQUIREMENTS['memory_limit'] . "MB+)");
        } else {
            $this->add_error('❌', 'Memory Limit', "Memory limit: {$memory_limit} is too low. Required: " . self::REQUIREMENTS['memory_limit'] . "MB+");
        }
        
        // Recommend higher memory for better performance
        if ($memory_bytes >= 256 * 1024 * 1024) {
            $this->add_result('🚀', 'Memory Performance', 'Memory limit 256MB+ - Excellent for large datasets!');
        }
    }
    
    /**
     * Check max execution time
     */
    private function check_execution_time(): void {
        $max_execution_time = ini_get('max_execution_time');
        $required = self::REQUIREMENTS['max_execution_time'];
        
        if ($max_execution_time == 0 || $max_execution_time >= $required) {
            $this->add_result('✅', 'Execution Time', "Max execution time: {$max_execution_time}s (Required: {$required}s+)");
        } else {
            $this->add_warning('⚠️', 'Execution Time', "Max execution time: {$max_execution_time}s might be too low for data sync operations");
        }
    }
    
    /**
     * Check upload file size
     */
    private function check_upload_size(): void {
        $upload_max = ini_get('upload_max_filesize');
        $upload_bytes = $this->convert_to_bytes($upload_max);
        $required_bytes = self::REQUIREMENTS['upload_max_filesize'] * 1024 * 1024;
        
        if ($upload_bytes >= $required_bytes) {
            $this->add_result('✅', 'Upload Size', "Upload max filesize: {$upload_max} (Required: " . self::REQUIREMENTS['upload_max_filesize'] . "MB+)");
        } else {
            $this->add_warning('⚠️', 'Upload Size', "Upload max filesize: {$upload_max} might be too low for report exports");
        }
    }
    
    /**
     * Check PHP extensions
     */
    private function check_php_extensions(): void {
        echo "📦 PHP Extensions Check:\n";
        
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (extension_loaded($extension)) {
                $this->add_result('✅', 'Extension', "{$extension} - loaded");
            } else {
                $this->add_error('❌', 'Extension', "{$extension} - MISSING (required)");
            }
        }
        
        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            if (extension_loaded($extension)) {
                $this->add_result('🚀', 'Extension', "{$extension} - loaded (recommended)");
            } else {
                $this->add_warning('⚠️', 'Extension', "{$extension} - not loaded (recommended for performance)");
            }
        }
    }
    
    /**
     * Check WordPress constants
     */
    private function check_wordpress_constants(): void {
        if (!defined('ABSPATH')) {
            $this->add_warning('⚠️', 'WordPress Constants', 'WordPress constants check skipped - not in WordPress environment');
            return;
        }
        
        echo "\n🔧 WordPress Configuration:\n";
        
        foreach (self::RECOMMENDED_CONSTANTS as $constant => $recommended_value) {
            if (defined($constant)) {
                $current_value = constant($constant);
                $status = ($current_value === $recommended_value) ? '✅' : '⚠️';
                $message = "{$constant}: " . var_export($current_value, true) . " (Recommended: " . var_export($recommended_value, true) . ")";
                
                if ($status === '✅') {
                    $this->add_result($status, 'WP Constant', $message);
                } else {
                    $this->add_warning($status, 'WP Constant', $message);
                }
            } else {
                $this->add_warning('⚠️', 'WP Constant', "{$constant} not defined (Recommended: " . var_export($recommended_value, true) . ")");
            }
        }
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions(): void {
        echo "\n📁 File Permissions Check:\n";
        
        $check_paths = [
            'wp-content/uploads' => '755',
            'wp-content/plugins' => '755'
        ];
        
        if (defined('ABSPATH')) {
            foreach ($check_paths as $path => $required_perm) {
                $full_path = ABSPATH . $path;
                if (file_exists($full_path)) {
                    $perms = substr(sprintf('%o', fileperms($full_path)), -3);
                    if ($perms >= $required_perm) {
                        $this->add_result('✅', 'Permissions', "{$path}: {$perms} (Required: {$required_perm}+)");
                    } else {
                        $this->add_error('❌', 'Permissions', "{$path}: {$perms} insufficient (Required: {$required_perm}+)");
                    }
                } else {
                    $this->add_warning('⚠️', 'Permissions', "{$path}: path not found");
                }
            }
        }
    }
    
    /**
     * Check .htaccess file
     */
    private function check_htaccess(): void {
        if (defined('ABSPATH')) {
            $htaccess_path = ABSPATH . '.htaccess';
            if (file_exists($htaccess_path)) {
                $content = file_get_contents($htaccess_path);
                if (strpos($content, 'RewriteEngine On') !== false) {
                    $this->add_result('✅', 'URL Rewriting', '.htaccess with mod_rewrite detected');
                } else {
                    $this->add_warning('⚠️', 'URL Rewriting', '.htaccess exists but mod_rewrite rules not found');
                }
            } else {
                $this->add_warning('⚠️', 'URL Rewriting', '.htaccess file not found - pretty permalinks may not work');
            }
        }
    }
    
    /**
     * Check SSL
     */
    private function check_ssl(): void {
        if (defined('ABSPATH')) {
            if (is_ssl()) {
                $this->add_result('✅', 'Security', 'SSL/HTTPS enabled');
            } else {
                $this->add_warning('⚠️', 'Security', 'SSL/HTTPS not enabled - recommended for production');
            }
        }
    }
    
    /**
     * Check server software
     */
    private function check_server_software(): void {
        echo "\n🖥️ Server Environment:\n";
        
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $this->add_result('ℹ️', 'Server', "Server software: {$server_software}");
        
        // Check for recommended server software
        if (stripos($server_software, 'nginx') !== false) {
            $this->add_result('🚀', 'Server', 'Nginx detected - excellent performance for WordPress');
        } elseif (stripos($server_software, 'apache') !== false) {
            $this->add_result('✅', 'Server', 'Apache detected - good compatibility with WordPress');
        }
        
        // Check for Cloudflare
        if (isset($_SERVER['HTTP_CF_RAY'])) {
            $this->add_result('🚀', 'CDN', 'Cloudflare detected - excellent for performance and security');
        }
    }
    
    /**
     * Check Composer dependencies
     */
    private function check_composer_dependencies(): void {
        echo "\n📚 Composer Dependencies:\n";
        
        $composer_json = dirname(__FILE__) . '/composer.json';
        if (file_exists($composer_json)) {
            $this->add_result('✅', 'Composer', 'composer.json found');
            
            $vendor_dir = dirname(__FILE__) . '/vendor';
            if (is_dir($vendor_dir)) {
                $this->add_result('✅', 'Dependencies', 'Vendor directory exists');
                
                $autoload_file = $vendor_dir . '/autoload.php';
                if (file_exists($autoload_file)) {
                    $this->add_result('✅', 'Autoload', 'Composer autoloader available');
                } else {
                    $this->add_error('❌', 'Autoload', 'Composer autoloader missing - run "composer install"');
                }
            } else {
                $this->add_error('❌', 'Dependencies', 'Vendor directory missing - run "composer install"');
            }
        } else {
            $this->add_warning('⚠️', 'Composer', 'composer.json not found');
        }
    }
    
    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes(string $value): int {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Add result
     */
    private function add_result(string $icon, string $category, string $message): void {
        $this->results[] = ['icon' => $icon, 'category' => $category, 'message' => $message];
        echo "{$icon} {$category}: {$message}\n";
    }
    
    /**
     * Add error
     */
    private function add_error(string $icon, string $category, string $message): void {
        $this->errors[] = ['icon' => $icon, 'category' => $category, 'message' => $message];
        echo "{$icon} {$category}: {$message}\n";
    }
    
    /**
     * Add warning
     */
    private function add_warning(string $icon, string $category, string $message): void {
        $this->warnings[] = ['icon' => $icon, 'category' => $category, 'message' => $message];
        echo "{$icon} {$category}: {$message}\n";
    }
    
    /**
     * Display final results
     */
    private function display_results(): void {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "📊 SYSTEM CHECK SUMMARY\n";
        echo str_repeat('=', 50) . "\n";
        
        $total_checks = count($this->results);
        $error_count = count($this->errors);
        $warning_count = count($this->warnings);
        $success_count = $total_checks - $error_count - $warning_count;
        
        echo "✅ Passed: {$success_count}\n";
        echo "⚠️ Warnings: {$warning_count}\n";
        echo "❌ Errors: {$error_count}\n";
        echo "📊 Total Checks: {$total_checks}\n\n";
        
        if ($error_count === 0) {
            echo "🎉 SYSTEM CHECK PASSED!\n";
            echo "Your system meets all requirements for FP Digital Marketing Suite.\n";
            echo "You can proceed with installation.\n";
        } else {
            echo "🚨 SYSTEM CHECK FAILED!\n";
            echo "Please resolve the following errors before installation:\n\n";
            foreach ($this->errors as $error) {
                echo "• {$error['message']}\n";
            }
        }
        
        if ($warning_count > 0) {
            echo "\n⚠️ RECOMMENDATIONS:\n";
            foreach ($this->warnings as $warning) {
                echo "• {$warning['message']}\n";
            }
        }
        
        echo "\n🔗 For support and documentation:\n";
        echo "• GitHub: https://github.com/franpass87/FP-Digital-Marketing-Suite\n";
        echo "• Email: franpass87@example.com\n";
    }
}

// Run the system check if executed directly
if (defined('FP_DMS_SYSTEM_CHECK')) {
    $checker = new FP_DMS_SystemChecker();
    $results = $checker->run_checks();
    
    // Exit with appropriate code for CI/CD
    exit($results['status'] === 'pass' ? 0 : 1);
}
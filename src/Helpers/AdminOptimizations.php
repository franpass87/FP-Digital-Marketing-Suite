<?php
/**
 * Admin Performance & UX Optimizations Helper
 * 
 * Manages advanced optimizations for the admin interface including:
 * - Asset optimization and caching
 * - Enhanced error handling
 * - Accessibility improvements
 * - Mobile responsiveness
 * - Performance monitoring
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Performance and UX optimization utilities
 */
class AdminOptimizations {

    /**
     * Cache group for optimization data
     */
    private const CACHE_GROUP = 'fp_dms_optimizations';

    /**
     * Initialize optimizations
     */
    public function init(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_optimization_assets' ], 5 );
        add_action( 'admin_head', [ $this, 'add_performance_hints' ] );
        add_action( 'admin_footer', [ $this, 'add_performance_monitoring' ] );
        add_filter( 'script_loader_tag', [ $this, 'optimize_script_loading' ], 10, 3 );
        add_filter( 'style_loader_tag', [ $this, 'optimize_style_loading' ], 10, 4 );
        add_action( 'wp_ajax_fp_dms_performance_metrics', [ $this, 'handle_performance_metrics' ] );
    }

    /**
     * Enqueue optimization assets
     */
    public function enqueue_optimization_assets( string $hook ): void {
        // Only load on our admin pages
        if ( ! $this->is_fp_dms_admin_page() ) {
            return;
        }

        // Enqueue optimization CSS
        wp_enqueue_style(
            'fp-dms-admin-optimizations',
            FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/admin-optimizations.css',
            [],
            FP_DIGITAL_MARKETING_VERSION,
            'all'
        );

        // Enqueue optimization JavaScript
        wp_enqueue_script(
            'fp-dms-admin-optimizations',
            FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/js/admin-optimizations.js',
            [ 'jquery', 'wp-util' ],
            FP_DIGITAL_MARKETING_VERSION,
            true
        );

        // Enqueue keyboard shortcuts
        wp_enqueue_script(
            'fp-dms-keyboard-shortcuts',
            FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/js/keyboard-shortcuts.js',
            [ 'jquery', 'fp-dms-admin-optimizations' ],
            FP_DIGITAL_MARKETING_VERSION,
            true
        );

        // Add localized script data
        wp_localize_script(
            'fp-dms-admin-optimizations',
            'fpDmsOptimizations',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'fp_dms_optimizations' ),
                'settings' => $this->get_optimization_settings(),
                'i18n' => $this->get_i18n_strings(),
                'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            ]
        );
    }

    /**
     * Add performance hints to page head
     */
    public function add_performance_hints(): void {
        if ( ! $this->is_fp_dms_admin_page() ) {
            return;
        }

        echo "<!-- FP DMS Performance Optimizations -->\n";
        
        // DNS prefetch for external resources
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        
        // Preload critical CSS
        $critical_css = FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/admin-optimizations.css';
        echo '<link rel="preload" href="' . esc_url( $critical_css ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . esc_url( $critical_css ) . '"></noscript>' . "\n";
        
        // Add viewport meta for mobile optimization
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
        
        // Theme color for mobile browsers
        echo '<meta name="theme-color" content="#0073aa">' . "\n";
        
        echo "<!-- End FP DMS Performance Optimizations -->\n";
    }

    /**
     * Add performance monitoring script
     */
    public function add_performance_monitoring(): void {
        if ( ! $this->is_fp_dms_admin_page() || ! $this->should_monitor_performance() ) {
            return;
        }

        ?>
        <script>
        (function() {
            'use strict';
            
            // Monitor Core Web Vitals
            function observeWebVitals() {
                if ('web-vital' in window) {
                    return; // Already loaded
                }
                
                // Simple LCP monitoring
                if ('PerformanceObserver' in window) {
                    try {
                        new PerformanceObserver((list) => {
                            const entries = list.getEntries();
                            const lastEntry = entries[entries.length - 1];
                            
                            if (lastEntry && lastEntry.startTime > 0) {
                                // Send to server if enabled
                                if (window.fpDmsOptimizations && window.fpDmsOptimizations.settings.collectMetrics) {
                                    wp.ajax.post('fp_dms_performance_metrics', {
                                        metric: 'lcp',
                                        value: lastEntry.startTime,
                                        url: window.location.href,
                                        nonce: window.fpDmsOptimizations.nonce
                                    });
                                }
                            }
                        }).observe({ type: 'largest-contentful-paint', buffered: true });
                    } catch (e) {
                        if (window.WP_DEBUG) {
                            console.warn('Performance monitoring failed:', e);
                        }
                    }
                }
            }
            
            // Monitor JavaScript errors
            window.addEventListener('error', function(e) {
                if (window.fpDmsOptimizations && window.fpDmsOptimizations.settings.collectErrors) {
                    wp.ajax.post('fp_dms_performance_metrics', {
                        metric: 'js_error',
                        value: e.message,
                        stack: e.error ? e.error.stack : '',
                        url: window.location.href,
                        nonce: window.fpDmsOptimizations.nonce
                    });
                }
            });
            
            // Initialize monitoring when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', observeWebVitals);
            } else {
                observeWebVitals();
            }
        })();
        </script>
        <?php
    }

    /**
     * Optimize script loading with async/defer
     */
    public function optimize_script_loading( string $tag, string $handle, string $src ): string {
        // Only optimize our scripts
        if ( strpos( $handle, 'fp-dms' ) === false ) {
            return $tag;
        }

        // Add async to non-critical scripts
        $async_handles = [
            'fp-dms-admin-optimizations',
            'fp-dms-analytics',
            'fp-dms-charts'
        ];

        if ( in_array( $handle, $async_handles, true ) ) {
            return str_replace( ' src', ' async src', $tag );
        }

        return $tag;
    }

    /**
     * Optimize style loading
     */
    public function optimize_style_loading( string $html, string $handle, string $href, string $media ): string {
        // Only optimize our styles
        if ( strpos( $handle, 'fp-dms' ) === false ) {
            return $html;
        }

        // Add media queries for responsive loading
        if ( $handle === 'fp-dms-admin-optimizations' ) {
            return str_replace( "media='all'", "media='all'", $html );
        }

        return $html;
    }

    /**
     * Handle performance metrics AJAX request
     */
    public function handle_performance_metrics(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'fp_dms_optimizations' ) ) {
            wp_die( 'Security check failed' );
        }

        // Verify capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $metric = sanitize_text_field( $_POST['metric'] ?? '' );
        $value = sanitize_text_field( $_POST['value'] ?? '' );
        $url = esc_url_raw( $_POST['url'] ?? '' );

        if ( empty( $metric ) || empty( $value ) ) {
            wp_send_json_error( 'Invalid metric data' );
        }

        // Store performance metric
        $this->store_performance_metric( $metric, $value, $url );

        wp_send_json_success( 'Metric recorded' );
    }

    /**
     * Store performance metric
     */
    private function store_performance_metric( string $metric, string $value, string $url ): void {
        $metrics = get_option( 'fp_dms_performance_metrics', [] );
        
        if ( ! is_array( $metrics ) ) {
            $metrics = [];
        }

        $metrics[] = [
            'metric' => $metric,
            'value' => $value,
            'url' => $url,
            'timestamp' => current_time( 'timestamp' ),
            'user_id' => get_current_user_id(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        // Keep only last 1000 metrics to prevent database bloat
        if ( count( $metrics ) > 1000 ) {
            $metrics = array_slice( $metrics, -1000 );
        }

        update_option( 'fp_dms_performance_metrics', $metrics );
    }

    /**
     * Get optimization settings
     */
    private function get_optimization_settings(): array {
        return [
            'cacheEnabled' => true,
            'cacheDuration' => 60, // minutes
            'retryAttempts' => 3,
            'retryDelay' => 1000, // milliseconds
            'collectMetrics' => $this->should_monitor_performance(),
            'collectErrors' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'debounceDelay' => 300,
            'throttleLimit' => 100,
            'lazyLoadThreshold' => 0.1,
            'lazyLoadRootMargin' => '50px',
        ];
    }

    /**
     * Get internationalization strings
     */
    private function get_i18n_strings(): array {
        return [
            'loading' => __( 'Loading...', 'fp-digital-marketing' ),
            'error' => __( 'Error', 'fp-digital-marketing' ),
            'success' => __( 'Success', 'fp-digital-marketing' ),
            'retry' => __( 'Retry', 'fp-digital-marketing' ),
            'dismiss' => __( 'Dismiss', 'fp-digital-marketing' ),
            'networkError' => __( 'Network error. Please check your connection.', 'fp-digital-marketing' ),
            'serverError' => __( 'Server error. Please try again later.', 'fp-digital-marketing' ),
            'timeoutError' => __( 'Request timed out. Please try again.', 'fp-digital-marketing' ),
            'permissionError' => __( 'Permission denied. Please refresh the page.', 'fp-digital-marketing' ),
            'formSaved' => __( 'Form data saved automatically.', 'fp-digital-marketing' ),
            'formRestored' => __( 'Form data restored from previous session.', 'fp-digital-marketing' ),
            'contentLoaded' => __( 'Content loaded successfully.', 'fp-digital-marketing' ),
        ];
    }

    /**
     * Check if current page is an FP DMS admin page
     */
    private function is_fp_dms_admin_page(): bool {
        $screen = get_current_screen();
        
        if ( ! $screen ) {
            return false;
        }

        $main_menu_slug = 'fp-digital-marketing-dashboard';

        $page_slugs = [
            'fp-digital-marketing-dashboard',
            'fp-digital-marketing-reports',
            'fp-utm-campaign-manager',
            'fp-digital-marketing-funnel-analysis',
            'fp-audience-segments',
            'fp-conversion-events',
            'fp-platform-connections',
            'fp-digital-marketing-cache-performance',
            'fp-digital-marketing-alerts',
            'fp-digital-marketing-anomalies',
            'fp-digital-marketing-settings',
            'fp-digital-marketing-security',
            'fp-digital-marketing-onboarding',
        ];

        $screen_ids = [ 'toplevel_page_' . $main_menu_slug ];

        foreach ( $page_slugs as $slug ) {
            $screen_ids[] = $main_menu_slug . '_page_' . $slug;

            // Legacy prefix support when menus are registered without the "-dashboard" suffix.
            $screen_ids[] = 'fp-digital-marketing_page_' . $slug;
        }

        // Client-specific admin tools registered under the Cliente post type menu.
        $screen_ids[] = 'cliente_page_fp-anomaly-radar';

        $screen_ids = array_values( array_unique( $screen_ids ) );

        return in_array( $screen->id, $screen_ids, true );
    }

    /**
     * Check if performance monitoring should be enabled
     */
    private function should_monitor_performance(): bool {
        // Only monitor in development or if explicitly enabled
        return defined( 'WP_DEBUG' ) && WP_DEBUG ||
               get_option( 'fp_dms_enable_performance_monitoring', false );
    }

    /**
     * Get cached performance recommendations
     */
    public function get_performance_recommendations(): array {
        $cache_key = 'performance_recommendations';
        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
        
        if ( $cached !== false ) {
            return $cached;
        }

        $recommendations = $this->generate_performance_recommendations();
        
        // Cache for 1 hour
        wp_cache_set( $cache_key, $recommendations, self::CACHE_GROUP, HOUR_IN_SECONDS );
        
        return $recommendations;
    }

    /**
     * Generate performance recommendations based on metrics
     */
    private function generate_performance_recommendations(): array {
        $recommendations = [];
        $metrics = get_option( 'fp_dms_performance_metrics', [] );
        
        if ( empty( $metrics ) ) {
            return [];
        }

        // Analyze recent metrics (last 24 hours)
        $recent_metrics = array_filter( $metrics, function( $metric ) {
            return $metric['timestamp'] > ( current_time( 'timestamp' ) - DAY_IN_SECONDS );
        });

        // Check for slow LCP
        $lcp_metrics = array_filter( $recent_metrics, function( $metric ) {
            return $metric['metric'] === 'lcp';
        });

        if ( ! empty( $lcp_metrics ) ) {
            $avg_lcp = array_sum( array_column( $lcp_metrics, 'value' ) ) / count( $lcp_metrics );
            
            if ( $avg_lcp > 2500 ) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => __( 'Slow Page Load', 'fp-digital-marketing' ),
                    'message' => sprintf(
                        __( 'Average page load time is %.1fs. Consider optimizing images and reducing plugin overhead.', 'fp-digital-marketing' ),
                        $avg_lcp / 1000
                    ),
                    'action' => 'optimize_assets',
                ];
            }
        }

        // Check for JavaScript errors
        $error_metrics = array_filter( $recent_metrics, function( $metric ) {
            return $metric['metric'] === 'js_error';
        });

        if ( count( $error_metrics ) > 5 ) {
            $recommendations[] = [
                'type' => 'error',
                'title' => __( 'JavaScript Errors Detected', 'fp-digital-marketing' ),
                'message' => sprintf(
                    __( '%d JavaScript errors detected in the last 24 hours. Check the browser console for details.', 'fp-digital-marketing' ),
                    count( $error_metrics )
                ),
                'action' => 'check_console',
            ];
        }

        return $recommendations;
    }

    /**
     * Clear performance cache
     */
    public function clear_performance_cache(): void {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( self::CACHE_GROUP );
            return;
        }

        if ( class_exists( PerformanceCache::class ) && method_exists( PerformanceCache::class, 'invalidate_group' ) ) {
            PerformanceCache::invalidate_group( self::CACHE_GROUP );
        }

        wp_cache_delete( 'performance_recommendations', self::CACHE_GROUP );
    }

    /**
     * Get performance dashboard widget data
     */
    public function get_performance_widget_data(): array {
        $metrics = get_option( 'fp_dms_performance_metrics', [] );
        $recent_metrics = array_filter( $metrics, function( $metric ) {
            return $metric['timestamp'] > ( current_time( 'timestamp' ) - WEEK_IN_SECONDS );
        });

        $lcp_metrics = array_filter( $recent_metrics, function( $metric ) {
            return $metric['metric'] === 'lcp';
        });

        $error_count = count( array_filter( $recent_metrics, function( $metric ) {
            return $metric['metric'] === 'js_error';
        }));

        $avg_lcp = 0;
        if ( ! empty( $lcp_metrics ) ) {
            $avg_lcp = array_sum( array_column( $lcp_metrics, 'value' ) ) / count( $lcp_metrics );
        }

        return [
            'avg_load_time' => round( $avg_lcp / 1000, 2 ),
            'error_count' => $error_count,
            'metrics_count' => count( $recent_metrics ),
            'recommendations' => $this->get_performance_recommendations(),
        ];
    }
}
<?php
/**
 * Tests for SiteHealth helper.
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\SiteHealth;

/**
 * Minimal wpdb stub for Site Health tests.
 */
class SiteHealthWPDBStub {
        /**
         * WordPress table prefix.
         *
         * @var string
         */
        public string $prefix = 'wp_';

        /**
         * List of tables that should be reported as existing.
         *
         * @var array<int, string>
         */
        private array $existing_tables = [];

        /**
         * Update the list of tables reported as existing.
         *
         * @param array<int, string> $tables Table names.
         * @return void
         */
        public function set_existing_tables( array $tables ): void {
                $this->existing_tables = $tables;
        }

        /**
         * Simulate wpdb::prepare.
         *
         * @param string $query SQL query.
         * @param mixed  ...$args Arguments.
         * @return string
         */
        public function prepare( string $query, ...$args ): string { // phpcs:ignore Squiz.Commenting.FunctionComment.MissingParamTag
                if ( strpos( $query, '%s' ) !== false && ! empty( $args ) ) {
                        $replacements = array_map(
                                static function ( $arg ) {
                                        return "'" . $arg . "'";
                                },
                                $args
                        );

                        foreach ( $replacements as $replacement ) {
                                $query = preg_replace( '/%s/', $replacement, $query, 1 );
                        }
                }

                return $query;
        }

        /**
         * Simulate wpdb::get_var.
         *
         * @param string $query Prepared query.
         * @return string|null
         */
        public function get_var( string $query ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $query, $matches ) ) {
                        $table = $matches[1];

                        if ( in_array( $table, $this->existing_tables, true ) ) {
                                return $table;
                        }

                        return null;
                }

                return null;
        }
}

/**
 * @covers \FP\DigitalMarketing\Helpers\SiteHealth
 */
class SiteHealthTest extends TestCase {

        /**
         * Original wpdb instance.
         *
         * @var mixed
         */
        private $original_wpdb;

        /**
         * Original WordPress options store.
         *
         * @var array<string, mixed>
         */
        private array $original_options = [];

        /**
         * Original mocked functions map.
         *
         * @var array<string, callable>
         */
        private array $original_mock_functions = [];

        protected function setUp(): void {
                parent::setUp();

                global $wpdb, $wp_options, $wp_mock_functions;

                $this->original_wpdb            = $wpdb ?? null;
                $this->original_options         = $wp_options ?? [];
                $this->original_mock_functions  = $wp_mock_functions ?? [];

                $wp_options        = $this->original_options;
                $wp_mock_functions = $this->original_mock_functions;
        }

        protected function tearDown(): void {
                global $wpdb, $wp_options, $wp_mock_functions;

                $wpdb              = $this->original_wpdb;
                $wp_options        = $this->original_options;
                $wp_mock_functions = $this->original_mock_functions;

                parent::tearDown();
        }

        public function test_register_tests_adds_site_health_entries(): void {
                $tests   = [ 'direct' => [] ];
                $result  = SiteHealth::register_tests( $tests );
                $entries = array_keys( $result['direct'] );

                $this->assertContains( 'fp_dms_database', $entries );
                $this->assertContains( 'fp_dms_scheduled_events', $entries );
        }

        public function test_database_tables_reports_success_when_all_tables_exist(): void {
                global $wpdb;

                $stub = new SiteHealthWPDBStub();
                $all_tables = [
                        'wp_fp_metrics_cache',
                        'wp_fp_conversion_events',
                        'wp_fp_audience_segments',
                        'wp_fp_segment_membership',
                        'wp_fp_utm_campaigns',
                        'wp_fp_dms_funnels',
                        'wp_fp_dms_funnel_stages',
                        'wp_fp_dms_customer_journeys',
                        'wp_fp_dms_journey_sessions',
                        'wp_fp_dms_custom_reports',
                        'wp_fp_dms_social_sentiment',
                        'wp_fp_alert_rules',
                        'wp_fp_anomaly_rules',
                        'wp_fp_detected_anomalies',
                ];
                $stub->set_existing_tables( $all_tables );

                $wpdb = $stub;

                $result = SiteHealth::test_database_tables();

                $this->assertSame( 'good', $result['status'] );
        }

        public function test_database_tables_reports_critical_when_tables_missing(): void {
                global $wpdb;

                $stub = new SiteHealthWPDBStub();
                $stub->set_existing_tables( [ 'wp_fp_metrics_cache' ] );

                $wpdb = $stub;

                $result = SiteHealth::test_database_tables();

                $this->assertSame( 'critical', $result['status'] );
                $this->assertStringContainsString( 'fp_conversion_events', $result['description'] );
        }

        public function test_scheduled_events_reports_good_when_all_hooks_present(): void {
                global $wp_options, $wp_mock_functions;

                $wp_options['fp_digital_marketing_sync_settings']  = [ 'enable_sync' => true ];
                $wp_options['fp_digital_marketing_email_settings'] = [ 'daily_digest_enabled' => true ];

                $scheduled = [
                        'fp_dms_sync_data_sources'     => time() + 60,
                        'fp_dms_generate_reports'      => time() + 120,
                        'fp_dms_evaluate_all_segments' => time() + 180,
                        'fp_dms_cache_warmup'          => time() + 240,
                        'fp_dms_daily_digest'          => time() + 300,
                ];

                $wp_mock_functions['wp_next_scheduled'] = static function ( string $hook ) use ( $scheduled ) {
                        return $scheduled[ $hook ] ?? false;
                };

                $result = SiteHealth::test_scheduled_events();

                $this->assertSame( 'good', $result['status'] );
        }

        public function test_scheduled_events_reports_recommended_when_hook_missing(): void {
                global $wp_options, $wp_mock_functions;

                $wp_options['fp_digital_marketing_sync_settings']  = [ 'enable_sync' => true ];
                $wp_options['fp_digital_marketing_email_settings'] = [ 'daily_digest_enabled' => true ];

                $wp_mock_functions['wp_next_scheduled'] = static function ( string $hook ) {
                        return 'fp_dms_generate_reports' === $hook ? false : time() + 60;
                };

                $result = SiteHealth::test_scheduled_events();

                $this->assertSame( 'recommended', $result['status'] );
                $this->assertStringContainsString( 'Generazione report programmati', $result['description'] );
        }
}

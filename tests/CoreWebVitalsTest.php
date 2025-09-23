<?php
/**
 * Core Web Vitals Unit Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\CoreWebVitals;

/**
 * Test class for Core Web Vitals integration
 */
class CoreWebVitalsTest extends TestCase {

        /**
         * Test that demo metrics are stored via MetricsCache::save without errors.
         */
        public function test_fetch_metrics_saves_into_metrics_cache(): void {
                $core_web_vitals = new CoreWebVitals( 'https://example.com', '' );

                $client_id = 55;
                $start_date = '2024-03-01';
                $end_date = '2024-03-28';

                [ $wpdb_mock, $restore_wpdb ] = $this->replace_wpdb_with_spy();

                try {
                        $metrics = $core_web_vitals->fetch_metrics( $client_id, $start_date, $end_date );
                } finally {
                        $restore_wpdb();
                }

                $this->assertIsArray( $metrics );
                $this->assertGreaterThan( 0, $wpdb_mock->insert_calls );
                $this->assertCount( count( $metrics ), $wpdb_mock->records );

                foreach ( $wpdb_mock->records as $record ) {
                        $this->assertEquals( 'wp_fp_metrics_cache', $record['table'] );
                        $this->assertEquals( $client_id, (int) $record['data']['client_id'] );
                        $this->assertEquals( CoreWebVitals::SOURCE_ID, $record['data']['source'] );
                        $this->assertEquals( $start_date . ' 00:00:00', $record['data']['period_start'] );
                        $this->assertEquals( $end_date . ' 23:59:59', $record['data']['period_end'] );

                        $meta = json_decode( (string) $record['data']['meta'], true );
                        $this->assertIsArray( $meta );
                        $this->assertSame( 'https://example.com', $meta['origin_url'] );
                        $this->assertEquals( 75, $meta['percentile'] );
                        $this->assertEquals( '28_days', $meta['collection_period'] );
                        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_start'] );
                        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_end'] );
                }
        }

        /**
         * Ensure datetime inputs are normalized to full-day MySQL timestamps.
         */
        public function test_fetch_metrics_normalizes_datetime_boundaries(): void {
                $core_web_vitals = new CoreWebVitals( 'https://example.com', '' );

                $client_id = 87;
                $start_date = '2024-03-01 12:34:56';
                $end_date = '2024-03-28 05:06:07';

                [ $wpdb_mock, $restore_wpdb ] = $this->replace_wpdb_with_spy();

                try {
                        $metrics = $core_web_vitals->fetch_metrics( $client_id, $start_date, $end_date );
                } finally {
                        $restore_wpdb();
                }

                $this->assertIsArray( $metrics );
                $this->assertGreaterThan( 0, $wpdb_mock->insert_calls );

                foreach ( $wpdb_mock->records as $record ) {
                        $this->assertEquals( '2024-03-01 00:00:00', $record['data']['period_start'] );
                        $this->assertEquals( '2024-03-28 23:59:59', $record['data']['period_end'] );
                        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_start'] );
                        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_end'] );
                }
        }

        /**
         * Replace global $wpdb with a spy capturing insert calls.
         *
         * @return array{0:WPDB_Mock,1:callable} Spy instance and restore callback.
         */
        private function replace_wpdb_with_spy(): array {
                global $wpdb;

                $original_wpdb = $wpdb;

                $wpdb_mock = new class extends WPDB_Mock {
                        /**
                         * Stored insert calls for later assertions.
                         *
                         * @var array<int, array<string, mixed>>
                         */
                        public $records = [];

                        /**
                         * Number of insert operations executed.
                         *
                         * @var int
                         */
                        public $insert_calls = 0;

                        /**
                         * Capture insert invocations.
                         *
                         * @param string     $table  Table name.
                         * @param array      $data   Row data.
                         * @param array|null $format Format definitions.
                         * @return int Insert result.
                         */
                        public function insert( $table, $data, $format = null ) { // phpcs:ignore WordPress.DB
                                $this->insert_calls++;
                                $this->records[] = [
                                        'table'  => $table,
                                        'data'   => $data,
                                        'format' => $format,
                                ];

                                return parent::insert( $table, $data, $format );
                        }
                };

                $wpdb = $wpdb_mock;

                $restore = static function () use ( $original_wpdb ): void {
                        global $wpdb;
                        $wpdb = $original_wpdb;
                };

                return [ $wpdb_mock, $restore ];
        }
}

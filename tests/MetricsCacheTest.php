<?php
/**
 * Tests for MetricsCache model
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Database\MetricsCacheTable;

/**
 * Lightweight wpdb stub for MetricsCache tests.
 */
class MetricsCacheWPDBStub {
        /**
         * WordPress table prefix.
         *
         * @var string
         */
        public string $prefix = 'wp_';

        /**
         * Next insert ID to report.
         *
         * @var int
         */
        public int $next_insert_id = 1;

        /**
         * Last insert ID stored on successful insert.
         *
         * @var int
         */
        public int $insert_id = 0;

        /**
         * Whether insert operations should fail.
         *
         * @var bool
         */
        public bool $insert_should_fail = false;

        /**
         * Result to return for update operations.
         *
         * @var int
         */
        public int $update_result = 1;

        /**
         * Result to return for delete operations.
         *
         * @var int
         */
        public $delete_result = 1;

        /**
         * Row result for get_row queries.
         *
         * @var mixed
         */
        public $get_row_result = null;

        /**
         * Results returned from get_results.
         *
         * @var array
         */
        public array $results = [];

        /**
         * Value returned from get_var.
         *
         * @var mixed
         */
        public $var_result = null;

        /**
         * Last prepared query string.
         *
         * @var string
         */
        public string $prepared_query = '';

        /**
         * Arguments passed to the last prepare call.
         *
         * @var array
         */
        public array $prepare_args = [];

        /**
         * Last executed query string.
         *
         * @var string
         */
        public string $last_query = '';

        /**
         * Capture prepared queries and arguments.
         *
         * @param string $query SQL query.
         * @param mixed  ...$args Query arguments.
         * @return string
         */
        public function prepare( string $query, ...$args ): string {
                $this->prepared_query = $query;
                $this->prepare_args   = $args;

                return $query;
        }

        /**
         * Simulate wpdb::insert behaviour.
         *
         * @return int|false
         */
        public function insert( ...$args ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                if ( $this->insert_should_fail ) {
                        return false;
                }

                $this->insert_id = $this->next_insert_id++;

                return 1;
        }

        /**
         * Simulate wpdb::update behaviour.
         *
         * @return int
         */
        public function update( ...$args ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                return $this->update_result;
        }

        /**
         * Simulate wpdb::delete behaviour.
         *
         * @return int|false
         */
        public function delete( ...$args ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                return $this->delete_result;
        }

        /**
         * Provide row results.
         *
         * @param mixed $query SQL query.
         * @return mixed
         */
        public function get_row( $query ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                $this->last_query = is_string( $query ) ? $query : '';

                return $this->get_row_result;
        }

        /**
         * Provide result sets.
         *
         * @param mixed $query SQL query.
         * @return array
         */
        public function get_results( $query ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                $this->last_query = is_string( $query ) ? $query : '';

                return $this->results;
        }

        /**
         * Provide scalar results.
         *
         * @param mixed $query SQL query.
         * @return mixed
         */
        public function get_var( $query ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                $this->last_query = is_string( $query ) ? $query : '';

                return $this->var_result;
        }

        /**
         * Capture arbitrary queries.
         *
         * @param mixed $query SQL query.
         * @return bool
         */
        public function query( $query ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
                $this->last_query = is_string( $query ) ? $query : '';

                return true;
        }

        /**
         * Provide charset information when required by table helpers.
         *
         * @return string
         */
        public function get_charset_collate(): string {
                return 'utf8_general_ci';
        }
}

/**
 * Test case for MetricsCache CRUD operations.
 */
class MetricsCacheTest extends TestCase {

        /**
         * Previous global wpdb instance.
         *
         * @var mixed
         */
        private $previous_wpdb;

        /**
         * Stubbed wpdb instance.
         *
         * @var MetricsCacheWPDBStub
         */
        private MetricsCacheWPDBStub $wpdb_stub;

        /**
         * Set up test environment.
         */
        protected function setUp(): void {
                parent::setUp();

                global $wpdb;

                $this->previous_wpdb = $wpdb ?? null;
                $this->wpdb_stub     = new MetricsCacheWPDBStub();

                $wpdb = $this->wpdb_stub;
        }

        /**
         * Restore original global state.
         */
        protected function tearDown(): void {
                global $wpdb;

                if ( null !== $this->previous_wpdb ) {
                        $wpdb = $this->previous_wpdb;
                } else {
                        unset( $GLOBALS['wpdb'] );
                }

                parent::tearDown();
        }

        /**
         * Test table name generation.
         */
        public function test_get_table_name(): void {
                $expected = 'wp_fp_metrics_cache';
                $actual   = MetricsCacheTable::get_table_name();

                $this->assertEquals( $expected, $actual );
        }

        /**
         * Test saving a metric record.
         */
        public function test_save_metric(): void {
                $result = MetricsCache::save(
                        123,
                        'google_analytics_4',
                        'sessions',
                        '2024-01-01 00:00:00',
                        '2024-01-31 23:59:59',
                        '1500',
                        [ 'device' => 'desktop' ]
                );

                $this->assertEquals( 1, $result );
        }

        /**
         * Test saving metric with minimal data.
         */
        public function test_save_metric_minimal(): void {
                $result = MetricsCache::save(
                        456,
                        'facebook_ads',
                        'impressions',
                        '2024-02-01 00:00:00',
                        '2024-02-29 23:59:59',
                        '25000'
                );

                $this->assertEquals( 1, $result );
        }

        /**
         * Test saving metric failure.
         */
        public function test_save_metric_failure(): void {
                $this->wpdb_stub->insert_should_fail = true;

                $result = MetricsCache::save(
                        789,
                        'google_ads',
                        'clicks',
                        '2024-03-01 00:00:00',
                        '2024-03-31 23:59:59',
                        '850'
                );

                $this->assertFalse( $result );
        }

        /**
         * Test getting a metric record by ID.
         */
        public function test_get_metric(): void {
                $this->wpdb_stub->get_row_result = (object) [
                        'id'           => 1,
                        'client_id'    => 123,
                        'source'       => 'google_analytics_4',
                        'metric'       => 'sessions',
                        'period_start' => '2024-01-01 00:00:00',
                        'period_end'   => '2024-01-31 23:59:59',
                        'value'        => '1500',
                        'meta'         => '{"device":"desktop"}',
                        'fetched_at'   => '2024-01-31 12:00:00',
                ];

                $result = MetricsCache::get( 1 );

                $this->assertNotNull( $result );
                $this->assertEquals( 1, $result->id );
                $this->assertEquals( [ 'device' => 'desktop' ], $result->meta );
                $this->assertStringContainsString( 'WHERE id = %d', $this->wpdb_stub->prepared_query );
        }

        /**
         * Test getting non-existent metric record.
         */
        public function test_get_metric_not_found(): void {
                $this->wpdb_stub->get_row_result = null;

                $result = MetricsCache::get( 999 );

                $this->assertNull( $result );
        }

        /**
         * Test getting multiple metrics with filters.
         */
        public function test_get_metrics_with_filters(): void {
                $this->wpdb_stub->results = [
                        (object) [
                                'id'           => 1,
                                'client_id'    => 123,
                                'source'       => 'google_analytics_4',
                                'metric'       => 'sessions',
                                'period_start' => '2024-01-01 00:00:00',
                                'period_end'   => '2024-01-31 23:59:59',
                                'value'        => '1500',
                                'meta'         => null,
                                'fetched_at'   => '2024-01-31 12:00:00',
                        ],
                        (object) [
                                'id'           => 2,
                                'client_id'    => 123,
                                'source'       => 'google_analytics_4',
                                'metric'       => 'pageviews',
                                'period_start' => '2024-01-01 00:00:00',
                                'period_end'   => '2024-01-31 23:59:59',
                                'value'        => '4500',
                                'meta'         => null,
                                'fetched_at'   => '2024-01-31 12:00:00',
                        ],
                ];

                $result = MetricsCache::get_metrics( [ 'client_id' => 123 ] );

                $this->assertIsArray( $result );
                $this->assertCount( 2, $result );
                $this->assertEquals( [ 123, 100, 0 ], $this->wpdb_stub->prepare_args );
                $this->assertStringContainsString( 'WHERE client_id = %d', $this->wpdb_stub->prepared_query );
        }

        /**
         * Ensure array filters generate IN clauses with proper placeholders.
         */
        public function test_get_metrics_with_array_filters_uses_in_clause(): void {
                $this->wpdb_stub->results = [];

                MetricsCache::get_metrics(
                        [
                                'client_id' => [ 10, 20 ],
                                'source'    => [ 'google_analytics_4', 'facebook_ads' ],
                                'metric'    => [ 'sessions', 'clicks' ],
                        ]
                );

                $this->assertStringContainsString( 'client_id IN (%d, %d)', $this->wpdb_stub->prepared_query );
                $this->assertStringContainsString( 'source IN (%s, %s)', $this->wpdb_stub->prepared_query );
                $this->assertStringContainsString( 'metric IN (%s, %s)', $this->wpdb_stub->prepared_query );
                $this->assertEquals(
                        [
                                10,
                                20,
                                'google_analytics_4',
                                'facebook_ads',
                                'sessions',
                                'clicks',
                                100,
                                0,
                        ],
                        $this->wpdb_stub->prepare_args
                );
        }

        /**
         * Ensure invalid ordering falls back to safe defaults.
         */
        public function test_get_metrics_invalid_ordering_falls_back_to_default(): void {
                $this->wpdb_stub->results = [];

                MetricsCache::get_metrics(
                        [
                                'order_by' => '1=1; DROP TABLE',
                                'order'    => 'sideways',
                        ]
                );

                $this->assertStringContainsString( 'ORDER BY fetched_at DESC', $this->wpdb_stub->prepared_query );
        }

        /**
         * Test updating a metric record.
         */
        public function test_update_metric(): void {
                $this->wpdb_stub->update_result = 1;

                $result = MetricsCache::update( 1, [
                        'value' => '1800',
                        'meta'  => [ 'device' => 'mobile' ],
                ] );

                $this->assertTrue( $result );
        }

        /**
         * Test updating with invalid fields.
         */
        public function test_update_metric_invalid_fields(): void {
                $result = MetricsCache::update( 1, [ 'invalid_field' => 'test' ] );

                $this->assertFalse( $result );
        }

        /**
         * Test deleting a metric record.
         */
        public function test_delete_metric(): void {
                $this->wpdb_stub->delete_result = 1;

                $result = MetricsCache::delete( 1 );

                $this->assertTrue( $result );
        }

        /**
         * Test deleting by criteria.
         */
        public function test_delete_by_criteria(): void {
                $this->wpdb_stub->delete_result = 3;

                $result = MetricsCache::delete_by_criteria(
                        [
                                'client_id' => 123,
                                'source'    => 'google_analytics_4',
                        ]
                );

                $this->assertEquals( 3, $result );
        }

        /**
         * Test counting records with simple filters.
         */
        public function test_count_metrics(): void {
                $this->wpdb_stub->var_result = '5';

                $result = MetricsCache::count( [ 'client_id' => 123 ] );

                $this->assertEquals( 5, $result );
                $this->assertStringContainsString( 'client_id = %d', $this->wpdb_stub->prepared_query );
        }

        /**
         * Ensure count queries use IN clauses for array filters.
         */
        public function test_count_with_array_filters_uses_in_clause(): void {
                $this->wpdb_stub->var_result = '3';

                $result = MetricsCache::count(
                        [
                                'source' => [ 'google_analytics_4', 'facebook_ads' ],
                                'metric' => [ 'sessions', 'clicks' ],
                        ]
                );

                $this->assertEquals( 3, $result );
                $this->assertStringContainsString( 'source IN (%s, %s)', $this->wpdb_stub->prepared_query );
                $this->assertStringContainsString( 'metric IN (%s, %s)', $this->wpdb_stub->prepared_query );
        }
}

<?php
/**
 * Custom Reports Table Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Database\CustomReportsTable;

/**
 * Simple stub for the WordPress database layer used in tests.
 */
class CustomReportsWPDBStub {
        /**
         * WordPress table prefix.
         *
         * @var string
         */
        public $prefix = 'wp_';

        /**
         * Captured query passed to prepare.
         *
         * @var string
         */
        public $prepared_query = '';

        /**
         * Captured query passed to get_results.
         *
         * @var string
         */
        public $last_query = '';

        /**
         * Captured parameters passed to prepare.
         *
         * @var array
         */
        public $prepare_args = [];

        /**
         * Stubbed results returned from get_results.
         *
         * @var array
         */
        public $results = [];

        /**
         * Capture prepared query and arguments.
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
         * Capture the executed query and return stubbed results.
         *
         * @param string $query SQL query.
         * @param string $output Output format (unused).
         * @return array
         */
        public function get_results( string $query, string $output = ARRAY_A ): array {
                $this->last_query = $query;

                return $this->results;
        }
}

/**
 * Tests for CustomReportsTable.
 */
class CustomReportsTableTest extends TestCase {
        /**
         * Previous $wpdb instance.
         *
         * @var mixed
         */
        private $previous_wpdb;

        /**
         * Stubbed wpdb instance.
         *
         * @var CustomReportsWPDBStub
         */
        private CustomReportsWPDBStub $wpdb_stub;

        /**
         * Set up a stubbed $wpdb before each test.
         */
        protected function setUp(): void {
                parent::setUp();

                global $wpdb;

                $this->previous_wpdb = $wpdb ?? null;
                $this->wpdb_stub     = new CustomReportsWPDBStub();

                $wpdb = $this->wpdb_stub;
        }

        /**
         * Restore the previous $wpdb after each test.
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
         * Ensure invalid ordering arguments fall back to safe defaults for client reports.
         */
        public function test_get_client_reports_invalid_ordering_uses_defaults(): void {
                CustomReportsTable::get_client_reports(
                        123,
                        [
                                'order_by' => 'malicious_column; DROP TABLE reports',
                                'order'    => 'invalid_direction',
                        ]
                );

                $this->assertStringContainsString( 'ORDER BY created_at DESC', $this->wpdb_stub->prepared_query );
                $this->assertStringContainsString( 'ORDER BY created_at DESC', $this->wpdb_stub->last_query );
        }

        /**
         * Ensure invalid ordering arguments fall back to safe defaults for all reports.
         */
        public function test_get_all_reports_invalid_ordering_uses_defaults(): void {
                CustomReportsTable::get_all_reports(
                        [
                                'status'   => 'inactive',
                                'order_by' => '1=1; --',
                                'order'    => 'drop',
                        ]
                );

                $this->assertStringContainsString( 'ORDER BY created_at DESC', $this->wpdb_stub->prepared_query );
                $this->assertStringContainsString( 'ORDER BY created_at DESC', $this->wpdb_stub->last_query );
        }
}

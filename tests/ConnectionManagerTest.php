<?php
/**
 * Connection Manager Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\ConnectionManager;

/**
 * Tests for the ConnectionManager helper.
 */
final class ConnectionManagerTest extends TestCase {
        /**
         * Backup of previously registered mock functions.
         *
         * @var array<string, callable>
         */
        private $previous_wp_mock_functions = [];

        /**
         * Prepare the mock environment before each test.
         */
        protected function setUp(): void {
                parent::setUp();

                global $wp_mock_functions;

                $this->previous_wp_mock_functions = $wp_mock_functions ?? [];
                $wp_mock_functions               = $this->previous_wp_mock_functions;
        }

        /**
         * Restore the mock environment after each test.
         */
        protected function tearDown(): void {
                global $wp_mock_functions;

                $wp_mock_functions               = $this->previous_wp_mock_functions;
                $this->previous_wp_mock_functions = [];

                parent::tearDown();
        }

        /**
         * Ensure Clarity status reports disconnected when no project IDs are configured.
         */
        public function test_clarity_connection_status_disconnected_when_no_projects(): void {
                global $wp_mock_functions;

                $self = $this;

                $wp_mock_functions['get_posts'] = static function ( $args ) use ( $self ) {
                        $self->assertIsArray( $args );
                        $self->assertSame( 'cliente', $args['post_type'] ?? null );
                        $self->assertSame( 'ids', $args['fields'] ?? null );
                        $self->assertIsArray( $args['meta_query'] ?? null );
                        $self->assertSame( 'AND', $args['meta_query']['relation'] ?? null );

                        $clauses = array_values(
                                array_filter(
                                        $args['meta_query'],
                                        static function ( $clause ) {
                                                return is_array( $clause );
                                        }
                                )
                        );

                        $self->assertCount( 2, $clauses );

                        $has_exists    = false;
                        $has_non_empty = false;

                        foreach ( $clauses as $clause ) {
                                if ( isset( $clause['key'], $clause['compare'] ) && 'clarity_project_id' === $clause['key'] ) {
                                        if ( 'EXISTS' === $clause['compare'] ) {
                                                $has_exists = true;
                                        }

                                        if ( '!=' === $clause['compare'] && array_key_exists( 'value', $clause ) && '' === $clause['value'] ) {
                                                $has_non_empty = true;
                                        }
                                }
                        }

                        $self->assertTrue( $has_exists );
                        $self->assertTrue( $has_non_empty );

                        return [];
                };

                $status = ConnectionManager::get_clarity_connection_status();

                $this->assertSame( ConnectionManager::STATUS_DISCONNECTED, $status['status'] );
                $this->assertSame(
                        __( 'Nessun cliente configurato', 'fp-digital-marketing' ),
                        $status['message']
                );
                $this->assertArrayHasKey( 'setup_steps', $status );
                $this->assertArrayHasKey( 'setup_priority', $status );
        }

        /**
         * Ensure only clients with real project IDs are counted as connected.
         */
        public function test_clarity_connection_status_counts_only_clients_with_project_ids(): void {
                global $wp_mock_functions;

                $self = $this;

                $wp_mock_functions['get_posts'] = static function ( $args ) use ( $self ) {
                        $self->assertIsArray( $args );
                        $self->assertIsArray( $args['meta_query'] ?? null );
                        $self->assertSame( 'AND', $args['meta_query']['relation'] ?? null );

                        $clauses = array_values(
                                array_filter(
                                        $args['meta_query'],
                                        static function ( $clause ) {
                                                return is_array( $clause );
                                        }
                                )
                        );

                        $self->assertCount( 2, $clauses );

                        $has_exists    = false;
                        $has_non_empty = false;

                        foreach ( $clauses as $clause ) {
                                if ( isset( $clause['key'], $clause['compare'] ) && 'clarity_project_id' === $clause['key'] ) {
                                        if ( 'EXISTS' === $clause['compare'] ) {
                                                $has_exists = true;
                                        }

                                        if ( '!=' === $clause['compare'] && array_key_exists( 'value', $clause ) && '' === $clause['value'] ) {
                                                $has_non_empty = true;
                                        }
                                }
                        }

                        $self->assertTrue( $has_exists );
                        $self->assertTrue( $has_non_empty );

                        return [ 10, 11, 12 ];
                };

                $project_meta = [
                        10 => 'abc123',
                        11 => '   ',
                        12 => " 987654 ",
                ];

                $wp_mock_functions['get_post_meta'] = static function ( $post_id, $key, $single = false ) use ( $project_meta ) {
                        return $project_meta[ $post_id ] ?? '';
                };

                $status = ConnectionManager::get_clarity_connection_status();

                $this->assertSame( ConnectionManager::STATUS_CONNECTED, $status['status'] );
                $this->assertSame( 2, $status['client_count'] );
                $this->assertSame(
                        sprintf(
                                _n(
                                        '%d cliente configurato',
                                        '%d clienti configurati',
                                        2,
                                        'fp-digital-marketing'
                                ),
                                2
                        ),
                        $status['message']
                );
        }
}

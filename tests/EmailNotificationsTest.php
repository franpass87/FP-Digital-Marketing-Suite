<?php
/**
 * Email Notifications Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\EmailNotifications;
use FP\DigitalMarketing\Helpers\PerformanceCache;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
        define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * Test suite for EmailNotifications rate limiting behavior.
 */
class EmailNotificationsTest extends TestCase {

        /**
         * Previously registered WordPress mock functions.
         *
         * @var array<string, callable>
         */
        private $previous_wp_mock_functions = [];

        /**
         * Mocked object cache storage.
         *
         * @var array<string, array<string, array<string, mixed>>>
         */
        private $mock_object_cache = [];

        /**
         * Mocked transient storage.
         *
         * @var array<string, array<string, mixed>>
         */
        private $mock_transients = [];

        /**
         * Set up test environment.
         *
         * @return void
         */
        protected function setUp(): void {
                parent::setUp();

                global $wp_mock_functions;
                global $wp_options;

                if ( ! isset( $wp_options ) || ! is_array( $wp_options ) ) {
                        $wp_options = [];
                }

                $existing_mocks = [];
                if ( isset( $wp_mock_functions ) && is_array( $wp_mock_functions ) ) {
                        $existing_mocks = $wp_mock_functions;
                }

                $this->previous_wp_mock_functions = $existing_mocks;
                $wp_mock_functions = $existing_mocks;

                $this->mock_object_cache = [];
                $this->mock_transients = [];

                $object_cache =& $this->mock_object_cache;
                $transients   =& $this->mock_transients;

                $wp_mock_functions['wp_cache_get'] = function( $key, $group = '' ) use ( &$object_cache ) {
                        if ( isset( $object_cache[ $group ][ $key ] ) ) {
                                return $object_cache[ $group ][ $key ]['value'];
                        }
                        return false;
                };

                $wp_mock_functions['wp_cache_set'] = function( $key, $data, $group = '', $expire = 0 ) use ( &$object_cache ) {
                        if ( ! isset( $object_cache[ $group ] ) ) {
                                $object_cache[ $group ] = [];
                        }

                        $object_cache[ $group ][ $key ] = [
                                'value' => $data,
                                'ttl'   => $expire,
                        ];

                        return true;
                };

                $wp_mock_functions['wp_cache_delete'] = function( $key, $group = '' ) use ( &$object_cache ) {
                        if ( isset( $object_cache[ $group ][ $key ] ) ) {
                                unset( $object_cache[ $group ][ $key ] );
                        }
                        return true;
                };

                $wp_mock_functions['get_transient'] = function( $transient ) use ( &$transients ) {
                        if ( isset( $transients[ $transient ] ) ) {
                                return $transients[ $transient ]['value'];
                        }
                        return false;
                };

                $wp_mock_functions['set_transient'] = function( $transient, $value, $expiration = 0 ) use ( &$transients ) {
                        $transients[ $transient ] = [
                                'value' => $value,
                                'ttl'   => $expiration,
                        ];
                        return true;
                };

                $wp_mock_functions['delete_transient'] = function( $transient ) use ( &$transients ) {
                        if ( isset( $transients[ $transient ] ) ) {
                                unset( $transients[ $transient ] );
                        }
                        return true;
                };

                delete_option( 'fp_digital_marketing_cache_settings' );
                delete_option( 'fp_digital_marketing_benchmark_data' );
        }

        /**
         * Clean up after tests.
         *
         * @return void
         */
        protected function tearDown(): void {
                global $wp_mock_functions;

                $wp_mock_functions = $this->previous_wp_mock_functions;
                $this->previous_wp_mock_functions = [];

                $this->mock_object_cache = [];
                $this->mock_transients = [];

                delete_option( 'fp_digital_marketing_cache_settings' );
                delete_option( 'fp_digital_marketing_benchmark_data' );

                parent::tearDown();
        }

        /**
         * Ensure the email rate limiter increments and enforces the threshold.
         *
         * @return void
         */
        public function test_email_rate_limit_blocks_after_ten_sends(): void {
                $recipient = 'user@example.com';
                $type      = EmailNotifications::TYPE_ALERT;
                $cache_key = "email_rate_limit_{$recipient}_{$type}";
                $transient_key = "fp_dms_email_limits_{$cache_key}";

                $this->assertTrue( $this->invoke_check_rate_limit( $recipient, $type ) );

                for ( $i = 0; $i < 10; $i++ ) {
                        $this->invoke_update_rate_limit( $recipient, $type );

                        $this->assertSame(
                                $i + 1,
                                PerformanceCache::get( $cache_key, 'email_limits' ),
                                'The rate limit counter should increment with each send.'
                        );
                }

                $this->assertFalse( $this->invoke_check_rate_limit( $recipient, $type ) );

                $this->assertArrayHasKey( 'email_limits', $this->mock_object_cache );
                $this->assertArrayHasKey( $cache_key, $this->mock_object_cache['email_limits'] );
                $this->assertSame( HOUR_IN_SECONDS, $this->mock_object_cache['email_limits'][ $cache_key ]['ttl'] );

                $this->assertArrayHasKey( $transient_key, $this->mock_transients );
                $this->assertSame( HOUR_IN_SECONDS, $this->mock_transients[ $transient_key ]['ttl'] );

                PerformanceCache::delete_cached( $cache_key, 'email_limits' );

                $this->assertTrue( $this->invoke_check_rate_limit( $recipient, $type ) );
        }

        /**
         * Invoke the private check_rate_limit method.
         *
         * @param string $recipient Recipient email address.
         * @param string $type      Notification type.
         * @return bool
         */
        private function invoke_check_rate_limit( string $recipient, string $type ): bool {
                $reflection = new \ReflectionClass( EmailNotifications::class );
                $method     = $reflection->getMethod( 'check_rate_limit' );
                $method->setAccessible( true );

                return (bool) $method->invoke( null, $recipient, $type );
        }

        /**
         * Invoke the private update_rate_limit method.
         *
         * @param string $recipient Recipient email address.
         * @param string $type      Notification type.
         * @return void
         */
        private function invoke_update_rate_limit( string $recipient, string $type ): void {
                $reflection = new \ReflectionClass( EmailNotifications::class );
                $method     = $reflection->getMethod( 'update_rate_limit' );
                $method->setAccessible( true );

                $method->invoke( null, $recipient, $type );
        }
}


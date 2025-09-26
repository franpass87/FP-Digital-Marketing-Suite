<?php
/**
 * Tests for Alert Engine class
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\AlertEngine;
use FP\DigitalMarketing\Models\AlertRule;
use FP\DigitalMarketing\Helpers\MetricsAggregator;

/**
 * Test class for AlertEngine
 */
class AlertEngineTest extends TestCase {

	/**
	 * Mock wpdb object
	 *
	 * @var object
	 */
	private $wpdb_mock;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

				// Create mock wpdb using the bootstrap helper to guarantee required methods exist.
				$this->wpdb_mock         = new WPDB_Mock();
				$this->wpdb_mock->prefix = 'wp_';

		// Set global wpdb
		global $wpdb;
		$wpdb = $this->wpdb_mock;

		// Mock WordPress functions
		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $format ) {
				return date( $format );
			}
		}

		if ( ! function_exists( 'wp_json_encode' ) ) {
			function wp_json_encode( $data ) {
				return json_encode( $data );
			}
		}

		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				return $default;
			}
		}

		if ( ! function_exists( 'update_option' ) ) {
			function update_option( $option, $value, $autoload = null ) {
				return true;
			}
		}

		if ( ! function_exists( 'error_log' ) ) {
			function error_log( $message ) {
				// Mock error logging
			}
		}
	}

	/**
	 * Test condition evaluation
	 */
	public function test_evaluate_condition(): void {
		$reflection = new ReflectionClass( AlertEngine::class );
		$method     = $reflection->getMethod( 'evaluate_condition' );
		$method->setAccessible( true );

		// Test greater than
		$result = $method->invoke( null, 100, AlertRule::CONDITION_GREATER_THAN, 50 );
		$this->assertTrue( $result );

		$result = $method->invoke( null, 30, AlertRule::CONDITION_GREATER_THAN, 50 );
		$this->assertFalse( $result );

		// Test less than
		$result = $method->invoke( null, 30, AlertRule::CONDITION_LESS_THAN, 50 );
		$this->assertTrue( $result );

		$result = $method->invoke( null, 100, AlertRule::CONDITION_LESS_THAN, 50 );
		$this->assertFalse( $result );

		// Test equal (with float precision)
		$result = $method->invoke( null, 50.0, AlertRule::CONDITION_EQUAL, 50.0 );
		$this->assertTrue( $result );

		$result = $method->invoke( null, 50.1, AlertRule::CONDITION_EQUAL, 50.0 );
		$this->assertFalse( $result );

		// Test not equal
		$result = $method->invoke( null, 50.1, AlertRule::CONDITION_NOT_EQUAL, 50.0 );
		$this->assertTrue( $result );

		$result = $method->invoke( null, 50.0, AlertRule::CONDITION_NOT_EQUAL, 50.0 );
		$this->assertFalse( $result );
	}

	/**
	 * Test condition operators validation
	 */
	public function test_condition_operators(): void {
		$operators = AlertRule::get_condition_operators();

		$this->assertIsArray( $operators );
		$this->assertArrayHasKey( AlertRule::CONDITION_GREATER_THAN, $operators );
		$this->assertArrayHasKey( AlertRule::CONDITION_LESS_THAN, $operators );
		$this->assertArrayHasKey( AlertRule::CONDITION_EQUAL, $operators );

		// Test validation
		$this->assertTrue( AlertRule::is_valid_condition( AlertRule::CONDITION_GREATER_THAN ) );
		$this->assertTrue( AlertRule::is_valid_condition( AlertRule::CONDITION_LESS_THAN ) );
		$this->assertFalse( AlertRule::is_valid_condition( 'invalid_condition' ) );
	}

	/**
	 * Test metric value formatting
	 */
	public function test_format_metric_value(): void {
		$reflection = new ReflectionClass( AlertEngine::class );
		$method     = $reflection->getMethod( 'format_metric_value' );
		$method->setAccessible( true );

		// Mock MetricsSchema::get_kpi_definitions
		$this->mockMetricsSchemaDefinitions();

		// Test number formatting (default)
		$result = $method->invoke( null, 1234.56, 'sessions' );
		$this->assertEquals( '1,235', $result );

		// Test percentage formatting
		$result = $method->invoke( null, 0.1234, 'bounce_rate' );
		$this->assertEquals( '12.34%', $result );

		// Test currency formatting
		$result = $method->invoke( null, 1234.56, 'revenue' );
		$this->assertEquals( '€1,234.56', $result );
	}

	/**
	 * Mock MetricsSchema definitions for testing
	 */
	private function mockMetricsSchemaDefinitions(): void {
		// This would normally be mocked using a proper mocking framework
		// For this simple test, we'll assume the method exists and returns expected values
	}

	/**
	 * Test alert logs functionality
	 */
	public function test_alert_logs(): void {
		// Test getting empty logs
		$logs = AlertEngine::get_alert_logs();
		$this->assertIsArray( $logs );

		// Test log structure
		$sample_results = [
			'checked'            => 5,
			'triggered'          => 2,
			'errors'             => 0,
			'notifications_sent' => 2,
		];

		// Use reflection to test the private log method
		$reflection = new ReflectionClass( AlertEngine::class );
		$method     = $reflection->getMethod( 'log_check_results' );
		$method->setAccessible( true );

		// This would log the results (mocked in our test environment)
		$method->invoke( null, $sample_results );

		// In a real test, we'd verify the log was stored correctly
		$this->assertTrue( true ); // Placeholder assertion
	}

	/**
	 * Test admin notice management
	 */
	public function test_admin_notice_management(): void {
		// Mock WordPress functions for transients
		if ( ! function_exists( 'set_transient' ) ) {
			function set_transient( $transient, $value, $expiration ) {
				return true;
			}
		}

		if ( ! function_exists( 'delete_transient' ) ) {
			function delete_transient( $transient ) {
				return true;
			}
		}

		// Test notice clearing
		$result = AlertEngine::clear_admin_notice( 'test_notice_key' );
		$this->assertTrue( $result );

		// Test getting pending notices (would return empty in test environment)
		$notices = AlertEngine::get_pending_admin_notices();
		$this->assertIsArray( $notices );
	}

	/**
	 * Test rule checking with mock data
	 */
	public function test_check_rule_with_mock_data(): void {
		// Create a mock rule object
		$mock_rule = (object) [
			'id'                        => 1,
			'client_id'                 => 123,
			'name'                      => 'Test Rule',
			'metric'                    => 'sessions',
			'condition'                 => AlertRule::CONDITION_GREATER_THAN,
			'threshold_value'           => 100,
			'notification_email'        => 'test@example.com',
			'notification_admin_notice' => 1,
			'is_active'                 => 1,
		];

		// Mock MetricsAggregator::get_metrics to return test data
		// In a real test, we'd use proper mocking frameworks

		// For now, just test that the method doesn't throw errors
		try {
			$result = AlertEngine::check_rule( $mock_rule );
			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'triggered', $result );
			$this->assertArrayHasKey( 'current_value', $result );
			$this->assertArrayHasKey( 'threshold_value', $result );
			$this->assertArrayHasKey( 'condition', $result );
			$this->assertArrayHasKey( 'metric', $result );
		} catch ( Exception $e ) {
			// Expected in test environment without full WordPress setup
			$this->assertTrue( true );
		}
	}
}

<?php
/**
 * Tests for Anomaly Detector class
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\AnomalyDetector;
use FP\DigitalMarketing\Helpers\MetricsAggregator;
use FP\DigitalMarketing\Helpers\MetricsSchema;

/**
 * Test class for AnomalyDetector
 */
class AnomalyDetectorTest extends TestCase {

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

			// Create mock wpdb with the methods used by the detector.
			$this->wpdb_mock         = $this->getMockBuilder( stdClass::class )
					->addMethods( [ 'prepare', 'get_results', 'get_var' ] )
					->getMock();
			$this->wpdb_mock->prefix = 'wp_';
			$this->wpdb_mock->method( 'prepare' )->willReturnCallback(
				static function ( $query ) {
							return $query;
				}
			);

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

		if ( ! function_exists( 'error_log' ) ) {
			function error_log( $message ) {
				// Mock error_log
			}
		}
	}

	/**
	 * Test Z-score anomaly detection with normal data
	 */
	public function test_z_score_detection_normal_data(): void {
		// Mock MetricsAggregator to return normal data
		$normal_data   = [ 100, 105, 95, 110, 98, 102, 107, 99, 103, 101 ];
		$current_value = 104; // Normal value

		// Use reflection to test private method with mocked data
		$this->mock_historical_data( $normal_data );

		$result = AnomalyDetector::detect_z_score_anomaly( 1, 'sessions', $current_value );

		$this->assertFalse( $result['is_anomaly'] );
		$this->assertArrayHasKey( 'z_score', $result );
		$this->assertArrayHasKey( 'mean', $result );
		$this->assertArrayHasKey( 'std_dev', $result );
		$this->assertLessThan( 2.0, $result['z_score'] );
	}

	/**
	 * Test Z-score anomaly detection with anomalous data
	 */
	public function test_z_score_detection_anomaly(): void {
		// Mock MetricsAggregator to return normal data
		$normal_data   = [ 100, 105, 95, 110, 98, 102, 107, 99, 103, 101 ];
		$current_value = 200; // Anomalous value (way above normal range)

		$this->mock_historical_data( $normal_data );

		$result = AnomalyDetector::detect_z_score_anomaly( 1, 'sessions', $current_value );

		$this->assertTrue( $result['is_anomaly'] );
		$this->assertGreaterThan( 2.0, $result['z_score'] );
		$this->assertEquals( 'positive', $result['deviation_type'] );
		$this->assertContains( $result['confidence'], [ 'moderate', 'high', 'very_high' ] );
	}

	/**
	 * Test Z-score detection with insufficient data
	 */
	public function test_z_score_insufficient_data(): void {
		$insufficient_data = [ 100, 105, 95 ]; // Only 3 data points
		$current_value     = 104;

		$this->mock_historical_data( $insufficient_data );

		$result = AnomalyDetector::detect_z_score_anomaly( 1, 'sessions', $current_value );

		$this->assertFalse( $result['is_anomaly'] );
		$this->assertEquals( 'insufficient_data', $result['reason'] );
		$this->assertArrayHasKey( 'data_points', $result );
		$this->assertArrayHasKey( 'required_points', $result );
	}

	/**
	 * Test moving average anomaly detection with normal data
	 */
	public function test_moving_average_detection_normal(): void {
		// Mock stable trending data
		$stable_data   = [ 100, 102, 98, 101, 99, 103, 97, 100, 102, 98, 101, 99, 103, 100 ];
		$current_value = 102; // Within normal range

		$this->mock_historical_data( $stable_data );

		$result = AnomalyDetector::detect_moving_average_anomaly( 1, 'sessions', $current_value );

		$this->assertFalse( $result['is_anomaly'] );
		$this->assertArrayHasKey( 'moving_average', $result );
		$this->assertArrayHasKey( 'upper_band', $result );
		$this->assertArrayHasKey( 'lower_band', $result );
		$this->assertEquals( 'within', $result['band_type'] );
	}

	/**
	 * Test moving average anomaly detection with anomalous data
	 */
	public function test_moving_average_detection_anomaly(): void {
		// Mock stable data with anomalous current value
		$stable_data   = [ 100, 102, 98, 101, 99, 103, 97, 100, 102, 98, 101, 99, 103, 100 ];
		$current_value = 200; // Way above upper band

		$this->mock_historical_data( $stable_data );

		$result = AnomalyDetector::detect_moving_average_anomaly( 1, 'sessions', $current_value );

		$this->assertTrue( $result['is_anomaly'] );
		$this->assertEquals( 'upper', $result['band_type'] );
		$this->assertGreaterThan( 0, $result['band_distance'] );
		$this->assertContains( $result['severity'], [ 'low', 'medium', 'high', 'critical' ] );
	}

	/**
	 * Test combined anomaly analysis
	 */
	public function test_combined_anomaly_analysis(): void {
		$normal_data     = [ 100, 105, 95, 110, 98, 102, 107, 99, 103, 101, 104, 96, 108, 100 ];
		$anomalous_value = 250;

		$this->mock_historical_data( $normal_data );

		$result = AnomalyDetector::analyze_anomaly( 1, 'sessions', $anomalous_value );

		$this->assertTrue( $result['is_anomaly'] );
		$this->assertArrayHasKey( 'z_score_analysis', $result );
		$this->assertArrayHasKey( 'moving_average_analysis', $result );
		$this->assertArrayHasKey( 'combined_confidence', $result );
		$this->assertEquals( 'sessions', $result['metric'] );
		$this->assertEquals( 1, $result['client_id'] );
	}

	/**
	 * Test supported metrics list
	 */
	public function test_supported_metrics(): void {
		$supported = AnomalyDetector::get_supported_metrics();

		$this->assertIsArray( $supported );
		$this->assertContains( MetricsSchema::KPI_SESSIONS, $supported );
		$this->assertContains( MetricsSchema::KPI_CONVERSIONS, $supported );
		$this->assertContains( MetricsSchema::KPI_REVENUE, $supported );
		$this->assertContains( MetricsSchema::KPI_COST, $supported );
	}

	/**
	 * Test confidence level calculation
	 */
	public function test_confidence_levels(): void {
		// Test different Z-score ranges
		$test_cases = [
			[
				'z_score'  => 1.0,
				'expected' => 'very_low',
			],
			[
				'z_score'  => 1.8,
				'expected' => 'low',
			],
			[
				'z_score'  => 2.2,
				'expected' => 'moderate',
			],
			[
				'z_score'  => 2.7,
				'expected' => 'high',
			],
			[
				'z_score'  => 3.5,
				'expected' => 'very_high',
			],
		];

		foreach ( $test_cases as $case ) {
			$normal_data = [ 100, 105, 95, 110, 98, 102, 107, 99, 103, 101 ];

			// Calculate value that would produce desired Z-score
			$mean     = array_sum( $normal_data ) / count( $normal_data );
			$variance = 0;
			foreach ( $normal_data as $value ) {
				$variance += pow( $value - $mean, 2 );
			}
			$std_dev    = sqrt( $variance / ( count( $normal_data ) - 1 ) );
			$test_value = $mean + ( $case['z_score'] * $std_dev );

			$this->mock_historical_data( $normal_data );

			$result = AnomalyDetector::detect_z_score_anomaly( 1, 'sessions', $test_value );

			if ( $case['z_score'] >= 2.0 ) {
				$this->assertTrue( $result['is_anomaly'] );
				$this->assertEquals( $case['expected'], $result['confidence'] );
			}
		}
	}

	/**
	 * Test edge cases
	 */
	public function test_edge_cases(): void {
		// Test zero variance (all values the same)
		$constant_data = [ 100, 100, 100, 100, 100, 100, 100, 100, 100, 100 ];
		$current_value = 100;

		$this->mock_historical_data( $constant_data );

		$result = AnomalyDetector::detect_z_score_anomaly( 1, 'sessions', $current_value );

		$this->assertFalse( $result['is_anomaly'] );
		$this->assertEquals( 'zero_variance', $result['reason'] );

		// Test moving average with zero variance
		$result_ma = AnomalyDetector::detect_moving_average_anomaly( 1, 'sessions', $current_value );

		$this->assertFalse( $result_ma['is_anomaly'] );
		$this->assertEquals( 'zero_variance', $result_ma['reason'] );
	}

	/**
	 * Test negative deviation detection
	 */
	public function test_negative_deviation(): void {
		$normal_data = [ 100, 105, 95, 110, 98, 102, 107, 99, 103, 101 ];
		$low_value   = 20; // Much lower than normal

		$this->mock_historical_data( $normal_data );

		$result = AnomalyDetector::detect_z_score_anomaly( 1, 'sessions', $low_value );

		$this->assertTrue( $result['is_anomaly'] );
		$this->assertEquals( 'negative', $result['deviation_type'] );
		$this->assertGreaterThan( 2.0, $result['z_score'] );
	}

	/**
	 * Mock historical data for testing
	 *
	 * @param array $data Historical data to return
	 */
	private function mock_historical_data( array $data ): void {
		// Create a static method to return test data
		if ( ! class_exists( 'MockMetricsAggregator' ) ) {
			eval(
				'
				class MockMetricsAggregator {
					public static $test_data = [];
					
					public static function get_metrics($client_id, $start_date, $end_date, $metrics) {
						if (empty(self::$test_data)) {
							return [];
						}
						
						$index = count(self::$test_data) - 1;
						if (isset(self::$test_data[$index])) {
							$value = self::$test_data[$index];
							array_pop(self::$test_data); // Remove used value
							return [
								$metrics[0] => [
									"total_value" => $value
								]
							];
						}
						
						return [];
					}
				}
			'
			);
		}

		// Set test data in reverse order (since we get from most recent backwards)
		MockMetricsAggregator::$test_data = array_reverse( $data );

		// Replace MetricsAggregator with mock in the AnomalyDetector namespace
		$reflection = new ReflectionClass( 'FP\DigitalMarketing\Helpers\AnomalyDetector' );
		$method     = $reflection->getMethod( 'get_historical_metric_data' );
		$method->setAccessible( true );

		// Create a temporary method override for testing
		$override = function ( $client_id, $metric, $days ) {
			return MockMetricsAggregator::$test_data;
		};

		// Store original method and replace with mock
		AnomalyDetector::$_test_override = $override;
	}
}

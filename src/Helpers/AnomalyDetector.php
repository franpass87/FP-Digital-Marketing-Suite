<?php
/**
 * Anomaly Detector for Digital Marketing Suite
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use Exception;

/**
 * AnomalyDetector class for statistical anomaly detection
 *
 * This class implements various statistical methods for detecting anomalies
 * in digital marketing metrics including Z-score and moving average band analysis.
 */
class AnomalyDetector {

	/**
	 * Default number of days to use for historical analysis
	 */
	private const DEFAULT_HISTORICAL_DAYS = 30;

	/**
	 * Default Z-score threshold for anomaly detection
	 */
	private const DEFAULT_Z_SCORE_THRESHOLD = 2.0;

	/**
	 * Default standard deviations for moving average bands
	 */
	private const DEFAULT_BAND_DEVIATIONS = 2.0;

	/**
	 * Minimum number of data points required for reliable detection
	 */
	private const MIN_DATA_POINTS = 7;

	/**
	 * Detect anomalies using Z-score analysis
	 *
	 * @param int $client_id Client ID
	 * @param string $metric Metric name
	 * @param float $current_value Current metric value
	 * @param array $options Optional parameters (threshold, historical_days)
	 * @return array Anomaly detection result
	 */
	public static function detect_z_score_anomaly( int $client_id, string $metric, float $current_value, array $options = [] ): array {
		$threshold = $options['threshold'] ?? self::DEFAULT_Z_SCORE_THRESHOLD;
		$historical_days = $options['historical_days'] ?? self::DEFAULT_HISTORICAL_DAYS;

		// Get historical data
		$historical_data = self::get_historical_metric_data( $client_id, $metric, $historical_days );

		if ( count( $historical_data ) < self::MIN_DATA_POINTS ) {
			return [
				'is_anomaly' => false,
				'reason' => 'insufficient_data',
				'data_points' => count( $historical_data ),
				'required_points' => self::MIN_DATA_POINTS,
			];
		}

		// Calculate statistics
		$mean = array_sum( $historical_data ) / count( $historical_data );
		$variance = self::calculate_variance( $historical_data, $mean );
		$std_dev = sqrt( $variance );

		// Avoid division by zero
		if ( $std_dev == 0 ) {
			return [
				'is_anomaly' => false,
				'reason' => 'zero_variance',
				'mean' => $mean,
				'std_dev' => $std_dev,
			];
		}

		// Calculate Z-score
		$z_score = abs( ( $current_value - $mean ) / $std_dev );
		$is_anomaly = $z_score > $threshold;

		return [
			'is_anomaly' => $is_anomaly,
			'z_score' => round( $z_score, 4 ),
			'threshold' => $threshold,
			'current_value' => $current_value,
			'mean' => round( $mean, 4 ),
			'std_dev' => round( $std_dev, 4 ),
			'deviation_type' => $current_value > $mean ? 'positive' : 'negative',
			'data_points' => count( $historical_data ),
			'confidence' => self::calculate_confidence_level( $z_score ),
		];
	}

	/**
	 * Detect anomalies using moving average bands
	 *
	 * @param int $client_id Client ID
	 * @param string $metric Metric name
	 * @param float $current_value Current metric value
	 * @param array $options Optional parameters (band_deviations, window_size, historical_days)
	 * @return array Anomaly detection result
	 */
	public static function detect_moving_average_anomaly( int $client_id, string $metric, float $current_value, array $options = [] ): array {
		$band_deviations = $options['band_deviations'] ?? self::DEFAULT_BAND_DEVIATIONS;
		$window_size = $options['window_size'] ?? 7; // 7-day moving average
		$historical_days = $options['historical_days'] ?? self::DEFAULT_HISTORICAL_DAYS;

		// Get historical data
		$historical_data = self::get_historical_metric_data( $client_id, $metric, $historical_days );

		if ( count( $historical_data ) < max( self::MIN_DATA_POINTS, $window_size ) ) {
			return [
				'is_anomaly' => false,
				'reason' => 'insufficient_data',
				'data_points' => count( $historical_data ),
				'required_points' => max( self::MIN_DATA_POINTS, $window_size ),
			];
		}

		// Calculate moving averages and bands
		$moving_averages = self::calculate_moving_averages( $historical_data, $window_size );
		$latest_ma = end( $moving_averages );

		// Calculate standard deviation of moving averages
		$ma_std_dev = self::calculate_standard_deviation( $moving_averages );

		if ( $ma_std_dev == 0 ) {
			return [
				'is_anomaly' => false,
				'reason' => 'zero_variance',
				'moving_average' => $latest_ma,
				'std_dev' => $ma_std_dev,
			];
		}

		// Calculate upper and lower bands
		$upper_band = $latest_ma + ( $band_deviations * $ma_std_dev );
		$lower_band = $latest_ma - ( $band_deviations * $ma_std_dev );

		// Check if current value is outside bands
		$is_anomaly = $current_value > $upper_band || $current_value < $lower_band;

		// Calculate how far outside the bands (if anomaly)
		$band_distance = 0;
		$band_type = 'within';
		
		if ( $current_value > $upper_band ) {
			$band_distance = $current_value - $upper_band;
			$band_type = 'upper';
		} elseif ( $current_value < $lower_band ) {
			$band_distance = $lower_band - $current_value;
			$band_type = 'lower';
		}

		return [
			'is_anomaly' => $is_anomaly,
			'current_value' => $current_value,
			'moving_average' => round( $latest_ma, 4 ),
			'upper_band' => round( $upper_band, 4 ),
			'lower_band' => round( $lower_band, 4 ),
			'band_distance' => round( $band_distance, 4 ),
			'band_type' => $band_type,
			'band_deviations' => $band_deviations,
			'window_size' => $window_size,
			'data_points' => count( $historical_data ),
			'severity' => self::calculate_severity( $band_distance, $ma_std_dev ),
		];
	}

	/**
	 * Get historical metric data for analysis
	 *
	 * @param int $client_id Client ID
	 * @param string $metric Metric name
	 * @param int $days Number of days of historical data
	 * @return array Array of daily metric values
	 */
	private static function get_historical_metric_data( int $client_id, string $metric, int $days ): array {
		$historical_data = [];
		$end_date = current_time( 'mysql' );

		// Get daily metrics for the specified period
		for ( $i = 1; $i <= $days; $i++ ) {
			$day_end = date( 'Y-m-d 23:59:59', strtotime( "-{$i} days", strtotime( $end_date ) ) );
			$day_start = date( 'Y-m-d 00:00:00', strtotime( "-{$i} days", strtotime( $end_date ) ) );

			try {
				$metrics = MetricsAggregator::get_metrics( $client_id, $day_start, $day_end, [ $metric ] );
				
				if ( isset( $metrics[ $metric ] ) ) {
					$historical_data[] = (float) $metrics[ $metric ]['total_value'];
				} else {
					// Use fallback value if no data available
					$historical_data[] = 0.0;
				}
			} catch ( Exception $e ) {
				// Log error and use fallback value
				error_log( 'FP Digital Marketing Anomaly Detection Error: ' . $e->getMessage() );
				$historical_data[] = 0.0;
			}
		}

		// Reverse to get chronological order (oldest first)
		return array_reverse( $historical_data );
	}

	/**
	 * Calculate variance of a dataset
	 *
	 * @param array $data Dataset
	 * @param float $mean Mean value
	 * @return float Variance
	 */
	private static function calculate_variance( array $data, float $mean ): float {
		$variance = 0.0;
		$count = count( $data );

		foreach ( $data as $value ) {
			$variance += pow( $value - $mean, 2 );
		}

		return $count > 1 ? $variance / ( $count - 1 ) : 0.0; // Sample variance
	}

	/**
	 * Calculate standard deviation of a dataset
	 *
	 * @param array $data Dataset
	 * @return float Standard deviation
	 */
	private static function calculate_standard_deviation( array $data ): float {
		if ( count( $data ) < 2 ) {
			return 0.0;
		}

		$mean = array_sum( $data ) / count( $data );
		$variance = self::calculate_variance( $data, $mean );
		
		return sqrt( $variance );
	}

	/**
	 * Calculate moving averages for a dataset
	 *
	 * @param array $data Dataset
	 * @param int $window_size Window size for moving average
	 * @return array Array of moving averages
	 */
	private static function calculate_moving_averages( array $data, int $window_size ): array {
		$moving_averages = [];
		$data_count = count( $data );

		for ( $i = $window_size - 1; $i < $data_count; $i++ ) {
			$window = array_slice( $data, $i - $window_size + 1, $window_size );
			$moving_averages[] = array_sum( $window ) / $window_size;
		}

		return $moving_averages;
	}

	/**
	 * Calculate confidence level based on Z-score
	 *
	 * @param float $z_score Z-score value
	 * @return string Confidence level description
	 */
	private static function calculate_confidence_level( float $z_score ): string {
		if ( $z_score >= 3.0 ) {
			return 'very_high'; // 99.7%
		} elseif ( $z_score >= 2.5 ) {
			return 'high'; // 98.8%
		} elseif ( $z_score >= 2.0 ) {
			return 'moderate'; // 95.4%
		} elseif ( $z_score >= 1.5 ) {
			return 'low'; // 86.6%
		} else {
			return 'very_low';
		}
	}

	/**
	 * Calculate severity based on band distance
	 *
	 * @param float $band_distance Distance from band
	 * @param float $std_dev Standard deviation
	 * @return string Severity level
	 */
	private static function calculate_severity( float $band_distance, float $std_dev ): string {
		if ( $std_dev == 0 ) {
			return 'unknown';
		}

		$relative_distance = $band_distance / $std_dev;

		if ( $relative_distance >= 3.0 ) {
			return 'critical';
		} elseif ( $relative_distance >= 2.0 ) {
			return 'high';
		} elseif ( $relative_distance >= 1.0 ) {
			return 'medium';
		} else {
			return 'low';
		}
	}

	/**
	 * Perform comprehensive anomaly analysis using multiple methods
	 *
	 * @param int $client_id Client ID
	 * @param string $metric Metric name
	 * @param float $current_value Current metric value
	 * @param array $options Optional parameters
	 * @return array Comprehensive analysis result
	 */
	public static function analyze_anomaly( int $client_id, string $metric, float $current_value, array $options = [] ): array {
		$z_score_result = self::detect_z_score_anomaly( $client_id, $metric, $current_value, $options );
		$moving_avg_result = self::detect_moving_average_anomaly( $client_id, $metric, $current_value, $options );

		// Determine overall anomaly status
		$is_anomaly = $z_score_result['is_anomaly'] || $moving_avg_result['is_anomaly'];
		
		// Calculate combined confidence
		$combined_confidence = 'normal';
		if ( $is_anomaly ) {
			if ( $z_score_result['is_anomaly'] && $moving_avg_result['is_anomaly'] ) {
				$combined_confidence = 'very_high';
			} elseif ( $z_score_result['is_anomaly'] || $moving_avg_result['is_anomaly'] ) {
				$combined_confidence = 'moderate';
			}
		}

		return [
			'is_anomaly' => $is_anomaly,
			'current_value' => $current_value,
			'metric' => $metric,
			'client_id' => $client_id,
			'combined_confidence' => $combined_confidence,
			'z_score_analysis' => $z_score_result,
			'moving_average_analysis' => $moving_avg_result,
			'timestamp' => current_time( 'mysql' ),
		];
	}

	/**
	 * Get supported metrics for anomaly detection
	 *
	 * @return array Array of supported metrics
	 */
	public static function get_supported_metrics(): array {
		return [
			MetricsSchema::KPI_SESSIONS,
			MetricsSchema::KPI_CONVERSIONS,
			MetricsSchema::KPI_REVENUE,
			MetricsSchema::KPI_COST,
			MetricsSchema::KPI_USERS,
			MetricsSchema::KPI_PAGEVIEWS,
			MetricsSchema::KPI_CLICKS,
			MetricsSchema::KPI_IMPRESSIONS,
		];
	}
}
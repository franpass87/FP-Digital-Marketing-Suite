<?php
/**
 * Core Web Vitals Performance Helper
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Core Web Vitals Performance helper class
 * 
 * This class provides utilities for Core Web Vitals analysis,
 * recommendations, and performance monitoring.
 */
class CoreWebVitalsHelper {

	/**
	 * Generate performance recommendations based on Core Web Vitals
	 *
	 * @param array $metrics Current metrics data
	 * @return array Array of recommendations
	 */
	public static function get_performance_recommendations( array $metrics ): array {
		$recommendations = [];

		// LCP Recommendations
		if ( isset( $metrics['lcp'] ) ) {
			$lcp_value = (float) $metrics['lcp'];
			$lcp_status = MetricsSchema::get_performance_status( MetricsSchema::KPI_LCP, $lcp_value );

			if ( $lcp_status === 'needs_improvement' || $lcp_status === 'poor' ) {
				$recommendations[] = [
					'metric' => 'lcp',
					'priority' => $lcp_status === 'poor' ? 'high' : 'medium',
					'title' => __( 'Migliorare Largest Contentful Paint (LCP)', 'fp-digital-marketing' ),
					'description' => sprintf(
						__( 'Il tuo LCP è %s ms. Per migliorarlo:', 'fp-digital-marketing' ),
						number_format( $lcp_value )
					),
					'actions' => [
						__( 'Ottimizza le immagini (WebP, compressione)', 'fp-digital-marketing' ),
						__( 'Utilizza un CDN per contenuti statici', 'fp-digital-marketing' ),
						__( 'Implementa lazy loading per immagini', 'fp-digital-marketing' ),
						__( 'Riduci il tempo di risposta del server', 'fp-digital-marketing' ),
						__( 'Elimina CSS e JavaScript non utilizzati', 'fp-digital-marketing' ),
					],
				];
			}
		}

		// INP Recommendations
		if ( isset( $metrics['inp'] ) ) {
			$inp_value = (float) $metrics['inp'];
			$inp_status = MetricsSchema::get_performance_status( MetricsSchema::KPI_INP, $inp_value );

			if ( $inp_status === 'needs_improvement' || $inp_status === 'poor' ) {
				$recommendations[] = [
					'metric' => 'inp',
					'priority' => $inp_status === 'poor' ? 'high' : 'medium',
					'title' => __( 'Migliorare Interaction to Next Paint (INP)', 'fp-digital-marketing' ),
					'description' => sprintf(
						__( 'Il tuo INP è %s ms. Per migliorarlo:', 'fp-digital-marketing' ),
						number_format( $inp_value )
					),
					'actions' => [
						__( 'Riduci il JavaScript main thread blocking', 'fp-digital-marketing' ),
						__( 'Ottimizza i gestori di eventi', 'fp-digital-marketing' ),
						__( 'Usa web workers per elaborazioni pesanti', 'fp-digital-marketing' ),
						__( 'Implementa code splitting per JavaScript', 'fp-digital-marketing' ),
						__( 'Evita layout thrashing', 'fp-digital-marketing' ),
					],
				];
			}
		}

		// CLS Recommendations
		if ( isset( $metrics['cls'] ) ) {
			$cls_value = (float) $metrics['cls'];
			$cls_status = MetricsSchema::get_performance_status( MetricsSchema::KPI_CLS, $cls_value );

			if ( $cls_status === 'needs_improvement' || $cls_status === 'poor' ) {
				$recommendations[] = [
					'metric' => 'cls',
					'priority' => $cls_status === 'poor' ? 'high' : 'medium',
					'title' => __( 'Migliorare Cumulative Layout Shift (CLS)', 'fp-digital-marketing' ),
					'description' => sprintf(
						__( 'Il tuo CLS è %s. Per migliorarlo:', 'fp-digital-marketing' ),
						number_format( $cls_value, 3 )
					),
					'actions' => [
						__( 'Specifica dimensioni per immagini e video', 'fp-digital-marketing' ),
						__( 'Riserva spazio per annunci e iframe', 'fp-digital-marketing' ),
						__( 'Evita di inserire contenuti sopra contenuti esistenti', 'fp-digital-marketing' ),
						__( 'Usa font-display: swap per web fonts', 'fp-digital-marketing' ),
						__( 'Precarica font critici', 'fp-digital-marketing' ),
					],
				];
			}
		}

		// General performance recommendations
		if ( count( $recommendations ) > 1 ) {
			$recommendations[] = [
				'metric' => 'general',
				'priority' => 'low',
				'title' => __( 'Raccomandazioni Generali', 'fp-digital-marketing' ),
				'description' => __( 'Migliora le performance complessive:', 'fp-digital-marketing' ),
				'actions' => [
					__( 'Implementa Service Worker per caching', 'fp-digital-marketing' ),
					__( 'Usa HTTP/2 e compressione gzip', 'fp-digital-marketing' ),
					__( 'Minimizza i redirect', 'fp-digital-marketing' ),
					__( 'Ottimizza il Critical Rendering Path', 'fp-digital-marketing' ),
				],
			];
		}

		return $recommendations;
	}

	/**
	 * Get Core Web Vitals trend analysis
	 *
	 * @param array $historical_data Historical metrics data (28 days)
	 * @return array Trend analysis
	 */
	public static function analyze_trends( array $historical_data ): array {
		$trends = [];

		foreach ( ['lcp', 'inp', 'cls'] as $metric ) {
			if ( ! isset( $historical_data[ $metric ] ) || empty( $historical_data[ $metric ] ) ) {
				continue;
			}

			$values = array_values( $historical_data[ $metric ] );
			$count = count( $values );

			if ( $count < 2 ) {
				$trends[ $metric ] = [
					'direction' => 'stable',
					'change_percent' => 0,
					'status' => 'insufficient_data',
				];
				continue;
			}

			// Calculate trend using linear regression
			$recent_avg = array_sum( array_slice( $values, -7 ) ) / min( 7, $count );
			$older_avg = array_sum( array_slice( $values, 0, 7 ) ) / min( 7, $count );

			$change_percent = $older_avg > 0 ? ( ( $recent_avg - $older_avg ) / $older_avg ) * 100 : 0;

			if ( abs( $change_percent ) < 5 ) {
				$direction = 'stable';
			} elseif ( $change_percent > 0 ) {
				// For Core Web Vitals, increase is bad (worse performance)
				$direction = 'worsening';
			} else {
				// Decrease is good (better performance)
				$direction = 'improving';
			}

			$trends[ $metric ] = [
				'direction' => $direction,
				'change_percent' => round( abs( $change_percent ), 1 ),
				'recent_average' => round( $recent_avg, $metric === 'cls' ? 3 : 0 ),
				'older_average' => round( $older_avg, $metric === 'cls' ? 3 : 0 ),
				'status' => 'calculated',
			];
		}

		return $trends;
	}

	/**
	 * Generate Core Web Vitals score (0-100)
	 *
	 * @param array $metrics Current metrics
	 * @return array Score data
	 */
	public static function calculate_performance_score( array $metrics ): array {
		$scores = [];
		$total_score = 0;
		$metric_count = 0;

		// LCP Score (0-100)
		if ( isset( $metrics['lcp'] ) ) {
			$lcp_value = (float) $metrics['lcp'];
			if ( $lcp_value <= 2500 ) {
				$lcp_score = 100;
			} elseif ( $lcp_value <= 4000 ) {
				$lcp_score = 100 - ( ( $lcp_value - 2500 ) / 1500 ) * 50;
			} else {
				$lcp_score = max( 0, 50 - ( ( $lcp_value - 4000 ) / 2000 ) * 50 );
			}
			$scores['lcp'] = round( $lcp_score );
			$total_score += $lcp_score;
			$metric_count++;
		}

		// INP Score (0-100)
		if ( isset( $metrics['inp'] ) ) {
			$inp_value = (float) $metrics['inp'];
			if ( $inp_value <= 200 ) {
				$inp_score = 100;
			} elseif ( $inp_value <= 500 ) {
				$inp_score = 100 - ( ( $inp_value - 200 ) / 300 ) * 50;
			} else {
				$inp_score = max( 0, 50 - ( ( $inp_value - 500 ) / 500 ) * 50 );
			}
			$scores['inp'] = round( $inp_score );
			$total_score += $inp_score;
			$metric_count++;
		}

		// CLS Score (0-100)
		if ( isset( $metrics['cls'] ) ) {
			$cls_value = (float) $metrics['cls'];
			if ( $cls_value <= 0.1 ) {
				$cls_score = 100;
			} elseif ( $cls_value <= 0.25 ) {
				$cls_score = 100 - ( ( $cls_value - 0.1 ) / 0.15 ) * 50;
			} else {
				$cls_score = max( 0, 50 - ( ( $cls_value - 0.25 ) / 0.25 ) * 50 );
			}
			$scores['cls'] = round( $cls_score );
			$total_score += $cls_score;
			$metric_count++;
		}

		$overall_score = $metric_count > 0 ? round( $total_score / $metric_count ) : 0;

		return [
			'overall' => $overall_score,
			'individual' => $scores,
			'grade' => self::get_performance_grade( $overall_score ),
		];
	}

	/**
	 * Get performance grade based on score
	 *
	 * @param int $score Performance score (0-100)
	 * @return string Performance grade
	 */
	private static function get_performance_grade( int $score ): string {
		if ( $score >= 90 ) {
			return 'A';
		} elseif ( $score >= 80 ) {
			return 'B';
		} elseif ( $score >= 70 ) {
			return 'C';
		} elseif ( $score >= 60 ) {
			return 'D';
		} else {
			return 'F';
		}
	}

	/**
	 * Format Core Web Vitals value for display
	 *
	 * @param string $metric Metric name
	 * @param mixed  $value  Metric value
	 * @return string Formatted value
	 */
	public static function format_metric_value( string $metric, $value ): string {
		switch ( $metric ) {
			case 'lcp':
			case 'inp':
				return number_format( (float) $value ) . ' ms';
			case 'cls':
				return number_format( (float) $value, 3 );
			default:
				return (string) $value;
		}
	}

	/**
	 * Get metric display name
	 *
	 * @param string $metric Metric name
	 * @return string Display name
	 */
	public static function get_metric_display_name( string $metric ): string {
		$names = [
			'lcp' => __( 'LCP', 'fp-digital-marketing' ),
			'inp' => __( 'INP', 'fp-digital-marketing' ),
			'cls' => __( 'CLS', 'fp-digital-marketing' ),
		];

		return $names[ $metric ] ?? strtoupper( $metric );
	}
}
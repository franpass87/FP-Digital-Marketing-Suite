<?php
/**
 * Microsoft Clarity Data Source Integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Microsoft Clarity data source integration class
 * 
 * This class handles the integration with Microsoft Clarity API,
 * including project configuration, data fetching, and normalization.
 */
class MicrosoftClarity {

	/**
	 * Data source identifier
	 */
	public const SOURCE_ID = 'microsoft_clarity';

	/**
	 * Project ID
	 *
	 * @var string
	 */
	private $project_id;

	/**
	 * Constructor
	 *
	 * @param string $project_id Microsoft Clarity project ID
	 */
	public function __construct( string $project_id = '' ) {
		$this->project_id = $project_id;
	}

	/**
	 * Check if the Microsoft Clarity integration is configured
	 *
	 * @return bool True if configured
	 */
	public function is_connected(): bool {
		return ! empty( $this->project_id );
	}

	/**
	 * Get the tracking script code for Microsoft Clarity
	 *
	 * @return string JavaScript tracking code
	 */
	public function get_tracking_script(): string {
		if ( empty( $this->project_id ) ) {
			return '';
		}

		return sprintf(
			'<script type="text/javascript">
(function(c,l,a,r,i,t,y){
	c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
	t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
	y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
})(window, document, "clarity", "script", "%s");
</script>',
			esc_js( $this->project_id )
		);
	}

	/**
	 * Fetch metrics from Microsoft Clarity
	 *
	 * @param int    $client_id     Client ID
	 * @param string $start_date    Start date (Y-m-d format)
	 * @param string $end_date      End date (Y-m-d format)
	 * @return array|false Metrics data on success, false on failure
	 */
	public function fetch_metrics( int $client_id, string $start_date, string $end_date ): array|false {
		try {
			if ( empty( $this->project_id ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
					error_log( 'FP Digital Marketing MicrosoftClarity: Project ID not configured' );
				}
				return false;
			}

			// For now, return demo data since Microsoft Clarity API requires additional authentication
			// In a real implementation, this would make API calls to Clarity endpoints
			$demo_data = $this->generate_demo_data( $start_date, $end_date );

			// Store metrics in cache
			$cache = new MetricsCache();
			$normalized_data = $this->normalize_metrics( $demo_data, $client_id, $start_date, $end_date );
			
			foreach ( $normalized_data as $metric ) {
				$cache->store_metric( 
					$metric['client_id'],
					$metric['source'],
					$metric['kpi'],
					$metric['value'],
					$metric['date'],
					$metric['metadata']
				);
			}

			return $demo_data;

		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing MicrosoftClarity Error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Generate demo data for Microsoft Clarity
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return array Demo metrics data
	 */
	private function generate_demo_data( string $start_date, string $end_date ): array {
		$start = new \DateTime( $start_date );
		$end = new \DateTime( $end_date );
		$days = $start->diff( $end )->days + 1;

		// Base metrics that would come from Clarity
		$base_sessions = rand( 50, 200 );
		$base_pageviews = $base_sessions * rand( 2, 5 );
		$rage_clicks = rand( 5, 25 );
		$dead_clicks = rand( 10, 40 );

		return [
			'sessions' => $base_sessions * $days,
			'page_views' => $base_pageviews * $days,
			'recordings_available' => rand( 20, 80 ),
			'heatmaps_generated' => rand( 5, 15 ),
			'rage_clicks' => $rage_clicks * $days,
			'dead_clicks' => $dead_clicks * $days,
			'scroll_depth_avg' => rand( 60, 85 ),
			'time_to_click_avg' => rand( 2, 8 ),
			'javascript_errors' => rand( 0, 10 ) * $days,
			'period' => [
				'start' => $start_date,
				'end' => $end_date,
				'days' => $days,
			],
		];
	}

	/**
	 * Normalize Microsoft Clarity metrics to common schema
	 *
	 * @param array  $raw_data    Raw metrics from Clarity
	 * @param int    $client_id   Client ID
	 * @param string $start_date  Start date
	 * @param string $end_date    End date
	 * @return array Normalized metrics
	 */
	private function normalize_metrics( array $raw_data, int $client_id, string $start_date, string $end_date ): array {
		$normalized = [];
		$date = $start_date;

		// Sessions
		if ( isset( $raw_data['sessions'] ) ) {
			$normalized[] = [
				'client_id' => $client_id,
				'source' => self::SOURCE_ID,
				'kpi' => 'sessions',
				'value' => (int) $raw_data['sessions'],
				'date' => $date,
				'metadata' => [
					'period_days' => $raw_data['period']['days'] ?? 1,
					'data_type' => 'user_behavior',
				],
			];
		}

		// Page Views
		if ( isset( $raw_data['page_views'] ) ) {
			$normalized[] = [
				'client_id' => $client_id,
				'source' => self::SOURCE_ID,
				'kpi' => 'pageviews',
				'value' => (int) $raw_data['page_views'],
				'date' => $date,
				'metadata' => [
					'period_days' => $raw_data['period']['days'] ?? 1,
					'data_type' => 'user_behavior',
				],
			];
		}

		// Clarity-specific metrics
		$clarity_metrics = [
			'recordings_available' => 'clarity_recordings',
			'heatmaps_generated' => 'clarity_heatmaps',
			'rage_clicks' => 'clarity_rage_clicks',
			'dead_clicks' => 'clarity_dead_clicks',
			'scroll_depth_avg' => 'clarity_scroll_depth',
			'time_to_click_avg' => 'clarity_time_to_click',
			'javascript_errors' => 'clarity_js_errors',
		];

		foreach ( $clarity_metrics as $raw_key => $kpi ) {
			if ( isset( $raw_data[ $raw_key ] ) ) {
				$normalized[] = [
					'client_id' => $client_id,
					'source' => self::SOURCE_ID,
					'kpi' => $kpi,
					'value' => is_float( $raw_data[ $raw_key ] ) ? 
						(float) $raw_data[ $raw_key ] : 
						(int) $raw_data[ $raw_key ],
					'date' => $date,
					'metadata' => [
						'period_days' => $raw_data['period']['days'] ?? 1,
						'data_type' => 'user_behavior',
						'clarity_specific' => true,
					],
				];
			}
		}

		return $normalized;
	}

	/**
	 * Get project status information
	 *
	 * @return array Status information
	 */
	public function get_project_status(): array {
		if ( empty( $this->project_id ) ) {
			return [
				'connected' => false,
				'status' => __( 'Non configurato', 'fp-digital-marketing' ),
				'class' => 'disconnected',
			];
		}

		return [
			'connected' => true,
			'status' => __( 'Configurato', 'fp-digital-marketing' ),
			'class' => 'connected',
			'project_id' => $this->project_id,
		];
	}

	/**
	 * Validate project ID format
	 *
	 * @param string $project_id Project ID to validate
	 * @return bool True if valid format
	 */
	public static function validate_project_id( string $project_id ): bool {
		// Microsoft Clarity project IDs are typically alphanumeric strings
		return ! empty( $project_id ) && preg_match( '/^[a-zA-Z0-9]+$/', $project_id );
	}
}
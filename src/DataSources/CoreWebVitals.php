<?php
/**
 * Core Web Vitals Data Source Integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Core Web Vitals data source integration class
 * 
 * This class handles the integration with Chrome UX Report (CrUX) API
 * for Core Web Vitals metrics and client-side performance beacons.
 */
class CoreWebVitals {

	/**
	 * Data source identifier
	 */
	public const SOURCE_ID = 'core_web_vitals';

	/**
	 * CrUX API endpoint
	 */
	private const CRUX_API_ENDPOINT = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord';

	/**
	 * API key for CrUX
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Origin URL
	 *
	 * @var string
	 */
	private $origin_url;

	/**
	 * Request backoff delay in seconds
	 *
	 * @var int
	 */
	private $backoff_delay = 1;

	/**
	 * Maximum backoff delay in seconds
	 *
	 * @var int
	 */
	private const MAX_BACKOFF = 60;

	/**
	 * Constructor
	 *
	 * @param string $origin_url Origin URL to analyze
	 * @param string $api_key    CrUX API key
	 */
	public function __construct( string $origin_url = '', string $api_key = '' ) {
		$this->origin_url = $origin_url;
		$this->api_key = $api_key ?: get_option( 'fp_dms_crux_api_key', '' );
	}

	/**
	 * Check if the data source is connected
	 *
	 * @return bool True if connected, false otherwise
	 */
	public function is_connected(): bool {
		return ! empty( $this->api_key ) && ! empty( $this->origin_url );
	}

	/**
	 * Fetch Core Web Vitals metrics from CrUX API
	 *
	 * @param int    $client_id  Client ID
	 * @param string $start_date Start date (not used for CrUX - it returns last 28 days)
	 * @param string $end_date   End date (not used for CrUX - it returns last 28 days)
	 * @param array  $filters    Additional filters
	 * @return array|false Metrics data or false on failure
	 */
	public function fetch_metrics( int $client_id, string $start_date, string $end_date, array $filters = [] ) {
		// Check cache first
		$cache_key = PerformanceCache::generate_metrics_key([
			'source' => self::SOURCE_ID,
			'client_id' => $client_id,
			'origin_url' => $this->origin_url,
			'filters' => $filters,
		]);

               $cached_data = null;
               if ( PerformanceCache::is_cache_enabled() ) {
                       $cached_data = PerformanceCache::get_cached( $cache_key, PerformanceCache::CACHE_GROUP_METRICS );
                       if ( $cached_data !== false && $cached_data !== null ) {
                               return $cached_data;
                       }
               }

		try {
			if ( ! $this->is_connected() ) {
				// Return demo data if not connected
				$demo_data = $this->get_demo_metrics();
				$this->store_metrics( $client_id, $demo_data, $start_date, $end_date );
				return $demo_data;
			}

			// Make actual CrUX API call
			$metrics = $this->make_crux_api_request( $filters );
			
			if ( $metrics ) {
				// Store in cache
				PerformanceCache::set_cached( $cache_key, PerformanceCache::CACHE_GROUP_METRICS, $metrics, 3600 ); // 1 hour cache
				$this->store_metrics( $client_id, $metrics, $start_date, $end_date );
				return $metrics;
			}

			return false;

		} catch ( \Exception $e ) {
			error_log( 'Core Web Vitals fetch error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Make CrUX API request with retry logic
	 *
	 * @param array $filters Additional filters
	 * @return array|false CrUX API response or false on failure
	 */
	private function make_crux_api_request( array $filters = [] ) {
		$max_retries = 3;
		$retry_count = 0;

		while ( $retry_count < $max_retries ) {
			try {
				$request_body = [
					'origin' => $this->origin_url,
					'metrics' => [
						'largest_contentful_paint',
						'interaction_to_next_paint',
						'cumulative_layout_shift',
					],
				];

				// Add form factor filter if specified
				if ( ! empty( $filters['form_factor'] ) ) {
					$request_body['formFactor'] = strtoupper( $filters['form_factor'] );
				}

				$response = wp_remote_post( 
					self::CRUX_API_ENDPOINT . '?key=' . $this->api_key,
					[
						'headers' => [
							'Content-Type' => 'application/json',
						],
						'body' => wp_json_encode( $request_body ),
						'timeout' => 30,
					]
				);

				if ( is_wp_error( $response ) ) {
					throw new \Exception( $response->get_error_message() );
				}

				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );

				if ( wp_remote_retrieve_response_code( $response ) === 200 && $data ) {
					// Reset backoff on success
					$this->backoff_delay = 1;
					return $this->parse_crux_response( $data );
				}

				// Check for rate limiting or quota exceeded
				if ( wp_remote_retrieve_response_code( $response ) === 429 ||
					 wp_remote_retrieve_response_code( $response ) === 403 ) {
					if ( $retry_count < $max_retries - 1 ) {
						sleep( $this->backoff_delay );
						$this->backoff_delay = min( $this->backoff_delay * 2, self::MAX_BACKOFF );
						$retry_count++;
						continue;
					}
				}

				return false;

			} catch ( \Exception $e ) {
				$retry_count++;
				
				if ( strpos( $e->getMessage(), '429' ) !== false || 
					 strpos( $e->getMessage(), 'quota' ) !== false ) {
					if ( $retry_count < $max_retries ) {
						sleep( $this->backoff_delay );
						$this->backoff_delay = min( $this->backoff_delay * 2, self::MAX_BACKOFF );
						continue;
					}
				}
				
				error_log( 'CrUX API error: ' . $e->getMessage() );
				break;
			}
		}

		return false;
	}

	/**
	 * Parse CrUX API response to normalized metrics
	 *
	 * @param array $data CrUX API response
	 * @return array Normalized metrics
	 */
	private function parse_crux_response( array $data ): array {
		$metrics = [];

		if ( isset( $data['record']['metrics'] ) ) {
			$crux_metrics = $data['record']['metrics'];

			// Extract LCP (75th percentile)
			if ( isset( $crux_metrics['largest_contentful_paint']['percentiles']['p75'] ) ) {
				$metrics['lcp'] = (string) $crux_metrics['largest_contentful_paint']['percentiles']['p75'];
			}

			// Extract INP (75th percentile)
			if ( isset( $crux_metrics['interaction_to_next_paint']['percentiles']['p75'] ) ) {
				$metrics['inp'] = (string) $crux_metrics['interaction_to_next_paint']['percentiles']['p75'];
			}

			// Extract CLS (75th percentile)
			if ( isset( $crux_metrics['cumulative_layout_shift']['percentiles']['p75'] ) ) {
				$metrics['cls'] = (string) ( $crux_metrics['cumulative_layout_shift']['percentiles']['p75'] / 100 );
			}
		}

		return $metrics;
	}

	/**
	 * Get demo Core Web Vitals metrics for testing
	 *
	 * @return array Demo metrics
	 */
	private function get_demo_metrics(): array {
		return [
			'lcp' => (string) rand( 1500, 4000 ), // 1.5s to 4s
			'inp' => (string) rand( 100, 600 ),   // 100ms to 600ms
			'cls' => number_format( rand( 5, 30 ) / 100, 3 ), // 0.05 to 0.30
		];
	}

	/**
	 * Store metrics in the cache
	 *
	 * @param int    $client_id  Client ID
	 * @param array  $metrics    Metrics data
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return void
	 */
        private function store_metrics( int $client_id, array $metrics, string $start_date, string $end_date ): void {
                $period_start = $this->normalize_period_boundary( $start_date, true );
                $period_end = $this->normalize_period_boundary( $end_date, false );

                foreach ( $metrics as $metric_name => $value ) {
                        MetricsCache::save(
                                $client_id,
                                self::SOURCE_ID,
                                $metric_name,
                                $period_start,
                                $period_end,
                                $value,
                                [
                                        'origin_url' => $this->origin_url,
                                        'percentile' => 75,
                                        'collection_period' => '28_days',
                                ]
                        );
                }
        }

        /**
         * Normalize incoming date or datetime strings to MySQL timestamps.
         *
         * Ensures the returned value is a valid YYYY-mm-dd HH:MM:SS string and
         * clamps the time portion to the beginning or end of the day.
         *
         * @param string $date_string Raw date or datetime string.
         * @param bool   $is_start    Whether this represents the period start.
         * @return string Normalized MySQL-compatible timestamp.
         */
        private function normalize_period_boundary( string $date_string, bool $is_start ): string {
                $timestamp = strtotime( $date_string );

                if ( false === $timestamp && preg_match( '/^\d{4}-\d{2}-\d{2}/', $date_string, $matches ) ) {
                        $timestamp = strtotime( $matches[0] );
                }

                if ( false === $timestamp ) {
                        $timestamp = time();
                }

                if ( function_exists( 'wp_timezone' ) ) {
                        $timezone = wp_timezone();
                } else {
                        try {
                                $timezone = new \DateTimeZone( date_default_timezone_get() ?: 'UTC' );
                        } catch ( \Exception $exception ) {
                                $timezone = new \DateTimeZone( 'UTC' );
                        }
                }

                $date_time = new \DateTime( '@' . $timestamp );
                $date_time->setTimezone( $timezone );

                if ( $is_start ) {
                        $date_time->setTime( 0, 0, 0 );
                } else {
                        $date_time->setTime( 23, 59, 59 );
                }

                return $date_time->format( 'Y-m-d H:i:s' );
        }

	/**
	 * Generate client-side beacon JavaScript for real-time metrics
	 *
	 * @return string JavaScript code for Core Web Vitals collection
	 */
	public static function get_client_beacon_script(): string {
		return "
		<script>
		(function() {
			// Core Web Vitals collection
			if ('PerformanceObserver' in window) {
				// LCP Observer
				new PerformanceObserver((entryList) => {
					const entries = entryList.getEntries();
					const lastEntry = entries[entries.length - 1];
					
					// Send LCP data to server
					fp_dms_send_vital('lcp', lastEntry.startTime);
				}).observe({entryTypes: ['largest-contentful-paint']});

				// INP Observer (using first-input as fallback for older browsers)
				if ('PerformanceEventTiming' in window) {
					new PerformanceObserver((entryList) => {
						let maxINP = 0;
						entryList.getEntries().forEach((entry) => {
							const inp = entry.processingStart - entry.startTime;
							maxINP = Math.max(maxINP, inp);
						});
						if (maxINP > 0) {
							fp_dms_send_vital('inp', maxINP);
						}
					}).observe({entryTypes: ['event']});
				} else {
					// Fallback to FID for older browsers
					new PerformanceObserver((entryList) => {
						const entries = entryList.getEntries();
						entries.forEach((entry) => {
							fp_dms_send_vital('fid', entry.processingStart - entry.startTime);
						});
					}).observe({entryTypes: ['first-input']});
				}

				// CLS Observer
				let clsValue = 0;
				new PerformanceObserver((entryList) => {
					entryList.getEntries().forEach((entry) => {
						if (!entry.hadRecentInput) {
							clsValue += entry.value;
						}
					});
					fp_dms_send_vital('cls', clsValue);
				}).observe({entryTypes: ['layout-shift']});
			}

			// Function to send vital data
			function fp_dms_send_vital(metric, value) {
				if (typeof jQuery !== 'undefined') {
					jQuery.post(ajaxurl, {
						action: 'fp_dms_record_client_vital',
						metric: metric,
						value: value,
						url: window.location.href,
						nonce: fpDmsVitals.nonce
					});
				}
			}
		})();
		</script>";
	}

	/**
	 * Set the API key
	 *
	 * @param string $api_key CrUX API key
	 * @return void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
	}

	/**
	 * Set the origin URL
	 *
	 * @param string $origin_url Origin URL
	 * @return void
	 */
	public function set_origin_url( string $origin_url ): void {
		$this->origin_url = $origin_url;
	}
}
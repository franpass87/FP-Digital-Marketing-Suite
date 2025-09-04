<?php
/**
 * Google Analytics 4 Data Source Integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Google Analytics 4 data source integration class
 * 
 * This class handles the integration with Google Analytics 4 API,
 * including OAuth authentication, data fetching, and normalization.
 */
class GoogleAnalytics4 {

	/**
	 * Data source identifier
	 */
	public const SOURCE_ID = 'google_analytics_4';

	/**
	 * OAuth client
	 *
	 * @var GoogleOAuth
	 */
	private $oauth_client;

	/**
	 * GA4 property ID
	 *
	 * @var string
	 */
	private $property_id;

	/**
	 * Constructor
	 *
	 * @param string $property_id GA4 property ID
	 */
	public function __construct( string $property_id = '' ) {
		try {
			$this->property_id = $property_id;
			$this->oauth_client = new GoogleOAuth();
		} catch ( \Throwable $e ) {
			// Log error but allow object creation to prevent WSOD
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing GoogleAnalytics4 Constructor Error: ' . $e->getMessage() );
			}
			$this->oauth_client = null;
		}
	}

	/**
	 * Check if the GA4 integration is configured and connected
	 *
	 * @return bool True if configured and connected
	 */
	public function is_connected(): bool {
		return $this->oauth_client->is_authenticated() && ! empty( $this->property_id );
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @return string Authorization URL
	 */
	public function get_authorization_url(): string {
		return $this->oauth_client->get_authorization_url();
	}

	/**
	 * Handle OAuth callback and exchange code for tokens
	 *
	 * @param string $authorization_code The authorization code from Google
	 * @return bool True on success, false on failure
	 */
	public function handle_oauth_callback( string $authorization_code ): bool {
		return $this->oauth_client->exchange_code_for_tokens( $authorization_code );
	}

	/**
	 * Fetch basic metrics from GA4
	 *
	 * @param int    $client_id     Client ID
	 * @param string $start_date    Start date (Y-m-d format)
	 * @param string $end_date      End date (Y-m-d format)
	 * @return array|false Metrics data on success, false on failure
	 */
	public function fetch_metrics( int $client_id, string $start_date, string $end_date ): array|false {
		try {
			// Check if OAuth client is available (could be null from constructor error)
			if ( $this->oauth_client === null ) {
				if ( function_exists( 'error_log' ) ) {
					error_log( 'FP Digital Marketing GoogleAnalytics4: OAuth client not available' );
				}
				return false;
			}

			if ( ! $this->is_connected() ) {
				return false;
			}

			// Refresh token if needed with error handling
			$this->oauth_client->refresh_token_if_needed();

			$metrics = [
				'sessions' => $this->fetch_sessions( $start_date, $end_date ),
				'users' => $this->fetch_users( $start_date, $end_date ),
				'conversions' => $this->fetch_conversions( $start_date, $end_date ),
				'revenue' => $this->fetch_revenue( $start_date, $end_date ),
			];

			// Store metrics in cache with error handling
			$this->store_metrics_in_cache( $client_id, $metrics, $start_date, $end_date );

			return $metrics;

		} catch ( \Throwable $e ) {
			// Enhanced error logging for all types of errors
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing GA4 metrics fetch error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return false;
		}
	}

	/**
	 * Fetch sessions data from GA4
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return string Sessions count
	 */
	private function fetch_sessions( string $start_date, string $end_date ): string {
		// For demo purposes, return mock data
		// In production, this would make an actual API call
		return $this->make_api_request( 'sessions', $start_date, $end_date );
	}

	/**
	 * Fetch users data from GA4
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return string Users count
	 */
	private function fetch_users( string $start_date, string $end_date ): string {
		return $this->make_api_request( 'totalUsers', $start_date, $end_date );
	}

	/**
	 * Fetch conversions data from GA4
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return string Conversions count
	 */
	private function fetch_conversions( string $start_date, string $end_date ): string {
		return $this->make_api_request( 'conversions', $start_date, $end_date );
	}

	/**
	 * Fetch revenue data from GA4
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return string Revenue amount
	 */
	private function fetch_revenue( string $start_date, string $end_date ): string {
		return $this->make_api_request( 'totalRevenue', $start_date, $end_date );
	}

	/**
	 * Make API request to GA4 (mock implementation for demo)
	 *
	 * @param string $metric     Metric name
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return string Metric value
	 */
	private function make_api_request( string $metric, string $start_date, string $end_date ): string {
		// Mock implementation for demo purposes
		// In production, this would make actual API calls to GA4
		$mock_data = [
			'sessions' => (string) rand( 1000, 5000 ),
			'totalUsers' => (string) rand( 800, 4000 ),
			'conversions' => (string) rand( 10, 100 ),
			'totalRevenue' => (string) rand( 1000, 10000 ),
		];

		return $mock_data[ $metric ] ?? '0';
	}

	/**
	 * Store metrics in the cache
	 *
	 * @param int    $client_id Client ID
	 * @param array  $metrics   Metrics data
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @return void
	 */
	private function store_metrics_in_cache( int $client_id, array $metrics, string $start_date, string $end_date ): void {
		$period_start = $start_date . ' 00:00:00';
		$period_end = $end_date . ' 23:59:59';

		foreach ( $metrics as $metric_name => $value ) {
			MetricsCache::save(
				$client_id,
				self::SOURCE_ID,
				$metric_name,
				$period_start,
				$period_end,
				$value,
				[
					'property_id' => $this->property_id,
					'fetch_date' => current_time( 'mysql' ),
				]
			);
		}
	}

	/**
	 * Get property ID
	 *
	 * @return string Property ID
	 */
	public function get_property_id(): string {
		return $this->property_id;
	}

	/**
	 * Set property ID
	 *
	 * @param string $property_id Property ID
	 * @return void
	 */
	public function set_property_id( string $property_id ): void {
		$this->property_id = $property_id;
	}
}
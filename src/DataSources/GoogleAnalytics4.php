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
		 * Google Analytics Data API base URL
		 */
	private const API_BASE_URL = 'https://analyticsdata.googleapis.com/v1beta';

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
			$this->property_id  = $property_id;
			$this->oauth_client = new GoogleOAuth();
		} catch ( \Throwable $e ) {
			// Log error but allow object creation to prevent WSOD
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
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
		if ( ! $this->has_oauth_client() ) {
				return false;
		}

			return $this->oauth_client->is_authenticated() && ! empty( $this->property_id );
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @return string Authorization URL
	 */
	public function get_authorization_url(): string {
		if ( ! $this->has_oauth_client() ) {
				return '';
		}

			return $this->oauth_client->get_authorization_url();
	}

	/**
	 * Handle OAuth callback and exchange code for tokens
	 *
	 * @param string $authorization_code The authorization code from Google
	 * @return bool True on success, false on failure
	 */
	public function handle_oauth_callback( string $authorization_code ): bool {
		if ( ! $this->has_oauth_client() ) {
				return false;
		}

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
			if ( ! $this->has_oauth_client() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
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
				'sessions'    => $this->fetch_sessions( $start_date, $end_date ),
				'users'       => $this->fetch_users( $start_date, $end_date ),
				'conversions' => $this->fetch_conversions( $start_date, $end_date ),
				'revenue'     => $this->fetch_revenue( $start_date, $end_date ),
			];

			// Store metrics in cache with error handling
			$this->store_metrics_in_cache( $client_id, $metrics, $start_date, $end_date );

			return $metrics;

		} catch ( \Throwable $e ) {
			// Enhanced error logging for all types of errors
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
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
		 * Make API request to GA4 using the Data API
		 *
		 * @param string $metric     Metric name
		 * @param string $start_date Start date
		 * @param string $end_date   End date
		 * @param bool   $retry_on_unauthorized Whether we should attempt a token refresh on 401 responses.
		 * @return string Metric value
		 */
	private function make_api_request( string $metric, string $start_date, string $end_date, bool $retry_on_unauthorized = true ): string {
		if ( ! $this->has_oauth_client() || '' === $this->property_id ) {
				return '0';
		}

		if ( ! function_exists( 'wp_remote_post' ) || ! function_exists( 'wp_remote_retrieve_body' ) || ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
				// We're likely running in a non-WordPress context (e.g., unit tests)
				return '0';
		}

			$access_token = $this->oauth_client->get_access_token();

		if ( ! is_string( $access_token ) || '' === $access_token ) {
			if ( ! $this->oauth_client->refresh_token_if_needed() ) {
					$this->log_api_error( 'Missing GA4 access token', [ 'metric' => $metric ] );
					return '0';
			}

				$access_token = $this->oauth_client->get_access_token();

			if ( ! is_string( $access_token ) || '' === $access_token ) {
					$this->log_api_error( 'Failed to obtain GA4 access token after refresh', [ 'metric' => $metric ] );
					return '0';
			}
		}

			$payload = [
				'dateRanges' => [
					[
						'startDate' => $start_date,
						'endDate'   => $end_date,
					],
				],
				'metrics'    => [
					[
						'name' => $metric,
					],
				],
			];

			$encoded_payload = wp_json_encode( $payload );

			if ( ! is_string( $encoded_payload ) ) {
					$this->log_api_error( 'Failed to encode GA4 API payload', [ 'metric' => $metric ] );
					return '0';
			}

			$url = sprintf(
				'%s/properties/%s:runReport',
				self::API_BASE_URL,
				rawurlencode( $this->property_id )
			);

			$response = wp_remote_post(
				$url,
				[
					'timeout' => 20,
					'headers' => [
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					],
					'body'    => $encoded_payload,
				]
			);

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
				$this->log_api_error(
					'GA4 API request failed',
					[
						'metric' => $metric,
						'error'  => $response->get_error_message(),
					]
				);
				return '0';
		}

			$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $status_code && $retry_on_unauthorized ) {
			if ( $this->oauth_client->refresh_token_if_needed() ) {
					return $this->make_api_request( $metric, $start_date, $end_date, false );
			}

				$this->log_api_error( 'GA4 token refresh failed after unauthorized response', [ 'metric' => $metric ] );
				return '0';
		}

			$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
				$this->log_api_error(
					'Unexpected GA4 API status code',
					[
						'metric' => $metric,
						'status' => $status_code,
					]
				);
				return '0';
		}

			$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
				$this->log_api_error( 'GA4 API returned invalid JSON', [ 'metric' => $metric ] );
				return '0';
		}

		if ( empty( $data['rows'][0]['metricValues'][0]['value'] ) ) {
				return '0';
		}

			$value = (string) $data['rows'][0]['metricValues'][0]['value'];

			// Ensure numeric strings use a consistent format.
		if ( is_numeric( $value ) ) {
				$value = (string) ( 0 + $value );
		}

			return $value;
	}

		/**
		 * Log GA4 API errors when debugging is enabled
		 *
		 * @param string               $message Message to log.
		 * @param array<string, mixed> $context Additional context data.
		 * @return void
		 */
	private function log_api_error( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'error_log' ) ) {
				return;
		}

			$context_output = '';

		if ( ! empty( $context ) ) {
				$encoded_context = function_exists( 'wp_json_encode' ) ? wp_json_encode( $context ) : json_encode( $context );
			if ( is_string( $encoded_context ) ) {
					$context_output = ' ' . $encoded_context;
			}
		}

			error_log( 'FP Digital Marketing GA4: ' . $message . $context_output );
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
			$period_end   = $end_date . ' 23:59:59';

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
					'fetch_date'  => current_time( 'mysql' ),
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

		/**
		 * Check if the OAuth client is available
		 *
		 * @return bool True if the OAuth client is instantiated
		 */
	private function has_oauth_client(): bool {
			return $this->oauth_client instanceof GoogleOAuth;
	}
}

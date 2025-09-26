<?php
/**
 * Google Search Console Data Source Integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Google Search Console data source integration class
 *
 * This class handles the integration with Google Search Console API,
 * including OAuth authentication, data fetching, and normalization.
 */
class GoogleSearchConsole {

	/**
	 * Data source identifier
	 */
	public const SOURCE_ID = 'google_search_console';

	/**
	 * OAuth client
	 *
	 * @var GoogleOAuth
	 */
	private $oauth_client;

	/**
	 * Site URL (property)
	 *
	 * @var string
	 */
	private $site_url;

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
	 * @param string $site_url Site URL (property)
	 */
	public function __construct( string $site_url = '' ) {
		$this->site_url     = $site_url;
		$this->oauth_client = new GoogleOAuth();
	}

	/**
	 * Check if the GSC integration is configured and connected
	 *
	 * @return bool True if configured and connected
	 */
	public function is_connected(): bool {
		return $this->oauth_client->is_authenticated() && ! empty( $this->site_url );
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
	 * Fetch basic metrics from Google Search Console
	 *
	 * @param int    $client_id     Client ID
	 * @param string $start_date    Start date (Y-m-d format)
	 * @param string $end_date      End date (Y-m-d format)
	 * @param array  $filters       Optional filters (query, page, country, device)
	 * @return array|false Metrics data on success, false on failure
	 */
	public function fetch_metrics( int $client_id, string $start_date, string $end_date, array $filters = [] ): array|false {
		if ( ! $this->is_connected() ) {
			return false;
		}

		try {
			// Refresh token if needed
			$this->oauth_client->refresh_token_if_needed();

			$metrics = [
				'impressions' => $this->fetch_impressions( $start_date, $end_date, $filters ),
				'clicks'      => $this->fetch_clicks( $start_date, $end_date, $filters ),
				'ctr'         => $this->fetch_ctr( $start_date, $end_date, $filters ),
				'position'    => $this->fetch_avg_position( $start_date, $end_date, $filters ),
			];

			// Store metrics in cache
			$this->store_metrics_in_cache( $client_id, $metrics, $start_date, $end_date, $filters );

			return $metrics;

		} catch ( \Exception $e ) {
			error_log( 'GSC metrics fetch error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Fetch impressions data from GSC
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Impressions count
	 */
	private function fetch_impressions( string $start_date, string $end_date, array $filters = [] ): string {
		return $this->make_api_request( 'impressions', $start_date, $end_date, $filters );
	}

	/**
	 * Fetch clicks data from GSC
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Clicks count
	 */
	private function fetch_clicks( string $start_date, string $end_date, array $filters = [] ): string {
		return $this->make_api_request( 'clicks', $start_date, $end_date, $filters );
	}

	/**
	 * Fetch CTR data from GSC
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string CTR value
	 */
	private function fetch_ctr( string $start_date, string $end_date, array $filters = [] ): string {
		return $this->make_api_request( 'ctr', $start_date, $end_date, $filters );
	}

	/**
	 * Fetch average position data from GSC
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Average position value
	 */
	private function fetch_avg_position( string $start_date, string $end_date, array $filters = [] ): string {
		return $this->make_api_request( 'position', $start_date, $end_date, $filters );
	}

	/**
	 * Make API request to GSC with exponential backoff
	 *
	 * @param string $metric     Metric name
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Metric value
	 */
	private function make_api_request( string $metric, string $start_date, string $end_date, array $filters = [] ): string {
		$max_retries = 3;
		$retry_count = 0;

		while ( $retry_count < $max_retries ) {
			try {
				// For demo purposes, return mock data
				// In production, this would make an actual API call
				$result = $this->execute_api_call( $metric, $start_date, $end_date, $filters );

				// Reset backoff on success
				$this->backoff_delay = 1;
				return $result;

			} catch ( \Exception $e ) {
				++$retry_count;

				// Check for rate limiting
				if ( strpos( $e->getMessage(), '429' ) !== false || strpos( $e->getMessage(), 'quota' ) !== false ) {
					if ( $retry_count < $max_retries ) {
						// Exponential backoff
						sleep( $this->backoff_delay );
						$this->backoff_delay = min( $this->backoff_delay * 2, self::MAX_BACKOFF );
						continue;
					}
				}

				// For other errors, don't retry
				error_log( 'GSC API error: ' . $e->getMessage() );
				break;
			}
		}

		return '0';
	}

	/**
	 * Execute the actual API call (mock implementation for demo)
	 *
	 * @param string $metric     Metric name
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Metric value
	 */
	private function execute_api_call( string $metric, string $start_date, string $end_date, array $filters = [] ): string {
		// Mock implementation for demo purposes
		// In production, this would make actual API calls to GSC
		$mock_data = [
			'impressions' => (string) rand( 5000, 50000 ),
			'clicks'      => (string) rand( 200, 2000 ),
			'ctr'         => number_format( rand( 200, 800 ) / 100, 2 ), // 2.00 - 8.00%
			'position'    => number_format( rand( 300, 1500 ) / 100, 1 ), // 3.0 - 15.0
		];

		return $mock_data[ $metric ] ?? '0';
	}

	/**
	 * Store metrics in the cache
	 *
	 * @param int    $client_id  Client ID
	 * @param array  $metrics    Metrics data
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Applied filters
	 * @return void
	 */
	private function store_metrics_in_cache( int $client_id, array $metrics, string $start_date, string $end_date, array $filters = [] ): void {
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
					'site_url'   => $this->site_url,
					'filters'    => $filters,
					'fetch_date' => current_time( 'mysql' ),
				]
			);
		}
	}

	/**
	 * Get site URL
	 *
	 * @return string Site URL
	 */
	public function get_site_url(): string {
		return $this->site_url;
	}

	/**
	 * Set site URL
	 *
	 * @param string $site_url Site URL
	 * @return void
	 */
	public function set_site_url( string $site_url ): void {
		$this->site_url = $site_url;
	}

	/**
	 * Get available properties from GSC (mock implementation)
	 *
	 * @return array List of properties
	 */
	public function get_properties(): array {
		if ( ! $this->is_connected() ) {
			return [];
		}

		// Mock implementation - in production would fetch from API
		$site_url = get_site_url();
		$domain   = parse_url( $site_url, PHP_URL_HOST );

		return [
			$site_url . '/'        => sprintf( 'URL Prefix - %s', $domain ),
			'sc-domain:' . $domain => sprintf( 'Domain Property - %s', $domain ),
		];
	}

	/**
	 * Validate property exists in Search Console
	 *
	 * @param string $site_url Site URL to validate
	 * @return bool True if property exists
	 */
	public function validate_property( string $site_url ): bool {
		if ( ! $this->is_connected() ) {
			return false;
		}

		// Mock implementation - always return true for demo
		// In production would check if property exists in user's GSC account
		return ! empty( $site_url );
	}
}

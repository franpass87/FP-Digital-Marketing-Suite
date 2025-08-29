<?php
/**
 * Google Ads Data Source Integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Google Ads data source integration class
 * 
 * This class handles the integration with Google Ads API,
 * including OAuth authentication, data fetching, and normalization.
 * Implements base metrics: impressions, clicks, cost, conversions
 * with currency normalization and UTM campaign mapping.
 */
class GoogleAds {

	/**
	 * Data source identifier
	 */
	public const SOURCE_ID = 'google_ads';

	/**
	 * OAuth client
	 *
	 * @var GoogleOAuth
	 */
	private $oauth_client;

	/**
	 * Google Ads Customer ID
	 *
	 * @var string
	 */
	private $customer_id;

	/**
	 * Google Ads Developer Token
	 *
	 * @var string
	 */
	private $developer_token;

	/**
	 * Constructor
	 *
	 * @param string $customer_id Google Ads Customer ID
	 * @param string $developer_token Google Ads Developer Token
	 */
	public function __construct( string $customer_id = '', string $developer_token = '' ) {
		$this->customer_id = $customer_id;
		$this->developer_token = $developer_token;
		$this->oauth_client = new GoogleOAuth();
	}

	/**
	 * Get customer ID
	 *
	 * @return string Customer ID
	 */
	public function get_customer_id(): string {
		return $this->customer_id;
	}

	/**
	 * Set customer ID
	 *
	 * @param string $customer_id Customer ID
	 */
	public function set_customer_id( string $customer_id ): void {
		$this->customer_id = $customer_id;
	}

	/**
	 * Get developer token
	 *
	 * @return string Developer token
	 */
	public function get_developer_token(): string {
		return $this->developer_token;
	}

	/**
	 * Set developer token
	 *
	 * @param string $developer_token Developer token
	 */
	public function set_developer_token( string $developer_token ): void {
		$this->developer_token = $developer_token;
	}

	/**
	 * Check if Google Ads connection is properly configured
	 *
	 * @return bool True if connected
	 */
	public function is_connected(): bool {
		return $this->oauth_client->is_authenticated() && 
		       ! empty( $this->customer_id ) && 
		       ! empty( $this->developer_token );
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
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code
	 * @return bool Success status
	 */
	public function handle_oauth_callback( string $code ): bool {
		return $this->oauth_client->exchange_code_for_tokens( $code );
	}

	/**
	 * Fetch Google Ads metrics for a client and date range
	 *
	 * @param int    $client_id   Client ID
	 * @param string $start_date  Start date (Y-m-d format)
	 * @param string $end_date    End date (Y-m-d format)
	 * @param array  $filters     Optional filters (campaign_id, utm_source, etc.)
	 * @return array|false Metrics array or false on failure
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
				'clicks' => $this->fetch_clicks( $start_date, $end_date, $filters ),
				'cost' => $this->fetch_cost( $start_date, $end_date, $filters ),
				'conversions' => $this->fetch_conversions( $start_date, $end_date, $filters ),
			];

			// Normalize currency
			$metrics['cost'] = $this->normalize_currency( $metrics['cost'] );

			// Store metrics in cache with UTM mapping
			$this->store_metrics_in_cache( $client_id, $metrics, $start_date, $end_date, $filters );

			return $metrics;

		} catch ( \Exception $e ) {
			error_log( 'Google Ads metrics fetch error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Fetch impressions data from Google Ads
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
	 * Fetch clicks data from Google Ads
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
	 * Fetch cost data from Google Ads
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Cost amount in micros
	 */
	private function fetch_cost( string $start_date, string $end_date, array $filters = [] ): string {
		return $this->make_api_request( 'cost_micros', $start_date, $end_date, $filters );
	}

	/**
	 * Fetch conversions data from Google Ads
	 *
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Conversions count
	 */
	private function fetch_conversions( string $start_date, string $end_date, array $filters = [] ): string {
		return $this->make_api_request( 'conversions', $start_date, $end_date, $filters );
	}

	/**
	 * Make API request to Google Ads
	 *
	 * @param string $metric     Metric name
	 * @param string $start_date Start date
	 * @param string $end_date   End date
	 * @param array  $filters    Filters
	 * @return string Metric value
	 */
	private function make_api_request( string $metric, string $start_date, string $end_date, array $filters = [] ): string {
		// For demo purposes, return mock data
		// In production, this would make actual Google Ads API calls
		
		$mock_values = [
			'impressions' => '15000',
			'clicks' => '750',
			'cost_micros' => '150000000', // $150 in micros
			'conversions' => '25',
		];

		// Add some variation based on date range
		$days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
		$base_value = (int) $mock_values[$metric];
		
		// Scale by number of days (with some randomness for demo)
		$scaled_value = (int) ($base_value * ($days / 30) * (0.8 + mt_rand(0, 40) / 100));
		
		return (string) $scaled_value;
	}

	/**
	 * Normalize currency from micros to standard format
	 *
	 * @param string $cost_micros Cost in micros
	 * @return string Normalized cost
	 */
	private function normalize_currency( string $cost_micros ): string {
		// Google Ads returns cost in micros (1 unit = 1,000,000 micros)
		$cost = (float) $cost_micros / 1000000;
		return number_format( $cost, 2, '.', '' );
	}

	/**
	 * Map Google Ads campaigns to UTM parameters
	 *
	 * @param array $campaign_data Campaign data from API
	 * @return array UTM mappings
	 */
	private function map_campaigns_to_utm( array $campaign_data ): array {
		$utm_mappings = [];

		foreach ( $campaign_data as $campaign ) {
			$utm_mappings[] = [
				'campaign_id' => $campaign['id'] ?? '',
				'campaign_name' => $campaign['name'] ?? '',
				'utm_source' => 'google',
				'utm_medium' => 'cpc',
				'utm_campaign' => $this->sanitize_utm_campaign( $campaign['name'] ?? '' ),
			];
		}

		return $utm_mappings;
	}

	/**
	 * Sanitize campaign name for UTM parameter
	 *
	 * @param string $campaign_name Campaign name
	 * @return string Sanitized UTM campaign parameter
	 */
	private function sanitize_utm_campaign( string $campaign_name ): string {
		// Convert to lowercase, replace spaces with underscores, remove special chars
		$sanitized = strtolower( $campaign_name );
		$sanitized = preg_replace( '/[^a-z0-9_-]/', '_', $sanitized );
		$sanitized = preg_replace( '/_+/', '_', $sanitized );
		return trim( $sanitized, '_' );
	}

	/**
	 * Store metrics in cache with UTM campaign mapping
	 *
	 * @param int    $client_id   Client ID
	 * @param array  $metrics     Metrics data
	 * @param string $start_date  Start date
	 * @param string $end_date    End date
	 * @param array  $filters     Filters (may contain campaign info)
	 */
	private function store_metrics_in_cache( int $client_id, array $metrics, string $start_date, string $end_date, array $filters = [] ): void {
		foreach ( $metrics as $metric_name => $value ) {
			$metadata = [
				'customer_id' => $this->customer_id,
				'source_type' => 'google_ads',
			];

			// Add UTM mapping if campaign info is available
			if ( ! empty( $filters['campaign_id'] ) ) {
				$metadata['campaign_id'] = $filters['campaign_id'];
				$metadata['utm_source'] = 'google';
				$metadata['utm_medium'] = 'cpc';
				
				if ( ! empty( $filters['campaign_name'] ) ) {
					$metadata['utm_campaign'] = $this->sanitize_utm_campaign( $filters['campaign_name'] );
				}
			}

			MetricsCache::save(
				$client_id,
				self::SOURCE_ID,
				$metric_name,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$value,
				$metadata
			);
		}
	}

	/**
	 * Get campaign data with UTM mappings
	 *
	 * @param int    $client_id   Client ID
	 * @param string $start_date  Start date
	 * @param string $end_date    End date
	 * @return array Campaign data with UTM mappings
	 */
	public function get_campaigns_with_utm( int $client_id, string $start_date, string $end_date ): array {
		if ( ! $this->is_connected() ) {
			return [];
		}

		// Mock campaign data for demo
		$mock_campaigns = [
			[
				'id' => '12345678',
				'name' => 'Summer Sale 2024',
				'status' => 'ENABLED',
				'impressions' => '5000',
				'clicks' => '250',
				'cost_micros' => '50000000',
				'conversions' => '10',
			],
			[
				'id' => '87654321',
				'name' => 'Brand Awareness Q4',
				'status' => 'ENABLED',
				'impressions' => '10000',
				'clicks' => '500',
				'cost_micros' => '100000000',
				'conversions' => '15',
			],
		];

		// Add UTM mappings to campaigns
		foreach ( $mock_campaigns as &$campaign ) {
			$campaign['utm_mappings'] = [
				'utm_source' => 'google',
				'utm_medium' => 'cpc',
				'utm_campaign' => $this->sanitize_utm_campaign( $campaign['name'] ),
			];
		}

		return $mock_campaigns;
	}
}
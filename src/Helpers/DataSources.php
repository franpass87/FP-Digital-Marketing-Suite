<?php
/**
 * Data Sources Helper
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Data Sources helper class
 * 
 * This class manages the registry of data sources for the Digital Marketing Suite.
 * It provides a extensible structure for integrating various marketing data sources
 * like Google Analytics 4, Search Console, Facebook Ads, etc.
 */
class DataSources {

	/**
	 * Available data source types
	 */
	public const TYPE_ANALYTICS = 'analytics';
	public const TYPE_SEARCH = 'search';
	public const TYPE_SOCIAL = 'social';
	public const TYPE_ADVERTISING = 'advertising';
	public const TYPE_EMAIL = 'email';
	public const TYPE_PERFORMANCE = 'performance';

	/**
	 * Get all registered data sources
	 *
	 * @param string $type Optional. Filter by data source type.
	 * @return array Array of registered data sources.
	 */
	public static function get_data_sources( string $type = '' ): array {
		$data_sources = [
			'google_analytics_4' => [
				'id'          => 'google_analytics_4',
				'name'        => __( 'Google Analytics 4', 'fp-digital-marketing' ),
				'description' => __( 'Analisi del traffico web e comportamento utenti', 'fp-digital-marketing' ),
				'type'        => self::TYPE_ANALYTICS,
				'status'      => 'available',
				'version'     => '1.0',
				'endpoints'   => [
					'reports' => 'https://analyticsreporting.googleapis.com/v4/reports:batchGet',
					'realtime' => 'https://analyticsdata.googleapis.com/v1beta/',
				],
				'required_credentials' => [
					'client_id',
					'client_secret',
					'property_id',
				],
				'capabilities' => [
					'real_time_data',
					'historical_reports',
					'audience_insights',
					'conversion_tracking',
				],
			],
			'google_search_console' => [
				'id'          => 'google_search_console',
				'name'        => __( 'Google Search Console', 'fp-digital-marketing' ),
				'description' => __( 'Dati di performance SEO e ricerca organica', 'fp-digital-marketing' ),
				'type'        => self::TYPE_SEARCH,
				'status'      => 'available',
				'version'     => '1.0',
				'endpoints'   => [
					'searchanalytics' => 'https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/searchAnalytics/query',
					'sitemaps' => 'https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/sitemaps',
				],
				'required_credentials' => [
					'client_id',
					'client_secret',
					'site_url',
				],
				'capabilities' => [
					'search_performance',
					'keyword_rankings',
					'sitemap_status',
					'crawl_errors',
				],
			],
			'facebook_ads' => [
				'id'          => 'facebook_ads',
				'name'        => __( 'Facebook Ads', 'fp-digital-marketing' ),
				'description' => __( 'Metriche e performance delle campagne Facebook/Meta', 'fp-digital-marketing' ),
				'type'        => self::TYPE_ADVERTISING,
				'status'      => 'planned',
				'version'     => '1.0',
				'endpoints'   => [
					'insights' => 'https://graph.facebook.com/v18.0/{ad-account-id}/insights',
					'campaigns' => 'https://graph.facebook.com/v18.0/{ad-account-id}/campaigns',
				],
				'required_credentials' => [
					'app_id',
					'app_secret',
					'access_token',
					'ad_account_id',
				],
				'capabilities' => [
					'campaign_metrics',
					'audience_insights',
					'ad_performance',
					'cost_analysis',
				],
			],
			'google_ads' => [
				'id'          => 'google_ads',
				'name'        => __( 'Google Ads', 'fp-digital-marketing' ),
				'description' => __( 'Dati delle campagne pubblicitarie Google Ads', 'fp-digital-marketing' ),
				'type'        => self::TYPE_ADVERTISING,
				'status'      => 'available',
				'version'     => '1.0',
				'endpoints'   => [
					'reports' => 'https://googleads.googleapis.com/v14/customers/{customer_id}/googleAds:searchStream',
					'campaigns' => 'https://googleads.googleapis.com/v14/customers/{customer_id}/campaigns',
				],
				'required_credentials' => [
					'client_id',
					'client_secret',
					'developer_token',
					'customer_id',
				],
				'capabilities' => [
					'campaign_performance',
					'keyword_data',
					'ad_groups',
					'conversion_tracking',
				],
			],
			'mailchimp' => [
				'id'          => 'mailchimp',
				'name'        => __( 'Mailchimp', 'fp-digital-marketing' ),
				'description' => __( 'Statistiche email marketing e automazioni', 'fp-digital-marketing' ),
				'type'        => self::TYPE_EMAIL,
				'status'      => 'planned',
				'version'     => '1.0',
				'endpoints'   => [
					'reports' => 'https://{dc}.api.mailchimp.com/3.0/reports',
					'campaigns' => 'https://{dc}.api.mailchimp.com/3.0/campaigns',
				],
				'required_credentials' => [
					'api_key',
					'datacenter',
				],
				'capabilities' => [
					'email_performance',
					'subscriber_analytics',
					'automation_metrics',
					'audience_growth',
				],
			],
			'core_web_vitals' => [
				'id'          => 'core_web_vitals',
				'name'        => __( 'Core Web Vitals', 'fp-digital-marketing' ),
				'description' => __( 'Metriche di performance (LCP, INP, CLS) da Chrome UX Report', 'fp-digital-marketing' ),
				'type'        => self::TYPE_PERFORMANCE,
				'status'      => 'available',
				'version'     => '1.0',
				'endpoints'   => [
					'crux_api' => 'https://chromeuxreport.googleapis.com/v1/records:queryRecord',
				],
				'required_credentials' => [
					'crux_api_key',
					'origin_url',
				],
				'capabilities' => [
					'core_web_vitals',
					'performance_monitoring',
					'real_user_monitoring',
					'client_side_beacons',
					'28_day_rolling_data',
				],
			],
		];

		// Apply the hook for extensibility.
		$data_sources = apply_filters( 'fp_dms_data_sources', $data_sources );

		// Filter by type if specified.
		if ( ! empty( $type ) ) {
			$data_sources = array_filter( $data_sources, function( $source ) use ( $type ) {
				return isset( $source['type'] ) && $source['type'] === $type;
			} );
		}

		return $data_sources;
	}

	/**
	 * Get data sources by status
	 *
	 * @param string $status Status to filter by (available, planned, deprecated).
	 * @return array Filtered data sources.
	 */
	public static function get_data_sources_by_status( string $status ): array {
		$data_sources = self::get_data_sources();
		
		return array_filter( $data_sources, function( $source ) use ( $status ) {
			return isset( $source['status'] ) && $source['status'] === $status;
		} );
	}

	/**
	 * Get available data source types
	 *
	 * @return array Available types with labels.
	 */
	public static function get_data_source_types(): array {
		return [
			self::TYPE_ANALYTICS    => __( 'Analytics', 'fp-digital-marketing' ),
			self::TYPE_SEARCH       => __( 'Search/SEO', 'fp-digital-marketing' ),
			self::TYPE_SOCIAL       => __( 'Social Media', 'fp-digital-marketing' ),
			self::TYPE_ADVERTISING  => __( 'Advertising', 'fp-digital-marketing' ),
			self::TYPE_EMAIL        => __( 'Email Marketing', 'fp-digital-marketing' ),
			self::TYPE_PERFORMANCE  => __( 'Performance', 'fp-digital-marketing' ),
		];
	}

	/**
	 * Check if a data source is available
	 *
	 * @param string $source_id Data source ID.
	 * @return bool True if available, false otherwise.
	 */
	public static function is_data_source_available( string $source_id ): bool {
		$data_sources = self::get_data_sources();
		
		return isset( $data_sources[ $source_id ] ) && 
			   isset( $data_sources[ $source_id ]['status'] ) &&
			   $data_sources[ $source_id ]['status'] === 'available';
	}

	/**
	 * Get data source by ID
	 *
	 * @param string $source_id Data source ID.
	 * @return array|null Data source configuration or null if not found.
	 */
	public static function get_data_source( string $source_id ): ?array {
		$data_sources = self::get_data_sources();
		
		return $data_sources[ $source_id ] ?? null;
	}
}
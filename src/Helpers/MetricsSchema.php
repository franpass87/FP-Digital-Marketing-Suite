<?php
/**
 * Metrics Schema Definition
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Metrics Schema class
 *
 * This class defines the common schema for normalizing metrics across different data sources.
 * It provides mapping between source-specific metric names and standardized KPIs.
 */
class MetricsSchema {

	/**
	 * Standard KPI metric names
	 */
	public const KPI_SESSIONS            = 'sessions';
	public const KPI_USERS               = 'users';
	public const KPI_PAGEVIEWS           = 'pageviews';
	public const KPI_BOUNCE_RATE         = 'bounce_rate';
	public const KPI_CONVERSIONS         = 'conversions';
	public const KPI_REVENUE             = 'revenue';
	public const KPI_IMPRESSIONS         = 'impressions';
	public const KPI_CLICKS              = 'clicks';
	public const KPI_CTR                 = 'ctr';
	public const KPI_CPC                 = 'cpc';
	public const KPI_COST                = 'cost';
	public const KPI_ORGANIC_CLICKS      = 'organic_clicks';
	public const KPI_ORGANIC_IMPRESSIONS = 'organic_impressions';
	public const KPI_AVG_POSITION        = 'avg_position';
	public const KPI_EMAIL_OPENS         = 'email_opens';
	public const KPI_EMAIL_CLICKS        = 'email_clicks';

	// Core Web Vitals metrics
	public const KPI_LCP = 'lcp'; // Largest Contentful Paint
	public const KPI_INP = 'inp'; // Interaction to Next Paint
	public const KPI_CLS = 'cls'; // Cumulative Layout Shift

	/**
	 * Metric categories for grouping related KPIs
	 */
	public const CATEGORY_TRAFFIC     = 'traffic';
	public const CATEGORY_ENGAGEMENT  = 'engagement';
	public const CATEGORY_CONVERSIONS = 'conversions';
	public const CATEGORY_ADVERTISING = 'advertising';
	public const CATEGORY_SEARCH      = 'search';
	public const CATEGORY_EMAIL       = 'email';
	public const CATEGORY_PERFORMANCE = 'performance';

	/**
	 * Get all standard KPI definitions
	 *
	 * @return array KPI definitions with metadata
	 */
	public static function get_kpi_definitions(): array {
		return [
			self::KPI_SESSIONS            => [
				'name'        => __( 'Sessioni', 'fp-digital-marketing' ),
				'description' => __( 'Numero totale di sessioni sul sito', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_TRAFFIC,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_USERS               => [
				'name'        => __( 'Utenti', 'fp-digital-marketing' ),
				'description' => __( 'Numero di utenti unici', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_TRAFFIC,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_PAGEVIEWS           => [
				'name'        => __( 'Visualizzazioni Pagina', 'fp-digital-marketing' ),
				'description' => __( 'Numero totale di pagine visualizzate', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_TRAFFIC,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_BOUNCE_RATE         => [
				'name'        => __( 'Frequenza Rimbalzo', 'fp-digital-marketing' ),
				'description' => __( 'Percentuale di sessioni con una sola pagina visualizzata', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_ENGAGEMENT,
				'format'      => 'percentage',
				'aggregation' => 'average',
			],
			self::KPI_CONVERSIONS         => [
				'name'        => __( 'Conversioni', 'fp-digital-marketing' ),
				'description' => __( 'Numero totale di conversioni', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_CONVERSIONS,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_REVENUE             => [
				'name'        => __( 'Fatturato', 'fp-digital-marketing' ),
				'description' => __( 'Fatturato totale generato', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_CONVERSIONS,
				'format'      => 'currency',
				'aggregation' => 'sum',
			],
			self::KPI_IMPRESSIONS         => [
				'name'        => __( 'Impressioni', 'fp-digital-marketing' ),
				'description' => __( 'Numero di volte che un annuncio è stato mostrato', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_ADVERTISING,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_CLICKS              => [
				'name'        => __( 'Clic', 'fp-digital-marketing' ),
				'description' => __( 'Numero di clic sugli annunci', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_ADVERTISING,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_CTR                 => [
				'name'        => __( 'CTR', 'fp-digital-marketing' ),
				'description' => __( 'Click-through rate (percentuale di clic)', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_ADVERTISING,
				'format'      => 'percentage',
				'aggregation' => 'average',
			],
			self::KPI_CPC                 => [
				'name'        => __( 'CPC', 'fp-digital-marketing' ),
				'description' => __( 'Costo per clic medio', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_ADVERTISING,
				'format'      => 'currency',
				'aggregation' => 'average',
			],
			self::KPI_COST                => [
				'name'        => __( 'Costo', 'fp-digital-marketing' ),
				'description' => __( 'Costo totale della campagna', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_ADVERTISING,
				'format'      => 'currency',
				'aggregation' => 'sum',
			],
			self::KPI_ORGANIC_CLICKS      => [
				'name'        => __( 'Clic Organici', 'fp-digital-marketing' ),
				'description' => __( 'Clic dai risultati di ricerca organici', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_SEARCH,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_ORGANIC_IMPRESSIONS => [
				'name'        => __( 'Impressioni Organiche', 'fp-digital-marketing' ),
				'description' => __( 'Impressioni nei risultati di ricerca organici', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_SEARCH,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_AVG_POSITION        => [
				'name'        => __( 'Posizione Media', 'fp-digital-marketing' ),
				'description' => __( 'Posizione media nei risultati di ricerca', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_SEARCH,
				'format'      => 'decimal',
				'aggregation' => 'avg',
			],
			self::KPI_EMAIL_OPENS         => [
				'name'        => __( 'Aperture Email', 'fp-digital-marketing' ),
				'description' => __( 'Numero di email aperte', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_EMAIL,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_EMAIL_CLICKS        => [
				'name'        => __( 'Clic Email', 'fp-digital-marketing' ),
				'description' => __( 'Numero di clic nelle email', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_EMAIL,
				'format'      => 'number',
				'aggregation' => 'sum',
			],
			self::KPI_LCP                 => [
				'name'        => __( 'LCP (ms)', 'fp-digital-marketing' ),
				'description' => __( 'Largest Contentful Paint - tempo di caricamento elemento principale', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_PERFORMANCE,
				'format'      => 'milliseconds',
				'aggregation' => 'percentile_75',
				'thresholds'  => [
					'good'              => 2500,
					'needs_improvement' => 4000,
				],
			],
			self::KPI_INP                 => [
				'name'        => __( 'INP (ms)', 'fp-digital-marketing' ),
				'description' => __( 'Interaction to Next Paint - reattività delle interazioni', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_PERFORMANCE,
				'format'      => 'milliseconds',
				'aggregation' => 'percentile_75',
				'thresholds'  => [
					'good'              => 200,
					'needs_improvement' => 500,
				],
			],
			self::KPI_CLS                 => [
				'name'        => __( 'CLS', 'fp-digital-marketing' ),
				'description' => __( 'Cumulative Layout Shift - stabilità visuale della pagina', 'fp-digital-marketing' ),
				'category'    => self::CATEGORY_PERFORMANCE,
				'format'      => 'decimal',
				'aggregation' => 'percentile_75',
				'thresholds'  => [
					'good'              => 0.1,
					'needs_improvement' => 0.25,
				],
			],
		];
	}

	/**
	 * Get metric mappings for each data source
	 *
	 * @return array Source-specific metric mappings to standard KPIs
	 */
	public static function get_source_mappings(): array {
		return [
			'google_analytics_4'    => [
				'sessions'        => self::KPI_SESSIONS,
				'users'           => self::KPI_USERS,
				'screenPageViews' => self::KPI_PAGEVIEWS,
				'pageviews'       => self::KPI_PAGEVIEWS,
				'bounceRate'      => self::KPI_BOUNCE_RATE,
				'bounce_rate'     => self::KPI_BOUNCE_RATE,
				'conversions'     => self::KPI_CONVERSIONS,
				'purchaseRevenue' => self::KPI_REVENUE,
				'revenue'         => self::KPI_REVENUE,
			],
			'google_search_console' => [
				'clicks'      => self::KPI_ORGANIC_CLICKS,
				'impressions' => self::KPI_ORGANIC_IMPRESSIONS,
				'ctr'         => self::KPI_CTR,
				'position'    => self::KPI_AVG_POSITION,
			],
			'facebook_ads'          => [
				'impressions'      => self::KPI_IMPRESSIONS,
				'clicks'           => self::KPI_CLICKS,
				'ctr'              => self::KPI_CTR,
				'cpc'              => self::KPI_CPC,
				'spend'            => self::KPI_COST,
				'conversions'      => self::KPI_CONVERSIONS,
				'conversion_value' => self::KPI_REVENUE,
			],
			'google_ads'            => [
				'impressions'      => self::KPI_IMPRESSIONS,
				'clicks'           => self::KPI_CLICKS,
				'ctr'              => self::KPI_CTR,
				'avg_cpc'          => self::KPI_CPC,
				'cost'             => self::KPI_COST,
				'conversions'      => self::KPI_CONVERSIONS,
				'conversion_value' => self::KPI_REVENUE,
			],
			'mailchimp'             => [
				'opens'      => self::KPI_EMAIL_OPENS,
				'clicks'     => self::KPI_EMAIL_CLICKS,
				'open_rate'  => 'email_open_rate', // Non-standard KPI
				'click_rate' => 'email_click_rate', // Non-standard KPI
			],
			'core_web_vitals'       => [
				'lcp' => self::KPI_LCP,
				'inp' => self::KPI_INP,
				'cls' => self::KPI_CLS,
				'fid' => self::KPI_INP, // Map FID to INP for backwards compatibility
			],
		];
	}

	/**
	 * Normalize a metric name from a specific source to standard KPI
	 *
	 * @param string $source Source identifier
	 * @param string $metric Original metric name
	 * @return string Normalized KPI name
	 */
	public static function normalize_metric_name( string $source, string $metric ): string {
		$mappings = self::get_source_mappings();

		if ( isset( $mappings[ $source ][ $metric ] ) ) {
			return $mappings[ $source ][ $metric ];
		}

		// Return original metric name if no mapping found
		return $metric;
	}

	/**
	 * Get KPIs by category
	 *
	 * @param string $category Category name
	 * @return array KPI names in the specified category
	 */
	public static function get_kpis_by_category( string $category ): array {
		$kpi_definitions = self::get_kpi_definitions();
		$category_kpis   = [];

		foreach ( $kpi_definitions as $kpi => $definition ) {
			if ( $definition['category'] === $category ) {
				$category_kpis[] = $kpi;
			}
		}

		return $category_kpis;
	}

	/**
	 * Get all available categories
	 *
	 * @return array Category definitions
	 */
	public static function get_categories(): array {
		return [
			self::CATEGORY_TRAFFIC     => [
				'name'        => __( 'Traffico', 'fp-digital-marketing' ),
				'description' => __( 'Metriche di traffico del sito web', 'fp-digital-marketing' ),
			],
			self::CATEGORY_ENGAGEMENT  => [
				'name'        => __( 'Coinvolgimento', 'fp-digital-marketing' ),
				'description' => __( 'Metriche di coinvolgimento degli utenti', 'fp-digital-marketing' ),
			],
			self::CATEGORY_CONVERSIONS => [
				'name'        => __( 'Conversioni', 'fp-digital-marketing' ),
				'description' => __( 'Metriche di conversione e fatturato', 'fp-digital-marketing' ),
			],
			self::CATEGORY_ADVERTISING => [
				'name'        => __( 'Pubblicità', 'fp-digital-marketing' ),
				'description' => __( 'Metriche delle campagne pubblicitarie', 'fp-digital-marketing' ),
			],
			self::CATEGORY_SEARCH      => [
				'name'        => __( 'Ricerca', 'fp-digital-marketing' ),
				'description' => __( 'Metriche di ricerca organica (SEO)', 'fp-digital-marketing' ),
			],
			self::CATEGORY_EMAIL       => [
				'name'        => __( 'Email', 'fp-digital-marketing' ),
				'description' => __( 'Metriche di email marketing', 'fp-digital-marketing' ),
			],
			self::CATEGORY_PERFORMANCE => [
				'name'        => __( 'Performance', 'fp-digital-marketing' ),
				'description' => __( 'Core Web Vitals e metriche di performance', 'fp-digital-marketing' ),
			],
		];
	}

	/**
	 * Validate if a metric is a standard KPI
	 *
	 * @param string $metric Metric name
	 * @return bool True if it's a standard KPI
	 */
	public static function is_standard_kpi( string $metric ): bool {
		$kpi_definitions = self::get_kpi_definitions();
		return array_key_exists( $metric, $kpi_definitions );
	}

	/**
	 * Get aggregation method for a KPI
	 *
	 * @param string $kpi KPI name
	 * @return string Aggregation method (sum, average, etc.)
	 */
	public static function get_aggregation_method( string $kpi ): string {
		$kpi_definitions = self::get_kpi_definitions();
		return $kpi_definitions[ $kpi ]['aggregation'] ?? 'sum';
	}

	/**
	 * Get format type for a KPI
	 *
	 * @param string $kpi KPI name
	 * @return string Format type (number, percentage, currency)
	 */
	public static function get_format_type( string $kpi ): string {
		$kpi_definitions = self::get_kpi_definitions();
		return $kpi_definitions[ $kpi ]['format'] ?? 'number';
	}

	/**
	 * Get performance status for Core Web Vitals metrics
	 *
	 * @param string $kpi KPI name
	 * @param float  $value Metric value
	 * @return string Performance status (good, needs_improvement, poor)
	 */
	public static function get_performance_status( string $kpi, float $value ): string {
		$kpi_definitions = self::get_kpi_definitions();

		if ( ! isset( $kpi_definitions[ $kpi ]['thresholds'] ) ) {
			return 'unknown';
		}

		$thresholds = $kpi_definitions[ $kpi ]['thresholds'];

		if ( $value <= $thresholds['good'] ) {
			return 'good';
		} elseif ( $value <= $thresholds['needs_improvement'] ) {
			return 'needs_improvement';
		} else {
			return 'poor';
		}
	}

	/**
	 * Get performance status color
	 *
	 * @param string $status Performance status
	 * @return string CSS color class
	 */
	public static function get_performance_color( string $status ): string {
		$colors = [
			'good'              => 'green',
			'needs_improvement' => 'orange',
			'poor'              => 'red',
			'unknown'           => 'gray',
		];

		return $colors[ $status ] ?? 'gray';
	}
}

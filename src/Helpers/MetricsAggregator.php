<?php
/**
 * Metrics Aggregator
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Metrics Aggregator class
 * 
 * This class provides unified metric aggregation across multiple data sources,
 * normalizing data to a common schema and providing internal API for queries.
 */
class MetricsAggregator {

	/**
	 * Default fallback values for missing data
	 */
	private const DEFAULT_FALLBACKS = [
		MetricsSchema::KPI_SESSIONS => 0,
		MetricsSchema::KPI_USERS => 0,
		MetricsSchema::KPI_PAGEVIEWS => 0,
		MetricsSchema::KPI_BOUNCE_RATE => 0.0,
		MetricsSchema::KPI_CONVERSIONS => 0,
		MetricsSchema::KPI_REVENUE => 0.0,
		MetricsSchema::KPI_IMPRESSIONS => 0,
		MetricsSchema::KPI_CLICKS => 0,
		MetricsSchema::KPI_CTR => 0.0,
		MetricsSchema::KPI_CPC => 0.0,
		MetricsSchema::KPI_COST => 0.0,
		MetricsSchema::KPI_ORGANIC_CLICKS => 0,
		MetricsSchema::KPI_ORGANIC_IMPRESSIONS => 0,
		MetricsSchema::KPI_EMAIL_OPENS => 0,
		MetricsSchema::KPI_EMAIL_CLICKS => 0,
	];

	/**
	 * Get aggregated metrics for a client across all sources
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param array $kpis Optional. Specific KPIs to retrieve
	 * @param array $sources Optional. Specific sources to include
	 * @return array Aggregated metrics grouped by KPI
	 */
	public static function get_aggregated_metrics( int $client_id, string $period_start, string $period_end, array $kpis = [], array $sources = [] ): array {
		try {
			// Generate cache key for performance caching
			$cache_params = [
				'client_id' => $client_id,
				'period_start' => $period_start,
				'period_end' => $period_end,
				'kpis' => $kpis,
				'sources' => $sources,
			];
			$cache_key = PerformanceCache::generate_metrics_key( $cache_params );

			// Use performance cache with fallback to database query
			return PerformanceCache::get_cached(
				$cache_key,
				PerformanceCache::CACHE_GROUP_AGGREGATED,
				function() use ( $client_id, $period_start, $period_end, $kpis, $sources ) {
					return self::get_aggregated_metrics_uncached( $client_id, $period_start, $period_end, $kpis, $sources );
				},
				PerformanceCache::get_cache_settings()['aggregated_ttl']
			);
		} catch ( \Throwable $e ) {
			// Log error and return fallback data to prevent WSOD
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing MetricsAggregator Error: ' . $e->getMessage() );
			}
			
			// Return fallback empty array with basic structure
			return [];
		}
	}

	/**
	 * Get aggregated metrics without caching (internal method)
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param array $kpis Optional. Specific KPIs to retrieve
	 * @param array $sources Optional. Specific sources to include
	 * @return array Aggregated metrics grouped by KPI
	 */
	private static function get_aggregated_metrics_uncached( int $client_id, string $period_start, string $period_end, array $kpis = [], array $sources = [] ): array {
		try {
			// Get raw metrics from cache
			$criteria = [
				'client_id' => $client_id,
				'period_start' => $period_start,
				'period_end' => $period_end,
			];

			if ( ! empty( $sources ) ) {
				$criteria['source'] = $sources;
			}

			$raw_metrics = MetricsCache::get_metrics( $criteria );

			// Normalize and aggregate
			$aggregated = [];
			
			foreach ( $raw_metrics as $metric ) {
				$normalized_kpi = MetricsSchema::normalize_metric_name( $metric->source, $metric->metric );
				
				// Filter by requested KPIs if specified
				if ( ! empty( $kpis ) && ! in_array( $normalized_kpi, $kpis, true ) ) {
					continue;
				}

				if ( ! isset( $aggregated[ $normalized_kpi ] ) ) {
					$aggregated[ $normalized_kpi ] = [
						'kpi' => $normalized_kpi,
						'values' => [],
						'sources' => [],
						'total_value' => 0,
						'count' => 0,
					];
				}

			$value = is_numeric( $metric->value ) ? (float) $metric->value : 0;
			$aggregated[ $normalized_kpi ]['values'][] = $value;
			$aggregated[ $normalized_kpi ]['sources'][] = $metric->source;
			$aggregated[ $normalized_kpi ]['count']++;

			// Apply aggregation method
			$aggregation_method = MetricsSchema::get_aggregation_method( $normalized_kpi );
			
			if ( $aggregation_method === 'sum' ) {
				$aggregated[ $normalized_kpi ]['total_value'] += $value;
			} elseif ( $aggregation_method === 'average' ) {
				$aggregated[ $normalized_kpi ]['total_value'] = array_sum( $aggregated[ $normalized_kpi ]['values'] ) / $aggregated[ $normalized_kpi ]['count'];
			}
		}

		// Apply fallbacks for missing KPIs
		$aggregated = self::apply_fallbacks( $aggregated, $kpis );

		return $aggregated;
	} catch ( \Throwable $e ) {
		// Log error and return fallback data to prevent WSOD
		if ( function_exists( 'error_log' ) ) {
			error_log( 'FP Digital Marketing MetricsAggregator Uncached Error: ' . $e->getMessage() );
		}
		
		// Return fallback data with default values
		return self::apply_fallbacks( [], $kpis );
	}
}

	/**
	 * Get KPI summary for a client
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param string $category Optional. Filter by KPI category
	 * @return array KPI summary with normalized values
	 */
	public static function get_kpi_summary( int $client_id, string $period_start, string $period_end, string $category = '' ): array {
		try {
			// Generate cache key for KPI summary
			$cache_params = [
				'method' => 'kpi_summary',
				'client_id' => $client_id,
				'period_start' => $period_start,
				'period_end' => $period_end,
				'category' => $category,
			];
			$cache_key = PerformanceCache::generate_metrics_key( $cache_params );

			// Use performance cache with fallback to computation
			return PerformanceCache::get_cached(
				$cache_key,
				PerformanceCache::CACHE_GROUP_REPORTS,
				function() use ( $client_id, $period_start, $period_end, $category ) {
					return self::get_kpi_summary_uncached( $client_id, $period_start, $period_end, $category );
				},
				PerformanceCache::get_cache_settings()['reports_ttl']
			);
		} catch ( \Throwable $e ) {
			// Log error and return fallback data to prevent WSOD
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FP Digital Marketing KPI Summary Error: ' . $e->getMessage() );
			}
			
			// Return basic fallback structure
			return [
				'kpis' => [],
				'summary' => [
					'total_sessions' => 0,
					'total_users' => 0,
					'total_conversions' => 0,
					'total_revenue' => 0.0,
				],
			];
		}
	}

	/**
	 * Get KPI summary without caching (internal method)
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param string $category Optional. Filter by KPI category
	 * @return array KPI summary with normalized values
	 */
	private static function get_kpi_summary_uncached( int $client_id, string $period_start, string $period_end, string $category = '' ): array {
		$kpis = [];
		
		if ( $category ) {
			$kpis = MetricsSchema::get_kpis_by_category( $category );
		}

		$aggregated_metrics = self::get_aggregated_metrics( $client_id, $period_start, $period_end, $kpis );
		
		$summary = [];
		
		foreach ( $aggregated_metrics as $kpi => $data ) {
			$kpi_definitions = MetricsSchema::get_kpi_definitions();
			$definition = $kpi_definitions[ $kpi ] ?? [];
			
			$summary[ $kpi ] = [
				'name' => $definition['name'] ?? $kpi,
				'description' => $definition['description'] ?? '',
				'category' => $definition['category'] ?? 'other',
				'value' => $data['total_value'],
				'formatted_value' => self::format_value( $data['total_value'], $definition['format'] ?? 'number' ),
				'sources' => array_unique( $data['sources'] ?? [] ),
				'source_count' => count( array_unique( $data['sources'] ?? [] ) ),
				'has_data' => $data['count'] > 0,
			];
		}

		return $summary;
	}

	/**
	 * Get metrics comparison between two periods
	 *
	 * @param int $client_id Client ID
	 * @param string $current_start Current period start
	 * @param string $current_end Current period end
	 * @param string $previous_start Previous period start
	 * @param string $previous_end Previous period end
	 * @param array $kpis Optional. Specific KPIs to compare
	 * @return array Comparison data with change percentages
	 */
	public static function get_period_comparison( int $client_id, string $current_start, string $current_end, string $previous_start, string $previous_end, array $kpis = [] ): array {
		// Generate cache key for period comparison
		$cache_params = [
			'method' => 'period_comparison',
			'client_id' => $client_id,
			'current_start' => $current_start,
			'current_end' => $current_end,
			'previous_start' => $previous_start,
			'previous_end' => $previous_end,
			'kpis' => $kpis,
		];
		$cache_key = PerformanceCache::generate_metrics_key( $cache_params );

		// Use performance cache with fallback to computation
		return PerformanceCache::get_cached(
			$cache_key,
			PerformanceCache::CACHE_GROUP_REPORTS,
			function() use ( $client_id, $current_start, $current_end, $previous_start, $previous_end, $kpis ) {
				return self::get_period_comparison_uncached( $client_id, $current_start, $current_end, $previous_start, $previous_end, $kpis );
			},
			PerformanceCache::get_cache_settings()['reports_ttl']
		);
	}

	/**
	 * Get metrics comparison without caching (internal method)
	 *
	 * @param int $client_id Client ID
	 * @param string $current_start Current period start
	 * @param string $current_end Current period end
	 * @param string $previous_start Previous period start
	 * @param string $previous_end Previous period end
	 * @param array $kpis Optional. Specific KPIs to compare
	 * @return array Comparison data with change percentages
	 */
	private static function get_period_comparison_uncached( int $client_id, string $current_start, string $current_end, string $previous_start, string $previous_end, array $kpis = [] ): array {
		$current_metrics = self::get_aggregated_metrics( $client_id, $current_start, $current_end, $kpis );
		$previous_metrics = self::get_aggregated_metrics( $client_id, $previous_start, $previous_end, $kpis );

		$comparison = [];

		foreach ( $current_metrics as $kpi => $current_data ) {
			$previous_value = $previous_metrics[ $kpi ]['total_value'] ?? 0;
			$current_value = $current_data['total_value'];

			$change = $current_value - $previous_value;
			$change_percentage = $previous_value > 0 ? ( $change / $previous_value ) * 100 : 0;

			$comparison[ $kpi ] = [
				'kpi' => $kpi,
				'current_value' => $current_value,
				'previous_value' => $previous_value,
				'change' => $change,
				'change_percentage' => round( $change_percentage, 2 ),
				'trend' => $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'stable' ),
				'has_data' => $current_data['count'] > 0 || ( $previous_metrics[ $kpi ]['count'] ?? 0 ) > 0,
			];
		}

		return $comparison;
	}

	/**
	 * Get metrics by data source for a client
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @return array Metrics grouped by data source
	 */
	public static function get_metrics_by_source( int $client_id, string $period_start, string $period_end ): array {
		$criteria = [
			'client_id' => $client_id,
			'period_start' => $period_start,
			'period_end' => $period_end,
		];

		$raw_metrics = MetricsCache::get_metrics( $criteria );
		
		$by_source = [];

		foreach ( $raw_metrics as $metric ) {
			if ( ! isset( $by_source[ $metric->source ] ) ) {
				$by_source[ $metric->source ] = [
					'source' => $metric->source,
					'metrics' => [],
					'total_metrics' => 0,
				];
			}

			$normalized_kpi = MetricsSchema::normalize_metric_name( $metric->source, $metric->metric );
			
			$by_source[ $metric->source ]['metrics'][ $normalized_kpi ] = [
				'original_name' => $metric->metric,
				'normalized_name' => $normalized_kpi,
				'value' => $metric->value,
				'meta' => $metric->meta ? json_decode( $metric->meta, true ) : [],
				'fetched_at' => $metric->fetched_at,
			];
			
			$by_source[ $metric->source ]['total_metrics']++;
		}

		return $by_source;
	}

	/**
	 * Generate mock aggregated data for testing/demo purposes
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @return array Mock aggregated data
	 */
	public static function generate_mock_data( int $client_id, string $period_start, string $period_end ): array {
		$mock_data = [];
		
		// Generate realistic mock values for different KPIs
		$mock_values = [
			MetricsSchema::KPI_SESSIONS => rand( 800, 3000 ),
			MetricsSchema::KPI_USERS => rand( 600, 2500 ),
			MetricsSchema::KPI_PAGEVIEWS => rand( 2000, 8000 ),
			MetricsSchema::KPI_BOUNCE_RATE => rand( 40, 80 ) / 100,
			MetricsSchema::KPI_CONVERSIONS => rand( 15, 120 ),
			MetricsSchema::KPI_REVENUE => rand( 1500, 15000 ),
			MetricsSchema::KPI_IMPRESSIONS => rand( 10000, 50000 ),
			MetricsSchema::KPI_CLICKS => rand( 200, 1500 ),
			MetricsSchema::KPI_CTR => rand( 1, 8 ) / 100,
			MetricsSchema::KPI_CPC => rand( 50, 350 ) / 100,
			MetricsSchema::KPI_COST => rand( 200, 2000 ),
			MetricsSchema::KPI_ORGANIC_CLICKS => rand( 150, 800 ),
			MetricsSchema::KPI_ORGANIC_IMPRESSIONS => rand( 5000, 25000 ),
		];

		foreach ( $mock_values as $kpi => $value ) {
			$mock_data[ $kpi ] = [
				'kpi' => $kpi,
				'values' => [ $value ],
				'sources' => [ 'mock_source' ],
				'total_value' => $value,
				'count' => 1,
			];
		}

		return $mock_data;
	}

	/**
	 * Apply fallback values for missing KPIs
	 *
	 * @param array $aggregated Existing aggregated data
	 * @param array $requested_kpis Requested KPIs
	 * @return array Aggregated data with fallbacks applied
	 */
	private static function apply_fallbacks( array $aggregated, array $requested_kpis = [] ): array {
		$all_kpis = ! empty( $requested_kpis ) ? $requested_kpis : array_keys( self::DEFAULT_FALLBACKS );

		foreach ( $all_kpis as $kpi ) {
			if ( ! isset( $aggregated[ $kpi ] ) ) {
				$fallback_value = self::DEFAULT_FALLBACKS[ $kpi ] ?? 0;
				
				$aggregated[ $kpi ] = [
					'kpi' => $kpi,
					'values' => [ $fallback_value ],
					'sources' => [],
					'total_value' => $fallback_value,
					'count' => 0, // 0 indicates fallback data
				];
			}
		}

		return $aggregated;
	}

	/**
	 * Format value according to its type
	 *
	 * @param mixed $value Raw value
	 * @param string $format Format type (number, percentage, currency)
	 * @return string Formatted value
	 */
	private static function format_value( $value, string $format ): string {
		switch ( $format ) {
			case 'percentage':
				return number_format( (float) $value * 100, 2 ) . '%';
			case 'currency':
				return '€' . number_format( (float) $value, 2 );
			case 'number':
			default:
				return number_format( (float) $value );
		}
	}

	/**
	 * Get available data sources with metrics count
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @return array Available sources with data availability
	 */
	public static function get_source_availability( int $client_id, string $period_start, string $period_end ): array {
		$criteria = [
			'client_id' => $client_id,
			'period_start' => $period_start,
			'period_end' => $period_end,
		];

		$raw_metrics = MetricsCache::get_metrics( $criteria );
		
		$source_stats = [];
		$available_sources = DataSources::get_data_sources();

		// Initialize all sources
		foreach ( $available_sources as $source_id => $source_info ) {
			$source_stats[ $source_id ] = [
				'source_id' => $source_id,
				'name' => $source_info['name'],
				'status' => $source_info['status'],
				'metrics_count' => 0,
				'last_fetch' => null,
				'has_data' => false,
			];
		}

		// Count metrics per source
		foreach ( $raw_metrics as $metric ) {
			if ( isset( $source_stats[ $metric->source ] ) ) {
				$source_stats[ $metric->source ]['metrics_count']++;
				$source_stats[ $metric->source ]['has_data'] = true;
				
				if ( ! $source_stats[ $metric->source ]['last_fetch'] || $metric->fetched_at > $source_stats[ $metric->source ]['last_fetch'] ) {
					$source_stats[ $metric->source ]['last_fetch'] = $metric->fetched_at;
				}
			}
		}

		return $source_stats;
	}

	/**
	 * Get data quality report for a client
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @return array Data quality report
	 */
	public static function get_data_quality_report( int $client_id, string $period_start, string $period_end ): array {
		$source_availability = self::get_source_availability( $client_id, $period_start, $period_end );
		$kpi_summary = self::get_kpi_summary( $client_id, $period_start, $period_end );

		$total_sources = count( $source_availability );
		$active_sources = count( array_filter( $source_availability, function( $source ) {
			return $source['has_data'];
		} ) );

		$total_kpis = count( $kpi_summary );
		$kpis_with_data = count( array_filter( $kpi_summary, function( $kpi ) {
			return $kpi['has_data'];
		} ) );

		return [
			'client_id' => $client_id,
			'period' => [
				'start' => $period_start,
				'end' => $period_end,
			],
			'data_coverage' => [
				'total_sources' => $total_sources,
				'active_sources' => $active_sources,
				'source_coverage_percentage' => $total_sources > 0 ? round( ( $active_sources / $total_sources ) * 100, 2 ) : 0,
				'total_kpis' => $total_kpis,
				'kpis_with_data' => $kpis_with_data,
				'kpi_coverage_percentage' => $total_kpis > 0 ? round( ( $kpis_with_data / $total_kpis ) * 100, 2 ) : 0,
			],
			'source_details' => $source_availability,
			'missing_kpis' => array_keys( array_filter( $kpi_summary, function( $kpi ) {
				return ! $kpi['has_data'];
			} ) ),
			'recommendations' => self::generate_recommendations( $source_availability, $kpi_summary ),
		];
	}

	/**
	 * Advanced metrics query with comprehensive filtering
	 *
	 * @param array $query_params Query parameters array
	 * @return array Query results with metadata
	 */
	public static function query_metrics( array $query_params ): array {
		// Extract and validate query parameters
		$client_id = $query_params['client_id'] ?? 0;
		$period_start = $query_params['period_start'] ?? '';
		$period_end = $query_params['period_end'] ?? '';
		$kpis = $query_params['kpis'] ?? [];
		$sources = $query_params['sources'] ?? [];
		$source_types = $query_params['source_types'] ?? [];
		$categories = $query_params['categories'] ?? [];
		$metric_types = $query_params['metric_types'] ?? [];
		$aggregation_method = $query_params['aggregation'] ?? 'default';
		$include_trends = $query_params['include_trends'] ?? false;
		$limit = $query_params['limit'] ?? 0;
		$offset = $query_params['offset'] ?? 0;
		$sort_by = $query_params['sort_by'] ?? 'value';
		$sort_order = $query_params['sort_order'] ?? 'desc';

		// Build base criteria
		$criteria = [
			'client_id' => $client_id,
			'period_start' => $period_start,
			'period_end' => $period_end,
		];

		// Apply source filtering
		if ( ! empty( $sources ) ) {
			$criteria['source'] = $sources;
		} elseif ( ! empty( $source_types ) ) {
			$sources_by_type = self::get_sources_by_types( $source_types );
			if ( ! empty( $sources_by_type ) ) {
				$criteria['source'] = $sources_by_type;
			}
		}

		// Filter KPIs by categories if specified
		if ( ! empty( $categories ) ) {
			$kpis_by_category = [];
			foreach ( $categories as $category ) {
				$kpis_by_category = array_merge( $kpis_by_category, MetricsSchema::get_kpis_by_category( $category ) );
			}
			$kpis = ! empty( $kpis ) ? array_intersect( $kpis, $kpis_by_category ) : $kpis_by_category;
		}

		// Get aggregated metrics
		$aggregated_metrics = self::get_aggregated_metrics( $client_id, $period_start, $period_end, $kpis, $criteria['source'] ?? [] );

		// Filter by metric types if specified
		if ( ! empty( $metric_types ) ) {
			$aggregated_metrics = self::filter_by_metric_types( $aggregated_metrics, $metric_types );
		}

		// Apply custom aggregation if specified
		if ( $aggregation_method !== 'default' ) {
			$aggregated_metrics = self::apply_custom_aggregation( $aggregated_metrics, $aggregation_method );
		}

		// Add trend analysis if requested
		if ( $include_trends ) {
			$aggregated_metrics = self::add_trend_analysis( $aggregated_metrics, $client_id, $period_start, $period_end );
		}

		// Sort results
		$aggregated_metrics = self::sort_metrics( $aggregated_metrics, $sort_by, $sort_order );

		// Apply pagination
		if ( $limit > 0 ) {
			$aggregated_metrics = array_slice( $aggregated_metrics, $offset, $limit, true );
		}

		return [
			'query_params' => $query_params,
			'results' => $aggregated_metrics,
			'metadata' => [
				'total_results' => count( $aggregated_metrics ),
				'query_time' => date( 'Y-m-d H:i:s' ),
				'has_pagination' => $limit > 0,
				'offset' => $offset,
				'limit' => $limit,
			],
		];
	}

	/**
	 * Get metrics filtered by metric types
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param array $metric_types Metric types to filter by (traffic, conversion, engagement, etc.)
	 * @return array Filtered metrics
	 */
	public static function get_metrics_by_type( int $client_id, string $period_start, string $period_end, array $metric_types ): array {
		$kpis_by_type = [];
		
		foreach ( $metric_types as $type ) {
			switch ( $type ) {
				case 'traffic':
					$kpis_by_type = array_merge( $kpis_by_type, MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_TRAFFIC ) );
					break;
				case 'engagement':
					$kpis_by_type = array_merge( $kpis_by_type, MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_ENGAGEMENT ) );
					break;
				case 'conversion':
					$kpis_by_type = array_merge( $kpis_by_type, MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_CONVERSIONS ) );
					break;
				case 'advertising':
					$kpis_by_type = array_merge( $kpis_by_type, MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_ADVERTISING ) );
					break;
				case 'search':
					$kpis_by_type = array_merge( $kpis_by_type, MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_SEARCH ) );
					break;
				case 'email':
					$kpis_by_type = array_merge( $kpis_by_type, MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_EMAIL ) );
					break;
			}
		}

		return self::get_aggregated_metrics( $client_id, $period_start, $period_end, array_unique( $kpis_by_type ) );
	}

	/**
	 * Get metrics filtered by source types
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param array $source_types Source types to filter by (analytics, advertising, social, etc.)
	 * @return array Metrics from specified source types
	 */
	public static function get_metrics_by_source_type( int $client_id, string $period_start, string $period_end, array $source_types ): array {
		$sources = self::get_sources_by_types( $source_types );
		return self::get_aggregated_metrics( $client_id, $period_start, $period_end, [], $sources );
	}

	/**
	 * Get trending metrics with growth analysis
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param int $trend_periods Number of periods to analyze for trends (default: 4)
	 * @return array Metrics with trend analysis
	 */
	public static function get_trending_metrics( int $client_id, string $period_start, string $period_end, int $trend_periods = 4 ): array {
		$metrics = self::get_aggregated_metrics( $client_id, $period_start, $period_end );
		
		// Calculate period length
		$start_date = new \DateTime( $period_start );
		$end_date = new \DateTime( $period_end );
		$period_length = $start_date->diff( $end_date )->days;

		$trending_metrics = [];

		foreach ( $metrics as $kpi => $data ) {
			$trend_data = [];
			
			// Get historical data for trend analysis
			for ( $i = $trend_periods; $i > 0; $i-- ) {
				$trend_start = clone $start_date;
				$trend_end = clone $end_date;
				
				$trend_start->sub( new \DateInterval( "P{$i}D" ) );
				$trend_end->sub( new \DateInterval( "P{$i}D" ) );
				
				$historical_metrics = self::get_aggregated_metrics( 
					$client_id, 
					$trend_start->format( 'Y-m-d H:i:s' ), 
					$trend_end->format( 'Y-m-d H:i:s' ), 
					[ $kpi ] 
				);
				
				$trend_data[] = $historical_metrics[ $kpi ]['total_value'] ?? 0;
			}

			// Calculate trend direction and velocity
			$trend_direction = self::calculate_trend_direction( $trend_data );
			$trend_velocity = self::calculate_trend_velocity( $trend_data );

			$trending_metrics[ $kpi ] = array_merge( $data, [
				'trend' => [
					'direction' => $trend_direction,
					'velocity' => $trend_velocity,
					'historical_values' => $trend_data,
					'periods_analyzed' => $trend_periods,
				],
			] );
		}

		return $trending_metrics;
	}

	/**
	 * Search metrics by name or description
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param string $search_term Search term to match against metric names/descriptions
	 * @return array Matching metrics
	 */
	public static function search_metrics( int $client_id, string $period_start, string $period_end, string $search_term ): array {
		$all_metrics = self::get_aggregated_metrics( $client_id, $period_start, $period_end );
		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		
		$matching_metrics = [];
		$search_term_lower = strtolower( $search_term );

		foreach ( $all_metrics as $kpi => $data ) {
			$definition = $kpi_definitions[ $kpi ] ?? [];
			$name = $definition['name'] ?? $kpi;
			$description = $definition['description'] ?? '';

			// Search in KPI key, name, and description
			if ( 
				stripos( $kpi, $search_term ) !== false ||
				stripos( $name, $search_term ) !== false ||
				stripos( $description, $search_term ) !== false
			) {
				$matching_metrics[ $kpi ] = array_merge( $data, [
					'match_info' => [
						'matched_kpi' => stripos( $kpi, $search_term ) !== false,
						'matched_name' => stripos( $name, $search_term ) !== false,
						'matched_description' => stripos( $description, $search_term ) !== false,
					],
				] );
			}
		}

		return $matching_metrics;
	}

	/**
	 * Get sources by types
	 *
	 * @param array $source_types Source types to filter
	 * @return array Source IDs matching the types
	 */
	private static function get_sources_by_types( array $source_types ): array {
		$all_sources = DataSources::get_data_sources();
		$matching_sources = [];

		foreach ( $all_sources as $source_id => $source_info ) {
			if ( in_array( $source_info['type'], $source_types, true ) ) {
				$matching_sources[] = $source_id;
			}
		}

		return $matching_sources;
	}

	/**
	 * Filter metrics by metric types
	 *
	 * @param array $metrics Metrics to filter
	 * @param array $metric_types Types to filter by
	 * @return array Filtered metrics
	 */
	private static function filter_by_metric_types( array $metrics, array $metric_types ): array {
		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		$filtered = [];

		foreach ( $metrics as $kpi => $data ) {
			$definition = $kpi_definitions[ $kpi ] ?? [];
			$category = $definition['category'] ?? '';
			
			if ( in_array( $category, $metric_types, true ) ) {
				$filtered[ $kpi ] = $data;
			}
		}

		return $filtered;
	}

	/**
	 * Apply custom aggregation method
	 *
	 * @param array $metrics Metrics to re-aggregate
	 * @param string $method Aggregation method (sum, average, max, min)
	 * @return array Re-aggregated metrics
	 */
	private static function apply_custom_aggregation( array $metrics, string $method ): array {
		foreach ( $metrics as $kpi => &$data ) {
			if ( empty( $data['values'] ) ) {
				continue;
			}

			switch ( $method ) {
				case 'average':
					$data['total_value'] = array_sum( $data['values'] ) / count( $data['values'] );
					break;
				case 'max':
					$data['total_value'] = max( $data['values'] );
					break;
				case 'min':
					$data['total_value'] = min( $data['values'] );
					break;
				case 'sum':
				default:
					$data['total_value'] = array_sum( $data['values'] );
					break;
			}
		}

		return $metrics;
	}

	/**
	 * Add trend analysis to metrics
	 *
	 * @param array $metrics Current metrics
	 * @param int $client_id Client ID
	 * @param string $period_start Start date
	 * @param string $period_end End date
	 * @return array Metrics with trend data
	 */
	private static function add_trend_analysis( array $metrics, int $client_id, string $period_start, string $period_end ): array {
		// Calculate previous period for comparison
		$start_date = new \DateTime( $period_start );
		$end_date = new \DateTime( $period_end );
		$period_length = $start_date->diff( $end_date )->days;

		$prev_start = clone $start_date;
		$prev_end = clone $end_date;
		$prev_start->sub( new \DateInterval( "P{$period_length}D" ) );
		$prev_end->sub( new \DateInterval( "P{$period_length}D" ) );

		$comparison = self::get_period_comparison( 
			$client_id, 
			$period_start, 
			$period_end, 
			$prev_start->format( 'Y-m-d H:i:s' ), 
			$prev_end->format( 'Y-m-d H:i:s' ) 
		);

		foreach ( $metrics as $kpi => &$data ) {
			if ( isset( $comparison[ $kpi ] ) ) {
				$data['trend_analysis'] = [
					'change' => $comparison[ $kpi ]['change'],
					'change_percentage' => $comparison[ $kpi ]['change_percentage'],
					'trend' => $comparison[ $kpi ]['trend'],
					'previous_value' => $comparison[ $kpi ]['previous_value'],
				];
			}
		}

		return $metrics;
	}

	/**
	 * Sort metrics by specified field and order
	 *
	 * @param array $metrics Metrics to sort
	 * @param string $sort_by Field to sort by (value, name, change, etc.)
	 * @param string $sort_order Order (asc, desc)
	 * @return array Sorted metrics
	 */
	private static function sort_metrics( array $metrics, string $sort_by, string $sort_order ): array {
		$kpi_definitions = MetricsSchema::get_kpi_definitions();

		uasort( $metrics, function( $a, $b ) use ( $sort_by, $sort_order, $kpi_definitions ) {
			$value_a = 0;
			$value_b = 0;

			switch ( $sort_by ) {
				case 'value':
					$value_a = $a['total_value'];
					$value_b = $b['total_value'];
					break;
				case 'name':
					$def_a = $kpi_definitions[ $a['kpi'] ] ?? [];
					$def_b = $kpi_definitions[ $b['kpi'] ] ?? [];
					$value_a = $def_a['name'] ?? $a['kpi'];
					$value_b = $def_b['name'] ?? $b['kpi'];
					break;
				case 'change':
					$value_a = $a['trend_analysis']['change'] ?? 0;
					$value_b = $b['trend_analysis']['change'] ?? 0;
					break;
				case 'change_percentage':
					$value_a = $a['trend_analysis']['change_percentage'] ?? 0;
					$value_b = $b['trend_analysis']['change_percentage'] ?? 0;
					break;
				default:
					$value_a = $a['total_value'];
					$value_b = $b['total_value'];
					break;
			}

			if ( $sort_by === 'name' ) {
				$result = strcmp( $value_a, $value_b );
			} else {
				$result = $value_a <=> $value_b;
			}

			return $sort_order === 'desc' ? -$result : $result;
		} );

		return $metrics;
	}

	/**
	 * Calculate trend direction from historical data
	 *
	 * @param array $values Historical values
	 * @return string Trend direction (up, down, stable)
	 */
	private static function calculate_trend_direction( array $values ): string {
		if ( count( $values ) < 2 ) {
			return 'stable';
		}

		$first_half = array_slice( $values, 0, ceil( count( $values ) / 2 ) );
		$second_half = array_slice( $values, floor( count( $values ) / 2 ) );

		$first_avg = array_sum( $first_half ) / count( $first_half );
		$second_avg = array_sum( $second_half ) / count( $second_half );

		$change_threshold = 0.05; // 5% threshold for stability

		if ( $second_avg > $first_avg * ( 1 + $change_threshold ) ) {
			return 'up';
		} elseif ( $second_avg < $first_avg * ( 1 - $change_threshold ) ) {
			return 'down';
		}

		return 'stable';
	}

	/**
	 * Calculate trend velocity from historical data
	 *
	 * @param array $values Historical values
	 * @return float Trend velocity (rate of change)
	 */
	private static function calculate_trend_velocity( array $values ): float {
		if ( count( $values ) < 2 ) {
			return 0.0;
		}

		// Simple linear regression slope calculation
		$n = count( $values );
		$sum_x = ( $n * ( $n + 1 ) ) / 2;
		$sum_y = array_sum( $values );
		$sum_xy = 0;
		$sum_x2 = ( $n * ( $n + 1 ) * ( 2 * $n + 1 ) ) / 6;

		foreach ( $values as $i => $value ) {
			$sum_xy += ( $i + 1 ) * $value;
		}

		$slope = ( $n * $sum_xy - $sum_x * $sum_y ) / ( $n * $sum_x2 - $sum_x * $sum_x );

		return round( $slope, 4 );
	}

	/**
	 * Generate recommendations based on data quality
	 *
	 * @param array $source_availability Source availability data
	 * @param array $kpi_summary KPI summary data
	 * @return array Recommendations for improving data coverage
	 */
	private static function generate_recommendations( array $source_availability, array $kpi_summary ): array {
		$recommendations = [];

		// Check for inactive sources
		foreach ( $source_availability as $source ) {
			if ( $source['status'] === 'available' && ! $source['has_data'] ) {
				$recommendations[] = sprintf(
					__( 'Configura la sorgente dati %s per migliorare la copertura dei dati', 'fp-digital-marketing' ),
					$source['name']
				);
			}
		}

		// Check for missing KPI categories
		$categories_with_data = [];
		foreach ( $kpi_summary as $kpi => $data ) {
			if ( $data['has_data'] ) {
				$categories_with_data[ $data['category'] ] = true;
			}
		}

		$all_categories = MetricsSchema::get_categories();
		foreach ( $all_categories as $category_id => $category_info ) {
			if ( ! isset( $categories_with_data[ $category_id ] ) ) {
				$recommendations[] = sprintf(
					__( 'Nessun dato disponibile per la categoria %s', 'fp-digital-marketing' ),
					$category_info['name']
				);
			}
		}

		if ( empty( $recommendations ) ) {
			$recommendations[] = __( 'Ottima copertura dei dati! Tutti i KPI principali sono disponibili.', 'fp-digital-marketing' );
		}

		return $recommendations;
	}

	/**
	 * Get conversion events aggregated metrics
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param array $event_types Optional. Specific event types to include
	 * @return array Aggregated conversion metrics
	 */
	public static function get_conversion_events_metrics( int $client_id, string $period_start, string $period_end, array $event_types = [] ): array {
		$criteria = [
			'client_id' => $client_id,
			'period_start' => $period_start,
			'period_end' => $period_end,
			'exclude_duplicates' => true,
		];

		if ( ! empty( $event_types ) ) {
			$criteria['event_type'] = $event_types;
		}

		// Get conversion events summary
		$summary = \FP\DigitalMarketing\Helpers\ConversionEventManager::get_events_summary( $criteria );

		// Build normalized metrics for aggregation
		$conversion_metrics = [
			MetricsSchema::KPI_CONVERSIONS => [
				'value' => $summary['total_events'],
				'source' => 'conversion_events',
				'category' => MetricsSchema::CATEGORY_CONVERSIONS,
				'format' => 'number',
			],
			MetricsSchema::KPI_REVENUE => [
				'value' => $summary['total_value'],
				'source' => 'conversion_events',
				'category' => MetricsSchema::CATEGORY_CONVERSIONS,
				'format' => 'currency',
			],
		];

		// Add per-event-type metrics
		foreach ( $summary['breakdown_by_type'] as $breakdown ) {
			$event_type = $breakdown['event_type'];
			$conversion_metrics[ "conversions_{$event_type}" ] = [
				'value' => $breakdown['count'],
				'source' => 'conversion_events',
				'category' => MetricsSchema::CATEGORY_CONVERSIONS,
				'format' => 'number',
				'event_type' => $event_type,
			];
			$conversion_metrics[ "revenue_{$event_type}" ] = [
				'value' => $breakdown['total_value'],
				'source' => 'conversion_events',
				'category' => MetricsSchema::CATEGORY_CONVERSIONS,
				'format' => 'currency',
				'event_type' => $event_type,
			];
		}

		return $conversion_metrics;
	}

	/**
	 * Get enhanced aggregated metrics including conversion events
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param array $kpis Optional. Specific KPIs to retrieve
	 * @param array $sources Optional. Specific sources to include
	 * @param bool $include_conversion_events Whether to include conversion events data
	 * @return array Enhanced aggregated metrics
	 */
	public static function get_enhanced_aggregated_metrics( int $client_id, string $period_start, string $period_end, array $kpis = [], array $sources = [], bool $include_conversion_events = true ): array {
		// Get base metrics
		$metrics = self::get_aggregated_metrics( $client_id, $period_start, $period_end, $kpis, $sources );

		// Add conversion events metrics if requested
		if ( $include_conversion_events ) {
			$conversion_metrics = self::get_conversion_events_metrics( $client_id, $period_start, $period_end );
			
			// Merge conversion metrics into base metrics
			foreach ( $conversion_metrics as $metric_key => $metric_data ) {
				if ( empty( $kpis ) || in_array( $metric_key, $kpis, true ) ) {
					$metrics[ $metric_key ] = $metric_data;
				}
			}
		}

		return $metrics;
	}

	/**
	 * Get conversion funnel metrics
	 *
	 * @param int $client_id Client ID
	 * @param array $funnel_steps Array of event types in funnel order
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @return array Funnel metrics with conversion rates
	 */
	public static function get_conversion_funnel_metrics( int $client_id, array $funnel_steps, string $period_start, string $period_end ): array {
		$criteria = [
			'period_start' => $period_start,
			'period_end' => $period_end,
		];

		$funnel_data = \FP\DigitalMarketing\Helpers\ConversionEventManager::get_conversion_funnel( $client_id, $funnel_steps, $criteria );

		// Convert to metrics format
		$funnel_metrics = [];
		foreach ( $funnel_data as $step_data ) {
			$step_key = "funnel_step_{$step_data['step']}";
			$funnel_metrics[ $step_key ] = [
				'value' => $step_data['count'],
				'conversion_rate' => $step_data['conversion_rate'],
				'drop_off_rate' => $step_data['drop_off_rate'],
				'event_type' => $step_data['event_type'],
				'event_name' => $step_data['event_name'],
				'total_value' => $step_data['total_value'],
				'source' => 'conversion_funnel',
				'category' => MetricsSchema::CATEGORY_CONVERSIONS,
				'format' => 'number',
			];
		}

		return $funnel_metrics;
	}

	/**
	 * Get campaign conversion metrics
	 *
	 * @param int $client_id Client ID
	 * @param string $period_start Start date (Y-m-d H:i:s)
	 * @param string $period_end End date (Y-m-d H:i:s)
	 * @param int $limit Number of campaigns to return
	 * @return array Top converting campaigns with metrics
	 */
	public static function get_campaign_conversion_metrics( int $client_id, string $period_start, string $period_end, int $limit = 10 ): array {
		$criteria = [
			'period_start' => $period_start,
			'period_end' => $period_end,
		];

		$campaigns = \FP\DigitalMarketing\Helpers\ConversionEventManager::get_top_converting_campaigns( $client_id, $criteria, $limit );

		// Convert to metrics format
		$campaign_metrics = [];
		foreach ( $campaigns as $i => $campaign ) {
			$campaign_key = "campaign_" . sanitize_key( $campaign['utm_campaign'] );
			$campaign_metrics[ $campaign_key ] = [
				'campaign_name' => $campaign['utm_campaign'],
				'utm_source' => $campaign['utm_source'],
				'utm_medium' => $campaign['utm_medium'],
				'conversion_count' => $campaign['conversion_count'],
				'total_value' => $campaign['total_value'],
				'avg_value' => $campaign['avg_value'],
				'source' => 'campaign_conversions',
				'category' => MetricsSchema::CATEGORY_CONVERSIONS,
				'rank' => $i + 1,
			];
		}

		return $campaign_metrics;
	}
}
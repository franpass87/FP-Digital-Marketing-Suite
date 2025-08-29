<?php
/**
 * Metrics Aggregator
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Models\MetricsCache;

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
}
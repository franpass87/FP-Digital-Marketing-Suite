<?php
/**
 * Core Web Vitals Metrics Model.
 *
 * Provides helper methods to persist and query Core Web Vitals metrics
 * stored in the unified metrics cache. Handles period normalization so
 * reporting layers can rely on consistent ranges regardless of the
 * shorthand period key that is provided.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Models;

use FP\DigitalMarketing\Helpers\PerformanceCache;

/**
 * Core Web Vitals model for metrics persistence and retrieval.
 */
class CoreWebVitals {

		/**
		 * Metrics cache source identifier.
		 */
	public const SOURCE = 'core_web_vitals';

		/**
		 * Default period range when none is provided.
		 */
	private const DEFAULT_PERIOD = '28_days';

		/**
		 * Alias map translating user facing period keys to canonical ones.
		 *
		 * @var array<string, string>
		 */
	private const PERIOD_ALIASES = [
		'default'         => self::DEFAULT_PERIOD,
		'28days'          => self::DEFAULT_PERIOD,
		'28_day'          => self::DEFAULT_PERIOD,
		'28_days'         => self::DEFAULT_PERIOD,
		'last_28_days'    => self::DEFAULT_PERIOD,
		'rolling_28_days' => self::DEFAULT_PERIOD,
		'last28days'      => self::DEFAULT_PERIOD,
		'7_days'          => '7_days',
		'last_7_days'     => '7_days',
		'7days'           => '7_days',
		'14_days'         => '14_days',
		'last_14_days'    => '14_days',
		'14days'          => '14_days',
		'30_days'         => '30_days',
		'last_30_days'    => '30_days',
		'30days'          => '30_days',
		'90_days'         => '90_days',
		'last_90_days'    => '90_days',
		'90days'          => '90_days',
		'6_months'        => '6_months',
		'last_6_months'   => '6_months',
		'6months'         => '6_months',
		'12_months'       => '12_months',
		'last_12_months'  => '12_months',
		'12months'        => '12_months',
		'custom'          => 'custom',
	];

		/**
		 * Period configuration expressed in days for simpler calculations.
		 *
		 * @var array<string, array<string, int>>
		 */
	private const PERIOD_DEFINITIONS = [
		'7_days'    => [ 'days' => 7 ],
		'14_days'   => [ 'days' => 14 ],
		'28_days'   => [ 'days' => 28 ],
		'30_days'   => [ 'days' => 30 ],
		'90_days'   => [ 'days' => 90 ],
		'6_months'  => [ 'days' => 182 ],
		'12_months' => [ 'days' => 365 ],
		'custom'    => [],
	];

		/**
		 * Persist Core Web Vitals metrics for the provided period.
		 *
		 * @param int    $client_id    Client identifier.
		 * @param array  $metrics      Metrics keyed by KPI.
		 * @param string $period_range Period shorthand (e.g. `28_days`).
		 * @param array  $options      Additional options: `period_start`, `period_end`, `meta`.
		 * @return void
		 */
	public static function store_metrics( int $client_id, array $metrics, string $period_range = self::DEFAULT_PERIOD, array $options = [] ): void {
		if ( $client_id <= 0 || empty( $metrics ) ) {
				return;
		}

			$period = self::resolve_period( $period_range, $options );

			$base_meta = $options['meta'] ?? [];
		if ( ! isset( $base_meta['collection_period'] ) || '' === (string) $base_meta['collection_period'] ) {
				$base_meta['collection_period'] = $period['range'];
		}

			$base_meta['period_range'] = $period['range'];
			$base_meta['period_days']  = $period['days'];

		foreach ( $metrics as $metric => $value ) {
				$metric_key = trim( (string) $metric );
			if ( '' === $metric_key ) {
					continue;
			}

				MetricsCache::save(
					$client_id,
					self::SOURCE,
					$metric_key,
					$period['start'],
					$period['end'],
					$value,
					self::filter_meta( $base_meta )
				);
		}
	}

		/**
		 * Fetch metrics for a client within the specified period range.
		 *
		 * @param int    $client_id    Client identifier.
		 * @param string $period_range Period shorthand (e.g. `28_days`).
		 * @param array  $options      Optional filters: `metrics`, `period_start`, `period_end`.
		 * @return array Structured metrics and resolved period metadata.
		 */
	public static function fetch_metrics( int $client_id, string $period_range = self::DEFAULT_PERIOD, array $options = [] ): array {
			$period = self::resolve_period( $period_range, $options );

		if ( $client_id <= 0 ) {
				return [
					'period'  => $period,
					'metrics' => [],
				];
		}

			$filters = [
				'client_id'    => $client_id,
				'source'       => self::SOURCE,
				'period_start' => $period['start'],
				'period_end'   => $period['end'],
			];

			if ( ! empty( $options['metrics'] ) ) {
					$filters['metric'] = is_array( $options['metrics'] )
							? array_map( 'strval', $options['metrics'] )
							: [ (string) $options['metrics'] ];
			}

			$query_callback = static function () use ( $filters ) {
					return MetricsCache::get_metrics( $filters );
			};

		if ( PerformanceCache::is_cache_enabled() ) {
				$cache_params = [
					'client_id'    => $client_id,
					'source'       => self::SOURCE,
					'period_start' => $period['start'],
					'period_end'   => $period['end'],
					'period_range' => $period['range'],
				];

				if ( isset( $filters['metric'] ) ) {
						$cache_params['metrics'] = $filters['metric'];
				}

				$cache_key = PerformanceCache::generate_metrics_key( $cache_params );
				$settings  = PerformanceCache::get_cache_settings();
				$records   = PerformanceCache::get_cached(
					$cache_key,
					PerformanceCache::CACHE_GROUP_METRICS,
					$query_callback,
					$settings['metrics_ttl'] ?? PerformanceCache::DEFAULT_TTL
				);
		} else {
				$records = $query_callback();
		}

		if ( ! is_array( $records ) ) {
				$records = [];
		}

			$metrics = [];
		foreach ( $records as $record ) {
				$metric_name = is_object( $record )
						? (string) ( $record->metric ?? '' )
						: (string) ( $record['metric'] ?? '' );

			if ( '' === $metric_name ) {
					continue;
			}

				$metrics[ $metric_name ] = [
					'value'        => self::cast_metric_value( is_object( $record ) ? ( $record->value ?? 0 ) : ( $record['value'] ?? 0 ) ),
					'period_start' => is_object( $record ) ? (string) ( $record->period_start ?? $period['start'] ) : (string) ( $record['period_start'] ?? $period['start'] ),
					'period_end'   => is_object( $record ) ? (string) ( $record->period_end ?? $period['end'] ) : (string) ( $record['period_end'] ?? $period['end'] ),
					'meta'         => self::normalize_record_meta( $record, $period['range'], $period['days'] ),
				];
		}

			return [
				'period'  => $period,
				'metrics' => $metrics,
			];
	}

		/**
		 * Normalize stored record meta ensuring period context is present.
		 *
		 * @param mixed  $record       Database record (object or array).
		 * @param string $period_range Canonical period range.
		 * @param int    $period_days  Number of days included in the period.
		 * @return array<string, mixed> Normalized metadata.
		 */
	private static function normalize_record_meta( $record, string $period_range, int $period_days ): array {
		if ( is_object( $record ) && property_exists( $record, 'meta' ) ) {
				$meta_source = $record->meta;
		} elseif ( is_array( $record ) && array_key_exists( 'meta', $record ) ) {
				$meta_source = $record['meta'];
		} else {
				$meta_source = [];
		}

		if ( is_string( $meta_source ) && '' !== $meta_source ) {
				$decoded = json_decode( $meta_source, true );
				$meta    = is_array( $decoded ) ? $decoded : [];
		} elseif ( is_array( $meta_source ) ) {
				$meta = $meta_source;
		} else {
				$meta = [];
		}

		if ( ! isset( $meta['period_range'] ) ) {
				$meta['period_range'] = $period_range;
		}

		if ( ! isset( $meta['period_days'] ) ) {
				$meta['period_days'] = $period_days;
		}

		if ( ! isset( $meta['collection_period'] ) ) {
				$meta['collection_period'] = $period_range;
		}

			return $meta;
	}

		/**
		 * Cast stored metric values back to numeric values when possible.
		 *
		 * @param mixed $value Raw value from the database.
		 * @return float|int|string Normalized value.
		 */
	private static function cast_metric_value( $value ) {
		if ( is_numeric( $value ) ) {
				// Casting with 0+ preserves numeric type without forcing float precision.
				return 0 + $value;
		}

			return $value;
	}

		/**
		 * Filter metadata to ensure valid associative array.
		 *
		 * @param array $meta Raw metadata.
		 * @return array<string, mixed> Filtered metadata.
		 */
	private static function filter_meta( array $meta ): array {
			$filtered = [];

		foreach ( $meta as $key => $value ) {
				$normalized_key = trim( (string) $key );
			if ( '' === $normalized_key ) {
				continue;
			}

				$filtered[ $normalized_key ] = $value;
		}

			return $filtered;
	}

		/**
		 * Resolve the concrete period boundaries for a shorthand period key.
		 *
		 * @param string $period_range Period shorthand provided by caller.
		 * @param array  $options      Optional overrides including `period_start` or `period_end`.
		 * @return array{range:string,start:string,end:string,days:int} Period metadata.
		 */
	private static function resolve_period( string $period_range, array $options = [] ): array {
			$normalized_key = self::normalize_period_key( $period_range );

		if ( ! array_key_exists( $normalized_key, self::PERIOD_DEFINITIONS ) ) {
				$normalized_key = self::DEFAULT_PERIOD;
		}

			$timezone   = self::get_timezone();
			$end_string = $options['period_end'] ?? null;

			$end_date = self::normalize_boundary( $end_string, $timezone, false );

			$start_override = $options['period_start'] ?? null;
		if ( 'custom' === $normalized_key && null === $start_override ) {
				$start_override = $end_date->format( 'Y-m-d' );
		}

		if ( null !== $start_override ) {
				$start_date = self::normalize_boundary( $start_override, $timezone, true );
		} elseif ( 'custom' === $normalized_key ) {
				$start_date = self::normalize_boundary( $end_date->format( 'Y-m-d' ), $timezone, true );
		} else {
				$definition = self::PERIOD_DEFINITIONS[ $normalized_key ];
				$days       = isset( $definition['days'] ) ? max( 1, (int) $definition['days'] ) : 1;

				$start_date = clone $end_date;
				$start_date->modify( sprintf( '-%d days', $days - 1 ) );
				$start_date->setTime( 0, 0, 0 );
		}

		if ( $start_date > $end_date ) {
				$start_date = clone $end_date;
				$start_date->setTime( 0, 0, 0 );
		}

			$start_string = $start_date->format( 'Y-m-d H:i:s' );
			$end_string   = $end_date->format( 'Y-m-d H:i:s' );

			return [
				'range' => $normalized_key,
				'start' => $start_string,
				'end'   => $end_string,
				'days'  => self::calculate_period_days( $start_string, $end_string ),
			];
	}

		/**
		 * Normalize shorthand period key to canonical internal representation.
		 *
		 * @param string $period_range Provided period key.
		 * @return string Canonical period key.
		 */
	private static function normalize_period_key( string $period_range ): string {
			$normalized = strtolower( trim( $period_range ) );
			$normalized = str_replace( [ ' ', '-' ], '_', $normalized );

			return self::PERIOD_ALIASES[ $normalized ] ?? $normalized;
	}

		/**
		 * Convert arbitrary date strings to DateTime instances aligned to day boundaries.
		 *
		 * @param string|null   $date_string Raw input string.
		 * @param \DateTimeZone $timezone    Target timezone.
		 * @param bool          $is_start    Whether the boundary represents the start of the period.
		 * @return \DateTime Normalized DateTime instance.
		 */
	private static function normalize_boundary( ?string $date_string, \DateTimeZone $timezone, bool $is_start ): \DateTime {
			$timestamp = null;

		if ( null !== $date_string && '' !== trim( (string) $date_string ) ) {
				$timestamp = strtotime( (string) $date_string );

			if ( false === $timestamp && preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $date_string, $matches ) ) {
				$timestamp = strtotime( $matches[0] );
			}
		}

		if ( ! is_int( $timestamp ) ) {
				$timestamp = time();
		}

			$date_time = new \DateTime( '@' . $timestamp );
			$date_time->setTimezone( $timezone );

		if ( $is_start ) {
				$date_time->setTime( 0, 0, 0 );
		} else {
				$date_time->setTime( 23, 59, 59 );
		}

			return $date_time;
	}

		/**
		 * Calculate the number of days encompassed by the period.
		 *
		 * @param string $start Start timestamp (Y-m-d H:i:s).
		 * @param string $end   End timestamp (Y-m-d H:i:s).
		 * @return int Number of calendar days within the period (inclusive).
		 */
	private static function calculate_period_days( string $start, string $end ): int {
			$start_ts = strtotime( $start );
			$end_ts   = strtotime( $end );

		if ( false === $start_ts || false === $end_ts || $end_ts < $start_ts ) {
				return 0;
		}

			return (int) floor( ( $end_ts - $start_ts ) / 86400 ) + 1;
	}

		/**
		 * Determine the timezone to use for period normalization.
		 *
		 * @return \DateTimeZone Active timezone instance.
		 */
	private static function get_timezone(): \DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
				$wp_timezone = wp_timezone();
			if ( $wp_timezone instanceof \DateTimeZone ) {
				return $wp_timezone;
			}
		}

		try {
				$timezone_string = date_default_timezone_get() ?: 'UTC';
				return new \DateTimeZone( $timezone_string );
		} catch ( \Exception $exception ) {
				return new \DateTimeZone( 'UTC' );
		}
	}
}

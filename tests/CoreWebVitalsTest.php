<?php
/**
 * Core Web Vitals Unit Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\CoreWebVitals;
use FP\DigitalMarketing\Models\CoreWebVitals as CoreWebVitalsModel;

/**
 * Test class for Core Web Vitals integration
 */
class CoreWebVitalsTest extends TestCase {

		/**
		 * Test that demo metrics are stored via MetricsCache::save without errors.
		 */
	public function test_fetch_metrics_saves_into_metrics_cache(): void {
			$core_web_vitals = new CoreWebVitals( 'https://example.com', '' );

			$client_id  = 55;
			$start_date = '2024-03-01';
			$end_date   = '2024-03-28';

			[ $wpdb_mock, $restore_wpdb ] = $this->replace_wpdb_with_spy();

		try {
				$metrics = $core_web_vitals->fetch_metrics( $client_id, $start_date, $end_date );
		} finally {
				$restore_wpdb();
		}

			$this->assertIsArray( $metrics );
			$this->assertGreaterThan( 0, $wpdb_mock->insert_calls );
			$this->assertCount( count( $metrics ), $wpdb_mock->records );

		foreach ( $wpdb_mock->records as $record ) {
				$this->assertEquals( 'wp_fp_metrics_cache', $record['table'] );
				$this->assertEquals( $client_id, (int) $record['data']['client_id'] );
				$this->assertEquals( CoreWebVitals::SOURCE_ID, $record['data']['source'] );
				$this->assertEquals( $start_date . ' 00:00:00', $record['data']['period_start'] );
				$this->assertEquals( $end_date . ' 23:59:59', $record['data']['period_end'] );

				$meta = json_decode( (string) $record['data']['meta'], true );
				$this->assertIsArray( $meta );
				$this->assertSame( 'https://example.com', $meta['origin_url'] );
				$this->assertEquals( 75, $meta['percentile'] );
				$this->assertEquals( '28_days', $meta['collection_period'] );
				$this->assertEquals( '28_days', $meta['period_range'] );
				$this->assertEquals( 28, $meta['period_days'] );
				$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_start'] );
				$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_end'] );
		}
	}

		/**
		 * Ensure datetime inputs are normalized to full-day MySQL timestamps.
		 */
	public function test_fetch_metrics_normalizes_datetime_boundaries(): void {
			$core_web_vitals = new CoreWebVitals( 'https://example.com', '' );

			$client_id  = 87;
			$start_date = '2024-03-01 12:34:56';
			$end_date   = '2024-03-28 05:06:07';

			[ $wpdb_mock, $restore_wpdb ] = $this->replace_wpdb_with_spy();

		try {
				$metrics = $core_web_vitals->fetch_metrics( $client_id, $start_date, $end_date );
		} finally {
				$restore_wpdb();
		}

			$this->assertIsArray( $metrics );
			$this->assertGreaterThan( 0, $wpdb_mock->insert_calls );

		foreach ( $wpdb_mock->records as $record ) {
				$this->assertEquals( '2024-03-01 00:00:00', $record['data']['period_start'] );
				$this->assertEquals( '2024-03-28 23:59:59', $record['data']['period_end'] );
				$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_start'] );
				$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['data']['period_end'] );
		}
	}

		/**
		 * Ensure the Core Web Vitals model normalizes aliases to canonical periods.
		 */
	public function test_model_fetch_metrics_maps_period_aliases(): void {
			$client_id = 99;
			$metrics   = [
				'lcp' => 2150,
				'inp' => 180,
				'cls' => 0.12,
			];

			[ $wpdb_mock, $restore_wpdb ] = $this->replace_wpdb_with_spy();

			try {
					CoreWebVitalsModel::store_metrics(
						$client_id,
						$metrics,
						'28_days',
						[
							'period_end' => '2024-03-28 11:00:00',
							'meta'       => [
								'origin_url' => 'https://alias-example.test',
								'percentile' => 75,
							],
						]
					);

					$result = CoreWebVitalsModel::fetch_metrics(
						$client_id,
						'last_28_days',
						[
							'period_end' => '2024-03-28 23:59:59',
						]
					);
			} finally {
					$restore_wpdb();
			}

			$this->assertArrayHasKey( 'period', $result );
			$this->assertSame( '28_days', $result['period']['range'] );
			$this->assertSame( '2024-03-01 00:00:00', $result['period']['start'] );
			$this->assertSame( '2024-03-28 23:59:59', $result['period']['end'] );
			$this->assertEquals( 28, $result['period']['days'] );

			$this->assertArrayHasKey( 'metrics', $result );
			foreach ( $metrics as $metric => $value ) {
					$this->assertArrayHasKey( $metric, $result['metrics'] );
					$this->assertEquals( 0 + $value, $result['metrics'][ $metric ]['value'] );
					$this->assertSame( '2024-03-01 00:00:00', $result['metrics'][ $metric ]['period_start'] );
					$this->assertSame( '2024-03-28 23:59:59', $result['metrics'][ $metric ]['period_end'] );
					$this->assertSame( '28_days', $result['metrics'][ $metric ]['meta']['period_range'] );
					$this->assertSame( '28_days', $result['metrics'][ $metric ]['meta']['collection_period'] );
					$this->assertEquals( 28, $result['metrics'][ $metric ]['meta']['period_days'] );
			}
	}

		/**
		 * Verify metrics retrieval works when caching is disabled.
		 */
	public function test_model_fetch_metrics_handles_disabled_cache(): void {
			global $wp_options;

			$client_id = 73;
			$metrics   = [ 'lcp' => 2050 ];

			$previous_settings                                 = $wp_options['fp_digital_marketing_cache_settings'] ?? null;
			$wp_options['fp_digital_marketing_cache_settings'] = [
				'enabled'          => false,
				'use_object_cache' => false,
				'use_transients'   => false,
			];

			[ $wpdb_mock, $restore_wpdb ] = $this->replace_wpdb_with_spy();

			try {
					CoreWebVitalsModel::store_metrics(
						$client_id,
						$metrics,
						'28_days',
						[
							'period_end' => '2024-04-30 09:00:00',
						]
					);

					$result = CoreWebVitalsModel::fetch_metrics(
						$client_id,
						'28_days',
						[
							'period_end' => '2024-04-30 23:59:59',
						]
					);
			} finally {
					$restore_wpdb();

				if ( null === $previous_settings ) {
						unset( $wp_options['fp_digital_marketing_cache_settings'] );
				} else {
						$wp_options['fp_digital_marketing_cache_settings'] = $previous_settings;
				}
			}

			$this->assertArrayHasKey( 'metrics', $result );
			$this->assertArrayHasKey( 'lcp', $result['metrics'] );
			$this->assertEquals( 2050, $result['metrics']['lcp']['value'] );
	}

		/**
		 * Replace global $wpdb with a spy capturing insert calls.
		 *
		 * @return array{0:WPDB_Mock,1:callable} Spy instance and restore callback.
		 */
	private function replace_wpdb_with_spy(): array {
			global $wpdb;

			$original_wpdb = $wpdb;

			$wpdb_mock = new class() extends WPDB_Mock {
					/**
					 * Stored insert calls for later assertions.
					 *
					 * @var array<int, array<string, mixed>>
					 */
					public $records = [];

					/**
					 * Number of insert operations executed.
					 *
					 * @var int
					 */
					public $insert_calls = 0;

					/**
					 * Capture insert invocations.
					 *
					 * @param string     $table  Table name.
					 * @param array      $data   Row data.
					 * @param array|null $format Format definitions.
					 * @return int Insert result.
					 */
				public function insert( $table, $data, $format = null ) { // phpcs:ignore WordPress.DB
						++$this->insert_calls;
						$this->records[] = [
							'table'  => $table,
							'data'   => $data,
							'format' => $format,
						];

						return parent::insert( $table, $data, $format );
				}

					/**
					 * Return stored rows that match the provided SQL query.
					 *
					 * @param string $query SQL query string.
					 * @return array<int, object> Result set.
					 */
				public function get_results( $query ) { // phpcs:ignore WordPress.DB
						$results = [];

					foreach ( $this->records as $record ) {
							$data = $record['data'];

						if ( $this->should_skip_record( $query, $data ) ) {
							continue;
						}

							$results[] = (object) [
								'client_id'    => (int) $data['client_id'],
								'source'       => $data['source'],
								'metric'       => $data['metric'],
								'period_start' => $data['period_start'],
								'period_end'   => $data['period_end'],
								'value'        => $data['value'],
								'meta'         => $data['meta'],
								'fetched_at'   => $data['fetched_at'] ?? null,
							];
					}

						return $results;
				}

					/**
					 * Determine whether the stored record should be filtered out.
					 *
					 * @param string $query SQL query string.
					 * @param array  $data  Stored row data.
					 * @return bool True if the record does not match the query.
					 */
				private function should_skip_record( string $query, array $data ): bool {
					if ( preg_match( "/client_id\s*=\s*'?([0-9]+)'?/", $query, $match ) ) {
						if ( (int) $data['client_id'] !== (int) $match[1] ) {
							return true;
						}
					}

					if ( $this->value_not_in_clause( $query, 'source', (string) $data['source'] ) ) {
							return true;
					}

					if ( $this->value_not_in_clause( $query, 'metric', (string) $data['metric'] ) ) {
							return true;
					}

					if ( preg_match( "/period_start\s*>=\s*'([^']+)'/", $query, $match ) ) {
						if ( strcmp( $data['period_start'], $match[1] ) < 0 ) {
								return true;
						}
					}

					if ( preg_match( "/period_end\s*<=\s*'([^']+)'/", $query, $match ) ) {
						if ( strcmp( $data['period_end'], $match[1] ) > 0 ) {
								return true;
						}
					}

						return false;
				}

					/**
					 * Evaluate if a column value is not included in a WHERE clause.
					 *
					 * @param string $query SQL query string.
					 * @param string $column Column name.
					 * @param string $value  Value to compare.
					 * @return bool True if the value is excluded by the clause.
					 */
				private function value_not_in_clause( string $query, string $column, string $value ): bool {
					if ( preg_match( sprintf( "/%s\s*=\s*'([^']+)'/", preg_quote( $column, '/' ) ), $query, $match ) ) {
							return $value !== $match[1];
					}

					if ( preg_match( sprintf( '/%s\s+IN\s*\(([^)]+)\)/', preg_quote( $column, '/' ) ), $query, $match ) ) {
							$raw_values = array_map( 'trim', explode( ',', $match[1] ) );
							$normalized = array_map(
								static function ( $item ) {
											return trim( $item, "'\" " );
								},
								$raw_values
							);

							return ! in_array( $value, $normalized, true );
					}

						return false;
				}
			};

			$wpdb = $wpdb_mock;

			$restore = static function () use ( $original_wpdb ): void {
					global $wpdb;
					$wpdb = $original_wpdb;
			};

			return [ $wpdb_mock, $restore ];
	}
}

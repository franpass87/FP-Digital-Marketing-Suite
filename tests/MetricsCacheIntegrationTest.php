<?php
/**
 * Integration test for MetricsCache functionality
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Models\MetricsCache;
use FP\DigitalMarketing\Database\MetricsCacheTable;

/**
 * Integration test demonstrating practical usage of MetricsCache
 */
class MetricsCacheIntegrationTest extends TestCase {

	/**
	 * Test saving and retrieving Google Analytics metrics
	 */
	public function test_google_analytics_metrics_workflow(): void {
		$client_id    = 123;
		$source       = 'google_analytics_4';
		$period_start = '2024-01-01 00:00:00';
		$period_end   = '2024-01-31 23:59:59';

		// Save different metrics for the same client and period
		$metrics_data = [
			[
				'metric' => 'sessions',
				'value'  => '1500',
				'meta'   => [
					'device' => 'desktop',
					'region' => 'Italy',
				],
			],
			[
				'metric' => 'pageviews',
				'value'  => '4500',
				'meta'   => [
					'device' => 'desktop',
					'region' => 'Italy',
				],
			],
			[
				'metric' => 'bounce_rate',
				'value'  => '0.65',
				'meta'   => [
					'device' => 'desktop',
					'region' => 'Italy',
				],
			],
		];

		$saved_ids = [];
		foreach ( $metrics_data as $metric_data ) {
			$id = MetricsCache::save(
				$client_id,
				$source,
				$metric_data['metric'],
				$period_start,
				$period_end,
				$metric_data['value'],
				$metric_data['meta']
			);
			$this->assertIsInt( $id );
			$saved_ids[] = $id;
		}

		// Retrieve all metrics for this client
		$retrieved_metrics = MetricsCache::get_metrics(
			[
				'client_id' => $client_id,
				'source'    => $source,
			]
		);

		$this->assertCount( 3, $retrieved_metrics );

		// Test filtering by specific metric
		$sessions_metrics = MetricsCache::get_metrics(
			[
				'client_id' => $client_id,
				'source'    => $source,
				'metric'    => 'sessions',
			]
		);

		$this->assertCount( 1, $sessions_metrics );
		$this->assertEquals( 'sessions', $sessions_metrics[0]->metric );
		$this->assertEquals( '1500', $sessions_metrics[0]->value );
	}

	/**
	 * Test working with multiple data sources
	 */
	public function test_multiple_data_sources(): void {
		$client_id    = 456;
		$period_start = '2024-02-01 00:00:00';
		$period_end   = '2024-02-29 23:59:59';

		// Save metrics from different sources
		$sources_data = [
			[
				'source' => 'google_analytics_4',
				'metric' => 'users',
				'value'  => '850',
			],
			[
				'source' => 'facebook_ads',
				'metric' => 'impressions',
				'value'  => '25000',
			],
			[
				'source' => 'google_ads',
				'metric' => 'clicks',
				'value'  => '420',
			],
		];

		foreach ( $sources_data as $source_data ) {
			$id = MetricsCache::save(
				$client_id,
				$source_data['source'],
				$source_data['metric'],
				$period_start,
				$period_end,
				$source_data['value']
			);
			$this->assertIsInt( $id );
		}

		// Get all metrics for this client
		$all_metrics = MetricsCache::get_metrics( [ 'client_id' => $client_id ] );
		$this->assertCount( 3, $all_metrics );

		// Get metrics from specific source
		$ga_metrics = MetricsCache::get_metrics(
			[
				'client_id' => $client_id,
				'source'    => 'google_analytics_4',
			]
		);
		$this->assertCount( 1, $ga_metrics );
		$this->assertEquals( 'users', $ga_metrics[0]->metric );
	}

	/**
	 * Test updating cached metrics
	 */
	public function test_update_cached_metrics(): void {
		$client_id    = 789;
		$source       = 'mailchimp';
		$metric       = 'open_rate';
		$period_start = '2024-03-01 00:00:00';
		$period_end   = '2024-03-31 23:59:59';

		// Save initial metric
		$id = MetricsCache::save(
			$client_id,
			$source,
			$metric,
			$period_start,
			$period_end,
			'0.25',
			[ 'campaign_type' => 'newsletter' ]
		);

		$this->assertIsInt( $id );

		// Update the metric value and metadata
		$update_result = MetricsCache::update(
			$id,
			[
				'value' => '0.28',
				'meta'  => [
					'campaign_type' => 'newsletter',
					'segment'       => 'premium_customers',
				],
			]
		);

		$this->assertTrue( $update_result );

		// Verify the update
		$updated_metric = MetricsCache::get( $id );
		$this->assertEquals( '0.28', $updated_metric->value );
		$this->assertEquals( 'premium_customers', $updated_metric->meta['segment'] );
	}

	/**
	 * Test bulk operations
	 */
	public function test_bulk_operations(): void {
		$client_id = 999;
		$source    = 'google_search_console';

		// Save multiple metrics
		$keywords_data = [
			[
				'metric' => 'impressions_keyword_1',
				'value'  => '1250',
			],
			[
				'metric' => 'impressions_keyword_2',
				'value'  => '980',
			],
			[
				'metric' => 'impressions_keyword_3',
				'value'  => '750',
			],
		];

		foreach ( $keywords_data as $keyword_data ) {
			MetricsCache::save(
				$client_id,
				$source,
				$keyword_data['metric'],
				'2024-04-01 00:00:00',
				'2024-04-30 23:59:59',
				$keyword_data['value']
			);
		}

		// Count metrics for this client
		$count = MetricsCache::count(
			[
				'client_id' => $client_id,
				'source'    => $source,
			]
		);
		$this->assertEquals( 3, $count );

		// Delete all metrics for this client and source
		$deleted_count = MetricsCache::delete_by_criteria(
			[
				'client_id' => $client_id,
				'source'    => $source,
			]
		);
		$this->assertEquals( 3, $deleted_count );

		// Verify deletion
		$remaining_count = MetricsCache::count(
			[
				'client_id' => $client_id,
				'source'    => $source,
			]
		);
		$this->assertEquals( 0, $remaining_count );
	}
}

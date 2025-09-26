<?php
/**
 * Integration tests for Google Ads metrics storage
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\GoogleAds;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Test class for Google Ads integration with metrics cache
 */
class GoogleAdsIntegrationTest extends TestCase {

	/**
	 * Clean up after each test
	 */
	protected function tearDown(): void {
		// Clean up any test data
		MetricsCache::delete_by_criteria(
			[
				'source' => GoogleAds::SOURCE_ID,
			]
		);
	}

	/**
	 * Test Google Ads metrics storage in cache (mock implementation)
	 */
	public function test_google_ads_metrics_cache_storage(): void {
		// Create a Google Ads instance
		$google_ads = new GoogleAds( '123-456-7890', 'dev_token_123' );

		// Manually store some test metrics to verify the structure
		$client_id  = 999;
		$start_date = '2024-01-01';
		$end_date   = '2024-01-31';

		$test_metrics = [
			'impressions' => '15000',
			'clicks'      => '750',
			'cost'        => '150.00',
			'conversions' => '25',
		];

		// Simulate storing metrics (since actual fetch would require OAuth)
		foreach ( $test_metrics as $metric_name => $value ) {
			$id = MetricsCache::save(
				$client_id,
				GoogleAds::SOURCE_ID,
				$metric_name,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$value,
				[
					'customer_id'  => '123-456-7890',
					'source_type'  => 'google_ads',
					'utm_source'   => 'google',
					'utm_medium'   => 'cpc',
					'utm_campaign' => 'summer_sale_2024',
					'test_data'    => true,
				]
			);

			$this->assertIsInt( $id );
			$this->assertGreaterThan( 0, $id );
		}

		// Verify metrics were stored
		$stored_metrics = MetricsCache::get_metrics(
			[
				'client_id' => $client_id,
				'source'    => GoogleAds::SOURCE_ID,
			]
		);

		$this->assertCount( 4, $stored_metrics );

		// Verify first metric structure
		$first_metric = $stored_metrics[0];
		$this->assertEquals( GoogleAds::SOURCE_ID, $first_metric->source );
		$this->assertEquals( $client_id, $first_metric->client_id );
		$this->assertContains( $first_metric->metric, [ 'impressions', 'clicks', 'cost', 'conversions' ] );
	}

	/**
	 * Test Google Ads source filtering
	 */
	public function test_google_ads_source_filtering(): void {
		$client_id = 888;

		// Store metrics from Google Ads
		MetricsCache::save(
			$client_id,
			GoogleAds::SOURCE_ID,
			'impressions',
			'2024-02-01 00:00:00',
			'2024-02-28 23:59:59',
			'10000',
			[ 'customer_id' => '123-456-7890' ]
		);

		// Store metrics from another source for comparison
		MetricsCache::save(
			$client_id,
			'google_analytics_4',
			'sessions',
			'2024-02-01 00:00:00',
			'2024-02-28 23:59:59',
			'3000',
			[ 'property_id' => '123456789' ]
		);

		// Retrieve only Google Ads metrics
		$google_ads_metrics = MetricsCache::get_metrics(
			[
				'client_id' => $client_id,
				'source'    => GoogleAds::SOURCE_ID,
			]
		);

		$this->assertCount( 1, $google_ads_metrics );
		$this->assertEquals( GoogleAds::SOURCE_ID, $google_ads_metrics[0]->source );
		$this->assertEquals( 'impressions', $google_ads_metrics[0]->metric );

		// Clean up
		MetricsCache::delete_by_criteria( [ 'client_id' => $client_id ] );
	}

	/**
	 * Test Google Ads constants and identifiers
	 */
	public function test_google_ads_source_identifier(): void {
		$this->assertEquals( 'google_ads', GoogleAds::SOURCE_ID );

		// Verify this matches the DataSources registry
		$data_sources = fp_dms_get_data_sources();
		$this->assertArrayHasKey( GoogleAds::SOURCE_ID, $data_sources );
		$this->assertEquals( 'available', $data_sources[ GoogleAds::SOURCE_ID ]['status'] );
	}

	/**
	 * Test UTM mapping metadata storage
	 */
	public function test_utm_mapping_metadata_storage(): void {
		$client_id        = 777;
		$campaign_metrics = [
			'impressions' => '5000',
			'clicks'      => '250',
			'cost'        => '50.00',
			'conversions' => '10',
		];

		// Store metrics with UTM mapping metadata
		foreach ( $campaign_metrics as $metric_name => $value ) {
			MetricsCache::save(
				$client_id,
				GoogleAds::SOURCE_ID,
				$metric_name,
				'2024-03-01 00:00:00',
				'2024-03-31 23:59:59',
				$value,
				[
					'customer_id'   => '123-456-7890',
					'campaign_id'   => '12345678',
					'campaign_name' => 'Summer Sale 2024',
					'utm_source'    => 'google',
					'utm_medium'    => 'cpc',
					'utm_campaign'  => 'summer_sale_2024',
				]
			);
		}

		// Retrieve and verify UTM metadata
		$metrics_with_utm = MetricsCache::get_metrics(
			[
				'client_id' => $client_id,
				'source'    => GoogleAds::SOURCE_ID,
			]
		);

		$this->assertCount( 4, $metrics_with_utm );

		foreach ( $metrics_with_utm as $metric ) {
			$metadata = json_decode( $metric->metadata, true );
			$this->assertEquals( 'google', $metadata['utm_source'] );
			$this->assertEquals( 'cpc', $metadata['utm_medium'] );
			$this->assertEquals( 'summer_sale_2024', $metadata['utm_campaign'] );
			$this->assertEquals( '12345678', $metadata['campaign_id'] );
		}

		// Clean up
		MetricsCache::delete_by_criteria( [ 'client_id' => $client_id ] );
	}

	/**
	 * Test currency normalization in stored metrics
	 */
	public function test_currency_normalization_storage(): void {
		$client_id = 666;

		// Store cost metrics with normalized currency
		$cost_metrics = [
			'150000000' => '150.00',  // $150 in micros normalized
			'50500000'  => '50.50',    // $50.50 in micros normalized
			'1000000'   => '1.00',      // $1 in micros normalized
		];

		foreach ( $cost_metrics as $original_micros => $expected_normalized ) {
			MetricsCache::save(
				$client_id,
				GoogleAds::SOURCE_ID,
				'cost',
				'2024-04-01 00:00:00',
				'2024-04-30 23:59:59',
				$expected_normalized,
				[
					'customer_id'     => '123-456-7890',
					'original_micros' => $original_micros,
				]
			);
		}

		// Retrieve and verify normalized values
		$cost_metrics_stored = MetricsCache::get_metrics(
			[
				'client_id' => $client_id,
				'source'    => GoogleAds::SOURCE_ID,
				'metric'    => 'cost',
			]
		);

		$this->assertCount( 3, $cost_metrics_stored );

		foreach ( $cost_metrics_stored as $cost_metric ) {
			// Verify the value is in normalized format (not micros)
			$value = (float) $cost_metric->value;
			$this->assertLessThan( 1000, $value ); // Should be normal currency, not micros
			$this->assertContains( $cost_metric->value, [ '150.00', '50.50', '1.00' ] );
		}

		// Clean up
		MetricsCache::delete_by_criteria( [ 'client_id' => $client_id ] );
	}
}

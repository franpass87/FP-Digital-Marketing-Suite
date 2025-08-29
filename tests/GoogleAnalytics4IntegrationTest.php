<?php
/**
 * Integration tests for GA4 metrics storage
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Test class for GA4 integration with metrics cache
 */
class GoogleAnalytics4IntegrationTest extends TestCase {

	/**
	 * Clean up after each test
	 */
	protected function tearDown(): void {
		// Clean up any test data
		MetricsCache::delete_by_criteria([
			'source' => GoogleAnalytics4::SOURCE_ID,
		]);
	}

	/**
	 * Test GA4 metrics storage in cache (mock implementation)
	 */
	public function test_ga4_metrics_cache_storage(): void {
		// Create a GA4 instance
		$ga4 = new GoogleAnalytics4( '123456789' );

		// Manually store some test metrics to verify the structure
		$client_id = 999;
		$start_date = '2024-01-01';
		$end_date = '2024-01-31';
		
		$test_metrics = [
			'sessions' => '2500',
			'users' => '2000',
			'conversions' => '50',
			'revenue' => '5000',
		];

		// Simulate storing metrics (since actual fetch would require OAuth)
		foreach ( $test_metrics as $metric_name => $value ) {
			$id = MetricsCache::save(
				$client_id,
				GoogleAnalytics4::SOURCE_ID,
				$metric_name,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$value,
				[
					'property_id' => '123456789',
					'test_data' => true,
				]
			);
			
			$this->assertIsInt( $id );
			$this->assertGreaterThan( 0, $id );
		}

		// Verify metrics were stored
		$stored_metrics = MetricsCache::get_metrics([
			'client_id' => $client_id,
			'source' => GoogleAnalytics4::SOURCE_ID,
		]);

		$this->assertCount( 4, $stored_metrics );

		// Check that all expected metrics are present
		$metric_names = array_column( $stored_metrics, 'metric' );
		$this->assertContains( 'sessions', $metric_names );
		$this->assertContains( 'users', $metric_names );
		$this->assertContains( 'conversions', $metric_names );
		$this->assertContains( 'revenue', $metric_names );

		// Verify meta data structure
		foreach ( $stored_metrics as $metric ) {
			$this->assertEquals( GoogleAnalytics4::SOURCE_ID, $metric->source );
			$this->assertEquals( $client_id, $metric->client_id );
			$this->assertIsArray( $metric->meta );
			$this->assertEquals( '123456789', $metric->meta['property_id'] );
			$this->assertTrue( $metric->meta['test_data'] );
		}
	}

	/**
	 * Test GA4 metrics retrieval by source
	 */
	public function test_ga4_metrics_retrieval_by_source(): void {
		$client_id = 888;
		
		// Store some GA4 metrics
		MetricsCache::save(
			$client_id,
			GoogleAnalytics4::SOURCE_ID,
			'sessions',
			'2024-02-01 00:00:00',
			'2024-02-28 23:59:59',
			'3000',
			[ 'property_id' => '123456789' ]
		);

		// Store metrics from another source for comparison
		MetricsCache::save(
			$client_id,
			'facebook_ads',
			'impressions',
			'2024-02-01 00:00:00',
			'2024-02-28 23:59:59',
			'50000',
			[ 'campaign_id' => 'test_campaign' ]
		);

		// Retrieve only GA4 metrics
		$ga4_metrics = MetricsCache::get_metrics([
			'client_id' => $client_id,
			'source' => GoogleAnalytics4::SOURCE_ID,
		]);

		$this->assertCount( 1, $ga4_metrics );
		$this->assertEquals( GoogleAnalytics4::SOURCE_ID, $ga4_metrics[0]->source );
		$this->assertEquals( 'sessions', $ga4_metrics[0]->metric );

		// Clean up
		MetricsCache::delete_by_criteria([ 'client_id' => $client_id ]);
	}

	/**
	 * Test GA4 constants and identifiers
	 */
	public function test_ga4_source_identifier(): void {
		$this->assertEquals( 'google_analytics_4', GoogleAnalytics4::SOURCE_ID );
		
		// Verify this matches the DataSources registry
		$data_sources = fp_dms_get_data_sources();
		$this->assertArrayHasKey( GoogleAnalytics4::SOURCE_ID, $data_sources );
		$this->assertEquals( 'available', $data_sources[GoogleAnalytics4::SOURCE_ID]['status'] );
	}
}
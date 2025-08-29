<?php
/**
 * Tests for Metrics Schema class
 *
 * @package FP_Digital_Marketing_Suite
 */

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Helpers\MetricsSchema;

/**
 * Test class for MetricsSchema
 */
class MetricsSchemaTest extends TestCase {

	/**
	 * Test getting KPI definitions
	 */
	public function test_get_kpi_definitions(): void {
		$definitions = MetricsSchema::get_kpi_definitions();
		
		$this->assertIsArray( $definitions );
		$this->assertNotEmpty( $definitions );
		
		// Test specific KPI definition structure
		$this->assertArrayHasKey( MetricsSchema::KPI_SESSIONS, $definitions );
		
		$sessions_def = $definitions[ MetricsSchema::KPI_SESSIONS ];
		$this->assertArrayHasKey( 'name', $sessions_def );
		$this->assertArrayHasKey( 'description', $sessions_def );
		$this->assertArrayHasKey( 'category', $sessions_def );
		$this->assertArrayHasKey( 'format', $sessions_def );
		$this->assertArrayHasKey( 'aggregation', $sessions_def );
		
		$this->assertEquals( 'traffic', $sessions_def['category'] );
		$this->assertEquals( 'number', $sessions_def['format'] );
		$this->assertEquals( 'sum', $sessions_def['aggregation'] );
	}

	/**
	 * Test getting source mappings
	 */
	public function test_get_source_mappings(): void {
		$mappings = MetricsSchema::get_source_mappings();
		
		$this->assertIsArray( $mappings );
		$this->assertNotEmpty( $mappings );
		
		// Test GA4 mappings
		$this->assertArrayHasKey( 'google_analytics_4', $mappings );
		$ga4_mappings = $mappings['google_analytics_4'];
		
		$this->assertArrayHasKey( 'sessions', $ga4_mappings );
		$this->assertEquals( MetricsSchema::KPI_SESSIONS, $ga4_mappings['sessions'] );
		
		$this->assertArrayHasKey( 'users', $ga4_mappings );
		$this->assertEquals( MetricsSchema::KPI_USERS, $ga4_mappings['users'] );
		
		// Test Search Console mappings
		$this->assertArrayHasKey( 'google_search_console', $mappings );
		$gsc_mappings = $mappings['google_search_console'];
		
		$this->assertArrayHasKey( 'clicks', $gsc_mappings );
		$this->assertEquals( MetricsSchema::KPI_ORGANIC_CLICKS, $gsc_mappings['clicks'] );
	}

	/**
	 * Test metric name normalization
	 */
	public function test_normalize_metric_name(): void {
		// Test GA4 metric normalization
		$normalized = MetricsSchema::normalize_metric_name( 'google_analytics_4', 'sessions' );
		$this->assertEquals( MetricsSchema::KPI_SESSIONS, $normalized );
		
		$normalized = MetricsSchema::normalize_metric_name( 'google_analytics_4', 'screenPageViews' );
		$this->assertEquals( MetricsSchema::KPI_PAGEVIEWS, $normalized );
		
		// Test Search Console metric normalization
		$normalized = MetricsSchema::normalize_metric_name( 'google_search_console', 'clicks' );
		$this->assertEquals( MetricsSchema::KPI_ORGANIC_CLICKS, $normalized );
		
		// Test unknown metric (should return original)
		$normalized = MetricsSchema::normalize_metric_name( 'unknown_source', 'unknown_metric' );
		$this->assertEquals( 'unknown_metric', $normalized );
		
		$normalized = MetricsSchema::normalize_metric_name( 'google_analytics_4', 'unknown_metric' );
		$this->assertEquals( 'unknown_metric', $normalized );
	}

	/**
	 * Test getting KPIs by category
	 */
	public function test_get_kpis_by_category(): void {
		$traffic_kpis = MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_TRAFFIC );
		
		$this->assertIsArray( $traffic_kpis );
		$this->assertContains( MetricsSchema::KPI_SESSIONS, $traffic_kpis );
		$this->assertContains( MetricsSchema::KPI_USERS, $traffic_kpis );
		$this->assertContains( MetricsSchema::KPI_PAGEVIEWS, $traffic_kpis );
		
		$advertising_kpis = MetricsSchema::get_kpis_by_category( MetricsSchema::CATEGORY_ADVERTISING );
		
		$this->assertIsArray( $advertising_kpis );
		$this->assertContains( MetricsSchema::KPI_IMPRESSIONS, $advertising_kpis );
		$this->assertContains( MetricsSchema::KPI_CLICKS, $advertising_kpis );
		$this->assertContains( MetricsSchema::KPI_CTR, $advertising_kpis );
		
		// Test empty category
		$empty_kpis = MetricsSchema::get_kpis_by_category( 'nonexistent_category' );
		$this->assertEmpty( $empty_kpis );
	}

	/**
	 * Test getting categories
	 */
	public function test_get_categories(): void {
		$categories = MetricsSchema::get_categories();
		
		$this->assertIsArray( $categories );
		$this->assertNotEmpty( $categories );
		
		$this->assertArrayHasKey( MetricsSchema::CATEGORY_TRAFFIC, $categories );
		$this->assertArrayHasKey( MetricsSchema::CATEGORY_ENGAGEMENT, $categories );
		$this->assertArrayHasKey( MetricsSchema::CATEGORY_CONVERSIONS, $categories );
		$this->assertArrayHasKey( MetricsSchema::CATEGORY_ADVERTISING, $categories );
		$this->assertArrayHasKey( MetricsSchema::CATEGORY_SEARCH, $categories );
		$this->assertArrayHasKey( MetricsSchema::CATEGORY_EMAIL, $categories );
		
		// Test category structure
		$traffic_category = $categories[ MetricsSchema::CATEGORY_TRAFFIC ];
		$this->assertArrayHasKey( 'name', $traffic_category );
		$this->assertArrayHasKey( 'description', $traffic_category );
	}

	/**
	 * Test KPI validation
	 */
	public function test_is_standard_kpi(): void {
		$this->assertTrue( MetricsSchema::is_standard_kpi( MetricsSchema::KPI_SESSIONS ) );
		$this->assertTrue( MetricsSchema::is_standard_kpi( MetricsSchema::KPI_USERS ) );
		$this->assertTrue( MetricsSchema::is_standard_kpi( MetricsSchema::KPI_REVENUE ) );
		
		$this->assertFalse( MetricsSchema::is_standard_kpi( 'unknown_kpi' ) );
		$this->assertFalse( MetricsSchema::is_standard_kpi( 'custom_metric' ) );
	}

	/**
	 * Test getting aggregation methods
	 */
	public function test_get_aggregation_method(): void {
		$this->assertEquals( 'sum', MetricsSchema::get_aggregation_method( MetricsSchema::KPI_SESSIONS ) );
		$this->assertEquals( 'sum', MetricsSchema::get_aggregation_method( MetricsSchema::KPI_REVENUE ) );
		$this->assertEquals( 'average', MetricsSchema::get_aggregation_method( MetricsSchema::KPI_BOUNCE_RATE ) );
		$this->assertEquals( 'average', MetricsSchema::get_aggregation_method( MetricsSchema::KPI_CTR ) );
		
		// Test default aggregation for unknown KPI
		$this->assertEquals( 'sum', MetricsSchema::get_aggregation_method( 'unknown_kpi' ) );
	}

	/**
	 * Test getting format types
	 */
	public function test_get_format_type(): void {
		$this->assertEquals( 'number', MetricsSchema::get_format_type( MetricsSchema::KPI_SESSIONS ) );
		$this->assertEquals( 'currency', MetricsSchema::get_format_type( MetricsSchema::KPI_REVENUE ) );
		$this->assertEquals( 'percentage', MetricsSchema::get_format_type( MetricsSchema::KPI_BOUNCE_RATE ) );
		$this->assertEquals( 'percentage', MetricsSchema::get_format_type( MetricsSchema::KPI_CTR ) );
		
		// Test default format for unknown KPI
		$this->assertEquals( 'number', MetricsSchema::get_format_type( 'unknown_kpi' ) );
	}

	/**
	 * Test KPI constants are defined
	 */
	public function test_kpi_constants(): void {
		$this->assertEquals( 'sessions', MetricsSchema::KPI_SESSIONS );
		$this->assertEquals( 'users', MetricsSchema::KPI_USERS );
		$this->assertEquals( 'pageviews', MetricsSchema::KPI_PAGEVIEWS );
		$this->assertEquals( 'bounce_rate', MetricsSchema::KPI_BOUNCE_RATE );
		$this->assertEquals( 'conversions', MetricsSchema::KPI_CONVERSIONS );
		$this->assertEquals( 'revenue', MetricsSchema::KPI_REVENUE );
		$this->assertEquals( 'impressions', MetricsSchema::KPI_IMPRESSIONS );
		$this->assertEquals( 'clicks', MetricsSchema::KPI_CLICKS );
		$this->assertEquals( 'ctr', MetricsSchema::KPI_CTR );
		$this->assertEquals( 'cpc', MetricsSchema::KPI_CPC );
		$this->assertEquals( 'cost', MetricsSchema::KPI_COST );
		$this->assertEquals( 'organic_clicks', MetricsSchema::KPI_ORGANIC_CLICKS );
		$this->assertEquals( 'organic_impressions', MetricsSchema::KPI_ORGANIC_IMPRESSIONS );
		$this->assertEquals( 'email_opens', MetricsSchema::KPI_EMAIL_OPENS );
		$this->assertEquals( 'email_clicks', MetricsSchema::KPI_EMAIL_CLICKS );
	}

	/**
	 * Test category constants are defined
	 */
	public function test_category_constants(): void {
		$this->assertEquals( 'traffic', MetricsSchema::CATEGORY_TRAFFIC );
		$this->assertEquals( 'engagement', MetricsSchema::CATEGORY_ENGAGEMENT );
		$this->assertEquals( 'conversions', MetricsSchema::CATEGORY_CONVERSIONS );
		$this->assertEquals( 'advertising', MetricsSchema::CATEGORY_ADVERTISING );
		$this->assertEquals( 'search', MetricsSchema::CATEGORY_SEARCH );
		$this->assertEquals( 'email', MetricsSchema::CATEGORY_EMAIL );
	}
}
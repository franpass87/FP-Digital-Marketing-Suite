<?php
/**
 * UTM Campaign Model Tests
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\Models\UTMCampaign;

/**
 * Test class for UTM Campaign Model functionality
 */
class UTMCampaignTest extends TestCase {

	/**
	 * Set up before each test
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress globals and functions
				global $wpdb;
				$wpdb         = $this->getMockBuilder( stdClass::class )
						->addMethods( [ 'prepare', 'update', 'insert', 'get_var' ] )
						->getMock();
				$wpdb->prefix = 'wp_';
				$wpdb->method( 'prepare' )->willReturnCallback(
					static function ( $query ) {
								return $query;
					}
				);

		// Mock WordPress functions
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $str ) {
				return trim( strip_tags( $str ) );
			}
		}

		if ( ! function_exists( 'esc_url_raw' ) ) {
			function esc_url_raw( $url ) {
				return filter_var( $url, FILTER_SANITIZE_URL );
			}
		}

		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return 1;
			}
		}
	}

	/**
	 * Test campaign creation with valid data
	 */
	public function test_create_campaign_valid_data(): void {
		$data = [
			'campaign_name' => 'Test Campaign',
			'utm_source'    => 'google',
			'utm_medium'    => 'cpc',
			'utm_campaign'  => 'test_campaign',
			'utm_term'      => 'keyword',
			'utm_content'   => 'ad1',
			'base_url'      => 'https://example.com',
			'status'        => 'active',
		];

		$campaign = new UTMCampaign( $data );

		$this->assertEquals( 'Test Campaign', $campaign->get_campaign_name() );
		$this->assertEquals( 'active', $campaign->get_status() );
		$this->assertEquals( 0, $campaign->get_clicks() );
		$this->assertEquals( 0, $campaign->get_conversions() );
		$this->assertEquals( 0.0, $campaign->get_revenue() );
	}

	/**
	 * Test campaign creation with empty data
	 */
	public function test_create_campaign_empty_data(): void {
		$campaign = new UTMCampaign();

		$this->assertEquals( '', $campaign->get_campaign_name() );
		$this->assertEquals( 'active', $campaign->get_status() );
		$this->assertNull( $campaign->get_id() );
	}

	/**
	 * Test campaign data population
	 */
	public function test_populate_campaign_data(): void {
		$campaign = new UTMCampaign();

		$data = [
			'id'            => 123,
			'campaign_name' => 'Updated Campaign',
			'utm_source'    => 'facebook',
			'utm_medium'    => 'social',
			'utm_campaign'  => 'social_campaign',
			'base_url'      => 'https://example.com/page',
			'clicks'        => 100,
			'conversions'   => 5,
			'revenue'       => 250.50,
			'status'        => 'paused',
		];

		$campaign->populate( $data );

		$this->assertEquals( 123, $campaign->get_id() );
		$this->assertEquals( 'Updated Campaign', $campaign->get_campaign_name() );
		$this->assertEquals( 100, $campaign->get_clicks() );
		$this->assertEquals( 5, $campaign->get_conversions() );
		$this->assertEquals( 250.50, $campaign->get_revenue() );
		$this->assertEquals( 'paused', $campaign->get_status() );
	}

	/**
	 * Test campaign data conversion to array
	 */
	public function test_to_array(): void {
		$data = [
			'campaign_name' => 'Test Campaign',
			'utm_source'    => 'google',
			'utm_medium'    => 'cpc',
			'utm_campaign'  => 'test',
			'base_url'      => 'https://example.com',
		];

		$campaign = new UTMCampaign( $data );
		$array    = $campaign->to_array();

		$this->assertIsArray( $array );
		$this->assertEquals( 'Test Campaign', $array['campaign_name'] );
		$this->assertEquals( 'google', $array['utm_source'] );
		$this->assertEquals( 'cpc', $array['utm_medium'] );
		$this->assertEquals( 'test', $array['utm_campaign'] );
		$this->assertEquals( 'https://example.com', $array['base_url'] );
		$this->assertArrayHasKey( 'final_url', $array );
	}

	/**
	 * Test campaign URL generation
	 */
	public function test_final_url_generation(): void {
		$data = [
			'utm_source'   => 'newsletter',
			'utm_medium'   => 'email',
			'utm_campaign' => 'monthly',
			'base_url'     => 'https://example.com',
		];

		$campaign  = new UTMCampaign( $data );
		$final_url = $campaign->get_final_url();

		$this->assertStringContainsString( 'utm_source=newsletter', $final_url );
		$this->assertStringContainsString( 'utm_medium=email', $final_url );
		$this->assertStringContainsString( 'utm_campaign=monthly', $final_url );
		$this->assertStringStartsWith( 'https://example.com', $final_url );
	}

	/**
	 * Test campaign with optional parameters
	 */
	public function test_campaign_with_optional_params(): void {
		$data = [
			'utm_source'   => 'google',
			'utm_medium'   => 'cpc',
			'utm_campaign' => 'test',
			'utm_term'     => 'keyword',
			'utm_content'  => 'ad_variant',
			'base_url'     => 'https://example.com',
		];

		$campaign  = new UTMCampaign( $data );
		$final_url = $campaign->get_final_url();

		$this->assertStringContainsString( 'utm_term=keyword', $final_url );
		$this->assertStringContainsString( 'utm_content=ad_variant', $final_url );
	}

	/**
	 * Test campaign without optional parameters
	 */
	public function test_campaign_without_optional_params(): void {
		$data = [
			'utm_source'   => 'google',
			'utm_medium'   => 'cpc',
			'utm_campaign' => 'test',
			'base_url'     => 'https://example.com',
		];

		$campaign  = new UTMCampaign( $data );
		$final_url = $campaign->get_final_url();

		$this->assertStringNotContainsString( 'utm_term=', $final_url );
		$this->assertStringNotContainsString( 'utm_content=', $final_url );
	}

	/**
	 * Test performance metrics update
	 */
	public function test_update_performance(): void {
		global $wpdb;

		// Mock successful update
		$wpdb->method( 'update' )->willReturn( 1 );
		$wpdb->method( 'prepare' )->willReturn( 'query' );
		$wpdb->method( 'get_var' )->willReturn( null ); // No duplicates

		$data = [
			'id'            => 123,
			'campaign_name' => 'Test Campaign',
			'utm_source'    => 'google',
			'utm_medium'    => 'cpc',
			'utm_campaign'  => 'test',
			'base_url'      => 'https://example.com',
			'clicks'        => 10,
			'conversions'   => 1,
			'revenue'       => 50.0,
		];

		$campaign = new UTMCampaign( $data );

		// Update performance
		$result = $campaign->update_performance( 5, 1, 25.0 );

		$this->assertEquals( 15, $campaign->get_clicks() );
		$this->assertEquals( 2, $campaign->get_conversions() );
		$this->assertEquals( 75.0, $campaign->get_revenue() );
	}

	/**
	 * Test short URL functionality
	 */
	public function test_short_url_functionality(): void {
		$campaign = new UTMCampaign();

		$this->assertNull( $campaign->get_short_url() );

		$campaign->set_short_url( 'https://short.ly/abc123' );

		$this->assertEquals( 'https://short.ly/abc123', $campaign->get_short_url() );
	}

	/**
	 * Test campaign data sanitization
	 */
	public function test_data_sanitization(): void {
		$data = [
			'campaign_name' => '<script>alert("xss")</script>Test Campaign',
			'utm_source'    => 'google<script>',
			'utm_medium'    => 'cpc</script>',
			'utm_campaign'  => 'test"campaign',
			'base_url'      => 'javascript:alert("xss")',
		];

		$campaign = new UTMCampaign( $data );
		$array    = $campaign->to_array();

		// Data should be sanitized
		$this->assertStringNotContainsString( '<script>', $array['campaign_name'] );
		$this->assertStringNotContainsString( '<script>', $array['utm_source'] );
		$this->assertStringNotContainsString( '</script>', $array['utm_medium'] );
		$this->assertEquals( 'Test Campaign', $array['campaign_name'] );
	}

	/**
	 * Test campaign with preset
	 */
	public function test_campaign_with_preset(): void {
		$data = [
			'campaign_name' => 'Email Campaign',
			'utm_source'    => 'newsletter',
			'utm_medium'    => 'email',
			'utm_campaign'  => 'monthly',
			'base_url'      => 'https://example.com',
			'preset_used'   => 'email_newsletter',
		];

		$campaign = new UTMCampaign( $data );
		$array    = $campaign->to_array();

		$this->assertEquals( 'email_newsletter', $array['preset_used'] );
	}

	/**
	 * Test campaign status values
	 */
	public function test_campaign_status_values(): void {
		$statuses = [ 'active', 'paused', 'completed' ];

		foreach ( $statuses as $status ) {
			$data = [
				'campaign_name' => 'Test Campaign',
				'utm_source'    => 'test',
				'utm_medium'    => 'test',
				'utm_campaign'  => 'test',
				'base_url'      => 'https://example.com',
				'status'        => $status,
			];

			$campaign = new UTMCampaign( $data );
			$this->assertEquals( $status, $campaign->get_status() );
		}
	}

	/**
	 * Test invalid URL handling
	 */
	public function test_invalid_url_handling(): void {
		$data = [
			'utm_source'   => 'test',
			'utm_medium'   => 'test',
			'utm_campaign' => 'test',
			'base_url'     => 'not-a-valid-url',
		];

		$campaign = new UTMCampaign( $data );

		// Should handle invalid URLs gracefully
		$this->assertIsString( $campaign->get_final_url() );
	}

	/**
	 * Test campaign timestamps
	 */
	public function test_campaign_timestamps(): void {
		$now  = date( 'Y-m-d H:i:s' );
		$data = [
			'campaign_name' => 'Test Campaign',
			'utm_source'    => 'test',
			'utm_medium'    => 'test',
			'utm_campaign'  => 'test',
			'base_url'      => 'https://example.com',
			'created_at'    => $now,
			'updated_at'    => $now,
		];

		$campaign = new UTMCampaign( $data );
		$array    = $campaign->to_array();

		$this->assertEquals( $now, $array['created_at'] );
		$this->assertEquals( $now, $array['updated_at'] );
	}
}

<?php
/**
 * Tests for GoogleAds integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\GoogleAds;
use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Test class for GoogleAds integration
 */
class GoogleAdsTest extends TestCase {

	/**
	 * Test GoogleAds class instantiation
	 */
	public function test_google_ads_instantiation(): void {
		$google_ads = new GoogleAds( '123-456-7890', 'dev_token_123' );
		$this->assertInstanceOf( GoogleAds::class, $google_ads );
		$this->assertEquals( '123-456-7890', $google_ads->get_customer_id() );
		$this->assertEquals( 'dev_token_123', $google_ads->get_developer_token() );
	}

	/**
	 * Test customer ID setter and getter
	 */
	public function test_customer_id_management(): void {
		$google_ads = new GoogleAds();
		$this->assertEquals( '', $google_ads->get_customer_id() );

		$google_ads->set_customer_id( '987-654-3210' );
		$this->assertEquals( '987-654-3210', $google_ads->get_customer_id() );
	}

	/**
	 * Test developer token setter and getter
	 */
	public function test_developer_token_management(): void {
		$google_ads = new GoogleAds();
		$this->assertEquals( '', $google_ads->get_developer_token() );

		$google_ads->set_developer_token( 'new_dev_token' );
		$this->assertEquals( 'new_dev_token', $google_ads->get_developer_token() );
	}

	/**
	 * Test source ID constant
	 */
	public function test_source_id_constant(): void {
		$this->assertEquals( 'google_ads', GoogleAds::SOURCE_ID );
	}

	/**
	 * Test fetch metrics returns expected structure
	 */
	public function test_fetch_metrics_structure(): void {
		$google_ads = new GoogleAds( '123-456-7890', 'dev_token_123' );

		// Since this is a mock implementation, we expect it to return false
		// when not properly connected (which it won't be in tests)
		$result = $google_ads->fetch_metrics( 1, '2024-01-01', '2024-01-31' );
		$this->assertFalse( $result );
	}

	/**
	 * Test connection status when not configured
	 */
	public function test_connection_status_not_configured(): void {
		$google_ads = new GoogleAds();
		$this->assertFalse( $google_ads->is_connected() );
	}

	/**
	 * Test OAuth authorization URL generation
	 */
	public function test_oauth_authorization_url(): void {
		$google_ads = new GoogleAds( '123-456-7890', 'dev_token_123' );
		$auth_url   = $google_ads->get_authorization_url();

		// Should return empty string when not configured
		$this->assertEquals( '', $auth_url );
	}

	/**
	 * Test OAuth callback handling
	 */
	public function test_oauth_callback_handling(): void {
		$google_ads = new GoogleAds( '123-456-7890', 'dev_token_123' );

		// Should return false with invalid/empty code
		$result = $google_ads->handle_oauth_callback( '' );
		$this->assertFalse( $result );
	}

	/**
	 * Test campaigns with UTM mappings when not connected
	 */
	public function test_campaigns_utm_not_connected(): void {
		$google_ads = new GoogleAds();
		$campaigns  = $google_ads->get_campaigns_with_utm( 1, '2024-01-01', '2024-01-31' );
		$this->assertIsArray( $campaigns );
		$this->assertEmpty( $campaigns );
	}

	/**
	 * Test UTM campaign sanitization
	 */
	public function test_utm_campaign_sanitization(): void {
		// Use reflection to test private method
		$google_ads = new GoogleAds();
		$reflection = new \ReflectionClass( $google_ads );
		$method     = $reflection->getMethod( 'sanitize_utm_campaign' );
		$method->setAccessible( true );

		$test_cases = [
			'Summer Sale 2024!'   => 'summer_sale_2024',
			'Brand-Awareness Q4'  => 'brand-awareness_q4',
			'  Special   Offer  ' => 'special_offer',
			'Email#Campaign@123'  => 'email_campaign_123',
		];

		foreach ( $test_cases as $input => $expected ) {
			$result = $method->invoke( $google_ads, $input );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * Test currency normalization
	 */
	public function test_currency_normalization(): void {
		// Use reflection to test private method
		$google_ads = new GoogleAds();
		$reflection = new \ReflectionClass( $google_ads );
		$method     = $reflection->getMethod( 'normalize_currency' );
		$method->setAccessible( true );

		$test_cases = [
			'1000000'   => '1.00',        // $1
			'150000000' => '150.00',    // $150
			'50500000'  => '50.50',      // $50.50
			'0'         => '0.00',              // $0
		];

		foreach ( $test_cases as $input => $expected ) {
			$result = $method->invoke( $google_ads, $input );
			$this->assertEquals( $expected, $result );
		}
	}
}

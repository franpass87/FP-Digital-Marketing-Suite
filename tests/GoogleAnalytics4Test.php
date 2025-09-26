<?php
/**
 * Tests for GoogleAnalytics4 integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\GoogleAnalytics4;
use FP\DigitalMarketing\DataSources\GoogleOAuth;
use FP\DigitalMarketing\Models\MetricsCache;

/**
 * Test class for GoogleAnalytics4 integration
 */
class GoogleAnalytics4Test extends TestCase {

	/**
	 * Test GoogleAnalytics4 class instantiation
	 */
	public function test_ga4_instantiation(): void {
		$ga4 = new GoogleAnalytics4( '123456789' );
		$this->assertInstanceOf( GoogleAnalytics4::class, $ga4 );
		$this->assertEquals( '123456789', $ga4->get_property_id() );
	}

	/**
	 * Test property ID setter and getter
	 */
	public function test_property_id_management(): void {
		$ga4 = new GoogleAnalytics4();
		$this->assertEquals( '', $ga4->get_property_id() );

		$ga4->set_property_id( '987654321' );
		$this->assertEquals( '987654321', $ga4->get_property_id() );
	}

	/**
	 * Test source ID constant
	 */
	public function test_source_id_constant(): void {
		$this->assertEquals( 'google_analytics_4', GoogleAnalytics4::SOURCE_ID );
	}

	/**
	 * Test fetch metrics returns expected structure
	 */
	public function test_fetch_metrics_structure(): void {
		$ga4 = new GoogleAnalytics4( '123456789' );

		// Since this is a mock implementation, we expect it to return false
		// when not properly connected (which it won't be in tests)
		$result = $ga4->fetch_metrics( 1, '2024-01-01', '2024-01-31' );
		$this->assertFalse( $result );
	}

	/**
	 * Test connection status when not configured
	 */
	public function test_connection_status_not_configured(): void {
		$ga4 = new GoogleAnalytics4();
		$this->assertFalse( $ga4->is_connected() );
	}

	/**
	 * Test OAuth authorization URL generation
	 */
	public function test_oauth_authorization_url(): void {
		$ga4      = new GoogleAnalytics4( '123456789' );
		$auth_url = $ga4->get_authorization_url();

		// Should return empty string when not configured
		$this->assertEquals( '', $auth_url );
	}

	/**
	 * Test OAuth callback handling
	 */
	public function test_oauth_callback_handling(): void {
		$ga4 = new GoogleAnalytics4( '123456789' );

		// Should return false with invalid/empty code
		$result = $ga4->handle_oauth_callback( '' );
		$this->assertFalse( $result );
	}
}

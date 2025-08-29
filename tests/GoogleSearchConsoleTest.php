<?php
/**
 * Tests for GoogleSearchConsole class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\GoogleSearchConsole;

/**
 * Test class for GoogleSearchConsole
 */
class GoogleSearchConsoleTest extends TestCase {

	/**
	 * Test GoogleSearchConsole instantiation
	 */
	public function test_instantiation(): void {
		$gsc = new GoogleSearchConsole();
		$this->assertInstanceOf( GoogleSearchConsole::class, $gsc );
	}

	/**
	 * Test site URL getter and setter
	 */
	public function test_site_url_get_set(): void {
		$gsc = new GoogleSearchConsole();
		$site_url = 'https://example.com/';
		
		$gsc->set_site_url( $site_url );
		$this->assertEquals( $site_url, $gsc->get_site_url() );
	}

	/**
	 * Test site URL in constructor
	 */
	public function test_site_url_constructor(): void {
		$site_url = 'https://example.com/';
		$gsc = new GoogleSearchConsole( $site_url );
		
		$this->assertEquals( $site_url, $gsc->get_site_url() );
	}

	/**
	 * Test connection status when not configured
	 */
	public function test_is_connected_false_when_not_configured(): void {
		$gsc = new GoogleSearchConsole();
		$this->assertFalse( $gsc->is_connected() );
	}

	/**
	 * Test authorization URL when not configured
	 */
	public function test_authorization_url_when_not_configured(): void {
		$gsc = new GoogleSearchConsole();
		$auth_url = $gsc->get_authorization_url();
		
		// Should return empty string when not configured
		$this->assertIsString( $auth_url );
	}

	/**
	 * Test fetch metrics when not connected
	 */
	public function test_fetch_metrics_when_not_connected(): void {
		$gsc = new GoogleSearchConsole();
		$result = $gsc->fetch_metrics( 1, '2024-01-01', '2024-01-31' );
		
		$this->assertFalse( $result );
	}

	/**
	 * Test get properties when not connected
	 */
	public function test_get_properties_when_not_connected(): void {
		$gsc = new GoogleSearchConsole();
		$properties = $gsc->get_properties();
		
		$this->assertIsArray( $properties );
		$this->assertEmpty( $properties );
	}

	/**
	 * Test validate property when not connected
	 */
	public function test_validate_property_when_not_connected(): void {
		$gsc = new GoogleSearchConsole();
		$valid = $gsc->validate_property( 'https://example.com/' );
		
		$this->assertFalse( $valid );
	}

	/**
	 * Test source ID constant
	 */
	public function test_source_id_constant(): void {
		$this->assertEquals( 'google_search_console', GoogleSearchConsole::SOURCE_ID );
	}

	/**
	 * Test fetch metrics with filters
	 */
	public function test_fetch_metrics_with_filters(): void {
		$gsc = new GoogleSearchConsole( 'https://example.com/' );
		
		$filters = [
			'query' => 'test query',
			'page' => 'https://example.com/page',
			'country' => 'ita',
			'device' => 'mobile',
		];
		
		$result = $gsc->fetch_metrics( 1, '2024-01-01', '2024-01-31', $filters );
		
		// Should return false when not connected, even with filters
		$this->assertFalse( $result );
	}

	/**
	 * Test OAuth callback handling
	 */
	public function test_oauth_callback_handling(): void {
		$gsc = new GoogleSearchConsole();
		
		// Should return boolean
		$result = $gsc->handle_oauth_callback( 'test_code' );
		$this->assertIsBool( $result );
	}
}
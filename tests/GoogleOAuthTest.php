<?php
/**
 * Tests for GoogleOAuth integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FP\DigitalMarketing\DataSources\GoogleOAuth;

/**
 * Test class for GoogleOAuth integration
 */
class GoogleOAuthTest extends TestCase {

	/**
	 * Test GoogleOAuth class instantiation
	 */
	public function test_oauth_instantiation(): void {
		$oauth = new GoogleOAuth();
		$this->assertInstanceOf( GoogleOAuth::class, $oauth );
	}

	/**
	 * Test OAuth configuration check when not configured
	 */
	public function test_oauth_not_configured(): void {
		$oauth = new GoogleOAuth();
		$this->assertFalse( $oauth->is_configured() );
	}

	/**
	 * Test authentication status when not authenticated
	 */
	public function test_not_authenticated(): void {
		$oauth = new GoogleOAuth();
		$this->assertFalse( $oauth->is_authenticated() );
	}

	/**
	 * Test authorization URL when not configured
	 */
	public function test_authorization_url_not_configured(): void {
		$oauth = new GoogleOAuth();
		$url = $oauth->get_authorization_url();
		$this->assertEquals( '', $url );
	}

	/**
	 * Test access token when not authenticated
	 */
	public function test_access_token_not_available(): void {
		$oauth = new GoogleOAuth();
		$token = $oauth->get_access_token();
		$this->assertFalse( $token );
	}

	/**
	 * Test connection status structure
	 */
	public function test_connection_status_structure(): void {
		$oauth = new GoogleOAuth();
		$status = $oauth->get_connection_status();
		
		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'connected', $status );
		$this->assertArrayHasKey( 'status', $status );
		$this->assertArrayHasKey( 'class', $status );
		$this->assertFalse( $status['connected'] );
	}

	/**
	 * Test token exchange with invalid code
	 */
	public function test_token_exchange_invalid_code(): void {
		$oauth = new GoogleOAuth();
		$result = $oauth->exchange_code_for_tokens( 'invalid_code' );
		$this->assertFalse( $result );
	}

	/**
	 * Test token refresh when no token exists
	 */
	public function test_token_refresh_no_token(): void {
		$oauth = new GoogleOAuth();
		$result = $oauth->refresh_token_if_needed();
		$this->assertFalse( $result );
	}

	/**
	 * Test revoke access
	 */
	public function test_revoke_access(): void {
		$oauth = new GoogleOAuth();
		$result = $oauth->revoke_access();
		$this->assertTrue( $result );
	}
}
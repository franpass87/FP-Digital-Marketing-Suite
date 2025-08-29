<?php
/**
 * Google OAuth Helper for Analytics integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

use FP\DigitalMarketing\Helpers\Security;

/**
 * Google OAuth client for handling authentication
 * 
 * This class manages OAuth 2.0 flow with Google for accessing Analytics API.
 * It handles token storage, refresh, and validation.
 */
class GoogleOAuth {

	/**
	 * Google OAuth endpoint
	 */
	private const OAUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

	/**
	 * Google token endpoint
	 */
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Analytics scope
	 */
	private const ANALYTICS_SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

	/**
	 * Search Console scope
	 */
	private const SEARCH_CONSOLE_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Combined scopes for both services
	 */
	private const COMBINED_SCOPES = self::ANALYTICS_SCOPE . ' ' . self::SEARCH_CONSOLE_SCOPE;

	/**
	 * Option name for storing tokens
	 */
	private const TOKEN_OPTION = 'fp_dms_google_oauth_tokens';

	/**
	 * Option name for storing OAuth settings
	 */
	private const OAUTH_SETTINGS_OPTION = 'fp_dms_google_oauth_settings';

	/**
	 * Client credentials
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->credentials = $this->get_oauth_credentials();
	}

	/**
	 * Get OAuth credentials from settings
	 *
	 * @return array OAuth credentials
	 */
	private function get_oauth_credentials(): array {
		$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );
		
		return [
			'client_id' => $api_keys['google_client_id'] ?? '',
			'client_secret' => $api_keys['google_client_secret'] ?? '',
			'redirect_uri' => admin_url( 'admin.php?page=fp-digital-marketing-settings&ga4_callback=1' ),
		];
	}

	/**
	 * Check if OAuth is properly configured
	 *
	 * @return bool True if configured
	 */
	public function is_configured(): bool {
		return ! empty( $this->credentials['client_id'] ) && ! empty( $this->credentials['client_secret'] );
	}

	/**
	 * Get authorization URL for OAuth flow
	 *
	 * @return string Authorization URL
	 */
	public function get_authorization_url(): string {
		if ( ! $this->is_configured() ) {
			return '';
		}

		$state = wp_create_nonce( 'ga4_oauth_state' );
		update_option( 'fp_dms_oauth_state', $state );

		$params = [
			'client_id' => $this->credentials['client_id'],
			'redirect_uri' => $this->credentials['redirect_uri'],
			'scope' => self::COMBINED_SCOPES,
			'response_type' => 'code',
			'access_type' => 'offline',
			'prompt' => 'consent',
			'state' => $state,
		];

		return self::OAUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access and refresh tokens
	 *
	 * @param string $authorization_code Authorization code from Google
	 * @return bool True on success, false on failure
	 */
	public function exchange_code_for_tokens( string $authorization_code ): bool {
		if ( ! $this->is_configured() ) {
			return false;
		}

		$data = [
			'client_id' => $this->credentials['client_id'],
			'client_secret' => $this->credentials['client_secret'],
			'redirect_uri' => $this->credentials['redirect_uri'],
			'grant_type' => 'authorization_code',
			'code' => $authorization_code,
		];

		$response = wp_remote_post( self::TOKEN_URL, [
			'body' => $data,
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'GA4 OAuth token exchange error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$tokens = json_decode( $body, true );

		if ( isset( $tokens['access_token'] ) ) {
			$this->store_tokens( $tokens );
			return true;
		}

		error_log( 'GA4 OAuth token exchange failed: ' . $body );
		return false;
	}

	/**
	 * Store tokens securely with encryption
	 *
	 * @param array $tokens Token data from Google
	 * @return void
	 */
	private function store_tokens( array $tokens ): void {
		$token_data = [
			'access_token' => Security::encrypt_sensitive_data( $tokens['access_token'] ),
			'refresh_token' => isset( $tokens['refresh_token'] ) ? Security::encrypt_sensitive_data( $tokens['refresh_token'] ) : '',
			'expires_in' => $tokens['expires_in'] ?? 3600,
			'token_type' => $tokens['token_type'] ?? 'Bearer',
			'created_at' => time(),
		];

		update_option( self::TOKEN_OPTION, $token_data, false ); // autoload = false for security
	}

	/**
	 * Get stored tokens with decryption
	 *
	 * @return array|false Token data with decrypted sensitive values or false if not found
	 */
	private function get_stored_tokens(): array|false {
		$tokens = get_option( self::TOKEN_OPTION, false );
		
		if ( ! $tokens ) {
			return false;
		}

		// Decrypt sensitive token data
		$decrypted_tokens = [
			'access_token' => Security::decrypt_sensitive_data( $tokens['access_token'] ),
			'refresh_token' => ! empty( $tokens['refresh_token'] ) ? Security::decrypt_sensitive_data( $tokens['refresh_token'] ) : '',
			'expires_in' => $tokens['expires_in'],
			'token_type' => $tokens['token_type'],
			'created_at' => $tokens['created_at'],
		];

		return $decrypted_tokens;
	}

	/**
	 * Check if user is authenticated
	 *
	 * @return bool True if authenticated
	 */
	public function is_authenticated(): bool {
		$tokens = $this->get_stored_tokens();
		return $tokens && ! empty( $tokens['access_token'] );
	}

	/**
	 * Get current access token
	 *
	 * @return string|false Access token or false if not available
	 */
	public function get_access_token(): string|false {
		$tokens = $this->get_stored_tokens();
		
		if ( ! $tokens || empty( $tokens['access_token'] ) ) {
			return false;
		}

		return $tokens['access_token'];
	}

	/**
	 * Check if token needs refresh and refresh if necessary
	 *
	 * @return bool True if token is valid (refreshed if needed), false otherwise
	 */
	public function refresh_token_if_needed(): bool {
		$tokens = $this->get_stored_tokens();
		
		if ( ! $tokens ) {
			return false;
		}

		// Check if token is expired (with 5 minute buffer)
		$expires_at = $tokens['created_at'] + $tokens['expires_in'] - 300;
		
		if ( time() < $expires_at ) {
			return true; // Token is still valid
		}

		// Token needs refresh
		return $this->refresh_access_token();
	}

	/**
	 * Refresh access token using refresh token
	 *
	 * @return bool True on success, false on failure
	 */
	private function refresh_access_token(): bool {
		$tokens = $this->get_stored_tokens();
		
		if ( ! $tokens || empty( $tokens['refresh_token'] ) ) {
			return false;
		}

		$data = [
			'client_id' => $this->credentials['client_id'],
			'client_secret' => $this->credentials['client_secret'],
			'refresh_token' => $tokens['refresh_token'],
			'grant_type' => 'refresh_token',
		];

		$response = wp_remote_post( self::TOKEN_URL, [
			'body' => $data,
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'GA4 token refresh error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$new_tokens = json_decode( $body, true );

		if ( isset( $new_tokens['access_token'] ) ) {
			// Update tokens, preserving refresh token if not provided
			$new_tokens['refresh_token'] = $new_tokens['refresh_token'] ?? $tokens['refresh_token'];
			$this->store_tokens( $new_tokens );
			return true;
		}

		error_log( 'GA4 token refresh failed: ' . $body );
		return false;
	}

	/**
	 * Revoke access and clear stored tokens
	 *
	 * @return bool True on success, false on failure
	 */
	public function revoke_access(): bool {
		$tokens = $this->get_stored_tokens();
		
		if ( $tokens && ! empty( $tokens['access_token'] ) ) {
			// Revoke token with Google
			$revoke_url = 'https://oauth2.googleapis.com/revoke';
			wp_remote_post( $revoke_url, [
				'body' => [ 'token' => $tokens['access_token'] ],
			] );
		}

		// Clear stored tokens
		delete_option( self::TOKEN_OPTION );
		return true;
	}

	/**
	 * Get OAuth connection status for display
	 *
	 * @return array Status information
	 */
	public function get_connection_status(): array {
		$tokens = $this->get_stored_tokens();
		
		if ( ! $tokens ) {
			return [
				'connected' => false,
				'status' => __( 'Non connesso', 'fp-digital-marketing' ),
				'class' => 'disconnected',
			];
		}

		$expires_at = $tokens['created_at'] + $tokens['expires_in'];
		$is_expired = time() > $expires_at;

		return [
			'connected' => ! $is_expired,
			'status' => $is_expired ? 
				__( 'Token scaduto', 'fp-digital-marketing' ) :
				__( 'Connesso', 'fp-digital-marketing' ),
			'class' => $is_expired ? 'expired' : 'connected',
			'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
		];
	}
}
<?php
/**
 * Google OAuth Helper for Analytics integration
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\DataSources;

use FP\DigitalMarketing\Helpers\Security;
use FP\DigitalMarketing\Helpers\SecretsManager;
use FP\DigitalMarketing\Setup\SettingsManager;

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
	 * Google Ads scope
	 */
	private const GOOGLE_ADS_SCOPE = 'https://www.googleapis.com/auth/adwords';

	/**
	 * Combined scopes for all services
	 */
	private const COMBINED_SCOPES = self::ANALYTICS_SCOPE . ' ' . self::SEARCH_CONSOLE_SCOPE . ' ' . self::GOOGLE_ADS_SCOPE;

	/**
	 * Option name for storing tokens
	 */
        private const TOKEN_OPTION = SettingsManager::OPTION_GOOGLE_OAUTH_TOKENS;

	/**
	 * Option name for storing OAuth settings
	 */
        private const OAUTH_SETTINGS_OPTION = SettingsManager::OPTION_GOOGLE_OAUTH_SETTINGS;

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
                $api_keys = SecretsManager::get_api_keys();
                $display_context = SecretsManager::prepare_for_display( $api_keys );
                $decrypted_keys = $display_context['values'];
                $decryption_errors = $display_context['errors'];

                $client_id = '';
                if ( isset( $decrypted_keys['google_client_id'] ) && is_string( $decrypted_keys['google_client_id'] ) ) {
                        $client_id = trim( $decrypted_keys['google_client_id'] );
                }

                $client_secret = '';
                if ( isset( $decrypted_keys['google_client_secret'] ) && is_string( $decrypted_keys['google_client_secret'] ) ) {
                        $client_secret = $decrypted_keys['google_client_secret'];
                }

                $secret_decryption_failed = in_array( 'google_client_secret', $decryption_errors, true );
                $credentials_expired = $secret_decryption_failed || '' === $client_id || '' === $client_secret;

                $status = SettingsManager::get_option( self::OAUTH_SETTINGS_OPTION, [] );
                if ( ! is_array( $status ) ) {
                        $status = [];
                }

                $reason = 'ok';
                if ( $secret_decryption_failed ) {
                        $reason = 'decryption_failed';
                } elseif ( '' === $client_id || '' === $client_secret ) {
                        $reason = 'missing_credentials';
                }

                $status_update = array_merge(
                        $status,
                        [
                                'credentials_expired' => $credentials_expired,
                                'decryption_failed' => $secret_decryption_failed,
                                'has_client_id' => '' !== $client_id,
                                'has_client_secret' => '' !== $client_secret,
                                'reason' => $reason,
                        ]
                );

                $status_has_changed = (
                        ( $status['credentials_expired'] ?? null ) !== $status_update['credentials_expired'] ||
                        ( $status['decryption_failed'] ?? null ) !== $status_update['decryption_failed'] ||
                        ( $status['has_client_id'] ?? null ) !== $status_update['has_client_id'] ||
                        ( $status['has_client_secret'] ?? null ) !== $status_update['has_client_secret'] ||
                        ( $status['reason'] ?? null ) !== $status_update['reason']
                );

                if ( $status_has_changed ) {
                        $status_update['checked_at'] = time();

                        if ( ! $credentials_expired ) {
                                $status_update['last_valid_credentials_at'] = time();
                        }

                        SettingsManager::update_option( self::OAUTH_SETTINGS_OPTION, $status_update, false );
                }

                return [
                        'client_id' => $client_id,
                        'client_secret' => $client_secret,
                        'redirect_uri' => admin_url( 'admin.php?page=fp-digital-marketing-settings&ga4_callback=1' ),
                        'expired' => $credentials_expired,
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
                SettingsManager::update_option( SettingsManager::OPTION_OAUTH_STATE, $state );

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
                        'timeout' => 20,
                ] );

                if ( is_wp_error( $response ) ) {
                        if ( function_exists( 'error_log' ) ) {
                                error_log( 'GA4 OAuth token exchange error: ' . $response->get_error_message() );
                        }
                        return false;
                }

                $status_code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );

                if ( 200 !== $status_code ) {
                        if ( function_exists( 'error_log' ) ) {
                                error_log( sprintf( 'GA4 OAuth token exchange HTTP %d: %s', $status_code, $body ) );
                        }
                        return false;
                }

                $tokens = json_decode( $body, true );

                if ( ! is_array( $tokens ) ) {
                        if ( function_exists( 'error_log' ) ) {
                                error_log( 'GA4 OAuth token exchange returned invalid JSON.' );
                        }
                        return false;
                }

                if ( empty( $tokens['access_token'] ) ) {
                        if ( function_exists( 'error_log' ) ) {
                                $error_message = isset( $tokens['error'] ) ? $tokens['error'] : 'missing access token';
                                error_log( 'GA4 OAuth token exchange failed: ' . $error_message );
                        }
                        return false;
                }

                $existing_tokens = $this->get_stored_tokens();
                if ( empty( $tokens['refresh_token'] ) && is_array( $existing_tokens ) && ! empty( $existing_tokens['refresh_token'] ) ) {
                        $tokens['refresh_token'] = $existing_tokens['refresh_token'];
                }

                $this->store_tokens( $tokens );

                return true;
        }

	/**
	 * Store tokens securely with encryption
	 *
	 * @param array $tokens Token data from Google
	 * @return void
	 */
        private function store_tokens( array $tokens ): void {
                $existing_tokens = $this->get_stored_tokens();

                $refresh_token = $tokens['refresh_token'] ?? '';
                if ( '' === $refresh_token && is_array( $existing_tokens ) && ! empty( $existing_tokens['refresh_token'] ) ) {
                        $refresh_token = $existing_tokens['refresh_token'];
                }

                $expires_in = isset( $tokens['expires_in'] ) ? (int) $tokens['expires_in'] : null;
                if ( null === $expires_in && is_array( $existing_tokens ) && isset( $existing_tokens['expires_in'] ) ) {
                        $expires_in = (int) $existing_tokens['expires_in'];
                }

                $token_data = [
                        'access_token' => Security::encrypt_sensitive_data( $tokens['access_token'] ),
                        'refresh_token' => $refresh_token !== '' ? Security::encrypt_sensitive_data( $refresh_token ) : '',
                        'expires_in' => $expires_in ?? 3600,
                        'token_type' => $tokens['token_type'] ?? ( $existing_tokens['token_type'] ?? 'Bearer' ),
                        'created_at' => time(),
                ];

                SettingsManager::update_option( self::TOKEN_OPTION, $token_data, false ); // autoload = false for security
        }

	/**
	 * Get stored tokens with decryption
	 *
	 * @return array|false Token data with decrypted sensitive values or false if not found
	 */
	private function get_stored_tokens(): array|false {
                $tokens = SettingsManager::get_option( self::TOKEN_OPTION, [] );

                if ( ! is_array( $tokens ) || empty( $tokens ) ) {
                        return false;
                }

                $access_token = isset( $tokens['access_token'] ) ? Security::decrypt_sensitive_data( $tokens['access_token'] ) : '';
                $refresh_token_encrypted = $tokens['refresh_token'] ?? '';
                $refresh_token = '' !== $refresh_token_encrypted
                        ? Security::decrypt_sensitive_data( $refresh_token_encrypted )
                        : '';

                return [
                        'access_token' => $access_token,
                        'refresh_token' => $refresh_token,
                        'expires_in' => isset( $tokens['expires_in'] ) ? (int) $tokens['expires_in'] : 0,
                        'token_type' => $tokens['token_type'] ?? 'Bearer',
                        'created_at' => isset( $tokens['created_at'] ) ? (int) $tokens['created_at'] : 0,
                ];
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
                SettingsManager::delete_option( self::TOKEN_OPTION );
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
                                'expired' => false,
                                'status' => __( 'Non connesso', 'fp-digital-marketing' ),
                                'class' => 'disconnected',
                        ];
                }

                $expires_at = $tokens['created_at'] + $tokens['expires_in'];
                $is_expired = time() >= $expires_at;

                return [
                        'connected' => ! $is_expired,
                        'expired' => $is_expired,
                        'status' => $is_expired ?
                                __( 'Token scaduto', 'fp-digital-marketing' ) :
                                __( 'Connesso', 'fp-digital-marketing' ),
                        'class' => $is_expired ? 'expired' : 'connected',
                        'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
                        'expires_at_timestamp' => $expires_at,
                ];
        }
}

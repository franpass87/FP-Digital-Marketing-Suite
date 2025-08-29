<?php
/**
 * Security Helper Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Security utility class for enhanced security measures
 */
class Security {

	/**
	 * Encryption method for API keys
	 */
	private const ENCRYPTION_METHOD = 'AES-256-CBC';

	/**
	 * Salt for key derivation
	 */
	private const SALT_LENGTH = 32;

	/**
	 * Security audit results
	 *
	 * @var array
	 */
	private static array $audit_results = [];

	/**
	 * Encrypt sensitive data (API keys, tokens)
	 *
	 * @param string $data Data to encrypt.
	 * @return string Encrypted data with IV prepended.
	 */
	public static function encrypt_sensitive_data( string $data ): string {
		if ( empty( $data ) ) {
			return '';
		}

		$key = self::get_encryption_key();
		$iv = random_bytes( openssl_cipher_iv_length( self::ENCRYPTION_METHOD ) );
		
		$encrypted = openssl_encrypt( $data, self::ENCRYPTION_METHOD, $key, 0, $iv );
		
		if ( false === $encrypted ) {
			error_log( 'FP Digital Marketing: Failed to encrypt sensitive data' );
			return '';
		}

		// Prepend IV to encrypted data for decryption
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt sensitive data
	 *
	 * @param string $encrypted_data Encrypted data with IV prepended.
	 * @return string Decrypted data.
	 */
	public static function decrypt_sensitive_data( string $encrypted_data ): string {
		if ( empty( $encrypted_data ) ) {
			return '';
		}

		$data = base64_decode( $encrypted_data );
		if ( false === $data ) {
			error_log( 'FP Digital Marketing: Failed to decode base64 data' );
			return '';
		}

		$key = self::get_encryption_key();
		$iv_length = openssl_cipher_iv_length( self::ENCRYPTION_METHOD );
		
		if ( strlen( $data ) < $iv_length ) {
			error_log( 'FP Digital Marketing: Encrypted data too short for IV' );
			return '';
		}

		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv );
		
		if ( false === $decrypted ) {
			error_log( 'FP Digital Marketing: Failed to decrypt sensitive data' );
			return '';
		}

		return $decrypted;
	}

	/**
	 * Get or generate encryption key
	 *
	 * @return string Encryption key.
	 */
	private static function get_encryption_key(): string {
		$key_option = 'fp_dms_encryption_key';
		$stored_key = get_option( $key_option );

		if ( empty( $stored_key ) ) {
			// Generate new key using WordPress salts and constants
			$key_material = '';
			
			// Use WordPress security salts if available
			if ( defined( 'AUTH_SALT' ) ) {
				$key_material .= AUTH_SALT;
			}
			if ( defined( 'SECURE_AUTH_SALT' ) ) {
				$key_material .= SECURE_AUTH_SALT;
			}
			if ( defined( 'LOGGED_IN_SALT' ) ) {
				$key_material .= LOGGED_IN_SALT;
			}
			if ( defined( 'NONCE_SALT' ) ) {
				$key_material .= NONCE_SALT;
			}

			// Add plugin-specific entropy
			$key_material .= FP_DIGITAL_MARKETING_VERSION;
			if ( defined( 'ABSPATH' ) ) {
				$key_material .= ABSPATH;
			}
			
			// Fallback if no key material
			if ( empty( $key_material ) ) {
				$key_material = 'fp-digital-marketing-fallback-key-' . time();
			}
			
			// Use simple sha256 hash for 32-byte key
			$key = hash( 'sha256', $key_material, true );
			
			$stored_key = base64_encode( $key );
			update_option( $key_option, $stored_key, false ); // autoload = false for security
		}

		$key = base64_decode( $stored_key );
		if ( strlen( $key ) !== 32 ) {
			// Invalid key length, regenerate
			delete_option( $key_option );
			return self::get_encryption_key();
		}
		
		return $key;
	}

	/**
	 * Enhanced nonce verification with logging
	 *
	 * @param string $action Nonce action.
	 * @param string $name Nonce field name.
	 * @return bool True if nonce is valid.
	 */
	public static function verify_nonce_with_logging( string $action, string $name = '_wpnonce' ): bool {
		$nonce = $_POST[ $name ] ?? $_GET[ $name ] ?? '';
		
		if ( empty( $nonce ) ) {
			self::log_security_event( 'nonce_missing', [
				'action' => $action,
				'name' => $name,
				'user_id' => get_current_user_id(),
				'ip' => self::get_client_ip(),
			] );
			return false;
		}

		$verified = wp_verify_nonce( $nonce, $action );
		
		if ( ! $verified ) {
			self::log_security_event( 'nonce_invalid', [
				'action' => $action,
				'name' => $name,
				'user_id' => get_current_user_id(),
				'ip' => self::get_client_ip(),
			] );
		}

		return (bool) $verified;
	}

	/**
	 * Enhanced capability check with logging
	 *
	 * @param string $capability Required capability.
	 * @param int    $object_id Optional object ID.
	 * @return bool True if user has capability.
	 */
	public static function verify_capability_with_logging( string $capability, int $object_id = 0 ): bool {
		$user_id = get_current_user_id();
		$has_cap = current_user_can( $capability, $object_id );

		if ( ! $has_cap ) {
			self::log_security_event( 'capability_denied', [
				'capability' => $capability,
				'object_id' => $object_id,
				'user_id' => $user_id,
				'ip' => self::get_client_ip(),
			] );
		}

		return $has_cap;
	}

	/**
	 * Comprehensive security audit
	 *
	 * @return array Audit results.
	 */
	public static function run_security_audit(): array {
		self::$audit_results = [
			'timestamp' => current_time( 'mysql' ),
			'plugin_version' => FP_DIGITAL_MARKETING_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'checks' => [],
			'overall_score' => 0,
			'critical_issues' => 0,
			'warnings' => 0,
		];

		// Check WordPress version
		self::audit_wordpress_version();
		
		// Check PHP version
		self::audit_php_version();
		
		// Check file permissions
		self::audit_file_permissions();
		
		// Check security constants
		self::audit_security_constants();
		
		// Check encryption capabilities
		self::audit_encryption_support();
		
		// Check database security
		self::audit_database_security();
		
		// Check API key storage
		self::audit_api_key_storage();

		// Calculate overall score
		self::calculate_audit_score();

		return self::$audit_results;
	}

	/**
	 * Log security events
	 *
	 * @param string $event_type Type of security event.
	 * @param array  $context Additional context data.
	 */
	private static function log_security_event( string $event_type, array $context = [] ): void {
		$log_entry = [
			'timestamp' => current_time( 'c' ),
			'event_type' => $event_type,
			'context' => $context,
		];

		error_log( 'FP Digital Marketing Security Event: ' . wp_json_encode( $log_entry ) );
		
		// Store in database for admin review
		$security_logs = get_option( 'fp_dms_security_logs', [] );
		
		// Keep only last 100 entries
		if ( count( $security_logs ) >= 100 ) {
			$security_logs = array_slice( $security_logs, -99 );
		}
		
		$security_logs[] = $log_entry;
		update_option( 'fp_dms_security_logs', $security_logs, false );
	}

	/**
	 * Get client IP address safely
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		// Check for various headers that might contain the real IP
		$headers = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Take first IP if comma-separated list
				$ip = explode( ',', $ip )[0];
				$ip = trim( $ip );
				
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}

	/**
	 * Audit WordPress version
	 */
	private static function audit_wordpress_version(): void {
		$wp_version = get_bloginfo( 'version' );
		$latest_version = '6.4'; // This would ideally be fetched from WordPress.org API
		
		$check = [
			'name' => 'WordPress Version',
			'status' => version_compare( $wp_version, '5.0', '>=' ) ? 'pass' : 'fail',
			'message' => sprintf( 'WordPress version: %s', $wp_version ),
			'severity' => version_compare( $wp_version, '5.0', '<' ) ? 'critical' : 'info',
		];

		if ( version_compare( $wp_version, $latest_version, '<' ) ) {
			$check['severity'] = 'warning';
			$check['message'] .= sprintf( ' (Latest: %s)', $latest_version );
		}

		self::$audit_results['checks']['wp_version'] = $check;
	}

	/**
	 * Audit PHP version
	 */
	private static function audit_php_version(): void {
		$php_version = PHP_VERSION;
		
		$check = [
			'name' => 'PHP Version',
			'status' => version_compare( $php_version, '7.4', '>=' ) ? 'pass' : 'fail',
			'message' => sprintf( 'PHP version: %s', $php_version ),
			'severity' => version_compare( $php_version, '7.4', '<' ) ? 'critical' : 'info',
		];

		self::$audit_results['checks']['php_version'] = $check;
	}

	/**
	 * Audit file permissions
	 */
	private static function audit_file_permissions(): void {
		$plugin_dir = defined( 'FP_DIGITAL_MARKETING_PLUGIN_DIR' ) ? FP_DIGITAL_MARKETING_PLUGIN_DIR : __DIR__ . '/../../';
		$perms = @fileperms( $plugin_dir );
		$readable_perms = $perms !== false ? substr( sprintf( '%o', $perms ), -4 ) : 'unknown';
		
		$check = [
			'name' => 'File Permissions',
			'status' => $perms !== false ? 'pass' : 'fail',
			'message' => sprintf( 'Plugin directory permissions: %s', $readable_perms ),
			'severity' => 'info',
		];

		self::$audit_results['checks']['file_permissions'] = $check;
	}

	/**
	 * Audit security constants
	 */
	private static function audit_security_constants(): void {
		$required_constants = [
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		];

		$missing_constants = [];
		foreach ( $required_constants as $constant ) {
			if ( ! defined( $constant ) || empty( constant( $constant ) ) ) {
				$missing_constants[] = $constant;
			}
		}

		$check = [
			'name' => 'Security Constants',
			'status' => empty( $missing_constants ) ? 'pass' : 'fail',
			'message' => empty( $missing_constants ) 
				? 'All security constants are defined'
				: sprintf( 'Missing constants: %s', implode( ', ', $missing_constants ) ),
			'severity' => empty( $missing_constants ) ? 'info' : 'critical',
		];

		self::$audit_results['checks']['security_constants'] = $check;
	}

	/**
	 * Audit encryption support
	 */
	private static function audit_encryption_support(): void {
		$has_openssl = extension_loaded( 'openssl' );
		
		$check = [
			'name' => 'Encryption Support',
			'status' => $has_openssl ? 'pass' : 'fail',
			'message' => $has_openssl ? 'OpenSSL extension available' : 'OpenSSL extension not available',
			'severity' => $has_openssl ? 'info' : 'critical',
		];

		self::$audit_results['checks']['encryption_support'] = $check;
	}

	/**
	 * Audit database security
	 */
	private static function audit_database_security(): void {
		global $wpdb;
		
		$db_version = '';
		if ( isset( $wpdb ) && method_exists( $wpdb, 'get_var' ) ) {
			$db_version = $wpdb->get_var( 'SELECT VERSION()' );
		}
		
		$check = [
			'name' => 'Database Security',
			'status' => ! empty( $db_version ) ? 'pass' : 'warning',
			'message' => ! empty( $db_version ) ? sprintf( 'Database version: %s', $db_version ) : 'Database connection not available in test environment',
			'severity' => ! empty( $db_version ) ? 'info' : 'warning',
		];

		self::$audit_results['checks']['database_security'] = $check;
	}

	/**
	 * Audit API key storage
	 */
	private static function audit_api_key_storage(): void {
		$api_keys = get_option( 'fp_digital_marketing_api_keys', [] );
		$encrypted_count = 0;
		$total_count = 0;

		foreach ( $api_keys as $key => $value ) {
			if ( ! empty( $value ) ) {
				$total_count++;
				// Check if value looks encrypted (base64 encoded)
				if ( base64_encode( base64_decode( $value, true ) ) === $value ) {
					$encrypted_count++;
				}
			}
		}

		$status = $total_count === 0 ? 'pass' : ( $encrypted_count === $total_count ? 'pass' : 'warning' );
		
		$check = [
			'name' => 'API Key Storage',
			'status' => $status,
			'message' => sprintf( 'API keys: %d total, %d encrypted', $total_count, $encrypted_count ),
			'severity' => $status === 'pass' ? 'info' : 'warning',
		];

		self::$audit_results['checks']['api_key_storage'] = $check;
	}

	/**
	 * Calculate overall audit score
	 */
	private static function calculate_audit_score(): void {
		$total_checks = count( self::$audit_results['checks'] );
		$passed_checks = 0;
		$critical_issues = 0;
		$warnings = 0;

		foreach ( self::$audit_results['checks'] as $check ) {
			if ( $check['status'] === 'pass' ) {
				$passed_checks++;
			}

			if ( $check['severity'] === 'critical' ) {
				$critical_issues++;
			} elseif ( $check['severity'] === 'warning' ) {
				$warnings++;
			}
		}

		self::$audit_results['overall_score'] = $total_checks > 0 ? round( ( $passed_checks / $total_checks ) * 100 ) : 0;
		self::$audit_results['critical_issues'] = $critical_issues;
		self::$audit_results['warnings'] = $warnings;
	}

	/**
	 * Get security logs for admin review
	 *
	 * @param int $limit Number of logs to retrieve.
	 * @return array Security logs.
	 */
	public static function get_security_logs( int $limit = 50 ): array {
		$logs = get_option( 'fp_dms_security_logs', [] );
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Clear security logs
	 */
	public static function clear_security_logs(): void {
		delete_option( 'fp_dms_security_logs' );
		self::log_security_event( 'logs_cleared', [
			'user_id' => get_current_user_id(),
			'ip' => self::get_client_ip(),
		] );
	}
}
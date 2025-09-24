<?php
/**
 * Secrets Manager Helper
 *
 * Centralizes handling of sensitive settings such as API keys. This helper
 * ensures sensitive values are decrypted when displayed in the admin UI and
 * re-encrypted safely when persisted back to WordPress options.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

use FP\DigitalMarketing\Setup\SettingsManager;

/**
 * Secrets manager utility class.
 */
class SecretsManager {

		/**
		 * Keys that should be treated as sensitive and stored encrypted.
		 */
	public const SENSITIVE_KEYS = [
		'google_client_secret',
		'api_token',
		'secret_key',
	];

		/**
		 * Retrieve stored API keys.
		 *
		 * @param bool $decrypt_sensitive Whether to decrypt sensitive values.
		 * @return array<string, mixed>
		 */
	public static function get_api_keys( bool $decrypt_sensitive = false ): array {
			$api_keys = SettingsManager::get_option( SettingsManager::OPTION_API_KEYS, [] );

		if ( ! is_array( $api_keys ) ) {
				return [];
		}

		if ( ! $decrypt_sensitive ) {
				return $api_keys;
		}

			$prepared = self::prepare_for_display( $api_keys );

			return $prepared['values'];
	}

		/**
		 * Decrypt sensitive values for safe rendering in admin forms.
		 *
		 * @param array<string, mixed>    $values         Raw option values.
		 * @param array<int, string>|null $sensitive_keys Optional override for sensitive keys.
		 * @return array{values: array<string, mixed>, errors: array<int, string>} Decrypted values and keys that failed to decrypt.
		 */
	public static function prepare_for_display( array $values, ?array $sensitive_keys = null ): array {
			$sensitive_keys = $sensitive_keys ?? self::SENSITIVE_KEYS;
			$errors         = [];

		foreach ( $sensitive_keys as $key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

				$raw_value = $values[ $key ];

			if ( ! is_string( $raw_value ) || '' === $raw_value ) {
					$values[ $key ] = '';
					continue;
			}

				$result = self::decrypt_value( $raw_value );

			if ( $result['decryption_failed'] ) {
					$errors[] = $key;
			}

				$values[ $key ] = $result['value'];
		}

			return [
				'values' => $values,
				'errors' => $errors,
			];
	}

		/**
		 * Prepare sanitized values for secure storage.
		 *
		 * @param array<string, string>   $values   Sanitized user input.
		 * @param array<string, mixed>    $existing Previously stored values.
		 * @param array<int, string>|null $sensitive_keys Optional override for sensitive keys.
		 * @return array{values: array<string, mixed>, updated_sensitive_keys: array<int, string>, errors: array<int, string>} Prepared values and metadata.
		 */
	public static function prepare_for_storage( array $values, array $existing, ?array $sensitive_keys = null ): array {
			$sensitive_keys = $sensitive_keys ?? self::SENSITIVE_KEYS;

			$prepared = [
				'values'                 => [],
				'updated_sensitive_keys' => [],
				'errors'                 => [],
			];

			foreach ( $values as $key => $value ) {
				if ( in_array( $key, $sensitive_keys, true ) ) {
						$sensitive_result           = self::prepare_sensitive_value_for_storage( $key, $value, $existing );
						$prepared['values'][ $key ] = $sensitive_result['value'];

					if ( $sensitive_result['was_updated'] ) {
						$prepared['updated_sensitive_keys'][] = $key;
					}

					if ( $sensitive_result['error'] ) {
							$prepared['errors'][] = $key;
					}
				} else {
						$prepared['values'][ $key ] = is_string( $value ) ? $value : (string) $value;
				}
			}

			return $prepared;
	}

		/**
		 * Decrypt a single stored sensitive value.
		 *
		 * @param string|null $value Stored value.
		 * @return array{value: string, had_value: bool, decryption_failed: bool}
		 */
	public static function decrypt_value( ?string $value ): array {
			$value = is_string( $value ) ? $value : '';

		if ( '' === $value ) {
				return [
					'value'             => '',
					'had_value'         => false,
					'decryption_failed' => false,
				];
		}

			$decrypted = Security::decrypt_sensitive_data( $value );

		if ( '' === $decrypted ) {
				return [
					'value'             => '',
					'had_value'         => true,
					'decryption_failed' => true,
				];
		}

			return [
				'value'             => $decrypted,
				'had_value'         => true,
				'decryption_failed' => false,
			];
	}

		/**
		 * Determine if a key is considered sensitive.
		 *
		 * @param string $key Option key name.
		 * @return bool True when the key must be encrypted.
		 */
	public static function is_sensitive_key( string $key ): bool {
			return in_array( $key, self::SENSITIVE_KEYS, true );
	}

		/**
		 * Prepare a single sensitive value for storage.
		 *
		 * @param string               $key      Option key name.
		 * @param string|mixed         $value    Sanitized user input.
		 * @param array<string, mixed> $existing Existing stored values.
		 * @return array{value: string, was_updated: bool, error: bool}
		 */
	private static function prepare_sensitive_value_for_storage( string $key, $value, array $existing ): array {
			$normalized_value = is_string( $value ) ? trim( $value ) : '';

			$existing_encrypted = '';
		if ( isset( $existing[ $key ] ) && is_string( $existing[ $key ] ) ) {
				$existing_encrypted = $existing[ $key ];
		}

			$existing_plain = '';
			$existing_error = false;

		if ( '' !== $existing_encrypted ) {
				$existing_plain = Security::decrypt_sensitive_data( $existing_encrypted );

			if ( '' === $existing_plain ) {
					$existing_error = true;
			}
		}

		if ( '' === $normalized_value ) {
				return [
					'value'       => '',
					'was_updated' => '' !== $existing_plain,
					'error'       => $existing_error,
				];
		}

		if ( '' !== $existing_plain && hash_equals( $existing_plain, $normalized_value ) ) {
				return [
					'value'       => $existing_encrypted,
					'was_updated' => false,
					'error'       => $existing_error,
				];
		}

			return [
				'value'       => Security::encrypt_sensitive_data( $normalized_value ),
				'was_updated' => true,
				'error'       => $existing_error,
			];
	}
}

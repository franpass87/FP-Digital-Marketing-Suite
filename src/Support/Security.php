<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use RuntimeException;
use function base64_decode;
use function base64_encode;
use function function_exists;
use function in_array;
use function is_array;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function openssl_get_cipher_methods;
use function random_bytes;
use function strlen;
use function str_starts_with;
use function substr;

class Security
{
    private const OPENSSL_PREFIX = 'openssl:';
    private const OPENSSL_METHOD = 'aes-256-gcm';
    private const OPENSSL_TAG_LENGTH = 16;

    private static bool $notice_hooked = false;

    public static function isEncryptionAvailable(): bool
    {
        return self::isSodiumAvailable() || self::isOpenSslAvailable();
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return $plain;
        }

        if (self::isSodiumAvailable()) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plain, $nonce, self::getKey());

            return base64_encode($nonce . $cipher);
        }

        if (self::isOpenSslAvailable()) {
            return self::encryptWithOpenSsl($plain);
        }

        throw new RuntimeException('No secure encryption backend is available.');
    }

    public static function decrypt(string $encoded, ?bool &$failed = null): string
    {
        $failed = false;

        if ($encoded === '') {
            return $encoded;
        }

        if (str_starts_with($encoded, self::OPENSSL_PREFIX)) {
            if (! self::isOpenSslAvailable()) {
                $failed = true;

                return $encoded;
            }

            return self::decryptWithOpenSsl($encoded, $failed);
        }

        if (! self::isSodiumAvailable()) {
            $failed = true;

            return $encoded;
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            $failed = true;

            return $encoded;
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::getKey());

        if ($plain === false) {
            $failed = true;

            return $encoded;
        }

        return $plain;
    }

    public static function registerAdminNotice(): void
    {
        if (self::$notice_hooked) {
            return;
        }

        self::$notice_hooked = true;

        if (self::isEncryptionAvailable()) {
            return;
        }

        add_action('admin_notices', static function (): void {
            if (! current_user_can('manage_options')) {
                return;
            }

            echo '<div class="notice notice-warning"><p>' . esc_html__(
                'FP Digital Marketing Suite recommends installing the sodium extension for secure credential storage.',
                'fp-dms'
            ) . '</p></div>';
        });
    }

    private static function getKey(): string
    {
        $salt = Wp::salt('fpdms');
        $hash = hash('sha256', $salt, true);

        return substr($hash, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function isSodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox');
    }

    private static function isOpenSslAvailable(): bool
    {
        if (! function_exists('openssl_encrypt') || ! function_exists('openssl_decrypt')) {
            return false;
        }

        $methods = openssl_get_cipher_methods();

        return is_array($methods) && in_array(self::OPENSSL_METHOD, $methods, true);
    }

    private static function encryptWithOpenSsl(string $plain): string
    {
        $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD);
        if ($ivLength === false || $ivLength <= 0) {
            throw new RuntimeException('Invalid OpenSSL IV length.');
        }

        $iv = random_bytes($ivLength);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::OPENSSL_METHOD, self::getKey(), OPENSSL_RAW_DATA, $iv, $tag, '', self::OPENSSL_TAG_LENGTH);

        if ($cipher === false || $tag === '') {
            throw new RuntimeException('Unable to encrypt payload with OpenSSL.');
        }

        return self::OPENSSL_PREFIX . base64_encode($iv . $tag . $cipher);
    }

    private static function decryptWithOpenSsl(string $encoded, ?bool &$failed): string
    {
        $payload = substr($encoded, strlen(self::OPENSSL_PREFIX));
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            $failed = true;

            return $encoded;
        }

        $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD);
        if ($ivLength === false || $ivLength <= 0) {
            $failed = true;

            return $encoded;
        }

        $minimumLength = $ivLength + self::OPENSSL_TAG_LENGTH + 1;
        if (strlen($decoded) < $minimumLength) {
            $failed = true;

            return $encoded;
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, self::OPENSSL_TAG_LENGTH);
        $cipher = substr($decoded, $ivLength + self::OPENSSL_TAG_LENGTH);

        $plain = openssl_decrypt($cipher, self::OPENSSL_METHOD, self::getKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            $failed = true;

            return $encoded;
        }

        return $plain;
    }

    /**
     * Verify a WordPress nonce.
     *
     * @param string $nonce Nonce value to verify
     * @param string $action Action name for the nonce
     * @return bool True if valid, false otherwise
     */
    public static function verifyNonce(string $nonce, string $action): bool
    {
        if (function_exists('wp_verify_nonce')) {
            $result = wp_verify_nonce($nonce, $action);
            return $result !== false && $result !== 0;
        }

        // Fallback: basic validation when wp_verify_nonce is not available
        return $nonce !== '' && strlen($nonce) >= 10;
    }

    /**
     * Create a WordPress nonce.
     *
     * @param string $action Action name for the nonce
     * @return string The nonce value
     */
    public static function createNonce(string $action): string
    {
        if (function_exists('wp_create_nonce')) {
            return wp_create_nonce($action);
        }

        // Fallback: create a simple nonce when wp_create_nonce is not available
        return substr(hash_hmac('sha256', $action . microtime(true), self::getKey()), 0, 10);
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Support;

class Security
{
    private static bool $notice_hooked = false;

    public static function isEncryptionAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox');
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return $plain;
        }

        if (! self::isEncryptionAvailable()) {
            return $plain;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, self::getKey());

        return base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $encoded, ?bool &$failed = null): string
    {
        $failed = false;

        if ($encoded === '') {
            return $encoded;
        }

        if (! self::isEncryptionAvailable()) {
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
        $salt = wp_salt('fpdms');
        $hash = hash('sha256', $salt, true);

        return substr($hash, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}

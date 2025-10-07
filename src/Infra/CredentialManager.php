<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use RuntimeException;

class CredentialManager
{
    private string $key;

    public function __construct(?string $key = null)
    {
        $this->key = $key ?? ($_ENV['FPDMS_CREDENTIAL_KEY'] ?? '');
        if ($this->key === '' || strlen($this->key) < 32) {
            throw new RuntimeException('Invalid credential key. Provide 32+ chars in FPDMS_CREDENTIAL_KEY');
        }
        $this->key = substr(hash('sha256', $this->key, true), 0, 32);
    }

    public function encrypt(string $plaintext, array $aad = []): string
    {
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad ? json_encode($aad, JSON_UNESCAPED_SLASHES) : '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded, array $aad = []): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Invalid payload');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad ? json_encode($aad, JSON_UNESCAPED_SLASHES) : ''
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed');
        }

        return $plaintext;
    }
}



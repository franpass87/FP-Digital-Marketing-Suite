<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Wp;
use function __;
use function base64_encode;
use function function_exists;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;
use function openssl_free_key;
use function openssl_pkey_get_private;
use function openssl_sign;
use function rtrim;
use function strtr;
use function time;
use function trim;

class ServiceAccountHttpClient
{
    /** @var array<string, mixed> */
    private array $credentials;

    /**
     * @param array<string, mixed> $credentials
     */
    private function __construct(array $credentials)
    {
        $this->credentials = $credentials;
    }

    public static function fromJson(string $json): ?self
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        $clientEmail = isset($decoded['client_email']) ? trim((string) $decoded['client_email']) : '';
        $privateKey = isset($decoded['private_key']) ? (string) $decoded['private_key'] : '';

        if ($clientEmail === '' || trim($privateKey) === '') {
            return null;
        }

        return new self($decoded);
    }

    /**
     * @param string[] $scopes
     *
     * @return array{ok:bool,token?:string,message?:string,status?:int}
     */
    public function fetchAccessToken(array $scopes): array
    {
        if (! function_exists('openssl_sign')) {
            return [
                'ok' => false,
                'message' => __('The PHP OpenSSL extension is required to sign service account tokens.', 'fp-dms'),
                'status' => 0,
            ];
        }

        $assertion = $this->buildAssertion($scopes);
        if ($assertion === null) {
            return [
                'ok' => false,
                'message' => __('Unable to sign the service account assertion.', 'fp-dms'),
                'status' => 0,
            ];
        }

        $response = Wp::remotePost('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ],
        ]);

        if (Wp::isWpError($response)) {
            return [
                'ok' => false,
                'message' => Wp::wpErrorMessage($response),
                'status' => 0,
            ];
        }

        $status = Wp::remoteRetrieveResponseCode($response);
        $body = Wp::remoteRetrieveBody($response);
        $decoded = json_decode($body, true);

        if ($status !== 200 || ! is_array($decoded) || empty($decoded['access_token'])) {
            $message = '';
            if (is_array($decoded) && isset($decoded['error_description'])) {
                $message = (string) $decoded['error_description'];
            } elseif (is_array($decoded) && isset($decoded['error'])) {
                $message = (string) $decoded['error'];
            } else {
                $message = $body;
            }

            return [
                'ok' => false,
                'message' => trim($message),
                'status' => $status,
            ];
        }

        return [
            'ok' => true,
            'token' => (string) $decoded['access_token'],
            'status' => $status,
        ];
    }

    /**
     * @param string[] $scopes
     * @param array<string,mixed> $body
     *
     * @return array{ok:bool,status:int,body:string,json:array<string,mixed>|null,message:string}
     */
    public function postJson(string $url, array $body, array $scopes): array
    {
        $token = $this->fetchAccessToken($scopes);
        if (! $token['ok']) {
            return [
                'ok' => false,
                'status' => $token['status'] ?? 0,
                'body' => '',
                'json' => null,
                'message' => $token['message'] ?? '',
            ];
        }

        $response = Wp::remotePost($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token['token'],
                'Content-Type' => 'application/json',
            ],
            'body' => Wp::jsonEncode($body) ?: '{}',
        ]);

        if (Wp::isWpError($response)) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'json' => null,
                'message' => Wp::wpErrorMessage($response),
            ];
        }

        $status = Wp::remoteRetrieveResponseCode($response);
        $rawBody = Wp::remoteRetrieveBody($response);
        $decoded = json_decode($rawBody, true);

        $message = '';
        if ($status < 200 || $status >= 300) {
            if (is_array($decoded)) {
                $message = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? $rawBody);
            } else {
                $message = $rawBody;
            }
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $rawBody,
            'json' => is_array($decoded) ? $decoded : null,
            'message' => trim($message),
        ];
    }

    /**
     * @param string[] $scopes
     */
    private function buildAssertion(array $scopes): ?string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => (string) $this->credentials['client_email'],
            'scope' => implode(' ', $scopes),
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header) ?: ''),
            $this->base64UrlEncode(json_encode($payload) ?: ''),
        ];

        $input = implode('.', $segments);
        $privateKey = openssl_pkey_get_private((string) $this->credentials['private_key']);
        if ($privateKey === false) {
            return null;
        }

        $signature = '';
        $result = openssl_sign($input, $signature, $privateKey, 'sha256');
        openssl_free_key($privateKey);

        if (! $result) {
            return null;
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

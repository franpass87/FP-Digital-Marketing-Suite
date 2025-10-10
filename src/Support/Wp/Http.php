<?php

declare(strict_types=1);

namespace FP\DMS\Support\Wp;

/**
 * WordPress HTTP/Remote request functions with fallbacks.
 */
final class Http
{
    /**
     * @param array<string,mixed> $args
     */
    public static function post(string $url, array $args = []): mixed
    {
        if (\function_exists('wp_remote_post')) {
            return \wp_remote_post($url, $args);
        }

        return self::request('POST', $url, $args);
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function get(string $url, array $args = []): mixed
    {
        if (\function_exists('wp_remote_get')) {
            return \wp_remote_get($url, $args);
        }

        return self::request('GET', $url, $args);
    }

    public static function retrieveResponseCode(mixed $response): int
    {
        if (\function_exists('wp_remote_retrieve_response_code')) {
            return (int) \wp_remote_retrieve_response_code($response);
        }

        if (\is_array($response) && isset($response['response']) && \is_array($response['response'])) {
            $code = $response['response']['code'] ?? 0;

            if (\is_numeric($code)) {
                return (int) $code;
            }
        }

        return 0;
    }

    public static function retrieveBody(mixed $response): string
    {
        if (\function_exists('wp_remote_retrieve_body')) {
            $body = \wp_remote_retrieve_body($response);
            return \is_string($body) ? $body : '';
        }

        if (\is_array($response) && isset($response['body'])) {
            $body = $response['body'];
            return \is_string($body) ? $body : '';
        }

        return '';
    }

    public static function retrieveHeaders(mixed $response): array
    {
        if (\function_exists('wp_remote_retrieve_headers')) {
            $headers = \wp_remote_retrieve_headers($response);
            return \is_array($headers) ? $headers : [];
        }

        if (\is_array($response) && isset($response['headers']) && \is_array($response['headers'])) {
            return $response['headers'];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $args
     */
    private static function request(string $method, string $url, array $args): array|false
    {
        // Simple fallback using cURL
        $ch = \curl_init($url);

        if ($ch === false) {
            return false;
        }

        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // CRITICAL: Enable SSL verification to prevent MITM attacks
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // Set timeouts to prevent hanging
        \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        \curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (isset($args['body'])) {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
        }

        if (isset($args['headers']) && \is_array($args['headers'])) {
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);
        }

        // Handle timeout from args if provided
        if (isset($args['timeout']) && \is_numeric($args['timeout'])) {
            \curl_setopt($ch, CURLOPT_TIMEOUT, (int) $args['timeout']);
        }

        $body = \curl_exec($ch);
        $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        return [
            'body' => $body,
            'response' => ['code' => $code],
        ];
    }
}

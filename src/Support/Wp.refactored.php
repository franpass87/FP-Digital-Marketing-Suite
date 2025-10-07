<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use FP\DMS\Support\Wp\Sanitizers;
use FP\DMS\Support\Wp\Escapers;
use FP\DMS\Support\Wp\Validators;
use FP\DMS\Support\Wp\Http;
use FP\DMS\Support\Wp\Formatters;

/**
 * Refactored Wp facade with modular architecture.
 * 
 * Delegates to specialized modules:
 * - Sanitizers: Data sanitization
 * - Escapers: HTML/JS/URL escaping
 * - Validators: Input validation
 * - Http: Remote requests
 * - Formatters: Data formatting
 * 
 * This maintains backward compatibility while providing a clean, modular structure.
 */
final class WpRefactored
{
    // ==================== SANITIZERS ====================
    
    public static function absInt(mixed $value): int
    {
        return Sanitizers::absInt($value);
    }

    public static function sanitizeTextField(mixed $value): string
    {
        return Sanitizers::textField($value);
    }

    public static function sanitizeTitle(mixed $value): string
    {
        return Sanitizers::title($value);
    }

    public static function sanitizeKey(mixed $value): string
    {
        return Sanitizers::key($value);
    }

    public static function sanitizeEmail(mixed $value): string
    {
        return Sanitizers::email($value);
    }

    public static function sanitizeHexColor(mixed $value): string
    {
        return Sanitizers::hexColor($value);
    }

    public static function restSanitizeBoolean(mixed $value): bool
    {
        return Sanitizers::boolean($value);
    }

    // ==================== ESCAPERS ====================
    
    public static function escHtml(mixed $value): string
    {
        return Escapers::html($value);
    }

    public static function escAttr(mixed $value): string
    {
        return Escapers::attr($value);
    }

    public static function escTextarea(mixed $value): string
    {
        return Escapers::textarea($value);
    }

    public static function escJs(mixed $value): string
    {
        return Escapers::js($value);
    }

    public static function escUrl(mixed $value): string
    {
        return Escapers::url($value);
    }

    public static function escUrlRaw(mixed $value): string
    {
        return Escapers::urlRaw($value);
    }

    // ==================== VALIDATORS ====================
    
    public static function isEmail(mixed $value): bool
    {
        return Validators::isEmail($value);
    }

    public static function isWpError(mixed $value): bool
    {
        return Validators::isWpError($value);
    }

    // ==================== HTTP ====================
    
    /**
     * @param array<string,mixed> $args
     */
    public static function remotePost(string $url, array $args = []): mixed
    {
        return Http::post($url, $args);
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function remoteGet(string $url, array $args = []): mixed
    {
        return Http::get($url, $args);
    }

    public static function remoteRetrieveResponseCode(mixed $response): int
    {
        return Http::retrieveResponseCode($response);
    }

    public static function remoteRetrieveBody(mixed $response): string
    {
        return Http::retrieveBody($response);
    }

    public static function remoteRetrieveHeaders(mixed $response): array
    {
        return Http::retrieveHeaders($response);
    }

    // ==================== FORMATTERS ====================
    
    public static function numberFormatI18n(float $number, int $decimals = 0): string
    {
        return Formatters::numberI18n($number, $decimals);
    }

    public static function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return Formatters::jsonEncode($data, $options, $depth);
    }

    public static function unslash(mixed $value): mixed
    {
        return Formatters::unslash($value);
    }

    public static function ksesPost(mixed $value): string
    {
        return Formatters::ksesPost($value);
    }

    // ==================== ERROR HANDLING ====================
    
    public static function wpErrorMessage(mixed $value): string
    {
        if (\function_exists('is_wp_error') && \is_wp_error($value)) {
            $message = $value->get_error_message();
            return \is_string($message) ? \trim($message) : '';
        }

        if (!\is_array($value)) {
            return '';
        }

        if (isset($value['error_message']) && \is_string($value['error_message'])) {
            return \trim($value['error_message']);
        }

        if (isset($value['error']) && \is_string($value['error'])) {
            return \trim($value['error']);
        }

        return '';
    }
}
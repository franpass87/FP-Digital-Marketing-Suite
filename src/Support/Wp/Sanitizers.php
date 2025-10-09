<?php

declare(strict_types=1);

namespace FP\DMS\Support\Wp;

/**
 * WordPress sanitization functions with fallbacks.
 */
final class Sanitizers
{
    public static function absInt(mixed $value): int
    {
        if (\function_exists('absint')) {
            /** @var int $result */
            $result = \absint($value);
            return $result;
        }

        if (\is_int($value)) {
            return $value < 0 ? -$value : $value;
        }

        if (\is_float($value)) {
            return (int) \abs($value);
        }

        if (\is_numeric($value)) {
            return (int) \abs((float) $value);
        }

        return 0;
    }

    public static function textField(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('sanitize_text_field')) {
            return \sanitize_text_field($string);
        }

        $stripped = \strip_tags($string);
        $normalized = \preg_replace('/[\r\n\t]+/', ' ', $stripped);

        return \trim($normalized ?? $stripped);
    }

    public static function title(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('sanitize_title')) {
            return (string) \sanitize_title($string);
        }

        $lower = \strtolower(\strip_tags($string));
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $lower);

        return \trim($slug ?? '', '-');
    }

    public static function key(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('sanitize_key')) {
            return \sanitize_key($string);
        }

        $lower = \strtolower($string);
        $key = \preg_replace('/[^a-z0-9_\-]/', '', $lower);

        return $key ?? '';
    }

    public static function email(mixed $value): string
    {
        $email = (string) $value;

        if (\function_exists('sanitize_email')) {
            return \sanitize_email($email);
        }

        $email = \trim($email);
        $filtered = \filter_var($email, FILTER_SANITIZE_EMAIL);

        return \is_string($filtered) ? $filtered : '';
    }

    public static function hexColor(mixed $value): string
    {
        $color = (string) $value;

        if (\function_exists('sanitize_hex_color')) {
            $sanitized = \sanitize_hex_color($color);
            return $sanitized === null ? '' : $sanitized;
        }

        $color = \trim($color);

        if ($color === '') {
            return '';
        }

        if (!\preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color)) {
            return '';
        }

        return \strtolower($color);
    }

    public static function url(string $url, bool $encode = true): string
    {
        if (\function_exists('esc_url')) {
            return $encode ? \esc_url($url) : \esc_url_raw($url);
        }

        // Simple fallback
        $url = \trim($url);

        if (!\preg_match('/^https?:\/\//i', $url)) {
            return '';
        }

        return $encode ? \htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : $url;
    }

    public static function boolean(mixed $value): bool
    {
        if (\function_exists('rest_sanitize_boolean')) {
            /** @var bool $result */
            $result = \rest_sanitize_boolean($value);
            return $result;
        }

        $filtered = \filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered !== null) {
            return $filtered;
        }

        return (bool) $value;
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Support\Wp;

/**
 * WordPress formatting functions with fallbacks.
 */
final class Formatters
{
    public static function numberI18n(float $number, int $decimals = 0): string
    {
        if (\function_exists('number_format_i18n')) {
            return \number_format_i18n($number, $decimals);
        }

        $locale = \localeconv();
        $decimalPoint = '.';
        $thousandsSep = ',';

        if (\is_array($locale)) {
            if (isset($locale['decimal_point']) && \is_string($locale['decimal_point']) && $locale['decimal_point'] !== '') {
                $decimalPoint = $locale['decimal_point'];
            }

            if (isset($locale['thousands_sep']) && \is_string($locale['thousands_sep']) && $locale['thousands_sep'] !== '') {
                $thousandsSep = $locale['thousands_sep'];
            }
        }

        return \number_format($number, max(0, $decimals), $decimalPoint, $thousandsSep);
    }

    public static function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        if (\function_exists('wp_json_encode')) {
            return \wp_json_encode($data, $options, $depth);
        }

        $defaultOptions = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS;
        $encodeOptions = $options === 0 ? $defaultOptions : $options;

        return \json_encode($data, $encodeOptions, $depth);
    }

    public static function unslash(mixed $value): mixed
    {
        if (\function_exists('wp_unslash')) {
            return \wp_unslash($value);
        }

        if (\is_array($value)) {
            return \array_map([self::class, 'unslash'], $value);
        }

        if (\is_string($value)) {
            return \stripslashes($value);
        }

        return $value;
    }

    public static function ksesPost(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('wp_kses_post')) {
            return \wp_kses_post($string);
        }

        // Simple fallback: allow only safe HTML tags
        $allowed = '<a><b><strong><i><em><u><ul><ol><li><p><br><span><div>';
        return \strip_tags($string, $allowed);
    }
}

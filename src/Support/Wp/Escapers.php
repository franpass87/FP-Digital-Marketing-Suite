<?php

declare(strict_types=1);

namespace FP\DMS\Support\Wp;

/**
 * WordPress escaping functions with fallbacks.
 */
final class Escapers
{
    public static function html(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_html')) {
            return \esc_html($string);
        }

        return \htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function attr(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_attr')) {
            return \esc_attr($string);
        }

        return \htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function textarea(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_textarea')) {
            return \esc_textarea($string);
        }

        return \htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function js(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_js')) {
            return \esc_js($string);
        }

        $escaped = \addcslashes($string, "\"'\\\n\r\t\0\x0B");
        return \str_replace('</', '<\/', $escaped);
    }

    public static function url(mixed $value): string
    {
        $url = (string) $value;

        if (\function_exists('esc_url')) {
            return \esc_url($url);
        }

        return Sanitizers::url($url, true);
    }

    public static function urlRaw(mixed $value): string
    {
        $url = (string) $value;

        if (\function_exists('esc_url_raw')) {
            return \esc_url_raw($url);
        }

        return Sanitizers::url($url, false);
    }
}
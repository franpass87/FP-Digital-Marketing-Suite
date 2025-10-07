<?php

declare(strict_types=1);

namespace FP\DMS\Support\Wp;

/**
 * WordPress validation functions with fallbacks.
 */
final class Validators
{
    public static function isEmail(mixed $value): bool
    {
        $email = (string) $value;

        if (\function_exists('is_email')) {
            return \is_email($email) !== false;
        }

        $filtered = \filter_var($email, FILTER_VALIDATE_EMAIL);
        return $filtered !== false;
    }

    public static function isWpError(mixed $value): bool
    {
        if (\function_exists('is_wp_error')) {
            return \is_wp_error($value);
        }

        return \is_array($value) && isset($value['error']);
    }

    public static function isUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $filtered = \filter_var($url, FILTER_VALIDATE_URL);
        return $filtered !== false;
    }

    public static function isHexColor(string $color): bool
    {
        return \preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color) === 1;
    }
}
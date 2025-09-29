<?php

declare(strict_types=1);

namespace FP\DMS\Support;

class Validation
{
    public static function isHexColor(string $value): bool
    {
        return (bool) preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value);
    }

    /**
     * @param array<int, string> $emails
     */
    public static function isEmailList(array $emails): bool
    {
        foreach ($emails as $email) {
            if (! is_string($email) || ! is_email($email)) {
                return false;
            }
        }

        return true;
    }

    public static function nonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    public static function positiveInt(mixed $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    public static function safeUrl(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $sanitized = esc_url_raw($value);

        return $sanitized !== '' && filter_var($sanitized, FILTER_VALIDATE_URL) !== false;
    }
}

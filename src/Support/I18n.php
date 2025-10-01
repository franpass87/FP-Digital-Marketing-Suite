<?php

declare(strict_types=1);

namespace FP\DMS\Support;

class I18n
{
    public const TEXTDOMAIN = 'fp-dms';

    public static function __(string $text, string $domain = self::TEXTDOMAIN): string
    {
        if (\function_exists('__')) {
            return \__($text, $domain);
        }

        return $text;
    }

    public static function _x(string $text, string $context, string $domain = self::TEXTDOMAIN): string
    {
        if (\function_exists('_x')) {
            return \_x($text, $context, $domain);
        }

        return $text;
    }
}

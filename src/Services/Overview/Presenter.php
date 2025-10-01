<?php

declare(strict_types=1);

namespace FP\DMS\Services\Overview;

use function __;
use function abs;
use function number_format_i18n;
use function sprintf;

class Presenter
{
    public static function formatNumber(float $value, int $precision = 0): string
    {
        if (abs($value) >= 1000 && $precision === 0) {
            $precision = 1;
        }

        return number_format_i18n($value, $precision);
    }

    /**
     * @return array{raw: float, formatted: string, direction: 'up'|'down'|'flat'}
     */
    public static function formatDelta(float $current, float $previous, int $precision = 1): array
    {
        if ($previous === 0.0) {
            if ($current === 0.0) {
                $raw = 0.0;
            } else {
                $raw = $current > 0 ? 100.0 : -100.0;
            }
        } else {
            $raw = (($current - $previous) / abs($previous)) * 100;
        }

        $direction = $raw > 0 ? 'up' : ($raw < 0 ? 'down' : 'flat');
        $sign = '';
        if ($raw > 0) {
            $sign = '+';
        } elseif ($raw < 0) {
            $sign = '-';
        }

        $formatted = sprintf('%s%s%%', $sign, number_format_i18n(abs($raw), $precision));

        return [
            'raw' => $raw,
            'formatted' => $formatted,
            'direction' => $direction,
        ];
    }

    /**
     * @return array{variant: string, label: string}
     */
    public static function severityBadge(float $score): array
    {
        $variant = 'neutral';
        $label = __('Info', 'fp-dms');

        if ($score >= 7.5) {
            $variant = 'critical';
            $label = __('Critical', 'fp-dms');
        } elseif ($score >= 4.0) {
            $variant = 'warning';
            $label = __('Warning', 'fp-dms');
        } elseif ($score >= 1.5) {
            $variant = 'info';
            $label = __('Notice', 'fp-dms');
        }

        return [
            'variant' => $variant,
            'label' => $label,
        ];
    }
}

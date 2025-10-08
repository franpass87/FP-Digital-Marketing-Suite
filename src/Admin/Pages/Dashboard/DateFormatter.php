<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Dashboard;

use DateTimeImmutable;
use Exception;
use FP\DMS\Support\Wp;
use function __;
use function is_int;
use function strtotime;

/**
 * Formats dates and time ranges for dashboard display
 */
class DateFormatter
{
    /**
     * Format a datetime string for display
     */
    public static function dateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return __('Not available', 'fp-dms');
        }

        try {
            $date = new DateTimeImmutable($value, Wp::timezone());
            $timestamp = $date->getTimestamp();
        } catch (Exception) {
            $timestamp = strtotime($value);
            if (!is_int($timestamp)) {
                return $value;
            }
        }

        return Wp::date('M j, Y g:i a', $timestamp);
    }

    /**
     * Format a date range (start-end) for display
     */
    public static function dateRange(?string $start, ?string $end): string
    {
        if ($start === null || $start === '' || $end === null || $end === '') {
            return '';
        }

        $startTs = strtotime($start . ' 00:00:00');
        $endTs = strtotime($end . ' 00:00:00');

        if (!is_int($startTs) || !is_int($endTs)) {
            return '';
        }

        $startLabel = Wp::date('M j, Y', $startTs);
        $endLabel = Wp::date('M j, Y', $endTs);

        if ($startLabel === $endLabel) {
            return $startLabel;
        }

        return $startLabel . ' – ' . $endLabel;
    }

    /**
     * Format a frequency string for display
     */
    public static function frequency(string $frequency): string
    {
        $normalized = \strtolower($frequency);

        return match ($normalized) {
            'hourly' => __('Hourly', 'fp-dms'),
            'daily' => __('Daily', 'fp-dms'),
            'weekly' => __('Weekly', 'fp-dms'),
            'monthly' => __('Monthly', 'fp-dms'),
            'quarterly' => __('Quarterly', 'fp-dms'),
            'yearly' => __('Yearly', 'fp-dms'),
            default => \ucwords(\str_replace('_', ' ', $frequency)),
        };
    }

    /**
     * Humanize a type string (replace underscores with spaces and capitalize)
     */
    public static function humanizeType(string $type): string
    {
        if ($type === '') {
            return __('Unknown anomaly', 'fp-dms');
        }

        $normalized = \str_replace('_', ' ', $type);
        return \ucwords($normalized);
    }
}
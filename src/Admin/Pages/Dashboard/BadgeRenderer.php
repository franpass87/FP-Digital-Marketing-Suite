<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Dashboard;

use function __;

/**
 * Renders badges for various statuses and severities
 */
class BadgeRenderer
{
    /**
     * Render a status badge for reports
     */
    public static function reportStatus(string $status): string
    {
        $label = self::reportStatusLabel($status);
        $class = self::statusBadgeClass($status);
        
        return sprintf(
            '<span class="fpdms-badge %s">%s</span>',
            \esc_attr($class),
            \esc_html($label)
        );
    }

    /**
     * Render a severity badge for anomalies
     */
    public static function anomalySeverity(string $severity): string
    {
        $label = self::anomalySeverityLabel($severity);
        $class = self::severityBadgeClass($severity);
        
        return sprintf(
            '<span class="fpdms-badge %s">%s</span>',
            \esc_attr($class),
            \esc_html($label)
        );
    }

    /**
     * Get human-readable label for report status
     */
    private static function reportStatusLabel(string $status): string
    {
        return match (\strtolower($status)) {
            'completed' => __('Completed', 'fp-dms'),
            'running' => __('Running', 'fp-dms'),
            'failed' => __('Failed', 'fp-dms'),
            'cancelled' => __('Cancelled', 'fp-dms'),
            default => __('Queued', 'fp-dms'),
        };
    }

    /**
     * Get CSS class for status badge
     */
    private static function statusBadgeClass(string $status): string
    {
        return match (\strtolower($status)) {
            'completed' => 'fpdms-badge-success',
            'running' => 'fpdms-badge-warning',
            'failed', 'cancelled' => 'fpdms-badge-danger',
            default => 'fpdms-badge-neutral',
        };
    }

    /**
     * Get human-readable label for anomaly severity
     */
    private static function anomalySeverityLabel(string $severity): string
    {
        return match (\strtolower($severity)) {
            'critical', 'error' => __('Critical', 'fp-dms'),
            'warning' => __('Warning', 'fp-dms'),
            'notice' => __('Notice', 'fp-dms'),
            default => __('Info', 'fp-dms'),
        };
    }

    /**
     * Get CSS class for severity badge
     */
    private static function severityBadgeClass(string $severity): string
    {
        return match (\strtolower($severity)) {
            'critical', 'error' => 'fpdms-badge-danger',
            'warning' => 'fpdms-badge-warning',
            'notice' => 'fpdms-badge-info',
            default => 'fpdms-badge-neutral',
        };
    }
}
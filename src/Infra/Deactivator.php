<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

class Deactivator
{
    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled('fpdms_cron_tick');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fpdms_cron_tick');
        }

        $retention = wp_next_scheduled('fpdms_retention_cleanup');
        if ($retention) {
            wp_unschedule_event($retention, 'fpdms_retention_cleanup');
        }
    }
}

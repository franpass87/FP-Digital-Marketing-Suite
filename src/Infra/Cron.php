<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

class Cron
{
    public static function bootstrap(): void
    {
        add_filter('cron_schedules', [self::class, 'registerInterval']);
        add_action('fpdms_retention_cleanup', ['FP\\DMS\\Infra\\Retention', 'cleanup']);
    }

    public static function registerInterval(array $schedules): array
    {
        if (! isset($schedules['fpdms_5min'])) {
            $schedules['fpdms_5min'] = [
                'interval' => 300,
                'display' => __('Every 5 Minutes', 'fp-dms'),
            ];
        }

        return $schedules;
    }
}

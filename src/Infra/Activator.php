<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Domain\Repos\TemplatesRepo;

class Activator
{
    public static function activate(): void
    {
        DB::migrate();
        Options::ensureDefaults();

        if (! wp_next_scheduled('fpdms_cron_tick')) {
            wp_schedule_event(time() + 60, 'fpdms_5min', 'fpdms_cron_tick');
        }

        if (! wp_next_scheduled('fpdms_retention_cleanup')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'fpdms_retention_cleanup');
        }

        self::ensureDefaultTemplate();
    }

    private static function ensureDefaultTemplate(): void
    {
        $templates = new TemplatesRepo();
        if ($templates->findDefault()) {
            return;
        }

        $templates->create([
            'name' => __('Default Report', 'fp-dms'),
            'description' => __('Automatically generated default layout.', 'fp-dms'),
            'content' => '<div class="kpi-grid">'
                . '<div class="kpi"><strong>Users</strong><div>{{kpi.ga4.users|number}}</div></div>'
                . '<div class="kpi"><strong>Sessions</strong><div>{{kpi.ga4.sessions|number}}</div></div>'
                . '<div class="kpi"><strong>Clicks</strong><div>{{kpi.google_ads.clicks|number}}</div></div>'
                . '<div class="kpi"><strong>Impressions</strong><div>{{kpi.gsc.impressions|number}}</div></div>'
                . '</div>',
            'is_default' => 1,
        ]);
    }
}

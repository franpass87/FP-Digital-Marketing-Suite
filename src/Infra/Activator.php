<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Domain\Templates\TemplateBlueprints;
use FP\DMS\Infra\Migrations\AddClientDescriptionColumn;
use FP\DMS\Support\Wp;

class Activator
{
    public static function activate(): void
    {
        DB::migrate();
        DB::migrateReportsReview(); // Add review fields to reports table
        AddClientDescriptionColumn::run(); // Add description field to clients table
        Options::ensureDefaults();

        if (! wp_next_scheduled('fpdms_cron_tick')) {
            wp_schedule_event(time() + 60, 'fpdms_5min', 'fpdms_cron_tick');
        }

        if (! wp_next_scheduled('fpdms_retention_cleanup')) {
            wp_schedule_event(time() + Wp::dayInSeconds(), 'daily', 'fpdms_retention_cleanup');
        }

        self::ensureDefaultTemplate();
    }

    private static function ensureDefaultTemplate(): void
    {
        $templates = new TemplatesRepo();
        if ($templates->findDefault()) {
            return;
        }

        $templates->create(TemplateBlueprints::defaultDraft());
    }
}

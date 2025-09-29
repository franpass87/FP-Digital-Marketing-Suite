<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Support\Security;

class Options
{
    private const GLOBAL_SETTINGS = 'fpdms_global_settings';
    private const LAST_TICK = 'fpdms_last_tick_at';
    private const QA_KEY = 'fpdms_qa_key';

    public static function getGlobalSettings(): array
    {
        $value = get_option(self::GLOBAL_SETTINGS, []);
        if (! is_array($value)) {
            $value = [];
        }

        $settings = array_replace_recursive(self::defaultGlobalSettings(), $value);

        if (isset($settings['mail']['smtp']['pass']) && is_string($settings['mail']['smtp']['pass'])) {
            $settings['mail']['smtp']['pass'] = Security::decrypt($settings['mail']['smtp']['pass']);
        }

        return $settings;
    }

    public static function updateGlobalSettings(array $settings): void
    {
        $merged = array_replace_recursive(self::defaultGlobalSettings(), $settings);

        if (isset($merged['mail']['smtp']['pass']) && is_string($merged['mail']['smtp']['pass'])) {
            $merged['mail']['smtp']['pass'] = Security::encrypt($merged['mail']['smtp']['pass']);
        }
        update_option(self::GLOBAL_SETTINGS, $merged, false);
    }

    public static function ensureDefaults(): void
    {
        $settings = self::getGlobalSettings();
        if (empty($settings['tick_key'])) {
            $settings['tick_key'] = wp_generate_password(32, false, false);
            self::updateGlobalSettings($settings);
        }

        self::getQaKey();
    }

    public static function getLastTick(): int
    {
        return (int) get_option(self::LAST_TICK, 0);
    }

    public static function setLastTick(int $timestamp): void
    {
        update_option(self::LAST_TICK, $timestamp, false);
    }

    public static function getQaKey(): string
    {
        $key = (string) get_option(self::QA_KEY, '');
        if ($key !== '') {
            return $key;
        }

        return self::regenerateQaKey();
    }

    public static function regenerateQaKey(): string
    {
        $key = wp_generate_password(40, false, false);
        update_option(self::QA_KEY, $key, false);

        return $key;
    }

    public static function defaultGlobalSettings(): array
    {
        return [
            'owner_email' => '',
            'pdf_branding' => [
                'logo_url' => '',
                'primary_color' => '#1d4ed8',
                'footer_text' => '',
            ],
            'mail' => [
                'smtp' => [
                    'host' => '',
                    'port' => '',
                    'secure' => 'none',
                    'user' => '',
                    'pass' => '',
                ],
            ],
            'retention_days' => 90,
            'error_webhook_url' => '',
            'tick_key' => '',
        ];
    }
}

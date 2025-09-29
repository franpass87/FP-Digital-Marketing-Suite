<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Support\Security;

class Options
{
    private const GLOBAL_SETTINGS = 'fpdms_global_settings';
    private const LAST_TICK = 'fpdms_last_tick_at';
    private const QA_KEY = 'fpdms_qa_key';
    private const ANOMALY_POLICY_PREFIX = 'fpdms_anomaly_policy_';

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

        $settings['anomaly_policy'] = self::normaliseAnomalyPolicy($settings['anomaly_policy'] ?? []);
        $settings['anomaly_policy']['routing'] = self::decryptRouting($settings['anomaly_policy']['routing']);

        return $settings;
    }

    public static function updateGlobalSettings(array $settings): void
    {
        $merged = array_replace_recursive(self::defaultGlobalSettings(), $settings);

        if (isset($merged['mail']['smtp']['pass']) && is_string($merged['mail']['smtp']['pass'])) {
            $merged['mail']['smtp']['pass'] = Security::encrypt($merged['mail']['smtp']['pass']);
        }

        $merged['anomaly_policy'] = self::prepareAnomalyPolicyForStorage($merged['anomaly_policy'] ?? []);

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
            'anomaly_policy' => self::defaultAnomalyPolicy(),
        ];
    }

    public static function defaultAnomalyPolicy(): array
    {
        return [
            'metrics' => [
                'users' => ['warn_pct' => 20.0, 'crit_pct' => 40.0, 'z_warn' => 1.5, 'z_crit' => 3.0],
                'sessions' => ['warn_pct' => 20.0, 'crit_pct' => 40.0, 'z_warn' => 1.5, 'z_crit' => 3.0],
                'clicks' => ['warn_pct' => 25.0, 'crit_pct' => 50.0, 'z_warn' => 2.0, 'z_crit' => 3.0],
                'conversions' => ['warn_pct' => 30.0, 'crit_pct' => 60.0, 'z_warn' => 2.0, 'z_crit' => 3.0],
                'spend' => ['warn_pct' => 30.0, 'crit_pct' => 60.0, 'z_warn' => 2.0, 'z_crit' => 3.0],
                'cost' => ['warn_pct' => 30.0, 'crit_pct' => 60.0, 'z_warn' => 2.0, 'z_crit' => 3.0],
                'revenue' => ['warn_pct' => 30.0, 'crit_pct' => 60.0, 'z_warn' => 2.0, 'z_crit' => 3.0],
            ],
            'baseline' => [
                'window_days' => 28,
                'seasonality' => 'dow',
                'ewma_alpha' => 0.3,
                'cusum_k' => 0.5,
                'cusum_h' => 5.0,
            ],
            'mute' => ['start' => '22:00', 'end' => '07:00', 'tz' => 'Europe/Rome'],
            'routing' => [
                'email' => ['enabled' => true, 'digest_window_min' => 15],
                'slack' => ['enabled' => true, 'webhook_url' => ''],
                'teams' => ['enabled' => false, 'webhook_url' => ''],
                'telegram' => ['enabled' => false, 'bot_token' => '', 'chat_id' => ''],
                'webhook' => ['enabled' => true, 'url' => '', 'hmac_secret' => ''],
                'sms_twilio' => ['enabled' => false, 'sid' => '', 'token' => '', 'from' => '', 'to' => ''],
            ],
            'cooldown_min' => 30,
            'max_per_window' => 5,
        ];
    }

    public static function getAnomalyPolicy(int $clientId): array
    {
        $global = self::getGlobalSettings()['anomaly_policy'];
        if ($clientId <= 0) {
            return $global;
        }

        $stored = get_option(self::ANOMALY_POLICY_PREFIX . $clientId, []);
        if (! is_array($stored) || empty($stored)) {
            return $global;
        }

        $stored['routing'] = self::decryptRouting($stored['routing'] ?? []);
        $policy = array_replace_recursive($global, $stored);

        return self::normaliseAnomalyPolicy($policy);
    }

    public static function updateAnomalyPolicy(int $clientId, array $policy): void
    {
        $normalized = self::prepareAnomalyPolicyForStorage($policy);
        update_option(self::ANOMALY_POLICY_PREFIX . $clientId, $normalized, false);
    }

    public static function deleteAnomalyPolicy(int $clientId): void
    {
        delete_option(self::ANOMALY_POLICY_PREFIX . $clientId);
    }

    /**
     * @param array<string,mixed> $policy
     * @return array<string,mixed>
     */
    private static function normaliseAnomalyPolicy(array $policy): array
    {
        $base = self::defaultAnomalyPolicy();

        return array_replace_recursive($base, $policy);
    }

    /**
     * @param array<string,mixed> $policy
     * @return array<string,mixed>
     */
    private static function prepareAnomalyPolicyForStorage(array $policy): array
    {
        $normalised = self::normaliseAnomalyPolicy($policy);
        $normalised['routing'] = self::encryptRouting($normalised['routing']);

        return $normalised;
    }

    /**
     * @param array<string,mixed> $routing
     * @return array<string,mixed>
     */
    private static function encryptRouting(array $routing): array
    {
        foreach (self::secretRoutingFields() as $channel => $fields) {
            if (! isset($routing[$channel]) || ! is_array($routing[$channel])) {
                continue;
            }
            foreach ($fields as $field) {
                if (! isset($routing[$channel][$field]) || ! is_string($routing[$channel][$field])) {
                    continue;
                }
                $value = trim((string) $routing[$channel][$field]);
                if ($value === '') {
                    continue;
                }
                $routing[$channel][$field] = Security::encrypt($value);
            }
        }

        return $routing;
    }

    /**
     * @param array<string,mixed> $routing
     * @return array<string,mixed>
     */
    private static function decryptRouting(array $routing): array
    {
        foreach (self::secretRoutingFields() as $channel => $fields) {
            if (! isset($routing[$channel]) || ! is_array($routing[$channel])) {
                continue;
            }
            foreach ($fields as $field) {
                if (! isset($routing[$channel][$field]) || ! is_string($routing[$channel][$field])) {
                    continue;
                }
                $routing[$channel][$field] = Security::decrypt((string) $routing[$channel][$field]);
            }
        }

        return $routing;
    }

    /**
     * @return array<string,string[]>
     */
    private static function secretRoutingFields(): array
    {
        return [
            'slack' => ['webhook_url'],
            'teams' => ['webhook_url'],
            'telegram' => ['bot_token'],
            'webhook' => ['url', 'hmac_secret'],
            'sms_twilio' => ['sid', 'token', 'from', 'to'],
        ];
    }
}

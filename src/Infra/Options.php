<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use DateTimeZone;
use Exception;
use FP\DMS\Support\Security;
use FP\DMS\Support\Wp;

class Options
{
    private const GLOBAL_SETTINGS = 'fpdms_global_settings';
    private const LAST_TICK = 'fpdms_last_tick_at';
    private const QA_KEY = 'fpdms_qa_key';
    private const ANOMALY_POLICY_PREFIX = 'fpdms_anomaly_policy_';
    private const SMTP_SECURE_MODES = ['none', 'ssl', 'tls'];

    public static function getGlobalSettings(): array
    {
        $value = get_option(self::GLOBAL_SETTINGS, []);
        if (! is_array($value)) {
            $value = [];
        }

        // Safe merge: only merge if value is a proper array to prevent type confusion
        $settings = self::safeMergeRecursive(self::defaultGlobalSettings(), $value);

        if (isset($settings['mail']['smtp']['pass']) && is_string($settings['mail']['smtp']['pass'])) {
            $cipher = $settings['mail']['smtp']['pass'];
            $failed = false;
            $plain = Security::decrypt($cipher, $failed);

            $settings['mail']['smtp']['pass_cipher'] = $cipher;
            $settings['mail']['smtp']['pass'] = $failed ? '' : $plain;
        } else {
            $settings['mail']['smtp']['pass'] = '';
            $settings['mail']['smtp']['pass_cipher'] = '';
        }

        if (isset($settings['mail']['smtp'])) {
            $settings['mail']['smtp']['secure'] = self::normaliseSmtpSecure($settings['mail']['smtp']['secure'] ?? 'none');
            $port = isset($settings['mail']['smtp']['port']) ? (int) $settings['mail']['smtp']['port'] : self::defaultGlobalSettings()['mail']['smtp']['port'];
            if ($port < 1 || $port > 65535) {
                $port = self::defaultGlobalSettings()['mail']['smtp']['port'];
            }
            $settings['mail']['smtp']['port'] = $port;
        }

        if (! isset($settings['overview']) || ! is_array($settings['overview'])) {
            $settings['overview'] = self::defaultGlobalSettings()['overview'];
        }

        $refresh = $settings['overview']['refresh_intervals'] ?? [];
        if (! is_array($refresh)) {
            $refresh = self::defaultGlobalSettings()['overview']['refresh_intervals'];
        }

        $refresh = array_values(array_filter(array_map([Wp::class, 'absInt'], $refresh), static fn(int $value): bool => $value > 0));
        if ($refresh === []) {
            $refresh = self::defaultGlobalSettings()['overview']['refresh_intervals'];
        }
        sort($refresh);
        $settings['overview']['refresh_intervals'] = $refresh;

        $policySource = is_array($settings['anomaly_policy'] ?? null) ? $settings['anomaly_policy'] : [];
        $policyResult = self::sanitizeAnomalyPolicyInput($policySource, self::defaultAnomalyPolicy());
        $settings['anomaly_policy'] = $policyResult['policy'];
        $settings['anomaly_policy']['routing'] = self::decryptRouting($settings['anomaly_policy']['routing']);

        return $settings;
    }

    public static function updateGlobalSettings(array $settings): void
    {
        $merged = self::safeMergeRecursive(self::defaultGlobalSettings(), $settings);

        if (isset($merged['mail']['smtp'])) {
            $merged['mail']['smtp']['secure'] = self::normaliseSmtpSecure($merged['mail']['smtp']['secure'] ?? 'none');
            $port = isset($merged['mail']['smtp']['port']) ? (int) $merged['mail']['smtp']['port'] : self::defaultGlobalSettings()['mail']['smtp']['port'];
            if ($port < 1 || $port > 65535) {
                $port = self::defaultGlobalSettings()['mail']['smtp']['port'];
            }
            $merged['mail']['smtp']['port'] = $port;

            $cipher = is_string($merged['mail']['smtp']['pass_cipher'] ?? null)
                ? (string) $merged['mail']['smtp']['pass_cipher']
                : '';

            if (isset($merged['mail']['smtp']['pass']) && is_string($merged['mail']['smtp']['pass']) && $merged['mail']['smtp']['pass'] !== '') {
                $merged['mail']['smtp']['pass'] = Security::encrypt($merged['mail']['smtp']['pass']);
            } elseif ($cipher !== '') {
                $merged['mail']['smtp']['pass'] = $cipher;
            } else {
                $merged['mail']['smtp']['pass'] = '';
            }

            unset($merged['mail']['smtp']['pass_cipher']);
        }

        if (isset($merged['overview']['refresh_intervals'])) {
            $intervals = $merged['overview']['refresh_intervals'];
            if (! is_array($intervals)) {
                $intervals = self::defaultGlobalSettings()['overview']['refresh_intervals'];
            }

            $intervals = array_values(array_filter(array_map([Wp::class, 'absInt'], $intervals), static fn(int $value): bool => $value > 0));
            if ($intervals === []) {
                $intervals = self::defaultGlobalSettings()['overview']['refresh_intervals'];
            }

            sort($intervals);
            $merged['overview']['refresh_intervals'] = $intervals;
        }

        $policySource = is_array($merged['anomaly_policy'] ?? null) ? $merged['anomaly_policy'] : [];
        $policyResult = self::sanitizeAnomalyPolicyInput($policySource, self::defaultAnomalyPolicy());
        $merged['anomaly_policy'] = self::prepareAnomalyPolicyForStorage($policyResult['policy']);

        update_option(self::GLOBAL_SETTINGS, $merged, false);
    }

    private static function normaliseSmtpSecure(mixed $value): string
    {
        if (! is_string($value)) {
            return 'none';
        }

        $normalized = strtolower($value);

        return in_array($normalized, self::SMTP_SECURE_MODES, true) ? $normalized : 'none';
    }

    public static function ensureDefaults(): void
    {
        $settings = self::getGlobalSettings();
        if (empty($settings['tick_key'])) {
            $settings['tick_key'] = Wp::generatePassword(32, false, false);
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
        $key = Wp::generatePassword(40, false, false);
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
                    'port' => 587,
                    'secure' => 'none',
                    'user' => '',
                    'pass' => '',
                ],
            ],
            'retention_days' => 90,
            'error_webhook_url' => '',
            'tick_key' => '',
            'overview' => [
                'refresh_intervals' => [60, 120],
            ],
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
                'sms_twilio' => [
                    'enabled' => false,
                    'sid' => '',
                    'token' => '',
                    'from' => '',
                    'to' => '',
                    'messaging_service_sid' => '',
                    'status_callback' => '',
                ],
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
        $policy = self::safeMergeRecursive($global, $stored);
        $result = self::sanitizeAnomalyPolicyInput($policy, $global);

        return $result['policy'];
    }

    public static function updateAnomalyPolicy(int $clientId, array $policy): void
    {
        $current = $clientId > 0 ? self::getAnomalyPolicy($clientId) : self::defaultAnomalyPolicy();
        $result = self::sanitizeAnomalyPolicyInput($policy, $current);
        $normalized = self::prepareAnomalyPolicyForStorage($result['policy']);
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

        return self::safeMergeRecursive($base, $policy);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $current
     * @return array{policy: array<string,mixed>, errors: array<string,bool>}
     */
    public static function sanitizeAnomalyPolicyInput(array $input, array $current): array
    {
        $policy = self::normaliseAnomalyPolicy($current);
        $errors = [];

        if (isset($input['metrics']) && is_array($input['metrics'])) {
            foreach ($policy['metrics'] as $metric => &$values) {
                $source = $input['metrics'][$metric] ?? [];
                foreach (['warn_pct', 'crit_pct', 'z_warn', 'z_crit'] as $field) {
                    if (! isset($source[$field]) || ! is_numeric($source[$field])) {
                        continue;
                    }

                    $value = (float) $source[$field];
                    $values[$field] = max(0.0, $value);
                }
            }
            unset($values);
        }

        if (isset($input['baseline']) && is_array($input['baseline'])) {
            $baseline = $input['baseline'];
            if (isset($baseline['window_days'])) {
                $policy['baseline']['window_days'] = max(1, (int) $baseline['window_days']);
            }
            if (isset($baseline['seasonality'])) {
                $policy['baseline']['seasonality'] = Wp::sanitizeTextField($baseline['seasonality']);
            }
            if (isset($baseline['ewma_alpha']) && is_numeric($baseline['ewma_alpha'])) {
                $alpha = (float) $baseline['ewma_alpha'];
                $policy['baseline']['ewma_alpha'] = max(0.0, min(1.0, $alpha));
            }
            if (isset($baseline['cusum_k']) && is_numeric($baseline['cusum_k'])) {
                $policy['baseline']['cusum_k'] = max(0.0, (float) $baseline['cusum_k']);
            }
            if (isset($baseline['cusum_h']) && is_numeric($baseline['cusum_h'])) {
                $policy['baseline']['cusum_h'] = max(0.0, (float) $baseline['cusum_h']);
            }
        }

        $muteInput = isset($input['mute']) && is_array($input['mute']) ? $input['mute'] : [];
        if (isset($muteInput['start'])) {
            $policy['mute']['start'] = Wp::sanitizeTextField($muteInput['start']);
        }
        if (isset($muteInput['end'])) {
            $policy['mute']['end'] = Wp::sanitizeTextField($muteInput['end']);
        }
        $timezoneFallback = $policy['mute']['tz'] ?? Wp::timezoneString();
        if (isset($muteInput['tz'])) {
            $candidate = Wp::sanitizeTextField($muteInput['tz']);
            if ($candidate === '') {
                $policy['mute']['tz'] = $timezoneFallback;
            } else {
                try {
                    new DateTimeZone($candidate);
                    $policy['mute']['tz'] = $candidate;
                } catch (Exception $exception) {
                    $policy['mute']['tz'] = $timezoneFallback;
                    $errors['invalid_mute_timezone'] = true;
                }
            }
        }

        if (isset($input['routing']) && is_array($input['routing'])) {
            foreach ($policy['routing'] as $channel => &$config) {
                $source = isset($input['routing'][$channel]) && is_array($input['routing'][$channel])
                    ? $input['routing'][$channel]
                    : [];
                $config['enabled'] = ! empty($source['enabled']);
                foreach ($config as $key => &$value) {
                    if ($key === 'enabled' || ! isset($source[$key])) {
                        continue;
                    }
                    $raw = (string) $source[$key];
                    $lowerKey = strtolower($key);
                    if (str_contains($lowerKey, 'url') || str_contains($lowerKey, 'callback')) {
                        $value = Wp::escUrlRaw($raw);
                    } elseif (
                        str_contains($lowerKey, 'token') ||
                        str_contains($lowerKey, 'secret') ||
                        str_contains($lowerKey, 'pass') ||
                        str_contains($lowerKey, 'key') ||
                        str_contains($lowerKey, 'sid')
                    ) {
                        $value = trim($raw);
                    } else {
                        $value = Wp::sanitizeTextField($raw);
                    }
                }
                unset($value);
            }
            unset($config);
        }

        if (isset($input['routing']['email']['digest_window_min'])) {
            $policy['routing']['email']['digest_window_min'] = max(1, (int) $input['routing']['email']['digest_window_min']);
        }

        if (isset($input['cooldown_min'])) {
            $policy['cooldown_min'] = max(1, (int) $input['cooldown_min']);
        }
        if (isset($input['max_per_window'])) {
            $policy['max_per_window'] = max(0, (int) $input['max_per_window']);
        }

        return ['policy' => $policy, 'errors' => $errors];
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

                try {
                    $routing[$channel][$field] = Security::encrypt($value);
                } catch (\RuntimeException $e) {
                    // Log encryption failure and keep original value
                    error_log('[FPDMS] Encryption failed for routing field: ' . $e->getMessage());
                    $routing[$channel][$field] = '';
                }
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
                $failed = false;
                $decrypted = Security::decrypt((string) $routing[$channel][$field], $failed);
                $routing[$channel][$field] = $failed ? '' : $decrypted;
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
            'sms_twilio' => ['sid', 'token', 'from', 'to', 'messaging_service_sid'],
        ];
    }

    /**
     * Safe recursive merge that prevents type confusion.
     * Only merges arrays with arrays, scalars override previous values.
     *
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private static function safeMergeRecursive(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            // If both are arrays, merge recursively
            if (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                $result[$key] = self::safeMergeRecursive($result[$key], $value);
            } else {
                // Otherwise, override (prevents type confusion)
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

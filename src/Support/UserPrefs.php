<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use DateTimeImmutable;
use FP\DMS\Http\OverviewRoutes;
use FP\DMS\Infra\Options;

use function abs;
use function absint;
use function array_filter;
use function array_map;
use function array_values;
use function get_current_user_id;
use function get_user_meta;
use function is_array;
use function is_numeric;
use function sanitize_key;
use function sort;
use function trim;
use function update_user_meta;

final class UserPrefs
{
    private const META_KEY = '_fpdms_prefs';

    /**
     * @return array<string, mixed>
     */
    public static function getOverviewPreferences(): array
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return [];
        }

        $stored = get_user_meta($userId, self::META_KEY, true);
        if (! is_array($stored)) {
            return [];
        }

        $prefs = $stored['overview'] ?? [];

        return is_array($prefs) ? self::sanitizeOverviewPreferences($prefs) : [];
    }

    public static function rememberOverviewPreferences(
        int $clientId,
        string $preset,
        string $from,
        string $to,
        bool $autoRefresh,
        int $refreshInterval
    ): void {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $meta = get_user_meta($userId, self::META_KEY, true);
        if (! is_array($meta)) {
            $meta = [];
        }

        $meta['overview'] = [
            'client_id' => absint($clientId),
            'preset' => self::normalizePreset(sanitize_key($preset)),
            'from' => self::sanitizeDate($from),
            'to' => self::sanitizeDate($to),
            'auto_refresh' => $autoRefresh,
            'refresh_interval' => self::sanitizeInterval($refreshInterval),
        ];

        update_user_meta($userId, self::META_KEY, $meta);
    }

    private static function sanitizeDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed ? $parsed->format('Y-m-d') : '';
    }

    private static function sanitizeInterval(int $seconds): int
    {
        $seconds = absint($seconds);
        $allowed = self::allowedRefreshIntervals();

        if ($allowed === []) {
            if ($seconds < 30) {
                $seconds = 30;
            }

            if ($seconds > 3600) {
                $seconds = 3600;
            }

            return $seconds;
        }

        if (in_array($seconds, $allowed, true)) {
            return $seconds;
        }

        $closest = $allowed[0];
        $minDiff = PHP_INT_MAX;

        foreach ($allowed as $value) {
            $diff = abs($value - $seconds);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $value;
            }
        }

        return $closest;
    }

    /**
     * @param array<string, mixed> $prefs
     *
     * @return array<string, mixed>
     */
    private static function sanitizeOverviewPreferences(array $prefs): array
    {
        $allowed = self::allowedRefreshIntervals();
        $defaultInterval = $allowed[0] ?? 60;

        $preset = isset($prefs['preset']) ? sanitize_key((string) $prefs['preset']) : '';

        return [
            'client_id' => isset($prefs['client_id']) ? absint((int) $prefs['client_id']) : 0,
            'preset' => self::normalizePreset($preset),
            'from' => isset($prefs['from']) ? self::sanitizeDate((string) $prefs['from']) : '',
            'to' => isset($prefs['to']) ? self::sanitizeDate((string) $prefs['to']) : '',
            'auto_refresh' => ! empty($prefs['auto_refresh']),
            'refresh_interval' => isset($prefs['refresh_interval'])
                ? self::sanitizeInterval((int) $prefs['refresh_interval'])
                : $defaultInterval,
        ];
    }

    private static function normalizePreset(string $preset): string
    {
        if ($preset === '') {
            return 'last7';
        }

        if (in_array($preset, OverviewRoutes::PRESET_WHITELIST, true)) {
            return $preset;
        }

        return 'last7';
    }

    /**
     * @return int[]
     */
    private static function allowedRefreshIntervals(): array
    {
        $settings = Options::getGlobalSettings();
        $configured = $settings['overview']['refresh_intervals'] ?? [];

        if (! is_array($configured)) {
            $configured = Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        }

        $intervals = array_values(array_filter(array_map('absint', $configured), static fn(int $value): bool => $value > 0));
        if ($intervals === []) {
            $intervals = Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        }

        sort($intervals);

        return $intervals;
    }
}

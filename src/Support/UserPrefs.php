<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use DateTimeImmutable;

use function absint;
use function get_current_user_id;
use function get_user_meta;
use function is_array;
use function sanitize_key;
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
            'preset' => sanitize_key($preset),
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
        if ($seconds < 30) {
            $seconds = 30;
        }

        if ($seconds > 600) {
            $seconds = 600;
        }

        return $seconds;
    }

    /**
     * @param array<string, mixed> $prefs
     *
     * @return array<string, mixed>
     */
    private static function sanitizeOverviewPreferences(array $prefs): array
    {
        return [
            'client_id' => isset($prefs['client_id']) ? absint((int) $prefs['client_id']) : 0,
            'preset' => isset($prefs['preset']) ? sanitize_key((string) $prefs['preset']) : '',
            'from' => isset($prefs['from']) ? self::sanitizeDate((string) $prefs['from']) : '',
            'to' => isset($prefs['to']) ? self::sanitizeDate((string) $prefs['to']) : '',
            'auto_refresh' => ! empty($prefs['auto_refresh']),
            'refresh_interval' => isset($prefs['refresh_interval'])
                ? self::sanitizeInterval((int) $prefs['refresh_interval'])
                : 60,
        ];
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Support;

use FP\DMS\Support\Wp;

use function add_settings_error;
use function delete_transient;
use function get_transient;
use function is_array;
use function set_transient;

class NoticeStore
{
    private const TRANSIENT_PREFIX = 'fpdms_notice_';

    public static function enqueue(string $group, string $code, string $message, string $type = 'updated'): void
    {
        $key = self::key($group);
        $existing = get_transient($key);
        if (! is_array($existing)) {
            $existing = [];
        }

        $existing[] = [
            'group' => $group,
            'code' => $code,
            'message' => $message,
            'type' => $type,
        ];

        set_transient($key, $existing, Wp::minuteInSeconds());
    }

    public static function flash(string $group): void
    {
        $key = self::key($group);
        $notices = get_transient($key);
        if (! is_array($notices)) {
            return;
        }

        delete_transient($key);

        foreach ($notices as $notice) {
            if (! isset($notice['message'])) {
                continue;
            }

            $code = is_string($notice['code'] ?? null) ? $notice['code'] : 'fpdms_notice';
            $type = is_string($notice['type'] ?? null) ? $notice['type'] : 'updated';
            add_settings_error($group, $code, (string) $notice['message'], $type);
        }
    }

    private static function key(string $group): string
    {
        return self::TRANSIENT_PREFIX . Wp::sanitizeKey($group);
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

class Logger
{
    public static function log(string $message): void
    {
        self::logChannel('INFO', $message);
    }

    public static function logQa(string $message): void
    {
        self::logChannel('QA', $message);
    }

    public static function logAnomaly(int $clientId, string $metric, string $severity, array $context = []): void
    {
        $parts = [
            sprintf('client=%d', $clientId),
            sprintf('metric=%s', $metric),
            sprintf('severity=%s', $severity),
        ];

        foreach (['algo', 'delta', 'z', 'ewma', 'cusum', 'qa'] as $key) {
            if (! array_key_exists($key, $context)) {
                continue;
            }
            $value = $context[$key];
            if (is_float($value)) {
                $value = round($value, 4);
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $parts[] = sprintf('%s=%s', $key, $value === null ? 'null' : $value);
        }

        self::logChannel('ANOM', implode(' ', $parts));
    }

    public static function logChannel(string $channel, string $message): void
    {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'fpdms-logs';
        wp_mkdir_p($dir);
        $file = trailingslashit($dir) . 'fpdms.log';
        $line = sprintf('[%s] [%s] %s%s', wp_date('Y-m-d H:i:s'), strtoupper($channel), $message, PHP_EOL);
        file_put_contents($file, $line, FILE_APPEND);
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Support\Wp;

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
        $upload = Wp::uploadDir();
        if (! empty($upload['error']) || empty($upload['basedir'])) {
            // Cannot write logs if upload directory unavailable
            return;
        }
        
        $baseDir = Wp::normalizePath($upload['basedir']);
        $dir = Wp::trailingSlashIt($baseDir) . 'fpdms-logs';

        try {
            Wp::mkdirP($dir);
        } catch (\RuntimeException) {
            return;
        }

        $file = Wp::trailingSlashIt($dir) . 'fpdms.log';
        
        // Validate path to prevent traversal
        $realFile = realpath(dirname($file));
        if ($realFile === false || !str_starts_with($realFile, realpath($baseDir))) {
            error_log('[FPDMS] Suspicious log path detected, skipping write');
            return;
        }
        
        $line = sprintf('[%s] [%s] %s%s', Wp::date('Y-m-d H:i:s'), strtoupper($channel), $message, PHP_EOL);
        
        // Check if file_put_contents succeeds
        $result = @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            // Fallback to error_log if file write fails
            error_log('[FPDMS] ' . $line);
        }
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

class Logger
{
    public static function log(string $message): void
    {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'fpdms-logs';
        wp_mkdir_p($dir);
        $file = trailingslashit($dir) . 'fpdms.log';
        $line = sprintf('[%s] %s%s', wp_date('Y-m-d H:i:s'), $message, PHP_EOL);
        file_put_contents($file, $line, FILE_APPEND);
    }

    public static function logQa(string $message): void
    {
        self::log('[QA] ' . $message);
    }
}

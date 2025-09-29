<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Domain\Repos\ReportsRepo;

class Retention
{
    public static function cleanup(): void
    {
        $settings = Options::getGlobalSettings();
        $days = max(1, (int) ($settings['retention_days'] ?? 90));
        $cutoff = strtotime('-' . $days . ' days');

        self::cleanupLogs($cutoff);
        self::cleanupReports($cutoff);
    }

    private static function cleanupLogs(int $cutoff): void
    {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'fpdms-logs';
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.log') as $file) {
            if (is_string($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private static function cleanupReports(int $cutoff): void
    {
        $reports = new ReportsRepo();
        $items = $reports->search(['status' => 'success']);
        $upload = wp_upload_dir();
        foreach ($items as $report) {
            $created = strtotime($report->createdAt);
            if ($created !== false && $created < $cutoff && $report->storagePath) {
                $path = trailingslashit($upload['basedir']) . $report->storagePath;
                if (file_exists($path)) {
                    @unlink($path);
                }
                $reports->update($report->id ?? 0, ['storage_path' => null]);
            }
        }
    }
}

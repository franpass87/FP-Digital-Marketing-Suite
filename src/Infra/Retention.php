<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FilesystemIterator;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Support\Wp;
use UnexpectedValueException;
use WP_Filesystem_Base;

use const ABSPATH;
use function error_log;
use function file_exists;
use function is_dir;
use function function_exists;
use function sprintf;
use function strtotime;
use function unlink;
use function WP_Filesystem;
use function ltrim;
use function str_starts_with;

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
        $upload = Wp::uploadDir();
        if (! empty($upload['error']) || empty($upload['basedir'])) {
            error_log('[FPDMS] Skipping log cleanup because uploads directory is unavailable.');

            return;
        }

        $dir = Wp::trailingSlashIt($upload['basedir']) . 'fpdms-logs';
        if (! is_dir($dir)) {
            return;
        }

        try {
            $iterator = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
        } catch (UnexpectedValueException $exception) {
            error_log(sprintf('[FPDMS] Unable to iterate log directory %s: %s', $dir, $exception->getMessage()));

            return;
        }

        foreach ($iterator as $entry) {
            if (! $entry->isFile() || $entry->getExtension() !== 'log') {
                continue;
            }

            if ($entry->getMTime() >= $cutoff) {
                continue;
            }

            if (! self::deletePath($entry->getPathname())) {
                error_log(sprintf('[FPDMS] Failed to delete log file %s', $entry->getPathname()));
            }
        }
    }

    private static function cleanupReports(int $cutoff): void
    {
        $reports = new ReportsRepo();
        $items = $reports->search([
            'status' => 'success',
            'created_before' => Wp::date('Y-m-d H:i:s', $cutoff),
        ]);
        $upload = Wp::uploadDir();
        if (! empty($upload['error']) || empty($upload['basedir'])) {
            error_log('[FPDMS] Skipping report cleanup because uploads directory is unavailable.');

            return;
        }

        $baseDir = Wp::trailingSlashIt(Wp::normalizePath($upload['basedir']));
        foreach ($items as $report) {
            if (empty($report->storagePath)) {
                continue;
            }

            $created = strtotime($report->createdAt ?? '');
            if ($created === false || $created >= $cutoff) {
                continue;
            }

            $relative = Wp::normalizePath(ltrim((string) $report->storagePath, '/\\'));
            if ($relative === '') {
                continue;
            }

            $absolute = Wp::normalizePath($baseDir . $relative);
            if (! str_starts_with($absolute, $baseDir)) {
                error_log(sprintf('[FPDMS] Skipping report cleanup for suspicious path %s', $report->storagePath));
                continue;
            }

            $deleted = true;
            if (file_exists($absolute)) {
                $deleted = self::deletePath($absolute);
                if (! $deleted) {
                    error_log(sprintf('[FPDMS] Failed to delete report artifact %s', $absolute));
                }
            }

            if ($deleted) {
                $reports->update($report->id ?? 0, ['storage_path' => null]);
            }
        }
    }

    private static function deletePath(string $path): bool
    {
        $path = (string) $path;
        if ($path === '' || ! file_exists($path)) {
            return true;
        }

        global $wp_filesystem;

        if (! isset($wp_filesystem) || ! $wp_filesystem instanceof WP_Filesystem_Base) {
            if (! function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            WP_Filesystem();
        }

        if (isset($wp_filesystem) && $wp_filesystem instanceof WP_Filesystem_Base) {
            return $wp_filesystem->delete($path, false, 'f');
        }

        return unlink($path);
    }
}

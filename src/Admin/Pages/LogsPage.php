<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Support\Wp;

use function __;

class LogsPage
{
    private const MAX_BYTES = 200000;

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $logData = self::readLogs();
        $logs = $logData['content'];
        $truncated = $logData['truncated'];
        $error = $logData['error'];
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Logs', 'fp-dms') . '</h1>';
        if ($error !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }
        if (empty($logs)) {
            echo '<p>' . esc_html__('No logs available yet.', 'fp-dms') . '</p>';
        } else {
            if ($truncated) {
                /* translators: %d: size in kilobytes. */
                $message = sprintf(__('Showing last %d KB of log output.', 'fp-dms'), (int) (self::MAX_BYTES / 1024));
                echo '<p class="description">' . esc_html($message) . '</p>';
            }
            echo '<pre style="background:#111;color:#0f0;padding:20px;max-height:500px;overflow:auto;">' . esc_html($logs) . '</pre>';
        }
        echo '</div>';
    }

    /**
     * @return array{content: string, truncated: bool, error: string}
     */
    private static function readLogs(): array
    {
        $upload = Wp::uploadDir();
        if (! empty($upload['error']) || empty($upload['basedir'])) {
            return [
                'content' => '',
                'truncated' => false,
                'error' => __('Unable to access the uploads directory. Check your WordPress configuration.', 'fp-dms'),
            ];
        }

        $file = Wp::trailingSlashIt($upload['basedir']) . 'fpdms-logs/fpdms.log';
        if (! file_exists($file)) {
            return ['content' => '', 'truncated' => false, 'error' => ''];
        }

        $handle = fopen($file, 'rb');
        if (! $handle) {
            return [
                'content' => '',
                'truncated' => false,
                'error' => __('Unable to open the log file for reading.', 'fp-dms'),
            ];
        }

        $size = filesize($file);
        $truncated = false;
        $offset = 0;

        if (is_int($size) && $size > self::MAX_BYTES) {
            $truncated = true;
            $offset = $size - self::MAX_BYTES;
        }

        if (fseek($handle, $offset, SEEK_SET) !== 0) {
            // Fall back to the beginning of the file when seeking fails.
            fseek($handle, 0, SEEK_SET);
            $truncated = false;
        }

        $contents = stream_get_contents($handle);
        fclose($handle);

        if (! is_string($contents)) {
            return ['content' => '', 'truncated' => $truncated, 'error' => __('Unable to read the log file.', 'fp-dms')];
        }

        return ['content' => $contents, 'truncated' => $truncated, 'error' => ''];
    }
}

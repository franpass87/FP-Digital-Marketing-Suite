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
        echo '<div class="wrap fpdms-admin-page">';
        
        // Header moderno
        echo '<div class="fpdms-page-header">';
        echo '<h1><span class="dashicons dashicons-media-text" style="margin-right:12px;"></span>' . esc_html__('Log Sistema', 'fp-dms') . '</h1>';
        echo '<p>' . esc_html__('Visualizza i log di sistema per debugging e monitoraggio delle operazioni.', 'fp-dms') . '</p>';
        echo '</div>';
        
        if ($error !== '') {
            echo '<div class="fpdms-alert fpdms-alert-danger"><span class="dashicons dashicons-warning"></span><p>' . esc_html($error) . '</p></div>';
        }
        if (empty($logs)) {
            echo '<div class="fpdms-empty-state"><span class="dashicons dashicons-media-text"></span><h3>' . esc_html__('Nessun Log Disponibile', 'fp-dms') . '</h3><p>' . esc_html__('I log appariranno qui quando il sistema inizierà a registrare attività.', 'fp-dms') . '</p></div>';
        } else {
            if ($truncated) {
                /* translators: %d: size in kilobytes. */
                $message = sprintf(__('Visualizzazione ultimi %d KB di log.', 'fp-dms'), (int) (self::MAX_BYTES / 1024));
                echo '<div class="fpdms-alert fpdms-alert-info"><span class="dashicons dashicons-info"></span><p>' . esc_html($message) . '</p></div>';
            }
            echo '<div class="fpdms-card"><pre style="background:#1a1a1a;color:#00ff00;padding:20px;max-height:600px;overflow:auto;border-radius:8px;font-family:\'Courier New\',monospace;font-size:12px;line-height:1.4;">' . esc_html($logs) . '</pre></div>';
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

        // Check if filesize succeeded and file is large enough to truncate
        if ($size === false) {
            fclose($handle);
            return [
                'content' => '',
                'truncated' => false,
                'error' => __('Unable to determine log file size.', 'fp-dms'),
            ];
        }

        if ($size > self::MAX_BYTES) {
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

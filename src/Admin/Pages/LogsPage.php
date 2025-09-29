<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

class LogsPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $logs = self::readLogs();
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Logs', 'fp-dms') . '</h1>';
        if (empty($logs)) {
            echo '<p>' . esc_html__('No logs available yet.', 'fp-dms') . '</p>';
        } else {
            echo '<pre style="background:#111;color:#0f0;padding:20px;max-height:500px;overflow:auto;">' . esc_html($logs) . '</pre>';
        }
        echo '</div>';
    }

    private static function readLogs(): string
    {
        $upload = wp_upload_dir();
        $file = trailingslashit($upload['basedir']) . 'fpdms-logs/fpdms.log';
        if (! file_exists($file)) {
            return '';
        }

        $contents = file_get_contents($file);
        return is_string($contents) ? $contents : '';
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Infra\Options;
use FP\DMS\Support\I18n;
use function wp_unslash;

class HealthPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        self::handleActions();

        $lastTick = Options::getLastTick();
        $nextSchedule = (new SchedulesRepo())->nextScheduledRun();
        $status = self::determineStatus($lastTick);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(I18n::__('System Health')) . '</h1>';
        settings_errors('fpdms_health');

        echo '<table class="widefat striped" style="max-width:600px">';
        echo '<tbody>';
        echo '<tr><th>' . esc_html__('Last Tick', 'fp-dms') . '</th><td>' . esc_html(self::formatTimestamp($lastTick)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Status', 'fp-dms') . '</th><td>' . esc_html($status) . '</td></tr>';
        $nextRun = $nextSchedule && $nextSchedule->nextRunAt
            ? self::formatDateTime($nextSchedule->nextRunAt)
            : I18n::__('Not scheduled');
        echo '<tr><th>' . esc_html__('Next Scheduled Run', 'fp-dms') . '</th><td>' . esc_html($nextRun) . '</td></tr>';
        echo '</tbody></table>';

        echo '<form method="post" style="margin-top:20px;">';
        wp_nonce_field('fpdms_health_action', 'fpdms_health_nonce');
        echo '<input type="hidden" name="fpdms_health_action" value="force_tick">';
        submit_button(I18n::__('Force Tick Now'), 'secondary');
        echo '</form>';
        echo '</div>';
    }

    private static function handleActions(): void
    {
        $post = wp_unslash($_POST);
        if (empty($post['fpdms_health_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field((string) ($post['fpdms_health_nonce'] ?? '')), 'fpdms_health_action')) {
            return;
        }

        $action = sanitize_text_field((string) ($post['fpdms_health_action'] ?? ''));
        if ($action === 'force_tick') {
            do_action('fpdms/health/force_tick');
            add_settings_error('fpdms_health', 'fpdms_health_tick', I18n::__('Tick executed.'), 'updated');
        }
    }

    private static function formatTimestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return I18n::__('Never');
        }

        $diff = human_time_diff($timestamp, time());

        return sprintf(I18n::__('%1$s ago (%2$s)'), $diff, wp_date('Y-m-d H:i:s', $timestamp));
    }

    private static function determineStatus(int $lastTick): string
    {
        if ($lastTick <= 0) {
            return I18n::__('No tick recorded yet');
        }

        $delta = time() - $lastTick;
        if ($delta > 900) {
            return I18n::__('Warning: last tick more than 15 minutes ago');
        }

        return I18n::__('OK');
    }

    private static function formatDateTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (! $timestamp) {
            return $datetime;
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }
}

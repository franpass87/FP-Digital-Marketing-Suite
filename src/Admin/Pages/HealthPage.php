<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Infra\Options;
use FP\DMS\Support\I18n;
use FP\DMS\Support\Wp;

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

        echo '<div class="wrap fpdms-admin-page">';
        
        // Header moderno
        echo '<div class="fpdms-page-header">';
        echo '<h1><span class="dashicons dashicons-heart" style="margin-right:12px;"></span>' . esc_html__('Salute Sistema', 'fp-dms') . '</h1>';
        echo '<p>' . esc_html__('Monitora lo stato di salute del sistema, cron jobs e pianificazioni attive.', 'fp-dms') . '</p>';
        echo '</div>';
        
        settings_errors('fpdms_health');

        echo '<div class="fpdms-card" style="max-width:700px;">';
        echo '<table class="fpdms-table">';
        echo '<tbody>';
        echo '<tr><th>' . esc_html__('Ultimo Tick', 'fp-dms') . '</th><td>' . esc_html(self::formatTimestamp($lastTick)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Stato', 'fp-dms') . '</th><td>' . esc_html($status) . '</td></tr>';
        $nextRun = $nextSchedule && $nextSchedule->nextRunAt
            ? self::formatDateTime($nextSchedule->nextRunAt)
            : 'Non pianificato';
        echo '<tr><th>' . esc_html__('Prossima Esecuzione', 'fp-dms') . '</th><td>' . esc_html($nextRun) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        echo '<div class="fpdms-card" style="max-width:700px;margin-top:24px;">';
        echo '<form method="post">';
        wp_nonce_field('fpdms_health_action', 'fpdms_health_nonce');
        echo '<input type="hidden" name="fpdms_health_action" value="force_tick">';
        submit_button('Forza Tick Manuale', 'primary fpdms-button-success');
        echo '</form>';
        echo '</div>';
        
        echo '</div>';
    }

    private static function handleActions(): void
    {
        $post = Wp::unslash($_POST);
        if (empty($post['fpdms_health_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(Wp::sanitizeTextField($post['fpdms_health_nonce'] ?? ''), 'fpdms_health_action')) {
            return;
        }

        $action = Wp::sanitizeTextField($post['fpdms_health_action'] ?? '');
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

        return sprintf(I18n::__('%1$s ago (%2$s)'), $diff, Wp::date('Y-m-d H:i:s', $timestamp));
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

        return Wp::date('Y-m-d H:i:s', $timestamp);
    }
}

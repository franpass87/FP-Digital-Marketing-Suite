<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Support\I18n;

class AnomaliesPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $repo = new AnomaliesRepo();
        self::handleActions($repo);

        $clientsRepo = new ClientsRepo();
        $clients = $clientsRepo->all();
        $clientsMap = [];
        foreach ($clients as $client) {
            $clientsMap[$client->id ?? 0] = $client->name;
        }

        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        $anomalies = $clientId > 0 ? $repo->recentForClient($clientId, 50) : $repo->recent(50);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(I18n::__('Anomalies')) . '</h1>';
        settings_errors('fpdms_anomalies');

        echo '<form method="get" style="margin-top:20px;margin-bottom:20px;display:flex;gap:12px;align-items:center;">';
        echo '<input type="hidden" name="page" value="fp-dms-anomalies">';
        echo '<label for="fpdms-anomaly-client">' . esc_html(I18n::__('Filter by client')) . '</label>';
        echo '<select name="client_id" id="fpdms-anomaly-client">';
        echo '<option value="0">' . esc_html(I18n::__('All clients')) . '</option>';
        foreach ($clients as $client) {
            $selected = selected($clientId, (int) $client->id, false);
            echo '<option value="' . esc_attr((string) $client->id) . '"' . $selected . '>' . esc_html($client->name) . '</option>';
        }
        echo '</select>';
        submit_button(I18n::__('Apply'), '', '', false);
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html(I18n::__('Detected at')) . '</th>';
        echo '<th>' . esc_html(I18n::__('Client')) . '</th>';
        echo '<th>' . esc_html(I18n::__('Metric')) . '</th>';
        echo '<th>' . esc_html(I18n::__('Severity')) . '</th>';
        echo '<th>' . esc_html(I18n::__('Δ %')) . '</th>';
        echo '<th>' . esc_html(I18n::__('Z-score')) . '</th>';
        echo '<th>' . esc_html(I18n::__('Note')) . '</th>';
        echo '<th>' . esc_html(I18n::__('Actions')) . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($anomalies)) {
            echo '<tr><td colspan="8">' . esc_html(I18n::__('No anomalies recorded.')) . '</td></tr>';
        }

        foreach ($anomalies as $anomaly) {
            $payload = $anomaly->payload;
            $metric = isset($payload['metric']) ? (string) $payload['metric'] : $anomaly->type;
            $delta = isset($payload['delta_percent']) && is_numeric($payload['delta_percent'])
                ? number_format_i18n((float) $payload['delta_percent'], 2) . '%'
                : I18n::__('n/a');
            $zScore = isset($payload['z_score']) && is_numeric($payload['z_score'])
                ? number_format_i18n((float) $payload['z_score'], 2)
                : I18n::__('n/a');
            $note = isset($payload['note']) ? (string) $payload['note'] : '';
            $resolved = ! empty($payload['resolved']);
            $clientName = $clientsMap[$anomaly->clientId] ?? I18n::__('Unknown client');

            echo '<tr>';
            echo '<td>' . esc_html(wp_date('Y-m-d H:i', strtotime($anomaly->detectedAt))) . '</td>';
            echo '<td>' . esc_html($clientName) . '</td>';
            echo '<td>' . esc_html($metric) . '</td>';
            echo '<td>' . esc_html(ucfirst($anomaly->severity)) . '</td>';
            echo '<td>' . esc_html($delta) . '</td>';
            echo '<td>' . esc_html($zScore) . '</td>';
            echo '<td>' . esc_html($note) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:flex;gap:8px;align-items:center;">';
            wp_nonce_field('fpdms_anomaly_update', 'fpdms_anomaly_nonce');
            echo '<input type="hidden" name="anomaly_id" value="' . esc_attr((string) ($anomaly->id ?? 0)) . '">';
            echo '<label><input type="checkbox" name="resolved" value="1"' . checked($resolved, true, false) . '> ' . esc_html(I18n::__('Resolved')) . '</label>';
            echo '<label class="screen-reader-text" for="fpdms-note-' . esc_attr((string) $anomaly->id) . '">' . esc_html(I18n::__('Add note')) . '</label>';
            echo '<input type="text" id="fpdms-note-' . esc_attr((string) $anomaly->id) . '" name="note" value="' . esc_attr($note) . '" placeholder="' . esc_attr(I18n::__('Add note')) . '" style="width:160px;">';
            submit_button(I18n::__('Save'), 'secondary small', 'submit', false);
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private static function handleActions(AnomaliesRepo $repo): void
    {
        if (empty($_POST['fpdms_anomaly_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field((string) $_POST['fpdms_anomaly_nonce']), 'fpdms_anomaly_update')) {
            return;
        }

        $id = (int) ($_POST['anomaly_id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $anomaly = $repo->find($id);
        if (! $anomaly) {
            return;
        }

        $payload = $anomaly->payload;
        $payload['resolved'] = ! empty($_POST['resolved']);
        $payload['note'] = sanitize_text_field((string) ($_POST['note'] ?? ''));

        if ($repo->updatePayload($id, $payload)) {
            add_settings_error('fpdms_anomalies', 'fpdms_anomaly_saved', I18n::__('Anomaly updated.'), 'updated');
        } else {
            add_settings_error('fpdms_anomalies', 'fpdms_anomaly_error', I18n::__('Unable to update anomaly.'), 'error');
        }
    }
}

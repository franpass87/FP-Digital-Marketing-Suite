<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Infra\Options;
use FP\DMS\Services\Anomalies\Detector;
use FP\DMS\Support\I18n;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use function is_array;
use function is_numeric;
use function str_contains;

class AnomaliesPage
{
    /** @var array<string,mixed> */
    private static array $policyTestResult = [];

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $repo = new AnomaliesRepo();
        self::handleActions($repo);
        self::handlePolicyActions();

        $clientsRepo = new ClientsRepo();
        $clients = $clientsRepo->all();
        $clientsMap = [];
        foreach ($clients as $client) {
            $clientsMap[$client->id ?? 0] = $client->name;
        }

        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        $tab = isset($_GET['tab']) ? Wp::sanitizeKey($_GET['tab']) : 'anomalies';
        $anomalies = $clientId > 0 ? $repo->recentForClient($clientId, 50) : $repo->recent(50);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(I18n::__('Anomalies')) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        $tabs = [
            'anomalies' => I18n::__('Recent Anomalies'),
            'policy' => I18n::__('Policy'),
        ];
        foreach ($tabs as $key => $label) {
            $url = add_query_arg([
                'page' => 'fp-dms-anomalies',
                'tab' => $key,
                'client_id' => $clientId,
            ], admin_url('admin.php'));
            $class = $tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        if ($tab === 'policy') {
            settings_errors('fpdms_anomaly_policy');
            self::renderPolicyTab($clients, $clientId);
        } else {
            settings_errors('fpdms_anomalies');
            echo '<form method="get" style="margin-top:20px;margin-bottom:20px;display:flex;gap:12px;align-items:center;">';
            echo '<input type="hidden" name="page" value="fp-dms-anomalies">';
            echo '<input type="hidden" name="tab" value="anomalies">';
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
                    ? Wp::numberFormatI18n((float) $payload['delta_percent'], 2) . '%'
                    : I18n::__('n/a');
                $zScore = isset($payload['z_score']) && is_numeric($payload['z_score'])
                    ? Wp::numberFormatI18n((float) $payload['z_score'], 2)
                    : I18n::__('n/a');
                $note = isset($payload['note']) ? (string) $payload['note'] : '';
                $resolved = ! empty($payload['resolved']);
                $clientName = $clientsMap[$anomaly->clientId] ?? I18n::__('Unknown client');

                echo '<tr>';
                echo '<td>' . esc_html(Wp::date('Y-m-d H:i', strtotime($anomaly->detectedAt))) . '</td>';
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
        }
        echo '</div>';
    }

    private static function handleActions(AnomaliesRepo $repo): void
    {
        $post = Wp::unslash($_POST);
        if (empty($post['fpdms_anomaly_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(Wp::sanitizeTextField($post['fpdms_anomaly_nonce'] ?? ''), 'fpdms_anomaly_update')) {
            return;
        }

        $id = (int) ($post['anomaly_id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $anomaly = $repo->find($id);
        if (! $anomaly) {
            return;
        }

        $payload = $anomaly->payload;
        $payload['resolved'] = ! empty($post['resolved']);
        $payload['note'] = Wp::sanitizeTextField($post['note'] ?? '');

        if ($repo->updatePayload($id, $payload)) {
            add_settings_error('fpdms_anomalies', 'fpdms_anomaly_saved', I18n::__('Anomaly updated.'), 'updated');
        } else {
            add_settings_error('fpdms_anomalies', 'fpdms_anomaly_error', I18n::__('Unable to update anomaly.'), 'error');
        }
    }

    private static function handlePolicyActions(): void
    {
        $post = Wp::unslash($_POST);
        if (empty($post['fpdms_anomaly_policy_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(Wp::sanitizeTextField($post['fpdms_anomaly_policy_nonce'] ?? ''), 'fpdms_anomaly_policy')) {
            return;
        }

        $clientId = (int) ($post['client_id'] ?? 0);
        $action = Wp::sanitizeTextField($post['fpdms_policy_action'] ?? 'save');

        if ($action === 'reset') {
            Options::deleteAnomalyPolicy($clientId);
            add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_reset', I18n::__('Policy reset to defaults.'), 'updated');

            return;
        }

        if ($action === 'test') {
            self::$policyTestResult = self::evaluatePolicy($clientId);

            return;
        }

        if (str_starts_with($action, 'test_')) {
            $channel = substr($action, 5);
            self::sendTestNotification($clientId, $channel);

            return;
        }

        $result = self::sanitizePolicyInput($clientId);
        Options::updateAnomalyPolicy($clientId, $result['policy']);
        if (! empty($result['errors']['invalid_mute_timezone'])) {
            add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_tz', I18n::__('Invalid mute timezone provided. Reverted to the previous value.'), 'error');
        }
        add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_saved', I18n::__('Policy saved.'), 'updated');
    }

    private static function renderPolicyTab(array $clients, int $clientId): void
    {
        if (empty($clients)) {
            echo '<p>' . esc_html(I18n::__('Add a client before configuring anomaly policies.')) . '</p>';

            return;
        }

        if ($clientId <= 0) {
            $first = $clients[0];
            $clientId = $first->id ?? 0;
        }

        $policy = Options::getAnomalyPolicy($clientId);

        echo '<form method="post" action="">';
        wp_nonce_field('fpdms_anomaly_policy', 'fpdms_anomaly_policy_nonce');
        echo '<input type="hidden" name="tab" value="policy">';
        echo '<input type="hidden" name="page" value="fp-dms-anomalies">';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="fpdms-policy-client">' . esc_html(I18n::__('Client')) . '</label></th>';
        echo '<td><select name="client_id" id="fpdms-policy-client">';
        foreach ($clients as $client) {
            $selected = selected($clientId, (int) $client->id, false);
            echo '<option value="' . esc_attr((string) $client->id) . '"' . $selected . '>' . esc_html($client->name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18n::__('Thresholds')) . '</th><td>';
        echo '<table class="widefat striped" style="max-width:720px">';
        echo '<thead><tr><th>' . esc_html(I18n::__('Metric')) . '</th><th>' . esc_html(I18n::__('Warn %')) . '</th><th>' . esc_html(I18n::__('Crit %')) . '</th><th>' . esc_html(I18n::__('Warn z')) . '</th><th>' . esc_html(I18n::__('Crit z')) . '</th></tr></thead><tbody>';
        foreach ($policy['metrics'] as $metric => $values) {
            echo '<tr>';
            echo '<td>' . esc_html($metric) . '</td>';
            echo '<td><input type="number" step="0.1" name="metrics[' . esc_attr($metric) . '][warn_pct]" value="' . esc_attr((string) $values['warn_pct']) . '" class="small-text"></td>';
            echo '<td><input type="number" step="0.1" name="metrics[' . esc_attr($metric) . '][crit_pct]" value="' . esc_attr((string) $values['crit_pct']) . '" class="small-text"></td>';
            echo '<td><input type="number" step="0.1" name="metrics[' . esc_attr($metric) . '][z_warn]" value="' . esc_attr((string) $values['z_warn']) . '" class="small-text"></td>';
            echo '<td><input type="number" step="0.1" name="metrics[' . esc_attr($metric) . '][z_crit]" value="' . esc_attr((string) $values['z_crit']) . '" class="small-text"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18n::__('Baseline')) . '</th><td>';
        echo '<label>' . esc_html(I18n::__('Window (days)')) . ' <input type="number" class="small-text" name="baseline[window_days]" value="' . esc_attr((string) $policy['baseline']['window_days']) . '"></label> ';
        echo '<label>' . esc_html(I18n::__('Seasonality')) . ' <input type="text" class="regular-text" name="baseline[seasonality]" value="' . esc_attr((string) $policy['baseline']['seasonality']) . '"></label> ';
        echo '<label>' . esc_html(I18n::__('EWMA α')) . ' <input type="number" step="0.01" class="small-text" name="baseline[ewma_alpha]" value="' . esc_attr((string) $policy['baseline']['ewma_alpha']) . '"></label> ';
        echo '<label>' . esc_html(I18n::__('CUSUM k')) . ' <input type="number" step="0.01" class="small-text" name="baseline[cusum_k]" value="' . esc_attr((string) $policy['baseline']['cusum_k']) . '"></label> ';
        echo '<label>' . esc_html(I18n::__('CUSUM h')) . ' <input type="number" step="0.01" class="small-text" name="baseline[cusum_h]" value="' . esc_attr((string) $policy['baseline']['cusum_h']) . '"></label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18n::__('Mute window')) . '</th><td>';
        echo '<label>' . esc_html(I18n::__('Start')) . ' <input type="time" name="mute[start]" value="' . esc_attr((string) $policy['mute']['start']) . '"></label> ';
        echo '<label>' . esc_html(I18n::__('End')) . ' <input type="time" name="mute[end]" value="' . esc_attr((string) $policy['mute']['end']) . '"></label> ';
        echo '<label>' . esc_html(I18n::__('Timezone')) . ' <input type="text" class="regular-text" name="mute[tz]" value="' . esc_attr((string) $policy['mute']['tz']) . '"></label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18n::__('Routing')) . '</th><td>';
        echo '<p>' . esc_html(I18n::__('Enable channels and provide credentials where required.')) . '</p>';
        foreach ($policy['routing'] as $channel => $config) {
            echo '<fieldset style="margin-bottom:16px;padding:12px;border:1px solid #ddd;">';
            echo '<legend>' . esc_html(ucfirst(str_replace('_', ' ', $channel))) . '</legend>';
            echo '<label><input type="checkbox" name="routing[' . esc_attr($channel) . '][enabled]" value="1"' . checked(! empty($config['enabled']), true, false) . '> ' . esc_html(I18n::__('Enabled')) . '</label><br>';
            foreach ($config as $key => $value) {
                if ($key === 'enabled') {
                    continue;
                }
                $inputType = str_contains($key, 'token') || str_contains($key, 'secret') ? 'password' : 'text';
                $label = ucwords(str_replace('_', ' ', $key));
                echo '<label>' . esc_html($label) . ' <input type="' . esc_attr($inputType) . '" class="regular-text" name="routing[' . esc_attr($channel) . '][' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '"></label><br>';
            }
            echo '<button type="submit" class="button" name="fpdms_policy_action" value="test_' . esc_attr($channel) . '">' . esc_html(I18n::__('Test notification')) . '</button>';
            echo '</fieldset>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18n::__('Rate limiting')) . '</th><td>';
        echo '<label>' . esc_html(I18n::__('Cooldown (minutes)')) . ' <input type="number" class="small-text" name="cooldown_min" value="' . esc_attr((string) $policy['cooldown_min']) . '"></label> ';
        echo '<label>' . esc_html(I18n::__('Max per window')) . ' <input type="number" class="small-text" name="max_per_window" value="' . esc_attr((string) $policy['max_per_window']) . '"></label>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(I18n::__('Save Policy'), 'primary', 'fpdms_policy_action', false, ['value' => 'save']);
        submit_button(I18n::__('Reset to defaults'), 'secondary', 'fpdms_policy_action', false, ['value' => 'reset']);
        submit_button(I18n::__('Evaluate last 30 days'), 'secondary', 'fpdms_policy_action', false, ['value' => 'test']);
        echo '</form>';

        if (! empty(self::$policyTestResult['anomalies'])) {
            $count = count(self::$policyTestResult['anomalies']);
            echo '<div class="notice notice-info" style="margin-top:20px;"><p>' . esc_html(sprintf(I18n::__('Evaluation produced %d anomalies:'), $count)) . '</p><ul>';
            foreach (self::$policyTestResult['anomalies'] as $item) {
                $metric = esc_html((string) ($item['metric'] ?? 'metric'));
                $severity = esc_html((string) ($item['severity'] ?? 'warn'));
                $delta = isset($item['delta_percent']) ? esc_html((string) $item['delta_percent']) : 'n/a';
                echo '<li>' . $metric . ' (' . $severity . ') Δ ' . $delta . '</li>';
            }
            echo '</ul></div>';
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function sanitizePolicyInput(int $clientId): array
    {
        $post = Wp::unslash($_POST);

        $input = [];
        if (isset($post['metrics']) && is_array($post['metrics'])) {
            $input['metrics'] = $post['metrics'];
        }
        if (isset($post['baseline']) && is_array($post['baseline'])) {
            $input['baseline'] = $post['baseline'];
        }
        if (isset($post['mute']) && is_array($post['mute'])) {
            $input['mute'] = $post['mute'];
        }
        if (isset($post['routing']) && is_array($post['routing'])) {
            $input['routing'] = $post['routing'];
        }
        if (isset($post['cooldown_min'])) {
            $input['cooldown_min'] = $post['cooldown_min'];
        }
        if (isset($post['max_per_window'])) {
            $input['max_per_window'] = $post['max_per_window'];
        }

        $current = $clientId > 0
            ? Options::getAnomalyPolicy($clientId)
            : Options::getGlobalSettings()['anomaly_policy'];

        return Options::sanitizeAnomalyPolicyInput($input, $current);
    }

    /**
     * @return array<string,mixed>
     */
    private static function evaluatePolicy(int $clientId): array
    {
        $clientsRepo = new ClientsRepo();
        $client = $clientsRepo->find($clientId);
        if (! $client) {
            add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_client', I18n::__('Client not found.'), 'error');

            return [];
        }

        $reports = new \FP\DMS\Domain\Repos\ReportsRepo();
        $report = $reports->search(['client_id' => $clientId, 'status' => 'success'])[0] ?? null;
        if (! $report) {
            add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_report', I18n::__('No successful reports available for evaluation.'), 'error');

            return [];
        }

        $period = Period::fromStrings($report->periodStart, $report->periodEnd, $client->timezone);
        $detector = new Detector(new AnomaliesRepo());
        $anomalies = $detector->evaluatePeriod($clientId, $period, $report->meta, [], false);
        self::$policyTestResult = ['anomalies' => $anomalies];

        if (empty($anomalies)) {
            add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_none', I18n::__('No anomalies detected for the sampled period.'), 'info');
        }

        return self::$policyTestResult;
    }

    private static function sendTestNotification(int $clientId, string $channel): void
    {
        $clientsRepo = new ClientsRepo();
        $client = $clientsRepo->find($clientId);
        if (! $client) {
            add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_client', I18n::__('Client not found.'), 'error');

            return;
        }

        $policy = Options::getAnomalyPolicy($clientId);
        foreach ($policy['routing'] as $key => &$config) {
            $config['enabled'] = $key === $channel;
        }
        unset($config);

        $period = Period::fromStrings(
            gmdate('Y-m-d', strtotime('-1 day')),
            gmdate('Y-m-d'),
            $client->timezone
        );

        $anomalies = [[
            'metric' => 'test_metric',
            'severity' => 'warn',
            'delta_percent' => 5.0,
            'z_score' => 1.2,
            'period' => [
                'start' => $period->start->format('Y-m-d'),
                'end' => $period->end->format('Y-m-d'),
            ],
        ]];

        $router = new \FP\DMS\Infra\NotificationRouter();
        $result = $router->route($anomalies, $policy, $client, $period);
        if (empty($result['channels'])) {
            add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_test_fail', I18n::__('Test notification could not be sent. Check the configuration and cooldown windows.'), 'error');

            return;
        }

        add_settings_error('fpdms_anomaly_policy', 'fpdms_anomaly_policy_test_ok', I18n::__('Test notification dispatched.'), 'updated');
    }
}

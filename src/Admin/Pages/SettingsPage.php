<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Infra\Options;
use FP\DMS\Support\Validation;
use function __;
use function absint;
use function in_array;
use function sanitize_key;
use function strtolower;
use function wp_unslash;

class SettingsPage
{
    private const SMTP_SECURE_MODES = ['none', 'ssl', 'tls'];

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        self::handlePost();
        $settings = Options::getGlobalSettings();
        $configuredIntervals = $settings['overview']['refresh_intervals'] ?? Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        if (! is_array($configuredIntervals)) {
            $configuredIntervals = Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        }
        $refreshIntervalsDisplay = implode(', ', array_map(static fn($seconds): string => (string) (int) $seconds, $configuredIntervals));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('FP Suite Settings', 'fp-dms') . '</h1>';
        settings_errors('fpdms_settings');
        echo '<form method="post" action="">';
        wp_nonce_field('fpdms_save_settings', 'fpdms_settings_nonce');
        echo '<input type="hidden" name="fpdms_settings_action" value="save">';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="fpdms-owner-email">' . esc_html__('Owner Email', 'fp-dms') . '</label></th>';
        echo '<td><input type="email" class="regular-text" id="fpdms-owner-email" name="owner_email" value="' . esc_attr($settings['owner_email']) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-retention">' . esc_html__('Retention Days', 'fp-dms') . '</label></th>';
        echo '<td><input type="number" min="1" class="small-text" id="fpdms-retention" name="retention_days" value="' . esc_attr((string) $settings['retention_days']) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-overview-refresh-intervals">' . esc_html__('Overview refresh intervals', 'fp-dms') . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="fpdms-overview-refresh-intervals" name="overview[refresh_intervals]" value="' . esc_attr($refreshIntervalsDisplay) . '">';
        echo '<p class="description">' . esc_html__('Comma-separated list of allowed auto-refresh intervals in seconds.', 'fp-dms') . '</p></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Branding', 'fp-dms') . '</th>';
        echo '<td>';
        echo '<label for="fpdms-logo-url">' . esc_html__('Logo URL', 'fp-dms') . '</label><br>';
        echo '<input type="url" class="regular-text" id="fpdms-logo-url" name="pdf_branding[logo_url]" value="' . esc_attr($settings['pdf_branding']['logo_url']) . '"><br><br>';
        echo '<label for="fpdms-primary-color">' . esc_html__('Primary Color', 'fp-dms') . '</label><br>';
        echo '<input type="text" class="regular-text" id="fpdms-primary-color" name="pdf_branding[primary_color]" value="' . esc_attr($settings['pdf_branding']['primary_color']) . '"><br><br>';
        echo '<label for="fpdms-footer-text">' . esc_html__('Footer Text', 'fp-dms') . '</label><br>';
        echo '<textarea class="large-text" rows="3" id="fpdms-footer-text" name="pdf_branding[footer_text]">' . esc_textarea($settings['pdf_branding']['footer_text']) . '</textarea>';
        echo '</td></tr>';

        $settings['mail']['smtp']['secure'] = self::normaliseSecureMode((string) ($settings['mail']['smtp']['secure'] ?? 'none'));

        echo '<tr><th scope="row">' . esc_html__('SMTP Settings', 'fp-dms') . '</th>';
        echo '<td>';
        echo '<label for="fpdms-smtp-host">' . esc_html__('Host', 'fp-dms') . '</label><br>';
        echo '<input type="text" class="regular-text" id="fpdms-smtp-host" name="mail[smtp][host]" value="' . esc_attr($settings['mail']['smtp']['host']) . '"><br><br>';
        echo '<label for="fpdms-smtp-port">' . esc_html__('Port', 'fp-dms') . '</label><br>';
        echo '<input type="number" class="small-text" id="fpdms-smtp-port" name="mail[smtp][port]" value="' . esc_attr((string) $settings['mail']['smtp']['port']) . '"><br><br>';
        echo '<label for="fpdms-smtp-secure">' . esc_html__('Secure Mode', 'fp-dms') . '</label><br>';
        echo '<select id="fpdms-smtp-secure" name="mail[smtp][secure]">';
        foreach (['none' => __('None', 'fp-dms'), 'ssl' => __('SSL', 'fp-dms'), 'tls' => __('TLS', 'fp-dms')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($settings['mail']['smtp']['secure'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><br><br>';
        echo '<label for="fpdms-smtp-user">' . esc_html__('Username', 'fp-dms') . '</label><br>';
        echo '<input type="text" class="regular-text" id="fpdms-smtp-user" name="mail[smtp][user]" value="' . esc_attr($settings['mail']['smtp']['user']) . '"><br><br>';
        echo '<label for="fpdms-smtp-pass">' . esc_html__('Password', 'fp-dms') . '</label><br>';
        echo '<input type="password" class="regular-text" id="fpdms-smtp-pass" name="mail[smtp][pass]" value="' . esc_attr($settings['mail']['smtp']['pass']) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-webhook">' . esc_html__('Error Webhook URL', 'fp-dms') . '</label></th>';
        echo '<td><input type="url" class="regular-text" id="fpdms-webhook" name="error_webhook_url" value="' . esc_attr($settings['error_webhook_url']) . '"></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Tick Key', 'fp-dms') . '</th>';
        echo '<td>';
        echo '<code style="display:block;margin-bottom:8px;">' . esc_html($settings['tick_key']) . '</code>';
        echo '<button type="submit" name="fpdms_settings_action" value="regenerate" class="button">' . esc_html__('Generate New Tick Key', 'fp-dms') . '</button>';
        echo '</td></tr>';

        $policy = $settings['anomaly_policy'];
        echo '<tr><th scope="row">' . esc_html__('Anomaly thresholds', 'fp-dms') . '</th><td>';
        echo '<table class="widefat striped" style="max-width:720px">';
        echo '<thead><tr><th>' . esc_html__('Metric', 'fp-dms') . '</th><th>' . esc_html__('Warn %', 'fp-dms') . '</th><th>' . esc_html__('Crit %', 'fp-dms') . '</th><th>' . esc_html__('Warn z', 'fp-dms') . '</th><th>' . esc_html__('Crit z', 'fp-dms') . '</th></tr></thead><tbody>';
        foreach ($policy['metrics'] as $metric => $values) {
            echo '<tr>';
            echo '<td>' . esc_html($metric) . '</td>';
            echo '<td><input type="number" step="0.1" class="small-text" name="anomaly_policy[metrics][' . esc_attr($metric) . '][warn_pct]" value="' . esc_attr((string) $values['warn_pct']) . '"></td>';
            echo '<td><input type="number" step="0.1" class="small-text" name="anomaly_policy[metrics][' . esc_attr($metric) . '][crit_pct]" value="' . esc_attr((string) $values['crit_pct']) . '"></td>';
            echo '<td><input type="number" step="0.1" class="small-text" name="anomaly_policy[metrics][' . esc_attr($metric) . '][z_warn]" value="' . esc_attr((string) $values['z_warn']) . '"></td>';
            echo '<td><input type="number" step="0.1" class="small-text" name="anomaly_policy[metrics][' . esc_attr($metric) . '][z_crit]" value="' . esc_attr((string) $values['z_crit']) . '"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Baseline defaults', 'fp-dms') . '</th><td>';
        echo '<label>' . esc_html__('Window days', 'fp-dms') . ' <input type="number" class="small-text" name="anomaly_policy[baseline][window_days]" value="' . esc_attr((string) $policy['baseline']['window_days']) . '"></label> ';
        echo '<label>' . esc_html__('Seasonality', 'fp-dms') . ' <input type="text" class="regular-text" name="anomaly_policy[baseline][seasonality]" value="' . esc_attr((string) $policy['baseline']['seasonality']) . '"></label> ';
        echo '<label>' . esc_html__('EWMA α', 'fp-dms') . ' <input type="number" step="0.01" class="small-text" name="anomaly_policy[baseline][ewma_alpha]" value="' . esc_attr((string) $policy['baseline']['ewma_alpha']) . '"></label> ';
        echo '<label>' . esc_html__('CUSUM k', 'fp-dms') . ' <input type="number" step="0.01" class="small-text" name="anomaly_policy[baseline][cusum_k]" value="' . esc_attr((string) $policy['baseline']['cusum_k']) . '"></label> ';
        echo '<label>' . esc_html__('CUSUM h', 'fp-dms') . ' <input type="number" step="0.01" class="small-text" name="anomaly_policy[baseline][cusum_h]" value="' . esc_attr((string) $policy['baseline']['cusum_h']) . '"></label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Routing defaults', 'fp-dms') . '</th><td>';
        echo '<p>' . esc_html__('Enable global channels and enter credentials used when client overrides are missing.', 'fp-dms') . '</p>';
        foreach ($policy['routing'] as $channel => $config) {
            echo '<fieldset style="margin-bottom:16px;padding:12px;border:1px solid #ddd;">';
            echo '<legend>' . esc_html(ucfirst(str_replace('_', ' ', $channel))) . '</legend>';
            echo '<label><input type="checkbox" name="anomaly_policy[routing][' . esc_attr($channel) . '][enabled]" value="1"' . checked(! empty($config['enabled']), true, false) . '> ' . esc_html__('Enabled', 'fp-dms') . '</label><br>';
            foreach ($config as $key => $value) {
                if ($key === 'enabled') {
                    continue;
                }
                $label = ucwords(str_replace('_', ' ', $key));
                $type = str_contains($key, 'token') || str_contains($key, 'secret') ? 'password' : 'text';
                echo '<label>' . esc_html($label) . ' <input type="' . esc_attr($type) . '" class="regular-text" name="anomaly_policy[routing][' . esc_attr($channel) . '][' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '"></label><br>';
            }
            if ($channel === 'email') {
                echo '<label>' . esc_html__('Digest window (minutes)', 'fp-dms') . ' <input type="number" class="small-text" name="anomaly_policy[routing][email][digest_window_min]" value="' . esc_attr((string) $config['digest_window_min']) . '"></label>';
            }
            echo '</fieldset>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Rate limiting', 'fp-dms') . '</th><td>';
        echo '<label>' . esc_html__('Cooldown (minutes)', 'fp-dms') . ' <input type="number" class="small-text" name="anomaly_policy[cooldown_min]" value="' . esc_attr((string) $policy['cooldown_min']) . '"></label> ';
        echo '<label>' . esc_html__('Max per window', 'fp-dms') . ' <input type="number" class="small-text" name="anomaly_policy[max_per_window]" value="' . esc_attr((string) $policy['max_per_window']) . '"></label>';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save Settings', 'fp-dms'));
        echo '</form>';

        $tickUrl = esc_url_raw(rest_url('fpdms/v1/tick?key=' . $settings['tick_key']));
        echo '<p><strong>' . esc_html__('Cron Fallback Endpoint:', 'fp-dms') . '</strong> <code>' . esc_html($tickUrl) . '</code></p>';
        echo '</div>';
    }

    private static function handlePost(): void
    {
        if (empty($_POST['fpdms_settings_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field((string) wp_unslash($_POST['fpdms_settings_nonce']));

        if (! wp_verify_nonce($nonce, 'fpdms_save_settings')) {
            return;
        }

        $post = wp_unslash($_POST);

        $action = sanitize_text_field((string) ($post['fpdms_settings_action'] ?? 'save'));
        $settings = Options::getGlobalSettings();

        if ($action === 'regenerate') {
            $settings['tick_key'] = wp_generate_password(32, false, false);
            Options::updateGlobalSettings($settings);
            add_settings_error('fpdms_settings', 'fpdms_settings_tick', __('Tick key regenerated.', 'fp-dms'), 'updated');
            return;
        }

        $email = sanitize_email((string) ($post['owner_email'] ?? ''));
        if ($email !== '' && ! Validation::isEmailList([$email])) {
            add_settings_error('fpdms_settings', 'fpdms_settings_email', __('Owner email is not valid.', 'fp-dms'));
        } else {
            $settings['owner_email'] = $email;
        }

        $retention = (int) ($post['retention_days'] ?? 90);
        $settings['retention_days'] = Validation::positiveInt($retention) ? $retention : 90;

        $overviewInput = isset($post['overview']) && is_array($post['overview']) ? $post['overview'] : [];
        $intervalInput = $overviewInput['refresh_intervals'] ?? '';
        $currentIntervals = $settings['overview']['refresh_intervals'] ?? Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        if (! is_array($currentIntervals)) {
            $currentIntervals = Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        }
        $settings['overview']['refresh_intervals'] = self::sanitizeRefreshIntervals($intervalInput, $currentIntervals);

        $brandingInput = isset($post['pdf_branding']) && is_array($post['pdf_branding']) ? $post['pdf_branding'] : [];
        $logoUrl = esc_url_raw((string) ($brandingInput['logo_url'] ?? ''));
        $settings['pdf_branding']['logo_url'] = Validation::safeUrl($logoUrl) ? $logoUrl : '';

        $primary = sanitize_text_field((string) ($brandingInput['primary_color'] ?? '#1d4ed8'));
        $settings['pdf_branding']['primary_color'] = Validation::isHexColor($primary) ? $primary : '#1d4ed8';
        $settings['pdf_branding']['footer_text'] = wp_kses_post((string) ($brandingInput['footer_text'] ?? ''));

        $mailInput = isset($post['mail']) && is_array($post['mail']) ? $post['mail'] : [];
        $smtpInput = isset($mailInput['smtp']) && is_array($mailInput['smtp']) ? $mailInput['smtp'] : [];

        $settings['mail']['smtp']['host'] = sanitize_text_field((string) ($smtpInput['host'] ?? ''));

        $defaultPort = (int) Options::defaultGlobalSettings()['mail']['smtp']['port'];
        $currentPort = (int) ($settings['mail']['smtp']['port'] ?? $defaultPort);
        if ($currentPort < 1 || $currentPort > 65535) {
            $currentPort = $defaultPort;
        }

        $submittedPort = isset($smtpInput['port']) ? absint($smtpInput['port']) : 0;
        if ($submittedPort < 1 || $submittedPort > 65535) {
            $submittedPort = $currentPort;
        }
        $settings['mail']['smtp']['port'] = $submittedPort;

        $secure = isset($smtpInput['secure']) ? sanitize_key((string) $smtpInput['secure']) : 'none';
        $settings['mail']['smtp']['secure'] = self::normaliseSecureMode($secure);
        $settings['mail']['smtp']['user'] = sanitize_text_field((string) ($smtpInput['user'] ?? ''));

        $existingCipher = isset($settings['mail']['smtp']['pass_cipher']) && is_string($settings['mail']['smtp']['pass_cipher'])
            ? $settings['mail']['smtp']['pass_cipher']
            : '';
        $submittedPass = isset($smtpInput['pass']) ? (string) $smtpInput['pass'] : '';

        if ($submittedPass === '') {
            $settings['mail']['smtp']['pass'] = '';
            $settings['mail']['smtp']['pass_cipher'] = $existingCipher;
        } else {
            $settings['mail']['smtp']['pass'] = $submittedPass;
            $settings['mail']['smtp']['pass_cipher'] = '';
        }

        $webhook = esc_url_raw((string) ($post['error_webhook_url'] ?? ''));
        $settings['error_webhook_url'] = Validation::safeUrl($webhook) ? $webhook : '';

        $policyInput = isset($post['anomaly_policy']) && is_array($post['anomaly_policy']) ? $post['anomaly_policy'] : [];
        $policyResult = Options::sanitizeAnomalyPolicyInput($policyInput, $settings['anomaly_policy']);
        $settings['anomaly_policy'] = $policyResult['policy'];
        if (! empty($policyResult['errors']['invalid_mute_timezone'])) {
            add_settings_error('fpdms_settings', 'fpdms_settings_mute_tz', __('Invalid mute timezone provided. Reverted to the previous value.', 'fp-dms'));
        }

        Options::updateGlobalSettings($settings);
        add_settings_error('fpdms_settings', 'fpdms_settings_saved', __('Settings saved.', 'fp-dms'), 'updated');
    }

    private static function normaliseSecureMode(string $mode): string
    {
        $normalized = strtolower($mode);

        return in_array($normalized, self::SMTP_SECURE_MODES, true) ? $normalized : 'none';
    }

    /**
     * @param mixed $input
     * @param array<int, int> $current
     * @return array<int, int>
     */
    private static function sanitizeRefreshIntervals(mixed $input, array $current): array
    {
        if (is_string($input)) {
            $parts = preg_split('/[\s,]+/', $input) ?: [];
        } elseif (is_array($input)) {
            $parts = $input;
        } else {
            $parts = [];
        }

        $intervals = [];

        foreach ($parts as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $seconds = absint($value);
            if ($seconds < 30 || $seconds > 3600) {
                continue;
            }

            $intervals[] = $seconds;
        }

        if (! is_array($current) || $current === []) {
            $current = Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        }

        if ($intervals === []) {
            $intervals = array_map('absint', $current);
        }

        $intervals = array_values(array_unique(array_map('absint', $intervals)));
        sort($intervals);

        if ($intervals === []) {
            $intervals = Options::defaultGlobalSettings()['overview']['refresh_intervals'];
        }

        return $intervals;
    }
}

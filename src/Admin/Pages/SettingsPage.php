<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Infra\Options;
use FP\DMS\Support\Validation;

class SettingsPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        self::handlePost();
        $settings = Options::getGlobalSettings();

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

        echo '<tr><th scope="row">' . esc_html__('Branding', 'fp-dms') . '</th>';
        echo '<td>';
        echo '<label for="fpdms-logo-url">' . esc_html__('Logo URL', 'fp-dms') . '</label><br>';
        echo '<input type="url" class="regular-text" id="fpdms-logo-url" name="pdf_branding[logo_url]" value="' . esc_attr($settings['pdf_branding']['logo_url']) . '"><br><br>';
        echo '<label for="fpdms-primary-color">' . esc_html__('Primary Color', 'fp-dms') . '</label><br>';
        echo '<input type="text" class="regular-text" id="fpdms-primary-color" name="pdf_branding[primary_color]" value="' . esc_attr($settings['pdf_branding']['primary_color']) . '"><br><br>';
        echo '<label for="fpdms-footer-text">' . esc_html__('Footer Text', 'fp-dms') . '</label><br>';
        echo '<textarea class="large-text" rows="3" id="fpdms-footer-text" name="pdf_branding[footer_text]">' . esc_textarea($settings['pdf_branding']['footer_text']) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('SMTP Settings', 'fp-dms') . '</th>';
        echo '<td>';
        echo '<label for="fpdms-smtp-host">' . esc_html__('Host', 'fp-dms') . '</label><br>';
        echo '<input type="text" class="regular-text" id="fpdms-smtp-host" name="mail[smtp][host]" value="' . esc_attr($settings['mail']['smtp']['host']) . '"><br><br>';
        echo '<label for="fpdms-smtp-port">' . esc_html__('Port', 'fp-dms') . '</label><br>';
        echo '<input type="number" class="small-text" id="fpdms-smtp-port" name="mail[smtp][port]" value="' . esc_attr((string) $settings['mail']['smtp']['port']) . '"><br><br>';
        echo '<label for="fpdms-smtp-secure">' . esc_html__('Secure Mode', 'fp-dms') . '</label><br>';
        echo '<select id="fpdms-smtp-secure" name="mail[smtp][secure]">';
        foreach (['none' => __('None', 'fp-dms'), 'ssl' => 'SSL', 'tls' => 'TLS'] as $value => $label) {
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

        if (! wp_verify_nonce(sanitize_text_field($_POST['fpdms_settings_nonce']), 'fpdms_save_settings')) {
            return;
        }

        $action = sanitize_text_field($_POST['fpdms_settings_action'] ?? 'save');
        $settings = Options::getGlobalSettings();

        if ($action === 'regenerate') {
            $settings['tick_key'] = wp_generate_password(32, false, false);
            Options::updateGlobalSettings($settings);
            add_settings_error('fpdms_settings', 'fpdms_settings_tick', __('Tick key regenerated.', 'fp-dms'), 'updated');
            return;
        }

        $email = sanitize_email($_POST['owner_email'] ?? '');
        if ($email !== '' && ! Validation::isEmailList([$email])) {
            add_settings_error('fpdms_settings', 'fpdms_settings_email', __('Owner email is not valid.', 'fp-dms'));
        } else {
            $settings['owner_email'] = $email;
        }

        $retention = (int) ($_POST['retention_days'] ?? 90);
        $settings['retention_days'] = Validation::positiveInt($retention) ? $retention : 90;

        $logoUrl = esc_url_raw($_POST['pdf_branding']['logo_url'] ?? '');
        $settings['pdf_branding']['logo_url'] = Validation::safeUrl($logoUrl) ? $logoUrl : '';

        $primary = sanitize_text_field($_POST['pdf_branding']['primary_color'] ?? '#1d4ed8');
        $settings['pdf_branding']['primary_color'] = Validation::isHexColor($primary) ? $primary : '#1d4ed8';
        $settings['pdf_branding']['footer_text'] = wp_kses_post($_POST['pdf_branding']['footer_text'] ?? '');
        $settings['mail']['smtp']['host'] = sanitize_text_field($_POST['mail']['smtp']['host'] ?? '');
        $settings['mail']['smtp']['port'] = sanitize_text_field($_POST['mail']['smtp']['port'] ?? '');
        $settings['mail']['smtp']['secure'] = sanitize_text_field($_POST['mail']['smtp']['secure'] ?? 'none');
        $settings['mail']['smtp']['user'] = sanitize_text_field($_POST['mail']['smtp']['user'] ?? '');
        $settings['mail']['smtp']['pass'] = sanitize_text_field($_POST['mail']['smtp']['pass'] ?? '');
        $webhook = esc_url_raw($_POST['error_webhook_url'] ?? '');
        $settings['error_webhook_url'] = Validation::safeUrl($webhook) ? $webhook : '';

        $policyInput = isset($_POST['anomaly_policy']) && is_array($_POST['anomaly_policy']) ? $_POST['anomaly_policy'] : [];
        $settings['anomaly_policy'] = self::sanitizeAnomalyPolicy($policyInput, $settings['anomaly_policy']);

        Options::updateGlobalSettings($settings);
        add_settings_error('fpdms_settings', 'fpdms_settings_saved', __('Settings saved.', 'fp-dms'), 'updated');
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $current
     * @return array<string,mixed>
     */
    private static function sanitizeAnomalyPolicy(array $input, array $current): array
    {
        $policy = Options::defaultAnomalyPolicy();
        $policy = array_replace_recursive($policy, $current);

        if (isset($input['metrics']) && is_array($input['metrics'])) {
            foreach ($policy['metrics'] as $metric => &$values) {
                $source = $input['metrics'][$metric] ?? [];
                foreach (['warn_pct', 'crit_pct', 'z_warn', 'z_crit'] as $field) {
                    if (isset($source[$field]) && is_numeric($source[$field])) {
                        $values[$field] = (float) $source[$field];
                    }
                }
            }
            unset($values);
        }

        if (isset($input['baseline']) && is_array($input['baseline'])) {
            foreach (['window_days', 'seasonality', 'ewma_alpha', 'cusum_k', 'cusum_h'] as $field) {
                if (! isset($input['baseline'][$field])) {
                    continue;
                }
                $value = $input['baseline'][$field];
                $policy['baseline'][$field] = is_numeric($value) ? (float) $value : sanitize_text_field((string) $value);
            }
            $policy['baseline']['window_days'] = (int) $policy['baseline']['window_days'];
        }

        if (isset($input['routing']) && is_array($input['routing'])) {
            foreach ($policy['routing'] as $channel => &$config) {
                $source = $input['routing'][$channel] ?? [];
                $config['enabled'] = ! empty($source['enabled']);
                foreach ($config as $key => &$value) {
                    if ($key === 'enabled' || ! isset($source[$key])) {
                        continue;
                    }
                    $raw = (string) $source[$key];
                    $value = str_contains($key, 'url') ? esc_url_raw($raw) : sanitize_text_field($raw);
                }
                unset($value);
            }
            unset($config);
        }

        if (isset($policy['routing']['email']['digest_window_min']) && isset($input['routing']['email']['digest_window_min'])) {
            $policy['routing']['email']['digest_window_min'] = (int) $input['routing']['email']['digest_window_min'];
        }

        if (isset($input['cooldown_min'])) {
            $policy['cooldown_min'] = (int) $input['cooldown_min'];
        }
        if (isset($input['max_per_window'])) {
            $policy['max_per_window'] = (int) $input['max_per_window'];
        }

        return $policy;
    }
}

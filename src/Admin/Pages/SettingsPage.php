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

        Options::updateGlobalSettings($settings);
        add_settings_error('fpdms_settings', 'fpdms_settings_saved', __('Settings saved.', 'fp-dms'), 'updated');
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Infra\Options;
use FP\DMS\Support\I18n;

class QaPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $notice = '';
        if (isset($_POST['fpdms_regenerate_qa_key'])) {
            check_admin_referer('fpdms_regenerate_qa_key');
            Options::regenerateQaKey();
            $notice = I18n::__('QA key regenerated successfully.');
        }

        $qaKey = Options::getQaKey();
        $restNonce = wp_create_nonce('wp_rest');
        $masked = self::maskKey($qaKey);
        $restBase = esc_url_raw(rest_url('fpdms/v1/qa/'));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(I18n::__('QA Automation')) . '</h1>';

        if ($notice !== '') {
            echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
        }

        echo '<p>' . esc_html(I18n::__('Trigger the automated QA harness to seed fixtures, run the reporting pipeline, and inspect results without using WP-CLI.')) . '</p>';

        echo '<form method="post" style="margin-bottom:1rem;display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">';
        wp_nonce_field('fpdms_regenerate_qa_key');
        echo '<div><label class="screen-reader-text" for="fpdms-qa-key">' . esc_html(I18n::__('QA key')) . '</label>';
        echo '<input type="text" id="fpdms-qa-key" class="regular-text" value="' . esc_attr($masked) . '" readonly />';
        echo '<p class="description">' . esc_html(I18n::__('Share this key only with trusted automation.')) . '</p></div>';
        echo '<div><button type="submit" name="fpdms_regenerate_qa_key" class="button button-secondary">' . esc_html(I18n::__('Regenerate QA key')) . '</button></div>';
        echo '</form>';

        echo '<div style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:0.5rem;">';
        $buttons = [
            'all' => I18n::__('Run All'),
            'seed' => I18n::__('Seed Data'),
            'run' => I18n::__('Run Report'),
            'anomalies' => I18n::__('Trigger Anomalies'),
            'status' => I18n::__('Show Status'),
            'cleanup' => I18n::__('Cleanup'),
        ];
        foreach ($buttons as $action => $label) {
            echo '<button type="button" class="button" data-fpdms-qa-action="' . esc_attr($action) . '">' . esc_html($label) . '</button>';
        }
        echo '</div>';

        echo '<textarea id="fpdms-qa-output" rows="14" style="width:100%;max-width:900px;" readonly></textarea>';

        echo '<script>'; ?>
        (function(){
            const restBase = '<?php echo esc_js($restBase); ?>';
            const nonce = '<?php echo esc_js($restNonce); ?>';
            const qaKey = '<?php echo esc_js($qaKey); ?>';
            const output = document.getElementById('fpdms-qa-output');

            function writeOutput(data) {
                try {
                    output.value = JSON.stringify(data, null, 2);
                } catch (err) {
                    output.value = 'Unable to parse response';
                }
            }

            function handleError(error) {
                writeOutput({ error: String(error) });
            }

            function call(action) {
                const method = action === 'status' ? 'GET' : 'POST';
                const init = {
                    method,
                    headers: {
                        'X-WP-Nonce': nonce,
                        'X-FPDMS-QA-KEY': qaKey
                    },
                    credentials: 'same-origin'
                };

                if (method === 'POST') {
                    init.headers['Content-Type'] = 'application/json';
                    init.body = JSON.stringify({});
                }

                fetch(restBase + action, init)
                    .then((response) => {
                        if (!response.ok) {
                            return response.json().then((payload) => { throw payload; });
                        }

                        return response.json();
                    })
                    .then(writeOutput)
                    .catch(handleError);
            }

            document.querySelectorAll('[data-fpdms-qa-action]').forEach(function(button){
                button.addEventListener('click', function(){
                    call(button.getAttribute('data-fpdms-qa-action'));
                });
            });
        })();
        <?php echo '</script>'; ?>
        <?php
        echo '</div>';
    }

    private static function maskKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return $key;
        }

        return substr($key, 0, 4) . str_repeat('•', max(0, strlen($key) - 8)) . substr($key, -4);
    }
}

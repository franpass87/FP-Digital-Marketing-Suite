<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use DateTimeZone;
use Exception;
use FP\DMS\Admin\Support\NoticeStore;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Services\Connectors\CentralServiceAccount;
use FP\DMS\Services\Connectors\ClientConnectorValidator;
use FP\DMS\Support\Wp;
use function __;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_js;
use function esc_textarea;
use function esc_url;
use function get_post;
use function sprintf;
use function wp_create_nonce;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_get_attachment_image_url;

class ClientsPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $repo = new ClientsRepo();
        self::handleActions($repo);

        $clients = $repo->all();
        $editing = null;
        if (isset($_GET['action'], $_GET['client']) && $_GET['action'] === 'edit') {
            $editing = $repo->find((int) $_GET['client']);
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Clients', 'fp-dms') . '</h1>';
        NoticeStore::flash('fpdms_clients');
        settings_errors('fpdms_clients');
        self::renderForm($editing);
        self::renderList($clients);
        echo '</div>';
    }

    private static function handleActions(ClientsRepo $repo): void
    {
        $post = Wp::unslash($_POST);

        if (! empty($post['fpdms_client_nonce']) && wp_verify_nonce(Wp::sanitizeTextField($post['fpdms_client_nonce'] ?? ''), 'fpdms_save_client')) {
            $id = isset($post['client_id']) ? (int) $post['client_id'] : 0;
            $existing = $id > 0 ? $repo->find($id) : null;
            $data = [
                'name' => Wp::sanitizeTextField($post['name'] ?? ''),
                'email_to' => self::sanitizeEmails((string) ($post['email_to'] ?? '')),
                'email_cc' => self::sanitizeEmails((string) ($post['email_cc'] ?? '')),
                'timezone' => Wp::sanitizeTextField($post['timezone'] ?? 'UTC'),
                'notes' => Wp::ksesPost((string) ($post['notes'] ?? '')),
                'logo_id' => self::sanitizeLogoId($post['logo_id'] ?? null),
                'ga4_property_id' => ClientConnectorValidator::sanitizeGa4PropertyId($post['ga4_property_id'] ?? ''),
                'ga4_stream_id' => ClientConnectorValidator::sanitizeGa4StreamId($post['ga4_stream_id'] ?? ''),
                'ga4_measurement_id' => ClientConnectorValidator::sanitizeGa4MeasurementId($post['ga4_measurement_id'] ?? ''),
                'gsc_site_property' => ClientConnectorValidator::sanitizeGscSiteProperty($post['gsc_site_property'] ?? ''),
            ];

            $fallbackTz = $existing?->timezone ?? 'UTC';
            $normalizedTimezone = self::normalizeTimezone($data['timezone'], $fallbackTz);
            if ($normalizedTimezone !== $data['timezone']) {
                if ($data['timezone'] !== '') {
                    NoticeStore::enqueue(
                        'fpdms_clients',
                        'fpdms_client_timezone',
                        __('Invalid timezone provided. Using the previous value instead.', 'fp-dms'),
                        'error'
                    );
                }
                $data['timezone'] = $normalizedTimezone;
            }

            if ($id > 0) {
                if ($repo->update($id, $data)) {
                    NoticeStore::enqueue('fpdms_clients', 'fpdms_client_saved', __('Client updated.', 'fp-dms'), 'updated');
                } else {
                    NoticeStore::enqueue('fpdms_clients', 'fpdms_client_error', __('Failed to update client.', 'fp-dms'), 'error');
                }
            } else {
                $client = $repo->create($data);
                if ($client === null) {
                    NoticeStore::enqueue('fpdms_clients', 'fpdms_client_error', __('Failed to save client.', 'fp-dms'), 'error');
                } else {
                    NoticeStore::enqueue('fpdms_clients', 'fpdms_client_saved', __('Client created.', 'fp-dms'), 'updated');
                }
            }

            wp_safe_redirect(add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php')));
            exit;
        }

        $query = Wp::unslash($_GET);
        if (isset($query['action'], $query['client']) && $query['action'] === 'delete') {
            $clientId = (int) $query['client'];
            $nonce = Wp::sanitizeTextField($query['_wpnonce'] ?? '');
            if (wp_verify_nonce($nonce, 'fpdms_delete_client_' . $clientId)) {
                $repo->delete($clientId);
                NoticeStore::enqueue('fpdms_clients', 'fpdms_client_deleted', __('Client deleted.', 'fp-dms'), 'updated');
            }
            wp_safe_redirect(add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * @param array<int,\FP\DMS\Domain\Entities\Client> $clients
     */
    private static function renderList(array $clients): void
    {
        echo '<h2>' . esc_html__('Existing Clients', 'fp-dms') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Name', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Logo', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Emails', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Timezone', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Actions', 'fp-dms') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($clients)) {
            echo '<tr><td colspan="5">' . esc_html__('No clients found.', 'fp-dms') . '</td></tr>';
        }

        foreach ($clients as $client) {
            $editUrl = add_query_arg([
                'page' => 'fp-dms-clients',
                'action' => 'edit',
                'client' => $client->id,
            ], admin_url('admin.php'));
            $deleteUrl = wp_nonce_url(add_query_arg([
                'page' => 'fp-dms-clients',
                'action' => 'delete',
                'client' => $client->id,
            ], admin_url('admin.php')), 'fpdms_delete_client_' . $client->id);

            echo '<tr>';
            echo '<td>' . esc_html($client->name) . '</td>';
            $logo = $client->logoId ? wp_get_attachment_image_url($client->logoId, 'thumbnail') : false;
            if ($logo) {
                echo '<td><img src="' . esc_url((string) $logo) . '" alt="' . esc_attr($client->name) . '" style="max-width:60px;height:auto;"></td>';
            } else {
                echo '<td><span style="color:#94a3b8;">' . esc_html__('—', 'fp-dms') . '</span></td>';
            }
            echo '<td>' . esc_html(implode(', ', array_merge($client->emailTo, $client->emailCc))) . '</td>';
            echo '<td>' . esc_html($client->timezone) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'fp-dms') . '</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'' . esc_js(__('Delete this client?', 'fp-dms')) . '\');">' . esc_html__('Delete', 'fp-dms') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function renderForm(?\FP\DMS\Domain\Entities\Client $client): void
    {
        $title = $client ? __('Edit Client', 'fp-dms') : __('Add Client', 'fp-dms');
        $emailTo = $client ? implode(', ', $client->emailTo) : '';
        $emailCc = $client ? implode(', ', $client->emailCc) : '';

        wp_enqueue_media();
        wp_enqueue_script('jquery');
        $logoId = $client?->logoId ?? null;
        $logoUrl = $logoId ? wp_get_attachment_image_url($logoId, 'medium') : false;
        $logoSrc = $logoUrl ? (string) $logoUrl : '';
        $ga4PropertyId = $client?->ga4PropertyId ?? '';
        $ga4StreamId = $client?->ga4StreamId ?? '';
        $ga4MeasurementId = $client?->ga4MeasurementId ?? '';
        $gscSiteProperty = $client?->gscSiteProperty ?? '';
        $ajaxNonce = wp_create_nonce('fpdms_test_connector');
        $hasGa4ServiceAccount = CentralServiceAccount::getJson('ga4') !== '';
        $hasGscServiceAccount = CentralServiceAccount::getJson('gsc') !== '';
        $documentationUrl = 'https://github.com/francescopasseri/FP-Digital-Marketing-Suite/blob/main/docs/faq.md';

        echo '<div class="card" style="max-width:800px;margin-top:20px;padding:20px;">';
        echo '<h2>' . esc_html($title) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('fpdms_save_client', 'fpdms_client_nonce');
        echo '<input type="hidden" name="client_id" value="' . esc_attr((string) ($client->id ?? 0)) . '">';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="fpdms-name">' . esc_html__('Name', 'fp-dms') . '</label></th>';
        echo '<td><input name="name" type="text" id="fpdms-name" class="regular-text" value="' . esc_attr($client->name ?? '') . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-email-to">' . esc_html__('Emails TO', 'fp-dms') . '</label></th>';
        echo '<td><input name="email_to" type="text" id="fpdms-email-to" class="regular-text" value="' . esc_attr($emailTo) . '"><p class="description">' . esc_html__('Comma separated list.', 'fp-dms') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-email-cc">' . esc_html__('Emails CC', 'fp-dms') . '</label></th>';
        echo '<td><input name="email_cc" type="text" id="fpdms-email-cc" class="regular-text" value="' . esc_attr($emailCc) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-timezone">' . esc_html__('Timezone', 'fp-dms') . '</label></th>';
        echo '<td><input name="timezone" type="text" id="fpdms-timezone" class="regular-text" value="' . esc_attr($client->timezone ?? 'UTC') . '"></td></tr>';

        echo '<tr><th scope="row"><label>' . esc_html__('Logo', 'fp-dms') . '</label></th>';
        echo '<td>';
        echo '<div id="fpdms-logo-preview" style="margin-bottom:12px;max-width:200px;min-height:60px;display:flex;align-items:center;justify-content:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">';
        if ($logoSrc !== '') {
            echo '<img src="' . esc_url($logoSrc) . '" alt="' . esc_attr($client->name ?? '') . '" style="max-width:100%;height:auto;">';
        } else {
            echo '<span style="color:#64748b;">' . esc_html__('No logo selected', 'fp-dms') . '</span>';
        }
        echo '</div>';
        echo '<input type="hidden" name="logo_id" id="fpdms-logo-id" value="' . esc_attr($logoId ? (string) $logoId : '') . '">';
        echo '<button type="button" class="button" id="fpdms-logo-select">' . esc_html__('Select logo', 'fp-dms') . '</button> ';
        $removeStyle = $logoId ? '' : 'style="display:none;"';
        echo '<button type="button" class="button-link-delete" id="fpdms-logo-remove" ' . $removeStyle . '>' . esc_html__('Remove logo', 'fp-dms') . '</button>';
        echo '<p class="description" style="margin-top:8px;">' . esc_html__('Pick an image from the media library to personalise reports for this client.', 'fp-dms') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-notes">' . esc_html__('Notes', 'fp-dms') . '</label></th>';
        echo '<td><textarea name="notes" id="fpdms-notes" class="large-text" rows="4">' . esc_textarea($client->notes ?? '') . '</textarea></td></tr>';

        echo '</tbody></table>';
        echo '<input type="hidden" id="fpdms-test-connector-nonce" value="' . esc_attr($ajaxNonce) . '">';
        echo '<div class="fpdms-connectors" style="margin-top:24px;">';
        echo '<h2>' . esc_html__('Connettori dati', 'fp-dms') . '</h2>';
        echo '<div class="notice notice-info"><p>' . esc_html__('Il plugin usa un account di servizio centrale; non serve caricare JSON per cliente.', 'fp-dms') . '</p></div>';

        if (! $hasGa4ServiceAccount) {
            /* translators: 1: constant name, 2: opening link tag, 3: closing link tag */
            $ga4DocMessage = sprintf(
                esc_html__('Definisci la costante %1$s in wp-config.php oppure carica il JSON centrale. Consulta la %2$sdocumentazione%3$s.', 'fp-dms'),
                'FPDMS_GA4_SERVICE_ACCOUNT',
                '<a href="' . esc_url($documentationUrl) . '" target="_blank" rel="noopener noreferrer">',
                '</a>'
            );
            echo '<div class="notice notice-error"><p>' . Wp::ksesPost($ga4DocMessage) . '</p></div>';
        }

        if (! $hasGscServiceAccount) {
            /* translators: 1: constant name, 2: opening link tag, 3: closing link tag */
            $gscDocMessage = sprintf(
                esc_html__('Definisci la costante %1$s in wp-config.php oppure carica il JSON centrale. Consulta la %2$sdocumentazione%3$s.', 'fp-dms'),
                'FPDMS_GSC_SERVICE_ACCOUNT',
                '<a href="' . esc_url($documentationUrl) . '" target="_blank" rel="noopener noreferrer">',
                '</a>'
            );
            echo '<div class="notice notice-error"><p>' . Wp::ksesPost($gscDocMessage) . '</p></div>';
        }

        echo '<h2 class="nav-tab-wrapper" id="fpdms-connector-tabs">';
        echo '<a href="#" class="nav-tab nav-tab-active" data-target="ga4">' . esc_html__('Google Analytics 4', 'fp-dms') . '</a>';
        echo '<a href="#" class="nav-tab" data-target="gsc">' . esc_html__('Google Search Console', 'fp-dms') . '</a>';
        echo '</h2>';

        echo '<div class="fpdms-connector-panel is-active" id="fpdms-connector-panel-ga4">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="fpdms-ga4-property-id">' . esc_html__('ID proprietà GA4', 'fp-dms') . '</label></th>';
        echo '<td><input name="ga4_property_id" type="text" id="fpdms-ga4-property-id" class="regular-text" value="' . esc_attr($ga4PropertyId) . '" placeholder="123456789">';
        echo '<p class="description">' . esc_html__('Inserisci l’ID numerico della proprietà GA4.', 'fp-dms') . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="fpdms-ga4-stream-id">' . esc_html__('ID stream GA4', 'fp-dms') . '</label></th>';
        echo '<td><input name="ga4_stream_id" type="text" id="fpdms-ga4-stream-id" class="regular-text" value="' . esc_attr($ga4StreamId) . '" placeholder="1234567890">';
        echo '<p class="description">' . esc_html__('Inserisci lo stream Web associato (valore numerico).', 'fp-dms') . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="fpdms-ga4-measurement-id">' . esc_html__('Measurement ID', 'fp-dms') . '</label></th>';
        echo '<td><input name="ga4_measurement_id" type="text" id="fpdms-ga4-measurement-id" class="regular-text" value="' . esc_attr($ga4MeasurementId) . '" placeholder="G-XXXXXXX">';
        echo '<p class="description">' . esc_html__('Formato es. G-XXXXXXXX per il tag di misurazione.', 'fp-dms') . '</p></td></tr>';
        echo '</tbody></table>';
        echo '<p><button type="button" class="button fpdms-test-connector" data-connector="ga4"' . ($hasGa4ServiceAccount ? '' : ' disabled') . '>' . esc_html__('Verifica connessione', 'fp-dms') . '</button> <span class="fpdms-connector-status" data-target="ga4"></span></p>';
        echo '</div>';

        echo '<div class="fpdms-connector-panel" id="fpdms-connector-panel-gsc">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="fpdms-gsc-site-property">' . esc_html__('Proprietà Search Console', 'fp-dms') . '</label></th>';
        echo '<td><input name="gsc_site_property" type="text" id="fpdms-gsc-site-property" class="regular-text" value="' . esc_attr($gscSiteProperty) . '" placeholder="https://example.com/">';
        echo '<p class="description">' . esc_html__('Usa l’identificatore esatto (es. sc-domain:example.com o URL completo).', 'fp-dms') . '</p></td></tr>';
        echo '</tbody></table>';
        echo '<p><button type="button" class="button fpdms-test-connector" data-connector="gsc"' . ($hasGscServiceAccount ? '' : ' disabled') . '>' . esc_html__('Verifica connessione', 'fp-dms') . '</button> <span class="fpdms-connector-status" data-target="gsc"></span></p>';
        echo '</div>';

        echo '</div>';
        echo '<style>.fpdms-connector-panel{display:none;margin-top:16px;}.fpdms-connector-panel.is-active{display:block;}.fpdms-connector-status{margin-left:12px;font-weight:600;}.fpdms-connector-status.success{color:#047857;}.fpdms-connector-status.error{color:#b91c1c;}</style>';

        submit_button($client ? __('Update Client', 'fp-dms') : __('Add Client', 'fp-dms'));
        echo '</form>';
        $placeholderText = esc_js(__('No logo selected', 'fp-dms'));
        $chooseLogoText = esc_js(__('Choose logo', 'fp-dms'));
        $useImageText = esc_js(__('Use image', 'fp-dms'));
        $testingText = esc_js(__('Verifica in corso…', 'fp-dms'));
        $successText = esc_js(__('Connessione verificata.', 'fp-dms'));
        $genericErrorText = esc_js(__('Impossibile verificare la connessione.', 'fp-dms'));

        $scriptLines = [
            '<script type="text/javascript">',
            '(function($){',
            '    var frame;',
            '    var selectButton = $("#fpdms-logo-select");',
            '    var removeButton = $("#fpdms-logo-remove");',
            '    var input = $("#fpdms-logo-id");',
            '    var preview = $("#fpdms-logo-preview");',
            '    var testingText = "' . $testingText . '";',
            '    var successText = "' . $successText . '";',
            '    var genericErrorText = "' . $genericErrorText . '";',
            '    var connectorTabs = $("#fpdms-connector-tabs .nav-tab");',
            '    var connectorPanels = $(".fpdms-connector-panel");',
            '    var ajaxNonce = $("#fpdms-test-connector-nonce").val();',
            '',
            '    function renderPreview(url){',
            '        preview.empty();',
            '        if (url) {',
            '            preview.append($("<img>").attr("src", url).attr("alt", "logo").css({"max-width":"100%","height":"auto"}));',
            '        } else {',
            '            preview.append($("<span>").text(selectButton.data("placeholder")).css({color: "#64748b"}));',
            '        }',
            '    }',
            '',
            '    selectButton.data("placeholder", "' . $placeholderText . '");',
            '',
            '    if (typeof wp !== "undefined" && wp.media) {',
            '        selectButton.on("click", function(e){',
            '            e.preventDefault();',
            '            if (frame) {',
            '                frame.open();',
            '                return;',
            '            }',
            '',
            '            frame = wp.media({',
            '                title: "' . $chooseLogoText . '",',
            '                button: { text: "' . $useImageText . '" },',
            '                library: { type: "image" },',
            '                multiple: false',
            '            });',
            '',
            '            frame.on("select", function(){',
            '                var attachment = frame.state().get("selection").first().toJSON();',
            '                input.val(attachment.id);',
            '                var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;',
            '                renderPreview(previewUrl);',
            '                removeButton.show();',
            '            });',
            '',
            '            frame.open();',
            '        });',
            '',
            '        removeButton.on("click", function(e){',
            '            e.preventDefault();',
            '            input.val("");',
            '            renderPreview("");',
            '            removeButton.hide();',
            '        });',
            '    }',
            '',
            '    if (!input.val()) {',
            '        renderPreview("");',
            '    }',
            '',
            '    function activateConnector(tab){',
            '        if (!tab || !tab.length) {',
            '            return;',
            '        }',
            '        connectorTabs.removeClass("nav-tab-active");',
            '        var current = $(tab);',
            '        current.addClass("nav-tab-active");',
            '        var target = current.data("target");',
            '        connectorPanels.removeClass("is-active");',
            '        if (target) {',
            '            $("#fpdms-connector-panel-" + target).addClass("is-active");',
            '        }',
            '    }',
            '',
            '    connectorTabs.on("click", function(e){',
            '        e.preventDefault();',
            '        activateConnector($(this));',
            '    });',
            '',
            '    if (connectorTabs.length) {',
            '        activateConnector(connectorTabs.first());',
            '    }',
            '',
            '    $(".fpdms-test-connector").on("click", function(e){',
            '        e.preventDefault();',
            '        var button = $(this);',
            '        if (button.is(":disabled")) {',
            '            return;',
            '        }',
            '        var connector = button.data("connector");',
            '        var status = $(".fpdms-connector-status[data-target=\"" + connector + "\"]");',
            '        var payload = {',
            '            action: "fpdms_test_connector",',
            '            _ajax_nonce: ajaxNonce,',
            '            connector_type: connector,',
            '            client_id: $("input[name=client_id]").val() || "0"',
            '        };',
            '        if (connector === "ga4") {',
            '            payload.property_id = $("#fpdms-ga4-property-id").val();',
            '            payload.stream_id = $("#fpdms-ga4-stream-id").val();',
            '            payload.measurement_id = $("#fpdms-ga4-measurement-id").val();',
            '        } else {',
            '            payload.site_property = $("#fpdms-gsc-site-property").val();',
            '        }',
            '        status.removeClass("success error").text(testingText);',
            '        button.prop("disabled", true);',
            '        $.post(ajaxurl, payload).done(function(response){',
            '            if (response && response.success && response.data && response.data.ok) {',
            '                status.text(response.data.message || successText).removeClass("error").addClass("success");',
            '            } else if (response && response.data) {',
            '                status.text(response.data.message || genericErrorText).removeClass("success").addClass("error");',
            '            } else {',
            '                status.text(genericErrorText).removeClass("success").addClass("error");',
            '            }',
            '        }).fail(function(){',
            '            status.text(genericErrorText).removeClass("success").addClass("error");',
            '        }).always(function(){',
            '            button.prop("disabled", false);',
            '        });',
            '    });',
            '})(jQuery);',
            '</script>',
        ];
        echo implode("\n", $scriptLines);
        echo '</div>';
    }

    /**
     * @return string[]
     */
    private static function sanitizeEmails(string $value): array
    {
        $parts = array_filter(array_map('trim', explode(',', $value)));
        $valid = [];
        foreach ($parts as $email) {
            $sanitized = Wp::sanitizeEmail($email);
            if ($sanitized === '' || ! Wp::isEmail($sanitized)) {
                continue;
            }

            $normalized = strtolower($sanitized);
            if (isset($valid[$normalized])) {
                continue;
            }

            $valid[$normalized] = $sanitized;
        }

        return array_values($valid);
    }

    private static function sanitizeLogoId(mixed $value): ?int
    {
        $id = Wp::absInt($value);
        if ($id <= 0) {
            return null;
        }

        $post = get_post($id);
        if (! $post || $post->post_type !== 'attachment') {
            return null;
        }

        return $id;
    }

    private static function normalizeTimezone(string $timezone, string $fallback): string
    {
        $candidate = trim($timezone);
        if ($candidate === '') {
            return $fallback;
        }

        try {
            new DateTimeZone($candidate);

            return $candidate;
        } catch (Exception $exception) {
            return $fallback;
        }
    }
}

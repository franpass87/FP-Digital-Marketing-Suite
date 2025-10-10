<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use DateTimeZone;
use Exception;
use FP\DMS\Admin\Support\NoticeStore;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Support\Wp;

use function __;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_js;
use function esc_textarea;
use function esc_url;
use function get_post;
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
        $clientName = $client->name ?? '';
        $logoAlt = $clientName !== '' ? $clientName : __('Client logo', 'fp-dms');
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
        echo '<div id="fpdms-logo-preview" data-client-name="' . esc_attr($clientName) . '" style="margin-bottom:12px;max-width:200px;min-height:60px;display:flex;align-items:center;justify-content:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">';
        if ($logoSrc !== '') {
            echo '<img src="' . esc_url($logoSrc) . '" alt="' . esc_attr($logoAlt) . '" style="max-width:100%;height:auto;">';
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
        submit_button($client ? __('Update Client', 'fp-dms') : __('Add Client', 'fp-dms'));
        echo '</form>';
        $placeholderText = esc_js(__('No logo selected', 'fp-dms'));
        $chooseLogoText = esc_js(__('Choose logo', 'fp-dms'));
        $useImageText = esc_js(__('Use image', 'fp-dms'));
        $defaultAltText = esc_js(__('Client logo', 'fp-dms'));

        $scriptLines = [
            '<script type="text/javascript">',
            '(function($){',
            '    var frame;',
            '    var selectButton = $("#fpdms-logo-select");',
            '    var removeButton = $("#fpdms-logo-remove");',
            '    var input = $("#fpdms-logo-id");',
            '    var preview = $("#fpdms-logo-preview");',
            '    var nameField = $("#fpdms-name");',
            '    var placeholder = "' . $placeholderText . '";',
            '    var clientName = preview.data("client-name") || "";',
            '    var fallbackAlt = "' . $defaultAltText . '";',
            '    var defaultAlt = clientName ? clientName : fallbackAlt;',
            '',
            '    function renderPreview(url, alt){',
            '        preview.empty();',
            '        if (url) {',
            '            var altText = alt || defaultAlt;',
            '            preview.append($("<img>").attr({src: url, alt: altText}).css({"max-width":"100%","height":"auto"}));',
            '        } else {',
            '            preview.append($("<span>").text(placeholder).css({color: "#64748b"}));',
            '        }',
            '    }',
            '',
            '    function updateDefaultAlt(){',
            '        var candidate = $.trim(nameField.val());',
            '        defaultAlt = candidate !== "" ? candidate : fallbackAlt;',
            '        preview.attr("data-client-name", defaultAlt);',
            '        var img = preview.find("img");',
            '        if (img.length) {',
            '            img.attr("alt", defaultAlt);',
            '        }',
            '    }',
            '',
            '    if (typeof wp !== "undefined" && wp.media) {',
            '        selectButton.on("click", function(e){',
            '            e.preventDefault();',
            '            if (!frame) {',
            '                frame = wp.media({',
            '                    title: "' . $chooseLogoText . '",',
            '                    button: { text: "' . $useImageText . '" },',
            '                    library: { type: "image" },',
            '                    multiple: false',
            '                });',
            '',
            '                frame.on("select", function(){',
            '                    var attachment = frame.state().get("selection").first().toJSON();',
            '                    input.val(attachment.id);',
            '                    var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;',
            '                    var alt = attachment.alt || attachment.title || defaultAlt;',
            '                    renderPreview(previewUrl, alt);',
            '                    removeButton.show();',
            '                });',
            '            }',
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
            '    } else {',
            '        selectButton.on("click", function(e){',
            '            e.preventDefault();',
            '        });',
            '    }',
            '',
            '    nameField.on("input change", updateDefaultAlt);',
            '    updateDefaultAlt();',
            '',
            '    if (!input.val()) {',
            '        renderPreview("");',
            '    }',
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

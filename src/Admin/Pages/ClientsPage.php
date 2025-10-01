<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use DateTimeZone;
use Exception;
use FP\DMS\Admin\Support\NoticeStore;
use FP\DMS\Domain\Repos\ClientsRepo;
use function __;
use function is_email;
use function wp_unslash;

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
        $post = wp_unslash($_POST);

        if (! empty($post['fpdms_client_nonce']) && wp_verify_nonce(sanitize_text_field((string) ($post['fpdms_client_nonce'] ?? '')), 'fpdms_save_client')) {
            $id = isset($post['client_id']) ? (int) $post['client_id'] : 0;
            $existing = $id > 0 ? $repo->find($id) : null;
            $data = [
                'name' => sanitize_text_field((string) ($post['name'] ?? '')),
                'email_to' => self::sanitizeEmails((string) ($post['email_to'] ?? '')),
                'email_cc' => self::sanitizeEmails((string) ($post['email_cc'] ?? '')),
                'timezone' => sanitize_text_field((string) ($post['timezone'] ?? 'UTC')),
                'notes' => wp_kses_post((string) ($post['notes'] ?? '')),
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

        $query = wp_unslash($_GET);
        if (isset($query['action'], $query['client']) && $query['action'] === 'delete') {
            $clientId = (int) $query['client'];
            $nonce = sanitize_text_field((string) ($query['_wpnonce'] ?? ''));
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
        echo '<th>' . esc_html__('Emails', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Timezone', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Actions', 'fp-dms') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($clients)) {
            echo '<tr><td colspan="4">' . esc_html__('No clients found.', 'fp-dms') . '</td></tr>';
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

        echo '<tr><th scope="row"><label for="fpdms-notes">' . esc_html__('Notes', 'fp-dms') . '</label></th>';
        echo '<td><textarea name="notes" id="fpdms-notes" class="large-text" rows="4">' . esc_textarea($client->notes ?? '') . '</textarea></td></tr>';

        echo '</tbody></table>';
        submit_button($client ? __('Update Client', 'fp-dms') : __('Add Client', 'fp-dms'));
        echo '</form>';
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
            $sanitized = sanitize_email($email);
            if ($sanitized === '' || ! is_email($sanitized)) {
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

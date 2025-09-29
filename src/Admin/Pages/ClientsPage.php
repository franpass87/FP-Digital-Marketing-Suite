<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Repos\ClientsRepo;

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
        self::renderForm($editing);
        self::renderList($clients);
        echo '</div>';
    }

    private static function handleActions(ClientsRepo $repo): void
    {
        if (! empty($_POST['fpdms_client_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['fpdms_client_nonce']), 'fpdms_save_client')) {
            $id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
            $data = [
                'name' => sanitize_text_field($_POST['name'] ?? ''),
                'email_to' => self::sanitizeEmails($_POST['email_to'] ?? ''),
                'email_cc' => self::sanitizeEmails($_POST['email_cc'] ?? ''),
                'timezone' => sanitize_text_field($_POST['timezone'] ?? 'UTC'),
                'notes' => wp_kses_post($_POST['notes'] ?? ''),
            ];

            if ($id > 0) {
                $repo->update($id, $data);
                add_settings_error('fpdms_clients', 'fpdms_client_saved', __('Client updated.', 'fp-dms'), 'updated');
            } else {
                $client = $repo->create($data);
                if ($client === null) {
                    add_settings_error('fpdms_clients', 'fpdms_client_error', __('Failed to save client.', 'fp-dms'));
                } else {
                    add_settings_error('fpdms_clients', 'fpdms_client_saved', __('Client created.', 'fp-dms'), 'updated');
                }
            }

            set_transient('fpdms_clients_notices', get_settings_errors('fpdms_clients'), 30);
            wp_safe_redirect(add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php')));
            exit;
        }

        if (isset($_GET['action'], $_GET['client']) && $_GET['action'] === 'delete') {
            $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
            if (wp_verify_nonce($nonce, 'fpdms_delete_client_' . (int) $_GET['client'])) {
                $repo->delete((int) $_GET['client']);
                add_settings_error('fpdms_clients', 'fpdms_client_deleted', __('Client deleted.', 'fp-dms'), 'updated');
                set_transient('fpdms_clients_notices', get_settings_errors('fpdms_clients'), 30);
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
        $stored = get_transient('fpdms_clients_notices');
        if (is_array($stored)) {
            foreach ($stored as $notice) {
                add_settings_error(
                    'fpdms_clients',
                    $notice['code'] ?? uniqid('fpdms', true),
                    $notice['message'] ?? '',
                    $notice['type'] ?? 'updated'
                );
            }
            delete_transient('fpdms_clients_notices');
        }

        settings_errors('fpdms_clients');

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
            if ($sanitized !== '') {
                $valid[] = $sanitized;
            }
        }

        return $valid;
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Support\NoticeStore;
use FP\DMS\Domain\Entities\Template;
use FP\DMS\Domain\Repos\TemplatesRepo;
use function wp_unslash;

class TemplatesPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $repo = new TemplatesRepo();
        self::handleActions($repo);
        NoticeStore::flash('fpdms_templates');

        $editing = null;
        if (isset($_GET['action'], $_GET['template']) && $_GET['action'] === 'edit') {
            $editing = $repo->find((int) $_GET['template']);
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Templates', 'fp-dms') . '</h1>';
        settings_errors('fpdms_templates');

        self::renderForm($editing);
        self::renderList($repo->all());
        echo '</div>';
    }

    private static function handleActions(TemplatesRepo $repo): void
    {
        $post = wp_unslash($_POST);
        if (! empty($post['fpdms_template_nonce']) && wp_verify_nonce(sanitize_text_field((string) ($post['fpdms_template_nonce'] ?? '')), 'fpdms_save_template')) {
            $id = isset($post['template_id']) ? (int) $post['template_id'] : 0;
            $data = [
                'name' => sanitize_text_field($post['name'] ?? ''),
                'description' => sanitize_text_field($post['description'] ?? ''),
                'content' => wp_kses_post($post['content'] ?? ''),
                'is_default' => ! empty($post['is_default']) ? 1 : 0,
            ];

            if ($id > 0) {
                if ($repo->update($id, $data)) {
                    NoticeStore::enqueue('fpdms_templates', 'fpdms_template_saved', __('Template updated.', 'fp-dms'));
                } else {
                    NoticeStore::enqueue('fpdms_templates', 'fpdms_template_error', __('Failed to update template.', 'fp-dms'), 'error');
                }
            } else {
                $repo->create($data);
                NoticeStore::enqueue('fpdms_templates', 'fpdms_template_saved', __('Template created.', 'fp-dms'));
            }

            self::redirectToIndex();
            return;
        }

        $query = wp_unslash($_GET);
        if (isset($query['action'], $query['template']) && $query['action'] === 'delete') {
            $templateId = (int) $query['template'];
            $nonce = sanitize_text_field((string) ($query['_wpnonce'] ?? ''));
            if (wp_verify_nonce($nonce, 'fpdms_delete_template_' . $templateId)) {
                if ($repo->delete($templateId)) {
                    NoticeStore::enqueue('fpdms_templates', 'fpdms_template_deleted', __('Template deleted.', 'fp-dms'));
                } else {
                    NoticeStore::enqueue('fpdms_templates', 'fpdms_template_error', __('Failed to delete template.', 'fp-dms'), 'error');
                }
                self::redirectToIndex();
            }
        }
    }

    private static function redirectToIndex(): void
    {
        $url = admin_url('admin.php?page=fp-dms-templates');
        wp_safe_redirect($url);
        exit;
    }

    private static function renderForm(?Template $template): void
    {
        $title = $template ? __('Edit template', 'fp-dms') : __('Create template', 'fp-dms');
        echo '<div class="card" style="margin-top:20px;padding:20px;max-width:900px;">';
        echo '<h2>' . esc_html($title) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('fpdms_save_template', 'fpdms_template_nonce');
        echo '<input type="hidden" name="template_id" value="' . esc_attr((string) ($template->id ?? 0)) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="fpdms-template-name">' . esc_html__('Name', 'fp-dms') . '</label></th><td><input class="regular-text" id="fpdms-template-name" type="text" name="name" value="' . esc_attr($template->name ?? '') . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="fpdms-template-description">' . esc_html__('Description', 'fp-dms') . '</label></th><td><input class="regular-text" id="fpdms-template-description" type="text" name="description" value="' . esc_attr($template->description ?? '') . '"></td></tr>';
        echo '<tr><th scope="row"><label for="fpdms-template-content">' . esc_html__('Content', 'fp-dms') . '</label></th><td><textarea name="content" id="fpdms-template-content" class="large-text code" rows="12">' . esc_textarea($template->content ?? '') . '</textarea><p class="description">' . esc_html__('Use placeholders like {{client.name}}, {{period.start}}, {{kpi.ga4.users|number}}.', 'fp-dms') . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Default', 'fp-dms') . '</th><td><label><input type="checkbox" name="is_default" value="1"' . checked($template?->isDefault ?? false, true, false) . '> ' . esc_html__('Make this the default template', 'fp-dms') . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button($template ? __('Update template', 'fp-dms') : __('Create template', 'fp-dms'));
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param array<int,Template> $templates
     */
    private static function renderList(array $templates): void
    {
        echo '<h2 style="margin-top:40px;">' . esc_html__('Available templates', 'fp-dms') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__('Name', 'fp-dms') . '</th><th>' . esc_html__('Description', 'fp-dms') . '</th><th>' . esc_html__('Default', 'fp-dms') . '</th><th>' . esc_html__('Actions', 'fp-dms') . '</th></tr></thead><tbody>';
        if (empty($templates)) {
            echo '<tr><td colspan="4">' . esc_html__('No templates yet.', 'fp-dms') . '</td></tr>';
        }

        foreach ($templates as $template) {
            $editUrl = add_query_arg([
                'page' => 'fp-dms-templates',
                'action' => 'edit',
                'template' => $template->id,
            ], admin_url('admin.php'));
            $deleteUrl = wp_nonce_url(add_query_arg([
                'page' => 'fp-dms-templates',
                'action' => 'delete',
                'template' => $template->id,
            ], admin_url('admin.php')), 'fpdms_delete_template_' . $template->id);

            echo '<tr>';
            echo '<td>' . esc_html($template->name) . '</td>';
            echo '<td>' . esc_html($template->description) . '</td>';
            echo '<td>' . ($template->isDefault ? '<span class="dashicons dashicons-yes"></span>' : '&ndash;') . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'fp-dms') . '</a> | <a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'' . esc_js(__('Delete this template?', 'fp-dms')) . '\');">' . esc_html__('Delete', 'fp-dms') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}

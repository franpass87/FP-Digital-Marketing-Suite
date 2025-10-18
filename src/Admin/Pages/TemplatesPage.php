<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Support\NoticeStore;
use FP\DMS\Domain\Entities\Template;
use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Domain\Templates\TemplateBlueprints;
use FP\DMS\Domain\Templates\TemplateDraft;
use FP\DMS\Support\Wp;

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
        $post = Wp::unslash($_POST);
        if (! empty($post['fpdms_template_nonce']) && wp_verify_nonce(Wp::sanitizeTextField($post['fpdms_template_nonce'] ?? ''), 'fpdms_save_template')) {
            $id = isset($post['template_id']) ? (int) $post['template_id'] : 0;
            $draft = TemplateDraft::fromArray($post);

            if ($id > 0) {
                if ($repo->update($id, $draft)) {
                    NoticeStore::enqueue('fpdms_templates', 'fpdms_template_saved', __('Template updated.', 'fp-dms'));
                } else {
                    NoticeStore::enqueue('fpdms_templates', 'fpdms_template_error', __('Failed to update template.', 'fp-dms'), 'error');
                }
            } else {
                $repo->create($draft);
                NoticeStore::enqueue('fpdms_templates', 'fpdms_template_saved', __('Template created.', 'fp-dms'));
            }

            self::redirectToIndex();
            return;
        }

        $query = Wp::unslash($_GET);
        if (isset($query['action'], $query['template']) && $query['action'] === 'delete') {
            $templateId = (int) $query['template'];
            $nonce = Wp::sanitizeTextField($query['_wpnonce'] ?? '');
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
        if (! $template) {
            self::renderBlueprintSelector();
        }
        echo '<tr><th scope="row"><label for="fpdms-template-name">' . esc_html__('Name', 'fp-dms') . '</label></th><td><input class="regular-text" id="fpdms-template-name" type="text" name="name" value="' . esc_attr($template->name ?? '') . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="fpdms-template-description">' . esc_html__('Description', 'fp-dms') . '</label></th><td><input class="regular-text" id="fpdms-template-description" type="text" name="description" value="' . esc_attr($template->description ?? '') . '"></td></tr>';
        
        // Editor di testo avanzato con supporto HTML
        echo '<tr><th scope="row"><label for="fpdms-template-content">' . esc_html__('Content', 'fp-dms') . '</label></th><td>';
        
        $editorSettings = [
            'textarea_name' => 'content',
            'textarea_rows' => 20,
            'teeny' => false,
            'media_buttons' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,|,bullist,numlist,blockquote,|,alignleft,aligncenter,alignright,alignjustify,|,link,unlink,image,|,undo,redo,|,code,fullscreen',
                'toolbar2' => 'fontsizeselect,pastetext,removeformat,charmap,|,outdent,indent,|,table,|,wp_help',
                'content_css' => admin_url('css/colors.min.css'),
            ],
            'quicktags' => [
                'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close',
            ],
            'drag_drop_upload' => true,
        ];
        
        wp_editor(
            $template->content ?? '',
            'fpdms-template-content',
            $editorSettings
        );
        
        echo '<p class="description" style="margin-top:10px;">' . esc_html__('Use placeholders like {{client.name}}, {{period.start}}, {{kpi.ga4.users|number}}. You can use HTML formatting and styles.', 'fp-dms') . '</p>';
        echo '</td></tr>';
        
        echo '<tr><th scope="row">' . esc_html__('Default', 'fp-dms') . '</th><td><label><input type="checkbox" name="is_default" value="1"' . checked($template?->isDefault ?? false, true, false) . '> ' . esc_html__('Make this the default template', 'fp-dms') . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button($template ? __('Update template', 'fp-dms') : __('Create template', 'fp-dms'));
        echo '</form>';
        echo '</div>';
    }

    private static function renderBlueprintSelector(): void
    {
        $blueprints = TemplateBlueprints::all();
        if (empty($blueprints)) {
            return;
        }

        $options = '<option value="">' . esc_html__('Start from scratch', 'fp-dms') . '</option>';
        $data = [];
        foreach ($blueprints as $blueprint) {
            $options .= '<option value="' . esc_attr($blueprint->key) . '">' . esc_html($blueprint->name) . '</option>';
            $data[$blueprint->key] = [
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'content' => $blueprint->content,
            ];
        }

        $defaultDescription = esc_html__('Pick a preset to pre-fill the template details with a structured layout.', 'fp-dms');
        $json = Wp::jsonEncode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        if (! is_string($json)) {
            $json = '{}';
        }

        echo '<tr><th scope="row"><label for="fpdms-template-blueprint">' . esc_html__('Blueprint', 'fp-dms') . '</label></th><td>';
        echo '<select id="fpdms-template-blueprint" class="regular-text" style="max-width:320px;">' . $options . '</select>';
        echo '<p class="description" id="fpdms-template-blueprint-description" data-default="' . esc_attr($defaultDescription) . '">' . $defaultDescription . '</p>';
        echo '<button type="button" class="button" id="fpdms-apply-template-blueprint" disabled>' . esc_html__('Use preset', 'fp-dms') . '</button>';
        echo '</td></tr>';

        echo '<script>(function(){document.addEventListener("DOMContentLoaded",function(){var select=document.getElementById("fpdms-template-blueprint");if(!select){return;}var apply=document.getElementById("fpdms-apply-template-blueprint");var desc=document.getElementById("fpdms-template-blueprint-description");var textarea=document.getElementById("fpdms-template-content");var nameInput=document.getElementById("fpdms-template-name");var descriptionInput=document.getElementById("fpdms-template-description");if(!apply||!desc||!textarea){return;}var blueprints=' . $json . ';var markManual=function(el){if(!el){return;}el.addEventListener("input",function(){if(el.dataset){delete el.dataset.autofilled;}});};markManual(nameInput);markManual(descriptionInput);markManual(textarea);var fillField=function(el,value,force){if(!el){return;}var currentValue=typeof el.value==="string"?el.value:"";var currentTrimmed=typeof currentValue==="string"?currentValue.trim():"";if(force||!currentTrimmed||(el.dataset&&el.dataset.autofilled==="1")){if(currentValue!==value){el.value=value;if(el.dataset){el.dataset.autofilled="1";}if(el===textarea){el.dispatchEvent(new Event("input",{bubbles:true}));}}else if(el.dataset){el.dataset.autofilled="1";}}};var applyPreset=function(force){var key=select.value;if(!key||!blueprints[key]){return;}var preset=blueprints[key];fillField(nameInput,preset.name,force);fillField(descriptionInput,preset.description,force);fillField(textarea,preset.content,force);};var updateDescription=function(){var key=select.value;if(key&&blueprints[key]){desc.textContent=blueprints[key].description;apply.disabled=false;applyPreset(false);}else{desc.textContent=desc.dataset.default||"";apply.disabled=true;}};select.addEventListener("change",updateDescription);apply.addEventListener("click",function(){applyPreset(true);});updateDescription();});})();</script>';
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

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Shared\Breadcrumbs;
use FP\DMS\Admin\Pages\Shared\HelpIcon;
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

        // Enqueue script per i presets (solo se stiamo creando un nuovo template)
        if (! $editing) {
            self::enqueuePresetsScript();
        }

        echo '<div class="wrap fpdms-admin-page">';
        
        // Breadcrumbs
        Breadcrumbs::render(Breadcrumbs::getStandardItems('templates'));
        
        // Header moderno
        echo '<div class="fpdms-page-header">';
        echo '<h1>';
        echo '<span class="dashicons dashicons-media-document" style="margin-right:12px;"></span>';
        echo esc_html__('Template Report', 'fp-dms');
        HelpIcon::render(HelpIcon::getCommonHelp('templates'));
        echo '</h1>';
        echo '<p>' . esc_html__('Crea e personalizza i template per i report automatici. Usa l\'AI per generare analisi professionali con EB Garamond.', 'fp-dms') . '</p>';
        echo '</div>';
        
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

    private static function enqueuePresetsScript(): void
    {
        $scriptUrl = plugins_url('assets/js/template-presets.js', FP_DMS_PLUGIN_FILE);
        $cssUrl = plugins_url('assets/css/template-presets.css', FP_DMS_PLUGIN_FILE);
        $editorJsUrl = plugins_url('assets/js/template-editor.js', FP_DMS_PLUGIN_FILE);
        $editorCssUrl = plugins_url('assets/css/template-editor.css', FP_DMS_PLUGIN_FILE);
        $version = FP_DMS_VERSION;
        
        // Enqueue CSS
        wp_enqueue_style('fpdms-template-presets', $cssUrl, [], $version);
        wp_enqueue_style('fpdms-template-editor', $editorCssUrl, [], $version);
        
        // Enqueue JS
        wp_enqueue_script('fpdms-template-presets', $scriptUrl, ['jquery'], $version, true);
        wp_enqueue_script('fpdms-template-editor', $editorJsUrl, ['jquery'], $version, true);
        
        // Prepara i dati dei blueprints
        $blueprints = TemplateBlueprints::all();
        $data = [];
        foreach ($blueprints as $blueprint) {
            $data[$blueprint->key] = [
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'content' => $blueprint->content,
            ];
        }
        
        wp_localize_script('fpdms-template-presets', 'fpdmsTemplateBlueprints', $data);
        
        // Dati per l'editor con preview
        wp_localize_script('fpdms-template-editor', 'fpdmsTemplateEditor', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fpdms_template_preview'),
        ]);
    }

    private static function renderForm(?Template $template): void
    {
        $title = $template ? __('Modifica Template', 'fp-dms') : __('Crea Nuovo Template', 'fp-dms');
        
        echo '<div class="fpdms-template-editor-container">';
        
        // Pannello sinistro - Editor
        echo '<div class="fpdms-template-editor-panel">';
        echo '<div class="fpdms-card">';
        echo '<div class="fpdms-card-header">';
        echo '<h2><span class="dashicons dashicons-edit"></span>' . esc_html($title) . '</h2>';
        echo '</div>';
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
        echo '</div>';
        
        // Pannello destro - Live Preview
        echo '<div class="fpdms-template-preview-panel">';
        self::renderPreviewPanel();
        echo '</div>';
        
        echo '</div>'; // Close fpdms-template-editor-container
    }

    private static function renderBlueprintSelector(): void
    {
        $blueprints = TemplateBlueprints::all();
        if (empty($blueprints)) {
            return;
        }

        $options = '<option value="">' . esc_html__('Start from scratch', 'fp-dms') . '</option>';
        foreach ($blueprints as $blueprint) {
            $options .= '<option value="' . esc_attr($blueprint->key) . '">' . esc_html($blueprint->name) . '</option>';
        }

        $defaultDescription = esc_html__('Pick a preset to pre-fill the template details with a structured layout.', 'fp-dms');

        echo '<tr><th scope="row"><label for="fpdms-template-blueprint">' . esc_html__('Blueprint', 'fp-dms') . '</label></th><td>';
        echo '<select id="fpdms-template-blueprint" class="regular-text" style="max-width:320px;">' . $options . '</select>';
        echo '<p class="description" id="fpdms-template-blueprint-description" data-default="' . esc_attr($defaultDescription) . '">' . $defaultDescription . '</p>';
        echo '<button type="button" class="button" id="fpdms-apply-template-blueprint" disabled>' . esc_html__('Use preset', 'fp-dms') . '</button>';
        echo '</td></tr>';
    }

    private static function renderPreviewPanel(): void
    {
        // Ottieni lista clienti per il selettore
        $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        $clients = $clientsRepo->all();
        
        echo '<div class="fpdms-preview-container">';
        echo '<div class="fpdms-preview-header">';
        echo '<h3><span class="dashicons dashicons-visibility"></span>' . esc_html__('Anteprima Live', 'fp-dms') . '</h3>';
        echo '<div class="fpdms-preview-controls">';
        echo '<button type="button" class="fpdms-preview-refresh-btn" id="fpdms-preview-refresh" title="' . esc_attr__('Aggiorna anteprima', 'fp-dms') . '">';
        echo '<span class="dashicons dashicons-update"></span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        // Selettore cliente per la preview
        echo '<div class="fpdms-preview-client-selector">';
        echo '<label for="fpdms-preview-client-id">' . esc_html__('Cliente per l\'anteprima:', 'fp-dms') . '</label>';
        echo '<select id="fpdms-preview-client-id">';
        echo '<option value="">' . esc_html__('Nessun cliente selezionato', 'fp-dms') . '</option>';
        foreach ($clients as $client) {
            echo '<option value="' . esc_attr((string) $client->id) . '">' . esc_html($client->name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Area preview
        echo '<div id="fpdms-template-preview-content" class="fpdms-preview-content">';
        echo '<div id="fpdms-preview-body">';
        echo '<div class="fpdms-preview-empty">';
        echo '<span class="dashicons dashicons-media-document"></span>';
        echo '<p>' . esc_html__('Inizia a scrivere il contenuto per vedere l\'anteprima', 'fp-dms') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * @param array<int,Template> $templates
     */
    private static function renderList(array $templates): void
    {
        echo '<div style="margin-top:40px;">';
        echo '<h2 style="font-size:20px;font-weight:600;color:#1f2937;margin-bottom:16px;">' . esc_html__('Template Disponibili', 'fp-dms') . '</h2>';
        echo '<table class="fpdms-table">';
        echo '<thead><tr><th>' . esc_html__('Nome', 'fp-dms') . '</th><th>' . esc_html__('Descrizione', 'fp-dms') . '</th><th>' . esc_html__('Default', 'fp-dms') . '</th><th>' . esc_html__('Azioni', 'fp-dms') . '</th></tr></thead><tbody>';
        if (empty($templates)) {
            echo '</tbody></table>';
            EmptyState::render([
                'icon' => 'dashicons-media-document',
                'title' => __('Nessun Template Report', 'fp-dms'),
                'description' => __('I template definiscono l\'aspetto e la struttura dei tuoi report PDF. Crea il tuo primo template personalizzato o usa uno dei preset disponibili per diversi settori (hospitality, e-commerce, etc).', 'fp-dms'),
                'primaryAction' => [
                    'label' => __('+ Crea Template', 'fp-dms'),
                    'url' => 'javascript:void(0)',
                    'class' => 'button-primary fpdms-scroll-to-form'
                ],
                'secondaryAction' => [
                    'label' => __('📚 Guida Template', 'fp-dms'),
                    'url' => 'https://docs.francescopasseri.com/fp-dms/templates'
                ],
                'helpText' => __('Suggerimento: Ogni template supporta variabili dinamiche e AI insights', 'fp-dms')
            ]);
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var scrollBtn = document.querySelector(".fpdms-scroll-to-form");
                if (scrollBtn) {
                    scrollBtn.addEventListener("click", function(e) {
                        e.preventDefault();
                        var form = document.querySelector("form[method=post]");
                        if (form) {
                            form.scrollIntoView({ behavior: "smooth", block: "start" });
                            var firstInput = form.querySelector("input[type=text]");
                            if (firstInput) setTimeout(function() { firstInput.focus(); }, 500);
                        }
                    });
                }
            });
            </script>';
            return;
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

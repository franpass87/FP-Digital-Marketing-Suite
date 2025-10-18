<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard;

/**
 * Base implementation for wizard steps.
 */
abstract class AbstractWizardStep implements WizardStep
{
    protected string $id;
    protected string $title;
    protected string $description;
    protected bool $skippable = false;

    public function __construct(string $id, string $title, string $description = '')
    {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isSkippable(): bool
    {
        return $this->skippable;
    }

    public function getHelp(): array
    {
        return [];
    }

    public function process(array $data): array
    {
        // Default: return data as-is
        return $data;
    }

    /**
     * Helper to render a text input field.
     */
    protected function renderTextField(
        string $name,
        string $label,
        string $value = '',
        array $attrs = []
    ): string {
        $id = 'fpdms_' . $name;
        $required = $attrs['required'] ?? false;
        $placeholder = $attrs['placeholder'] ?? '';
        $description = $attrs['description'] ?? '';
        $validationAttrs = $attrs['data-validate'] ?? '';

        $html = '<div class="fpdms-field" data-field="' . esc_attr($name) . '">';
        $html .= '<label for="' . esc_attr($id) . '" class="fpdms-field-label">';
        $html .= esc_html($label);
        if ($required) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';

        $html .= '<input type="text" ';
        $html .= 'id="' . esc_attr($id) . '" ';
        $html .= 'name="' . esc_attr($name) . '" ';
        $html .= 'class="regular-text fpdms-validated-field" ';
        $html .= 'value="' . esc_attr($value) . '" ';

        if ($placeholder) {
            $html .= 'placeholder="' . esc_attr($placeholder) . '" ';
        }

        if ($required) {
            $html .= 'required ';
        }

        if ($validationAttrs) {
            $html .= $validationAttrs . ' ';
        }

        $html .= '/>';

        if ($description) {
            $html .= '<p class="description">' . wp_kses_post($description) . '</p>';
        }

        $html .= '<span class="fpdms-validation-icon"></span>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Helper to render a textarea field.
     */
    protected function renderTextareaField(
        string $name,
        string $label,
        string $value = '',
        array $attrs = []
    ): string {
        $id = 'fpdms_' . $name;
        $required = $attrs['required'] ?? false;
        $placeholder = $attrs['placeholder'] ?? '';
        $description = $attrs['description'] ?? '';
        $rows = $attrs['rows'] ?? 5;
        $validationAttrs = $attrs['data-validate'] ?? '';

        $html = '<div class="fpdms-field" data-field="' . esc_attr($name) . '">';
        $html .= '<label for="' . esc_attr($id) . '" class="fpdms-field-label">';
        $html .= esc_html($label);
        if ($required) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';

        $html .= '<textarea ';
        $html .= 'id="' . esc_attr($id) . '" ';
        $html .= 'name="' . esc_attr($name) . '" ';
        $html .= 'class="large-text fpdms-validated-field" ';
        $html .= 'rows="' . intval($rows) . '" ';

        if ($placeholder) {
            $html .= 'placeholder="' . esc_attr($placeholder) . '" ';
        }

        if ($required) {
            $html .= 'required ';
        }

        if ($validationAttrs) {
            $html .= $validationAttrs . ' ';
        }

        $html .= '>' . esc_textarea($value) . '</textarea>';

        if ($description) {
            $html .= '<p class="description">' . wp_kses_post($description) . '</p>';
        }

        $html .= '<span class="fpdms-validation-icon"></span>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Helper to render a help panel.
     */
    protected function renderHelpPanel(string $title, string $content, array $links = []): string
    {
        $html = '<div class="fpdms-help-panel">';
        $html .= '<div class="fpdms-help-title">' . esc_html($title) . '</div>';
        $html .= '<div class="fpdms-help-content">' . wp_kses_post($content) . '</div>';

        if (!empty($links)) {
            $html .= '<div class="fpdms-help-links">';
            foreach ($links as $link) {
                $html .= sprintf(
                    '<a href="%s" class="fpdms-help-link" target="_blank">%s ↗</a>',
                    esc_url($link['url']),
                    esc_html($link['label'])
                );
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}

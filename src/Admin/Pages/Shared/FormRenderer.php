<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Shared;

use function esc_attr;
use function esc_html;
use function esc_url;
use function selected;
use function checked;

/**
 * Shared component for rendering HTML forms
 */
class FormRenderer
{
    /**
     * Render a select dropdown
     *
     * @param array{
     *   id: string,
     *   name: string,
     *   label?: string,
     *   options: array<string|int, string>,
     *   selected?: string|int,
     *   required?: bool,
     *   class?: string
     * } $config
     */
    public static function select(array $config): void
    {
        $id = $config['id'];
        $name = $config['name'];
        $label = $config['label'] ?? '';
        $options = $config['options'];
        $selectedValue = $config['selected'] ?? '';
        $required = $config['required'] ?? false;
        $class = $config['class'] ?? '';

        if ($label !== '') {
            echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        }

        $attrs = '';
        if ($required) {
            $attrs .= ' required';
        }
        if ($class !== '') {
            $attrs .= ' class="' . esc_attr($class) . '"';
        }

        echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . $attrs . '>';
        foreach ($options as $value => $label) {
            $sel = selected($selectedValue, $value, false);
            echo '<option value="' . esc_attr((string) $value) . '"' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Render a text input
     *
     * @param array{
     *   id: string,
     *   name: string,
     *   label?: string,
     *   value?: string,
     *   placeholder?: string,
     *   required?: bool,
     *   type?: string,
     *   class?: string
     * } $config
     */
    public static function input(array $config): void
    {
        $id = $config['id'];
        $name = $config['name'];
        $label = $config['label'] ?? '';
        $value = $config['value'] ?? '';
        $placeholder = $config['placeholder'] ?? '';
        $required = $config['required'] ?? false;
        $type = $config['type'] ?? 'text';
        $class = $config['class'] ?? '';

        if ($label !== '') {
            echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        }

        $attrs = '';
        if ($required) {
            $attrs .= ' required';
        }
        if ($class !== '') {
            $attrs .= ' class="' . esc_attr($class) . '"';
        }
        if ($placeholder !== '') {
            $attrs .= ' placeholder="' . esc_attr($placeholder) . '"';
        }

        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $attrs . '>';
    }

    /**
     * Render a textarea
     *
     * @param array{
     *   id: string,
     *   name: string,
     *   label?: string,
     *   value?: string,
     *   placeholder?: string,
     *   required?: bool,
     *   rows?: int,
     *   class?: string
     * } $config
     */
    public static function textarea(array $config): void
    {
        $id = $config['id'];
        $name = $config['name'];
        $label = $config['label'] ?? '';
        $value = $config['value'] ?? '';
        $placeholder = $config['placeholder'] ?? '';
        $required = $config['required'] ?? false;
        $rows = $config['rows'] ?? 3;
        $class = $config['class'] ?? '';

        if ($label !== '') {
            echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        }

        $attrs = '';
        if ($required) {
            $attrs .= ' required';
        }
        if ($class !== '') {
            $attrs .= ' class="' . esc_attr($class) . '"';
        }
        if ($placeholder !== '') {
            $attrs .= ' placeholder="' . esc_attr($placeholder) . '"';
        }

        echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="' . esc_attr((string) $rows) . '"' . $attrs . '>';
        echo esc_html($value);
        echo '</textarea>';
    }

    /**
     * Render a checkbox
     *
     * @param array{
     *   id: string,
     *   name: string,
     *   label: string,
     *   value?: string,
     *   checked?: bool,
     *   class?: string
     * } $config
     */
    public static function checkbox(array $config): void
    {
        $id = $config['id'];
        $name = $config['name'];
        $label = $config['label'];
        $value = $config['value'] ?? '1';
        $isChecked = $config['checked'] ?? false;
        $class = $config['class'] ?? '';

        $attrs = '';
        if ($class !== '') {
            $attrs .= ' class="' . esc_attr($class) . '"';
        }

        $chk = checked($isChecked, true, false);

        echo '<label>';
        echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $chk . $attrs . '>';
        echo ' ' . esc_html($label);
        echo '</label>';
    }

    /**
     * Render a hidden input
     */
    public static function hidden(string $name, string $value): void
    {
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
    }

    /**
     * Render form opening tag
     *
     * @param array{
     *   action?: string,
     *   method?: string,
     *   class?: string
     * } $config
     */
    public static function open(array $config = []): void
    {
        $action = $config['action'] ?? '';
        $method = $config['method'] ?? 'post';
        $class = $config['class'] ?? '';

        $attrs = '';
        if ($action !== '') {
            $attrs .= ' action="' . esc_url($action) . '"';
        }
        if ($class !== '') {
            $attrs .= ' class="' . esc_attr($class) . '"';
        }

        echo '<form method="' . esc_attr($method) . '"' . $attrs . '>';
    }

    /**
     * Render form closing tag
     */
    public static function close(): void
    {
        echo '</form>';
    }

    /**
     * Render nonce field
     */
    public static function nonce(string $action, string $name = '_wpnonce'): void
    {
        \wp_nonce_field($action, $name);
    }
}

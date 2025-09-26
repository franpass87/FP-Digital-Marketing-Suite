<?php
/**
 * Admin UI reusable components.
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin\UI;

use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function sanitize_html_class;
use function wp_kses_post;
use function wp_unique_id;

/**
 * Provides reusable, accessible admin UI components that align with the
 * design tokens introduced for the refreshed dashboard experience.
 */
class Components
{
    /**
     * Render a page header with title, optional subtitle/meta, and actions.
     *
     * @param array<string,mixed> $args Arguments for the header.
     */
    public static function page_header(array $args): string
    {
        $title = isset($args['title']) ? trim((string) $args['title']) : '';

        if ($title === '') {
            return '';
        }

        $subtitle = isset($args['subtitle']) ? trim((string) $args['subtitle']) : '';
        $actions  = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : [];
        $meta     = isset($args['meta']) && is_array($args['meta']) ? array_filter($args['meta']) : [];

        $subtitle_id = $subtitle !== '' ? 'fp-dms-page-subtitle-' . wp_unique_id('') : '';
        $meta_id     = ! empty($meta) ? 'fp-dms-page-meta-' . wp_unique_id('') : '';

        $described_by = [];
        if ($subtitle_id !== '') {
            $described_by[] = $subtitle_id;
        }
        if ($meta_id !== '') {
            $described_by[] = $meta_id;
        }

        $header_attributes = [
            'class' => 'fp-dms-page-header',
        ];

        $title_attributes = [
            'class' => 'fp-dms-page-header__title',
        ];

        if (! empty($described_by)) {
            $title_attributes['aria-describedby'] = implode(' ', $described_by);
        }

        $output  = '<div' . self::html_attributes($header_attributes) . '>';
        $output .= '<div class="fp-dms-page-header__content">';
        $output .= '<h1' . self::html_attributes($title_attributes) . '>' . esc_html($title) . '</h1>';

        if ($subtitle !== '') {
            $output .= '<p class="fp-dms-page-header__subtitle" id="' . esc_attr($subtitle_id) . '">' . esc_html($subtitle) . '</p>';
        }

        if (! empty($meta)) {
            $output .= '<div class="fp-dms-page-header__meta" id="' . esc_attr($meta_id) . '">';
            foreach ($meta as $meta_item) {
                if (is_string($meta_item)) {
                    $output .= '<span class="fp-dms-page-header__meta-item">' . esc_html($meta_item) . '</span>';
                    continue;
                }

                if (! is_array($meta_item)) {
                    continue;
                }

                $label = isset($meta_item['label']) ? (string) $meta_item['label'] : '';
                $value = isset($meta_item['value']) ? (string) $meta_item['value'] : '';

                if ($label === '' && $value === '') {
                    continue;
                }

                $output .= '<span class="fp-dms-page-header__meta-item">';
                if ($label !== '') {
                    $output .= '<span class="fp-dms-u-text-subtle">' . esc_html($label) . '</span>';
                }
                if ($value !== '') {
                    $output .= '<span>' . esc_html($value) . '</span>';
                }
                $output .= '</span>';
            }
            $output .= '</div>';
        }

        $output .= '</div>';

        if (! empty($actions)) {
            $output .= '<div class="fp-dms-page-actions" role="group" aria-label="' . esc_attr__('Page actions', 'fp-digital-marketing') . '">';
            $output .= self::render_actions($actions, 'button button-secondary');
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render a card component.
     *
     * @param array<string,mixed> $args Arguments for the card.
     */
    public static function card(array $args): string
    {
        $title       = isset($args['title']) ? trim((string) $args['title']) : '';
        $description = isset($args['description']) ? trim((string) $args['description']) : '';
        $content     = isset($args['content']) ? (string) $args['content'] : '';
        $footer      = isset($args['footer']) ? (string) $args['footer'] : '';
        $meta        = isset($args['meta']) && is_array($args['meta']) ? array_filter($args['meta']) : [];

        $classes = ['fp-dms-card'];
        if (! empty($args['class'])) {
            $classes = array_merge($classes, self::explode_classes((string) $args['class']));
        }

        $output  = '<section' . self::html_attributes(['class' => implode(' ', array_filter($classes))]) . '>';

        if ($title !== '') {
            $output .= '<header class="fp-dms-card__header">';
            $output .= '<h2 class="fp-dms-card__title">' . esc_html($title) . '</h2>';
            if ($description !== '') {
                $output .= '<p class="fp-dms-card__description">' . esc_html($description) . '</p>';
            }
            if (! empty($meta)) {
                $output .= '<div class="fp-dms-card__meta">';
                foreach ($meta as $meta_item) {
                    $output .= '<span class="fp-dms-page-header__meta-item">' . esc_html((string) $meta_item) . '</span>';
                }
                $output .= '</div>';
            }
            $output .= '</header>';
        }

        if ($content !== '') {
            $output .= '<div class="fp-dms-card__content">' . wp_kses_post($content) . '</div>';
        }

        if ($footer !== '') {
            $output .= '<footer class="fp-dms-card__footer">' . wp_kses_post($footer) . '</footer>';
        }

        $output .= '</section>';

        return $output;
    }

    /**
     * Render an informational panel with optional tone.
     *
     * @param array<string,mixed> $args Arguments for the panel.
     */
    public static function panel(array $args): string
    {
        $tone = isset($args['tone']) ? (string) $args['tone'] : '';
        $body = isset($args['content']) ? (string) $args['content'] : '';

        if ($body === '') {
            return '';
        }

        $classes = ['fp-dms-panel'];
        if ($tone !== '') {
            $classes[] = 'fp-dms-panel--' . sanitize_html_class($tone);
        }

        return '<div' . self::html_attributes(['class' => implode(' ', $classes)]) . '>' . wp_kses_post($body) . '</div>';
    }

    /**
     * Render a form row with label, control, help, and error messaging.
     *
     * @param array<string,mixed> $args Arguments for the form row.
     */
    public static function form_row(array $args): string
    {
        $id       = isset($args['id']) ? (string) $args['id'] : 'fp-dms-field-' . wp_unique_id('');
        $label    = isset($args['label']) ? trim((string) $args['label']) : '';
        $control  = isset($args['control']) ? (string) $args['control'] : '';
        $help     = isset($args['help']) ? trim((string) $args['help']) : '';
        $error    = isset($args['error']) ? trim((string) $args['error']) : '';
        $required = ! empty($args['required']);
        $inline   = ! empty($args['inline']);

        if ($control === '') {
            return '';
        }

        $described_by = [];
        if ($help !== '') {
            $described_by[] = $id . '-help';
        }
        if ($error !== '') {
            $described_by[] = $id . '-error';
        }

        $row_classes = ['fp-dms-form-row'];
        if ($inline) {
            $row_classes[] = 'is-inline';
        }

        $output  = '<div class="' . esc_attr(implode(' ', $row_classes)) . '">';

        if ($label !== '') {
            $output .= '<label class="fp-dms-form-label" for="' . esc_attr($id) . '">';
            $output .= esc_html($label);
            if ($required) {
                $output .= ' <span class="required-indicator" aria-hidden="true">*</span>';
            }
            $output .= '</label>';
        }

        $output .= '<div class="fp-dms-form-control">';
        $control_markup = self::inject_accessibility_attributes($control, $id, $described_by, $required);
        $output        .= $control_markup;

        if ($help !== '') {
            $output .= '<p class="fp-dms-form-help" id="' . esc_attr($id . '-help') . '">' . esc_html($help) . '</p>';
        }

        if ($error !== '') {
            $output .= '<p class="fp-dms-form-error" id="' . esc_attr($id . '-error') . '" role="alert">' . esc_html($error) . '</p>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render a tab navigation.
     *
     * @param array<string,mixed> $args Arguments for the tab navigation.
     */
    public static function tab_nav(array $args): string
    {
        $tabs = isset($args['tabs']) && is_array($args['tabs']) ? $args['tabs'] : [];
        if (empty($tabs)) {
            return '';
        }

        $tablist_label = isset($args['label']) ? trim((string) $args['label']) : esc_html__('Page sections', 'fp-digital-marketing');
        $tablist_id    = 'fp-dms-tablist-' . wp_unique_id('');

        $output  = '<div class="fp-dms-tab-nav" role="tablist" aria-label="' . esc_attr($tablist_label) . '" id="' . esc_attr($tablist_id) . '">';

        foreach ($tabs as $index => $tab) {
            if (! is_array($tab)) {
                continue;
            }

            $tab_id    = isset($tab['id']) ? (string) $tab['id'] : 'fp-dms-tab-' . wp_unique_id('');
            $label     = isset($tab['label']) ? (string) $tab['label'] : '';
            $is_active = ! empty($tab['active']);
            $count     = isset($tab['count']) ? (int) $tab['count'] : null;
            $panel_id  = isset($tab['panel']) ? (string) $tab['panel'] : '';
            $url       = isset($tab['url']) ? (string) $tab['url'] : '';

            if ($label === '') {
                continue;
            }

            $tab_classes = ['fp-dms-tab', 'nav-tab'];
            if ($is_active) {
                $tab_classes[] = 'is-active';
                $tab_classes[] = 'nav-tab-active';
            }

            $attributes = [
                'class'         => implode(' ', $tab_classes),
                'role'          => 'tab',
                'aria-selected' => $is_active ? 'true' : 'false',
                'id'            => $tab_id,
                'tabindex'      => $is_active ? '0' : '-1',
            ];

            if ($panel_id !== '') {
                $attributes['aria-controls'] = $panel_id;
            }

            if ($url !== '') {
                $attributes['href'] = esc_url($url);
                $output            .= '<a' . self::html_attributes($attributes) . '>' . esc_html($label);
            } else {
                $output .= '<button type="button"' . self::html_attributes($attributes) . '>' . esc_html($label);
            }

            if ($count !== null) {
                $output .= '<span class="fp-dms-tab-count" aria-hidden="true">' . esc_html((string) $count) . '</span>';
            }

            if ($url !== '') {
                $output .= '</a>';
            } else {
                $output .= '</button>';
            }
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render a notice component with optional actions.
     *
     * @param array<string,mixed> $args Arguments for the notice.
     */
    public static function notice(array $args): string
    {
        $message = isset($args['message']) ? trim((string) $args['message']) : '';
        if ($message === '') {
            return '';
        }

        $title   = isset($args['title']) ? trim((string) $args['title']) : '';
        $type    = isset($args['type']) ? (string) $args['type'] : 'info';
        $actions = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : [];
        $status  = isset($args['status']) ? (string) $args['status'] : 'status';
        $classes = ['fp-dms-notice', 'is-' . sanitize_html_class($type)];

        if (! empty($args['dismissible'])) {
            $classes[] = 'is-dismissible';
        }

        $attributes = [
            'class' => implode(' ', $classes),
            'role'  => $status === 'alert' ? 'alert' : 'status',
        ];

        $output  = '<div' . self::html_attributes($attributes) . '>';

        if ($title !== '') {
            $output .= '<p class="fp-dms-notice__title">' . esc_html($title) . '</p>';
        }

        $output .= '<div class="fp-dms-notice__content">' . wp_kses_post($message) . '</div>';

        if (! empty($actions)) {
            $output .= '<div class="fp-dms-notice__actions">';
            $output .= self::render_actions($actions, 'button button-secondary');
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render a toolbar wrapper for filters/actions.
     *
     * @param array<string,mixed> $args Arguments for the toolbar.
     */
    public static function toolbar(array $args): string
    {
        $filters = isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : [];
        $actions = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : [];

        if (empty($filters) && empty($actions)) {
            return '';
        }

        $output = '<div class="fp-dms-toolbar" role="region" aria-label="' . esc_attr__('Table controls', 'fp-digital-marketing') . '">';

        if (! empty($filters)) {
            $output .= '<div class="fp-dms-toolbar__filters">';
            foreach ($filters as $filter) {
                if (is_string($filter)) {
                    $output .= wp_kses_post($filter);
                }
            }
            $output .= '</div>';
        }

        if (! empty($actions)) {
            $output .= '<div class="fp-dms-toolbar__actions">';
            $output .= self::render_actions($actions, 'button button-secondary');
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render actions helper.
     *
     * @param array<int,array<string,mixed>> $actions Actions list.
     */
    protected static function render_actions(array $actions, string $default_class): string
    {
        $output = '';

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $label = isset($action['label']) ? trim((string) $action['label']) : '';
            $url   = isset($action['url']) ? (string) $action['url'] : '';
            $html  = isset($action['html']) ? (string) $action['html'] : '';

            if ($html !== '') {
                $output .= wp_kses_post($html);
                continue;
            }

            if ($label === '') {
                continue;
            }

            $classes = self::explode_classes($default_class);
            if (! empty($action['variant'])) {
                $classes[] = 'button-' . sanitize_html_class((string) $action['variant']);
            }

            if (! empty($action['class'])) {
                $classes = array_merge($classes, self::explode_classes((string) $action['class']));
            }

            $attributes = [
                'class' => implode(' ', array_filter(array_unique($classes))),
            ];

            if (! empty($action['target'])) {
                $attributes['target'] = (string) $action['target'];
            }

            if (! empty($action['rel'])) {
                $attributes['rel'] = (string) $action['rel'];
            }

            if ($url !== '') {
                $attributes['href'] = esc_url($url);
                $output            .= '<a' . self::html_attributes($attributes) . '>' . esc_html($label) . '</a>';
            } else {
                $type                = isset($action['type']) ? (string) $action['type'] : 'button';
                $attributes['type']  = in_array($type, ['submit', 'reset', 'button'], true) ? $type : 'button';
                $output             .= '<button' . self::html_attributes($attributes) . '>' . esc_html($label) . '</button>';
            }
        }

        return $output;
    }

    /**
     * Inject accessibility attributes into a control markup when possible.
     *
     * @param array<int,string> $described_by Description IDs.
     */
    protected static function inject_accessibility_attributes(string $control, string $id, array $described_by, bool $required): string
    {
        if (strpos($control, 'id=') === false) {
            $result = preg_replace('/<(input|select|textarea)/i', '<$1 id="' . esc_attr($id) . '"', $control, 1);
            if (is_string($result)) {
                $control = $result;
            }
        }

        if (! empty($described_by) && strpos($control, 'aria-describedby=') === false) {
            $result = preg_replace('/<(input|select|textarea)/i', '<$1 aria-describedby="' . esc_attr(implode(' ', $described_by)) . '"', $control, 1);
            if (is_string($result)) {
                $control = $result;
            }
        }

        if ($required && strpos($control, 'required') === false) {
            $result = preg_replace('/<(input|select|textarea)/i', '<$1 required', $control, 1);
            if (is_string($result)) {
                $control = $result;
            }
        }

        return $control;
    }

    /**
     * Helper to render HTML attributes.
     *
     * @param array<string,string|null> $attributes Attribute map.
     */
    protected static function html_attributes(array $attributes): string
    {
        $pairs = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $pairs[] = sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
        }

        return implode('', $pairs);
    }

    /**
     * Convert a potentially space-delimited class list to sanitized tokens.
     *
     * @param string $classes Raw class string.
     *
     * @return array<int,string>
     */
    protected static function explode_classes(string $classes): array
    {
        $tokens = preg_split('/\s+/', $classes) ?: [];

        return array_filter(
            array_map(
                static function ($token) {
                    return $token !== '' ? sanitize_html_class($token) : '';
                },
                $tokens
            )
        );
    }
}

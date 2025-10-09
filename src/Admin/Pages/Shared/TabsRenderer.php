<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Shared;

use function add_query_arg;
use function admin_url;
use function esc_attr;
use function esc_html;
use function esc_url;

/**
 * Shared component for rendering WordPress admin tabs
 */
class TabsRenderer
{
    /**
     * Render WordPress admin tabs
     *
     * @param array<string, string> $tabs Array of tab_key => tab_label
     * @param string $currentTab Currently active tab key
     * @param array<string, string|int> $baseParams Base query parameters to maintain
     */
    public static function render(array $tabs, string $currentTab, array $baseParams = []): void
    {
        echo '<h2 class="nav-tab-wrapper">';

        foreach ($tabs as $key => $label) {
            $params = array_merge($baseParams, ['tab' => $key]);
            $url = add_query_arg($params, admin_url('admin.php'));
            $class = $currentTab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';

            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">';
            echo esc_html($label);
            echo '</a>';
        }

        echo '</h2>';
    }

    /**
     * Render tab content wrapper
     */
    public static function contentStart(string $class = ''): void
    {
        $classAttr = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';
        echo '<div' . $classAttr . '>';
    }

    /**
     * End tab content wrapper
     */
    public static function contentEnd(): void
    {
        echo '</div>';
    }
}

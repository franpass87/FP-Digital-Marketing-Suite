<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

class DashboardPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Dashboard', 'fp-dms') . '</h1>';
        echo '<p>' . esc_html__('This section is under construction.', 'fp-dms') . '</p>';
        echo '</div>';
    }
}

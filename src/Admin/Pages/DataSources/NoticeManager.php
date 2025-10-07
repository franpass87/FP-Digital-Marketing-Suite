<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\DataSources;

/**
 * Manages notices and messages for Data Sources page.
 */
class NoticeManager
{
    private const TRANSIENT_KEY = 'fpdms_datasources_notices';
    private const SETTINGS_GROUP = 'fpdms_datasources';

    public function bootNotices(): void
    {
        $stored = get_transient(self::TRANSIENT_KEY);
        
        if (!is_array($stored)) {
            return;
        }

        foreach ($stored as $notice) {
            add_settings_error(
                self::SETTINGS_GROUP,
                $notice['code'] ?? uniqid('fpdms', true),
                $notice['message'] ?? '',
                $notice['type'] ?? 'updated'
            );
        }

        delete_transient(self::TRANSIENT_KEY);
    }

    public function displayNotices(): void
    {
        settings_errors(self::SETTINGS_GROUP);
    }

    public function renderEmptyState(): void
    {
        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html__('Add at least one client before configuring data sources.', 'fp-dms') . '</p>';
        
        $url = add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php'));
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">';
        echo esc_html__('Add client', 'fp-dms');
        echo '</a></p>';
        echo '</div>';
    }

    public function renderGuidedIntro(): void
    {
        echo '<div class="notice notice-info" style="margin-top:16px;">';
        echo '<p><strong>' . esc_html__('How it works:', 'fp-dms') . '</strong></p>';
        echo '<ol style="margin-left:20px;">';
        echo '<li>' . esc_html__('Select a connector type (GA4, Google Ads, Meta Ads, etc.)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Provide authentication credentials (Service Account, API keys, etc.)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Configure the resource ID (Property ID, Customer ID, etc.)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Test the connection to verify it works', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Save and use in your reports', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '</div>';
    }
}
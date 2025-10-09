<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\DataSources;

use FP\DMS\Domain\Entities\DataSource;

/**
 * Renders HTML for Data Sources page components.
 */
class Renderer
{
    /**
     * Render data sources list table.
     *
     * @param array<int,DataSource> $dataSources
     * @param array<string,array<string,mixed>> $definitions
     */
    public function renderList(array $dataSources, array $definitions, ?int $selectedClientId): void
    {
        if (empty($dataSources)) {
            echo '<div class="notice notice-warning" style="margin-top:20px;">';
            echo '<p>' . esc_html__('No data sources configured yet for this client.', 'fp-dms') . '</p>';
            echo '</div>';
            return;
        }

        echo '<h2>' . esc_html__('Configured Data Sources', 'fp-dms') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Name', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Type', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Status', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Actions', 'fp-dms') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($dataSources as $source) {
            $this->renderListRow($source, $definitions, $selectedClientId);
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Render single row in list table.
     *
     * @param array<string,array<string,mixed>> $definitions
     */
    private function renderListRow(DataSource $source, array $definitions, ?int $selectedClientId): void
    {
        $typeName = $definitions[$source->type]['name'] ?? ucfirst($source->type);

        echo '<tr>';
        echo '<td><strong>' . esc_html($source->label) . '</strong></td>';
        echo '<td>' . esc_html($typeName) . '</td>';
        echo '<td>' . $this->renderStatusBadge($source) . '</td>';
        echo '<td>' . $this->renderActions($source, $selectedClientId) . '</td>';
        echo '</tr>';
    }

    private function renderStatusBadge(DataSource $source): string
    {
        $lastTested = $source->lastTestedAt ?? null;

        if (!$lastTested) {
            return '<span class="dashicons dashicons-warning" style="color:#f0b849;" title="' .
                esc_attr__('Not tested yet', 'fp-dms') . '"></span> ' .
                esc_html__('Untested', 'fp-dms');
        }

        $lastStatus = $source->lastTestStatus ?? 'unknown';

        if ($lastStatus === 'success') {
            return '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" title="' .
                esc_attr__('Last test successful', 'fp-dms') . '"></span> ' .
                esc_html__('Connected', 'fp-dms');
        }

        return '<span class="dashicons dashicons-no-alt" style="color:#dc3232;" title="' .
            esc_attr__('Last test failed', 'fp-dms') . '"></span> ' .
            esc_html__('Error', 'fp-dms');
    }

    private function renderActions(DataSource $source, ?int $selectedClientId): string
    {
        $actions = [];

        // Edit
        $editUrl = add_query_arg([
            'page' => 'fp-dms-datasources',
            'action' => 'edit',
            'source' => $source->id,
            'client' => $selectedClientId,
        ], admin_url('admin.php'));

        $actions[] = '<a href="' . esc_url($editUrl) . '">' .
            esc_html__('Edit', 'fp-dms') . '</a>';

        // Delete
        $deleteUrl = wp_nonce_url(
            add_query_arg([
                'page' => 'fp-dms-datasources',
                'action' => 'delete',
                'source' => $source->id,
                'client' => $selectedClientId,
            ], admin_url('admin.php')),
            'fpdms_delete_datasource_' . $source->id
        );

        $actions[] = '<a href="' . esc_url($deleteUrl) . '" ' .
            'onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this data source?', 'fp-dms')) . '\');">' .
            esc_html__('Delete', 'fp-dms') . '</a>';

        return implode(' | ', $actions);
    }

    public function outputInlineAssets(): void
    {
        echo '<style>
            .fpdms-datasource-form { 
                background: #fff; 
                border: 1px solid #ccd0d4; 
                border-radius: 4px; 
                padding: 20px; 
                margin: 20px 0; 
            }
            .fpdms-form-section { 
                margin-bottom: 24px; 
            }
            .fpdms-form-section h3 { 
                margin-top: 0; 
                border-bottom: 1px solid #dcdcde; 
                padding-bottom: 8px; 
            }
            .fpdms-field-group { 
                margin-bottom: 16px; 
            }
            .fpdms-field-group label { 
                display: block; 
                font-weight: 600; 
                margin-bottom: 4px; 
            }
            .fpdms-field-group input[type="text"],
            .fpdms-field-group textarea { 
                width: 100%; 
                max-width: 600px; 
            }
            .fpdms-field-help { 
                font-size: 12px; 
                color: #646970; 
                font-style: italic; 
                margin-top: 4px; 
            }
        </style>';
    }
}

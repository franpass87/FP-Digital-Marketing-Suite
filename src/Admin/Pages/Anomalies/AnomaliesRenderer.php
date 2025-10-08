<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Anomalies;

use FP\DMS\Admin\Pages\Shared\FormRenderer;
use FP\DMS\Admin\Pages\Shared\TabsRenderer;
use FP\DMS\Admin\Pages\Shared\TableRenderer;
use FP\DMS\Support\I18n;
use function add_query_arg;
use function admin_url;
use function esc_attr;
use function esc_html;
use function esc_url;

/**
 * Renders UI components for the Anomalies page
 */
class AnomaliesRenderer
{
    /**
     * Render page header with tabs
     */
    public static function renderHeader(string $currentTab, int $clientId): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(I18n::__('Anomalies')) . '</h1>';
        
        $tabs = [
            'anomalies' => I18n::__('Recent Anomalies'),
            'policy' => I18n::__('Policy'),
        ];
        
        TabsRenderer::render($tabs, $currentTab, [
            'page' => 'fp-dms-anomalies',
            'client_id' => $clientId,
        ]);
    }

    /**
     * Render client filter form
     *
     * @param array<int, \FP\DMS\Domain\Entities\Client> $clients
     */
    public static function renderClientFilter(array $clients, int $selectedClientId, string $tab): void
    {
        echo '<form method="get" style="margin-top:20px;margin-bottom:20px;display:flex;gap:12px;align-items:center;">';
        
        FormRenderer::hidden('page', 'fp-dms-anomalies');
        FormRenderer::hidden('tab', $tab);
        
        $options = ['0' => I18n::__('All clients')];
        foreach ($clients as $client) {
            $options[(string) $client->id] = $client->name;
        }
        
        FormRenderer::select([
            'id' => 'fpdms-anomaly-client',
            'name' => 'client_id',
            'label' => I18n::__('Filter by client'),
            'options' => $options,
            'selected' => (string) $selectedClientId,
        ]);
        
        \submit_button(I18n::__('Apply'), '', '', false);
        
        echo '</form>';
    }

    /**
     * Render anomalies table
     *
     * @param array<int, \FP\DMS\Domain\Entities\Anomaly> $anomalies
     * @param array<int, string> $clientsMap
     */
    public static function renderAnomaliesTable(array $anomalies, array $clientsMap): void
    {
        $headers = [
            I18n::__('Detected at'),
            I18n::__('Client'),
            I18n::__('Metric'),
            I18n::__('Severity'),
            I18n::__('Δ %'),
            I18n::__('Z-score'),
            I18n::__('Note'),
            I18n::__('Actions'),
        ];
        
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        
        if (empty($anomalies)) {
            echo '<tr><td colspan="8">' . esc_html(I18n::__('No anomalies recorded.')) . '</td></tr>';
        } else {
            foreach ($anomalies as $anomaly) {
                self::renderAnomalyRow($anomaly, $clientsMap);
            }
        }
        
        echo '</tbody></table>';
    }

    /**
     * Render a single anomaly table row
     *
     * @param \FP\DMS\Domain\Entities\Anomaly $anomaly
     * @param array<int, string> $clientsMap
     */
    private static function renderAnomalyRow($anomaly, array $clientsMap): void
    {
        $data = AnomaliesDataService::formatAnomalyForDisplay($anomaly, $clientsMap);
        
        TableRenderer::startRow();
        TableRenderer::cell($data['detected_at']);
        TableRenderer::cell($data['client']);
        TableRenderer::cell($data['metric']);
        
        // Severity with badge
        $badgeClass = AnomaliesDataService::getSeverityBadgeClass($anomaly->severity);
        $severityHtml = '<span class="fpdms-badge ' . esc_attr($badgeClass) . '">' . esc_html($data['severity']) . '</span>';
        TableRenderer::rawCell($severityHtml);
        
        TableRenderer::cell($data['delta_percent']);
        TableRenderer::cell($data['zscore']);
        TableRenderer::cell($data['note']);
        
        // Actions
        self::renderAnomalyActions($anomaly);
        
        TableRenderer::endRow();
    }

    /**
     * Render actions for an anomaly
     *
     * @param \FP\DMS\Domain\Entities\Anomaly $anomaly
     */
    private static function renderAnomalyActions($anomaly): void
    {
        echo '<td>';
        
        $resolveUrl = add_query_arg([
            'page' => 'fp-dms-anomalies',
            'action' => 'resolve',
            'id' => $anomaly->id,
        ], admin_url('admin.php'));
        
        $deleteUrl = add_query_arg([
            'page' => 'fp-dms-anomalies',
            'action' => 'delete',
            'id' => $anomaly->id,
        ], admin_url('admin.php'));
        
        echo '<a href="' . esc_url($resolveUrl) . '" class="button button-small">' . esc_html(I18n::__('Resolve')) . '</a> ';
        echo '<a href="' . esc_url($deleteUrl) . '" class="button button-small" onclick="return confirm(\'' . esc_attr(I18n::__('Delete this anomaly?')) . '\');">' . esc_html(I18n::__('Delete')) . '</a>';
        
        echo '</td>';
    }

    /**
     * Render policy configuration form
     *
     * @param array<int, \FP\DMS\Domain\Entities\Client> $clients
     * @param array<string, mixed> $policy
     */
    public static function renderPolicyForm(array $clients, int $clientId, array $policy): void
    {
        echo '<div class="fpdms-section" style="max-width:800px;margin-top:20px;">';
        echo '<h2>' . esc_html(I18n::__('Anomaly Detection Policy')) . '</h2>';
        echo '<p>' . esc_html(I18n::__('Configure when and how anomalies are detected for your data.')) . '</p>';
        
        FormRenderer::open();
        FormRenderer::nonce('fpdms_anomaly_policy');
        FormRenderer::hidden('action', 'save_policy');
        
        // Client selector
        if (!empty($clients)) {
            $clientOptions = ['0' => I18n::__('Default policy')];
            foreach ($clients as $client) {
                $clientOptions[(string) $client->id] = $client->name;
            }
            
            echo '<p>';
            FormRenderer::select([
                'id' => 'fpdms-policy-client',
                'name' => 'client_id',
                'label' => I18n::__('Apply to:'),
                'options' => $clientOptions,
                'selected' => (string) $clientId,
            ]);
            echo '</p>';
        }
        
        // Enabled
        echo '<p>';
        FormRenderer::checkbox([
            'id' => 'fpdms-policy-enabled',
            'name' => 'enabled',
            'label' => I18n::__('Enable anomaly detection'),
            'checked' => $policy['enabled'] ?? true,
        ]);
        echo '</p>';
        
        // Sensitivity
        echo '<p>';
        $sensitivities = AnomaliesDataService::getSensitivityLevels();
        FormRenderer::select([
            'id' => 'fpdms-policy-sensitivity',
            'name' => 'sensitivity',
            'label' => I18n::__('Sensitivity:'),
            'options' => $sensitivities,
            'selected' => $policy['sensitivity'] ?? 'medium',
        ]);
        echo '</p>';
        
        // Metrics
        echo '<p><strong>' . esc_html(I18n::__('Monitor these metrics:')) . '</strong></p>';
        $metrics = AnomaliesDataService::getAvailableMetrics();
        $selectedMetrics = $policy['metrics'] ?? [];
        
        foreach ($metrics as $key => $label) {
            echo '<p>';
            FormRenderer::checkbox([
                'id' => 'fpdms-metric-' . $key,
                'name' => 'metrics[]',
                'label' => $label,
                'value' => $key,
                'checked' => in_array($key, $selectedMetrics, true),
            ]);
            echo '</p>';
        }
        
        echo '<p>';
        \submit_button(I18n::__('Save Policy'), 'primary', 'submit', false);
        echo '</p>';
        
        FormRenderer::close();
        echo '</div>';
    }

    /**
     * Close page wrapper
     */
    public static function renderFooter(): void
    {
        echo '</div>';
    }
}
<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\DataSource;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;
use WP_Error;

class DataSourcesPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $clientsRepo = new ClientsRepo();
        $dataSourcesRepo = new DataSourcesRepo();

        self::handleActions($clientsRepo, $dataSourcesRepo);
        self::bootNotices();

        $clients = $clientsRepo->all();
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Data Sources', 'fp-dms') . '</h1>';

        if (empty($clients)) {
            self::renderEmptyState();
            echo '</div>';
            return;
        }

        $selectedClientId = self::determineSelectedClientId($clients);
        $selectedClient = self::findClientById($clients, $selectedClientId);
        self::renderClientSelector($clients, $selectedClientId);

        settings_errors('fpdms_datasources');

        if ($selectedClient) {
            echo '<p>' . esc_html(sprintf(__('Manage the data sources linked to %s. Use the forms below to add connectors or ingest CSV exports.', 'fp-dms'), $selectedClient->name)) . '</p>';
        }

        $dataSources = $selectedClientId ? $dataSourcesRepo->forClient($selectedClientId) : [];
        $editing = null;
        if (isset($_GET['action'], $_GET['source']) && $_GET['action'] === 'edit') {
            $candidate = $dataSourcesRepo->find((int) $_GET['source']);
            if ($candidate && $candidate->clientId === $selectedClientId) {
                $editing = $candidate;
            }
        }

        $definitions = ProviderFactory::definitions();

        self::renderForm($selectedClientId, $editing, $definitions);
        self::renderList($dataSources, $definitions, $selectedClientId);

        echo '</div>';
    }

    private static function handleActions(ClientsRepo $clientsRepo, DataSourcesRepo $repo): void
    {
        if (! empty($_POST['fpdms_datasource_action'])) {
            $nonce = sanitize_text_field($_POST['fpdms_datasource_nonce'] ?? '');
            if (! wp_verify_nonce($nonce, 'fpdms_manage_datasource')) {
                add_settings_error('fpdms_datasources', 'fpdms_datasources_nonce', __('Security check failed. Please try again.', 'fp-dms'));
                self::storeAndRedirect((int) ($_POST['client_id'] ?? 0));
            }

            $action = sanitize_key($_POST['fpdms_datasource_action']);
            $clientId = (int) ($_POST['client_id'] ?? 0);

            if ($clientId <= 0 || ! $clientsRepo->find($clientId)) {
                add_settings_error('fpdms_datasources', 'fpdms_datasources_client', __('Select a valid client before managing data sources.', 'fp-dms'));
                self::storeAndRedirect(0);
            }

            if ($action === 'save') {
                $id = (int) ($_POST['data_source_id'] ?? 0);
                $type = sanitize_key($_POST['type'] ?? '');

                if ($type === '') {
                    add_settings_error('fpdms_datasources', 'fpdms_datasources_type', __('Select a connector type.', 'fp-dms'));
                    self::storeAndRedirect($clientId);
                }

                $existing = $id > 0 ? $repo->find($id) : null;
                if ($existing && $existing->clientId !== $clientId) {
                    add_settings_error('fpdms_datasources', 'fpdms_datasources_owner', __('This data source belongs to another client.', 'fp-dms'));
                    self::storeAndRedirect($clientId);
                }

                $payload = self::buildPayload($type, $existing);
                if ($payload instanceof WP_Error) {
                    add_settings_error('fpdms_datasources', $payload->get_error_code(), $payload->get_error_message());
                    self::storeAndRedirect($clientId);
                }

                $payload['client_id'] = $clientId;

                if ($existing) {
                    $repo->update($existing->id ?? 0, $payload);
                    add_settings_error('fpdms_datasources', 'fpdms_datasources_saved', __('Data source updated.', 'fp-dms'), 'updated');
                } else {
                    $created = $repo->create($payload);
                    if ($created === null) {
                        add_settings_error('fpdms_datasources', 'fpdms_datasources_error', __('Unable to create data source.', 'fp-dms'));
                    } else {
                        add_settings_error('fpdms_datasources', 'fpdms_datasources_saved', __('Data source created.', 'fp-dms'), 'updated');
                    }
                }

                self::storeAndRedirect($clientId);
            }

            if ($action === 'test') {
                $id = (int) ($_POST['data_source_id'] ?? 0);
                $dataSource = $repo->find($id);

                if (! $dataSource || $dataSource->clientId !== $clientId) {
                    add_settings_error('fpdms_datasources', 'fpdms_datasources_missing', __('Data source not found.', 'fp-dms'));
                    self::storeAndRedirect($clientId);
                }

                $provider = ProviderFactory::create($dataSource->type, $dataSource->auth, $dataSource->config);
                if (! $provider) {
                    add_settings_error('fpdms_datasources', 'fpdms_datasources_provider', __('This connector cannot be tested automatically.', 'fp-dms'));
                    self::storeAndRedirect($clientId);
                }

                $result = $provider->testConnection();
                $code = $result->isSuccess() ? 'fpdms_datasources_test_success' : 'fpdms_datasources_test_error';
                $type = $result->isSuccess() ? 'updated' : 'error';
                add_settings_error('fpdms_datasources', $code, $result->message(), $type);

                self::storeAndRedirect($clientId);
            }
        }

        if (isset($_GET['action'], $_GET['source']) && $_GET['action'] === 'delete') {
            $id = (int) $_GET['source'];
            $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
            $dataSource = $repo->find($id);
            $clientId = $dataSource?->clientId ?? (int) ($_GET['client'] ?? 0);

            if ($dataSource && wp_verify_nonce($nonce, 'fpdms_delete_datasource_' . $id)) {
                $repo->delete($id);
                add_settings_error('fpdms_datasources', 'fpdms_datasources_deleted', __('Data source deleted.', 'fp-dms'), 'updated');
            } else {
                add_settings_error('fpdms_datasources', 'fpdms_datasources_delete_error', __('Unable to delete data source.', 'fp-dms'));
            }

            self::storeAndRedirect($clientId);
        }
    }

    private static function storeAndRedirect(int $clientId): void
    {
        set_transient('fpdms_datasources_notices', get_settings_errors('fpdms_datasources'), 30);

        $args = ['page' => 'fp-dms-datasources'];
        if ($clientId > 0) {
            $args['client'] = $clientId;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function bootNotices(): void
    {
        $stored = get_transient('fpdms_datasources_notices');
        if (! is_array($stored)) {
            return;
        }

        foreach ($stored as $notice) {
            add_settings_error(
                'fpdms_datasources',
                $notice['code'] ?? uniqid('fpdms', true),
                $notice['message'] ?? '',
                $notice['type'] ?? 'updated'
            );
        }

        delete_transient('fpdms_datasources_notices');
    }

    private static function renderEmptyState(): void
    {
        echo '<div class="notice notice-info"><p>' . esc_html__('Add at least one client before configuring data sources.', 'fp-dms') . '</p>';
        $url = add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php'));
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">' . esc_html__('Add client', 'fp-dms') . '</a></p></div>';
    }

    /**
     * @param array<int,Client> $clients
     */
    private static function determineSelectedClientId(array $clients): ?int
    {
        $requested = isset($_GET['client']) ? (int) $_GET['client'] : 0;
        if ($requested > 0) {
            foreach ($clients as $client) {
                if ($client->id === $requested) {
                    return $requested;
                }
            }
        }

        return $clients[0]->id ?? null;
    }

    /**
     * @param array<int,Client> $clients
     */
    private static function findClientById(array $clients, ?int $id): ?Client
    {
        if (! $id) {
            return null;
        }

        foreach ($clients as $client) {
            if ($client->id === $id) {
                return $client;
            }
        }

        return null;
    }

    /**
     * @param array<int,Client> $clients
     */
    private static function renderClientSelector(array $clients, ?int $selectedId): void
    {
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="fp-dms-datasources">';
        echo '<label class="screen-reader-text" for="fpdms-datasource-client">' . esc_html__('Select client', 'fp-dms') . '</label>';
        echo '<select name="client" id="fpdms-datasource-client" onchange="this.form.submit();" style="min-width:240px;">';
        foreach ($clients as $client) {
            echo '<option value="' . esc_attr((string) $client->id) . '"' . selected($selectedId, $client->id, false) . '>' . esc_html($client->name) . '</option>';
        }
        echo '</select>';
        echo '<noscript><button type="submit" class="button">' . esc_html__('Filter', 'fp-dms') . '</button></noscript>';
        echo '</form>';
    }

    private static function renderForm(?int $clientId, ?DataSource $editing, array $definitions): void
    {
        if (! $clientId) {
            return;
        }

        $currentType = $editing->type ?? (array_key_first($definitions) ?? 'ga4');
        $isActive = $editing ? $editing->active : true;

        echo '<div class="card" style="margin-top:20px;padding:20px;max-width:960px;">';
        echo '<h2>' . esc_html($editing ? __('Edit data source', 'fp-dms') : __('Add data source', 'fp-dms')) . '</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('fpdms_manage_datasource', 'fpdms_datasource_nonce');
        echo '<input type="hidden" name="fpdms_datasource_action" value="save">';
        echo '<input type="hidden" name="client_id" value="' . esc_attr((string) $clientId) . '">';
        echo '<input type="hidden" name="data_source_id" value="' . esc_attr((string) ($editing->id ?? 0)) . '">';
        echo '<table class="form-table">';
        echo '<tbody>';
        echo '<tr><th scope="row"><label for="fpdms-datasource-type">' . esc_html__('Connector type', 'fp-dms') . '</label></th><td><select name="type" id="fpdms-datasource-type">';
        foreach ($definitions as $type => $definition) {
            echo '<option value="' . esc_attr($type) . '"' . selected($currentType, $type, false) . '>' . esc_html($definition['label'] ?? ucfirst($type)) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Status', 'fp-dms') . '</th><td><label><input type="checkbox" name="active" value="1"' . checked($isActive, true, false) . '> ' . esc_html__('Active', 'fp-dms') . '</label></td></tr>';
        echo '</tbody>';

        foreach ($definitions as $type => $definition) {
            $display = $type === $currentType ? 'table-row-group' : 'none';
            echo '<tbody class="fpdms-ds-fields" data-type="' . esc_attr($type) . '" style="display:' . esc_attr($display) . ';">';
            if (! empty($definition['description'])) {
                echo '<tr><th scope="row"></th><td><p class="description">' . esc_html($definition['description']) . '</p></td></tr>';
            }

            foreach (($definition['fields']['auth'] ?? []) as $field => $info) {
                $value = $editing && $editing->type === $type ? (string) ($editing->auth[$field] ?? '') : '';
                self::renderInputRow('auth[' . $field . ']', $info, $value);
            }

            foreach (($definition['fields']['config'] ?? []) as $field => $info) {
                $value = $editing && $editing->type === $type ? (string) ($editing->config[$field] ?? '') : '';
                self::renderInputRow('config[' . $field . ']', $info, $value);
            }

            foreach (($definition['fields']['uploads'] ?? []) as $field => $info) {
                self::renderUploadRow($field, $info);
            }

            if ($editing && $editing->type === $type) {
                self::renderExistingSummaryRow($editing);
            }

            echo '</tbody>';
        }

        echo '</table>';
        submit_button($editing ? __('Update data source', 'fp-dms') : __('Add data source', 'fp-dms'));
        echo '</form>';
        echo '</div>';

        echo '<script>document.addEventListener("DOMContentLoaded",function(){var select=document.getElementById("fpdms-datasource-type");if(!select){return;}var toggle=function(){var current=select.value;document.querySelectorAll(".fpdms-ds-fields").forEach(function(group){group.style.display=group.getAttribute("data-type")===current?"table-row-group":"none";});};select.addEventListener("change",toggle);toggle();});</script>';
    }

    private static function renderInputRow(string $name, array $info, string $value): void
    {
        $label = $info['label'] ?? '';
        $description = $info['description'] ?? '';
        $type = $info['type'] ?? 'text';

        echo '<tr><th scope="row"><label>' . esc_html($label) . '</label></th><td>';
        if ($type === 'textarea') {
            echo '<textarea name="' . esc_attr($name) . '" rows="6" class="large-text code">' . esc_textarea($value) . '</textarea>';
        } else {
            $inputType = $type === 'url' ? 'url' : 'text';
            echo '<input type="' . esc_attr($inputType) . '" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        }
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td></tr>';
    }

    private static function renderUploadRow(string $field, array $info): void
    {
        $label = $info['label'] ?? '';
        $description = $info['description'] ?? '';
        $inputId = 'fpdms-' . $field;

        echo '<tr><th scope="row"><label for="' . esc_attr($inputId) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="file" name="' . esc_attr($field) . '" id="' . esc_attr($inputId) . '" accept=".csv,text/csv">';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td></tr>';
    }

    private static function renderExistingSummaryRow(DataSource $dataSource): void
    {
        $summary = $dataSource->config['summary'] ?? null;
        if (! is_array($summary) || empty($summary)) {
            return;
        }

        echo '<tr><th scope="row">' . esc_html__('Current summary', 'fp-dms') . '</th><td>';
        echo '<p>' . esc_html(self::formatSummary($dataSource)) . '</p>';
        echo '<p class="description">' . esc_html__('Upload a new CSV to refresh the aggregated metrics.', 'fp-dms') . '</p>';
        echo '</td></tr>';
    }

    /**
     * @param array<int,DataSource> $dataSources
     */
    private static function renderList(array $dataSources, array $definitions, int $clientId): void
    {
        echo '<h2 style="margin-top:40px;">' . esc_html__('Configured data sources', 'fp-dms') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__('Type', 'fp-dms') . '</th><th>' . esc_html__('Status', 'fp-dms') . '</th><th>' . esc_html__('Details', 'fp-dms') . '</th><th>' . esc_html__('Actions', 'fp-dms') . '</th></tr></thead><tbody>';

        if (empty($dataSources)) {
            echo '<tr><td colspan="4">' . esc_html__('No data sources configured yet.', 'fp-dms') . '</td></tr>';
        }

        foreach ($dataSources as $dataSource) {
            $label = $definitions[$dataSource->type]['label'] ?? ucwords(str_replace('_', ' ', $dataSource->type));
            $status = $dataSource->active ? __('Active', 'fp-dms') : __('Inactive', 'fp-dms');
            $details = self::formatSummary($dataSource);
            $editUrl = add_query_arg([
                'page' => 'fp-dms-datasources',
                'client' => $clientId,
                'action' => 'edit',
                'source' => $dataSource->id,
            ], admin_url('admin.php'));
            $deleteUrl = wp_nonce_url(add_query_arg([
                'page' => 'fp-dms-datasources',
                'client' => $clientId,
                'action' => 'delete',
                'source' => $dataSource->id,
            ], admin_url('admin.php')), 'fpdms_delete_datasource_' . $dataSource->id);

            echo '<tr>';
            echo '<td>' . esc_html($label) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html($details) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'fp-dms') . '</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'' . esc_js(__('Delete this data source?', 'fp-dms')) . '\');">' . esc_html__('Delete', 'fp-dms') . '</a>';
            echo '<form method="post" style="display:inline;margin-left:8px;">';
            wp_nonce_field('fpdms_manage_datasource', 'fpdms_datasource_nonce');
            echo '<input type="hidden" name="fpdms_datasource_action" value="test">';
            echo '<input type="hidden" name="data_source_id" value="' . esc_attr((string) $dataSource->id) . '">';
            echo '<input type="hidden" name="client_id" value="' . esc_attr((string) $clientId) . '">';
            echo '<button type="submit" class="button button-small" style="margin-left:6px;">' . esc_html__('Test connection', 'fp-dms') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function buildPayload(string $type, ?DataSource $existing): array|WP_Error
    {
        $active = ! empty($_POST['active']);
        $auth = [];
        $config = [];

        switch ($type) {
            case 'ga4':
                $serviceAccount = isset($_POST['auth']['service_account']) ? trim((string) wp_unslash($_POST['auth']['service_account'])) : '';
                $propertyId = sanitize_text_field($_POST['config']['property_id'] ?? '');
                if ($serviceAccount === '' || $propertyId === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Service account JSON and property ID are required for GA4.', 'fp-dms'));
                }
                $auth['service_account'] = $serviceAccount;
                $config['property_id'] = $propertyId;
                break;
            case 'gsc':
                $serviceAccount = isset($_POST['auth']['service_account']) ? trim((string) wp_unslash($_POST['auth']['service_account'])) : '';
                $siteUrl = esc_url_raw($_POST['config']['site_url'] ?? '');
                if ($serviceAccount === '' || $siteUrl === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Service account JSON and site URL are required for Google Search Console.', 'fp-dms'));
                }
                $auth['service_account'] = $serviceAccount;
                $config['site_url'] = $siteUrl;
                break;
            case 'google_ads':
            case 'meta_ads':
                $accountName = sanitize_text_field($_POST['config']['account_name'] ?? '');
                if ($accountName === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Account label is required for ads CSV connectors.', 'fp-dms'));
                }
                $config['account_name'] = $accountName;
                $summary = self::ingestCsvSummary('csv_file');
                if ($summary instanceof WP_Error) {
                    return $summary;
                }
                if ($summary === null && (! $existing || $existing->type !== $type || empty($existing->config['summary']))) {
                    return new WP_Error('fpdms_datasource_csv', __('Upload a CSV export to initialise this connector.', 'fp-dms'));
                }
                if ($summary === null && $existing && isset($existing->config['summary'])) {
                    $summary = $existing->config['summary'];
                }
                if (is_array($summary)) {
                    $config['summary'] = $summary;
                }
                break;
            case 'clarity':
                $siteUrl = esc_url_raw($_POST['config']['site_url'] ?? '');
                if ($siteUrl === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Site URL is required for Microsoft Clarity.', 'fp-dms'));
                }
                $config['site_url'] = $siteUrl;
                $webhook = esc_url_raw($_POST['config']['webhook_url'] ?? '');
                if ($webhook) {
                    $config['webhook_url'] = $webhook;
                }
                $summary = self::ingestCsvSummary('csv_file');
                if ($summary instanceof WP_Error) {
                    return $summary;
                }
                if ($summary === null && (! $existing || $existing->type !== $type || empty($existing->config['summary']))) {
                    return new WP_Error('fpdms_datasource_csv', __('Upload the Clarity CSV export to populate metrics.', 'fp-dms'));
                }
                if ($summary === null && $existing && isset($existing->config['summary'])) {
                    $summary = $existing->config['summary'];
                }
                if (is_array($summary)) {
                    $config['summary'] = $summary;
                }
                break;
            case 'csv_generic':
                $label = sanitize_text_field($_POST['config']['source_label'] ?? '');
                if ($label === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Provide a label for the custom CSV data source.', 'fp-dms'));
                }
                $config['source_label'] = $label;
                $summary = self::ingestCsvSummary('csv_file');
                if ($summary instanceof WP_Error) {
                    return $summary;
                }
                if ($summary === null && (! $existing || $existing->type !== $type || empty($existing->config['summary']))) {
                    return new WP_Error('fpdms_datasource_csv', __('Upload a CSV file so metrics can be summarised.', 'fp-dms'));
                }
                if ($summary === null && $existing && isset($existing->config['summary'])) {
                    $summary = $existing->config['summary'];
                }
                if (is_array($summary)) {
                    $config['summary'] = $summary;
                }
                break;
            default:
                return new WP_Error('fpdms_datasource_type', __('Unsupported data source type.', 'fp-dms'));
        }

        return [
            'type' => $type,
            'auth' => $auth,
            'config' => $config,
            'active' => $active ? 1 : 0,
        ];
    }

    private static function ingestCsvSummary(string $field): array|WP_Error|null
    {
        if (empty($_FILES[$field]) || ! is_array($_FILES[$field])) {
            return null;
        }

        $file = $_FILES[$field];
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return new WP_Error('fpdms_datasource_csv', __('Failed to upload CSV file. Please try again.', 'fp-dms'));
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (! $handle) {
            return new WP_Error('fpdms_datasource_csv', __('Unable to read the uploaded CSV file.', 'fp-dms'));
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            @unlink($file['tmp_name']);
            return new WP_Error('fpdms_datasource_csv', __('The CSV file appears to be empty.', 'fp-dms'));
        }

        $keys = array_map(static fn($value) => sanitize_key((string) $value), $header);
        $aliases = [
            'date' => ['date', 'day'],
            'users' => ['users'],
            'sessions' => ['sessions', 'visits'],
            'clicks' => ['clicks'],
            'impressions' => ['impressions', 'impr'],
            'conversions' => ['conversions', 'purchases', 'leads'],
            'cost' => ['cost'],
            'spend' => ['spend', 'amount_spent'],
            'revenue' => ['revenue', 'total_revenue', 'value'],
            'rage_clicks' => ['rage_clicks'],
            'dead_clicks' => ['dead_clicks'],
        ];

        $dateColumn = null;
        foreach ($aliases['date'] as $candidate) {
            if (in_array($candidate, $keys, true)) {
                $dateColumn = $candidate;
                break;
            }
        }
        unset($aliases['date']);

        $totals = array_fill_keys(array_keys($aliases), 0.0);
        $daily = [];
        $rows = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (! is_array($row)) {
                continue;
            }
            $rows++;
            $assoc = [];
            foreach ($keys as $index => $key) {
                $assoc[$key] = $row[$index] ?? '';
            }

            $date = null;
            if ($dateColumn && isset($assoc[$dateColumn])) {
                $date = self::normalizeDate((string) $assoc[$dateColumn]);
            }
            $date = $date ?? 'total';

            foreach ($aliases as $target => $sourceKeys) {
                foreach ($sourceKeys as $sourceKey) {
                    if (! isset($assoc[$sourceKey]) || $assoc[$sourceKey] === '') {
                        continue;
                    }
                    $value = self::normalizeNumber((string) $assoc[$sourceKey]);
                    $totals[$target] += $value;
                    $daily[$date][$target] = ($daily[$date][$target] ?? 0.0) + $value;
                    break;
                }
            }
        }

        fclose($handle);
        @unlink($file['tmp_name']);

        if ($rows === 0) {
            return new WP_Error('fpdms_datasource_csv', __('The CSV file did not contain any data rows.', 'fp-dms'));
        }

        if (($totals['cost'] ?? 0.0) === 0.0 && isset($daily['total']['cost']) && $daily['total']['cost'] > 0.0) {
            $totals['cost'] = $daily['total']['cost'];
        }
        if (isset($totals['cost'], $totals['conversions']) && $totals['cost'] > 0.0 && ! isset($totals['revenue'])) {
            $totals['revenue'] = $totals['revenue'] ?? 0.0;
        }

        ksort($daily);
        $daily = array_map(static function (array $metrics): array {
            foreach ($metrics as $key => $value) {
                $metrics[$key] = round((float) $value, 2);
            }

            return $metrics;
        }, $daily);

        return [
            'metrics' => array_map(static fn(float $value): float => round($value, 2), $totals),
            'daily' => $daily,
            'rows' => $rows,
            'last_ingested_at' => current_time('mysql'),
        ];
    }

    private static function normalizeNumber(string $value): float
    {
        $clean = preg_replace('/[^0-9,\.\-]/', '', $value);
        if ($clean === null || $clean === '') {
            return 0.0;
        }
        $clean = str_replace(',', '', $clean);

        return (float) $clean;
    }

    private static function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return wp_date('Y-m-d', $timestamp);
    }

    private static function formatSummary(DataSource $dataSource): string
    {
        $details = [];
        $config = $dataSource->config;

        if (! empty($config['account_name'])) {
            $details[] = sprintf(__('Account: %s', 'fp-dms'), $config['account_name']);
        }
        if (! empty($config['source_label'])) {
            $details[] = sprintf(__('Source: %s', 'fp-dms'), $config['source_label']);
        }
        if (! empty($config['site_url'])) {
            $details[] = sprintf(__('Site: %s', 'fp-dms'), $config['site_url']);
        }

        $summary = $config['summary'] ?? null;
        if (is_array($summary)) {
            if (! empty($summary['rows'])) {
                $details[] = sprintf(__('Rows: %d', 'fp-dms'), (int) $summary['rows']);
            }
            $metrics = is_array($summary['metrics'] ?? null) ? $summary['metrics'] : [];
            $metricParts = [];
            foreach (['users', 'sessions', 'clicks', 'impressions', 'conversions', 'spend', 'cost', 'revenue', 'rage_clicks', 'dead_clicks'] as $metric) {
                if (! empty($metrics[$metric])) {
                    $metricParts[] = sprintf('%s %s', self::formatMetricLabel($metric), self::formatNumber((float) $metrics[$metric]));
                }
            }
            if (! empty($metricParts)) {
                $details[] = implode(', ', $metricParts);
            }
            if (! empty($summary['last_ingested_at'])) {
                $details[] = sprintf(__('Updated %s', 'fp-dms'), self::formatDateTime((string) $summary['last_ingested_at']));
            }
        }

        if (empty($details)) {
            return __('No summary available.', 'fp-dms');
        }

        return implode(' • ', $details);
    }

    private static function formatMetricLabel(string $metric): string
    {
        $labels = [
            'users' => __('Users:', 'fp-dms'),
            'sessions' => __('Sessions:', 'fp-dms'),
            'clicks' => __('Clicks:', 'fp-dms'),
            'impressions' => __('Impressions:', 'fp-dms'),
            'conversions' => __('Conversions:', 'fp-dms'),
            'spend' => __('Spend:', 'fp-dms'),
            'cost' => __('Cost:', 'fp-dms'),
            'revenue' => __('Revenue:', 'fp-dms'),
            'rage_clicks' => __('Rage clicks:', 'fp-dms'),
            'dead_clicks' => __('Dead clicks:', 'fp-dms'),
        ];

        return $labels[$metric] ?? (ucwords(str_replace('_', ' ', $metric)) . ':');
    }

    private static function formatNumber(float $value): string
    {
        $rounded = round($value, 2);
        if (abs($rounded - round($rounded)) < 0.01) {
            return number_format_i18n((int) round($rounded));
        }

        return number_format_i18n($rounded, 2);
    }

    private static function formatDateTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (! $timestamp) {
            return $datetime;
        }

        return wp_date('Y-m-d H:i', $timestamp);
    }
}

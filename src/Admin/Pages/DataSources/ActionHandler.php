<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\DataSources;

use FP\DMS\Admin\Pages\DataSources\PayloadValidator;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;
use FP\DMS\Support\Wp;
use WP_Error;

/**
 * Handles all actions for Data Sources (save, test, delete).
 */
class ActionHandler
{
    private ClientsRepo $clientsRepo;
    private DataSourcesRepo $dataSourcesRepo;

    public function __construct(ClientsRepo $clientsRepo, DataSourcesRepo $dataSourcesRepo)
    {
        $this->clientsRepo = $clientsRepo;
        $this->dataSourcesRepo = $dataSourcesRepo;
    }

    public function handleActions(): void
    {
        if (!empty($_POST['fpdms_datasource_action'])) {
            $this->handlePostActions();
        }

        if (isset($_GET['action'], $_GET['source']) && $_GET['action'] === 'delete') {
            $this->handleDelete();
        }
    }

    private function handlePostActions(): void
    {
        $nonce = Wp::sanitizeTextField($_POST['fpdms_datasource_nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'fpdms_manage_datasource')) {
            add_settings_error('fpdms_datasources', 'fpdms_datasources_nonce', 
                __('Security check failed. Please try again.', 'fp-dms'));
            $this->storeAndRedirect((int) ($_POST['client_id'] ?? 0));
        }

        $action = Wp::sanitizeKey($_POST['fpdms_datasource_action']);
        $clientId = (int) ($_POST['client_id'] ?? 0);

        if (!$this->validateClient($clientId)) {
            $this->storeAndRedirect(0);
        }

        match ($action) {
            'save' => $this->handleSave($clientId),
            'test' => $this->handleTest($clientId),
            default => null,
        };
    }

    private function validateClient(int $clientId): bool
    {
        if ($clientId <= 0 || !$this->clientsRepo->find($clientId)) {
            add_settings_error('fpdms_datasources', 'fpdms_datasources_client', 
                __('Select a valid client before managing data sources.', 'fp-dms'));
            return false;
        }
        return true;
    }

    private function handleSave(int $clientId): void
    {
        $id = (int) ($_POST['data_source_id'] ?? 0);
        $type = Wp::sanitizeKey($_POST['type'] ?? '');

        if ($type === '') {
            add_settings_error('fpdms_datasources', 'fpdms_datasources_type', 
                __('Select a connector type.', 'fp-dms'));
            $this->storeAndRedirect($clientId);
        }

        $existing = $id > 0 ? $this->dataSourcesRepo->find($id) : null;
        
        if ($existing && $existing->clientId !== $clientId) {
            add_settings_error('fpdms_datasources', 'fpdms_datasources_owner', 
                __('This data source belongs to another client.', 'fp-dms'));
            $this->storeAndRedirect($clientId);
        }

        $validator = new PayloadValidator();
        $payload = $validator->buildPayload($type, $existing);
        
        if ($payload instanceof WP_Error) {
            add_settings_error('fpdms_datasources', $payload->get_error_code(), 
                $payload->get_error_message());
            $this->storeAndRedirect($clientId);
        }

        $payload['client_id'] = $clientId;

        if ($existing) {
            $this->dataSourcesRepo->update($existing->id ?? 0, $payload);
            add_settings_error('fpdms_datasources', 'fpdms_datasources_saved', 
                __('Data source updated.', 'fp-dms'), 'updated');
        } else {
            $created = $this->dataSourcesRepo->create($payload);
            if ($created === null) {
                add_settings_error('fpdms_datasources', 'fpdms_datasources_error', 
                    __('Unable to create data source.', 'fp-dms'));
            } else {
                add_settings_error('fpdms_datasources', 'fpdms_datasources_saved', 
                    __('Data source created.', 'fp-dms'), 'updated');
            }
        }

        $this->storeAndRedirect($clientId);
    }

    private function handleTest(int $clientId): void
    {
        $id = (int) ($_POST['data_source_id'] ?? 0);
        $dataSource = $this->dataSourcesRepo->find($id);

        if (!$dataSource || $dataSource->clientId !== $clientId) {
            add_settings_error('fpdms_datasources', 'fpdms_datasources_missing', 
                __('Data source not found.', 'fp-dms'));
            $this->storeAndRedirect($clientId);
        }

        $provider = ProviderFactory::create($dataSource->type, $dataSource->auth, $dataSource->config);
        
        if (!$provider) {
            add_settings_error('fpdms_datasources', 'fpdms_datasources_provider', 
                __('This connector cannot be tested automatically.', 'fp-dms'));
            $this->storeAndRedirect($clientId);
        }

        $result = $provider->testConnection();
        $code = $result->isSuccess() ? 'fpdms_datasources_test_success' : 'fpdms_datasources_test_error';
        $type = $result->isSuccess() ? 'updated' : 'error';
        
        add_settings_error('fpdms_datasources', $code, $result->message(), $type);
        $this->storeAndRedirect($clientId);
    }

    private function handleDelete(): void
    {
        $id = (int) $_GET['source'];
        $nonce = Wp::sanitizeTextField($_GET['_wpnonce'] ?? '');
        $dataSource = $this->dataSourcesRepo->find($id);
        $clientId = $dataSource?->clientId ?? (int) ($_GET['client'] ?? 0);

        if ($dataSource && wp_verify_nonce($nonce, 'fpdms_delete_datasource_' . $id)) {
            $this->dataSourcesRepo->delete($id);
            add_settings_error('fpdms_datasources', 'fpdms_datasources_deleted', 
                __('Data source deleted.', 'fp-dms'), 'updated');
        } else {
            add_settings_error('fpdms_datasources', 'fpdms_datasources_delete_error', 
                __('Unable to delete data source.', 'fp-dms'));
        }

        $this->storeAndRedirect($clientId);
    }

    private function storeAndRedirect(int $clientId): void
    {
        set_transient('fpdms_datasources_notices', get_settings_errors('fpdms_datasources'), 30);

        $args = ['page' => 'fp-dms-datasources'];
        if ($clientId > 0) {
            $args['client'] = $clientId;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
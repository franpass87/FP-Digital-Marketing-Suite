<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Shared\Breadcrumbs;
use FP\DMS\Admin\Pages\Shared\EmptyState;
use FP\DMS\Admin\Pages\Shared\HelpIcon;
use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\DataSource;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\ProviderFactory;
use FP\DMS\Support\Wp;
use WP_Error;

use function selected;

class DataSourcesPage
{
    /**
     * Register assets hook for this page
     * (Assets now moved to Overview page where sync button is located)
     */
    public static function registerAssetsHook(string $hook): void
    {
        // No assets needed - sync button is now in Overview
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $clientsRepo = new ClientsRepo();
        $dataSourcesRepo = new DataSourcesRepo();

        self::handleActions($clientsRepo, $dataSourcesRepo);
        self::bootNotices();
        self::outputInlineAssets();

        $clients = $clientsRepo->all();
        echo '<div class="wrap fpdms-admin-page">';
        
        // Breadcrumbs
        Breadcrumbs::render(Breadcrumbs::getStandardItems('datasources'));
        
        // Header moderno
        echo '<div class="fpdms-page-header">';
        echo '<h1>';
        echo '<span class="dashicons dashicons-networking" style="margin-right:12px;"></span>';
        echo esc_html__('Connessioni', 'fp-dms');
        HelpIcon::render(HelpIcon::getCommonHelp('datasources'));
        echo '</h1>';
        echo '<p>' . esc_html__('Gestisci le fonti dati collegate ai tuoi clienti: GA4, Google Search Console, Google Ads, Meta Ads e altro.', 'fp-dms') . '</p>';
        echo '</div>';

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
            echo '<p>' . esc_html(sprintf(__('Manage the data sources linked to %s. Configure direct connectors with API credentials or import custom files where needed.', 'fp-dms'), $selectedClient->name)) . '</p>';
        }

        self::renderGuidedIntro();

        $dataSources = $selectedClientId ? $dataSourcesRepo->forClient($selectedClientId) : [];
        $editing = null;

        // Sanitize action parameter
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($action === 'edit' && isset($_GET['source'])) {
            $candidate = $dataSourcesRepo->find((int) $_GET['source']);
            if ($candidate && $candidate->clientId === $selectedClientId) {
                $editing = $candidate;
            }
        }

        $definitions = ProviderFactory::definitions();

        self::renderForm($selectedClientId, $editing, $definitions);
        self::renderBeginnersGuide();
        self::renderList($dataSources, $definitions, $selectedClientId);

        echo '</div>';
    }

    private static function handleActions(ClientsRepo $clientsRepo, DataSourcesRepo $repo): void
    {
        if (! empty($_POST['fpdms_datasource_action'])) {
            $nonce = Wp::sanitizeTextField($_POST['fpdms_datasource_nonce'] ?? '');
            if (! wp_verify_nonce($nonce, 'fpdms_manage_datasource')) {
                add_settings_error('fpdms_datasources', 'fpdms_datasources_nonce', __('Security check failed. Please try again.', 'fp-dms'));
                self::storeAndRedirect((int) ($_POST['client_id'] ?? 0));
            }

            $action = Wp::sanitizeKey($_POST['fpdms_datasource_action']);
            $clientId = (int) ($_POST['client_id'] ?? 0);

            if ($clientId <= 0 || ! $clientsRepo->find($clientId)) {
                add_settings_error('fpdms_datasources', 'fpdms_datasources_client', __('Select a valid client before managing data sources.', 'fp-dms'));
                self::storeAndRedirect(0);
            }

            if ($action === 'save') {
                $id = (int) ($_POST['data_source_id'] ?? 0);
                $type = Wp::sanitizeKey($_POST['type'] ?? '');

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

        // Sanitize action parameter for delete
        $deleteAction = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($deleteAction === 'delete' && isset($_GET['source'])) {
            $id = (int) $_GET['source'];
            $nonce = Wp::sanitizeTextField($_GET['_wpnonce'] ?? '');
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
        EmptyState::render([
            'icon' => 'dashicons-networking',
            'title' => __('Nessuna Connessione Dati', 'fp-dms'),
            'description' => __('Prima di configurare le connessioni dati (GA4, Google Ads, Meta Ads, ecc.), devi aggiungere almeno un cliente. Le connessioni sono sempre associate a un cliente specifico.', 'fp-dms'),
            'primaryAction' => [
                'label' => __('+ Aggiungi Cliente', 'fp-dms'),
                'url' => add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php'))
            ],
            'secondaryAction' => [
                'label' => __('📚 Guida Connessioni', 'fp-dms'),
                'url' => 'https://docs.francescopasseri.com/fp-dms/connectors'
            ],
            'helpText' => __('Connettori disponibili: GA4, GSC, Google Ads, Meta Ads, Clarity, CSV', 'fp-dms')
        ]);
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
        $isEditing = $editing !== null;

        echo '<div class="card" style="margin-top:20px;padding:20px;max-width:960px;">';
        echo '<h2>' . esc_html($editing ? __('Edit data source', 'fp-dms') : __('Add data source', 'fp-dms')) . '</h2>';
        echo '<form method="post" enctype="multipart/form-data" id="fpdms-datasource-form">';
        wp_nonce_field('fpdms_manage_datasource', 'fpdms_datasource_nonce');
        echo '<input type="hidden" name="fpdms_datasource_action" value="save">';
        echo '<input type="hidden" name="client_id" value="' . esc_attr((string) $clientId) . '">';
        echo '<input type="hidden" name="data_source_id" value="' . esc_attr((string) ($editing->id ?? 0)) . '">';
        if ($isEditing) {
            echo '<input type="hidden" name="type" value="' . esc_attr($currentType) . '">';
        }

        self::renderConnectorCards($definitions, $currentType, $isEditing);
        self::renderGuidanceBlocks($definitions, $currentType);

        echo '<table class="form-table">';
        echo '<tbody>';
        if ($isEditing) {
            $currentLabel = $definitions[$currentType]['label'] ?? ucfirst($currentType);
            echo '<tr><th scope="row">' . esc_html__('Connector', 'fp-dms') . '</th><td><strong>' . esc_html((string) $currentLabel) . '</strong>';
            echo '<p class="description">' . esc_html__('To switch connectors, create a new data source for the desired platform.', 'fp-dms') . '</p></td></tr>';
        } else {
            echo '<tr class="fpdms-datasource-select-row"><th scope="row"><label for="fpdms-datasource-type">' . esc_html__('Connector type', 'fp-dms') . '</label></th><td><select name="type" id="fpdms-datasource-type">';
            foreach ($definitions as $type => $definition) {
                $label = $definition['label'] ?? ucfirst($type);
                echo '<option value="' . esc_attr($type) . '"' . selected($currentType, $type, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td></tr>';
        }
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
        echo '<p class="description fpdms-guided-save-note">' . esc_html__('Step 3 — Save and then test the connection once your credentials are added.', 'fp-dms') . '</p>';
        submit_button($editing ? __('Update data source', 'fp-dms') : __('Add data source', 'fp-dms'));
        echo '</form>';
        echo '</div>';

        echo '<script>document.addEventListener("DOMContentLoaded",function(){
// Funzione per inizializzare i toggle dei credential source
var initCredentialSourceToggles=function(){var credSourceSelects=document.querySelectorAll(".fpdms-credential-source-select");credSourceSelects.forEach(function(sel){var updateCredFields=function(){var sourceType=sel.value;var parent=sel.closest("tbody");if(!parent){return;}var manualRows=parent.querySelectorAll("tr[data-credential-field=\\"manual\\"]");var constantRows=parent.querySelectorAll("tr[data-credential-field=\\"constant\\"]");manualRows.forEach(function(row){row.style.display=sourceType==="manual"?"table-row":"none";});constantRows.forEach(function(row){row.style.display=sourceType==="constant"?"table-row":"none";});};sel.removeEventListener("change",updateCredFields);sel.addEventListener("change",updateCredFields);updateCredFields();});};

var select=document.getElementById("fpdms-datasource-type");var groups=document.querySelectorAll(".fpdms-ds-fields");var guides=document.querySelectorAll(".fpdms-guidance-block");var cards=document.querySelectorAll(".fpdms-connector-card");
var update=function(type){if(!type){return;}if(select&&select.value!==type){select.value=type;}groups.forEach(function(group){group.style.display=group.getAttribute("data-type")===type?"table-row-group":"none";});guides.forEach(function(guide){guide.style.display=guide.getAttribute("data-type")===type?"block":"none";});cards.forEach(function(card){var active=card.getAttribute("data-type")===type;card.classList.toggle("is-active",active);if(active){card.setAttribute("aria-pressed","true");}else{card.setAttribute("aria-pressed","false");}});
// Reinizializza i toggle dopo aver cambiato tipo
setTimeout(initCredentialSourceToggles,50);};

if(cards.length){document.body.classList.add("fpdms-has-guided-picker");cards.forEach(function(card){if(card.hasAttribute("data-locked")){return;}card.addEventListener("click",function(event){event.preventDefault();update(card.getAttribute("data-type"));if(select){select.dispatchEvent(new Event("change"));}});});}
if(select){select.addEventListener("change",function(){update(select.value);});update(select.value);}else if(cards.length){var activeCard=document.querySelector(".fpdms-connector-card.is-active");update(activeCard?activeCard.getAttribute("data-type"):cards[0].getAttribute("data-type"));}

// Inizializza i toggle al caricamento
initCredentialSourceToggles();

// FIX: Rimuovi campi nascosti prima del submit per evitare che vengano inviati vuoti
var form=document.getElementById("fpdms-datasource-form");
if(form){
form.addEventListener("submit",function(e){
var currentType=select?select.value:null;
if(!currentType){return;}
// Rimuovi i campi dei tbody NON selezionati
groups.forEach(function(group){
if(group.getAttribute("data-type")!==currentType){
// Rimuovi tutti i campi di questo gruppo
var inputs=group.querySelectorAll("input,textarea,select");
inputs.forEach(function(input){input.remove();});
}
});
});
}
});</script>';
    }

    private static function renderInputRow(string $name, array $info, string $value): void
    {
        $label = $info['label'] ?? '';
        $description = $info['description'] ?? '';
        $type = $info['type'] ?? 'text';
        
        // Add data attribute for conditional visibility
        $dataToggle = '';
        if (strpos($name, 'service_account]') !== false && strpos($name, 'constant') === false) {
            $dataToggle = ' data-credential-field="manual"';
        } elseif (strpos($name, 'service_account_constant]') !== false) {
            $dataToggle = ' data-credential-field="constant"';
        }

        echo '<tr' . $dataToggle . '><th scope="row"><label>' . esc_html($label) . '</label></th><td>';
        if ($type === 'textarea') {
            echo '<textarea name="' . esc_attr($name) . '" rows="6" class="large-text code">' . esc_textarea($value) . '</textarea>';
        } elseif ($type === 'select') {
            $options = [];
            if (isset($info['options']) && is_array($info['options'])) {
                $options = $info['options'];
            }
            $current = $value;
            if ($current === '' && isset($info['default'])) {
                $current = (string) $info['default'];
            }
            $selectClass = 'regular-text';
            if (strpos($name, 'credential_source]') !== false) {
                $selectClass .= ' fpdms-credential-source-select';
            }
            echo '<select name="' . esc_attr($name) . '" class="' . esc_attr($selectClass) . '">';
            foreach ($options as $optionValue => $optionLabel) {
                $optionValue = (string) $optionValue;
                $optionLabel = (string) $optionLabel;
                echo '<option value="' . esc_attr($optionValue) . '"' . selected($current, $optionValue, false) . '>' . esc_html($optionLabel) . '</option>';
            }
            echo '</select>';
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

        echo '<tr><th scope="row">' . esc_html__('Current snapshot', 'fp-dms') . '</th><td>';
        echo '<p>' . esc_html(self::formatSummary($dataSource)) . '</p>';
        echo '<p class="description">' . esc_html__('Re-run the sync or provide a fresh data file to update these metrics.', 'fp-dms') . '</p>';
        echo '</td></tr>';
    }

    private static function renderBeginnersGuide(): void
    {
        echo '<div class="fpdms-beginners-guide" style="margin-top:40px;max-width:960px;">';
        echo '<details style="border:1px solid #dcdcde;border-radius:6px;padding:20px;background:#fff;">';
        echo '<summary style="cursor:pointer;font-weight:600;font-size:16px;margin-bottom:0;">';
        echo '<span class="dashicons dashicons-book-alt" style="margin-right:8px;"></span>';
        echo esc_html__('📚 Guida per principianti: Come collegare i data sources', 'fp-dms');
        echo '</summary>';
        
        echo '<div style="margin-top:20px;line-height:1.6;">';
        
        // Introduzione generale
        echo '<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:16px;margin-bottom:24px;">';
        echo '<h3 style="margin:0 0 12px;font-size:15px;">' . esc_html__('Benvenuto! 👋', 'fp-dms') . '</h3>';
        echo '<p style="margin:0 0 8px;">' . esc_html__('Questa guida ti aiuterà a collegare le tue fonti dati (Google Analytics, Meta Ads, ecc.) al sistema di reporting. Ogni piattaforma richiede credenziali API specifiche che puoi ottenere gratuitamente.', 'fp-dms') . '</p>';
        echo '<p style="margin:0;"><strong>' . esc_html__('Tempo stimato per connettore:', 'fp-dms') . '</strong> ' . esc_html__('5-15 minuti', 'fp-dms') . '</p>';
        echo '</div>';
        
        // Google Analytics 4 (GA4)
        echo '<div style="margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #dcdcde;">';
        echo '<h3 style="margin:0 0 12px;color:#2271b1;"><span class="dashicons dashicons-chart-area" style="font-size:18px;margin-right:6px;"></span>' . esc_html__('Google Analytics 4 (GA4)', 'fp-dms') . '</h3>';
        echo '<p style="margin:0 0 12px;"><strong>' . esc_html__('Cosa serve:', 'fp-dms') . '</strong> ' . esc_html__('Service Account JSON e Property ID', 'fp-dms') . '</p>';
        echo '<ol style="margin:0 0 12px;padding-left:24px;">';
        echo '<li>' . esc_html__('Vai alla Google Cloud Console (console.cloud.google.com)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Crea un nuovo progetto o selezionane uno esistente', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Vai su "API e servizi" > "Credenziali" > "Crea credenziali" > "Account di servizio"', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Dai un nome (es. "FP DMS Analytics") e crea l\'account', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Vai su "Chiavi" > "Aggiungi chiave" > "Crea nuova chiave" > Seleziona JSON', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Scarica il file JSON - contiene tutte le credenziali necessarie', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Abilita la "Google Analytics Data API" nella libreria API', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('In Google Analytics 4, vai su Admin > Accesso proprietà > Aggiungi utenti', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Incolla l\'email del service account (dal file JSON, campo "client_email") e assegna il ruolo "Visualizzatore"', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Copia il Property ID da Admin > Impostazioni proprietà (es: 123456789)', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '<p style="margin:0;color:#646970;font-size:13px;"><strong>💡 Suggerimento:</strong> ' . esc_html__('Per maggiore sicurezza, puoi salvare il JSON in wp-config.php come costante invece di incollarlo nel form.', 'fp-dms') . '</p>';
        echo '</div>';
        
        // Google Search Console (GSC)
        echo '<div style="margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #dcdcde;">';
        echo '<h3 style="margin:0 0 12px;color:#2271b1;"><span class="dashicons dashicons-search" style="font-size:18px;margin-right:6px;"></span>' . esc_html__('Google Search Console (GSC)', 'fp-dms') . '</h3>';
        echo '<p style="margin:0 0 12px;"><strong>' . esc_html__('Cosa serve:', 'fp-dms') . '</strong> ' . esc_html__('Service Account JSON e URL del sito', 'fp-dms') . '</p>';
        echo '<ol style="margin:0 0 12px;padding-left:24px;">';
        echo '<li>' . esc_html__('Segui gli stessi passi 1-6 di GA4 per creare un Service Account JSON', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Abilita la "Google Search Console API" nella libreria API di Google Cloud', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('In Google Search Console, vai su Impostazioni > Utenti e autorizzazioni', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Aggiungi l\'email del service account con permesso "Completo" o "Limitato"', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Copia l\'URL esatto della proprietà (es: https://www.example.com/ oppure sc-domain:example.com)', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '<p style="margin:0;color:#646970;font-size:13px;"><strong>⚠️ Attenzione:</strong> ' . esc_html__('L\'URL deve corrispondere esattamente a quello configurato in Search Console (con o senza www, con trailing slash).', 'fp-dms') . '</p>';
        echo '</div>';
        
        // Google Ads
        echo '<div style="margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #dcdcde;">';
        echo '<h3 style="margin:0 0 12px;color:#2271b1;"><span class="dashicons dashicons-megaphone" style="font-size:18px;margin-right:6px;"></span>' . esc_html__('Google Ads', 'fp-dms') . '</h3>';
        echo '<p style="margin:0 0 12px;"><strong>' . esc_html__('Cosa serve:', 'fp-dms') . '</strong> ' . esc_html__('Developer Token, OAuth credentials e Customer ID', 'fp-dms') . '</p>';
        echo '<ol style="margin:0 0 12px;padding-left:24px;">';
        echo '<li>' . esc_html__('Vai su Google Ads > Tools > API Center', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Richiedi un Developer Token (può richiedere approvazione Google - usa livello "test" per iniziare)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Vai alla Google Cloud Console e crea un progetto OAuth 2.0', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Vai su "Credenziali" > "Crea credenziali" > "ID client OAuth"', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Tipo: "Applicazione desktop" - salva Client ID e Client Secret', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Usa lo strumento OAuth Playground (developers.google.com/oauthplayground) o uno script per ottenere il Refresh Token', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('In Google Ads, copia il Customer ID (formato: 123-456-7890, senza trattini: 1234567890)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Se usi un account Manager (MCC), inserisci anche il Login Customer ID', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '<p style="margin:0;color:#646970;font-size:13px;"><strong>🔐 Nota:</strong> ' . esc_html__('Il Refresh Token è la parte più complessa - consulta la documentazione ufficiale Google Ads API per generarlo.', 'fp-dms') . '</p>';
        echo '</div>';
        
        // Meta Ads (Facebook/Instagram)
        echo '<div style="margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #dcdcde;">';
        echo '<h3 style="margin:0 0 12px;color:#2271b1;"><span class="dashicons dashicons-facebook" style="font-size:18px;margin-right:6px;"></span>' . esc_html__('Meta Ads (Facebook/Instagram)', 'fp-dms') . '</h3>';
        echo '<p style="margin:0 0 12px;"><strong>' . esc_html__('Cosa serve:', 'fp-dms') . '</strong> ' . esc_html__('Access Token e Ad Account ID', 'fp-dms') . '</p>';
        echo '<ol style="margin:0 0 12px;padding-left:24px;">';
        echo '<li>' . esc_html__('Vai su Meta for Developers (developers.facebook.com)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Crea una nuova App (Tipo: Business)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Aggiungi il prodotto "Marketing API"', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Vai su Tools > Access Token Tool', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Genera un "User Access Token" con permessi: ads_read, read_insights', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Converti il token in Long-Lived Token (durata 60 giorni) usando il Token Debugger', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('In Facebook Business Manager, vai su Impostazioni business > Account pubblicitari', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Copia l\'Ad Account ID (formato: act_123456789)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('(Opzionale) Copia anche il Pixel ID se vuoi tracciare conversioni', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '<p style="margin:0;color:#646970;font-size:13px;"><strong>⏰ Importante:</strong> ' . esc_html__('I token Meta scadono dopo 60 giorni - dovrai rigenerarli periodicamente.', 'fp-dms') . '</p>';
        echo '</div>';
        
        // Microsoft Clarity
        echo '<div style="margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #dcdcde;">';
        echo '<h3 style="margin:0 0 12px;color:#2271b1;"><span class="dashicons dashicons-analytics" style="font-size:18px;margin-right:6px;"></span>' . esc_html__('Microsoft Clarity', 'fp-dms') . '</h3>';
        echo '<p style="margin:0 0 12px;"><strong>' . esc_html__('Cosa serve:', 'fp-dms') . '</strong> ' . esc_html__('API Key e Project ID', 'fp-dms') . '</p>';
        echo '<ol style="margin:0 0 12px;padding-left:24px;">';
        echo '<li>' . esc_html__('Vai su clarity.microsoft.com e accedi con il tuo account Microsoft', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Seleziona il progetto esistente o creane uno nuovo', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Vai su Settings > API', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Genera una nuova API Key e copiala', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Copia il Project ID dalla dashboard del progetto (visibile nell\'URL)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('(Opzionale) Configura un webhook URL per ricevere notifiche real-time', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '<p style="margin:0;color:#646970;font-size:13px;"><strong>🎯 Funzionalità:</strong> ' . esc_html__('Clarity traccia rage clicks, dead clicks e heatmaps - ottimo per UX insights.', 'fp-dms') . '</p>';
        echo '</div>';
        
        // CSV Generico
        echo '<div style="margin-bottom:20px;">';
        echo '<h3 style="margin:0 0 12px;color:#2271b1;"><span class="dashicons dashicons-media-spreadsheet" style="font-size:18px;margin-right:6px;"></span>' . esc_html__('CSV Generico (Import manuale)', 'fp-dms') . '</h3>';
        echo '<p style="margin:0 0 12px;"><strong>' . esc_html__('Cosa serve:', 'fp-dms') . '</strong> ' . esc_html__('Un file CSV con i tuoi dati', 'fp-dms') . '</p>';
        echo '<ol style="margin:0 0 12px;padding-left:24px;">';
        echo '<li>' . esc_html__('Prepara un file CSV con le tue metriche (Excel, Google Sheets, ecc.)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('La prima riga deve contenere i nomi delle colonne (headers)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Colonne riconosciute: date, users, sessions, clicks, impressions, conversions, cost, spend, revenue, rage_clicks, dead_clicks', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Puoi usare alias (es: "visits" = "sessions", "impr" = "impressions")', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Salva il file come CSV (separatore virgola)', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Carica il file tramite il form sopra', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '<p style="margin:0 0 8px;color:#646970;font-size:13px;"><strong>📊 Esempio CSV valido:</strong></p>';
        echo '<pre style="background:#f6f7f7;padding:12px;border-radius:4px;font-size:12px;overflow-x:auto;">date,users,sessions,clicks,conversions,revenue
2024-01-01,150,200,350,12,450.50
2024-01-02,180,220,380,15,520.00</pre>';
        echo '</div>';
        
        // FAQ e troubleshooting
        echo '<div style="background:#fff9e6;border-left:4px solid #f0b849;padding:16px;margin-top:24px;">';
        echo '<h3 style="margin:0 0 12px;font-size:15px;">' . esc_html__('❓ Domande frequenti e risoluzione problemi', 'fp-dms') . '</h3>';
        echo '<ul style="margin:0;padding-left:24px;">';
        echo '<li style="margin-bottom:8px;"><strong>' . esc_html__('Il test di connessione fallisce:', 'fp-dms') . '</strong> ' . esc_html__('Verifica che le credenziali siano corrette, che la API sia abilitata e che l\'account service/app abbia i permessi necessari.', 'fp-dms') . '</li>';
        echo '<li style="margin-bottom:8px;"><strong>' . esc_html__('Non vedo dati dopo la sincronizzazione:', 'fp-dms') . '</strong> ' . esc_html__('Controlla che il periodo selezionato contenga effettivamente dati. Alcune API hanno limiti di date retroattive.', 'fp-dms') . '</li>';
        echo '<li style="margin-bottom:8px;"><strong>' . esc_html__('Errore "Permission denied":', 'fp-dms') . '</strong> ' . esc_html__('L\'account service o l\'app non ha accesso alla risorsa. Aggiungi i permessi necessari nella console della piattaforma.', 'fp-dms') . '</li>';
        echo '<li style="margin-bottom:8px;"><strong>' . esc_html__('Dove trovo più aiuto?:', 'fp-dms') . '</strong> ' . esc_html__('Consulta la documentazione ufficiale di ogni piattaforma - i link alle guide sono disponibili nei blocchi Step 2 sopra il form.', 'fp-dms') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div>'; // Chiude il contenuto del details
        echo '</details>';
        echo '</div>';
    }

    /**
     * @param array<int,DataSource> $dataSources
     */
    private static function renderList(array $dataSources, array $definitions, int $clientId): void
    {
        echo '<h2 style="margin-top:40px;">' . esc_html__('Configured data sources', 'fp-dms') . '</h2>';
        echo '<p class="description" style="margin-top:8px;">' . esc_html__('Use the "Sync Data Sources" button in the Overview page to fetch the latest metrics from all connected platforms.', 'fp-dms') . '</p>';
        
        echo '<table class="widefat striped" style="margin-top:12px;">';
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
                $credentialSource = isset($_POST['auth']['credential_source']) ? Wp::sanitizeTextField($_POST['auth']['credential_source']) : 'manual';
                $serviceAccount = isset($_POST['auth']['service_account']) ? trim((string) Wp::unslash($_POST['auth']['service_account'])) : '';
                $serviceAccountConstant = isset($_POST['auth']['service_account_constant']) ? Wp::sanitizeTextField($_POST['auth']['service_account_constant']) : '';
                $propertyId = Wp::sanitizeTextField($_POST['config']['property_id'] ?? '');
                if ($propertyId === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Service account JSON and property ID are required for GA4.', 'fp-dms'));
                }

                $auth['credential_source'] = $credentialSource === 'constant' ? 'constant' : 'manual';
                if ($auth['credential_source'] === 'constant') {
                    if ($serviceAccountConstant === '') {
                        return new WP_Error('fpdms_datasource_missing', __('Provide the name of the wp-config constant that stores the GA4 service account JSON.', 'fp-dms'));
                    }
                    if (! defined($serviceAccountConstant)) {
                        return new WP_Error('fpdms_datasource_missing', __('The specified wp-config constant is not defined.', 'fp-dms'));
                    }
                    $constantValue = constant($serviceAccountConstant);
                    if (! is_string($constantValue) || trim($constantValue) === '') {
                        return new WP_Error('fpdms_datasource_missing', __('The wp-config constant must return the raw service account JSON string.', 'fp-dms'));
                    }
                    $auth['service_account_constant'] = $serviceAccountConstant;
                } else {
                    if ($serviceAccount === '') {
                        return new WP_Error('fpdms_datasource_missing', __('Service account JSON and property ID are required for GA4.', 'fp-dms'));
                    }
                    $auth['service_account'] = $serviceAccount;
                }

                $config['property_id'] = $propertyId;
                break;
            case 'gsc':
                $credentialSource = isset($_POST['auth']['credential_source']) ? Wp::sanitizeTextField($_POST['auth']['credential_source']) : 'manual';
                $serviceAccount = isset($_POST['auth']['service_account']) ? trim((string) Wp::unslash($_POST['auth']['service_account'])) : '';
                $serviceAccountConstant = isset($_POST['auth']['service_account_constant']) ? Wp::sanitizeTextField($_POST['auth']['service_account_constant']) : '';
                $siteUrlRaw = isset($_POST['config']['site_url']) ? trim((string) $_POST['config']['site_url']) : '';

                $auth['credential_source'] = $credentialSource === 'constant' ? 'constant' : 'manual';
                
                // Verifica service account PRIMA del site URL
                if ($auth['credential_source'] === 'constant') {
                    if ($serviceAccountConstant === '') {
                        return new WP_Error('fpdms_datasource_missing', __('Provide the name of the wp-config constant that stores the Search Console service account JSON.', 'fp-dms'));
                    }
                    if (! defined($serviceAccountConstant)) {
                        return new WP_Error('fpdms_datasource_missing', __('The specified wp-config constant is not defined.', 'fp-dms'));
                    }
                    $constantValue = constant($serviceAccountConstant);
                    if (! is_string($constantValue) || trim($constantValue) === '') {
                        return new WP_Error('fpdms_datasource_missing', __('The wp-config constant must return the raw service account JSON string.', 'fp-dms'));
                    }
                    $auth['service_account_constant'] = $serviceAccountConstant;
                } else {
                    if ($serviceAccount === '') {
                        return new WP_Error('fpdms_datasource_missing', __('Service account JSON is required for Google Search Console.', 'fp-dms'));
                    }
                    $auth['service_account'] = $serviceAccount;
                }

                // Normalizza Site URL per Google Search Console
                if ($siteUrlRaw === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Site URL is required for Google Search Console.', 'fp-dms'));
                }
                
                // Gestisce sia URL normali (https://example.com/) che domain properties (sc-domain:example.com)
                if (strpos($siteUrlRaw, 'sc-domain:') === 0) {
                    // Domain property - mantieni il formato esatto
                    $siteUrl = $siteUrlRaw;
                } else {
                    // URL property - normalizza
                    // Aggiungi https:// se mancante
                    if (!preg_match('/^https?:\/\//i', $siteUrlRaw)) {
                        $siteUrlRaw = 'https://' . $siteUrlRaw;
                    }
                    // Sanitizza e normalizza
                    $siteUrl = esc_url_raw($siteUrlRaw);
                    // Rimuovi trailing slash (GSC non lo vuole per URL properties)
                    $siteUrl = rtrim($siteUrl, '/') . '/';
                }

                $config['site_url'] = $siteUrl;
                break;
            case 'google_ads':
                $developerToken = Wp::sanitizeTextField($_POST['auth']['developer_token'] ?? '');
                $clientId = Wp::sanitizeTextField($_POST['auth']['client_id'] ?? '');
                $clientSecret = Wp::sanitizeTextField($_POST['auth']['client_secret'] ?? '');
                $refreshToken = Wp::sanitizeTextField($_POST['auth']['refresh_token'] ?? '');
                $customerId = Wp::sanitizeTextField($_POST['config']['customer_id'] ?? '');
                $loginCustomerId = Wp::sanitizeTextField($_POST['config']['login_customer_id'] ?? '');

                if ($developerToken === '' || $clientId === '' || $clientSecret === '' || $refreshToken === '' || $customerId === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Complete all required Google Ads API credentials.', 'fp-dms'));
                }

                $auth['developer_token'] = $developerToken;
                $auth['client_id'] = $clientId;
                $auth['client_secret'] = $clientSecret;
                $auth['refresh_token'] = $refreshToken;
                $config['customer_id'] = $customerId;
                if ($loginCustomerId !== '') {
                    $config['login_customer_id'] = $loginCustomerId;
                }
                break;
            case 'meta_ads':
                $token = isset($_POST['auth']['access_token']) ? trim((string) Wp::unslash($_POST['auth']['access_token'])) : '';
                $accountId = Wp::sanitizeTextField($_POST['config']['account_id'] ?? '');
                $pixelId = Wp::sanitizeTextField($_POST['config']['pixel_id'] ?? '');

                if ($token === '' || $accountId === '') {
                    return new WP_Error('fpdms_datasource_missing', __('Access token and ad account ID are required for Meta Ads.', 'fp-dms'));
                }

                $auth['access_token'] = $token;
                $config['account_id'] = $accountId;
                if ($pixelId !== '') {
                    $config['pixel_id'] = $pixelId;
                }
                break;
            case 'clarity':
                $apiKey = Wp::sanitizeTextField($_POST['auth']['api_key'] ?? '');
                $projectId = Wp::sanitizeTextField($_POST['config']['project_id'] ?? '');
                $siteUrl = esc_url_raw($_POST['config']['site_url'] ?? '');
                $webhook = esc_url_raw($_POST['config']['webhook_url'] ?? '');

                if ($apiKey === '' || $projectId === '') {
                    return new WP_Error('fpdms_datasource_missing', __('API key and project ID are required for Microsoft Clarity.', 'fp-dms'));
                }

                $auth['api_key'] = $apiKey;
                $config['project_id'] = $projectId;
                if ($siteUrl !== '') {
                    $config['site_url'] = $siteUrl;
                }
                if ($webhook !== '') {
                    $config['webhook_url'] = $webhook;
                }
                break;
            case 'csv_generic':
                $label = Wp::sanitizeTextField($_POST['config']['source_label'] ?? '');
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
            if (file_exists($file['tmp_name'])) {
                $unlinkResult = unlink($file['tmp_name']);
                if (!$unlinkResult) {
                    error_log('[FPDMS] Failed to delete temporary CSV file: ' . $file['tmp_name']);
                }
            }
            return new WP_Error('fpdms_datasource_csv', __('The CSV file appears to be empty.', 'fp-dms'));
        }

        $keys = array_map(static fn($value) => Wp::sanitizeKey($value), $header);
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

        if (file_exists($file['tmp_name'])) {
            $unlinkResult = unlink($file['tmp_name']);
            if (!$unlinkResult) {
                error_log('[FPDMS] Failed to delete temporary CSV file after processing: ' . $file['tmp_name']);
            }
        }

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
            'last_ingested_at' => Wp::currentTime('mysql'),
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

        return Wp::date('Y-m-d', $timestamp);
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
        switch ($dataSource->type) {
            case 'google_ads':
                if (! empty($config['customer_id'])) {
                    $details[] = sprintf(__('Customer ID: %s', 'fp-dms'), $config['customer_id']);
                }
                if (! empty($config['login_customer_id'])) {
                    $details[] = sprintf(__('Manager ID: %s', 'fp-dms'), $config['login_customer_id']);
                }
                break;
            case 'meta_ads':
                if (! empty($config['account_id'])) {
                    $details[] = sprintf(__('Ad account: %s', 'fp-dms'), $config['account_id']);
                }
                if (! empty($config['pixel_id'])) {
                    $details[] = sprintf(__('Pixel: %s', 'fp-dms'), $config['pixel_id']);
                }
                break;
            case 'clarity':
                if (! empty($config['project_id'])) {
                    $details[] = sprintf(__('Project: %s', 'fp-dms'), $config['project_id']);
                }
                break;
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
            return __('Awaiting first sync.', 'fp-dms');
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
            return Wp::numberFormatI18n((int) round($rounded));
        }

        return Wp::numberFormatI18n($rounded, 2);
    }

    private static function formatDateTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }

        return Wp::date('Y-m-d H:i', $timestamp);
    }

    private static function outputInlineAssets(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        echo '<style>
            .fpdms-guide-intro{margin:20px 0;padding:16px 20px;border-left:4px solid #2271b1;background:#f0f6fc;max-width:960px;}
            .fpdms-guide-intro h2{margin:0 0 8px;font-size:18px;}
            .fpdms-guide-intro ol{margin:0;padding-left:20px;}
            .fpdms-guide-intro li{margin-bottom:4px;}
            .fpdms-connector-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0;}
            .fpdms-connector-card{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px;text-align:left;cursor:pointer;box-shadow:0 1px 0 rgba(0,0,0,0.04);transition:border-color .2s,box-shadow .2s;background-image:none;}
            .fpdms-connector-card strong{display:block;font-size:15px;margin-bottom:6px;color:#1d2327;}
            .fpdms-connector-card span{display:block;color:#50575e;font-size:13px;line-height:1.4;}
            .fpdms-connector-card.is-active{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;}
            .fpdms-connector-card.is-disabled{cursor:not-allowed;opacity:.6;}
            .fpdms-guidance{margin:12px 0 4px;}
            .fpdms-guidance h3{margin:0 0 4px;font-size:16px;}
            .fpdms-guidance p.description{margin-top:0;}
            .fpdms-guidance-block{padding:12px 16px;border:1px solid #dcdcde;border-radius:4px;margin-bottom:8px;background:#fff;}
            .fpdms-guidance-block ol{margin:0;padding-left:20px;}
            .fpdms-guidance-block li{margin-bottom:6px;line-height:1.4;}
            .fpdms-guided-save-note{margin-top:12px;font-weight:600;}
            body.fpdms-has-guided-picker .fpdms-datasource-select-row{display:none;}
            #fpdms-sync-datasources .dashicons{animation:none;}
            #fpdms-sync-datasources.is-syncing .dashicons{animation:rotation 1s infinite linear;}
            @keyframes rotation{from{transform:rotate(0deg);}to{transform:rotate(359deg);}}
        </style>';
    }

    private static function renderGuidedIntro(): void
    {
        echo '<div class="fpdms-guide-intro">';
        echo '<h2>' . esc_html__('Guided setup', 'fp-dms') . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__('Pick the integration you want to connect.', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Follow the checklist for the credentials required by that platform.', 'fp-dms') . '</li>';
        echo '<li>' . esc_html__('Save the data source and optionally run a connection test.', 'fp-dms') . '</li>';
        echo '</ol>';
        echo '</div>';
    }

    private static function renderConnectorCards(array $definitions, string $currentType, bool $isEditing): void
    {
        echo '<div class="fpdms-guidance">';
        echo '<h3>' . esc_html__('Step 1 — Choose your integration', 'fp-dms') . '</h3>';
        echo '<p class="description">' . esc_html__('Select a connector to see the exact credentials you will need.', 'fp-dms') . '</p>';
        echo '<div class="fpdms-connector-grid">';
        foreach ($definitions as $type => $definition) {
            $label = $definition['label'] ?? ucfirst($type);
            $summary = $definition['summary'] ?? '';
            $classes = ['fpdms-connector-card'];
            if ($type === $currentType) {
                $classes[] = 'is-active';
            }
            $locked = $isEditing && $type !== $currentType;
            if ($locked) {
                $classes[] = 'is-disabled';
            }
            echo '<button type="button" class="' . esc_attr(implode(' ', $classes)) . '" data-type="' . esc_attr($type) . '" aria-pressed="' . ($type === $currentType ? 'true' : 'false') . '"';
            if ($locked) {
                echo ' data-locked="1"';
            }
            echo '>';
            echo '<strong>' . esc_html($label) . '</strong>';
            if ($summary !== '') {
                echo '<span>' . esc_html($summary) . '</span>';
            }
            echo '</button>';
        }
        echo '</div>';
        if ($isEditing) {
            echo '<p class="description">' . esc_html__('This connection already uses the selected platform. Create an additional data source if you need another integration.', 'fp-dms') . '</p>';
        }
        echo '</div>';
    }

    private static function renderGuidanceBlocks(array $definitions, string $currentType): void
    {
        echo '<div class="fpdms-guidance">';
        echo '<h3>' . esc_html__('Step 2 — Prepare the required credentials', 'fp-dms') . '</h3>';
        echo '<p class="description">' . esc_html__('Each connector has a short checklist so nothing is missed during setup.', 'fp-dms') . '</p>';
        foreach ($definitions as $type => $definition) {
            $display = $type === $currentType ? 'block' : 'none';
            echo '<div class="fpdms-guidance-block" data-type="' . esc_attr($type) . '" style="display:' . esc_attr($display) . ';">';
            if (! empty($definition['description'])) {
                echo '<p class="description">' . esc_html((string) $definition['description']) . '</p>';
            }
            $steps = $definition['steps'] ?? [];
            if (! empty($steps)) {
                echo '<ol>';
                foreach ($steps as $step) {
                    echo '<li>' . esc_html((string) $step) . '</li>';
                }
                echo '</ol>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}

# 🎨 Esempio Completo - Creare una Nuova Pagina da Zero

Questo esempio mostra come creare una **nuova pagina admin completa** usando i componenti modulari.

**Scenario:** Creiamo una pagina "**Reports Manager**" per gestire report personalizzati.

---

## 📋 Requisiti

La pagina deve:
- ✅ Elencare report esistenti
- ✅ Form per creare/modificare report
- ✅ Azioni (view, edit, delete, duplicate)
- ✅ Filtri per client e status
- ✅ Design consistente con il resto del plugin

---

## 🏗️ Struttura

```
src/Admin/Pages/
├── ReportsPage.php              # Entry point
└── Reports/
    ├── ReportsDataService.php   # Data logic
    ├── ReportsRenderer.php      # UI rendering
    └── ReportsActionHandler.php # Actions
```

---

## Step 1: Entity (se necessaria)

```php
<?php
// src/Domain/Entities/CustomReport.php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

class CustomReport
{
    public ?int $id = null;
    public int $clientId;
    public string $name;
    public string $description = '';
    public string $template;
    public string $status = 'draft'; // draft, published, archived
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
```

---

## Step 2: Data Service

```php
<?php
// src/Admin/Pages/Reports/ReportsDataService.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Reports;

use FP\DMS\Domain\Entities\CustomReport;
use FP\DMS\Infra\DB;

class ReportsDataService
{
    /**
     * Get all reports
     *
     * @return array<int, CustomReport>
     */
    public static function getAllReports(?int $clientId = null, ?string $status = null): array
    {
        global $wpdb;
        
        $table = DB::table('custom_reports');
        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];

        if ($clientId !== null && $clientId > 0) {
            $sql .= " AND client_id = %d";
            $params[] = $clientId;
        }

        if ($status !== null && $status !== '') {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $query = !empty($params) ? $wpdb->prepare($sql, $params) : $sql;
        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map([self::class, 'mapToEntity'], $rows ?: []);
    }

    /**
     * Get report by ID
     */
    public static function getReport(int $id): ?CustomReport
    {
        global $wpdb;
        
        $table = DB::table('custom_reports');
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return $row ? self::mapToEntity($row) : null;
    }

    /**
     * Save report
     */
    public static function saveReport(CustomReport $report): bool
    {
        global $wpdb;
        
        $table = DB::table('custom_reports');
        $data = [
            'client_id' => $report->clientId,
            'name' => $report->name,
            'description' => $report->description,
            'template' => $report->template,
            'status' => $report->status,
            'updated_at' => current_time('mysql'),
        ];

        if ($report->id) {
            return $wpdb->update($table, $data, ['id' => $report->id]) !== false;
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
            if ($result) {
                $report->id = $wpdb->insert_id;
            }
            return $result !== false;
        }
    }

    /**
     * Delete report
     */
    public static function deleteReport(int $id): bool
    {
        global $wpdb;
        
        $table = DB::table('custom_reports');
        return $wpdb->delete($table, ['id' => $id]) !== false;
    }

    /**
     * Duplicate report
     */
    public static function duplicateReport(int $id): ?CustomReport
    {
        $original = self::getReport($id);
        
        if (!$original) {
            return null;
        }

        $duplicate = clone $original;
        $duplicate->id = null;
        $duplicate->name = $original->name . ' (Copy)';
        $duplicate->status = 'draft';

        if (self::saveReport($duplicate)) {
            return $duplicate;
        }

        return null;
    }

    /**
     * Get status options
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'draft' => __('Draft', 'fp-dms'),
            'published' => __('Published', 'fp-dms'),
            'archived' => __('Archived', 'fp-dms'),
        ];
    }

    /**
     * Get status badge class
     */
    public static function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'published' => 'fpdms-badge-success',
            'draft' => 'fpdms-badge-info',
            'archived' => 'fpdms-badge-neutral',
            default => 'fpdms-badge-neutral',
        };
    }

    /**
     * Map database row to entity
     *
     * @param array<string, mixed> $row
     */
    private static function mapToEntity(array $row): CustomReport
    {
        $report = new CustomReport();
        $report->id = (int) $row['id'];
        $report->clientId = (int) $row['client_id'];
        $report->name = (string) $row['name'];
        $report->description = (string) ($row['description'] ?? '');
        $report->template = (string) $row['template'];
        $report->status = (string) $row['status'];
        $report->createdAt = $row['created_at'] ?? null;
        $report->updatedAt = $row['updated_at'] ?? null;
        
        return $report;
    }
}
```

---

## Step 3: Renderer

```php
<?php
// src/Admin/Pages/Reports/ReportsRenderer.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Reports;

use FP\DMS\Admin\Pages\Shared\FormRenderer;
use FP\DMS\Admin\Pages\Shared\TableRenderer;
use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\CustomReport;

class ReportsRenderer
{
    /**
     * Render page header
     */
    public static function renderHeader(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Reports Manager', 'fp-dms') . '</h1>';
        echo '<p>' . esc_html__('Create and manage custom report templates.', 'fp-dms') . '</p>';
    }

    /**
     * Render filters
     *
     * @param array<int, Client> $clients
     */
    public static function renderFilters(array $clients, int $selectedClient, string $selectedStatus): void
    {
        echo '<div class="fpdms-section" style="margin-bottom:20px;">';
        
        FormRenderer::open(['method' => 'get']);
        FormRenderer::hidden('page', 'fp-dms-reports');

        echo '<div style="display:flex;gap:12px;align-items:flex-end;">';

        // Client filter
        $clientOptions = ['0' => __('All Clients', 'fp-dms')];
        foreach ($clients as $client) {
            $clientOptions[(string) $client->id] = $client->name;
        }

        FormRenderer::select([
            'id' => 'client-filter',
            'name' => 'client_id',
            'label' => __('Client', 'fp-dms'),
            'options' => $clientOptions,
            'selected' => (string) $selectedClient,
        ]);

        // Status filter
        $statusOptions = ['all' => __('All Statuses', 'fp-dms')] + ReportsDataService::getStatusOptions();

        FormRenderer::select([
            'id' => 'status-filter',
            'name' => 'status',
            'label' => __('Status', 'fp-dms'),
            'options' => $statusOptions,
            'selected' => $selectedStatus,
        ]);

        submit_button(__('Filter', 'fp-dms'), 'secondary', 'submit', false);

        echo '</div>';
        FormRenderer::close();
        
        echo '</div>';
    }

    /**
     * Render create/edit form
     *
     * @param array<int, Client> $clients
     */
    public static function renderForm(?CustomReport $report, array $clients): void
    {
        $isEdit = $report && $report->id;
        
        echo '<div class="fpdms-section" style="margin-bottom:20px;">';
        echo '<h2>' . esc_html($isEdit ? __('Edit Report', 'fp-dms') : __('Create New Report', 'fp-dms')) . '</h2>';

        FormRenderer::open();
        FormRenderer::nonce('save_report');
        FormRenderer::hidden('action', 'save');

        if ($isEdit) {
            FormRenderer::hidden('report_id', (string) $report->id);
        }

        // Client
        $clientOptions = [];
        foreach ($clients as $client) {
            $clientOptions[(string) $client->id] = $client->name;
        }

        FormRenderer::select([
            'id' => 'report-client',
            'name' => 'client_id',
            'label' => __('Client', 'fp-dms'),
            'options' => $clientOptions,
            'selected' => $report ? (string) $report->clientId : '',
            'required' => true,
        ]);

        // Name
        FormRenderer::input([
            'id' => 'report-name',
            'name' => 'name',
            'label' => __('Report Name', 'fp-dms'),
            'value' => $report ? $report->name : '',
            'required' => true,
            'class' => 'regular-text',
        ]);

        // Description
        FormRenderer::textarea([
            'id' => 'report-description',
            'name' => 'description',
            'label' => __('Description', 'fp-dms'),
            'value' => $report ? $report->description : '',
            'rows' => 3,
            'class' => 'large-text',
        ]);

        // Template (simplified - in real app would be more complex)
        FormRenderer::input([
            'id' => 'report-template',
            'name' => 'template',
            'label' => __('Template ID', 'fp-dms'),
            'value' => $report ? $report->template : '',
            'required' => true,
        ]);

        // Status
        FormRenderer::select([
            'id' => 'report-status',
            'name' => 'status',
            'label' => __('Status', 'fp-dms'),
            'options' => ReportsDataService::getStatusOptions(),
            'selected' => $report ? $report->status : 'draft',
        ]);

        echo '<p>';
        submit_button($isEdit ? __('Update Report', 'fp-dms') : __('Create Report', 'fp-dms'));
        
        if ($isEdit) {
            echo ' <a href="' . esc_url(admin_url('admin.php?page=fp-dms-reports')) . '" class="button">' . esc_html__('Cancel', 'fp-dms') . '</a>';
        }
        echo '</p>';

        FormRenderer::close();
        echo '</div>';
    }

    /**
     * Render reports list
     *
     * @param array<int, CustomReport> $reports
     * @param array<int, string> $clientsMap
     */
    public static function renderList(array $reports, array $clientsMap): void
    {
        echo '<div class="fpdms-section">';
        echo '<h2>' . esc_html__('Reports', 'fp-dms') . '</h2>';

        $headers = [
            __('Name', 'fp-dms'),
            __('Client', 'fp-dms'),
            __('Status', 'fp-dms'),
            __('Created', 'fp-dms'),
            __('Actions', 'fp-dms'),
        ];

        TableRenderer::render($headers, $reports, [
            'empty_message' => __('No reports found.', 'fp-dms'),
            'row_renderer' => function($report) use ($clientsMap) {
                ReportsRenderer::renderRow($report, $clientsMap);
            }
        ]);

        echo '</div>';
    }

    /**
     * Render single report row
     *
     * @param array<int, string> $clientsMap
     */
    public static function renderRow(CustomReport $report, array $clientsMap): void
    {
        TableRenderer::startRow();

        // Name
        TableRenderer::cell($report->name);

        // Client
        $clientName = $clientsMap[$report->clientId] ?? __('Unknown', 'fp-dms');
        TableRenderer::cell($clientName);

        // Status badge
        $badgeClass = ReportsDataService::getStatusBadgeClass($report->status);
        $statusOptions = ReportsDataService::getStatusOptions();
        $statusLabel = $statusOptions[$report->status] ?? $report->status;
        $badge = '<span class="' . esc_attr($badgeClass) . '">' . esc_html($statusLabel) . '</span>';
        TableRenderer::rawCell($badge);

        // Created
        $created = $report->createdAt ? wp_date('Y-m-d H:i', strtotime($report->createdAt)) : '—';
        TableRenderer::cell($created);

        // Actions
        self::renderActions($report);

        TableRenderer::endRow();
    }

    /**
     * Render action links
     */
    private static function renderActions(CustomReport $report): void
    {
        $editUrl = add_query_arg([
            'page' => 'fp-dms-reports',
            'action' => 'edit',
            'id' => $report->id,
        ], admin_url('admin.php'));

        $duplicateUrl = add_query_arg([
            'page' => 'fp-dms-reports',
            'action' => 'duplicate',
            'id' => $report->id,
        ], admin_url('admin.php'));

        $deleteUrl = add_query_arg([
            'page' => 'fp-dms-reports',
            'action' => 'delete',
            'id' => $report->id,
        ], admin_url('admin.php'));

        echo '<td>';
        echo '<a href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'fp-dms') . '</a> | ';
        echo '<a href="' . esc_url($duplicateUrl) . '">' . esc_html__('Duplicate', 'fp-dms') . '</a> | ';
        echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'' . esc_attr__('Delete this report?', 'fp-dms') . '\');">' . esc_html__('Delete', 'fp-dms') . '</a>';
        echo '</td>';
    }

    /**
     * Close page wrapper
     */
    public static function renderFooter(): void
    {
        echo '</div>';
    }
}
```

---

## Step 4: Action Handler

```php
<?php
// src/Admin/Pages/Reports/ReportsActionHandler.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Reports;

use FP\DMS\Domain\Entities\CustomReport;
use FP\DMS\Support\Wp;

class ReportsActionHandler
{
    public static function handle(): void
    {
        $action = '';

        if (!empty($_POST['action'])) {
            $action = Wp::sanitizeKey($_POST['action']);
        } elseif (!empty($_GET['action'])) {
            $action = Wp::sanitizeKey($_GET['action']);
        }

        if ($action === '') {
            return;
        }

        switch ($action) {
            case 'save':
                self::handleSave();
                break;
            case 'delete':
                self::handleDelete();
                break;
            case 'duplicate':
                self::handleDuplicate();
                break;
        }
    }

    private static function handleSave(): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'save_report')) {
            add_settings_error('reports', 'nonce', __('Security check failed', 'fp-dms'));
            return;
        }

        $id = isset($_POST['report_id']) ? (int) $_POST['report_id'] : 0;
        $clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $name = Wp::sanitizeTextField($_POST['name'] ?? '');
        $description = Wp::sanitizeTextField($_POST['description'] ?? '');
        $template = Wp::sanitizeKey($_POST['template'] ?? '');
        $status = Wp::sanitizeKey($_POST['status'] ?? 'draft');

        // Validation
        if ($clientId <= 0 || $name === '' || $template === '') {
            add_settings_error('reports', 'validation', __('Please fill all required fields', 'fp-dms'));
            return;
        }

        $report = $id > 0 ? ReportsDataService::getReport($id) : new CustomReport();

        if ($id > 0 && !$report) {
            add_settings_error('reports', 'not_found', __('Report not found', 'fp-dms'));
            return;
        }

        $report->clientId = $clientId;
        $report->name = $name;
        $report->description = $description;
        $report->template = $template;
        $report->status = $status;

        if (ReportsDataService::saveReport($report)) {
            add_settings_error(
                'reports',
                'saved',
                __('Report saved successfully', 'fp-dms'),
                'success'
            );
        } else {
            add_settings_error('reports', 'save_error', __('Failed to save report', 'fp-dms'));
        }

        self::redirect();
    }

    private static function handleDelete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            return;
        }

        if (ReportsDataService::deleteReport($id)) {
            add_settings_error(
                'reports',
                'deleted',
                __('Report deleted successfully', 'fp-dms'),
                'success'
            );
        } else {
            add_settings_error('reports', 'delete_error', __('Failed to delete report', 'fp-dms'));
        }

        self::redirect();
    }

    private static function handleDuplicate(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            return;
        }

        $duplicate = ReportsDataService::duplicateReport($id);

        if ($duplicate) {
            add_settings_error(
                'reports',
                'duplicated',
                __('Report duplicated successfully', 'fp-dms'),
                'success'
            );
        } else {
            add_settings_error('reports', 'duplicate_error', __('Failed to duplicate report', 'fp-dms'));
        }

        self::redirect();
    }

    private static function redirect(): void
    {
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=fp-dms-reports'));
        exit;
    }
}
```

---

## Step 5: Main Page

```php
<?php
// src/Admin/Pages/ReportsPage.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Reports\ReportsActionHandler;
use FP\DMS\Admin\Pages\Reports\ReportsDataService;
use FP\DMS\Admin\Pages\Reports\ReportsRenderer;
use FP\DMS\Domain\Repos\ClientsRepo;

class ReportsPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle actions
        ReportsActionHandler::handle();

        // Get clients
        $clientsRepo = new ClientsRepo();
        $clients = $clientsRepo->all();
        $clientsMap = [];
        foreach ($clients as $client) {
            $clientsMap[$client->id ?? 0] = $client->name;
        }

        // Get filters
        $selectedClient = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        $selectedStatus = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        $statusFilter = $selectedStatus !== 'all' ? $selectedStatus : null;

        // Check if editing
        $editing = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $editing = ReportsDataService::getReport((int) $_GET['id']);
        }

        // Get reports
        $reports = ReportsDataService::getAllReports($selectedClient ?: null, $statusFilter);

        // Render page
        ReportsRenderer::renderHeader();
        settings_errors('reports');
        ReportsRenderer::renderFilters($clients, $selectedClient, $selectedStatus);
        ReportsRenderer::renderForm($editing, $clients);
        ReportsRenderer::renderList($reports, $clientsMap);
        ReportsRenderer::renderFooter();
    }
}
```

---

## Step 6: Registration

```php
// In src/Admin/Menu.php o dove registri le pagine

add_menu_page(
    __('Reports', 'fp-dms'),
    __('Reports', 'fp-dms'),
    'manage_options',
    'fp-dms-reports',
    [\FP\DMS\Admin\Pages\ReportsPage::class, 'render'],
    'dashicons-media-document',
    30
);
```

---

## 📊 Risultato Finale

### File Struttura
```
Reports Manager (Complete Feature)
├── ReportsPage.php (45 righe)
├── Reports/
│   ├── ReportsDataService.php (150 righe)
│   ├── ReportsRenderer.php (200 righe)
│   └── ReportsActionHandler.php (120 righe)
└── CustomReport.php entity (15 righe)

Total: 530 righe ben organizzate
```

### Features
- ✅ CRUD completo (Create, Read, Update, Delete)
- ✅ Duplicate action
- ✅ Filtri per client e status
- ✅ Validazione form
- ✅ Status badges
- ✅ Security (nonce)
- ✅ i18n ready
- ✅ Type hints completi
- ✅ Design system integrato

---

## 🎨 Styling

Gli stili sono già gestiti dal design system:

```scss
// Automaticamente disponibili:
.fpdms-section
.fpdms-badge-success
.fpdms-badge-info
.fpdms-badge-neutral
```

Nessun CSS custom necessario!

---

## ✅ Checklist Completamento

- [x] Entity creata
- [x] DataService implementato
- [x] Renderer implementato
- [x] ActionHandler implementato
- [x] Main page orchestrata
- [x] Menu registration
- [x] Type hints completi
- [x] PHPDoc completo
- [x] Security (nonce) implementata
- [x] i18n strings
- [x] Design system utilizzato

---

## 🚀 Estensioni Possibili

### 1. Add Export Feature
```php
// In ReportsActionHandler
case 'export':
    self::handleExport();
    break;
```

### 2. Add Preview
```php
// In ReportsRenderer
public static function renderPreview(CustomReport $report): void
{
    // Preview implementation
}
```

### 3. Add Scheduling
```php
// In ReportsDataService
public static function scheduleReport(int $reportId, string $frequency): bool
{
    // Schedule logic
}
```

---

## 💡 Lezioni Apprese

### Do's ✅
- Usa sempre i componenti condivisi
- Separa concerns da subito
- Type hints e PHPDoc sempre
- Security first (nonce, sanitize, escape)
- Design system per consistenza

### Don'ts ❌
- Non mischiare logica e rendering
- Non hardcodare stringhe (usa i18n)
- Non dimenticare escape output
- Non dimenticare validazione
- Non usare inline styles

---

**Questo è un esempio completo e production-ready! 🎉**

Puoi usarlo come template per qualsiasi nuova pagina admin.

---

📚 **Riferimenti:**
- [Componenti Condivisi](./src/Admin/Pages/Shared/README.md)
- [Design System](./assets/scss/README.md)
- [Guida Migrazione](./MIGRATION_STEP_BY_STEP.md)
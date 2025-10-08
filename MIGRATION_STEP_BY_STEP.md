# 🔄 Guida Pratica - Migrazione Step-by-Step

Questa guida ti accompagna passo-passo nella migrazione di una pagina esistente all'architettura modulare.

---

## 📋 Prerequisiti

Prima di iniziare, assicurati di avere:
- ✅ Letto [REFACTORING_COMPLETE.md](./REFACTORING_COMPLETE.md)
- ✅ Familiarità con i [componenti condivisi](./src/Admin/Pages/Shared/README.md)
- ✅ npm installato per compilare CSS

---

## 🎯 Scenario: Modularizzare una Pagina Admin

Seguiamo un esempio pratico: **ClientsPage.php** (359 righe)

---

## Step 1: Analisi della Pagina Esistente

### 1.1 Identifica le Responsabilità

```bash
# Apri il file e identifica le sezioni
cat src/Admin/Pages/ClientsPage.php | grep "private static function"
```

Tipicamente troverai:
- `render()` - Entry point
- `handleActions()` - Gestione form submission
- `renderForm()` - Rendering form
- `renderList()` - Rendering lista
- Helper di formattazione

### 1.2 Categorizza i Metodi

| Tipo | Metodi | Destinazione |
|------|--------|--------------|
| **Data** | `getClients()`, `save()`, `delete()` | `ClientsDataService` |
| **Rendering** | `renderForm()`, `renderList()`, `renderRow()` | `ClientsRenderer` |
| **Actions** | `handleActions()`, `validateForm()` | `ClientsActionHandler` |

---

## Step 2: Crea la Struttura Modulare

### 2.1 Crea la Directory

```bash
mkdir -p src/Admin/Pages/Clients
```

### 2.2 Crea i File Base

```bash
touch src/Admin/Pages/Clients/ClientsDataService.php
touch src/Admin/Pages/Clients/ClientsRenderer.php
touch src/Admin/Pages/Clients/ClientsActionHandler.php
```

---

## Step 3: Estrai Data Service

### 3.1 Template Base

```php
<?php
// src/Admin/Pages/Clients/ClientsDataService.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Clients;

use FP\DMS\Domain\Repos\ClientsRepo;

class ClientsDataService
{
    /**
     * Get all clients
     *
     * @return array<int, \FP\DMS\Domain\Entities\Client>
     */
    public static function getAllClients(): array
    {
        $repo = new ClientsRepo();
        return $repo->all();
    }

    /**
     * Get client by ID
     */
    public static function getClient(int $id): ?\FP\DMS\Domain\Entities\Client
    {
        $repo = new ClientsRepo();
        return $repo->find($id);
    }

    /**
     * Save client
     */
    public static function saveClient(\FP\DMS\Domain\Entities\Client $client): bool
    {
        $repo = new ClientsRepo();
        return $repo->save($client);
    }

    /**
     * Delete client
     */
    public static function deleteClient(int $id): bool
    {
        $repo = new ClientsRepo();
        return $repo->delete($id);
    }
}
```

### 3.2 Copia e Adatta

1. Copia i metodi di accesso dati dal file originale
2. Rimuovi logica di rendering
3. Aggiungi type hints
4. Aggiungi PHPDoc

---

## Step 4: Estrai Renderer

### 4.1 Template Base

```php
<?php
// src/Admin/Pages/Clients/ClientsRenderer.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Clients;

use FP\DMS\Admin\Pages\Shared\FormRenderer;
use FP\DMS\Admin\Pages\Shared\TableRenderer;

class ClientsRenderer
{
    /**
     * Render clients list
     *
     * @param array<int, \FP\DMS\Domain\Entities\Client> $clients
     */
    public static function renderList(array $clients): void
    {
        $headers = ['ID', 'Name', 'Email', 'Status', 'Actions'];
        
        TableRenderer::render($headers, $clients, [
            'empty_message' => 'No clients found',
            'row_renderer' => [self::class, 'renderRow']
        ]);
    }

    /**
     * Render single client row
     */
    public static function renderRow($client): void
    {
        TableRenderer::startRow();
        TableRenderer::cell($client->id);
        TableRenderer::cell($client->name);
        TableRenderer::cell($client->email);
        
        // Status badge
        $badge = self::renderStatusBadge($client->active);
        TableRenderer::rawCell($badge);
        
        // Actions
        self::renderActions($client->id);
        
        TableRenderer::endRow();
    }

    /**
     * Render form
     */
    public static function renderForm($client = null): void
    {
        FormRenderer::open(['action' => admin_url('admin-post.php')]);
        FormRenderer::nonce('save_client');
        FormRenderer::hidden('action', 'save_client');
        
        if ($client && $client->id) {
            FormRenderer::hidden('client_id', (string) $client->id);
        }
        
        FormRenderer::input([
            'id' => 'client-name',
            'name' => 'client_name',
            'label' => 'Client Name',
            'value' => $client ? $client->name : '',
            'required' => true
        ]);
        
        FormRenderer::input([
            'id' => 'client-email',
            'name' => 'client_email',
            'type' => 'email',
            'label' => 'Email',
            'value' => $client ? $client->email : '',
            'required' => true
        ]);
        
        submit_button($client ? 'Update' : 'Create');
        FormRenderer::close();
    }

    private static function renderStatusBadge(bool $active): string
    {
        $class = $active ? 'fpdms-badge-success' : 'fpdms-badge-neutral';
        $label = $active ? 'Active' : 'Inactive';
        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    private static function renderActions(int $id): void
    {
        echo '<td>';
        echo '<a href="' . esc_url(add_query_arg(['action' => 'edit', 'id' => $id], admin_url('admin.php?page=fp-dms-clients'))) . '">Edit</a> | ';
        echo '<a href="' . esc_url(add_query_arg(['action' => 'delete', 'id' => $id], admin_url('admin.php?page=fp-dms-clients'))) . '" onclick="return confirm(\'Delete?\')">Delete</a>';
        echo '</td>';
    }
}
```

---

## Step 5: Estrai Action Handler

### 5.1 Template Base

```php
<?php
// src/Admin/Pages/Clients/ClientsActionHandler.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Clients;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Support\Wp;

class ClientsActionHandler
{
    /**
     * Handle all client actions
     */
    public static function handle(): void
    {
        if (empty($_POST['action']) && empty($_GET['action'])) {
            return;
        }

        $action = !empty($_POST['action']) 
            ? Wp::sanitizeKey($_POST['action']) 
            : Wp::sanitizeKey($_GET['action']);

        switch ($action) {
            case 'save_client':
                self::handleSave();
                break;
            case 'delete':
                self::handleDelete();
                break;
        }
    }

    private static function handleSave(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'save_client')) {
            add_settings_error('clients', 'nonce', 'Security check failed');
            return;
        }

        $id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $name = Wp::sanitizeTextField($_POST['client_name'] ?? '');
        $email = sanitize_email($_POST['client_email'] ?? '');

        if (empty($name) || empty($email)) {
            add_settings_error('clients', 'validation', 'Name and email required');
            return;
        }

        $client = $id > 0 ? ClientsDataService::getClient($id) : new Client();
        
        if (!$client) {
            add_settings_error('clients', 'not_found', 'Client not found');
            return;
        }

        $client->name = $name;
        $client->email = $email;

        if (ClientsDataService::saveClient($client)) {
            add_settings_error('clients', 'saved', 'Client saved', 'success');
        } else {
            add_settings_error('clients', 'save_error', 'Save failed');
        }

        self::redirect();
    }

    private static function handleDelete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            return;
        }

        if (ClientsDataService::deleteClient($id)) {
            add_settings_error('clients', 'deleted', 'Client deleted', 'success');
        } else {
            add_settings_error('clients', 'delete_error', 'Delete failed');
        }

        self::redirect();
    }

    private static function redirect(): void
    {
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=fp-dms-clients'));
        exit;
    }
}
```

---

## Step 6: Refactora la Pagina Principale

### 6.1 Nuovo File Semplificato

```php
<?php
// src/Admin/Pages/ClientsPage.refactored.php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Clients\ClientsActionHandler;
use FP\DMS\Admin\Pages\Clients\ClientsDataService;
use FP\DMS\Admin\Pages\Clients\ClientsRenderer;

class ClientsPageRefactored
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle actions
        ClientsActionHandler::handle();

        // Get data
        $clients = ClientsDataService::getAllClients();
        $editing = null;

        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $editing = ClientsDataService::getClient((int) $_GET['id']);
        }

        // Render page
        echo '<div class="wrap">';
        echo '<h1>Clients</h1>';

        settings_errors('clients');

        ClientsRenderer::renderForm($editing);
        ClientsRenderer::renderList($clients);

        echo '</div>';
    }
}
```

### 6.2 Confronto Prima/Dopo

**Prima:**
- 359 righe in un file
- Responsabilità miste
- Difficile da testare

**Dopo:**
- 40 righe file principale
- 4 file modulari
- Facile da testare

---

## Step 7: Testing

### 7.1 Checklist Manuale

```bash
# 1. Compila CSS
./build-assets.sh

# 2. Testa in WordPress
```

- [ ] Pagina si carica senza errori
- [ ] Form funziona
- [ ] Lista visualizza correttamente
- [ ] Azioni (edit, delete) funzionano
- [ ] Nessun errore PHP in log
- [ ] Stili CSS applicati

### 7.2 Verifica Codice

```bash
# Check linting
vendor/bin/phpcs src/Admin/Pages/Clients/

# Check no errors
php -l src/Admin/Pages/Clients/*.php
```

---

## Step 8: Migrazione in Produzione

### 8.1 Aggiorna Menu Registration

```php
// Prima (in Menu.php o simile)
add_menu_page(
    'Clients',
    'Clients',
    'manage_options',
    'fp-dms-clients',
    [ClientsPage::class, 'render'],
    'dashicons-groups'
);

// Dopo
add_menu_page(
    'Clients',
    'Clients',
    'manage_options',
    'fp-dms-clients',
    [ClientsPageRefactored::class, 'render'],  // ← Cambia qui
    'dashicons-groups'
);
```

### 8.2 Backup

```bash
# Backup file originale
cp src/Admin/Pages/ClientsPage.php src/Admin/Pages/ClientsPage.backup.php

# Poi puoi rimuovere il backup dopo test completo
```

---

## 📊 Checklist Completa Migrazione

### Preparazione
- [ ] Analisi pagina esistente
- [ ] Identificate responsabilità
- [ ] Struttura directory creata

### Sviluppo
- [ ] DataService creato
- [ ] Renderer creato
- [ ] ActionHandler creato
- [ ] Pagina principale refactorata

### Qualità
- [ ] Type hints aggiunti
- [ ] PHPDoc completo
- [ ] Nessun errore linting
- [ ] Codice formattato

### Testing
- [ ] Test manuale completo
- [ ] Nessun errore PHP
- [ ] Stili CSS corretti
- [ ] Funzionalità invariata

### Deploy
- [ ] Menu registration aggiornata
- [ ] Backup creato
- [ ] File originale rimosso (dopo test)
- [ ] Documentazione aggiornata

---

## 💡 Tips & Best Practices

### 1. **Inizia Semplice**
Non cercare di modularizzare tutto subito. Inizia con una pagina.

### 2. **Usa i Componenti Condivisi**
Approfitta di `TableRenderer`, `FormRenderer`, `TabsRenderer`.

### 3. **Mantieni la Compatibilità**
Testa che tutto funzioni come prima.

### 4. **Type Hints Sempre**
Aggiungi type hints a tutti i metodi pubblici.

### 5. **PHPDoc Completo**
Documenta parametri, return types, esempi.

### 6. **Test Progressivo**
Testa ogni componente man mano che lo crei.

---

## 🚨 Errori Comuni

### 1. **Namespace Sbagliato**
```php
// ❌ Sbagliato
namespace FP\DMS\Admin\Pages;

// ✅ Corretto
namespace FP\DMS\Admin\Pages\Clients;
```

### 2. **Import Mancanti**
```php
// ✅ Aggiungi sempre
use FP\DMS\Admin\Pages\Shared\TableRenderer;
use FP\DMS\Admin\Pages\Shared\FormRenderer;
```

### 3. **Escape Output Dimenticato**
```php
// ❌ Non sicuro
echo $client->name;

// ✅ Sicuro
echo esc_html($client->name);

// ✅ O usa i renderer
TableRenderer::cell($client->name);  // Auto-escaped
```

---

## 📚 Risorse

- [Componenti Condivisi](./src/Admin/Pages/Shared/README.md)
- [Design System SCSS](./assets/scss/README.md)
- [Esempi Completi](./REFACTORING_COMPLETE.md)
- [Dashboard Example](./src/Admin/Pages/DashboardPage.php)
- [Anomalies Example](./src/Admin/Pages/AnomaliesPage.refactored.php)

---

## ✅ Conclusione

Seguendo questi passi hai:
- ✅ Modularizzato una pagina admin
- ✅ Separato concerns
- ✅ Creato codice riutilizzabile
- ✅ Migliorato testabilità

**Prossima pagina da modularizzare:**
- SettingsPage.php (300 righe)
- SchedulesPage.php (288 righe)
- Oppure crea una nuova pagina da zero con i componenti!

---

**Buon refactoring! 🚀**
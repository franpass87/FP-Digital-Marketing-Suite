# Componenti Condivisi - Guida d'Uso

## 📦 Panoramica

Questa cartella contiene componenti UI riutilizzabili per tutte le pagine admin del plugin.

### Componenti Disponibili

1. **TableRenderer** - Rendering tabelle HTML
2. **FormRenderer** - Elementi form (input, select, checkbox, etc.)
3. **TabsRenderer** - Tab navigation WordPress-style

---

## 🎯 TableRenderer

Componente per rendering tabelle HTML con stile WordPress admin.

### Esempio Base

```php
use FP\DMS\Admin\Pages\Shared\TableRenderer;

$headers = ['Nome', 'Email', 'Ruolo'];
$rows = [
    ['Mario Rossi', 'mario@example.com', 'Admin'],
    ['Lucia Verdi', 'lucia@example.com', 'Editor'],
];

TableRenderer::render($headers, $rows, [
    'class' => 'widefat striped',
    'empty_message' => 'Nessun utente trovato'
]);
```

### Esempio Avanzato con Custom Renderer

```php
use FP\DMS\Admin\Pages\Shared\TableRenderer;

$headers = ['Cliente', 'Status', 'Azioni'];
$data = $repo->getAllClients();

TableRenderer::render($headers, $data, [
    'empty_message' => 'Nessun cliente',
    'row_renderer' => function($client) {
        TableRenderer::startRow();
        TableRenderer::cell($client->name);
        
        // Status con badge
        $badge = '<span class="fpdms-badge-success">Attivo</span>';
        TableRenderer::rawCell($badge);
        
        // Actions
        $actions = '<a href="edit.php?id=' . $client->id . '">Modifica</a>';
        TableRenderer::rawCell($actions);
        
        TableRenderer::endRow();
    }
]);
```

### API Reference

#### `render(array $headers, array $rows, array $options = [])`
Renderizza una tabella completa.

**Parametri:**
- `$headers` - Array di intestazioni colonne
- `$rows` - Array di righe dati
- `$options` - Opzioni:
  - `class` (string) - Classe CSS tabella (default: 'widefat striped')
  - `empty_message` (string) - Messaggio quando vuota
  - `row_renderer` (callable) - Funzione custom per render righe

#### Metodi Helper

```php
TableRenderer::startRow($class = '');     // Inizia riga
TableRenderer::endRow();                  // Chiude riga
TableRenderer::cell($content, $class);    // Cella con escape
TableRenderer::rawCell($html, $class);    // Cella HTML raw (attenzione!)
```

---

## 📝 FormRenderer

Componente per rendering elementi form con WordPress styling.

### Select Dropdown

```php
use FP\DMS\Admin\Pages\Shared\FormRenderer;

FormRenderer::select([
    'id' => 'client-selector',
    'name' => 'client_id',
    'label' => 'Seleziona Cliente',
    'options' => [
        '1' => 'Cliente A',
        '2' => 'Cliente B',
        '3' => 'Cliente C',
    ],
    'selected' => '2',
    'required' => true,
    'class' => 'my-custom-class'
]);
```

### Text Input

```php
FormRenderer::input([
    'id' => 'user-email',
    'name' => 'email',
    'type' => 'email',
    'label' => 'Indirizzo Email',
    'value' => 'user@example.com',
    'placeholder' => 'Inserisci email',
    'required' => true,
    'class' => 'regular-text'
]);
```

### Textarea

```php
FormRenderer::textarea([
    'id' => 'description',
    'name' => 'description',
    'label' => 'Descrizione',
    'value' => 'Testo esistente',
    'placeholder' => 'Inserisci descrizione',
    'rows' => 5,
    'class' => 'large-text'
]);
```

### Checkbox

```php
FormRenderer::checkbox([
    'id' => 'enable-notifications',
    'name' => 'notifications_enabled',
    'label' => 'Abilita notifiche',
    'value' => '1',
    'checked' => true
]);
```

### Form Completo

```php
use FP\DMS\Admin\Pages\Shared\FormRenderer;

FormRenderer::open([
    'action' => admin_url('admin-post.php'),
    'method' => 'post',
    'class' => 'my-form'
]);

FormRenderer::nonce('my_action', '_my_nonce');
FormRenderer::hidden('action', 'save_settings');

FormRenderer::input([
    'id' => 'site-name',
    'name' => 'site_name',
    'label' => 'Nome Sito',
    'required' => true
]);

FormRenderer::select([
    'id' => 'theme',
    'name' => 'theme',
    'label' => 'Tema',
    'options' => ['light' => 'Chiaro', 'dark' => 'Scuro']
]);

submit_button('Salva Impostazioni');

FormRenderer::close();
```

### API Reference

#### Input Types
- `input()` - Text, email, url, number, date, etc.
- `textarea()` - Area testo multiriga
- `select()` - Dropdown
- `checkbox()` - Checkbox
- `hidden()` - Campo nascosto

#### Form Wrapper
- `open($config)` - Apri form
- `close()` - Chiudi form
- `nonce($action, $name)` - Nonce field

---

## 🔖 TabsRenderer

Componente per tab navigation WordPress-style.

### Esempio Base

```php
use FP\DMS\Admin\Pages\Shared\TabsRenderer;

$tabs = [
    'overview' => 'Panoramica',
    'settings' => 'Impostazioni',
    'advanced' => 'Avanzate'
];

$currentTab = $_GET['tab'] ?? 'overview';

TabsRenderer::render($tabs, $currentTab, [
    'page' => 'my-plugin-page',
    'client_id' => 123
]);
```

Output HTML:
```html
<h2 class="nav-tab-wrapper">
    <a href="admin.php?page=my-plugin-page&tab=overview&client_id=123" class="nav-tab nav-tab-active">Panoramica</a>
    <a href="admin.php?page=my-plugin-page&tab=settings&client_id=123" class="nav-tab">Impostazioni</a>
    <a href="admin.php?page=my-plugin-page&tab=advanced&client_id=123" class="nav-tab">Avanzate</a>
</h2>
```

### Con Content Wrapper

```php
$currentTab = $_GET['tab'] ?? 'overview';

TabsRenderer::render($tabs, $currentTab, ['page' => 'my-page']);

TabsRenderer::contentStart('tab-content');

if ($currentTab === 'overview') {
    echo '<p>Contenuto overview</p>';
} elseif ($currentTab === 'settings') {
    echo '<p>Contenuto settings</p>';
}

TabsRenderer::contentEnd();
```

### API Reference

#### `render(array $tabs, string $currentTab, array $baseParams = [])`
Renderizza i tab.

**Parametri:**
- `$tabs` - Array associativo [key => label]
- `$currentTab` - Chiave del tab attivo
- `$baseParams` - Parametri query da mantenere

#### Content Wrapper
```php
TabsRenderer::contentStart($class = '');  // Inizia contenuto tab
TabsRenderer::contentEnd();               // Chiude contenuto tab
```

---

## 🎨 Integrazione con Design System

Tutti i componenti sono compatibili con il design system SCSS.

### Badge con FormRenderer

```php
// In una tabella
$statusHtml = '<span class="fpdms-badge-success">' . esc_html('Attivo') . '</span>';
TableRenderer::rawCell($statusHtml);
```

### Card con Form

```php
echo '<div class="fpdms-card">';
    
FormRenderer::open();
FormRenderer::input([
    'id' => 'title',
    'name' => 'title',
    'label' => 'Titolo'
]);
submit_button();
FormRenderer::close();

echo '</div>';
```

---

## ✨ Best Practices

### 1. Sempre Escape Output
```php
// ✅ Corretto
TableRenderer::cell($user->name);  // Auto-escaped

// ❌ Sbagliato
TableRenderer::rawCell($user->name);  // No escape!

// ✅ Corretto con HTML
$html = '<strong>' . esc_html($user->name) . '</strong>';
TableRenderer::rawCell($html);
```

### 2. Usa Array Associativi per Configurazione
```php
// ✅ Leggibile e manutenibile
FormRenderer::input([
    'id' => 'email',
    'name' => 'email',
    'type' => 'email',
    'required' => true
]);
```

### 3. Custom Row Renderer per Logica Complessa
```php
TableRenderer::render($headers, $data, [
    'row_renderer' => function($item) {
        TableRenderer::startRow();
        
        // Logica custom qui
        if ($item->status === 'active') {
            TableRenderer::cell('✓ Attivo');
        } else {
            TableRenderer::cell('✗ Inattivo');
        }
        
        TableRenderer::endRow();
    }
]);
```

### 4. Nonce per Sicurezza
```php
// Sempre includere nonce nei form
FormRenderer::nonce('my_action');

// E verificare nel handler
if (!wp_verify_nonce($_POST['_wpnonce'], 'my_action')) {
    wp_die('Security check failed');
}
```

---

## 🔧 Estensione

Per aggiungere nuovi componenti condivisi:

1. Crea file in `src/Admin/Pages/Shared/`
2. Segui il pattern esistente
3. Documenta nel README
4. Usa type hints e PHPDoc

Esempio nuovo componente:

```php
<?php

namespace FP\DMS\Admin\Pages\Shared;

class AlertRenderer
{
    public static function success(string $message): void
    {
        self::render($message, 'success');
    }
    
    public static function error(string $message): void
    {
        self::render($message, 'error');
    }
    
    private static function render(string $message, string $type): void
    {
        $class = "notice notice-{$type}";
        echo '<div class="' . esc_attr($class) . '">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }
}
```

---

## 📚 Esempi Pratici Completi

Vedi i seguenti file per esempi di utilizzo reale:

- **AnomaliesPage.refactored.php** - Uso completo di tutti i componenti
- **DashboardPage.php** - Esempio con componenti custom e shared
- **OverviewPage.php** - Pattern avanzato con configurazione

---

**Tutti i componenti sono testati e pronti per l'uso! 🚀**
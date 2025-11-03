# Fix: Nascondi Notice di Altri Plugin nelle Pagine FP-DMS

## Problema Identificato

Le notice (notifiche) di altri plugin WordPress appaiono nell'header delle pagine del plugin FP-Digital-Marketing-Suite, creando confusione visiva e cattiva UX.

### Esempio
Nella pagina "Impostazioni" di FP-DMS appariva:
```
FP Digital Publisher requires at least one integration token configured in the settings.
```

Questo messaggio, proveniente dal plugin FP-Publisher, finiva nell'header della pagina Impostazioni di FP-DMS, sopra il titolo "Impostazioni".

### Causa
WordPress mostra tutte le `admin_notices` in tutte le pagine admin, indipendentemente dal plugin che le genera.

## Soluzione Implementata

### 1. Menu.php - Aggiungi Hook per Filtrare Notice
**File**: `src/Admin/Menu.php`

Aggiunto hook `admin_notices` con priorità 0 (esegue per primo):

```php
public static function init(): void
{
    add_action('admin_menu', [self::class, 'register']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueueGlobalAssets']);
    add_action('admin_notices', [self::class, 'hideExternalNotices'], 0);
}
```

### 2. Metodo hideExternalNotices()
**File**: `src/Admin/Menu.php`

Nuovo metodo che:
1. Verifica se siamo in una pagina FP-DMS
2. Rimuove TUTTE le notice di altri plugin
3. Ripristina solo le notice del plugin FP-DMS

```php
public static function hideExternalNotices(): void
{
    // Check if we're on a FP-DMS admin page
    $screen = get_current_screen();
    if (! $screen || strpos($screen->id, 'fp-dms') === false) {
        return;
    }

    // Remove all admin notices that are not from FP-DMS
    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    
    // Re-add only FP-DMS specific notices
    add_action('admin_notices', function() {
        // Display only settings_errors for fpdms
        settings_errors('fpdms_settings');
    });
}
```

### 3. SettingsPage.php - Rimossa Chiamata Duplicata
**File**: `src/Admin/Pages/SettingsPage.php`

Rimossa la chiamata duplicata a `settings_errors('fpdms_settings')` dato che ora è gestita globalmente.

**Prima**:
```php
echo '</div>';

settings_errors('fpdms_settings');

echo '<div class="fpdms-card">';
```

**Dopo**:
```php
echo '</div>';

// settings_errors are now handled globally in Menu::hideExternalNotices()

echo '<div class="fpdms-card">';
```

## Come Funziona

### Flusso di Esecuzione

1. **WordPress carica pagina admin** di FP-DMS
2. **Hook `admin_notices` viene chiamato** con priorità 0
3. **`hideExternalNotices()` esegue**:
   - Verifica screen ID (es: `toplevel_page_fp-dms-dashboard`, `fp-suite_page_fp-dms-settings`)
   - Se è una pagina FP-DMS: rimuove tutte le notice
   - Ripristina solo `settings_errors('fpdms_settings')`
4. **Risultato**: Solo le notice di FP-DMS vengono mostrate

### Pagine Protette

Il fix si applica automaticamente a **tutte le pagine del plugin**:
- ✅ Dashboard (`fp-dms-dashboard`)
- ✅ Overview (`fp-dms-overview`)
- ✅ Clienti (`fp-dms-clients`)
- ✅ Connessioni (`fp-dms-datasources`)
- ✅ Automazione (`fp-dms-schedules`)
- ✅ QA Automation (`fp-dms-qa`)
- ✅ Report (`fp-dms-reports`)
- ✅ Template (`fp-dms-templates`)
- ✅ Anomalie (`fp-dms-anomalies`)
- ✅ Impostazioni (`fp-dms-settings`)
- ✅ System Health (`fp-dms-health`)
- ✅ Logs (`fp-dms-logs`)
- ✅ Debug (`fp-dms-debug`)

## Testing

### Test Manuale

1. **Attiva altri plugin** che mostrano notice (es: FP-Publisher)
2. **Vai su una pagina WordPress** standard (es: Dashboard WP)
   - ✅ Dovresti vedere le notice di altri plugin
3. **Vai su una pagina FP-DMS** (es: Impostazioni)
   - ✅ NON dovresti vedere notice di altri plugin
   - ✅ Solo le notice di FP-DMS sono visibili
4. **Salva impostazioni** in FP-DMS Settings
   - ✅ Il messaggio di successo di FP-DMS appare
   - ✅ Nessuna notice esterna visibile

### Test con Debug

In `functions.php` del tema:
```php
add_action('admin_notices', function() {
    echo '<div class="notice notice-warning"><p>Test notice da altro plugin</p></div>';
}, 999);
```

**Comportamento atteso**:
- Pagine WordPress normali: notice visibile
- Pagine FP-DMS: notice NON visibile

## Vantaggi

1. **UX Migliorata**: Header pulito e professionale
2. **Focus**: Solo messaggi rilevanti al plugin
3. **Branding**: Interfaccia coerente e non inquinata
4. **Performance**: Meno HTML da renderizzare
5. **Automatico**: Si applica a tutte le pagine FP-DMS

## Note Tecniche

### Priorità Hook
L'hook usa priorità `0` (molto alta) per eseguire prima di altri plugin:
```php
add_action('admin_notices', [self::class, 'hideExternalNotices'], 0);
```

### Screen ID Detection
Il metodo verifica lo screen ID con `strpos()`:
```php
if (! $screen || strpos($screen->id, 'fp-dms') === false) {
    return;
}
```

Questo funziona perché tutte le pagine del plugin hanno ID che contiene `fp-dms`.

### Settings Errors
`settings_errors('fpdms_settings')` viene mantenuto perché:
- È il sistema standard WordPress per mostrare messaggi di successo/errore
- È specifico per FP-DMS (scope `fpdms_settings`)
- Fornisce feedback importante all'utente (es: "Impostazioni salvate")

## File Modificati

1. ✅ `src/Admin/Menu.php` - Aggiunto hook e metodo `hideExternalNotices()`
2. ✅ `src/Admin/Pages/SettingsPage.php` - Rimossa chiamata duplicata

## Compatibilità

- ✅ WordPress 5.8+
- ✅ PHP 8.1+
- ✅ Tutti i plugin WordPress compatibili
- ✅ No breaking changes

---

**Data Fix**: 26 Ottobre 2025
**Issue**: Notice di altri plugin nell'header FP-DMS
**Stato**: ✅ Risolto


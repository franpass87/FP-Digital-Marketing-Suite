# Fix: Fatal Error in Settings Page

## Problema Identificato

Errore critico nella pagina Impostazioni del plugin:
```
PHP Fatal error: Call to undefined method FP\DMS\Infra\Options::get() 
in SettingsPage.php:74
```

### Causa Root
La classe `Options` non aveva i metodi helper `get()`, `update()` e `delete()` per gestire singole opzioni WordPress.

Il file `SettingsPage.php` cercava di usare:
- `Options::get('fpdms_openai_api_key', '')`
- `Options::get('fpdms_ai_model', 'gpt-5-nano')`
- `Options::update('fpdms_openai_api_key', $value)`
- `Options::update('fpdms_ai_model', $value)`

Ma questi metodi non esistevano nella classe.

## Modifica Implementata

### Options.php - Aggiunta metodi helper per opzioni singole
**File**: `src/Infra/Options.php`

Aggiunti tre nuovi metodi statici pubblici:

```php
/**
 * Get a single option from WordPress
 */
public static function get(string $option, $default = '')
{
    return get_option($option, $default);
}

/**
 * Update a single option in WordPress
 */
public static function update(string $option, $value): bool
{
    return update_option($option, $value);
}

/**
 * Delete a single option from WordPress
 */
public static function delete(string $option): bool
{
    return delete_option($option);
}
```

### Vantaggi
1. **Consistenza API**: Tutti gli accessi alle opzioni passano attraverso la classe `Options`
2. **Type safety**: Dichiarazioni di tipo PHP per parametri e return values
3. **Documentazione**: PHPDoc per ogni metodo
4. **Estendibilità**: Facile aggiungere validazione o logging in futuro

## Utilizzo nella SettingsPage

La pagina Settings ora può usare correttamente:

```php
// Lettura
$openaiKey = Options::get('fpdms_openai_api_key', '');
$aiModel = Options::get('fpdms_ai_model', 'gpt-5-nano');

// Scrittura
Options::update('fpdms_openai_api_key', $newKey);
Options::update('fpdms_ai_model', $selectedModel);

// Cancellazione (se necessario)
Options::delete('fpdms_some_option');
```

## Testing

### Test Manuale
1. ✅ Vai in **FP Suite > Impostazioni**
2. ✅ La pagina si carica senza errori critici
3. ✅ I campi "OpenAI API Key" e "Modello AI" sono visibili
4. ✅ Inserisci/modifica valori e clicca "Salva"
5. ✅ I valori vengono salvati correttamente
6. ✅ Ricarica la pagina: i valori persistono

### Verifica Log
Prima del fix nel `debug.log`:
```
PHP Fatal error: Call to undefined method FP\DMS\Infra\Options::get()
```

Dopo il fix:
```
Nessun errore nella pagina Settings
```

## File Modificati

1. ✅ `src/Infra/Options.php` - Aggiunti metodi `get()`, `update()`, `delete()`

## Compatibilità

- ✅ Compatibile con PHP >= 8.1
- ✅ Backward compatible con codice esistente
- ✅ No breaking changes
- ✅ Non richiede migrazioni

## Note Aggiuntive

### Opzioni Gestite
Nella Settings Page vengono gestite opzioni per:
- **AI Settings**: 
  - `fpdms_openai_api_key` - Chiave API OpenAI
  - `fpdms_ai_model` - Modello AI selezionato (gpt-5-nano, gpt-5-mini, etc.)
- **Global Settings**: Gestite tramite `Options::getGlobalSettings()` / `updateGlobalSettings()`

### Sicurezza
Le chiavi API vengono gestite in modo sicuro:
- Input type `password` nel form
- Sanitizzazione tramite `sanitize_text_field()`
- Stored in WordPress options table

---

**Data Fix**: 26 Ottobre 2025
**Issue**: Fatal error in Settings page
**Stato**: ✅ Risolto


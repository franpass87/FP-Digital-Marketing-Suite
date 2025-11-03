# Fix: AI Insights Cache - Overview Mostra Errore anche con API Key Configurata

## Problema Identificato

Dopo aver configurato l'API Key OpenAI e il modello AI nelle Impostazioni, l'**Overview continuava a mostrare** il messaggio:
> "Per utilizzare questa funzionalità, configura la tua API Key OpenAI nelle impostazioni."

### Causa Root

Le opzioni AI erano **correttamente salvate** nel database, ma l'Overview mostrava una **risposta cachata** dell'endpoint `/overview/ai-insights` che aveva una durata di **10 minuti (600 secondi)**.

Quando l'API Key non era configurata, l'endpoint restituiva un errore che veniva cachato. Anche dopo aver configurato la chiave, la cache non veniva invalidata.

## Diagnostica

### Test Eseguito

Creato script di test (`test-ai-quick.php`) che ha verificato:

1. ✅ **Database**: `fpdms_openai_api_key` e `fpdms_ai_model` erano salvate
2. ✅ **Options::get()**: Il metodo recuperava correttamente le opzioni
3. ✅ **AIInsightsService::hasOpenAIKey()**: Restituiva `TRUE`
4. ✅ **Logica Settings**: Il salvataggio funzionava correttamente

**Conclusione**: Tutto OK, problema era la cache.

## Soluzioni Implementate

### 1. Script di Pulizia Cache Manuale

**File**: `clear-ai-cache.php`

Script browser-based per svuotare manualmente tutte le cache Overview:
- Svuota cache `ai_insights`
- Svuota cache `summary`
- Svuota cache `status`
- Svuota cache `trend`

**Utilizzo**: 
```
http://sito.local/wp-content/plugins/FP-Digital-Marketing-Suite-1/clear-ai-cache.php
```

### 2. Auto-Clear Cache quando Cambiano Impostazioni AI

**File**: `src/Admin/Pages/SettingsPage.php`

Modifiche implementate:

```php
// Detect AI settings changes
$aiSettingsChanged = false;

$openaiKey = Wp::sanitizeTextField($post['openai_api_key'] ?? '');
if ($openaiKey !== '') {
    $oldKey = Options::get('fpdms_openai_api_key', '');
    if ($oldKey !== $openaiKey) {
        $aiSettingsChanged = true;
    }
    Options::update('fpdms_openai_api_key', $openaiKey);
}

// Same for ai_model...

// Clear cache if settings changed
if ($aiSettingsChanged) {
    self::clearAICache();
}
```

Nuovo metodo `clearAICache()`:
```php
private static function clearAICache(): void
{
    try {
        $cache = new \FP\DMS\Services\Overview\Cache();
        $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        $clients = $clientsRepo->all();
        
        foreach ($clients as $client) {
            $cache->clearAllForClient($client->id);
        }
    } catch (\Throwable $e) {
        error_log('[FP-DMS] Failed to clear AI cache: ' . $e->getMessage());
    }
}
```

### 3. Debug Info nelle Impostazioni

**File**: `src/Admin/Pages/SettingsPage.php`

Aggiunto indicatore visivo sotto i campi AI che mostra:
- ✅ Verde: Opzione salvata (mostra prime 10 e ultime 4 cifre della chiave)
- ⚠️ Arancione: Opzione non salvata

```php
$savedKey = Options::get('fpdms_openai_api_key', '');
if (!empty($savedKey)) {
    echo '<p class="description" style="color:green;">✅ <strong>Chiave salvata:</strong> ' 
         . substr($savedKey, 0, 10) . '...' . substr($savedKey, -4) . '</p>';
} else {
    echo '<p class="description" style="color:orange;">⚠️ <strong>Nessuna chiave salvata</strong></p>';
}
```

## Flusso Risoluzione

### Prima del Fix
1. Utente configura API Key nelle Impostazioni
2. Utente va in Overview
3. ❌ Overview mostra errore (cache vecchia)
4. Utente aspetta 10 minuti oppure è confuso

### Dopo il Fix
1. Utente configura API Key nelle Impostazioni
2. **Cache viene automaticamente svuotata** ✅
3. Utente va in Overview
4. ✅ Overview funziona immediatamente

## Testing

### Test Manuale

1. **Vai nelle Impostazioni**
2. **Cambia API Key o Modello AI**
3. **Clicca "Salva impostazioni"**
4. **Vai in Overview immediatamente**
5. ✅ AI Insights dovrebbero funzionare (nessun messaggio di errore)

### Test Cache Timing

Senza il fix:
- Cache durata: 600 secondi (10 minuti)
- Comportamento: Errore persiste anche dopo configurazione

Con il fix:
- Cache invalidata: Immediatamente al salvataggio
- Comportamento: Funziona subito

## File Modificati

1. ✅ `src/Admin/Pages/SettingsPage.php` - Auto-clear cache + debug info
2. ✅ `clear-ai-cache.php` - Script manuale di pulizia cache
3. ✅ `test-ai-quick.php` - Script di diagnostica

## File di Supporto

- `src/Services/Overview/Cache.php` - Metodo `clearAllForClient()` già esistente
- `src/Http/OverviewRoutes.php` - Endpoint ai-insights con cache 600s

## Cache TTL nell'Overview

| Endpoint | Cache Key | TTL | Invalidazione |
|----------|-----------|-----|---------------|
| `/overview/summary` | `summary` | 120s | Manuale o auto (dopo sync) |
| `/overview/status` | `status` | 180s | Manuale |
| `/overview/ai-insights` | `ai_insights` | 600s | **Auto (dopo cambio settings)** ✅ |
| `/overview/trend` | `trend` | 120s | Manuale |

## Vantaggi

1. ✅ **UX Migliorata**: Funziona immediatamente dopo configurazione
2. ✅ **Debug Facilitato**: Indicatori visivi nelle impostazioni
3. ✅ **Zero Confusione**: Niente attese di 10 minuti
4. ✅ **Fail-Safe**: Se clearCache fallisce, log dell'errore ma non blocca il salvataggio

## Note

### Perché Cache di 10 Minuti?

Le AI Insights sono **costose**:
- Chiamata API OpenAI
- Processing dati
- Generazione insights

Cache lunga (600s) evita chiamate ripetute e costi inutili.

### Quando Svuotare Manualmente?

Usa `clear-ai-cache.php` se:
- Cambi le metriche disponibili nei dati
- Vuoi forzare rigenerazione insights
- Debug/testing

---

**Data Fix**: 26 Ottobre 2025
**Issue**: AI Insights cache non invalidata dopo configurazione
**Stato**: ✅ Risolto

**Impatto Utente**: Da "Confuso per 10 minuti" → "Funziona immediatamente" 🚀


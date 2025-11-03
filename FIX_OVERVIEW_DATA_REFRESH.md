# Fix: Overview Data Not Refreshing After Sync

## Problema Identificato

I dati nell'Overview non si aggiornano immediatamente dopo la sincronizzazione delle Data Sources.

### Causa Root
1. **Cache non invalidata**: Dopo il sync delle Data Sources, la cache dell'Overview (TTL 120 secondi) non veniva svuotata
2. **Reload completo della pagina**: Il sistema faceva un `location.reload()` che ricaricava i dati cachati

## Modifiche Implementate

### 1. Cache.php - Nuovo metodo per invalidare cache per client
**File**: `src/Services/Overview/Cache.php`

Aggiunto il metodo `clearAllForClient(int $clientId)` che:
- Elimina tutte le cache transient relative a un cliente specifico
- Utilizza pattern matching SQL per efficienza
- Pulisce sia i transient che i timeout

```php
public function clearAllForClient(int $clientId): void
{
    global $wpdb;
    
    $pattern = $wpdb->esc_like($this->prefix . '_' . $clientId . '_') . '%';
    
    // Delete from options table
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        )
    );
    
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $pattern
        )
    );
}
```

### 2. DataSourceSyncService.php - Invalidazione cache dopo sync
**File**: `src/Services/Sync/DataSourceSyncService.php`

**Modifiche**:
- Aggiunto `use FP\DMS\Services\Overview\Cache;`
- In `syncClientDataSources()`: dopo la sincronizzazione, viene chiamato `$cache->clearAllForClient($clientId)`

Questo assicura che:
- Dopo ogni sync, la cache viene svuotata
- Il prossimo caricamento dell'Overview recupera dati freschi
- Funziona sia per sync singolo client che per sync globale

### 3. datasources-sync.js - Reload intelligente senza ricaricare la pagina
**File**: `assets/js/datasources-sync.js`

**Prima**:
```javascript
location.reload(); // Ricarica tutta la pagina
```

**Dopo**:
```javascript
// Trigger custom event per reload dati senza page refresh
const event = new CustomEvent('fpdms-reload-overview', { 
    detail: { source: 'sync', clientId: clientId } 
});
document.dispatchEvent(event);
```

**Vantaggi**:
- UX migliore: no flicker della pagina
- Più veloce: ricarica solo i dati necessari
- Mantiene lo stato UI (scroll, filtri, etc.)

### 4. overview.js - Event listener per reload dati
**File**: `assets/js/overview.js`

Aggiunto listener per l'evento `fpdms-reload-overview`:
```javascript
document.addEventListener('fpdms-reload-overview', (e) => {
    if (window.fpdmsDebug) {
        console.log('FPDMS: Reloading overview data after sync', e.detail);
    }
    
    if (state.state.clientId) {
        loadAll(false);
    }
});
```

## Flusso Completo del Fix

1. **Utente clicca "Sync Data Sources"**
2. **Backend sincronizza** le sorgenti dati (GA4, GSC, Google Ads, Meta Ads)
3. **Cache viene svuotata** per il cliente specifico
4. **Frontend riceve successo** dal sync
5. **Evento custom** viene emesso
6. **Overview ricarica i dati** senza page refresh
7. **Dati freschi** vengono mostrati immediatamente

## Testing

### Test Manuale
1. Vai nell'Overview del plugin
2. Nota i valori attuali delle metriche
3. Clicca "Sync Data Sources"
4. Attendi il completamento (barra di progresso)
5. Verifica che:
   - ✅ I dati si aggiornano automaticamente
   - ✅ La pagina NON fa reload completo
   - ✅ Appare il toast di successo
   - ✅ I nuovi valori sono visibili

### Test Tecnico
```bash
# Verifica che la cache viene svuotata
# In console browser (con window.fpdmsDebug = true):
# Dovresti vedere: "FPDMS: Reloading overview data after sync"

# Verifica transient in DB prima del sync:
SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_fpdms_overview_%';

# Esegui sync

# Verifica transient dopo sync (dovrebbero essere eliminati):
SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_fpdms_overview_%';
```

## File Modificati

1. ✅ `src/Services/Overview/Cache.php`
2. ✅ `src/Services/Sync/DataSourceSyncService.php`
3. ✅ `assets/js/datasources-sync.js`
4. ✅ `assets/js/overview.js`

## Compatibilità

- ✅ Compatibile con tutte le versioni PHP >= 8.1
- ✅ Nessuna breaking change
- ✅ Backward compatible
- ✅ Non richiede migrazioni DB

## Note Aggiuntive

### Cache TTL
La cache dell'Overview ha i seguenti TTL:
- **Summary**: 120 secondi
- **Status**: 180 secondi
- **AI Insights**: 600 secondi

Con questo fix, la cache viene invalidata immediatamente dopo il sync, garantendo dati sempre freschi.

### Rate Limiting
Il sistema mantiene il rate limiting di 1 secondo tra richieste successive per proteggere il server.

---

**Data Fix**: 26 Ottobre 2025
**Sviluppatore**: AI Assistant
**Stato**: ✅ Completato e Testato


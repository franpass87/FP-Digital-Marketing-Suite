# 🚀 QUICK REFERENCE - Miglioramenti Prioritari
## FP Digital Marketing Suite v0.9.0

> **Versione sintetica** - Per analisi completa vedi `ANALISI_MIGLIORAMENTI_2025-10-26.md`

---

## 📊 VALUTAZIONE RAPIDA

**Overall Score**: A- (87/100) ⭐⭐⭐⭐

| Area | Voto | Status |
|------|------|--------|
| Sicurezza | A (95%) | ✅ Eccellente |
| Architettura | A- (90%) | ✅ Molto buona |
| Performance | B (75%) | ⚠️ Migliorabile |
| UX | B+ (82%) | ⚠️ Migliorabile |
| Testing | B (75%) | ⚠️ Espandibile |

---

## 🎯 TOP 5 PRIORITÀ

### 1. 🔴 ALTA - CacheManager (Impact: ⬆️75% speed)
**Problema**: No persistent cache, ogni request ricarica tutto  
**Soluzione**: `src/Infra/CacheManager.php` con dual-layer  
**Tempo**: 4-6 ore

```php
$cache->remember('clients_all', 300, function() {
    return $repo->all();
});
```

---

### 2. 🔴 ALTA - Database Indices (Impact: ⬆️30% queries)
**Problema**: Indici mancanti su colonne critiche  
**Soluzione**: Migration in `DB::addIndices()`  
**Tempo**: 1-2 ore

```sql
ALTER TABLE wp_fpdms_reports 
ADD INDEX idx_client_status_date (client_id, status, created_at);
```

---

### 3. 🟡 MEDIA - Progress Indicators (Impact: UX)
**Problema**: No feedback su operazioni lunghe  
**Soluzione**: Loading spinners + status messages  
**Tempo**: 2-3 ore

```javascript
btn.html('<span class="spinner is-active"></span> Sincronizzazione...');
```

---

### 4. 🟡 MEDIA - Rate Limiting (Impact: Security)
**Problema**: No protection da abuse  
**Soluzione**: `Support/RateLimiter.php`  
**Tempo**: 2 ore

```php
if (!RateLimiter::check('tick_' . $ip, 10, 60)) {
    return new WP_Error('rate_limited', 'Too many requests');
}
```

---

### 5. 🟡 MEDIA - API Pagination (Impact: Scalability)
**Problema**: No pagination, limit fisso a 50  
**Soluzione**: Standard pagination con meta  
**Tempo**: 3-4 ore

```php
'meta' => [
    'current_page' => $page,
    'total' => $total,
    'total_pages' => ceil($total / $perPage),
]
```

---

## ⚡ QUICK WINS (< 1 giorno)

### A. CacheManager Base
**File**: `src/Infra/CacheManager.php` (nuovo)  
**Impact**: +50% performance  
**Code**: 150 righe

### B. Database Indices
**File**: `src/Infra/DB.php` (modifica)  
**Impact**: +30% query speed  
**Code**: 30 righe

### C. Form Validation
**File**: `assets/js/form-validation.js` (nuovo)  
**Impact**: -50% form errors  
**Code**: 100 righe

### D. Bulk Actions
**File**: `src/Admin/Pages/ClientsPage.php` (modifica)  
**Impact**: UX professionale  
**Code**: 80 righe

---

## 📁 FILE DA CREARE

```
src/Infra/
├── CacheManager.php          (nuovo - 200 righe) 🔴
├── NotificationQueue.php     (nuovo - 150 righe)
└── Metrics.php               (nuovo - 100 righe)

src/Support/
└── RateLimiter.php           (nuovo - 60 righe) 🔴

assets/js/
├── form-validation.js        (nuovo - 150 righe) 🔴
└── progress-indicators.js    (nuovo - 100 righe)

.php-cs-fixer.php             (nuovo - 40 righe)
phpstan.neon                  (nuovo - 20 righe)
```

---

## 🔧 FILE DA MODIFICARE

```
src/Infra/DB.php
├── addIndices()              (nuovo metodo - 30 righe) 🔴

src/Domain/Repos/
├── ClientsRepo.php           (add cache - 20 righe)
├── ReportsRepo.php           (add cache + eager loading - 40 righe)
└── DataSourcesRepo.php       (add cache - 20 righe)

src/Services/Connectors/
├── GoogleAdsProvider.php     (add cache - 15 righe)
├── ClarityProvider.php       (add cache - 15 righe)
└── GA4Provider.php           (add cache - 15 righe)

src/Http/Routes.php
├── handleTick()              (add rate limit - 5 righe)
├── handleReportsList()       (add pagination - 30 righe)
└── handleDocs()              (nuovo endpoint - 80 righe)

src/Admin/Pages/
├── ClientsPage.php           (bulk actions - 60 righe)
└── DataSourcesPage.php       (validazione - già fatto ✅)
```

---

## 🚦 ROADMAP 30 GIORNI

### Settimana 1: Performance
- [ ] Giorno 1-2: CacheManager implementation
- [ ] Giorno 3: Database indices + migrations
- [ ] Giorno 4-5: Cache integration in Repos & Providers

**Goal**: Page load < 300ms, API cache hit > 80%

---

### Settimana 2: User Experience
- [ ] Giorno 1-2: Progress indicators
- [ ] Giorno 3: Form validation real-time
- [ ] Giorno 4-5: Bulk actions + better errors

**Goal**: User confusion < 10%, form errors < 5%

---

### Settimana 3: API & Reliability
- [ ] Giorno 1-2: Rate limiting system
- [ ] Giorno 3: API pagination
- [ ] Giorno 4-5: Notification queue + retry logic

**Goal**: API uptime > 99.9%, webhook success > 95%

---

### Settimana 4: Quality (opzionale)
- [ ] Giorno 1-2: PSR-12 compliance
- [ ] Giorno 3: PHPStan level 8
- [ ] Giorno 4-5: Test coverage > 90%

**Goal**: 0 linter errors, 0 PHPStan errors

---

## 💡 CODE SNIPPETS UTILI

### Cache Pattern
```php
// In ogni Repo
private CacheManager $cache;

public function all(): array {
    return $this->cache->remember('key', 300, fn() => /* query */);
}
```

### Rate Limit Pattern
```php
// In ogni endpoint critico
if (!RateLimiter::check($key, $limit, $window)) {
    return new WP_Error('rate_limited', '...');
}
```

### Progress Pattern
```javascript
// In ogni operazione lunga
$btn.html('<span class="spinner is-active"></span> Loading...');
$.ajax({ /* ... */ })
    .done(() => showSuccess())
    .fail(() => showError())
    .always(() => restoreButton());
```

---

## 📊 METRICHE TARGET

| Metrica | Attuale | Target | Miglioramento |
|---------|---------|--------|---------------|
| Page Load | 800ms | 200ms | -75% |
| API Response | 2-5s | 50-200ms | -90% |
| DB Queries/req | 20-30 | 5-10 | -66% |
| Memory | 15MB | 10MB | -33% |
| Cache Hit Rate | 0% | 80%+ | NEW |
| Test Coverage | ~70% | 90%+ | +20% |

---

## 🔍 DEBUGGING TIPS

### Performance Issues
```bash
# Check slow queries
tail -f wp-content/uploads/fpdms-logs/fpdms.log | grep -i "query"

# Monitor cache
wp cache info

# Check transients
SELECT * FROM wp_options WHERE option_name LIKE '_transient_fpdms_%';
```

### Cache Issues
```php
// Flush all plugin cache
$cache = new CacheManager();
$cache->flush();

// Check cache hit
Logger::log('Cache hit: ' . ($value ? 'YES' : 'NO'));
```

### API Issues
```bash
# Test endpoint
curl -X POST https://site.com/wp-json/fpdms/v1/tick?key=SECRET

# Check rate limit
curl -I https://site.com/wp-json/fpdms/v1/reports
```

---

## ✅ CHECKLIST PRE-DEPLOY

- [ ] `composer cs-check` ✅ No errors
- [ ] `composer phpstan` ✅ No errors
- [ ] `composer test` ✅ All tests pass
- [ ] Cache warming done
- [ ] Database indices created
- [ ] Backup database
- [ ] Test on staging first
- [ ] Monitor error logs for 24h

---

## 📚 RISORSE

### Documentazione
- [Analisi Completa](./ANALISI_MIGLIORAMENTI_2025-10-26.md)
- [Architettura](./src/MODULAR_ARCHITECTURE.md)
- [Security Audit](./SECURITY_AUDIT_FINAL_2025-10-08.md)
- [Changelog](./CHANGELOG.md)

### File Chiave
- Cache: `src/Services/Overview/Cache.php` (esempio esistente)
- Security: `src/Infra/CredentialManager.php`
- Logger: `src/Infra/Logger.php`
- Routes: `src/Http/Routes.php`

### External
- [WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/)
- [PSR-12 Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)

---

**Last Updated**: 26 Ottobre 2025  
**Next Review**: Sprint retrospective ogni settimana  
**Contact**: info@francescopasseri.com


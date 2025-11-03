# 📋 Sistema di Review Report - Documentazione

## 🎯 Panoramica

Il sistema di **Review Report** permette di gestire, rivedere e approvare i report PDF generati automaticamente dal plugin. Include un'interfaccia admin completa con:

- ✅ Lista report con filtri avanzati
- ✅ Stati di review (Da rivedere, In revisione, Approvato, Rigettato)
- ✅ Note e commenti personalizzati
- ✅ Tracking di chi ha fatto la review e quando
- ✅ Anteprima PDF inline
- ✅ Statistiche dashboard

---

## 🚀 Attivazione

### Installazione Automatica

La funzionalità è **già integrata** nel plugin. Per attivarla:

1. **Disattiva** il plugin FP Digital Marketing Suite
2. **Riattiva** il plugin

Questo eseguirà automaticamente la migrazione del database aggiungendo i campi necessari.

### Verifica Installazione

Dopo la riattivazione, verifica che nel menu admin di WordPress sia apparsa la voce:

```
FP Suite > Reports
```

---

## 📊 Struttura Database

### Nuovi Campi Aggiunti alla Tabella `wp_fpdms_reports`

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `review_status` | VARCHAR(20) | Stato della review (pending, in_review, approved, rejected) |
| `review_notes` | LONGTEXT | Note e commenti sulla review |
| `reviewed_at` | DATETIME | Data e ora della review |
| `reviewed_by` | BIGINT | ID dell'utente che ha fatto la review |

### Stati Review Disponibili

| Stato | Descrizione | Badge |
|-------|-------------|-------|
| `pending` | Da rivedere (default) | 🟡 Giallo |
| `in_review` | In revisione | 🔵 Blu |
| `approved` | Approvato | 🟢 Verde |
| `rejected` | Rigettato | 🔴 Rosso |

---

## 🎨 Interfaccia Utente

### Pagina Reports Review

**Posizione:** `wp-admin > FP Suite > Reports`

#### Sezione Statistiche

Dashboard con 4 card:
- **Totale Report**: Numero totale di report generati
- **Da Rivedere**: Report in attesa di review (stato `pending`)
- **Approvati**: Report approvati
- **Rigettati**: Report rigettati

#### Filtri

Filtra i report per:
- **Cliente**: Seleziona un cliente specifico
- **Stato Generazione**: success, queued, running, failed
- **Stato Review**: pending, in_review, approved, rejected

#### Tabella Report

Colonne visibili:
1. **Cliente** - Nome del cliente
2. **Periodo** - Date inizio e fine del report
3. **Generato** - Data e ora di creazione
4. **Stato** - Stato della generazione (success, failed, etc.)
5. **Review** - Badge con stato review
6. **Azioni** - Pulsanti per visualizzare PDF e fare review

#### Form di Review

Cliccando sul pulsante "Review" si apre un form con:

- **Informazioni Review Precedente** (se presente)
  - Data/ora ultima review
  - Utente che ha fatto la review
  
- **Note di Revisione**
  - Area di testo per commenti e osservazioni
  
- **Azioni Disponibili**
  - ✅ **Approva** - Segna come approvato
  - ❌ **Rigetta** - Segna come rigettato
  - 🔄 **Ripristina** - Torna a "Da rivedere"
  - 🗑️ **Elimina** - Elimina definitivamente il report

---

## 🔧 File Creati/Modificati

### File Nuovi

1. **src/Admin/Pages/ReportsPage.php**
   - Pagina admin principale
   - Rendering interfaccia
   - Gestione filtri e azioni

2. **src/Admin/Ajax/ReportReviewHandler.php**
   - Handler AJAX per azioni di review
   - Metodi: `handleUpdateReview`, `handleDeleteReport`, `handleBulkAction`

3. **assets/css/reports-review.css**
   - Stili moderni per l'interfaccia
   - Design responsive
   - Animazioni e transizioni

4. **assets/js/reports-review.js**
   - JavaScript per interazioni
   - Toggle form review
   - Gestione stati UI

### File Modificati

1. **src/Infra/DB.php**
   - Aggiunto metodo `migrateReportsReview()`
   - Aggiornato schema tabella `reports`

2. **src/Domain/Entities/ReportJob.php**
   - Aggiunti campi: `reviewStatus`, `reviewNotes`, `reviewedAt`, `reviewedBy`
   - Aggiornati metodi `fromRow()` e `toRow()`

3. **src/Domain/Repos/ReportsRepo.php**
   - Aggiunto supporto campi review in `update()`
   - Aggiunto filtro `review_status` in `search()`

4. **src/Admin/Menu.php**
   - Aggiunta voce menu "Reports"
   - Registrazione assets hook

5. **src/Infra/Activator.php**
   - Aggiunta chiamata `DB::migrateReportsReview()`

6. **fp-digital-marketing-suite.php**
   - Registrato `ReportReviewHandler`

---

## 💻 Utilizzo API

### Aggiornare lo Stato di Review (PHP)

```php
use FP\DMS\Domain\Repos\ReportsRepo;

$repo = new ReportsRepo();
$repo->update($reportId, [
    'review_status' => 'approved',
    'review_notes' => 'Report verificato e approvato',
    'reviewed_at' => current_time('mysql'),
    'reviewed_by' => get_current_user_id(),
]);
```

### Filtrare Report per Stato Review

```php
$repo = new ReportsRepo();

// Solo report da rivedere
$pendingReports = $repo->search([
    'review_status' => 'pending'
]);

// Report approvati per un cliente
$approvedReports = $repo->search([
    'client_id' => 5,
    'review_status' => 'approved'
]);
```

### AJAX Endpoint

**Endpoint:** `wp-admin/admin-ajax.php`

#### Aggiornare Review

```javascript
jQuery.post(ajaxurl, {
    action: 'fpdms_update_report_review',
    nonce: fpdmsReports.nonce,
    report_id: 123,
    action: 'approve', // approve | reject | pending | in_review
    notes: 'Ottimo report!'
}, function(response) {
    if (response.success) {
        console.log('Review aggiornata!');
    }
});
```

#### Eliminare Report

```javascript
jQuery.post(ajaxurl, {
    action: 'fpdms_delete_report',
    nonce: fpdmsReports.nonce,
    report_id: 123
}, function(response) {
    if (response.success) {
        console.log('Report eliminato!');
    }
});
```

#### Azioni Bulk

```javascript
jQuery.post(ajaxurl, {
    action: 'fpdms_bulk_review_action',
    nonce: fpdmsReports.nonce,
    report_ids: [123, 124, 125],
    action: 'approve' // approve | reject | pending | delete
}, function(response) {
    console.log(response.data.success + ' report processati');
});
```

---

## 🎯 Workflow Consigliato

### Scenario 1: Review Manuale Completa

1. Cliente genera report automaticamente
2. Report appare in "Reports" con stato `pending`
3. Manager apre la review
4. Manager visualizza PDF
5. Manager aggiunge note
6. Manager approva o rigetta
7. Sistema salva review con timestamp e utente

### Scenario 2: Review Automatica

```php
// Dopo la generazione del report
add_action('fpdms_report_generated', function($report) {
    // Auto-approva se nessuna anomalia critica
    if (!hasAnomalie($report)) {
        $repo = new ReportsRepo();
        $repo->update($report->id, [
            'review_status' => 'approved',
            'review_notes' => 'Auto-approvato: nessuna anomalia rilevata',
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => 1, // System user
        ]);
    }
});
```

### Scenario 3: Workflow Multi-Livello

1. Analyst → Segna come `in_review`
2. Senior Analyst → Approva o rigetta
3. Manager → Verifica approvati

```php
// Check permessi per livello
if (current_user_can('review_reports_analyst')) {
    // Può solo segnare in_review
} elseif (current_user_can('review_reports_senior')) {
    // Può approvare/rigettare
}
```

---

## 🔐 Sicurezza

### Controlli Implementati

1. **Nonce Verification**
   - Tutte le azioni AJAX verificano il nonce `fpdms_report_review`

2. **Capability Check**
   - Solo utenti con `manage_options` possono fare review

3. **Input Sanitization**
   - Review notes: `wp_kses_post()`
   - Report ID: `intval()`
   - Status: whitelist validation

4. **SQL Injection Prevention**
   - Uso di `$wpdb->prepare()` per tutte le query

---

## 📱 Responsive Design

L'interfaccia è completamente responsive:

### Desktop (> 782px)
- Tabella completa con tutte le colonne
- Filtri in riga orizzontale
- Stats cards in grid 4 colonne

### Tablet (< 782px)
- Stats cards in grid 2 colonne
- Filtri in colonna verticale
- Tabella con colonne essenziali

### Mobile (< 480px)
- Stats cards in colonna singola
- Filtri full-width
- Tabella trasformata in card layout

---

## 🎨 Personalizzazione CSS

### Override Colori Badge

```css
/* Custom colors per il tuo brand */
.fpdms-review-badge.fpdms-review-approved {
    background: #your-green;
    color: #your-text-color;
}
```

### Nascondere Statistiche

```css
.fpdms-stats-cards {
    display: none;
}
```

---

## 🐛 Debug

### Abilitare Log

```php
// In wp-config.php
define('FPDMS_DEBUG_REVIEWS', true);

// Ora i log appariranno in debug.log
```

### Verificare Migrazione

```php
global $wpdb;
$table = $wpdb->prefix . 'fpdms_reports';
$columns = $wpdb->get_results("DESCRIBE {$table}");
foreach ($columns as $column) {
    echo $column->Field . "\n";
}
// Dovresti vedere: review_status, review_notes, reviewed_at, reviewed_by
```

---

## 📈 Metriche e Analytics

### Query Utili

```sql
-- Report pending da più di 7 giorni
SELECT * FROM wp_fpdms_reports 
WHERE review_status = 'pending' 
AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Tasso di approvazione per cliente
SELECT 
    client_id,
    COUNT(*) as total,
    SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) as approved,
    ROUND(SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as approval_rate
FROM wp_fpdms_reports
GROUP BY client_id;

-- Review per reviewer
SELECT 
    reviewed_by,
    COUNT(*) as reviews_done,
    review_status,
    COUNT(*) as count
FROM wp_fpdms_reports
WHERE reviewed_by IS NOT NULL
GROUP BY reviewed_by, review_status;
```

---

## 🔄 Migrazioni Future

Se in futuro vuoi aggiungere campi, usa questo pattern:

```php
// In DB.php
public static function migrateReviewsV2(): void
{
    global $wpdb;
    $table = self::table('reports');
    
    $columns = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
    $columnNames = array_column($columns, 'Field');
    
    if (!in_array('review_priority', $columnNames, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN review_priority VARCHAR(10) DEFAULT 'normal' AFTER review_status");
    }
}
```

---

## 📞 Supporto

Per problemi o domande:

- **Email**: info@francescopasseri.com
- **GitHub**: Apri una issue nel repository
- **Docs**: Controlla `/docs` per documentazione aggiuntiva

---

## ✅ Checklist Post-Installazione

- [ ] Plugin riattivato
- [ ] Voce menu "Reports" visibile
- [ ] Tabella reports contiene campi review (verifica con phpMyAdmin)
- [ ] Genera un report di test
- [ ] Apri pagina Reports
- [ ] Verifica statistiche mostrate correttamente
- [ ] Testa filtri (cliente, stato, review)
- [ ] Apri review su un report
- [ ] Aggiungi note e approva
- [ ] Verifica badge aggiornato
- [ ] Testa visualizzazione PDF
- [ ] Testa eliminazione report

---

## 🎉 Prossimi Passi

1. **Notifiche Email**
   - Email automatica quando un report è pronto per review
   - Email all'approvazione/rigetto

2. **Bulk Actions**
   - Approva/rigetta multipli report contemporaneamente
   - Esporta report approvati in ZIP

3. **Dashboard Widget**
   - Widget nella dashboard WP con report pending

4. **Review History**
   - Storico completo delle review per ogni report

5. **Custom Statuses**
   - Possibilità di creare stati personalizzati

---

**Versione**: 1.0.0  
**Data**: 25 Ottobre 2025  
**Autore**: Francesco Passeri


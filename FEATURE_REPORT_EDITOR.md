# ✏️ Editor Report - Documentazione Completa

## 🎯 Panoramica

Il **sistema di editing report** permette di modificare il contenuto HTML dei report PDF generati, con un'interfaccia completa che include:

- ✅ **Editor Visuale** (TinyMCE) - Modifica WYSIWYG
- ✅ **Editor HTML** - Modifica codice diretto
- ✅ **Anteprima Live** - Visualizza modifiche prima del salvataggio
- ✅ **Rigenerazione PDF Automatica** - Il PDF viene ricreato con le modifiche
- ✅ **Tracking modifiche** - Chi e quando ha modificato il report

---

## 🚀 Come Funziona

### Workflow Completo

1. **Generazione Report**
   - Il plugin genera il report normalmente
   - L'HTML viene salvato nel campo `meta['html_content']`
   
2. **Modifica Contenuto**
   - Clicca sul pulsante "Modifica Contenuto" (icona codice)
   - Si apre l'editor modale con 3 tab
   
3. **Editor Disponibili**
   - **Visuale**: Editor WYSIWYG per modifiche facili
   - **HTML**: Editor codice per controllo completo
   - **Anteprima**: Visualizza risultato prima di salvare
   
4. **Salvataggio**
   - Clicca "Salva e Rigenera PDF"
   - Il PDF viene ricreato automaticamente con le modifiche
   - Le modifiche vengono tracciate (chi, quando)

---

## 📁 File Creati/Modificati

### File Modificati

1. **src/Services/Reports/ReportBuilder.php**
   - Aggiunto salvataggio HTML in `meta['html_content']`
   - Salvato anche `template_id` per riferimento

2. **src/Admin/Pages/ReportsPage.php**
   - Aggiunto pulsante "Modifica Contenuto"
   - Aggiunto modal con editor completo
   - Tre tab: Visuale, HTML, Anteprima

3. **src/Admin/Ajax/ReportReviewHandler.php**
   - `handleLoadReportHtml()` - Carica HTML del report
   - `handleSaveReportHtml()` - Salva e rigenera PDF

4. **assets/js/reports-review.js**
   - Gestione apertura/chiusura editor
   - Switch tra tab
   - Sincronizzazione tra editor visuale e HTML
   - Salvataggio via AJAX
   - Refresh anteprima

5. **assets/css/reports-review.css**
   - Stili modal full-screen
   - Layout editor con tab
   - Responsive design
   - Animazioni smooth

---

## 🎨 Interfaccia Editor

### Modal Layout

```
┌─────────────────────────────────────────────────┐
│  Modifica Contenuto Report               [X]   │
├─────────────────────────────────────────────────┤
│  [Editor Visuale] [HTML] [Anteprima]          │
├─────────────────────────────────────────────────┤
│                                                 │
│  ┌───────────────────────────────────────────┐ │
│  │                                           │ │
│  │     EDITOR CONTENT AREA                   │ │
│  │                                           │ │
│  │     (TinyMCE / HTML / Preview)           │ │
│  │                                           │ │
│  │                                           │ │
│  └───────────────────────────────────────────┘ │
│                                                 │
├─────────────────────────────────────────────────┤
│             [Annulla] [Salva e Rigenera PDF]   │
└─────────────────────────────────────────────────┘
```

### Tab Editor

#### 1. **Editor Visuale** 
- Editor WYSIWYG basato su TinyMCE
- Toolbar completo: grassetto, corsivo, allineamento, liste, colori, link
- Ideale per modifiche semplici e veloci
- Preview in tempo reale

#### 2. **Editor HTML**
- Textarea monospaziato per codice HTML
- Syntax highlighting visivo
- Controllo completo del markup
- Ideale per modifiche avanzate

#### 3. **Anteprima**
- Rendering live dell'HTML
- Pulsante "Aggiorna Anteprima" per refresh
- Visualizza esattamente come apparirà nel PDF
- Include styling e formattazione

---

## 💻 Utilizzo

### Da Interfaccia Admin

1. **Vai in** FP Suite → Reports
2. **Trova** il report da modificare
3. **Clicca** sull'icona "Modifica Contenuto" (</>) nella colonna Azioni
4. **Modifica** il contenuto nell'editor che preferisci:
   - **Visuale** per modifiche rapide
   - **HTML** per controllo completo
5. **Clicca** "Aggiorna Anteprima" per vedere il risultato (opzionale)
6. **Salva** con "Salva e Rigenera PDF"
7. Il PDF viene automaticamente ricreato con le modifiche

### Via Codice PHP

```php
use FP\DMS\Domain\Repos\ReportsRepo;

$repo = new ReportsRepo();
$report = $repo->find(123);

// Ottieni HTML attuale
$currentHtml = $report->meta['html_content'] ?? '';

// Modifica HTML
$newHtml = str_replace('Vecchio Testo', 'Nuovo Testo', $currentHtml);

// Salva modifiche
$meta = $report->meta;
$meta['html_content'] = $newHtml;
$meta['last_edited_at'] = current_time('mysql');
$meta['last_edited_by'] = get_current_user_id();

$repo->update($report->id, [
    'meta' => $meta
]);

// Rigenera PDF
$upload = wp_upload_dir();
$pdfPath = trailingslashit($upload['basedir']) . ltrim($report->storagePath, '/');

$pdfRenderer = new \FP\DMS\Infra\PdfRenderer();
$pdfRenderer->render($newHtml, $pdfPath);
```

### Via AJAX

```javascript
// Carica HTML del report
jQuery.post(ajaxurl, {
    action: 'fpdms_load_report_html',
    nonce: fpdmsReports.nonce,
    report_id: 123
}, function(response) {
    if (response.success) {
        console.log('HTML:', response.data.html);
    }
});

// Salva HTML modificato
jQuery.post(ajaxurl, {
    action: 'fpdms_save_report_html',
    nonce: fpdmsReports.nonce,
    report_id: 123,
    html_content: '<h1>Nuovo contenuto</h1>'
}, function(response) {
    if (response.success) {
        console.log('PDF rigenerato:', response.data.pdf_url);
    }
});
```

---

## 📊 Dati Salvati

### Campo `meta` del Report

```json
{
    "html_content": "<html>...contenuto completo...</html>",
    "template_id": 5,
    "last_edited_at": "2025-10-25 14:30:00",
    "last_edited_by": 1,
    "completed_at": "2025-10-25 10:00:00",
    "generated_at": "2025-10-25 10:00:00",
    ...altri meta...
}
```

### Tracking Modifiche

| Campo | Descrizione |
|-------|-------------|
| `last_edited_at` | Data/ora ultima modifica contenuto |
| `last_edited_by` | ID utente che ha modificato |
| `html_content` | HTML completo del report |
| `template_id` | ID template usato per generazione originale |

---

## 🎯 Casi d'Uso

### 1. Correzione Errori di Battitura

**Scenario:** Un valore nel report è stato scritto male

**Soluzione:**
1. Apri editor
2. Usa "Editor Visuale"
3. Trova e correggi il testo
4. Salva

**Tempo:** < 1 minuto

---

### 2. Aggiungere Note Personalizzate

**Scenario:** Vuoi aggiungere un commento specifico per il cliente

**Soluzione:**
1. Apri editor
2. Vai alla sezione desiderata nell'editor visuale
3. Aggiungi il testo/note
4. Formatta con grassetto/colori se necessario
5. Salva

**Tempo:** 2-3 minuti

---

### 3. Modificare Layout/Design

**Scenario:** Vuoi cambiare la struttura HTML di una sezione

**Soluzione:**
1. Apri editor
2. Passa a tab "HTML"
3. Modifica il codice HTML direttamente
4. Passa a "Anteprima" per verificare
5. Salva

**Tempo:** 5-10 minuti

---

### 4. Rimuovere Sezioni

**Scenario:** Una sezione del report non è più rilevante

**Soluzione:**
1. Apri editor
2. Tab "HTML"
3. Trova e rimuovi il blocco HTML
4. Verifica in anteprima
5. Salva

**Tempo:** 2-5 minuti

---

### 5. Brand Personalizzazione

**Scenario:** Vuoi aggiungere loghi/colori specifici del cliente

**Soluzione:**
1. Apri editor HTML
2. Aggiungi/modifica tag `<img>` per loghi
3. Modifica attributi `style` per colori
4. Anteprima
5. Salva

**Tempo:** 5-10 minuti

---

## 🔧 Personalizzazione

### Limitare Accesso

```php
// Solo administrator può modificare
add_filter('fpdms_can_edit_report', function($can_edit, $report_id) {
    return current_user_can('administrator');
}, 10, 2);
```

### Validazione HTML Custom

```php
// Hook prima del salvataggio
add_filter('fpdms_validate_html_content', function($html) {
    // Rimuovi script non sicuri
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    
    // Forza encoding UTF-8
    $html = mb_convert_encoding($html, 'UTF-8', 'auto');
    
    return $html;
});
```

### Backup Prima delle Modifiche

```php
// Salva versione precedente
add_action('fpdms_before_save_report_html', function($report_id, $new_html) {
    $repo = new \FP\DMS\Domain\Repos\ReportsRepo();
    $report = $repo->find($report_id);
    
    $meta = $report->meta;
    $meta['html_backup'] = $meta['html_content'] ?? '';
    $meta['backup_created_at'] = current_time('mysql');
    
    $repo->update($report_id, ['meta' => $meta]);
}, 10, 2);
```

---

## 🛡️ Sicurezza

### Protezioni Implementate

1. **Capability Check**
   - Solo utenti con `manage_options`
   
2. **Nonce Verification**
   - Ogni richiesta AJAX verificata
   
3. **HTML Sanitization**
   - L'HTML non viene sanitizzato per permettere HTML completo
   - ⚠️ **IMPORTANTE**: Permetti solo a utenti fidati
   
4. **File System Protection**
   - Path PDF validato prima della scrittura
   - Impedisce path traversal

### Best Practices

```php
// Raccomandato: Aggiungi log delle modifiche
add_action('fpdms_after_save_report_html', function($report_id, $user_id) {
    error_log(sprintf(
        '[FPDMS] Report %d edited by user %d at %s',
        $report_id,
        $user_id,
        current_time('mysql')
    ));
}, 10, 2);
```

---

## 🐛 Troubleshooting

### Pulsante "Modifica Contenuto" Non Appare

**Causa:** Il report non ha `html_content` salvato

**Soluzione:** 
- Il campo viene salvato solo per report generati DOPO l'installazione
- Per report vecchi, rigenerarli

**Codice per rigenerare:**
```php
// Forza rigenerazione di un report
$job = $reportsRepo->find($reportId);
// Ri-esegui generation logic
```

---

### TinyMCE Non Carica

**Causa:** Conflitti con altri plugin

**Soluzione:**
```php
// Disabilita TinyMCE di altri plugin nella pagina reports
add_filter('wp_editor_settings', function($settings) {
    if (isset($_GET['page']) && $_GET['page'] === 'fp-dms-reports') {
        $settings['tinymce'] = true;
    }
    return $settings;
});
```

---

### PDF Non Si Rigenera

**Causa:** Permessi file system

**Soluzione:**
```bash
# Verifica permessi directory uploads
chmod 755 /path/to/wp-content/uploads/fpdms
chmod 644 /path/to/wp-content/uploads/fpdms/**/*.pdf
```

---

### Modifiche HTML Si Perdono

**Causa:** Cache browser o object cache

**Soluzione:**
```php
// Disabilita cache per meta report
add_filter('fpdms_cache_report_meta', '__return_false');
```

---

## 📈 Performance

### Ottimizzazioni

1. **HTML Compression**
```php
// Comprimi HTML prima del salvataggio
add_filter('fpdms_before_save_html', function($html) {
    // Rimuovi whitespace extra
    $html = preg_replace('/\s+/', ' ', $html);
    return $html;
});
```

2. **Lazy Loading Editor**
- TinyMCE viene inizializzato solo all'apertura del modal
- Riduce carico pagina iniziale

3. **AJAX Chunking per HTML Grandi**
```javascript
// Per HTML > 1MB, considera chunking
if (htmlContent.length > 1000000) {
    // Invia in chunk da 500KB
}
```

---

## 🔄 Migrazione Dati

### Aggiungere HTML a Report Esistenti

```php
/**
 * Script una-tantum per popolare html_content su report vecchi
 */
function fpdms_backfill_html_content() {
    $repo = new \FP\DMS\Domain\Repos\ReportsRepo();
    $reports = $repo->search(['status' => 'success']);
    
    foreach ($reports as $report) {
        // Skip se già presente
        if (!empty($report->meta['html_content'])) {
            continue;
        }
        
        // Rigenerazip HTML dal template e dati
        // (richiede context reconstruction)
        $meta = $report->meta;
        $meta['html_content'] = '[HTML rigenerato]'; // Placeholder
        
        $repo->update($report->id, ['meta' => $meta]);
    }
}

// Run once
// fpdms_backfill_html_content();
```

---

## 📚 FAQ

### Posso modificare report già inviati ai clienti?

**Sì**, ma il PDF originale inviato non cambia. Solo il PDF sul server viene rigenerato.

---

### Le modifiche influenzano il template originale?

**No**, il template rimane intatto. Le modifiche sono specifiche per quel report.

---

### Posso annullare le modifiche?

**Sì**, se implementi il sistema di backup (vedi Personalizzazione).

---

### Supporta immagini caricate?

**Non direttamente** nell'editor, ma puoi:
1. Carica immagine nella libreria media WP
2. Copia URL
3. Inserisci nell'editor HTML come `<img src="URL">`

---

### Quanti MB può gestire l'editor?

L'editor gestisce bene HTML fino a **2-3MB**. Per HTML più grandi, considera split in sezioni.

---

## 🎓 Tutorial Video (futuro)

1. **Introduzione** - Panoramica funzionalità (5 min)
2. **Editor Visuale** - Modifiche rapide (10 min)
3. **Editor HTML** - Controllo avanzato (15 min)
4. **Casi d'Uso Reali** - Esempi pratici (20 min)

---

## 📞 Supporto

Per problemi o domande:

- **Email**: info@francescopasseri.com
- **GitHub**: Apri issue nel repository
- **Docs**: Controlla `/docs` per guide aggiuntive

---

## 🎉 Prossimi Miglioramenti

- [ ] **Version History** - Storico modifiche con rollback
- [ ] **Collaborative Editing** - Più utenti simultanei
- [ ] **Template Library** - Blocchi HTML predefiniti
- [ ] **AI Suggestions** - Suggerimenti AI per miglioramenti
- [ ] **Export/Import** - Esporta HTML modificato
- [ ] **Diff Viewer** - Visualizza differenze tra versioni

---

**Versione**: 1.0.0  
**Data**: 25 Ottobre 2025  
**Autore**: Francesco Passeri

---

## ✅ Quick Start Checklist

- [ ] Plugin aggiornato e riattivato
- [ ] Genera un nuovo report di test
- [ ] Verifica che appaia il pulsante "Modifica Contenuto"
- [ ] Apri l'editor
- [ ] Testa modifica in editor visuale
- [ ] Passa a tab HTML
- [ ] Verifica anteprima
- [ ] Salva e verifica PDF rigenerato
- [ ] Scarica PDF e controlla modifiche applicate

**Tempo totale**: ~10 minuti

**Tutto pronto!** 🚀


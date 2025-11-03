# 🎨 Guida Componenti UX

Documentazione dei componenti UI riutilizzabili per il plugin FP Digital Marketing Suite.

---

## 📦 Componenti Disponibili

### 1. **Toast Notifications** (JavaScript)
### 2. **Breadcrumbs** (PHP)
### 3. **Help Icons** (PHP)
### 4. **Progress Indicators** (PHP)
### 5. **Empty States** (PHP)
### 6. **KPI Tooltips** (JavaScript)

---

## 1️⃣ Toast Notifications

### 📄 File: `assets/js/toast.js`

Sistema di notifiche non invasive con auto-dismiss e animazioni smooth.

### 🔧 Utilizzo (JavaScript):

```javascript
// Success toast
window.fpdmsToast.success('Cliente salvato con successo!', 4000);

// Error toast
window.fpdmsToast.error('Impossibile salvare il cliente', 5000);

// Warning toast
window.fpdmsToast.warning('Attenzione: alcuni dati mancano', 6000);

// Info toast
window.fpdmsToast.info('Caricamento completato', 3000);

// Toast personalizzato
window.fpdmsToast.show({
    message: 'Operazione completata',
    type: 'success', // success, error, warning, info
    duration: 4000, // ms (0 = no auto-dismiss)
    dismissible: true, // mostra X per chiudere
    icon: 'dashicons-yes-alt', // custom dashicon
    onClick: () => console.log('Toast clicked!')
});
```

### ✨ Features:
- ✅ 4 tipi: success, error, warning, info
- ✅ Auto-dismiss configurabile
- ✅ Icone dashicons
- ✅ Animazioni slide-in/out
- ✅ Stacking multipli toast
- ✅ Click handler
- ✅ Responsive
- ✅ WordPress admin bar aware

---

## 2️⃣ Breadcrumbs

### 📄 File: `src/Admin/Pages/Shared/Breadcrumbs.php`

Navigazione gerarchica per orientare l'utente.

### 🔧 Utilizzo (PHP):

```php
use FP\DMS\Admin\Pages\Shared\Breadcrumbs;

// Breadcrumb standard (pagine principali)
Breadcrumbs::render(Breadcrumbs::getStandardItems('clients'));

// Breadcrumb con livello extra
Breadcrumbs::render(Breadcrumbs::getStandardItems('clients', [
    ['label' => 'Modifica: Azienda XYZ']
]));

// Breadcrumb custom completo
Breadcrumbs::render([
    [
        'label' => 'Home',
        'url' => admin_url('admin.php?page=fp-dms-dashboard'),
        'icon' => 'dashicons-admin-home'
    ],
    [
        'label' => 'Clienti',
        'url' => admin_url('admin.php?page=fp-dms-clients')
    ],
    [
        'label' => 'Nuovo Cliente' // ultimo item senza URL (current page)
    ]
]);
```

### 📍 Pagine Standard:
- `dashboard`, `overview`, `clients`, `datasources`
- `schedules`, `reports`, `templates`, `settings`
- `anomalies`, `health`, `logs`

---

## 3️⃣ Help Icons

### 📄 File: `src/Admin/Pages/Shared/HelpIcon.php`

Icona "?" con tooltip per aiuto contestuale.

### 🔧 Utilizzo (PHP):

```php
use FP\DMS\Admin\Pages\Shared\HelpIcon;

// Help icon con testo predefinito
HelpIcon::render(HelpIcon::getCommonHelp('clients'));

// Help icon custom
HelpIcon::render([
    'text' => 'Questo campo è obbligatorio e deve contenere un email valida.',
    'link' => 'https://docs.francescopasseri.com/email-config',
    'position' => 'top' // top, bottom, left, right
]);

// Inline in un titolo
echo '<h2>Clienti ';
HelpIcon::render(HelpIcon::getCommonHelp('clients'));
echo '</h2>';
```

### 📚 Help Predefiniti:
- `clients` → Info clienti
- `datasources` → Info connessioni
- `templates` → Info template
- `schedules` → Info automazione
- `anomalies` → Info anomalie
- `ai_insights` → Info AI
- `overview` → Info dashboard

### ✨ Features:
- ✅ Tooltip dark theme
- ✅ Link "Scopri di più"
- ✅ 4 posizioni (top/bottom/left/right)
- ✅ Auto-sizing
- ✅ Responsive

---

## 4️⃣ Progress Indicators

### 📄 File: `src/Admin/Pages/Shared/ProgressIndicator.php`

Progress bar, spinner e step indicators per operazioni lunghe.

### 🔧 Utilizzo (PHP):

```php
use FP\DMS\Admin\Pages\Shared\ProgressIndicator;

// A. Progress Bar (determinata)
ProgressIndicator::renderBar([
    'percent' => 75,
    'label' => 'Download in corso...',
    'status' => 'progress', // progress, success, error
    'showPercent' => true
]);

// B. Spinner (indeterminata)
ProgressIndicator::renderSpinner([
    'label' => 'Caricamento dati...',
    'size' => 'medium', // small, medium, large
    'inline' => false
]);

// C. Step Indicator
ProgressIndicator::renderSteps([
    'current' => 2,
    'total' => 5,
    'labels' => [
        'Configurazione',
        'Connessione API',
        'Test Dati',
        'Validazione',
        'Completamento'
    ]
]);
```

### ✨ Features:
- ✅ Progress bar con shimmer effect
- ✅ 3 status: progress, success, error
- ✅ Spinner in 3 dimensioni
- ✅ Step indicator con checkmarks
- ✅ Animazioni smooth

---

## 5️⃣ Empty States

### 📄 File: `src/Admin/Pages/Shared/EmptyState.php`

Stati vuoti con design moderno e CTA chiare.

### 🔧 Utilizzo (PHP):

```php
use FP\DMS\Admin\Pages\Shared\EmptyState;

EmptyState::render([
    'icon' => 'dashicons-groups',
    'title' => __('Nessun cliente ancora', 'fp-dms'),
    'description' => __('Inizia aggiungendo il tuo primo cliente...', 'fp-dms'),
    'primaryAction' => [
        'label' => __('+ Aggiungi Cliente', 'fp-dms'),
        'url' => admin_url('admin.php?page=fp-dms-clients')
    ],
    'secondaryAction' => [
        'label' => __('📚 Guida', 'fp-dms'),
        'url' => 'https://docs.francescopasseri.com'
    ],
    'helpText' => __('Suggerimento: Puoi importare clienti via CSV', 'fp-dms')
]);
```

### ✨ Features:
- ✅ Icon grande con gradient e animazione float
- ✅ Titolo e descrizione chiari
- ✅ CTA primaria e secondaria
- ✅ Help text opzionale
- ✅ Responsive design

---

## 6️⃣ KPI Tooltips

### 📄 File: `assets/js/kpi-tooltips.js`

Tooltips informativi sulle KPI cards in Overview.

### ✨ Auto-inizializzazione:
Il sistema si inizializza automaticamente e aggiunge tooltips a tutte le KPI cards.

### 📊 Metriche Supportate:
- **GA4**: users, sessions, pageviews, events, new_users, total_users
- **GSC**: gsc_clicks, gsc_impressions, ctr, position
- **Google Ads**: google_clicks, google_impressions, google_cost, google_conversions
- **Meta Ads**: meta_clicks, meta_impressions, meta_cost, meta_conversions, meta_revenue
- **Generiche**: revenue, clicks, impressions, cost, conversions

### 📋 Tooltip Content:
Ogni tooltip mostra:
- **Titolo** metrica
- **Categoria** (GA4, GSC, Google Ads, etc)
- **Descrizione** dettagliata
- **Formula** di calcolo
- **Valore ottimale** (best practice)

### ✨ Features:
- ✅ Icona info (ℹ️) su hover
- ✅ Tooltip dark theme
- ✅ Auto-posizionamento
- ✅ Mobile friendly (click to show)
- ✅ Si ricarica dopo refresh dati

---

## 📊 Esempi Completi

### Pagina con Tutti i Componenti:

```php
use FP\DMS\Admin\Pages\Shared\Breadcrumbs;
use FP\DMS\Admin\Pages\Shared\HelpIcon;
use FP\DMS\Admin\Pages\Shared\EmptyState;
use FP\DMS\Admin\Pages\Shared\ProgressIndicator;

class MyCustomPage
{
    public static function render(): void
    {
        echo '<div class="wrap fpdms-admin-page">';
        
        // 1. Breadcrumbs
        Breadcrumbs::render([
            ['label' => 'FP Suite', 'url' => '...', 'icon' => 'dashicons-chart-area'],
            ['label' => 'La Mia Pagina']
        ]);
        
        // 2. Header con Help Icon
        echo '<div class="fpdms-page-header">';
        echo '<h1>';
        echo 'La Mia Pagina';
        HelpIcon::render([
            'text' => 'Questa pagina ti permette di...',
            'link' => 'https://docs.example.com'
        ]);
        echo '</h1>';
        echo '</div>';
        
        // 3. Empty State (se necessario)
        if (empty($items)) {
            EmptyState::render([
                'icon' => 'dashicons-admin-generic',
                'title' => 'Nessun dato',
                'description' => 'Inizia aggiungendo...',
                'primaryAction' => [
                    'label' => '+ Aggiungi',
                    'url' => '...'
                ]
            ]);
            echo '</div>';
            return;
        }
        
        // 4. Progress Indicator (se operazione lunga)
        ProgressIndicator::renderBar([
            'percent' => 65,
            'label' => 'Elaborazione...'
        ]);
        
        echo '</div>';
    }
}
```

### JavaScript con Toast:

```javascript
// Dopo salvataggio form
document.getElementById('save-button').addEventListener('click', async (e) => {
    e.preventDefault();
    
    try {
        const response = await fetch('/api/save', { method: 'POST' });
        const data = await response.json();
        
        if (data.success) {
            window.fpdmsToast.success('✅ Salvato con successo!');
        } else {
            window.fpdmsToast.error('❌ ' + data.message);
        }
    } catch (error) {
        window.fpdmsToast.error('Errore di connessione');
    }
});
```

---

## 🎨 Design System

### Colori:
```css
--fpdms-primary: #667eea
--fpdms-success: #10b981
--fpdms-error: #ef4444
--fpdms-warning: #f59e0b
--fpdms-info: #3b82f6
```

### Spacing:
```css
Gap cards: 20px
Padding card: 24px
Border radius: 12px
```

### Animations:
- **Toast**: slide-in from right
- **Empty State Icon**: floating animation
- **Progress Bar**: shimmer effect
- **Help Icon**: scale on hover

---

## ✅ Checklist Integrazione

Quando crei una nuova pagina admin:

- [ ] Aggiungi Breadcrumbs in alto
- [ ] Aggiungi Help Icon nel titolo sezione principale
- [ ] Usa EmptyState quando non ci sono dati
- [ ] Usa Toast per feedback azioni (save/delete)
- [ ] Usa Progress Indicator per operazioni lunghe
- [ ] Testa su mobile/tablet

---

## 🚀 Deployment

I componenti sono automaticamente caricati tramite:

```php
// Menu.php - enqueueGlobalAssets()
wp_enqueue_script('fpdms-toast', ...) // Toast system
wp_enqueue_script('fpdms-kpi-tooltips', ...) // KPI tooltips (solo Overview)
```

### Dipendenze:
- WordPress Dashicons
- CSS moderno inline (auto-iniettato)
- No external dependencies!

---

## 📱 Responsive

Tutti i componenti sono responsive:
- **Desktop**: Layout full con tutti i dettagli
- **Tablet**: Layout ottimizzato
- **Mobile**: Layout a colonna singola, font ridotti

---

## 🎯 Best Practices

### Toast:
- ✅ Usa per feedback immediato (save, delete, sync)
- ❌ Non usare per errori critici (usa notice permanente)
- ✅ Mantieni messaggi brevi (max 50 caratteri)
- ✅ Usa emoji per riconoscimento rapido (✅, ❌, ⚠️)

### Help Icons:
- ✅ Aggiungi accanto a concetti complessi
- ✅ Link sempre alla documentazione
- ❌ Non abusare (max 2-3 per pagina)

### Progress Indicators:
- ✅ Usa per operazioni >2 secondi
- ✅ Mostra percentuale quando possibile
- ✅ Aggiungi label descrittiva
- ❌ Non usare per operazioni <1 secondo

### Empty States:
- ✅ Spiega sempre il beneficio
- ✅ Fornisci CTA chiara
- ✅ Aggiungi link documentazione
- ✅ Usa help text per suggerimenti extra

---

## 🔧 Estendere i Componenti

### Aggiungere Help Predefinito:

```php
// In HelpIcon.php - getCommonHelp()
'my_section' => [
    'text' => __('La mia sezione serve a...', 'fp-dms'),
    'link' => 'https://docs.francescopasseri.com/my-section'
]
```

### Aggiungere Descrizione KPI:

```javascript
// In kpi-tooltips.js - metricDescriptions
'my_metric': {
    title: 'La Mia Metrica',
    description: 'Descrizione dettagliata...',
    formula: 'Formula di calcolo',
    goodValue: 'Valore ottimale da raggiungere',
    category: 'Custom'
}
```

---

## 🎓 Esempi Reali nel Plugin

### ClientsPage:
- ✅ Breadcrumbs: FP Suite / Clienti
- ✅ Help Icon nel titolo
- ✅ Empty State quando nessun cliente
- ✅ Toast su save/delete (TODO)

### Overview:
- ✅ Breadcrumbs: FP Suite / Overview
- ✅ Help Icon su sezioni (Overview, AI Insights)
- ✅ KPI Tooltips su tutte le metriche
- ✅ Progress bar su sync data sources
- ✅ Toast su sync completato

### DataSources:
- ✅ Breadcrumbs: FP Suite / Connessioni
- ✅ Help Icon nel titolo
- ✅ Empty State se nessun cliente
- ✅ Toast su test connessione (TODO)

---

## 🐛 Troubleshooting

### Toast non appare:
1. Verifica `toast.js` sia caricato: `console.log(window.fpdmsToast)`
2. Controlla console per errori JS
3. Verifica che sia in una pagina FP DMS

### Tooltip non mostra:
1. Verifica che la KPI card abbia `data-metric` attribute
2. Controlla che la metrica sia nel dizionario
3. Ricarica la pagina dopo modifica JS

### Progress bar non aggiorna:
1. Verifica gli ID degli elementi (`sync-progress-bar`)
2. Controlla che il clearInterval funzioni
3. Verifica timing degli update

---

## 📝 TODO Future

- [ ] Aggiungere suoni notifiche (opzionale)
- [ ] Toast con action button (Undo)
- [ ] Keyboard shortcuts (Dismiss con ESC)
- [ ] Toast queue con priorità
- [ ] Analytics tracking sui tooltip hover
- [ ] Dark mode support

---

**Autore**: Francesco Passeri  
**Versione**: 0.9.0  
**Ultimo aggiornamento**: 2025-10-26


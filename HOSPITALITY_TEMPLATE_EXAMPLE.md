# Esempio Pratico: Template Hotel con Generazione Contenuti

## Scenario

Un hotel di lusso vuole generare un report mensile con metriche specifiche per l'ospitalità, utilizzando i template specializzati implementati.

## 🏨 Configurazione Template

### 1. Selezione Template Automatica

Il sistema rileva automaticamente:
- **Settore:** hospitality
- **Tipo Business:** hotel
- **Template Suggeriti:**
  - `ga4_hotel` (GA4 - Hotel & Hospitality)
  - `meta_ads_hospitality` (Meta Ads - Hospitality)
  - `google_ads_hospitality` (Google Ads - Hospitality)

### 2. Blueprint Report Suggerito

Il sistema suggerisce automaticamente:
- **Blueprint:** `hotel` (Report Hotel)
- **Contenuto:** Template specializzato per hotel con placeholder dinamici

## 📊 Template di Contenuto

```html
<!DOCTYPE html>
<html>
<head>
    <title>Report Performance Hotel {{client.name}}</title>
    <style>
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
        .kpi { background: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center; }
        .kpi strong { font-size: 1.5rem; color: #007cba; }
        .section { margin: 2rem 0; }
        .alert { padding: 1rem; border-radius: 4px; margin: 1rem 0; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.warning { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <header>
        <h1>Report Performance Hotel {{client.name}}</h1>
        <p>Periodo: {{period.start}} - {{period.end}} ({{period.duration}} giorni)</p>
        <p>Generato il: {{report.generated_at}}</p>
    </header>

    <section class="section">
        <h2>Executive Summary</h2>
        <p>Analisi completa delle performance dell'hotel {{client.name}} per il periodo {{period.start}} - {{period.end}}. 
        Il report include metriche di occupazione, ricavi per dipartimento, analisi ospiti e performance marketing.</p>
    </section>

    <section class="section">
        <h2>Metriche Chiave</h2>
        <div class="kpi-grid">
            <div class="kpi">
                <span>Tasso di Occupazione</span>
                <strong>{{hospitality.occupancy_rate}}</strong>
            </div>
            <div class="kpi">
                <span>Ricavo per Camera</span>
                <strong>{{hospitality.revenue_per_room}}</strong>
            </div>
            <div class="kpi">
                <span>Durata Media Soggiorno</span>
                <strong>{{hospitality.average_stay_duration}}</strong>
            </div>
            <div class="kpi">
                <span>Soddisfazione Ospiti</span>
                <strong>{{hospitality.guest_satisfaction}}</strong>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Ricavi per Dipartimento</h2>
        <div class="kpi-grid">
            <div class="kpi">
                <span>Ricavi Camere</span>
                <strong>{{hotel.room_revenue}}</strong>
            </div>
            <div class="kpi">
                <span>Ricavi F&B</span>
                <strong>{{hotel.food_beverage_revenue}}</strong>
            </div>
            <div class="kpi">
                <span>Ricavi Conferenze</span>
                <strong>{{hotel.conference_revenue}}</strong>
            </div>
            <div class="kpi">
                <span>Ricavi Spa</span>
                <strong>{{hotel.spa_revenue}}</strong>
            </div>
        </div>
    </section>

    {{if hospitality.occupancy_rate > 80}}
    <div class="alert success">
        <h3>🎉 Eccellente Performance!</h3>
        <p>Il tasso di occupazione del {{hospitality.occupancy_rate}} supera l'obiettivo dell'80%. 
        Ottimo lavoro nel massimizzare l'utilizzo delle camere.</p>
    </div>
    {{endif}}

    {{if hospitality.occupancy_rate < 60}}
    <div class="alert warning">
        <h3>⚠️ Attenzione: Occupazione Bassa</h3>
        <p>Il tasso di occupazione del {{hospitality.occupancy_rate}} è sotto la soglia del 60%. 
        Considerare strategie di revenue management per migliorare le performance.</p>
    </div>
    {{endif}}

    <section class="section">
        <h2>Analisi Ospiti</h2>
        <div class="kpi-grid">
            <div class="kpi">
                <span>Viaggiatori Business</span>
                <strong>{{hotel.business_travelers}}</strong>
            </div>
            <div class="kpi">
                <span>Viaggiatori Leisure</span>
                <strong>{{hotel.leisure_travelers}}</strong>
            </div>
            <div class="kpi">
                <span>Prenotazioni Gruppi</span>
                <strong>{{hotel.group_bookings}}</strong>
            </div>
            <div class="kpi">
                <span>Ospiti di Ritorno</span>
                <strong>{{hotel.repeat_guests}}</strong>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Performance Marketing</h2>
        <div class="kpi-grid">
            <div class="kpi">
                <span>Prenotazioni Dirette</span>
                <strong>{{hospitality.direct_bookings}}</strong>
            </div>
            <div class="kpi">
                <span>Prenotazioni OTA</span>
                <strong>{{hospitality.ota_bookings}}</strong>
            </div>
            <div class="kpi">
                <span>Membri Loyalty</span>
                <strong>{{hotel.loyalty_members}}</strong>
            </div>
            <div class="kpi">
                <span>Ricavi Ancillari</span>
                <strong>{{hotel.ancillary_revenue}}</strong>
            </div>
        </div>
    </section>

    {{sections.trends|raw}}

    {{sections.gsc|raw}}

    {{sections.anomalies|raw}}

    <section class="section">
        <h2>Piano di Azione</h2>
        <ul>
            <li><strong>Massimizzare i ricavi dai servizi ancillari:</strong> 
                I ricavi ancillari di {{hotel.ancillary_revenue}} rappresentano un'opportunità di crescita. 
                Implementare strategie per aumentare le vendite di servizi aggiuntivi.</li>
            
            <li><strong>Sviluppare programmi di loyalty per ospiti business:</strong> 
                Con {{hotel.business_travelers}} viaggiatori business, creare programmi specifici 
                per aumentare la retention e la fedeltà.</li>
            
            <li><strong>Ottimizzare la gestione dei gruppi e conferenze:</strong> 
                Le {{hotel.group_bookings}} prenotazioni gruppi offrono potenziale per 
                pacchetti premium e ricavi garantiti.</li>
        </ul>
    </section>

    <footer>
        <p>Report generato automaticamente da FP Digital Marketing Suite</p>
        <p>Per domande o supporto, contattare il team di marketing</p>
    </footer>
</body>
</html>
```

## 🔄 Processo di Generazione

### 1. Rilevamento Settore

```php
$context = [
    'business_type' => 'hotel',
    'industry' => 'hospitality',
    'keywords' => ['hotel', 'luxury', 'hospitality']
];

$suggestions = TemplateSuggestionEngine::suggestTemplates($context);
// Risultato: ['ga4_hotel', 'meta_ads_hospitality', 'google_ads_hospitality']
```

### 2. Selezione Blueprint

```php
$blueprintSuggestions = TemplateSuggestionEngine::suggestReportBlueprints($context);
// Risultato: ['hotel']

$blueprint = TemplateBlueprints::find('hotel');
```

### 3. Raccolta Dati

```php
$data = [
    'ga4' => [
        'activeUsers' => 15420,
        'sessions' => 18950,
        'conversions' => 1250,
        'pageViews' => 45680,
        'averageSessionDuration' => 180,
        'engagementRate' => 0.65
    ],
    'hospitality' => [
        'occupancy_rate' => 85.5,
        'revenue_per_room' => 245.80,
        'average_stay_duration' => 2.3,
        'guest_satisfaction' => 8.7,
        'direct_bookings' => 850,
        'ota_bookings' => 400
    ],
    'hotel' => [
        'room_revenue' => 125000.00,
        'food_beverage_revenue' => 45000.00,
        'conference_revenue' => 15000.00,
        'spa_revenue' => 8000.00,
        'business_travelers' => 650,
        'leisure_travelers' => 600,
        'group_bookings' => 45,
        'loyalty_members' => 320,
        'repeat_guests' => 280,
        'ancillary_revenue' => 12000.00
    ]
];
```

### 4. Generazione Contenuto

```php
$contentEngine = new ContentGenerationEngine();
$generatedContent = $contentEngine->generateContent(
    $template->content,
    $context,
    'hotel'
);
```

## 📈 Risultato Finale

### Contenuto Generato

```html
<h1>Report Performance Hotel Hotel Excelsior</h1>
<p>Periodo: 01/01/2025 - 31/01/2025 (30 giorni)</p>

<div class="kpi-grid">
    <div class="kpi">
        <span>Tasso di Occupazione</span>
        <strong>85.5%</strong>
    </div>
    <div class="kpi">
        <span>Ricavo per Camera</span>
        <strong>€245.80</strong>
    </div>
    <!-- ... altri KPI ... -->
</div>

<div class="alert success">
    <h3>🎉 Eccellente Performance!</h3>
    <p>Il tasso di occupazione del 85.5% supera l'obiettivo dell'80%. 
    Ottimo lavoro nel massimizzare l'utilizzo delle camere.</p>
</div>

<!-- Sezioni dinamiche generate automaticamente -->
<div class="trends-section">
    <div class="trend-item">
        <h4>Tasso di Occupazione</h4>
        <p>Trend positivo (+5.2%)</p>
    </div>
    <!-- ... altri trend ... -->
</div>
```

## 🎯 Benefici del Sistema

### 1. **Automatizzazione Completa**
- Rilevamento automatico del settore
- Selezione automatica dei template
- Generazione automatica dei contenuti

### 2. **Personalizzazione per Settore**
- Metriche specifiche per hospitality
- Placeholder dinamici rilevanti
- Analisi specializzate

### 3. **Contenuti Intelligenti**
- Condizioni dinamiche ({{if}})
- Loop e iterazioni ({{foreach}})
- Sezioni automatiche (trends, anomalie)

### 4. **Scalabilità**
- Facile aggiunta di nuovi settori
- Template riutilizzabili
- Placeholder estendibili

## 🚀 Prossimi Passi

### Per l'Hotel
1. **Configurare** i template suggeriti
2. **Personalizzare** i placeholder se necessario
3. **Automatizzare** la generazione mensile
4. **Monitorare** le performance

### Per il Sistema
1. **Aggiungere** più settori verticali
2. **Implementare** AI per suggerimenti avanzati
3. **Creare** dashboard in tempo reale
4. **Integrare** con sistemi PMS

---

Questo esempio dimostra come il sistema generi automaticamente report specializzati e professionali per il settore hospitality, con contenuti dinamici e personalizzati per ogni hotel.

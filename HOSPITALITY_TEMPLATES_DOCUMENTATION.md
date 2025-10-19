# Template Specializzati per Settori Hospitality

## Panoramica

Questo documento descrive i template specializzati implementati per i settori hospitality, wine e B&B nel sistema FP Digital Marketing Suite. I template includono generazione di contenuti dinamici con placeholder specifici per settore.

## 🏨 Template Hospitality

### Template di Connessione

#### GA4 - Hotel & Hospitality
- **ID:** `ga4_hotel`
- **Provider:** GA4
- **Categoria:** hospitality
- **Difficoltà:** intermediate
- **Metriche:**
  - activeUsers, sessions, conversions
  - averageSessionDuration, pageViews
  - engagementRate, formSubmissions
  - scrollDepth, timeOnPage, eventCount
- **Dimensioni:** date, source, medium, pagePath, deviceCategory, country

#### GA4 - Resort & Luxury
- **ID:** `ga4_resort`
- **Provider:** GA4
- **Categoria:** hospitality
- **Difficoltà:** advanced
- **Metriche aggiuntive:**
  - userEngagementDuration
- **Dimensioni aggiuntive:** city

#### Meta Ads - Hospitality
- **ID:** `meta_ads_hospitality`
- **Provider:** Meta Ads
- **Metriche:** impressions, clicks, spend, conversions, cpc, cpm, ctr, cost_per_conversion, roas, reach, frequency

#### Google Ads - Hospitality
- **ID:** `google_ads_hospitality`
- **Provider:** Google Ads
- **Metriche:** impressions, clicks, cost, conversions, ctr, average_cpc, conversion_rate, cost_per_conversion, quality_score

### Blueprint di Report

#### Report Hospitality
- **ID:** `hospitality`
- **Sezioni:**
  - Performance Ricettivo
  - Metriche Chiave (occupancy_rate, revenue_per_room, average_stay_duration, guest_satisfaction)
  - Analisi Prenotazioni
  - Performance Stagionale
  - Raccomandazioni Strategiche

#### Report Hotel
- **ID:** `hotel`
- **Sezioni:**
  - Performance Hotel
  - Ricavi per Dipartimento (room_revenue, food_beverage_revenue, conference_revenue, spa_revenue)
  - Analisi Ospiti
  - Performance Marketing
  - Piano di Azione

#### Report Resort
- **ID:** `resort`
- **Sezioni:**
  - Performance Resort
  - Metriche Resort (villa_occupancy, activity_revenue, wedding_revenue, golf_revenue)
  - Analisi Pacchetti
  - Utilizzo Servizi
  - Strategia Resort

## 🍷 Template Wine Industry

### Template di Connessione

#### GA4 - Aziende di Vino
- **ID:** `ga4_wine_estate`
- **Provider:** GA4
- **Categoria:** wine
- **Difficoltà:** intermediate
- **Metriche aggiuntive:**
  - goalCompletions
- **Consigliato per:** Cantine, Vigneti, Aziende Vinicole, Wine Tourism

#### Meta Ads - Wine Tourism
- **ID:** `meta_ads_wine_tourism`
- **Provider:** Meta Ads
- **Metriche aggiuntive:**
  - video_views, video_view_rate
- **Consigliato per:** Cantine, Wine Tourism, Eventi Enogastronomici

#### Google Ads - Wine Tourism
- **ID:** `google_ads_wine_tourism`
- **Provider:** Google Ads
- **Consigliato per:** Cantine, Wine Tourism, Eventi Enogastronomici

### Blueprint di Report

#### Report Aziende di Vino
- **ID:** `wine`
- **Sezioni:**
  - Performance Azienda Vinicola
  - Ricavi per Canale (cellar_sales, tasting_revenue, restaurant_revenue, wedding_venue_revenue)
  - Wine Tourism
  - Club Vino e Fedeltà
  - Strategia Enoturistica

## 🏡 Template B&B

### Template di Connessione

#### GA4 - B&B & Agriturismi
- **ID:** `ga4_bnb`
- **Provider:** GA4
- **Categoria:** bnb
- **Difficoltà:** beginner
- **Consigliato per:** B&B, Agriturismi, Case Vacanza, Turismo Rurale

#### Meta Ads - B&B & Agriturismi
- **ID:** `meta_ads_bnb`
- **Provider:** Meta Ads
- **Categoria:** bnb
- **Difficoltà:** beginner

### Blueprint di Report

#### Report B&B
- **ID:** `bnb`
- **Sezioni:**
  - Performance B&B
  - Metriche B&B (room_occupancy, breakfast_revenue, local_experiences, weekend_bookings)
  - Esperienze Autentiche
  - Turismo Sostenibile
  - Strategia B&B

## 🔧 Placeholder Dinamici

### Hospitality Placeholders

```php
{{hospitality.occupancy_rate}}           // Tasso di occupazione
{{hospitality.revenue_per_room}}         // Ricavo per camera
{{hospitality.average_stay_duration}}    // Durata media soggiorno
{{hospitality.guest_satisfaction}}       // Soddisfazione ospiti
{{hospitality.direct_bookings}}          // Prenotazioni dirette
{{hospitality.ota_bookings}}             // Prenotazioni OTA
{{hospitality.seasonal_performance}}     // Performance stagionale
{{hospitality.amenities_usage}}          // Utilizzo servizi
```

### Hotel Placeholders

```php
{{hotel.room_revenue}}                   // Ricavi camere
{{hotel.food_beverage_revenue}}          // Ricavi F&B
{{hotel.conference_revenue}}             // Ricavi conferenze
{{hotel.spa_revenue}}                    // Ricavi spa
{{hotel.business_travelers}}             // Viaggiatori business
{{hotel.leisure_travelers}}              // Viaggiatori leisure
{{hotel.group_bookings}}                 // Prenotazioni gruppi
{{hotel.loyalty_members}}                // Membri loyalty
{{hotel.repeat_guests}}                  // Ospiti di ritorno
{{hotel.ancillary_revenue}}              // Ricavi ancillari
```

### Resort Placeholders

```php
{{resort.villa_occupancy}}               // Occupazione ville
{{resort.activity_revenue}}              // Ricavi attività
{{resort.wedding_revenue}}               // Ricavi matrimoni
{{resort.golf_revenue}}                  // Ricavi golf
{{resort.beach_usage}}                   // Utilizzo spiaggia
{{resort.spa_treatments}}                // Trattamenti spa
{{resort.family_packages}}               // Pacchetti family
{{resort.honeymoon_packages}}            // Pacchetti honeymoon
{{resort.all_inclusive_revenue}}         // Ricavi all-inclusive
{{resort.excursion_bookings}}            // Prenotazioni escursioni
```

### Wine Industry Placeholders

```php
{{wine.cellar_sales}}                    // Vendite cantina
{{wine.tasting_revenue}}                 // Ricavi degustazioni
{{wine.wine_club_members}}               // Membri wine club
{{wine.vineyard_tours}}                  // Tour vigneti
{{wine.restaurant_revenue}}              // Ricavi ristorante
{{wine.wedding_venue_revenue}}           // Ricavi location matrimoni
{{wine.retail_sales}}                    // Vendite retail
{{wine.wholesale_sales}}                 // Vendite wholesale
{{wine.export_sales}}                    // Vendite export
{{wine.seasonal_visitors}}               // Visitatori stagionali
{{wine.wine_education_classes}}          // Corsi educativi
{{wine.corporate_events}}                // Eventi corporate
```

### B&B Placeholders

```php
{{bnb.room_occupancy}}                   // Occupazione camere
{{bnb.breakfast_revenue}}                // Ricavi colazione
{{bnb.local_experiences}}                // Esperienze locali
{{bnb.weekend_bookings}}                 // Prenotazioni weekend
{{bnb.romantic_packages}}                // Pacchetti romantici
{{bnb.cultural_tours}}                   // Tour culturali
{{bnb.local_recommendations}}            // Raccomandazioni locali
{{bnb.sustainable_tourism}}              // Turismo sostenibile
{{bnb.homestay_experience}}              // Esperienza homestay
{{bnb.local_partnerships}}               // Partnership locali
```

## 🎯 Suggerimenti Intelligenti

### Rilevamento Automatico

Il sistema rileva automaticamente il tipo di business basandosi su:

#### Hospitality
- Keywords: hotel, resort, hospitality
- Business type: hospitality
- Industry: hospitality

#### Wine Industry
- Keywords: wine, vineyard, winery, cantine
- Business type: wine
- Industry: wine

#### B&B
- Keywords: bnb, bed and breakfast, agriturismo, rural
- Business type: bnb
- Industry: bnb

### Template Suggeriti

Per ogni settore, il sistema suggerisce automaticamente:

1. **Template di connessione** appropriati
2. **Blueprint di report** specializzati
3. **Metriche specifiche** per il settore
4. **Placeholder dinamici** rilevanti

## 📊 Generazione Contenuti

### Motore di Generazione

Il `ContentGenerationEngine` processa:

1. **Placeholder specifici per settore**
2. **Contenuti condizionali** ({{if condition}}...{{endif}})
3. **Loop e iterazioni** ({{foreach array}}...{{endforeach}})
4. **Placeholder generali** ({{client.name}}, {{period.start}}, etc.)

### Esempio di Template

```html
<h2>Performance {{client.name}}</h2>
<p>Report per il periodo {{period.start}} - {{period.end}}</p>

<div class="kpi-section">
    <h3>Metriche Chiave</h3>
    <div class="kpi-grid">
        <div class="kpi">
            <span>Tasso di Occupazione</span>
            <strong>{{hospitality.occupancy_rate}}</strong>
        </div>
        <div class="kpi">
            <span>Ricavo per Camera</span>
            <strong>{{hospitality.revenue_per_room}}</strong>
        </div>
    </div>
</div>

{{if hospitality.occupancy_rate > 80}}
<div class="alert success">
    <p>Eccellente performance di occupazione!</p>
</div>
{{endif}}

{{foreach top_pages}}
<div class="page-item">
    <h4>{{item.page}}</h4>
    <p>Visualizzazioni: {{item.views}}</p>
</div>
{{endforeach}}
```

## 🚀 Utilizzo

### Per gli Utenti

1. **Selezione Automatica:** Il sistema rileva automaticamente il settore
2. **Template Suggeriti:** Mostra template appropriati per il settore
3. **Contenuti Dinamici:** I report si generano automaticamente con dati specifici
4. **Personalizzazione:** Possibilità di modificare template e placeholder

### Per gli Sviluppatori

1. **Aggiunta Template:** Estendi `ConnectionTemplate::getTemplates()`
2. **Nuovi Placeholder:** Aggiungi a `ContentGenerationEngine`
3. **Blueprint Personalizzati:** Crea nuovi blueprint in `TemplateBlueprints`
4. **Suggerimenti:** Estendi `TemplateSuggestionEngine`

## 📈 Benefici

### Per Hotel e Resort
- Metriche specifiche per hospitality
- Analisi occupazione e ricavi
- Performance stagionali
- Gestione canali di prenotazione

### Per Aziende di Vino
- Metriche wine tourism
- Analisi vendite per canale
- Performance wine club
- Eventi e degustazioni

### Per B&B
- Metriche turismo rurale
- Esperienze autentiche
- Performance weekend
- Partnership locali

## 🔧 Configurazione

### Attivazione Template

I template sono automaticamente disponibili quando:
1. Il settore viene rilevato
2. I dati appropriati sono disponibili
3. I placeholder sono configurati

### Personalizzazione

Ogni template può essere:
- Modificato per metriche specifiche
- Personalizzato per il brand
- Adattato per esigenze particolari
- Esteso con nuovi placeholder

---

**Data di Implementazione:** Gennaio 2025  
**Versione:** 1.0  
**Settori Supportati:** Hospitality, Wine, B&B

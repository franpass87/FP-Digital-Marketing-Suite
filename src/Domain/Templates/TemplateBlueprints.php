<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Templates;

use function esc_html__;

final class TemplateBlueprints
{
    /** @var array<string,TemplateBlueprint>|null */
    private static ?array $cache = null;

    /**
     * @return array<string,TemplateBlueprint>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $balanced = TemplateBuilder::make()
            ->addSection(
                'Sommario Esecutivo',
                '<p>Utilizza questo spazio per riassumere i successi, le sfide e il contesto del periodo di reporting.</p>'
            )
            ->addRawSection('{{sections.kpi|raw}}')
            ->addSection(
                'Punti Chiave',
                '<ul><li>Evidenzia le attività di marketing che hanno mosso i numeri.</li><li>Metti in luce le opportunità di ottimizzazione per il prossimo ciclo.</li></ul>'
            )
            ->addRawSection('{{sections.trends|raw}}')
            ->addRawSection('{{sections.gsc|raw}}')
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Prossimi Passi',
                '<p>Delinea le azioni che intraprenderai per sostenere la crescita o risolvere i colli di bottiglia.</p>'
            )
            ->build();

        $kpiFocused = TemplateBuilder::make()
            ->addSection(
                'Snapshot delle Performance',
                '<p>Riassumi in un paragrafo come ha performato l\'account durante questo periodo.</p>'
            )
            ->addKpiSection('Panoramica KPI', [
                ['label' => 'Utenti', 'value' => '{{kpi.ga4.users|number}}'],
                ['label' => 'Sessioni', 'value' => '{{kpi.ga4.sessions|number}}'],
                ['label' => 'Clic', 'value' => '{{kpi.google_ads.clicks|number}}'],
                ['label' => 'Conversioni', 'value' => '{{kpi.google_ads.conversions|number}}'],
                ['label' => 'Costo', 'value' => '{{kpi.google_ads.cost|number}}'],
            ])
            ->addSection(
                'Insights e Commenti',
                '<p>Spiega cosa ha causato le variazioni principali e come si allineano con i tuoi obiettivi.</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Piano d\'Azione',
                '<ul><li>Elenca le ottimizzazioni che affronterai prossimamente.</li></ul>'
            )
            ->build();

        $searchBlueprint = TemplateBuilder::make()
            ->addSection(
                'Panoramica Performance Organica',
                '<p>Inquadra la visibilità di ricerca organica raggiunta nella finestra selezionata.</p>'
            )
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                'Opportunità',
                '<ul><li>Identifica cluster di parole chiave o pagine che meritano attenzione extra.</li><li>Descrivi esperimenti o contenuti che lancerai.</li></ul>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->build();

        // E-commerce focused blueprint
        $ecommerceBlueprint = TemplateBuilder::make()
            ->addSection(
                'Sommario Esecutivo',
                '<p>Panoramica delle performance e-commerce inclusi ricavi, conversioni e trend chiave.</p>'
            )
            ->addKpiSection('Ricavi & Vendite', [
                ['label' => 'Ricavo Totale', 'value' => '{{kpi.ga4.totalRevenue|currency}}'],
                ['label' => 'Transazioni', 'value' => '{{kpi.ga4.transactions|number}}'],
                ['label' => 'Valore Medio Ordine', 'value' => '{{kpi.ga4.averageOrderValue|currency}}'],
                ['label' => 'Tasso di Conversione', 'value' => '{{kpi.ga4.conversionRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                'Performance Prodotti',
                '<p>Analisi dei prodotti e categorie più performanti.</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Analisi Canali Marketing',
                '<p>Ripartizione delle performance per sorgenti di traffico e campagne.</p>'
            )
            ->addSection(
                'Prossimi Passi e Raccomandazioni',
                '<ul><li>Ottimizza le categorie di prodotti sottoperformanti</li><li>Scala i canali marketing di successo</li></ul>'
            )
            ->build();

        // SaaS/Software focused blueprint
        $saasBlueprint = TemplateBuilder::make()
            ->addSection(
                'Panoramica Performance Prodotto',
                '<p>Metriche chiave per adozione software, engagement e retention utenti.</p>'
            )
            ->addKpiSection('Engagement Utenti', [
                ['label' => 'Utenti Attivi', 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => 'Durata Sessione', 'value' => '{{kpi.ga4.averageSessionDuration|duration}}'],
                ['label' => 'Utilizzo Funzionalità', 'value' => '{{kpi.ga4.eventCount|number}}'],
                ['label' => 'Tasso di Retention', 'value' => '{{kpi.ga4.retentionRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                'Analisi User Journey',
                '<p>Insights sul comportamento degli utenti e pattern di adozione delle funzionalità.</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Insights Sviluppo Prodotto',
                '<ul><li>Identifica le funzionalità che generano maggior engagement</li><li>Risolvi i colli di bottiglia nell\'esperienza utente</li></ul>'
            )
            ->build();

        // Healthcare focused blueprint
        $healthcareBlueprint = TemplateBuilder::make()
            ->addSection(
                'Sommario Engagement Pazienti',
                '<p>Panoramica del traffico sito web, richieste pazienti e engagement servizi.</p>'
            )
            ->addKpiSection('Metriche Pazienti', [
                ['label' => 'Visitatori Sito Web', 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => 'Visualizzazioni Pagina', 'value' => '{{kpi.ga4.pageViews|number}}'],
                ['label' => 'Form di Contatto', 'value' => '{{kpi.ga4.formSubmissions|number}}'],
                ['label' => 'Tasso di Engagement', 'value' => '{{kpi.ga4.engagementRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                'Performance Pagine Servizi',
                '<p>Analisi di quali servizi e pagine sono più popolari tra i pazienti.</p>'
            )
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                'Insights Comunicazione Pazienti',
                '<ul><li>Ottimizza le pagine servizi ad alto traffico</li><li>Migliora i tassi di conversione dei form di contatto</li></ul>'
            )
            ->build();

        // Education focused blueprint
        $educationBlueprint = TemplateBuilder::make()
            ->addSection(
                'Performance Piattaforma Educativa',
                '<p>Panoramica dell\'engagement studenti, completamento corsi e risultati di apprendimento.</p>'
            )
            ->addKpiSection('Metriche di Apprendimento', [
                ['label' => 'Studenti Attivi', 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => 'Corsi Completati', 'value' => '{{kpi.ga4.courseCompletions|number}}'],
                ['label' => 'Iscrizioni', 'value' => '{{kpi.ga4.enrollments|number}}'],
                ['label' => 'Tasso di Engagement', 'value' => '{{kpi.ga4.engagementRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                'Performance Contenuti',
                '<p>Analisi dei materiali del corso, risorse e pattern di interazione degli studenti.</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Ottimizzazione Apprendimento',
                '<ul><li>Migliora i contenuti dei corsi più performanti</li><li>Affronta le sfide di engagement nell\'apprendimento</li></ul>'
            )
            ->build();

        // B2B/Lead Generation focused blueprint
        $b2bBlueprint = TemplateBuilder::make()
            ->addSection(
                'Performance Lead Generation',
                '<p>Panoramica della qualità dei lead, tassi di conversione e metriche pipeline vendite.</p>'
            )
            ->addKpiSection('Metriche Lead', [
                ['label' => 'Lead Totali', 'value' => '{{kpi.ga4.conversions|number}}'],
                ['label' => 'Tasso di Conversione', 'value' => '{{kpi.ga4.conversionRate|percentage}}'],
                ['label' => 'Costo per Lead', 'value' => '{{kpi.google_ads.cost_per_conversion|currency}}'],
                ['label' => 'Qualità Lead Score', 'value' => '{{kpi.linkedin_ads.lead_quality_score|number}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                'Performance Canali',
                '<p>Analisi dei canali di lead generation ed efficacia delle campagne.</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Insights Pipeline Vendite',
                '<ul><li>Ottimizza le fonti di lead ad alta conversione</li><li>Migliora i processi di nurturing dei lead</li></ul>'
            )
            ->build();

        // Local Business focused blueprint
        $localBlueprint = TemplateBuilder::make()
            ->addSection(
                'Performance Business Locale',
                '<p>Panoramica della visibilità locale, engagement clienti e metriche basate sulla posizione.</p>'
            )
            ->addKpiSection('Metriche Locali', [
                ['label' => 'Ricerche Locali', 'value' => '{{kpi.gsc.clicks|number}}'],
                ['label' => 'Visitatori Sito Web', 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => 'Richieste di Contatto', 'value' => '{{kpi.ga4.formSubmissions|number}}'],
                ['label' => 'CTR Locale', 'value' => '{{kpi.gsc.ctr|percentage}}'],
            ])
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                'Performance SEO Locale',
                '<p>Analisi del posizionamento nelle ricerche locali e insights Google My Business.</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Strategia Marketing Locale',
                '<ul><li>Migliora la visibilità nelle ricerche locali</li><li>Potenzia l\'engagement con i clienti</li></ul>'
            )
            ->build();

        // Content Marketing focused blueprint
        $contentBlueprint = TemplateBuilder::make()
            ->addSection(
                'Panoramica Performance Contenuti',
                '<p>Analisi dell\'engagement contenuti, lettorato e ROI del content marketing.</p>'
            )
            ->addKpiSection('Metriche Contenuti', [
                ['label' => 'Visualizzazioni Pagina', 'value' => '{{kpi.ga4.pageViews|number}}'],
                ['label' => 'Durata Media Sessione', 'value' => '{{kpi.ga4.averageSessionDuration|duration}}'],
                ['label' => 'Tasso di Engagement', 'value' => '{{kpi.ga4.engagementRate|percentage}}'],
                ['label' => 'Traffico Organico', 'value' => '{{kpi.gsc.clicks|number}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                'Contenuti Top Performanti',
                '<p>Analisi degli articoli, argomenti e formati di contenuto più coinvolgenti.</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Insights Strategia Contenuti',
                '<ul><li>Scala gli argomenti di contenuto di successo</li><li>Ottimizza i contenuti sottoperformanti</li></ul>'
            )
            ->build();

        // Hospitality focused blueprint
        $hospitalityBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Performance Ricettivo', 'fp-dms'),
                '<p>' . esc_html__('Analisi completa delle performance della struttura ricettiva per il periodo {{period.start}} - {{period.end}}.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Metriche Chiave', 'fp-dms'), [
                ['label' => esc_html__('Tasso di Occupazione', 'fp-dms'), 'value' => '{{hospitality.occupancy_rate}}'],
                ['label' => esc_html__('Ricavo per Camera', 'fp-dms'), 'value' => '{{hospitality.revenue_per_room}}'],
                ['label' => esc_html__('Durata Media Soggiorno', 'fp-dms'), 'value' => '{{hospitality.average_stay_duration}}'],
                ['label' => esc_html__('Soddisfazione Ospiti', 'fp-dms'), 'value' => '{{hospitality.guest_satisfaction}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Analisi Prenotazioni', 'fp-dms'),
                '<p>' . esc_html__('Breakdown delle prenotazioni per canale e tipologia di ospite.', 'fp-dms') . '</p>'
            )
            ->addSection(
                esc_html__('Performance Stagionale', 'fp-dms'),
                '<p>' . esc_html__('Analisi delle performance stagionali e trend di mercato.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Raccomandazioni Strategiche', 'fp-dms'),
                '<ul><li>' . esc_html__('Ottimizzare i canali di prenotazione più performanti', 'fp-dms') . '</li><li>' . esc_html__('Migliorare l\'esperienza ospiti per aumentare la retention', 'fp-dms') . '</li><li>' . esc_html__('Sviluppare pacchetti stagionali per massimizzare i ricavi', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Hotel focused blueprint
        $hotelBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Performance Hotel', 'fp-dms'),
                '<p>' . esc_html__('Report dettagliato delle performance dell\'hotel {{client.name}} per il periodo {{period.start}} - {{period.end}}.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Ricavi per Dipartimento', 'fp-dms'), [
                ['label' => esc_html__('Ricavi Camere', 'fp-dms'), 'value' => '{{hotel.room_revenue}}'],
                ['label' => esc_html__('Ricavi F&B', 'fp-dms'), 'value' => '{{hotel.food_beverage_revenue}}'],
                ['label' => esc_html__('Ricavi Conferenze', 'fp-dms'), 'value' => '{{hotel.conference_revenue}}'],
                ['label' => esc_html__('Ricavi Spa', 'fp-dms'), 'value' => '{{hotel.spa_revenue}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Analisi Ospiti', 'fp-dms'),
                '<p>' . esc_html__('Breakdown degli ospiti per tipologia e comportamento di prenotazione.', 'fp-dms') . '</p>'
            )
            ->addSection(
                esc_html__('Performance Marketing', 'fp-dms'),
                '<p>' . esc_html__('Analisi delle campagne marketing e dei canali di acquisizione.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Piano di Azione', 'fp-dms'),
                '<ul><li>' . esc_html__('Massimizzare i ricavi dai servizi ancillari', 'fp-dms') . '</li><li>' . esc_html__('Sviluppare programmi di loyalty per ospiti business', 'fp-dms') . '</li><li>' . esc_html__('Ottimizzare la gestione dei gruppi e conferenze', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Resort focused blueprint
        $resortBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Performance Resort', 'fp-dms'),
                '<p>' . esc_html__('Analisi completa delle performance del resort {{client.name}} per il periodo {{period.start}} - {{period.end}}.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Metriche Resort', 'fp-dms'), [
                ['label' => esc_html__('Occupazione Ville', 'fp-dms'), 'value' => '{{resort.villa_occupancy}}'],
                ['label' => esc_html__('Ricavi Attività', 'fp-dms'), 'value' => '{{resort.activity_revenue}}'],
                ['label' => esc_html__('Ricavi Matrimoni', 'fp-dms'), 'value' => '{{resort.wedding_revenue}}'],
                ['label' => esc_html__('Ricavi Golf', 'fp-dms'), 'value' => '{{resort.golf_revenue}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Analisi Pacchetti', 'fp-dms'),
                '<p>' . esc_html__('Performance dei pacchetti all-inclusive e delle esperienze premium.', 'fp-dms') . '</p>'
            )
            ->addSection(
                esc_html__('Utilizzo Servizi', 'fp-dms'),
                '<p>' . esc_html__('Analisi dell\'utilizzo di spiaggia, spa e attività ricreative.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Strategia Resort', 'fp-dms'),
                '<ul><li>' . esc_html__('Sviluppare pacchetti family e honeymoon premium', 'fp-dms') . '</li><li>' . esc_html__('Massimizzare i ricavi dalle attività e servizi', 'fp-dms') . '</li><li>' . esc_html__('Creare esperienze esclusive per ospiti VIP', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Wine industry focused blueprint
        $wineBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Performance Azienda Vinicola', 'fp-dms'),
                '<p>' . esc_html__('Report completo delle performance di {{client.name}} per il periodo {{period.start}} - {{period.end}}.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Ricavi per Canale', 'fp-dms'), [
                ['label' => esc_html__('Vendite Cantina', 'fp-dms'), 'value' => '{{wine.cellar_sales}}'],
                ['label' => esc_html__('Ricavi Degustazioni', 'fp-dms'), 'value' => '{{wine.tasting_revenue}}'],
                ['label' => esc_html__('Ricavi Ristorante', 'fp-dms'), 'value' => '{{wine.restaurant_revenue}}'],
                ['label' => esc_html__('Ricavi Matrimoni', 'fp-dms'), 'value' => '{{wine.wedding_venue_revenue}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Wine Tourism', 'fp-dms'),
                '<p>' . esc_html__('Analisi del turismo enogastronomico e delle esperienze offerte.', 'fp-dms') . '</p>'
            )
            ->addSection(
                esc_html__('Club Vino e Fedeltà', 'fp-dms'),
                '<p>' . esc_html__('Performance del wine club e programmi di fedeltà.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Strategia Enoturistica', 'fp-dms'),
                '<ul><li>' . esc_html__('Sviluppare esperienze enoturistiche premium', 'fp-dms') . '</li><li>' . esc_html__('Ampliare il wine club e i programmi di fedeltà', 'fp-dms') . '</li><li>' . esc_html__('Massimizzare i ricavi da eventi e matrimoni', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // B&B focused blueprint
        $bnbBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Performance B&B', 'fp-dms'),
                '<p>' . esc_html__('Analisi delle performance del B&B {{client.name}} per il periodo {{period.start}} - {{period.end}}.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Metriche B&B', 'fp-dms'), [
                ['label' => esc_html__('Occupazione Camere', 'fp-dms'), 'value' => '{{bnb.room_occupancy}}'],
                ['label' => esc_html__('Ricavi Colazione', 'fp-dms'), 'value' => '{{bnb.breakfast_revenue}}'],
                ['label' => esc_html__('Esperienze Locali', 'fp-dms'), 'value' => '{{bnb.local_experiences}}'],
                ['label' => esc_html__('Prenotazioni Weekend', 'fp-dms'), 'value' => '{{bnb.weekend_bookings}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Esperienze Autentiche', 'fp-dms'),
                '<p>' . esc_html__('Analisi delle esperienze locali e dei pacchetti romantici.', 'fp-dms') . '</p>'
            )
            ->addSection(
                esc_html__('Turismo Sostenibile', 'fp-dms'),
                '<p>' . esc_html__('Performance del turismo sostenibile e delle partnership locali.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Strategia B&B', 'fp-dms'),
                '<ul><li>' . esc_html__('Sviluppare esperienze autentiche e locali', 'fp-dms') . '</li><li>' . esc_html__('Massimizzare le prenotazioni weekend e romantiche', 'fp-dms') . '</li><li>' . esc_html__('Rafforzare le partnership con operatori locali', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Professional Complete Blueprint (IT) - Con tutte le metriche separate e AI
        $professionalComplete = TemplateBuilder::make()
            ->addSection(
                'Sommario Esecutivo',
                '<div style="background:#f8f9fa;padding:20px;border-left:4px solid #667eea;margin-bottom:24px;">
                    {{ai.executive_summary|raw}}
                </div>
                <p><em>Questo report analizza le performance digitali di <strong>{{client.name}}</strong> per il periodo {{period.start}} - {{period.end}}, fornendo insights basati su dati reali provenienti da Google Analytics 4, Google Search Console, Google Ads e Meta Ads.</em></p>'
            )
            ->addSection(
                'Google Analytics 4 - Traffico e Comportamento Utenti',
                '<div class="kpi-grid">
                    <div class="kpi"><span>Utenti Attivi</span><strong>{{kpi.ga4.users|number}}</strong></div>
                    <div class="kpi"><span>Sessioni Totali</span><strong>{{kpi.ga4.sessions|number}}</strong></div>
                    <div class="kpi"><span>Pagine Visualizzate</span><strong>{{kpi.ga4.pageviews|number}}</strong></div>
                    <div class="kpi"><span>Eventi Registrati</span><strong>{{kpi.ga4.events|number}}</strong></div>
                    <div class="kpi"><span>Nuovi Utenti</span><strong>{{kpi.ga4.new_users|number}}</strong></div>
                    <div class="kpi"><span>Utenti Totali</span><strong>{{kpi.ga4.total_users|number}}</strong></div>
                </div>
                <p><em>Analisi del comportamento degli utenti sul sito web e delle interazioni registrate da Google Analytics 4.</em></p>'
            )
            ->addSection(
                'Google Search Console - Visibilità Organica',
                '<div class="kpi-grid">
                    <div class="kpi"><span>Clic da Ricerca</span><strong>{{kpi.gsc.clicks|number}}</strong></div>
                    <div class="kpi"><span>Impressioni Organiche</span><strong>{{kpi.gsc.impressions|number}}</strong></div>
                    <div class="kpi"><span>CTR Medio</span><strong>{{kpi.gsc.ctr|percentage}}</strong></div>
                    <div class="kpi"><span>Posizione Media</span><strong>{{kpi.gsc.position|number}}</strong></div>
                </div>
                <p><em>Performance della visibilità organica su Google Search e posizionamento medio delle keyword.</em></p>'
            )
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                'Google Ads - Performance Campagne',
                '<div class="kpi-grid">
                    <div class="kpi"><span>Clic Google Ads</span><strong>{{kpi.google_ads.clicks|number}}</strong></div>
                    <div class="kpi"><span>Impressioni Google Ads</span><strong>{{kpi.google_ads.impressions|number}}</strong></div>
                    <div class="kpi"><span>Costo Totale</span><strong>€ {{kpi.google_ads.cost|number}}</strong></div>
                    <div class="kpi"><span>Conversioni</span><strong>{{kpi.google_ads.conversions|number}}</strong></div>
                </div>
                <p><em>Analisi delle campagne pubblicitarie su Google Ads con focus su ROI e conversioni.</em></p>'
            )
            ->addSection(
                'Meta Ads - Performance Social Advertising',
                '<div class="kpi-grid">
                    <div class="kpi"><span>Clic Meta Ads</span><strong>{{kpi.meta_ads.clicks|number}}</strong></div>
                    <div class="kpi"><span>Impressioni Meta Ads</span><strong>{{kpi.meta_ads.impressions|number}}</strong></div>
                    <div class="kpi"><span>Costo Meta Ads</span><strong>€ {{kpi.meta_ads.cost|number}}</strong></div>
                    <div class="kpi"><span>Conversioni Meta</span><strong>{{kpi.meta_ads.conversions|number}}</strong></div>
                    <div class="kpi"><span>Fatturato Meta</span><strong>€ {{kpi.meta_ads.revenue|number}}</strong></div>
                </div>
                <p><em>Risultati delle campagne pubblicitarie su Facebook e Instagram gestite tramite Meta Business Suite.</em></p>'
            )
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                'Analisi Trend (AI)',
                '<div style="background:#f0fdf4;padding:20px;border-left:4px solid #4caf50;margin:20px 0;">
                    {{ai.trend_analysis|raw}}
                </div>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                'Spiegazione Anomalie (AI)',
                '<div style="background:#fff7ed;padding:20px;border-left:4px solid #f59e0b;margin:20px 0;">
                    {{ai.anomaly_explanation|raw}}
                </div>'
            )
            ->addSection(
                'Raccomandazioni Strategiche (AI)',
                '<div style="background:#eff6ff;padding:24px;border-left:4px solid #2196f3;margin:20px 0;">
                    <h3 style="margin-top:0;color:#1976d2;">Piano d\'Azione Consigliato</h3>
                    {{ai.recommendations|raw}}
                </div>
                <p style="margin-top:20px;font-size:12pt;color:#666;"><em><strong>Nota:</strong> Le analisi e raccomandazioni in questo report sono generate automaticamente utilizzando intelligenza artificiale basata sui dati reali del periodo. Si consiglia di valutare ogni raccomandazione nel contesto specifico del business.</em></p>'
            )
            ->build();

        self::$cache = [
            'professional' => new TemplateBlueprint(
                'professional',
                'Report Professionale Completo',
                'Template professionale in italiano con tutte le metriche separate (GA4, GSC, Google Ads, Meta Ads). Ideale per report mensili dettagliati.',
                $professionalComplete
            ),
            'balanced' => new TemplateBlueprint(
                'balanced',
                esc_html__('Report Bilanciato', 'fp-dms'),
                esc_html__('Combina KPI, trend e insights di ricerca con spazio per commenti.', 'fp-dms'),
                $balanced
            ),
            'kpi' => new TemplateBlueprint(
                'kpi',
                esc_html__('Focus su KPI', 'fp-dms'),
                esc_html__('Mette in evidenza le metriche principali e gli insights qualitativi.', 'fp-dms'),
                $kpiFocused
            ),
            'search' => new TemplateBlueprint(
                'search',
                esc_html__('Recap Visibilità Search', 'fp-dms'),
                esc_html__('Centra il report sui risultati di Google Search Console e prossimi passi.', 'fp-dms'),
                $searchBlueprint
            ),
            'ecommerce' => new TemplateBlueprint(
                'ecommerce',
                esc_html__('Report E-commerce', 'fp-dms'),
                esc_html__('Ottimizzato per analisi di vendite, prodotti e performance commerciali.', 'fp-dms'),
                $ecommerceBlueprint
            ),
            'saas' => new TemplateBlueprint(
                'saas',
                esc_html__('Report SaaS & Software', 'fp-dms'),
                esc_html__('Focus su engagement utenti, retention e metriche di prodotto.', 'fp-dms'),
                $saasBlueprint
            ),
            'healthcare' => new TemplateBlueprint(
                'healthcare',
                esc_html__('Report Sanità', 'fp-dms'),
                esc_html__('Metriche per cliniche, centri medici e servizi di benessere.', 'fp-dms'),
                $healthcareBlueprint
            ),
            'education' => new TemplateBlueprint(
                'education',
                esc_html__('Report Educazione', 'fp-dms'),
                esc_html__('Analisi per scuole, università e piattaforme di apprendimento.', 'fp-dms'),
                $educationBlueprint
            ),
            'b2b' => new TemplateBlueprint(
                'b2b',
                esc_html__('Report B2B & Lead Gen', 'fp-dms'),
                esc_html__('Focus su generazione lead, qualità e pipeline di vendita.', 'fp-dms'),
                $b2bBlueprint
            ),
            'local' => new TemplateBlueprint(
                'local',
                esc_html__('Report Business Locali', 'fp-dms'),
                esc_html__('Metriche per ristoranti, negozi e business locali.', 'fp-dms'),
                $localBlueprint
            ),
            'content' => new TemplateBlueprint(
                'content',
                esc_html__('Report Content Marketing', 'fp-dms'),
                esc_html__('Analisi di engagement, lettori e ROI del content marketing.', 'fp-dms'),
                $contentBlueprint
            ),
            'hospitality' => new TemplateBlueprint(
                'hospitality',
                esc_html__('Report Hospitality', 'fp-dms'),
                esc_html__('Analisi completa per strutture ricettive e turismo.', 'fp-dms'),
                $hospitalityBlueprint
            ),
            'hotel' => new TemplateBlueprint(
                'hotel',
                esc_html__('Report Hotel', 'fp-dms'),
                esc_html__('Metriche specializzate per hotel e strutture alberghiere.', 'fp-dms'),
                $hotelBlueprint
            ),
            'resort' => new TemplateBlueprint(
                'resort',
                esc_html__('Report Resort', 'fp-dms'),
                esc_html__('Analisi per resort di lusso e strutture premium.', 'fp-dms'),
                $resortBlueprint
            ),
            'wine' => new TemplateBlueprint(
                'wine',
                esc_html__('Report Aziende di Vino', 'fp-dms'),
                esc_html__('Metriche per cantine, vigneti e wine tourism.', 'fp-dms'),
                $wineBlueprint
            ),
            'bnb' => new TemplateBlueprint(
                'bnb',
                esc_html__('Report B&B', 'fp-dms'),
                esc_html__('Analisi per B&B, agriturismi e turismo rurale.', 'fp-dms'),
                $bnbBlueprint
            ),
        ];

        return self::$cache;
    }

    public static function find(string $key): ?TemplateBlueprint
    {
        $all = self::all();

        return $all[$key] ?? null;
    }

    public static function defaultBlueprint(): TemplateBlueprint
    {
        return self::all()['balanced'];
    }

    public static function defaultDraft(): TemplateDraft
    {
        $blueprint = self::defaultBlueprint();

        return TemplateDraft::fromValues(
            esc_html__('Report Bilanciato', 'fp-dms'),
            esc_html__('Layout predefinito generato automaticamente.', 'fp-dms'),
            $blueprint->content,
            true
        );
    }

    /**
     * Get blueprints filtered by category.
     *
     * @param string $category Category type (analytics, ecommerce, seo, etc.)
     * @return array<string,TemplateBlueprint>
     */
    public static function getByCategory(string $category): array
    {
        $all = self::all();
        $categoryMap = [
            'analytics' => ['balanced', 'kpi'],
            'ecommerce' => ['ecommerce'],
            'saas' => ['saas'],
            'healthcare' => ['healthcare'],
            'education' => ['education'],
            'b2b' => ['b2b'],
            'local' => ['local'],
            'content' => ['content'],
            'seo' => ['search'],
        ];

        $templateIds = $categoryMap[$category] ?? [];
        return array_intersect_key($all, array_flip($templateIds));
    }

    /**
     * Get all available categories for blueprints.
     *
     * @return array<string> Array of category names
     */
    public static function getCategories(): array
    {
        return [
            'analytics' => esc_html__('Analytics', 'fp-dms'),
            'ecommerce' => esc_html__('E-commerce', 'fp-dms'),
            'saas' => esc_html__('SaaS & Software', 'fp-dms'),
            'healthcare' => esc_html__('Sanità', 'fp-dms'),
            'education' => esc_html__('Educazione', 'fp-dms'),
            'b2b' => esc_html__('B2B & Lead Gen', 'fp-dms'),
            'local' => esc_html__('Business Locali', 'fp-dms'),
            'content' => esc_html__('Content Marketing', 'fp-dms'),
            'seo' => esc_html__('SEO', 'fp-dms'),
        ];
    }

    /**
     * Suggest blueprints based on business context.
     *
     * @param array $context Context information
     * @return array<string> Array of suggested blueprint keys
     */
    public static function suggestBlueprints(array $context): array
    {
        $suggestions = [];
        $businessType = $context['business_type'] ?? '';
        $keywords = $context['keywords'] ?? [];

        // E-commerce detection
        if (
            in_array('shop', $keywords, true) ||
            in_array('ecommerce', $keywords, true) ||
            $businessType === 'ecommerce'
        ) {
            $suggestions[] = 'ecommerce';
        }

        // SaaS/Software detection
        if (
            in_array('saas', $keywords, true) ||
            in_array('software', $keywords, true) ||
            $businessType === 'saas'
        ) {
            $suggestions[] = 'saas';
        }

        // Healthcare detection
        if (
            in_array('health', $keywords, true) ||
            in_array('medical', $keywords, true) ||
            $businessType === 'healthcare'
        ) {
            $suggestions[] = 'healthcare';
        }

        // Education detection
        if (
            in_array('education', $keywords, true) ||
            in_array('school', $keywords, true) ||
            $businessType === 'education'
        ) {
            $suggestions[] = 'education';
        }

        // B2B detection
        if (
            in_array('b2b', $keywords, true) ||
            in_array('professional', $keywords, true) ||
            $businessType === 'b2b'
        ) {
            $suggestions[] = 'b2b';
        }

        // Local business detection
        if (
            in_array('local', $keywords, true) ||
            in_array('restaurant', $keywords, true) ||
            $businessType === 'local'
        ) {
            $suggestions[] = 'local';
        }

        // Content/Blog detection
        if (
            in_array('blog', $keywords, true) ||
            in_array('content', $keywords, true) ||
            $businessType === 'content'
        ) {
            $suggestions[] = 'content';
        }

        // Default suggestions
        if (empty($suggestions)) {
            $suggestions = ['balanced', 'kpi'];
        }

        return $suggestions;
    }
}

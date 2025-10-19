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
                esc_html__('Executive summary', 'fp-dms'),
                '<p>' . esc_html__('Use this space to summarise wins, challenges, and context for the reporting period.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.kpi|raw}}')
            ->addSection(
                esc_html__('Key takeaways', 'fp-dms'),
                '<ul><li>' . esc_html__('Call out the marketing activities that moved the numbers.', 'fp-dms') . '</li><li>' . esc_html__('Highlight optimisation opportunities for the next cycle.', 'fp-dms') . '</li></ul>'
            )
            ->addRawSection('{{sections.trends|raw}}')
            ->addRawSection('{{sections.gsc|raw}}')
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Next steps', 'fp-dms'),
                '<p>' . esc_html__('Outline the actions you will take to sustain growth or fix bottlenecks.', 'fp-dms') . '</p>'
            )
            ->build();

        $kpiFocused = TemplateBuilder::make()
            ->addSection(
                esc_html__('Performance snapshot', 'fp-dms'),
                '<p>' . esc_html__('Summarise how the account performed during this period in one paragraph.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('KPI overview', 'fp-dms'), [
                ['label' => esc_html__('Users', 'fp-dms'), 'value' => '{{kpi.ga4.users|number}}'],
                ['label' => esc_html__('Sessions', 'fp-dms'), 'value' => '{{kpi.ga4.sessions|number}}'],
                ['label' => esc_html__('Clicks', 'fp-dms'), 'value' => '{{kpi.google_ads.clicks|number}}'],
                ['label' => esc_html__('Conversions', 'fp-dms'), 'value' => '{{kpi.google_ads.conversions|number}}'],
                ['label' => esc_html__('Cost', 'fp-dms'), 'value' => '{{kpi.google_ads.cost|number}}'],
            ])
            ->addSection(
                esc_html__('Insights and commentary', 'fp-dms'),
                '<p>' . esc_html__('Explain what caused major swings and how they align with your objectives.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Action plan', 'fp-dms'),
                '<ul><li>' . esc_html__('List the optimisations you will tackle next.', 'fp-dms') . '</li></ul>'
            )
            ->build();

        $searchBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Organic performance overview', 'fp-dms'),
                '<p>' . esc_html__('Frame the organic search visibility achieved in the selected window.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                esc_html__('Opportunities', 'fp-dms'),
                '<ul><li>' . esc_html__('Identify keyword clusters or pages that deserve extra attention.', 'fp-dms') . '</li><li>' . esc_html__('Describe experiments or content you will launch.', 'fp-dms') . '</li></ul>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->build();

        // E-commerce focused blueprint
        $ecommerceBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Executive Summary', 'fp-dms'),
                '<p>' . esc_html__('Overview of e-commerce performance including revenue, conversions, and key trends.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Revenue & Sales', 'fp-dms'), [
                ['label' => esc_html__('Total Revenue', 'fp-dms'), 'value' => '{{kpi.ga4.totalRevenue|currency}}'],
                ['label' => esc_html__('Transactions', 'fp-dms'), 'value' => '{{kpi.ga4.transactions|number}}'],
                ['label' => esc_html__('Average Order Value', 'fp-dms'), 'value' => '{{kpi.ga4.averageOrderValue|currency}}'],
                ['label' => esc_html__('Conversion Rate', 'fp-dms'), 'value' => '{{kpi.ga4.conversionRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Product Performance', 'fp-dms'),
                '<p>' . esc_html__('Analysis of top-performing products and categories.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Marketing Channel Analysis', 'fp-dms'),
                '<p>' . esc_html__('Performance breakdown by traffic sources and campaigns.', 'fp-dms') . '</p>'
            )
            ->addSection(
                esc_html__('Next Steps & Recommendations', 'fp-dms'),
                '<ul><li>' . esc_html__('Optimize underperforming product categories', 'fp-dms') . '</li><li>' . esc_html__('Scale successful marketing channels', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // SaaS/Software focused blueprint
        $saasBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Product Performance Overview', 'fp-dms'),
                '<p>' . esc_html__('Key metrics for software adoption, engagement, and user retention.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('User Engagement', 'fp-dms'), [
                ['label' => esc_html__('Active Users', 'fp-dms'), 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => esc_html__('Session Duration', 'fp-dms'), 'value' => '{{kpi.ga4.averageSessionDuration|duration}}'],
                ['label' => esc_html__('Feature Usage', 'fp-dms'), 'value' => '{{kpi.ga4.eventCount|number}}'],
                ['label' => esc_html__('Retention Rate', 'fp-dms'), 'value' => '{{kpi.ga4.retentionRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('User Journey Analysis', 'fp-dms'),
                '<p>' . esc_html__('Insights into user behavior and feature adoption patterns.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Product Development Insights', 'fp-dms'),
                '<ul><li>' . esc_html__('Identify features driving user engagement', 'fp-dms') . '</li><li>' . esc_html__('Address user experience bottlenecks', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Healthcare focused blueprint
        $healthcareBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Patient Engagement Summary', 'fp-dms'),
                '<p>' . esc_html__('Overview of website traffic, patient inquiries, and service engagement.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Patient Metrics', 'fp-dms'), [
                ['label' => esc_html__('Website Visitors', 'fp-dms'), 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => esc_html__('Page Views', 'fp-dms'), 'value' => '{{kpi.ga4.pageViews|number}}'],
                ['label' => esc_html__('Contact Forms', 'fp-dms'), 'value' => '{{kpi.ga4.formSubmissions|number}}'],
                ['label' => esc_html__('Engagement Rate', 'fp-dms'), 'value' => '{{kpi.ga4.engagementRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Service Page Performance', 'fp-dms'),
                '<p>' . esc_html__('Analysis of which services and pages are most popular with patients.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                esc_html__('Patient Communication Insights', 'fp-dms'),
                '<ul><li>' . esc_html__('Optimize high-traffic service pages', 'fp-dms') . '</li><li>' . esc_html__('Improve contact form conversion rates', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Education focused blueprint
        $educationBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Educational Platform Performance', 'fp-dms'),
                '<p>' . esc_html__('Overview of student engagement, course completion, and learning outcomes.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Learning Metrics', 'fp-dms'), [
                ['label' => esc_html__('Active Students', 'fp-dms'), 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => esc_html__('Course Completions', 'fp-dms'), 'value' => '{{kpi.ga4.courseCompletions|number}}'],
                ['label' => esc_html__('Enrollments', 'fp-dms'), 'value' => '{{kpi.ga4.enrollments|number}}'],
                ['label' => esc_html__('Engagement Rate', 'fp-dms'), 'value' => '{{kpi.ga4.engagementRate|percentage}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Content Performance', 'fp-dms'),
                '<p>' . esc_html__('Analysis of course materials, resources, and student interaction patterns.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Learning Optimization', 'fp-dms'),
                '<ul><li>' . esc_html__('Enhance high-performing course content', 'fp-dms') . '</li><li>' . esc_html__('Address learning engagement challenges', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // B2B/Lead Generation focused blueprint
        $b2bBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Lead Generation Performance', 'fp-dms'),
                '<p>' . esc_html__('Overview of lead quality, conversion rates, and sales pipeline metrics.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Lead Metrics', 'fp-dms'), [
                ['label' => esc_html__('Total Leads', 'fp-dms'), 'value' => '{{kpi.ga4.conversions|number}}'],
                ['label' => esc_html__('Conversion Rate', 'fp-dms'), 'value' => '{{kpi.ga4.conversionRate|percentage}}'],
                ['label' => esc_html__('Cost per Lead', 'fp-dms'), 'value' => '{{kpi.google_ads.cost_per_conversion|currency}}'],
                ['label' => esc_html__('Lead Quality Score', 'fp-dms'), 'value' => '{{kpi.linkedin_ads.lead_quality_score|number}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addSection(
                esc_html__('Channel Performance', 'fp-dms'),
                '<p>' . esc_html__('Analysis of lead generation channels and campaign effectiveness.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Sales Pipeline Insights', 'fp-dms'),
                '<ul><li>' . esc_html__('Optimize high-converting lead sources', 'fp-dms') . '</li><li>' . esc_html__('Improve lead nurturing processes', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Local Business focused blueprint
        $localBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Local Business Performance', 'fp-dms'),
                '<p>' . esc_html__('Overview of local visibility, customer engagement, and location-based metrics.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Local Metrics', 'fp-dms'), [
                ['label' => esc_html__('Local Searches', 'fp-dms'), 'value' => '{{kpi.gsc.clicks|number}}'],
                ['label' => esc_html__('Website Visitors', 'fp-dms'), 'value' => '{{kpi.ga4.activeUsers|number}}'],
                ['label' => esc_html__('Contact Inquiries', 'fp-dms'), 'value' => '{{kpi.ga4.formSubmissions|number}}'],
                ['label' => esc_html__('Local CTR', 'fp-dms'), 'value' => '{{kpi.gsc.ctr|percentage}}'],
            ])
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                esc_html__('Local SEO Performance', 'fp-dms'),
                '<p>' . esc_html__('Analysis of local search rankings and Google My Business insights.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Local Marketing Strategy', 'fp-dms'),
                '<ul><li>' . esc_html__('Improve local search visibility', 'fp-dms') . '</li><li>' . esc_html__('Enhance customer engagement', 'fp-dms') . '</li></ul>'
            )
            ->build();

        // Content Marketing focused blueprint
        $contentBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Content Performance Overview', 'fp-dms'),
                '<p>' . esc_html__('Analysis of content engagement, readership, and content marketing ROI.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('Content Metrics', 'fp-dms'), [
                ['label' => esc_html__('Page Views', 'fp-dms'), 'value' => '{{kpi.ga4.pageViews|number}}'],
                ['label' => esc_html__('Average Session Duration', 'fp-dms'), 'value' => '{{kpi.ga4.averageSessionDuration|duration}}'],
                ['label' => esc_html__('Engagement Rate', 'fp-dms'), 'value' => '{{kpi.ga4.engagementRate|percentage}}'],
                ['label' => esc_html__('Organic Traffic', 'fp-dms'), 'value' => '{{kpi.gsc.clicks|number}}'],
            ])
            ->addRawSection('{{sections.trends|raw}}')
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                esc_html__('Top Performing Content', 'fp-dms'),
                '<p>' . esc_html__('Analysis of most engaging articles, topics, and content formats.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Content Strategy Insights', 'fp-dms'),
                '<ul><li>' . esc_html__('Scale successful content topics', 'fp-dms') . '</li><li>' . esc_html__('Optimize underperforming content', 'fp-dms') . '</li></ul>'
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

        self::$cache = [
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

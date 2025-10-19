<?php

declare(strict_types=1);

namespace FP\DMS\Services;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\Template;
use FP\DMS\Support\Period;

/**
 * Industry-specific report generator with dynamic content.
 */
class IndustryReportGenerator
{
    public function __construct(
        private ContentGenerationEngine $contentEngine,
        private TemplateSuggestionEngine $suggestionEngine
    ) {
    }

    /**
     * Generate industry-specific report content.
     *
     * @param Client $client Client information
     * @param Template $template Report template
     * @param Period $period Reporting period
     * @param array $data Collected data
     * @param string $industry Industry type
     * @return string Generated report content
     */
    public function generateReport(
        Client $client,
        Template $template,
        Period $period,
        array $data,
        string $industry = 'general'
    ): string {
        // Build context for content generation
        $context = $this->buildContext($client, $template, $period, $data, $industry);
        
        // Generate dynamic content
        $content = $this->contentEngine->generateContent(
            $template->content,
            $context,
            $industry
        );
        
        return $content;
    }

    /**
     * Build context for content generation.
     *
     * @param Client $client Client information
     * @param Template $template Report template
     * @param Period $period Reporting period
     * @param array $data Collected data
     * @param string $industry Industry type
     * @return array Context array
     */
    private function buildContext(
        Client $client,
        Template $template,
        Period $period,
        array $data,
        string $industry
    ): array {
        $context = [
            'client' => [
                'name' => $client->name ?? 'Cliente',
                'logo_url' => $client->logo_url ?? '',
                'industry' => $industry,
            ],
            'period' => [
                'start' => $period->start->format('d/m/Y'),
                'end' => $period->end->format('d/m/Y'),
                'duration' => $period->start->diff($period->end)->days,
            ],
            'report' => [
                'generated_at' => current_time('d/m/Y H:i'),
                'template_name' => $template->name ?? '',
            ],
            'kpi' => $this->extractKpis($data, $industry),
            'sections' => $this->buildSections($data, $industry),
            'industry' => $industry,
        ];

        return $context;
    }

    /**
     * Extract KPIs based on industry.
     *
     * @param array $data Collected data
     * @param string $industry Industry type
     * @return array KPI data
     */
    private function extractKpis(array $data, string $industry): array
    {
        $kpis = [];

        // Extract GA4 KPIs
        if (isset($data['ga4'])) {
            $kpis['ga4'] = [
                'activeUsers' => $data['ga4']['activeUsers'] ?? 0,
                'sessions' => $data['ga4']['sessions'] ?? 0,
                'conversions' => $data['ga4']['conversions'] ?? 0,
                'pageViews' => $data['ga4']['pageViews'] ?? 0,
                'averageSessionDuration' => $data['ga4']['averageSessionDuration'] ?? 0,
                'engagementRate' => $data['ga4']['engagementRate'] ?? 0,
                'bounceRate' => $data['ga4']['bounceRate'] ?? 0,
            ];
        }

        // Extract GSC KPIs
        if (isset($data['gsc'])) {
            $kpis['gsc'] = [
                'clicks' => $data['gsc']['clicks'] ?? 0,
                'impressions' => $data['gsc']['impressions'] ?? 0,
                'ctr' => $data['gsc']['ctr'] ?? 0,
                'position' => $data['gsc']['position'] ?? 0,
            ];
        }

        // Extract Meta Ads KPIs
        if (isset($data['meta_ads'])) {
            $kpis['meta_ads'] = [
                'impressions' => $data['meta_ads']['impressions'] ?? 0,
                'clicks' => $data['meta_ads']['clicks'] ?? 0,
                'spend' => $data['meta_ads']['spend'] ?? 0,
                'conversions' => $data['meta_ads']['conversions'] ?? 0,
                'cpc' => $data['meta_ads']['cpc'] ?? 0,
                'cpm' => $data['meta_ads']['cpm'] ?? 0,
                'ctr' => $data['meta_ads']['ctr'] ?? 0,
                'roas' => $data['meta_ads']['roas'] ?? 0,
            ];
        }

        // Extract Google Ads KPIs
        if (isset($data['google_ads'])) {
            $kpis['google_ads'] = [
                'impressions' => $data['google_ads']['impressions'] ?? 0,
                'clicks' => $data['google_ads']['clicks'] ?? 0,
                'cost' => $data['google_ads']['cost'] ?? 0,
                'conversions' => $data['google_ads']['conversions'] ?? 0,
                'ctr' => $data['google_ads']['ctr'] ?? 0,
                'average_cpc' => $data['google_ads']['average_cpc'] ?? 0,
                'conversion_rate' => $data['google_ads']['conversion_rate'] ?? 0,
                'cost_per_conversion' => $data['google_ads']['cost_per_conversion'] ?? 0,
            ];
        }

        // Add industry-specific KPIs
        $kpis = array_merge($kpis, $this->getIndustrySpecificKpis($data, $industry));

        return $kpis;
    }

    /**
     * Get industry-specific KPIs.
     *
     * @param array $data Collected data
     * @param string $industry Industry type
     * @return array Industry KPIs
     */
    private function getIndustrySpecificKpis(array $data, string $industry): array
    {
        $industryKpis = [];

        switch ($industry) {
            case 'hospitality':
            case 'hotel':
                $industryKpis = [
                    'occupancy_rate' => $data['hospitality']['occupancy_rate'] ?? 0,
                    'revenue_per_room' => $data['hospitality']['revenue_per_room'] ?? 0,
                    'average_stay_duration' => $data['hospitality']['average_stay_duration'] ?? 0,
                    'guest_satisfaction' => $data['hospitality']['guest_satisfaction'] ?? 0,
                    'direct_bookings' => $data['hospitality']['direct_bookings'] ?? 0,
                    'ota_bookings' => $data['hospitality']['ota_bookings'] ?? 0,
                ];
                break;

            case 'resort':
                $industryKpis = [
                    'villa_occupancy' => $data['resort']['villa_occupancy'] ?? 0,
                    'activity_revenue' => $data['resort']['activity_revenue'] ?? 0,
                    'wedding_revenue' => $data['resort']['wedding_revenue'] ?? 0,
                    'golf_revenue' => $data['resort']['golf_revenue'] ?? 0,
                    'beach_usage' => $data['resort']['beach_usage'] ?? 0,
                    'spa_treatments' => $data['resort']['spa_treatments'] ?? 0,
                    'family_packages' => $data['resort']['family_packages'] ?? 0,
                    'honeymoon_packages' => $data['resort']['honeymoon_packages'] ?? 0,
                    'all_inclusive_revenue' => $data['resort']['all_inclusive_revenue'] ?? 0,
                    'excursion_bookings' => $data['resort']['excursion_bookings'] ?? 0,
                ];
                break;

            case 'wine':
                $industryKpis = [
                    'cellar_sales' => $data['wine']['cellar_sales'] ?? 0,
                    'tasting_revenue' => $data['wine']['tasting_revenue'] ?? 0,
                    'wine_club_members' => $data['wine']['wine_club_members'] ?? 0,
                    'vineyard_tours' => $data['wine']['vineyard_tours'] ?? 0,
                    'restaurant_revenue' => $data['wine']['restaurant_revenue'] ?? 0,
                    'wedding_venue_revenue' => $data['wine']['wedding_venue_revenue'] ?? 0,
                    'retail_sales' => $data['wine']['retail_sales'] ?? 0,
                    'wholesale_sales' => $data['wine']['wholesale_sales'] ?? 0,
                    'export_sales' => $data['wine']['export_sales'] ?? 0,
                    'seasonal_visitors' => $data['wine']['seasonal_visitors'] ?? 0,
                    'wine_education_classes' => $data['wine']['wine_education_classes'] ?? 0,
                    'corporate_events' => $data['wine']['corporate_events'] ?? 0,
                ];
                break;

            case 'bnb':
                $industryKpis = [
                    'bnb_room_occupancy' => $data['bnb']['room_occupancy'] ?? 0,
                    'breakfast_revenue' => $data['bnb']['breakfast_revenue'] ?? 0,
                    'local_experiences' => $data['bnb']['local_experiences'] ?? 0,
                    'weekend_bookings' => $data['bnb']['weekend_bookings'] ?? 0,
                    'romantic_packages' => $data['bnb']['romantic_packages'] ?? 0,
                    'cultural_tours' => $data['bnb']['cultural_tours'] ?? 0,
                    'local_recommendations' => $data['bnb']['local_recommendations'] ?? 0,
                    'sustainable_tourism' => $data['bnb']['sustainable_tourism'] ?? 0,
                    'homestay_experience' => $data['bnb']['homestay_experience'] ?? 0,
                    'local_partnerships' => $data['bnb']['local_partnerships'] ?? 0,
                ];
                break;
        }

        return $industryKpis;
    }

    /**
     * Build sections for the report.
     *
     * @param array $data Collected data
     * @param string $industry Industry type
     * @return array Sections data
     */
    private function buildSections(array $data, string $industry): array
    {
        $sections = [];

        // Build trends section
        $sections['trends'] = $this->buildTrendsSection($data, $industry);

        // Build GSC section
        $sections['gsc'] = $this->buildGscSection($data);

        // Build anomalies section
        $sections['anomalies'] = $this->buildAnomaliesSection($data, $industry);

        return $sections;
    }

    /**
     * Build trends section.
     *
     * @param array $data Collected data
     * @param string $industry Industry type
     * @return string Trends HTML
     */
    private function buildTrendsSection(array $data, string $industry): string
    {
        $trends = [];

        // Analyze trends based on industry
        switch ($industry) {
            case 'hospitality':
            case 'hotel':
                $trends[] = $this->analyzeOccupancyTrend($data);
                $trends[] = $this->analyzeRevenueTrend($data);
                $trends[] = $this->analyzeBookingChannelTrend($data);
                break;

            case 'resort':
                $trends[] = $this->analyzeVillaOccupancyTrend($data);
                $trends[] = $this->analyzeActivityRevenueTrend($data);
                $trends[] = $this->analyzePackageTrend($data);
                break;

            case 'wine':
                $trends[] = $this->analyzeWineSalesTrend($data);
                $trends[] = $this->analyzeTourismTrend($data);
                $trends[] = $this->analyzeWineClubTrend($data);
                break;

            case 'bnb':
                $trends[] = $this->analyzeBnbOccupancyTrend($data);
                $trends[] = $this->analyzeWeekendTrend($data);
                $trends[] = $this->analyzeExperienceTrend($data);
                break;

            default:
                $trends[] = $this->analyzeGeneralTrend($data);
                break;
        }

        return '<div class="trends-section">' . implode('', $trends) . '</div>';
    }

    /**
     * Build GSC section.
     *
     * @param array $data Collected data
     * @return string GSC HTML
     */
    private function buildGscSection(array $data): string
    {
        if (!isset($data['gsc'])) {
            return '';
        }

        $gscData = $data['gsc'];
        
        return '<div class="gsc-section">
            <h3>Performance SEO</h3>
            <div class="gsc-metrics">
                <div class="metric">
                    <span class="label">Click Totali</span>
                    <span class="value">' . number_format($gscData['clicks'] ?? 0) . '</span>
                </div>
                <div class="metric">
                    <span class="label">Impresse</span>
                    <span class="value">' . number_format($gscData['impressions'] ?? 0) . '</span>
                </div>
                <div class="metric">
                    <span class="label">CTR</span>
                    <span class="value">' . number_format(($gscData['ctr'] ?? 0) * 100, 2) . '%</span>
                </div>
                <div class="metric">
                    <span class="label">Posizione Media</span>
                    <span class="value">' . number_format($gscData['position'] ?? 0, 1) . '</span>
                </div>
            </div>
        </div>';
    }

    /**
     * Build anomalies section.
     *
     * @param array $data Collected data
     * @param string $industry Industry type
     * @return string Anomalies HTML
     */
    private function buildAnomaliesSection(array $data, string $industry): string
    {
        $anomalies = [];

        // Detect anomalies based on industry
        switch ($industry) {
            case 'hospitality':
            case 'hotel':
                $anomalies[] = $this->detectOccupancyAnomaly($data);
                $anomalies[] = $this->detectRevenueAnomaly($data);
                break;

            case 'wine':
                $anomalies[] = $this->detectWineSalesAnomaly($data);
                $anomalies[] = $this->detectTourismAnomaly($data);
                break;

            default:
                $anomalies[] = $this->detectGeneralAnomaly($data);
                break;
        }

        $anomalies = array_filter($anomalies);

        if (empty($anomalies)) {
            return '';
        }

        return '<div class="anomalies-section">
            <h3>Anomalie Rilevate</h3>
            <ul>' . implode('', $anomalies) . '</ul>
        </div>';
    }

    // Trend analysis methods
    private function analyzeOccupancyTrend(array $data): string
    {
        $current = $data['hospitality']['occupancy_rate'] ?? 0;
        $previous = $data['hospitality']['previous_occupancy_rate'] ?? 0;
        $change = $current - $previous;
        $trend = $change > 0 ? 'positivo' : ($change < 0 ? 'negativo' : 'stabile');
        
        return '<div class="trend-item">
            <h4>Tasso di Occupazione</h4>
            <p>Trend ' . $trend . ' (' . ($change > 0 ? '+' : '') . number_format($change, 1) . '%)</p>
        </div>';
    }

    private function analyzeRevenueTrend(array $data): string
    {
        $current = $data['hospitality']['revenue_per_room'] ?? 0;
        $previous = $data['hospitality']['previous_revenue_per_room'] ?? 0;
        $change = $current - $previous;
        $trend = $change > 0 ? 'positivo' : ($change < 0 ? 'negativo' : 'stabile');
        
        return '<div class="trend-item">
            <h4>Ricavo per Camera</h4>
            <p>Trend ' . $trend . ' (€' . ($change > 0 ? '+' : '') . number_format($change, 2) . ')</p>
        </div>';
    }

    private function analyzeBookingChannelTrend(array $data): string
    {
        $direct = $data['hospitality']['direct_bookings'] ?? 0;
        $ota = $data['hospitality']['ota_bookings'] ?? 0;
        $total = $direct + $ota;
        $directPercentage = $total > 0 ? ($direct / $total) * 100 : 0;
        
        return '<div class="trend-item">
            <h4>Canali di Prenotazione</h4>
            <p>Prenotazioni dirette: ' . number_format($directPercentage, 1) . '%</p>
        </div>';
    }

    private function analyzeVillaOccupancyTrend(array $data): string
    {
        $current = $data['resort']['villa_occupancy'] ?? 0;
        $previous = $data['resort']['previous_villa_occupancy'] ?? 0;
        $change = $current - $previous;
        $trend = $change > 0 ? 'positivo' : ($change < 0 ? 'negativo' : 'stabile');
        
        return '<div class="trend-item">
            <h4>Occupazione Ville</h4>
            <p>Trend ' . $trend . ' (' . ($change > 0 ? '+' : '') . number_format($change, 1) . '%)</p>
        </div>';
    }

    private function analyzeActivityRevenueTrend(array $data): string
    {
        $current = $data['resort']['activity_revenue'] ?? 0;
        $previous = $data['resort']['previous_activity_revenue'] ?? 0;
        $change = $current - $previous;
        $trend = $change > 0 ? 'positivo' : ($change < 0 ? 'negativo' : 'stabile');
        
        return '<div class="trend-item">
            <h4>Ricavi Attività</h4>
            <p>Trend ' . $trend . ' (€' . ($change > 0 ? '+' : '') . number_format($change, 2) . ')</p>
        </div>';
    }

    private function analyzePackageTrend(array $data): string
    {
        $family = $data['resort']['family_packages'] ?? 0;
        $honeymoon = $data['resort']['honeymoon_packages'] ?? 0;
        $total = $family + $honeymoon;
        $familyPercentage = $total > 0 ? ($family / $total) * 100 : 0;
        
        return '<div class="trend-item">
            <h4>Pacchetti</h4>
            <p>Pacchetti family: ' . number_format($familyPercentage, 1) . '%</p>
        </div>';
    }

    private function analyzeWineSalesTrend(array $data): string
    {
        $current = $data['wine']['cellar_sales'] ?? 0;
        $previous = $data['wine']['previous_cellar_sales'] ?? 0;
        $change = $current - $previous;
        $trend = $change > 0 ? 'positivo' : ($change < 0 ? 'negativo' : 'stabile');
        
        return '<div class="trend-item">
            <h4>Vendite Cantina</h4>
            <p>Trend ' . $trend . ' (€' . ($change > 0 ? '+' : '') . number_format($change, 2) . ')</p>
        </div>';
    }

    private function analyzeTourismTrend(array $data): string
    {
        $tours = $data['wine']['vineyard_tours'] ?? 0;
        $tastings = $data['wine']['tasting_revenue'] ?? 0;
        
        return '<div class="trend-item">
            <h4>Wine Tourism</h4>
            <p>Tour vigneti: ' . number_format($tours) . ', Ricavi degustazioni: €' . number_format($tastings, 2) . '</p>
        </div>';
    }

    private function analyzeWineClubTrend(array $data): string
    {
        $members = $data['wine']['wine_club_members'] ?? 0;
        $previous = $data['wine']['previous_wine_club_members'] ?? 0;
        $change = $members - $previous;
        $trend = $change > 0 ? 'positivo' : ($change < 0 ? 'negativo' : 'stabile');
        
        return '<div class="trend-item">
            <h4>Wine Club</h4>
            <p>Trend ' . $trend . ' (' . ($change > 0 ? '+' : '') . number_format($change) . ' membri)</p>
        </div>';
    }

    private function analyzeBnbOccupancyTrend(array $data): string
    {
        $current = $data['bnb']['room_occupancy'] ?? 0;
        $previous = $data['bnb']['previous_room_occupancy'] ?? 0;
        $change = $current - $previous;
        $trend = $change > 0 ? 'positivo' : ($change < 0 ? 'negativo' : 'stabile');
        
        return '<div class="trend-item">
            <h4>Occupazione B&B</h4>
            <p>Trend ' . $trend . ' (' . ($change > 0 ? '+' : '') . number_format($change, 1) . '%)</p>
        </div>';
    }

    private function analyzeWeekendTrend(array $data): string
    {
        $weekend = $data['bnb']['weekend_bookings'] ?? 0;
        $total = $data['bnb']['total_bookings'] ?? 1;
        $weekendPercentage = ($weekend / $total) * 100;
        
        return '<div class="trend-item">
            <h4>Prenotazioni Weekend</h4>
            <p>Weekend: ' . number_format($weekendPercentage, 1) . '% del totale</p>
        </div>';
    }

    private function analyzeExperienceTrend(array $data): string
    {
        $experiences = $data['bnb']['local_experiences'] ?? 0;
        $romantic = $data['bnb']['romantic_packages'] ?? 0;
        
        return '<div class="trend-item">
            <h4>Esperienze</h4>
            <p>Esperienze locali: ' . number_format($experiences) . ', Pacchetti romantici: ' . number_format($romantic) . '</p>
        </div>';
    }

    private function analyzeGeneralTrend(array $data): string
    {
        return '<div class="trend-item">
            <h4>Trend Generale</h4>
            <p>Analisi delle performance generali del periodo.</p>
        </div>';
    }

    // Anomaly detection methods
    private function detectOccupancyAnomaly(array $data): ?string
    {
        $current = $data['hospitality']['occupancy_rate'] ?? 0;
        $average = $data['hospitality']['average_occupancy_rate'] ?? 0;
        
        if (abs($current - $average) > 20) {
            return '<li>Tasso di occupazione significativamente diverso dalla media (' . number_format($current, 1) . '% vs ' . number_format($average, 1) . '%)</li>';
        }
        
        return null;
    }

    private function detectRevenueAnomaly(array $data): ?string
    {
        $current = $data['hospitality']['revenue_per_room'] ?? 0;
        $average = $data['hospitality']['average_revenue_per_room'] ?? 0;
        
        if (abs($current - $average) > 50) {
            return '<li>Ricavo per camera significativamente diverso dalla media (€' . number_format($current, 2) . ' vs €' . number_format($average, 2) . ')</li>';
        }
        
        return null;
    }

    private function detectWineSalesAnomaly(array $data): ?string
    {
        $current = $data['wine']['cellar_sales'] ?? 0;
        $average = $data['wine']['average_cellar_sales'] ?? 0;
        
        if (abs($current - $average) > 1000) {
            return '<li>Vendite cantina significativamente diverse dalla media (€' . number_format($current, 2) . ' vs €' . number_format($average, 2) . ')</li>';
        }
        
        return null;
    }

    private function detectTourismAnomaly(array $data): ?string
    {
        $current = $data['wine']['vineyard_tours'] ?? 0;
        $average = $data['wine']['average_vineyard_tours'] ?? 0;
        
        if (abs($current - $average) > 10) {
            return '<li>Tour vigneti significativamente diversi dalla media (' . number_format($current) . ' vs ' . number_format($average) . ')</li>';
        }
        
        return null;
    }

    private function detectGeneralAnomaly(array $data): ?string
    {
        return '<li>Nessuna anomalia significativa rilevata nel periodo</li>';
    }
}

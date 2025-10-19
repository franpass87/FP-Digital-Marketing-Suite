<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use function __;

/**
 * Provides pre-configured templates for common connector setups.
 */
class ConnectionTemplate
{
    /**
     * Get all available templates.
     *
     * @return array<string, array{name: string, description: string, provider: string, metrics_preset: array, dimensions_preset?: array, recommended_for: array}>
     */
    public static function getTemplates(): array
    {
        return [
            // GA4 Templates - Basic & Advanced
            'ga4_basic' => [
                'name' => __('GA4 - Configurazione Base', 'fp-dms'),
                'description' => __('Metriche essenziali: utenti, sessioni, conversioni', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => ['activeUsers', 'sessions', 'conversions', 'bounceRate'],
                'dimensions_preset' => ['date'],
                'recommended_for' => [
                    __('Blog', 'fp-dms'),
                    __('Sito Aziendale', 'fp-dms'),
                    __('Portfolio', 'fp-dms'),
                ],
                'icon' => '📊',
                'difficulty' => 'beginner',
                'category' => 'analytics',
            ],
            'ga4_ecommerce' => [
                'name' => __('GA4 - E-commerce Completo', 'fp-dms'),
                'description' => __('Tutte le metriche e-commerce: ricavi, transazioni, AOV', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'conversions',
                    'totalRevenue',
                    'transactions',
                    'averageOrderValue',
                    'itemsViewed',
                    'addToCarts',
                    'checkouts',
                    'purchaseRevenue',
                    'refundValue',
                ],
                'dimensions_preset' => ['date', 'source', 'medium', 'itemCategory'],
                'recommended_for' => [
                    __('E-commerce', 'fp-dms'),
                    __('Shop Online', 'fp-dms'),
                    __('Marketplace', 'fp-dms'),
                ],
                'icon' => '🛒',
                'difficulty' => 'intermediate',
                'category' => 'ecommerce',
            ],
            'ga4_content' => [
                'name' => __('GA4 - Content Marketing', 'fp-dms'),
                'description' => __('Metriche per editori di contenuti e blog', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'averageSessionDuration',
                    'pageViews',
                    'screenPageViewsPerSession',
                    'engagementRate',
                    'scrollDepth',
                    'timeOnPage',
                ],
                'dimensions_preset' => ['date', 'pagePath', 'source', 'contentGroup'],
                'recommended_for' => [
                    __('Blog', 'fp-dms'),
                    __('Sito di Notizie', 'fp-dms'),
                    __('Piattaforma Contenuti', 'fp-dms'),
                ],
                'icon' => '📝',
                'difficulty' => 'intermediate',
                'category' => 'content',
            ],
            'ga4_saas' => [
                'name' => __('GA4 - SaaS & Software', 'fp-dms'),
                'description' => __('Metriche ottimizzate per software e servizi SaaS', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'conversions',
                    'userEngagementDuration',
                    'screenPageViews',
                    'eventCount',
                    'conversionRate',
                    'retentionRate',
                ],
                'dimensions_preset' => ['date', 'source', 'deviceCategory', 'userType'],
                'recommended_for' => [
                    __('Software SaaS', 'fp-dms'),
                    __('Applicazioni Web', 'fp-dms'),
                    __('Servizi Digitali', 'fp-dms'),
                ],
                'icon' => '💻',
                'difficulty' => 'advanced',
                'category' => 'saas',
            ],
            'ga4_lead_generation' => [
                'name' => __('GA4 - Lead Generation', 'fp-dms'),
                'description' => __('Metriche per generazione lead e conversioni', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'conversions',
                    'conversionRate',
                    'goalCompletions',
                    'formSubmissions',
                    'downloads',
                    'contactFormSubmissions',
                ],
                'dimensions_preset' => ['date', 'source', 'medium', 'campaign'],
                'recommended_for' => [
                    __('Lead Generation', 'fp-dms'),
                    __('Servizi Professionali', 'fp-dms'),
                    __('Agenzie', 'fp-dms'),
                ],
                'icon' => '🎯',
                'difficulty' => 'intermediate',
                'category' => 'leads',
            ],
            'ga4_healthcare' => [
                'name' => __('GA4 - Sanità & Salute', 'fp-dms'),
                'description' => __('Metriche per settore sanitario e benessere', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'pageViews',
                    'averageSessionDuration',
                    'bounceRate',
                    'engagementRate',
                    'scrollDepth',
                    'formSubmissions',
                ],
                'dimensions_preset' => ['date', 'pagePath', 'source', 'deviceCategory'],
                'recommended_for' => [
                    __('Cliniche', 'fp-dms'),
                    __('Centri Medici', 'fp-dms'),
                    __('Wellness', 'fp-dms'),
                ],
                'icon' => '🏥',
                'difficulty' => 'intermediate',
                'category' => 'healthcare',
            ],
            'ga4_education' => [
                'name' => __('GA4 - Educazione & Formazione', 'fp-dms'),
                'description' => __('Metriche per scuole, università e corsi online', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'pageViews',
                    'averageSessionDuration',
                    'engagementRate',
                    'courseCompletions',
                    'enrollments',
                    'certificateDownloads',
                ],
                'dimensions_preset' => ['date', 'pagePath', 'source', 'contentGroup'],
                'recommended_for' => [
                    __('Scuole', 'fp-dms'),
                    __('Università', 'fp-dms'),
                    __('Corsi Online', 'fp-dms'),
                ],
                'icon' => '🎓',
                'difficulty' => 'intermediate',
                'category' => 'education',
            ],

            // Google Search Console Templates
            'gsc_basic' => [
                'name' => __('GSC - SEO Base', 'fp-dms'),
                'description' => __('Metriche SEO essenziali: click, impressioni, CTR, posizione', 'fp-dms'),
                'provider' => 'gsc',
                'metrics_preset' => ['clicks', 'impressions', 'ctr', 'position'],
                'dimensions_preset' => ['date', 'query'],
                'recommended_for' => [
                    __('Qualsiasi Sito Web', 'fp-dms'),
                    __('Monitoraggio SEO', 'fp-dms'),
                ],
                'icon' => '🔍',
                'difficulty' => 'beginner',
                'category' => 'seo',
            ],
            'gsc_advanced' => [
                'name' => __('GSC - SEO Avanzato', 'fp-dms'),
                'description' => __('Analisi SEO completa con pagine e dispositivi', 'fp-dms'),
                'provider' => 'gsc',
                'metrics_preset' => ['clicks', 'impressions', 'ctr', 'position'],
                'dimensions_preset' => ['date', 'query', 'page', 'device', 'country'],
                'recommended_for' => [
                    __('SEO Avanzato', 'fp-dms'),
                    __('Agenzie SEO', 'fp-dms'),
                    __('E-commerce', 'fp-dms'),
                ],
                'icon' => '🚀',
                'difficulty' => 'advanced',
                'category' => 'seo',
            ],
            'gsc_local' => [
                'name' => __('GSC - SEO Locale', 'fp-dms'),
                'description' => __('Metriche per business locali e Google My Business', 'fp-dms'),
                'provider' => 'gsc',
                'metrics_preset' => ['clicks', 'impressions', 'ctr', 'position'],
                'dimensions_preset' => ['date', 'query', 'country', 'device'],
                'recommended_for' => [
                    __('Business Locali', 'fp-dms'),
                    __('Ristoranti', 'fp-dms'),
                    __('Negozi', 'fp-dms'),
                ],
                'icon' => '📍',
                'difficulty' => 'intermediate',
                'category' => 'local',
            ],

            // Meta Ads Templates
            'meta_ads_performance' => [
                'name' => __('Meta Ads - Performance Marketing', 'fp-dms'),
                'description' => __('Metriche ottimizzate per campagne performance', 'fp-dms'),
                'provider' => 'meta_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'spend',
                    'conversions',
                    'cpc',
                    'cpm',
                    'ctr',
                    'cost_per_conversion',
                    'roas',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'adset_name'],
                'recommended_for' => [
                    __('Lead Generation', 'fp-dms'),
                    __('Campagne Vendite', 'fp-dms'),
                ],
                'icon' => '📈',
                'difficulty' => 'intermediate',
                'category' => 'advertising',
            ],
            'meta_ads_brand' => [
                'name' => __('Meta Ads - Brand Awareness', 'fp-dms'),
                'description' => __('Metriche per campagne brand e awareness', 'fp-dms'),
                'provider' => 'meta_ads',
                'metrics_preset' => [
                    'impressions',
                    'reach',
                    'frequency',
                    'cpm',
                    'video_views',
                    'video_view_rate',
                    'brand_awareness',
                    'ad_recall',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'placement'],
                'recommended_for' => [
                    __('Campagne Brand', 'fp-dms'),
                    __('Video Marketing', 'fp-dms'),
                ],
                'icon' => '🎯',
                'difficulty' => 'intermediate',
                'category' => 'advertising',
            ],
            'meta_ads_retail' => [
                'name' => __('Meta Ads - Retail & E-commerce', 'fp-dms'),
                'description' => __('Metriche per vendite retail e catalogo prodotti', 'fp-dms'),
                'provider' => 'meta_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'spend',
                    'conversions',
                    'purchase_value',
                    'roas',
                    'catalog_segment_actions',
                    'add_to_cart',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'product_id'],
                'recommended_for' => [
                    __('E-commerce', 'fp-dms'),
                    __('Retail', 'fp-dms'),
                    __('Fashion', 'fp-dms'),
                ],
                'icon' => '🛍️',
                'difficulty' => 'advanced',
                'category' => 'ecommerce',
            ],

            // Google Ads Templates
            'google_ads_search' => [
                'name' => __('Google Ads - Campagne Search', 'fp-dms'),
                'description' => __('Metriche per pubblicità su ricerca', 'fp-dms'),
                'provider' => 'google_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'cost',
                    'conversions',
                    'ctr',
                    'average_cpc',
                    'conversion_rate',
                    'cost_per_conversion',
                    'quality_score',
                ],
                'dimensions_preset' => ['date', 'campaign', 'keyword'],
                'recommended_for' => [
                    __('Campagne Search', 'fp-dms'),
                    __('PPC Marketing', 'fp-dms'),
                ],
                'icon' => '🔍',
                'difficulty' => 'intermediate',
                'category' => 'advertising',
            ],
            'google_ads_display' => [
                'name' => __('Google Ads - Display & Video', 'fp-dms'),
                'description' => __('Metriche per campagne display e video', 'fp-dms'),
                'provider' => 'google_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'cost',
                    'conversions',
                    'ctr',
                    'cpm',
                    'video_views',
                    'viewability',
                    'reach',
                ],
                'dimensions_preset' => ['date', 'campaign', 'placement'],
                'recommended_for' => [
                    __('Display Advertising', 'fp-dms'),
                    __('Video Marketing', 'fp-dms'),
                ],
                'icon' => '📺',
                'difficulty' => 'intermediate',
                'category' => 'advertising',
            ],
            'google_ads_shopping' => [
                'name' => __('Google Ads - Shopping', 'fp-dms'),
                'description' => __('Metriche per campagne Google Shopping', 'fp-dms'),
                'provider' => 'google_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'cost',
                    'conversions',
                    'conversion_value',
                    'roas',
                    'ctr',
                    'average_cpc',
                ],
                'dimensions_preset' => ['date', 'campaign', 'product_id'],
                'recommended_for' => [
                    __('Google Shopping', 'fp-dms'),
                    __('E-commerce', 'fp-dms'),
                ],
                'icon' => '🛒',
                'difficulty' => 'advanced',
                'category' => 'ecommerce',
            ],

            // LinkedIn Ads Templates
            'linkedin_ads_b2b' => [
                'name' => __('LinkedIn Ads - B2B Marketing', 'fp-dms'),
                'description' => __('Metriche per marketing B2B e lead generation', 'fp-dms'),
                'provider' => 'linkedin_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'spend',
                    'conversions',
                    'cpc',
                    'cpm',
                    'ctr',
                    'cost_per_lead',
                    'lead_quality_score',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'audience'],
                'recommended_for' => [
                    __('B2B Marketing', 'fp-dms'),
                    __('Lead Generation', 'fp-dms'),
                    __('Servizi Professionali', 'fp-dms'),
                ],
                'icon' => '💼',
                'difficulty' => 'intermediate',
                'category' => 'b2b',
            ],

            // TikTok Ads Templates
            'tiktok_ads_creative' => [
                'name' => __('TikTok Ads - Creative Marketing', 'fp-dms'),
                'description' => __('Metriche per campagne creative e video', 'fp-dms'),
                'provider' => 'tiktok_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'spend',
                    'conversions',
                    'video_views',
                    'video_completion_rate',
                    'ctr',
                    'cpm',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'creative_id'],
                'recommended_for' => [
                    __('Marketing Creativo', 'fp-dms'),
                    __('Brand Awareness', 'fp-dms'),
                    __('Gen Z Marketing', 'fp-dms'),
                ],
                'icon' => '🎵',
                'difficulty' => 'intermediate',
                'category' => 'social',
            ],

            // Hospitality Industry Templates
            'ga4_hotel' => [
                'name' => __('GA4 - Hotel & Hospitality', 'fp-dms'),
                'description' => __('Metriche complete per hotel e strutture ricettive', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'conversions',
                    'averageSessionDuration',
                    'pageViews',
                    'engagementRate',
                    'formSubmissions',
                    'scrollDepth',
                    'timeOnPage',
                    'eventCount',
                ],
                'dimensions_preset' => ['date', 'source', 'medium', 'pagePath', 'deviceCategory', 'country'],
                'recommended_for' => [
                    __('Hotel', 'fp-dms'),
                    __('Resort', 'fp-dms'),
                    __('Strutture Ricettive', 'fp-dms'),
                ],
                'icon' => '🏨',
                'difficulty' => 'intermediate',
                'category' => 'hospitality',
            ],
            'ga4_resort' => [
                'name' => __('GA4 - Resort & Luxury', 'fp-dms'),
                'description' => __('Metriche per resort di lusso e strutture premium', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'conversions',
                    'averageSessionDuration',
                    'pageViews',
                    'engagementRate',
                    'formSubmissions',
                    'scrollDepth',
                    'timeOnPage',
                    'eventCount',
                    'userEngagementDuration',
                ],
                'dimensions_preset' => ['date', 'source', 'medium', 'pagePath', 'deviceCategory', 'country', 'city'],
                'recommended_for' => [
                    __('Resort di Lusso', 'fp-dms'),
                    __('Ville Private', 'fp-dms'),
                    __('Strutture Premium', 'fp-dms'),
                ],
                'icon' => '🏖️',
                'difficulty' => 'advanced',
                'category' => 'hospitality',
            ],
            'ga4_wine_estate' => [
                'name' => __('GA4 - Aziende di Vino', 'fp-dms'),
                'description' => __('Metriche per cantine, vigneti e aziende vinicole', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'conversions',
                    'averageSessionDuration',
                    'pageViews',
                    'engagementRate',
                    'formSubmissions',
                    'scrollDepth',
                    'timeOnPage',
                    'eventCount',
                    'goalCompletions',
                ],
                'dimensions_preset' => ['date', 'source', 'medium', 'pagePath', 'deviceCategory', 'country'],
                'recommended_for' => [
                    __('Cantine', 'fp-dms'),
                    __('Vigneti', 'fp-dms'),
                    __('Aziende Vinicole', 'fp-dms'),
                    __('Wine Tourism', 'fp-dms'),
                ],
                'icon' => '🍷',
                'difficulty' => 'intermediate',
                'category' => 'wine',
            ],
            'ga4_bnb' => [
                'name' => __('GA4 - B&B & Agriturismi', 'fp-dms'),
                'description' => __('Metriche per bed & breakfast e agriturismi', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'conversions',
                    'averageSessionDuration',
                    'pageViews',
                    'engagementRate',
                    'formSubmissions',
                    'scrollDepth',
                    'timeOnPage',
                    'eventCount',
                ],
                'dimensions_preset' => ['date', 'source', 'medium', 'pagePath', 'deviceCategory'],
                'recommended_for' => [
                    __('B&B', 'fp-dms'),
                    __('Agriturismi', 'fp-dms'),
                    __('Case Vacanza', 'fp-dms'),
                    __('Turismo Rurale', 'fp-dms'),
                ],
                'icon' => '🏡',
                'difficulty' => 'beginner',
                'category' => 'bnb',
            ],

            // Meta Ads for Hospitality
            'meta_ads_hospitality' => [
                'name' => __('Meta Ads - Hospitality', 'fp-dms'),
                'description' => __('Campagne Meta per hotel, resort e strutture ricettive', 'fp-dms'),
                'provider' => 'meta_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'spend',
                    'conversions',
                    'cpc',
                    'cpm',
                    'ctr',
                    'cost_per_conversion',
                    'roas',
                    'reach',
                    'frequency',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'adset_name', 'placement'],
                'recommended_for' => [
                    __('Hotel', 'fp-dms'),
                    __('Resort', 'fp-dms'),
                    __('Strutture Ricettive', 'fp-dms'),
                ],
                'icon' => '🏨',
                'difficulty' => 'intermediate',
                'category' => 'hospitality',
            ],
            'meta_ads_wine_tourism' => [
                'name' => __('Meta Ads - Wine Tourism', 'fp-dms'),
                'description' => __('Campagne per turismo enogastronomico e cantine', 'fp-dms'),
                'provider' => 'meta_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'spend',
                    'conversions',
                    'cpc',
                    'cpm',
                    'ctr',
                    'cost_per_conversion',
                    'roas',
                    'video_views',
                    'video_view_rate',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'adset_name', 'placement'],
                'recommended_for' => [
                    __('Cantine', 'fp-dms'),
                    __('Wine Tourism', 'fp-dms'),
                    __('Eventi Enogastronomici', 'fp-dms'),
                ],
                'icon' => '🍷',
                'difficulty' => 'intermediate',
                'category' => 'wine',
            ],
            'meta_ads_bnb' => [
                'name' => __('Meta Ads - B&B & Agriturismi', 'fp-dms'),
                'description' => __('Campagne per B&B, agriturismi e turismo rurale', 'fp-dms'),
                'provider' => 'meta_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'spend',
                    'conversions',
                    'cpc',
                    'cpm',
                    'ctr',
                    'cost_per_conversion',
                    'roas',
                    'reach',
                    'frequency',
                ],
                'dimensions_preset' => ['date', 'campaign_name', 'adset_name', 'placement'],
                'recommended_for' => [
                    __('B&B', 'fp-dms'),
                    __('Agriturismi', 'fp-dms'),
                    __('Turismo Rurale', 'fp-dms'),
                ],
                'icon' => '🏡',
                'difficulty' => 'beginner',
                'category' => 'bnb',
            ],

            // Google Ads for Hospitality
            'google_ads_hospitality' => [
                'name' => __('Google Ads - Hospitality', 'fp-dms'),
                'description' => __('Campagne Google per strutture ricettive', 'fp-dms'),
                'provider' => 'google_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'cost',
                    'conversions',
                    'ctr',
                    'average_cpc',
                    'conversion_rate',
                    'cost_per_conversion',
                    'quality_score',
                ],
                'dimensions_preset' => ['date', 'campaign', 'keyword', 'device'],
                'recommended_for' => [
                    __('Hotel', 'fp-dms'),
                    __('Resort', 'fp-dms'),
                    __('Strutture Ricettive', 'fp-dms'),
                ],
                'icon' => '🏨',
                'difficulty' => 'intermediate',
                'category' => 'hospitality',
            ],
            'google_ads_wine_tourism' => [
                'name' => __('Google Ads - Wine Tourism', 'fp-dms'),
                'description' => __('Campagne Google per turismo enogastronomico', 'fp-dms'),
                'provider' => 'google_ads',
                'metrics_preset' => [
                    'impressions',
                    'clicks',
                    'cost',
                    'conversions',
                    'ctr',
                    'average_cpc',
                    'conversion_rate',
                    'cost_per_conversion',
                    'quality_score',
                ],
                'dimensions_preset' => ['date', 'campaign', 'keyword', 'device'],
                'recommended_for' => [
                    __('Cantine', 'fp-dms'),
                    __('Wine Tourism', 'fp-dms'),
                    __('Eventi Enogastronomici', 'fp-dms'),
                ],
                'icon' => '🍷',
                'difficulty' => 'intermediate',
                'category' => 'wine',
            ],
        ];
    }

    /**
     * Get templates filtered by provider.
     *
     * @param string $provider Provider type (ga4, gsc, meta_ads, etc.)
     * @return array<string, array>
     */
    public static function getTemplatesByProvider(string $provider): array
    {
        $allTemplates = self::getTemplates();

        return array_filter(
            $allTemplates,
            fn($template) => $template['provider'] === $provider
        );
    }

    /**
     * Get a specific template by ID.
     *
     * @param string $templateId Template identifier
     * @return array|null Template data or null if not found
     */
    public static function getTemplate(string $templateId): ?array
    {
        $templates = self::getTemplates();
        return $templates[$templateId] ?? null;
    }

    /**
     * Apply a template to base configuration.
     *
     * @param string $templateId Template identifier
     * @param array $baseConfig Base configuration array
     * @return array Merged configuration
     * @throws \InvalidArgumentException If template not found
     */
    public static function applyTemplate(string $templateId, array $baseConfig): array
    {
        $template = self::getTemplate($templateId);

        if (!$template) {
            throw new \InvalidArgumentException("Template not found: {$templateId}");
        }

        // Merge template presets with base config
        $result = array_merge($baseConfig, [
            'metrics' => $template['metrics_preset'],
            'template_used' => $templateId,
        ]);

        // Add dimensions if provided
        if (isset($template['dimensions_preset'])) {
            $result['dimensions'] = $template['dimensions_preset'];
        }

        return $result;
    }

    /**
     * Get templates filtered by category.
     *
     * @param string $category Category type (analytics, ecommerce, seo, etc.)
     * @return array<string, array>
     */
    public static function getTemplatesByCategory(string $category): array
    {
        $allTemplates = self::getTemplates();

        return array_filter(
            $allTemplates,
            fn($template) => ($template['category'] ?? '') === $category
        );
    }

    /**
     * Get templates filtered by difficulty level.
     *
     * @param string $difficulty Difficulty level (beginner, intermediate, advanced)
     * @return array<string, array>
     */
    public static function getTemplatesByDifficulty(string $difficulty): array
    {
        $allTemplates = self::getTemplates();

        return array_filter(
            $allTemplates,
            fn($template) => ($template['difficulty'] ?? 'beginner') === $difficulty
        );
    }

    /**
     * Get all available categories.
     *
     * @return array<string> Array of category names
     */
    public static function getCategories(): array
    {
        $allTemplates = self::getTemplates();
        $categories = [];

        foreach ($allTemplates as $template) {
            $category = $template['category'] ?? 'general';
            if (!in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Get all available difficulty levels.
     *
     * @return array<string> Array of difficulty levels
     */
    public static function getDifficultyLevels(): array
    {
        return ['beginner', 'intermediate', 'advanced'];
    }

    /**
     * Get template suggestions based on user input or context.
     *
     * @param array $context Context information (keywords, site type, etc.)
     * @return array<string> Array of suggested template IDs
     */
    public static function suggestTemplates(array $context): array
    {
        $suggestions = [];
        $keywords = $context['keywords'] ?? [];
        $siteType = $context['site_type'] ?? '';
        $businessType = $context['business_type'] ?? '';
        $difficulty = $context['difficulty'] ?? 'beginner';

        // E-commerce detection
        if (
            in_array('shop', $keywords, true) ||
            in_array('ecommerce', $keywords, true) ||
            in_array('retail', $keywords, true) ||
            $siteType === 'ecommerce' ||
            $businessType === 'ecommerce'
        ) {
            $suggestions[] = 'ga4_ecommerce';
            $suggestions[] = 'meta_ads_retail';
            $suggestions[] = 'google_ads_shopping';
        }

        // SaaS/Software detection
        if (
            in_array('saas', $keywords, true) ||
            in_array('software', $keywords, true) ||
            in_array('app', $keywords, true) ||
            $businessType === 'saas'
        ) {
            $suggestions[] = 'ga4_saas';
            $suggestions[] = 'linkedin_ads_b2b';
        }

        // Content/Blog detection
        if (
            in_array('blog', $keywords, true) ||
            in_array('content', $keywords, true) ||
            in_array('news', $keywords, true) ||
            $siteType === 'blog' ||
            $businessType === 'content'
        ) {
            $suggestions[] = 'ga4_content';
            $suggestions[] = 'gsc_basic';
        }

        // Healthcare detection
        if (
            in_array('health', $keywords, true) ||
            in_array('medical', $keywords, true) ||
            in_array('clinic', $keywords, true) ||
            $businessType === 'healthcare'
        ) {
            $suggestions[] = 'ga4_healthcare';
            $suggestions[] = 'gsc_local';
        }

        // Education detection
        if (
            in_array('education', $keywords, true) ||
            in_array('school', $keywords, true) ||
            in_array('course', $keywords, true) ||
            $businessType === 'education'
        ) {
            $suggestions[] = 'ga4_education';
            $suggestions[] = 'gsc_basic';
        }

        // B2B/Professional services detection
        if (
            in_array('b2b', $keywords, true) ||
            in_array('professional', $keywords, true) ||
            in_array('agency', $keywords, true) ||
            $businessType === 'b2b'
        ) {
            $suggestions[] = 'ga4_lead_generation';
            $suggestions[] = 'linkedin_ads_b2b';
            $suggestions[] = 'google_ads_search';
        }

        // Local business detection
        if (
            in_array('local', $keywords, true) ||
            in_array('restaurant', $keywords, true) ||
            in_array('store', $keywords, true) ||
            $businessType === 'local'
        ) {
            $suggestions[] = 'gsc_local';
            $suggestions[] = 'ga4_basic';
        }

        // Filter by difficulty if specified
        if ($difficulty !== 'beginner') {
            $filteredSuggestions = [];
            foreach ($suggestions as $templateId) {
                $template = self::getTemplate($templateId);
                if ($template && ($template['difficulty'] ?? 'beginner') === $difficulty) {
                    $filteredSuggestions[] = $templateId;
                }
            }
            if (!empty($filteredSuggestions)) {
                $suggestions = $filteredSuggestions;
            }
        }

        // Default to basic if no specific suggestions
        if (empty($suggestions)) {
            $suggestions[] = 'ga4_basic';
            $suggestions[] = 'gsc_basic';
        }

        return array_unique($suggestions);
    }

    /**
     * Validate if a configuration matches a template.
     *
     * @param array $config Configuration to validate
     * @param string $templateId Template to check against
     * @return bool True if configuration uses this template
     */
    public static function usesTemplate(array $config, string $templateId): bool
    {
        return ($config['template_used'] ?? null) === $templateId;
    }

    /**
     * Get human-readable description of template usage stats.
     *
     * @param string $templateId Template identifier
     * @return array{name: string, users: int, icon: string}
     */
    public static function getTemplateStats(string $templateId): array
    {
        $template = self::getTemplate($templateId);

        if (!$template) {
            return [
                'name' => __('Unknown', 'fp-dms'),
                'users' => 0,
                'icon' => '❓',
            ];
        }

        // In a real implementation, this would query the database
        // For now, return mock data
        return [
            'name' => $template['name'],
            'users' => 0, // Would be populated from actual usage
            'icon' => $template['icon'] ?? '📋',
        ];
    }
}

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
            'ga4_basic' => [
                'name' => __('GA4 - Basic Configuration', 'fp-dms'),
                'description' => __('Essential metrics: users, sessions, conversions', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => ['activeUsers', 'sessions', 'conversions', 'bounceRate'],
                'dimensions_preset' => ['date'],
                'recommended_for' => [
                    __('Blog', 'fp-dms'),
                    __('Corporate Website', 'fp-dms'),
                    __('Portfolio', 'fp-dms'),
                ],
                'icon' => '📊',
            ],
            'ga4_ecommerce' => [
                'name' => __('GA4 - E-commerce Complete', 'fp-dms'),
                'description' => __('All e-commerce metrics: revenue, transactions, AOV', 'fp-dms'),
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
                ],
                'dimensions_preset' => ['date', 'source', 'medium'],
                'recommended_for' => [
                    __('E-commerce', 'fp-dms'),
                    __('Online Shop', 'fp-dms'),
                ],
                'icon' => '🛒',
            ],
            'ga4_content' => [
                'name' => __('GA4 - Content Marketing', 'fp-dms'),
                'description' => __('Metrics for content publishers and blogs', 'fp-dms'),
                'provider' => 'ga4',
                'metrics_preset' => [
                    'activeUsers',
                    'sessions',
                    'averageSessionDuration',
                    'pageViews',
                    'screenPageViewsPerSession',
                    'engagementRate',
                ],
                'dimensions_preset' => ['date', 'pagePath', 'source'],
                'recommended_for' => [
                    __('Blog', 'fp-dms'),
                    __('News Site', 'fp-dms'),
                    __('Content Platform', 'fp-dms'),
                ],
                'icon' => '📝',
            ],
            'gsc_basic' => [
                'name' => __('GSC - Basic SEO', 'fp-dms'),
                'description' => __('Essential SEO metrics: clicks, impressions, CTR, position', 'fp-dms'),
                'provider' => 'gsc',
                'metrics_preset' => ['clicks', 'impressions', 'ctr', 'position'],
                'dimensions_preset' => ['date', 'query'],
                'recommended_for' => [
                    __('Any Website', 'fp-dms'),
                    __('SEO Monitoring', 'fp-dms'),
                ],
                'icon' => '🔍',
            ],
            'meta_ads_performance' => [
                'name' => __('Meta Ads - Performance Marketing', 'fp-dms'),
                'description' => __('Optimized metrics for performance campaigns', 'fp-dms'),
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
                ],
                'dimensions_preset' => ['date', 'campaign_name'],
                'recommended_for' => [
                    __('Lead Generation', 'fp-dms'),
                    __('Sales Campaigns', 'fp-dms'),
                ],
                'icon' => '📈',
            ],
            'meta_ads_brand' => [
                'name' => __('Meta Ads - Brand Awareness', 'fp-dms'),
                'description' => __('Metrics for brand and awareness campaigns', 'fp-dms'),
                'provider' => 'meta_ads',
                'metrics_preset' => [
                    'impressions',
                    'reach',
                    'frequency',
                    'cpm',
                    'video_views',
                    'video_view_rate',
                ],
                'dimensions_preset' => ['date', 'campaign_name'],
                'recommended_for' => [
                    __('Brand Campaigns', 'fp-dms'),
                    __('Video Marketing', 'fp-dms'),
                ],
                'icon' => '🎯',
            ],
            'google_ads_search' => [
                'name' => __('Google Ads - Search Campaigns', 'fp-dms'),
                'description' => __('Metrics for search advertising', 'fp-dms'),
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
                ],
                'dimensions_preset' => ['date', 'campaign'],
                'recommended_for' => [
                    __('Search Campaigns', 'fp-dms'),
                    __('PPC Marketing', 'fp-dms'),
                ],
                'icon' => '🎯',
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

        // E-commerce detection
        if (
            in_array('shop', $keywords, true) ||
            in_array('ecommerce', $keywords, true) ||
            $siteType === 'ecommerce'
        ) {
            $suggestions[] = 'ga4_ecommerce';
            $suggestions[] = 'meta_ads_performance';
        }

        // Content/Blog detection
        if (
            in_array('blog', $keywords, true) ||
            in_array('content', $keywords, true) ||
            $siteType === 'blog'
        ) {
            $suggestions[] = 'ga4_content';
            $suggestions[] = 'gsc_basic';
        }

        // Default to basic if no specific suggestions
        if (empty($suggestions)) {
            $suggestions[] = 'ga4_basic';
            $suggestions[] = 'gsc_basic';
        }

        return $suggestions;
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

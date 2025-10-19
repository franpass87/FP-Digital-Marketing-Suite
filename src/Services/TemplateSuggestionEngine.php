<?php

declare(strict_types=1);

namespace FP\DMS\Services;

use FP\DMS\Domain\Templates\TemplateBlueprints;
use FP\DMS\Services\Connectors\ConnectionTemplate;

/**
 * Intelligent template suggestion engine based on business context.
 */
class TemplateSuggestionEngine
{
    /**
     * Get comprehensive template suggestions based on business context.
     *
     * @param array $context Business context information
     * @return array{connection_templates: array<string>, report_blueprints: array<string>, confidence: float}
     */
    public static function suggestTemplates(array $context): array
    {
        $businessType = $context['business_type'] ?? '';
        $keywords = $context['keywords'] ?? [];
        $industry = $context['industry'] ?? '';
        $size = $context['size'] ?? '';
        $goals = $context['goals'] ?? [];

        $connectionTemplates = self::suggestConnectionTemplates($context);
        $reportBlueprints = self::suggestReportBlueprints($context);
        $confidence = self::calculateConfidence($context);

        return [
            'connection_templates' => $connectionTemplates,
            'report_blueprints' => $reportBlueprints,
            'confidence' => $confidence,
            'reasoning' => self::generateReasoning($context, $connectionTemplates, $reportBlueprints),
        ];
    }

    /**
     * Suggest connection templates based on context.
     *
     * @param array $context Business context
     * @return array<string> Suggested template IDs
     */
    private static function suggestConnectionTemplates(array $context): array
    {
        $suggestions = [];
        $businessType = $context['business_type'] ?? '';
        $keywords = $context['keywords'] ?? [];
        $industry = $context['industry'] ?? '';
        $goals = $context['goals'] ?? [];

        // E-commerce suggestions
        if (
            in_array('ecommerce', $keywords, true) ||
            in_array('shop', $keywords, true) ||
            in_array('retail', $keywords, true) ||
            $businessType === 'ecommerce' ||
            $industry === 'retail'
        ) {
            $suggestions[] = 'ga4_ecommerce';
            $suggestions[] = 'meta_ads_retail';
            $suggestions[] = 'google_ads_shopping';
            
            if (in_array('seo', $goals, true)) {
                $suggestions[] = 'gsc_advanced';
            }
        }

        // SaaS/Software suggestions
        if (
            in_array('saas', $keywords, true) ||
            in_array('software', $keywords, true) ||
            in_array('app', $keywords, true) ||
            $businessType === 'saas' ||
            $industry === 'technology'
        ) {
            $suggestions[] = 'ga4_saas';
            $suggestions[] = 'linkedin_ads_b2b';
            
            if (in_array('lead_generation', $goals, true)) {
                $suggestions[] = 'ga4_lead_generation';
            }
        }

        // Healthcare suggestions
        if (
            in_array('health', $keywords, true) ||
            in_array('medical', $keywords, true) ||
            in_array('clinic', $keywords, true) ||
            $businessType === 'healthcare' ||
            $industry === 'healthcare'
        ) {
            $suggestions[] = 'ga4_healthcare';
            $suggestions[] = 'gsc_local';
            
            if (in_array('local_seo', $goals, true)) {
                $suggestions[] = 'gsc_local';
            }
        }

        // Education suggestions
        if (
            in_array('education', $keywords, true) ||
            in_array('school', $keywords, true) ||
            in_array('course', $keywords, true) ||
            $businessType === 'education' ||
            $industry === 'education'
        ) {
            $suggestions[] = 'ga4_education';
            $suggestions[] = 'gsc_basic';
            
            if (in_array('content_marketing', $goals, true)) {
                $suggestions[] = 'ga4_content';
            }
        }

        // B2B/Professional services suggestions
        if (
            in_array('b2b', $keywords, true) ||
            in_array('professional', $keywords, true) ||
            in_array('agency', $keywords, true) ||
            $businessType === 'b2b' ||
            $industry === 'professional_services'
        ) {
            $suggestions[] = 'ga4_lead_generation';
            $suggestions[] = 'linkedin_ads_b2b';
            $suggestions[] = 'google_ads_search';
            
            if (in_array('brand_awareness', $goals, true)) {
                $suggestions[] = 'meta_ads_brand';
            }
        }

        // Hospitality suggestions
        if (
            in_array('hotel', $keywords, true) ||
            in_array('resort', $keywords, true) ||
            in_array('hospitality', $keywords, true) ||
            $businessType === 'hospitality' ||
            $industry === 'hospitality'
        ) {
            $suggestions[] = 'ga4_hotel';
            $suggestions[] = 'meta_ads_hospitality';
            $suggestions[] = 'google_ads_hospitality';
        }

        // Wine industry suggestions
        if (
            in_array('wine', $keywords, true) ||
            in_array('vineyard', $keywords, true) ||
            in_array('winery', $keywords, true) ||
            in_array('cantine', $keywords, true) ||
            $businessType === 'wine' ||
            $industry === 'wine'
        ) {
            $suggestions[] = 'ga4_wine_estate';
            $suggestions[] = 'meta_ads_wine_tourism';
            $suggestions[] = 'google_ads_wine_tourism';
        }

        // B&B suggestions
        if (
            in_array('bnb', $keywords, true) ||
            in_array('bed and breakfast', $keywords, true) ||
            in_array('agriturismo', $keywords, true) ||
            in_array('rural', $keywords, true) ||
            $businessType === 'bnb' ||
            $industry === 'bnb'
        ) {
            $suggestions[] = 'ga4_bnb';
            $suggestions[] = 'meta_ads_bnb';
            $suggestions[] = 'gsc_local';
        }

        // Local business suggestions
        if (
            in_array('local', $keywords, true) ||
            in_array('restaurant', $keywords, true) ||
            in_array('store', $keywords, true) ||
            $businessType === 'local' ||
            $industry === 'retail'
        ) {
            $suggestions[] = 'gsc_local';
            $suggestions[] = 'ga4_basic';
            
            if (in_array('local_ads', $goals, true)) {
                $suggestions[] = 'meta_ads_performance';
            }
        }

        // Content/Blog suggestions
        if (
            in_array('blog', $keywords, true) ||
            in_array('content', $keywords, true) ||
            in_array('news', $keywords, true) ||
            $businessType === 'content' ||
            $industry === 'media'
        ) {
            $suggestions[] = 'ga4_content';
            $suggestions[] = 'gsc_basic';
            
            if (in_array('seo', $goals, true)) {
                $suggestions[] = 'gsc_advanced';
            }
        }

        // Creative/Marketing suggestions
        if (
            in_array('creative', $keywords, true) ||
            in_array('marketing', $keywords, true) ||
            in_array('brand', $keywords, true) ||
            $industry === 'marketing'
        ) {
            $suggestions[] = 'meta_ads_brand';
            $suggestions[] = 'tiktok_ads_creative';
            $suggestions[] = 'google_ads_display';
        }

        // Default suggestions if no specific matches
        if (empty($suggestions)) {
            $suggestions = ['ga4_basic', 'gsc_basic'];
        }

        return array_unique($suggestions);
    }

    /**
     * Suggest report blueprints based on context.
     *
     * @param array $context Business context
     * @return array<string> Suggested blueprint keys
     */
    private static function suggestReportBlueprints(array $context): array
    {
        $suggestions = [];
        $businessType = $context['business_type'] ?? '';
        $keywords = $context['keywords'] ?? [];
        $industry = $context['industry'] ?? '';
        $goals = $context['goals'] ?? [];

        // E-commerce suggestions
        if (
            in_array('ecommerce', $keywords, true) ||
            in_array('shop', $keywords, true) ||
            $businessType === 'ecommerce' ||
            $industry === 'retail'
        ) {
            $suggestions[] = 'ecommerce';
        }

        // SaaS/Software suggestions
        if (
            in_array('saas', $keywords, true) ||
            in_array('software', $keywords, true) ||
            $businessType === 'saas' ||
            $industry === 'technology'
        ) {
            $suggestions[] = 'saas';
        }

        // Healthcare suggestions
        if (
            in_array('health', $keywords, true) ||
            in_array('medical', $keywords, true) ||
            $businessType === 'healthcare' ||
            $industry === 'healthcare'
        ) {
            $suggestions[] = 'healthcare';
        }

        // Education suggestions
        if (
            in_array('education', $keywords, true) ||
            in_array('school', $keywords, true) ||
            $businessType === 'education' ||
            $industry === 'education'
        ) {
            $suggestions[] = 'education';
        }

        // B2B suggestions
        if (
            in_array('b2b', $keywords, true) ||
            in_array('professional', $keywords, true) ||
            $businessType === 'b2b' ||
            $industry === 'professional_services'
        ) {
            $suggestions[] = 'b2b';
        }

        // Hospitality suggestions
        if (
            in_array('hotel', $keywords, true) ||
            in_array('resort', $keywords, true) ||
            $businessType === 'hospitality' ||
            $industry === 'hospitality'
        ) {
            $suggestions[] = 'hotel';
        }

        // Wine industry suggestions
        if (
            in_array('wine', $keywords, true) ||
            in_array('vineyard', $keywords, true) ||
            $businessType === 'wine' ||
            $industry === 'wine'
        ) {
            $suggestions[] = 'wine';
        }

        // B&B suggestions
        if (
            in_array('bnb', $keywords, true) ||
            in_array('agriturismo', $keywords, true) ||
            $businessType === 'bnb' ||
            $industry === 'bnb'
        ) {
            $suggestions[] = 'bnb';
        }

        // Local business suggestions
        if (
            in_array('local', $keywords, true) ||
            in_array('restaurant', $keywords, true) ||
            $businessType === 'local' ||
            $industry === 'retail'
        ) {
            $suggestions[] = 'local';
        }

        // Content suggestions
        if (
            in_array('blog', $keywords, true) ||
            in_array('content', $keywords, true) ||
            $businessType === 'content' ||
            $industry === 'media'
        ) {
            $suggestions[] = 'content';
        }

        // SEO-focused suggestions
        if (in_array('seo', $goals, true)) {
            $suggestions[] = 'search';
        }

        // Default suggestions
        if (empty($suggestions)) {
            $suggestions = ['balanced', 'kpi'];
        }

        return array_unique($suggestions);
    }

    /**
     * Calculate confidence score for suggestions.
     *
     * @param array $context Business context
     * @return float Confidence score (0.0 to 1.0)
     */
    private static function calculateConfidence(array $context): float
    {
        $score = 0.0;
        $maxScore = 0.0;

        // Business type match (40% weight)
        if (!empty($context['business_type'])) {
            $maxScore += 0.4;
            $score += 0.4;
        }

        // Industry match (30% weight)
        if (!empty($context['industry'])) {
            $maxScore += 0.3;
            $score += 0.3;
        }

        // Keywords match (20% weight)
        if (!empty($context['keywords'])) {
            $maxScore += 0.2;
            $score += 0.2;
        }

        // Goals match (10% weight)
        if (!empty($context['goals'])) {
            $maxScore += 0.1;
            $score += 0.1;
        }

        return $maxScore > 0 ? $score / $maxScore : 0.5;
    }

    /**
     * Generate human-readable reasoning for suggestions.
     *
     * @param array $context Business context
     * @param array $connectionTemplates Suggested connection templates
     * @param array $reportBlueprints Suggested report blueprints
     * @return string Reasoning explanation
     */
    private static function generateReasoning(array $context, array $connectionTemplates, array $reportBlueprints): string
    {
        $reasons = [];
        $businessType = $context['business_type'] ?? '';
        $industry = $context['industry'] ?? '';
        $goals = $context['goals'] ?? [];

        if ($businessType === 'ecommerce') {
            $reasons[] = 'Rilevato business e-commerce: suggeriti template per vendite online e metriche di conversione.';
        } elseif ($businessType === 'saas') {
            $reasons[] = 'Rilevato business SaaS: suggeriti template per engagement utenti e retention.';
        } elseif ($businessType === 'healthcare') {
            $reasons[] = 'Rilevato business sanitario: suggeriti template per engagement pazienti e SEO locale.';
        } elseif ($businessType === 'education') {
            $reasons[] = 'Rilevato business educativo: suggeriti template per engagement studenti e contenuti.';
        } elseif ($businessType === 'b2b') {
            $reasons[] = 'Rilevato business B2B: suggeriti template per lead generation e marketing professionale.';
        } elseif ($businessType === 'local') {
            $reasons[] = 'Rilevato business locale: suggeriti template per SEO locale e visibilità geografica.';
        } elseif ($businessType === 'content') {
            $reasons[] = 'Rilevato business di contenuti: suggeriti template per engagement e content marketing.';
        }

        if (!empty($industry)) {
            $reasons[] = "Settore identificato: {$industry}.";
        }

        if (!empty($goals)) {
            $goalsText = implode(', ', $goals);
            $reasons[] = "Obiettivi rilevati: {$goalsText}.";
        }

        if (empty($reasons)) {
            $reasons[] = 'Suggerimenti basati su template generici per iniziare.';
        }

        return implode(' ', $reasons);
    }

    /**
     * Get template recommendations with explanations.
     *
     * @param array $context Business context
     * @return array{recommendations: array, explanations: array}
     */
    public static function getRecommendationsWithExplanations(array $context): array
    {
        $suggestions = self::suggestTemplates($context);
        $recommendations = [];
        $explanations = [];

        // Connection template recommendations
        foreach ($suggestions['connection_templates'] as $templateId) {
            $template = ConnectionTemplate::getTemplate($templateId);
            if ($template) {
                $recommendations['connection_templates'][] = [
                    'id' => $templateId,
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'icon' => $template['icon'] ?? '📋',
                    'difficulty' => $template['difficulty'] ?? 'beginner',
                    'category' => $template['category'] ?? 'general',
                ];
            }
        }

        // Report blueprint recommendations
        foreach ($suggestions['report_blueprints'] as $blueprintKey) {
            $blueprint = TemplateBlueprints::find($blueprintKey);
            if ($blueprint) {
                $recommendations['report_blueprints'][] = [
                    'key' => $blueprintKey,
                    'name' => $blueprint->name,
                    'description' => $blueprint->description,
                ];
            }
        }

        $explanations['reasoning'] = $suggestions['reasoning'];
        $explanations['confidence'] = $suggestions['confidence'];

        return [
            'recommendations' => $recommendations,
            'explanations' => $explanations,
        ];
    }
}

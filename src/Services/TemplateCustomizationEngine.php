<?php

declare(strict_types=1);

namespace FP\DMS\Services;

use FP\DMS\Services\Connectors\ConnectionTemplate;
use FP\DMS\Domain\Templates\TemplateBlueprints;

/**
 * Advanced template customization engine.
 */
class TemplateCustomizationEngine
{
    /**
     * Create a custom template based on user preferences.
     *
     * @param array $baseTemplate Base template to customize
     * @param array $customizations User customizations
     * @return array Customized template
     */
    public static function customizeTemplate(array $baseTemplate, array $customizations): array
    {
        $customized = $baseTemplate;

        // Customize metrics
        if (isset($customizations['metrics'])) {
            $customized['metrics_preset'] = self::customizeMetrics(
                $baseTemplate['metrics_preset'] ?? [],
                $customizations['metrics']
            );
        }

        // Customize dimensions
        if (isset($customizations['dimensions'])) {
            $customized['dimensions_preset'] = self::customizeDimensions(
                $baseTemplate['dimensions_preset'] ?? [],
                $customizations['dimensions']
            );
        }

        // Customize name and description
        if (isset($customizations['name'])) {
            $customized['name'] = $customizations['name'];
        }

        if (isset($customizations['description'])) {
            $customized['description'] = $customizations['description'];
        }

        // Add custom tags
        if (isset($customizations['tags'])) {
            $customized['recommended_for'] = array_merge(
                $baseTemplate['recommended_for'] ?? [],
                $customizations['tags']
            );
        }

        // Mark as customized
        $customized['customized'] = true;
        $customized['base_template'] = $baseTemplate['name'] ?? '';

        return $customized;
    }

    /**
     * Customize metrics based on user preferences.
     *
     * @param array $baseMetrics Base metrics array
     * @param array $customizations Metric customizations
     * @return array Customized metrics
     */
    private static function customizeMetrics(array $baseMetrics, array $customizations): array
    {
        $customized = $baseMetrics;

        // Add new metrics
        if (isset($customizations['add'])) {
            $customized = array_merge($customized, $customizations['add']);
        }

        // Remove metrics
        if (isset($customizations['remove'])) {
            $customized = array_diff($customized, $customizations['remove']);
        }

        // Replace metrics
        if (isset($customizations['replace'])) {
            foreach ($customizations['replace'] as $old => $new) {
                $key = array_search($old, $customized, true);
                if ($key !== false) {
                    $customized[$key] = $new;
                }
            }
        }

        // Reorder metrics
        if (isset($customizations['reorder'])) {
            $customized = self::reorderArray($customized, $customizations['reorder']);
        }

        return array_unique(array_values($customized));
    }

    /**
     * Customize dimensions based on user preferences.
     *
     * @param array $baseDimensions Base dimensions array
     * @param array $customizations Dimension customizations
     * @return array Customized dimensions
     */
    private static function customizeDimensions(array $baseDimensions, array $customizations): array
    {
        $customized = $baseDimensions;

        // Add new dimensions
        if (isset($customizations['add'])) {
            $customized = array_merge($customized, $customizations['add']);
        }

        // Remove dimensions
        if (isset($customizations['remove'])) {
            $customized = array_diff($customized, $customizations['remove']);
        }

        // Replace dimensions
        if (isset($customizations['replace'])) {
            foreach ($customizations['replace'] as $old => $new) {
                $key = array_search($old, $customized, true);
                if ($key !== false) {
                    $customized[$key] = $new;
                }
            }
        }

        // Reorder dimensions
        if (isset($customizations['reorder'])) {
            $customized = self::reorderArray($customized, $customizations['reorder']);
        }

        return array_unique(array_values($customized));
    }

    /**
     * Reorder array based on specified order.
     *
     * @param array $array Array to reorder
     * @param array $order Desired order
     * @return array Reordered array
     */
    private static function reorderArray(array $array, array $order): array
    {
        $reordered = [];
        $remaining = $array;

        // Add items in specified order
        foreach ($order as $item) {
            if (in_array($item, $remaining, true)) {
                $reordered[] = $item;
                $remaining = array_diff($remaining, [$item]);
            }
        }

        // Add remaining items
        return array_merge($reordered, $remaining);
    }

    /**
     * Get available metrics for a provider.
     *
     * @param string $provider Provider name
     * @return array Available metrics
     */
    public static function getAvailableMetrics(string $provider): array
    {
        $metrics = [
            'ga4' => [
                'activeUsers' => 'Utenti Attivi',
                'sessions' => 'Sessioni',
                'conversions' => 'Conversioni',
                'bounceRate' => 'Tasso di Rimbalzo',
                'averageSessionDuration' => 'Durata Media Sessione',
                'pageViews' => 'Visualizzazioni Pagina',
                'screenPageViewsPerSession' => 'Pagine per Sessione',
                'engagementRate' => 'Tasso di Coinvolgimento',
                'totalRevenue' => 'Ricavi Totali',
                'transactions' => 'Transazioni',
                'averageOrderValue' => 'Valore Medio Ordine',
                'itemsViewed' => 'Prodotti Visualizzati',
                'addToCarts' => 'Aggiunte al Carrello',
                'checkouts' => 'Checkout',
                'purchaseRevenue' => 'Ricavi da Acquisti',
                'refundValue' => 'Valore Rimborsi',
                'userEngagementDuration' => 'Durata Coinvolgimento Utente',
                'eventCount' => 'Conteggio Eventi',
                'conversionRate' => 'Tasso di Conversione',
                'retentionRate' => 'Tasso di Ritenzione',
                'goalCompletions' => 'Completamenti Obiettivo',
                'formSubmissions' => 'Invii Modulo',
                'downloads' => 'Download',
                'contactFormSubmissions' => 'Invii Modulo Contatto',
                'courseCompletions' => 'Completamenti Corso',
                'enrollments' => 'Iscrizioni',
                'certificateDownloads' => 'Download Certificati',
                'scrollDepth' => 'Profondità Scroll',
                'timeOnPage' => 'Tempo su Pagina',
            ],
            'gsc' => [
                'clicks' => 'Click',
                'impressions' => 'Impresse',
                'ctr' => 'CTR',
                'position' => 'Posizione',
            ],
            'meta_ads' => [
                'impressions' => 'Impresse',
                'clicks' => 'Click',
                'spend' => 'Spesa',
                'conversions' => 'Conversioni',
                'cpc' => 'CPC',
                'cpm' => 'CPM',
                'ctr' => 'CTR',
                'cost_per_conversion' => 'Costo per Conversione',
                'roas' => 'ROAS',
                'reach' => 'Copertura',
                'frequency' => 'Frequenza',
                'video_views' => 'Visualizzazioni Video',
                'video_view_rate' => 'Tasso Visualizzazione Video',
                'brand_awareness' => 'Brand Awareness',
                'ad_recall' => 'Ricordo Pubblicità',
                'purchase_value' => 'Valore Acquisti',
                'catalog_segment_actions' => 'Azioni Segmento Catalogo',
                'add_to_cart' => 'Aggiunta al Carrello',
            ],
            'google_ads' => [
                'impressions' => 'Impresse',
                'clicks' => 'Click',
                'cost' => 'Costo',
                'conversions' => 'Conversioni',
                'ctr' => 'CTR',
                'average_cpc' => 'CPC Medio',
                'conversion_rate' => 'Tasso di Conversione',
                'cost_per_conversion' => 'Costo per Conversione',
                'quality_score' => 'Quality Score',
                'cpm' => 'CPM',
                'video_views' => 'Visualizzazioni Video',
                'viewability' => 'Visualizzabilità',
                'reach' => 'Copertura',
                'conversion_value' => 'Valore Conversione',
            ],
            'linkedin_ads' => [
                'impressions' => 'Impresse',
                'clicks' => 'Click',
                'spend' => 'Spesa',
                'conversions' => 'Conversioni',
                'cpc' => 'CPC',
                'cpm' => 'CPM',
                'ctr' => 'CTR',
                'cost_per_lead' => 'Costo per Lead',
                'lead_quality_score' => 'Punteggio Qualità Lead',
            ],
            'tiktok_ads' => [
                'impressions' => 'Impresse',
                'clicks' => 'Click',
                'spend' => 'Spesa',
                'conversions' => 'Conversioni',
                'video_views' => 'Visualizzazioni Video',
                'video_completion_rate' => 'Tasso Completamento Video',
                'ctr' => 'CTR',
                'cpm' => 'CPM',
            ],
        ];

        return $metrics[$provider] ?? [];
    }

    /**
     * Get available dimensions for a provider.
     *
     * @param string $provider Provider name
     * @return array Available dimensions
     */
    public static function getAvailableDimensions(string $provider): array
    {
        $dimensions = [
            'ga4' => [
                'date' => 'Data',
                'source' => 'Sorgente',
                'medium' => 'Medium',
                'campaign' => 'Campagna',
                'pagePath' => 'Percorso Pagina',
                'contentGroup' => 'Gruppo Contenuti',
                'deviceCategory' => 'Categoria Dispositivo',
                'userType' => 'Tipo Utente',
                'itemCategory' => 'Categoria Prodotto',
                'country' => 'Paese',
                'city' => 'Città',
                'language' => 'Lingua',
                'browser' => 'Browser',
                'operatingSystem' => 'Sistema Operativo',
            ],
            'gsc' => [
                'date' => 'Data',
                'query' => 'Query',
                'page' => 'Pagina',
                'device' => 'Dispositivo',
                'country' => 'Paese',
            ],
            'meta_ads' => [
                'date' => 'Data',
                'campaign_name' => 'Nome Campagna',
                'adset_name' => 'Nome Adset',
                'placement' => 'Posizionamento',
                'product_id' => 'ID Prodotto',
            ],
            'google_ads' => [
                'date' => 'Data',
                'campaign' => 'Campagna',
                'keyword' => 'Parola Chiave',
                'placement' => 'Posizionamento',
                'product_id' => 'ID Prodotto',
            ],
            'linkedin_ads' => [
                'date' => 'Data',
                'campaign_name' => 'Nome Campagna',
                'audience' => 'Audience',
            ],
            'tiktok_ads' => [
                'date' => 'Data',
                'campaign_name' => 'Nome Campagna',
                'creative_id' => 'ID Creativo',
            ],
        ];

        return $dimensions[$provider] ?? [];
    }

    /**
     * Validate custom template configuration.
     *
     * @param array $template Template configuration
     * @return array{valid: bool, errors: array}
     */
    public static function validateCustomTemplate(array $template): array
    {
        $errors = [];

        // Check required fields
        if (empty($template['name'])) {
            $errors[] = 'Il nome del template è obbligatorio.';
        }

        if (empty($template['metrics_preset'])) {
            $errors[] = 'Almeno una metrica deve essere selezionata.';
        }

        // Check metrics validity
        if (isset($template['metrics_preset'])) {
            $provider = $template['provider'] ?? '';
            $availableMetrics = self::getAvailableMetrics($provider);
            
            foreach ($template['metrics_preset'] as $metric) {
                if (!array_key_exists($metric, $availableMetrics)) {
                    $errors[] = "La metrica '{$metric}' non è valida per il provider '{$provider}'.";
                }
            }
        }

        // Check dimensions validity
        if (isset($template['dimensions_preset'])) {
            $provider = $template['provider'] ?? '';
            $availableDimensions = self::getAvailableDimensions($provider);
            
            foreach ($template['dimensions_preset'] as $dimension) {
                if (!array_key_exists($dimension, $availableDimensions)) {
                    $errors[] = "La dimensione '{$dimension}' non è valida per il provider '{$provider}'.";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Save custom template.
     *
     * @param array $template Template configuration
     * @param int $userId User ID
     * @return array{success: bool, template_id?: string, error?: string}
     */
    public static function saveCustomTemplate(array $template, int $userId): array
    {
        $validation = self::validateCustomTemplate($template);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => implode(' ', $validation['errors']),
            ];
        }

        // Generate unique template ID
        $templateId = 'custom_' . $userId . '_' . time();

        // Add metadata
        $template['id'] = $templateId;
        $template['user_id'] = $userId;
        $template['created_at'] = current_time('mysql');
        $template['custom'] = true;

        // In a real implementation, this would save to database
        // For now, we'll simulate success
        return [
            'success' => true,
            'template_id' => $templateId,
        ];
    }

    /**
     * Get user's custom templates.
     *
     * @param int $userId User ID
     * @return array Custom templates
     */
    public static function getUserCustomTemplates(int $userId): array
    {
        // In a real implementation, this would query the database
        // For now, return empty array
        return [];
    }

    /**
     * Clone a template with customizations.
     *
     * @param string $templateId Template ID to clone
     * @param array $customizations Customizations to apply
     * @param int $userId User ID
     * @return array{success: bool, template_id?: string, error?: string}
     */
    public static function cloneTemplate(string $templateId, array $customizations, int $userId): array
    {
        $baseTemplate = ConnectionTemplate::getTemplate($templateId);
        
        if (!$baseTemplate) {
            return [
                'success' => false,
                'error' => 'Template non trovato.',
            ];
        }

        $customized = self::customizeTemplate($baseTemplate, $customizations);
        return self::saveCustomTemplate($customized, $userId);
    }

    /**
     * Get template comparison data.
     *
     * @param array $templateIds Template IDs to compare
     * @return array Comparison data
     */
    public static function compareTemplates(array $templateIds): array
    {
        $templates = [];
        $allMetrics = [];
        $allDimensions = [];

        foreach ($templateIds as $templateId) {
            $template = ConnectionTemplate::getTemplate($templateId);
            if ($template) {
                $templates[$templateId] = $template;
                $allMetrics = array_merge($allMetrics, $template['metrics_preset'] ?? []);
                $allDimensions = array_merge($allDimensions, $template['dimensions_preset'] ?? []);
            }
        }

        $allMetrics = array_unique($allMetrics);
        $allDimensions = array_unique($allDimensions);

        return [
            'templates' => $templates,
            'all_metrics' => $allMetrics,
            'all_dimensions' => $allDimensions,
        ];
    }
}

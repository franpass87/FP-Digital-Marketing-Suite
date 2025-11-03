<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Ajax;

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Support\Options;
use FP\DMS\Support\Wp;

final class TemplatePreviewHandler
{
    public static function register(): void
    {
        add_action('wp_ajax_fpdms_preview_template', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        // Verifica nonce
        $nonce = Wp::sanitizeTextField($_POST['nonce'] ?? '');
        if (! wp_verify_nonce($nonce, 'fpdms_template_preview')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'fp-dms')]);
            return;
        }

        // Verifica permessi
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'fp-dms')]);
            return;
        }

        $content = Wp::sanitizeTextarea($_POST['content'] ?? '');
        $name = Wp::sanitizeTextField($_POST['name'] ?? '');
        $clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;

        // Renderizza la preview
        $rendered = self::renderPreview($content, $clientId);

        wp_send_json_success($rendered);
    }

    /**
     * Renderizza la preview del template
     *
     * @param string $content Contenuto del template
     * @param int $clientId ID del cliente
     * @return array{rendered_content: string, logo_html: string, client_logo_html: string, footer_html: string}
     */
    private static function renderPreview(string $content, int $clientId): array
    {
        // Ottieni il logo del plugin dalle settings
        $settings = Options::get('fpdms_settings', []);
        $logoUrl = $settings['pdf_branding']['logo_url'] ?? '';
        $primaryColor = $settings['pdf_branding']['primary_color'] ?? '#2271b1';
        $footerText = $settings['pdf_branding']['footer_text'] ?? '';

        // Ottieni i dati del cliente se selezionato
        $client = null;
        $clientLogoUrl = '';
        if ($clientId > 0) {
            $clientsRepo = new ClientsRepo();
            $client = $clientsRepo->find($clientId);
            if ($client && $client->logoId) {
                $clientLogoUrl = wp_get_attachment_image_url($client->logoId, 'medium');
                if (! is_string($clientLogoUrl)) {
                    $clientLogoUrl = '';
                }
            }
        }

        // Logo HTML
        $logoHtml = '';
        if ($logoUrl !== '') {
            $logoHtml = '<img src="' . esc_url($logoUrl) . '" alt="' . esc_attr__('Company Logo', 'fp-dms') . '" class="fpdms-preview-logo">';
        } else {
            $logoHtml = '<div class="fpdms-preview-logo-placeholder">' . esc_html__('Logo aziendale', 'fp-dms') . '</div>';
        }

        // Client Logo HTML
        $clientLogoHtml = '';
        if ($clientLogoUrl !== '') {
            $clientName = $client ? $client->name : '';
            $clientLogoHtml = '<img src="' . esc_url($clientLogoUrl) . '" alt="' . esc_attr($clientName) . '" class="fpdms-preview-client-logo">';
        } else {
            $clientLogoHtml = '<div class="fpdms-preview-client-logo-placeholder">' . esc_html__('Logo cliente', 'fp-dms') . '</div>';
        }

        // Footer HTML
        $footerHtml = '';
        if ($footerText !== '') {
            $footerHtml = wp_kses_post($footerText);
        }

        // Processa i placeholder nel contenuto
        $renderedContent = self::processPlaceholders($content, $client);

        return [
            'rendered_content' => $renderedContent,
            'logo_html' => $logoHtml,
            'client_logo_html' => $clientLogoHtml,
            'footer_html' => $footerHtml,
        ];
    }

    /**
     * Processa i placeholder nel contenuto
     *
     * @param string $content Contenuto con placeholder
     * @param \FP\DMS\Domain\Entities\Client|null $client Cliente selezionato
     * @return string Contenuto processato
     */
    private static function processPlaceholders(string $content, $client): string
    {
        // Dati di esempio per la preview
        $placeholders = [
            // Cliente
            '{{client.name}}' => $client ? $client->name : '<span class="fpdms-placeholder">Nome Cliente</span>',
            
            // Periodo
            '{{period.start}}' => date('d/m/Y', strtotime('-30 days')),
            '{{period.end}}' => date('d/m/Y'),
            
            // KPI GA4 - Dati di esempio
            '{{kpi.ga4.users|number}}' => '12,543',
            '{{kpi.ga4.sessions|number}}' => '18,234',
            '{{kpi.ga4.pageviews|number}}' => '45,678',
            '{{kpi.ga4.events|number}}' => '89,234',
            '{{kpi.ga4.new_users|number}}' => '8,456',
            '{{kpi.ga4.total_users|number}}' => '12,543',
            '{{kpi.ga4.activeUsers|number}}' => '9,876',
            '{{kpi.ga4.averageSessionDuration|duration}}' => '3m 24s',
            '{{kpi.ga4.engagementRate|percentage}}' => '64.5%',
            '{{kpi.ga4.conversionRate|percentage}}' => '3.2%',
            '{{kpi.ga4.totalRevenue|currency}}' => '€ 45,678',
            
            // KPI GSC - Dati di esempio
            '{{kpi.gsc.clicks|number}}' => '5,432',
            '{{kpi.gsc.impressions|number}}' => '123,456',
            '{{kpi.gsc.ctr|percentage}}' => '4.4%',
            '{{kpi.gsc.position|number}}' => '12.3',
            
            // KPI Google Ads - Dati di esempio
            '{{kpi.google_ads.clicks|number}}' => '1,234',
            '{{kpi.google_ads.impressions|number}}' => '45,678',
            '{{kpi.google_ads.cost|number}}' => '2,345',
            '{{kpi.google_ads.conversions|number}}' => '89',
            '{{kpi.google_ads.cost_per_conversion|currency}}' => '€ 26.35',
            
            // KPI Meta Ads - Dati di esempio
            '{{kpi.meta_ads.clicks|number}}' => '2,345',
            '{{kpi.meta_ads.impressions|number}}' => '67,890',
            '{{kpi.meta_ads.cost|number}}' => '1,234',
            '{{kpi.meta_ads.conversions|number}}' => '56',
            '{{kpi.meta_ads.revenue|number}}' => '3,456',
            
            // Sezioni dinamiche - Placeholder per demo
            '{{sections.kpi|raw}}' => '<div style="background:#f8fafc;padding:16px;border-radius:6px;margin:20px 0;"><em style="color:#64748b;">📊 Sezione KPI automatica verrà inserita qui</em></div>',
            '{{sections.trends|raw}}' => '<div style="background:#f0fdf4;padding:16px;border-radius:6px;margin:20px 0;"><em style="color:#15803d;">📈 Grafici trend verranno inseriti qui</em></div>',
            '{{sections.gsc|raw}}' => '<div style="background:#eff6ff;padding:16px;border-radius:6px;margin:20px 0;"><em style="color:#1e40af;">🔍 Dati Search Console verranno inseriti qui</em></div>',
            '{{sections.anomalies|raw}}' => '<div style="background:#fef3c7;padding:16px;border-radius:6px;margin:20px 0;"><em style="color:#92400e;">⚠️ Anomalie rilevate verranno mostrate qui</em></div>',
            
            // AI placeholders
            '{{ai.executive_summary|raw}}' => '<p style="color:#4b5563;line-height:1.8;"><em>Il sommario esecutivo generato dall\'AI apparirà qui con un\'analisi professionale delle performance del periodo.</em></p>',
            '{{ai.trend_analysis|raw}}' => '<p style="color:#4b5563;line-height:1.8;"><em>L\'analisi dei trend generata dall\'AI mostrerà i pattern identificati nei dati.</em></p>',
            '{{ai.anomaly_explanation|raw}}' => '<p style="color:#4b5563;line-height:1.8;"><em>La spiegazione delle anomalie generata dall\'AI fornirà contesto e possibili cause.</em></p>',
            '{{ai.recommendations|raw}}' => '<ul style="color:#4b5563;line-height:1.8;"><li><em>Raccomandazione 1 dall\'AI</em></li><li><em>Raccomandazione 2 dall\'AI</em></li><li><em>Raccomandazione 3 dall\'AI</em></li></ul>',
        ];

        // Sostituisci i placeholder
        $processed = str_replace(array_keys($placeholders), array_values($placeholders), $content);

        // Processa eventuali placeholder rimanenti (evidenziali come placeholder)
        $processed = preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function ($matches) {
                return '<span class="fpdms-placeholder">' . esc_html($matches[0]) . '</span>';
            },
            $processed
        );

        return $processed;
    }
}


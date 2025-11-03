<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Shared;

use function esc_attr;
use function esc_html;
use function esc_url;

/**
 * Help Icon Component
 * 
 * Icona "?" con tooltip per aiuto contestuale
 */
class HelpIcon
{
    /**
     * Render help icon with tooltip
     *
     * @param array{
     *     text: string,
     *     link?: string,
     *     position?: string
     * } $config
     */
    public static function render(array $config): void
    {
        $text = $config['text'];
        $link = $config['link'] ?? '';
        $position = $config['position'] ?? 'top'; // top, bottom, left, right
        
        $uniqueId = 'fpdms-help-' . md5($text);
        
        echo '<span class="fpdms-help-icon" data-tooltip-id="' . esc_attr($uniqueId) . '">';
        echo '<span class="dashicons dashicons-editor-help"></span>';
        
        echo '<span class="fpdms-help-tooltip fpdms-tooltip-' . esc_attr($position) . '" id="' . esc_attr($uniqueId) . '">';
        echo '<span class="fpdms-tooltip-content">' . esc_html($text) . '</span>';
        
        if ($link) {
            echo '<a href="' . esc_url($link) . '" class="fpdms-tooltip-link" target="_blank" rel="noopener">';
            echo esc_html__('Scopri di più →', 'fp-dms');
            echo '</a>';
        }
        
        echo '</span>';
        echo '</span>';
        
        // Inline CSS and JS (only first time)
        self::renderAssets();
    }
    
    /**
     * Render inline assets
     */
    private static function renderAssets(): void
    {
        static $assetsRendered = false;
        
        if ($assetsRendered) {
            return;
        }
        
        $assetsRendered = true;
        
        echo '<style>
        .fpdms-help-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: help;
            position: relative;
            margin-left: 6px;
            vertical-align: middle;
        }
        
        .fpdms-help-icon .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
            color: #9ca3af;
            transition: all 0.2s;
        }
        
        .fpdms-help-icon:hover .dashicons {
            color: #667eea;
            transform: scale(1.1);
        }
        
        .fpdms-help-tooltip {
            position: absolute;
            z-index: 10000;
            background: #1f2937;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            width: 280px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
            pointer-events: none;
        }
        
        .fpdms-help-icon:hover .fpdms-help-tooltip {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        
        /* Tooltip positions */
        .fpdms-tooltip-top {
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-8px);
            margin-bottom: 8px;
        }
        
        .fpdms-tooltip-top::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #1f2937;
        }
        
        .fpdms-tooltip-bottom {
            top: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(8px);
            margin-top: 8px;
        }
        
        .fpdms-tooltip-bottom::after {
            content: "";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-bottom-color: #1f2937;
        }
        
        .fpdms-tooltip-left {
            right: 100%;
            top: 50%;
            transform: translateY(-50%) translateX(-8px);
            margin-right: 8px;
        }
        
        .fpdms-tooltip-left::after {
            content: "";
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-left-color: #1f2937;
        }
        
        .fpdms-tooltip-right {
            left: 100%;
            top: 50%;
            transform: translateY(-50%) translateX(8px);
            margin-left: 8px;
        }
        
        .fpdms-tooltip-right::after {
            content: "";
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-right-color: #1f2937;
        }
        
        .fpdms-tooltip-content {
            display: block;
            margin-bottom: 8px;
        }
        
        .fpdms-tooltip-link {
            display: inline-block;
            color: #93c5fd;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            margin-top: 4px;
            transition: color 0.2s;
        }
        
        .fpdms-tooltip-link:hover {
            color: white;
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fpdms-help-tooltip {
                width: 220px;
                font-size: 12px;
                padding: 10px 12px;
            }
        }
        </style>';
    }
    
    /**
     * Helper: Common help texts for various sections
     *
     * @param string $section
     * @return array{text: string, link?: string}|null
     */
    public static function getCommonHelp(string $section): ?array
    {
        $helps = [
            'clients' => [
                'text' => __('I clienti rappresentano le aziende per cui generi report. Ogni cliente può avere connessioni dati, template e schedule personalizzati.', 'fp-dms'),
                'link' => 'https://docs.francescopasseri.com/fp-dms/clients'
            ],
            'datasources' => [
                'text' => __('Le connessioni dati collegano il plugin a GA4, Google Ads, Meta Ads e altre piattaforme per raccogliere metriche automaticamente.', 'fp-dms'),
                'link' => 'https://docs.francescopasseri.com/fp-dms/connectors'
            ],
            'templates' => [
                'text' => __('I template definiscono il layout e il contenuto dei tuoi report PDF. Supportano HTML personalizzato e variabili dinamiche.', 'fp-dms'),
                'link' => 'https://docs.francescopasseri.com/fp-dms/templates'
            ],
            'schedules' => [
                'text' => __('Gli schedule automatizzano la generazione e invio di report a intervalli regolari (giornaliero, settimanale, mensile).', 'fp-dms'),
                'link' => 'https://docs.francescopasseri.com/fp-dms/schedules'
            ],
            'anomalies' => [
                'text' => __('Il sistema rileva automaticamente anomalie nelle metriche (cali improvvisi, picchi anomali) e ti notifica via email/Slack/Teams.', 'fp-dms'),
                'link' => 'https://docs.francescopasseri.com/fp-dms/anomalies'
            ],
            'ai_insights' => [
                'text' => __('L\'AI analizza i dati e genera automaticamente executive summary, trend analysis e raccomandazioni strategiche in italiano.', 'fp-dms'),
                'link' => 'https://docs.francescopasseri.com/fp-dms/ai'
            ],
            'overview' => [
                'text' => __('La dashboard Overview mostra metriche in tempo reale aggregate da tutte le tue sorgenti dati, con possibilità di filtrare per cliente e periodo.', 'fp-dms'),
                'link' => 'https://docs.francescopasseri.com/fp-dms/overview'
            ],
        ];
        
        return $helps[$section] ?? null;
    }
}


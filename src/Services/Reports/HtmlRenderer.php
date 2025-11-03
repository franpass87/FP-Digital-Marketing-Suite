<?php

declare(strict_types=1);

namespace FP\DMS\Services\Reports;

use FP\DMS\Domain\Entities\Template;
use FP\DMS\Support\Arr;
use FP\DMS\Support\I18n;
use FP\DMS\Support\Wp;

class HtmlRenderer
{
    public function __construct(private TokenEngine $tokens)
    {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function render(Template $template, array $context): string
    {
        $sections = $this->buildSections($context);
        $renderContext = $context;
        $renderContext['sections'] = $sections;

        $content = $template->content;
        $body = '';
        if (is_string($content) && trim($content) !== '') {
            $body = $this->tokens->render($content, $renderContext);
        }

        if (trim(strip_tags($body)) === '') {
            $body = implode('', $sections);
        }

        if (! empty($context['report']['empty'])) {
            $emptyMessage = (string) ($context['report']['empty_message'] ?? I18n::__('No data available for this period.'));
            $body .= '<div class="fpdms-empty">' . Wp::escHtml($emptyMessage) . '</div>';
        }

        $color = Wp::sanitizeHexColor($context['branding']['primary_color'] ?? '#1d4ed8') ?: '#1d4ed8';
        $logo = Wp::escUrl($context['client']['logo_url'] ?? ($context['branding']['logo_url'] ?? ''));
        $footer = Wp::ksesPost($context['branding']['footer_text'] ?? '');

        $logoHtml = $logo !== '' ? '<img src="' . $logo . '" alt="logo" style="max-width:200px;">' : '';

        $ebGaramondUrl = 'https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap';
        
        return '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="' . $ebGaramondUrl . '" rel="stylesheet"><style>' .
            '@page{size:A4;margin:20mm;}' .
            'body{font-family:"EB Garamond",Georgia,serif;color:#1a1a1a;margin:0;padding:40px;background:#ffffff;font-size:14pt;line-height:1.6;}' .
            'h1{font-family:"EB Garamond",Georgia,serif;color:' . Wp::escAttr($color) . ';margin:0 0 8px 0;font-size:32pt;font-weight:700;letter-spacing:-0.5px;}' .
            'h2{font-family:"EB Garamond",Georgia,serif;color:' . Wp::escAttr($color) . ';margin:24px 0 12px 0;font-size:20pt;font-weight:600;border-bottom:2px solid ' . Wp::escAttr($color) . ';padding-bottom:8px;}' .
            'h3{font-family:"EB Garamond",Georgia,serif;color:#2c2c2c;margin:16px 0 8px 0;font-size:16pt;font-weight:600;}' .
            '.report-cover{background:#ffffff;padding:60px 40px;text-align:center;border-bottom:4px solid ' . Wp::escAttr($color) . ';margin:-40px -40px 40px -40px;page-break-after:always;}' .
            '.report-cover h1{font-size:42pt;margin-bottom:12px;}' .
            '.report-cover p{font-size:16pt;color:#666;font-style:italic;margin-top:16px;}' .
            '.report-cover img{max-width:220px;height:auto;margin-bottom:32px;}' .
            '.section{background:#ffffff;padding:32px 0;margin-bottom:32px;page-break-inside:avoid;}' .
            '.section:not(:last-child){border-bottom:1px solid #e5e5e5;}' .
            '.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-top:20px;}' .
            '.kpi{padding:20px;border-radius:8px;background:#f9fafb;border-left:4px solid ' . Wp::escAttr($color) . ';}' .
            '.kpi span{display:block;font-size:11pt;color:#666;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;font-weight:500;}' .
            '.kpi strong{font-size:24pt;color:#1a1a1a;font-weight:700;}' .
            '.trend-table{width:100%;border-collapse:collapse;margin-top:20px;font-size:12pt;}' .
            '.trend-table thead{background:#f5f5f5;border-bottom:2px solid ' . Wp::escAttr($color) . ';}' .
            '.trend-table th{padding:12px 16px;text-align:left;font-weight:600;color:#2c2c2c;}' .
            '.trend-table td{padding:10px 16px;border-bottom:1px solid #e5e5e5;}' .
            '.trend-table tbody tr:hover{background:#fafafa;}' .
            '.trend-up{color:#16a34a;font-weight:600;}' .
            '.trend-down{color:#dc2626;font-weight:600;}' .
            '.tables-wrap{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:20px;}' .
            '.tables-wrap table{width:100%;border-collapse:collapse;font-size:11pt;background:#fff;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;}' .
            '.tables-wrap thead{background:#f5f5f5;}' .
            '.tables-wrap th{padding:10px 12px;text-align:left;font-weight:600;color:#2c2c2c;border-bottom:2px solid #e5e5e5;}' .
            '.tables-wrap td{padding:8px 12px;border-bottom:1px solid #f0f0f0;}' .
            '.tables-wrap tbody tr:last-child td{border-bottom:none;}' .
            '.anomalies{list-style:none;padding:0;margin:16px 0;}' .
            '.anomalies li{margin-bottom:12px;padding:12px 16px;border-radius:6px;background:#fff8f0;border-left:4px solid #f59e0b;}' .
            '.severity-critical{background:#fef2f2;border-left-color:#dc2626;color:#991b1b;font-weight:600;}' .
            '.severity-warn{background:#fff7ed;border-left-color:#f59e0b;color:#9a3412;font-weight:600;}' .
            '.fpdms-empty{margin-top:20px;padding:32px;border:2px dashed #d1d5db;border-radius:8px;background:#fafafa;text-align:center;font-style:italic;color:#666;}' .
            '.footer{margin-top:48px;padding-top:24px;border-top:1px solid #e5e5e5;font-size:10pt;color:#999;text-align:center;line-height:1.4;}' .
            '@media print{body{background:#fff;padding:0;}' .
            '.report-cover{margin:-40px -40px 20px -40px;}' .
            '.section{page-break-inside:avoid;}}' .
            '</style></head><body>' .
            '<div class="section report-cover">' . $logoHtml . '<h1>' . Wp::escHtml((string) ($context['client']['name'] ?? '')) . '</h1>' .
            '<p style="color:#475569;margin-top:8px;">' . Wp::escHtml((string) ($context['period']['label'] ?? '')) . '</p></div>' .
            '<div class="report-body">' . $body . '</div>' .
            '<div class="footer">' . $footer . '</div>' .
            '</body></html>';
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,string>
     */
    private function buildSections(array $context): array
    {
        $sections = [
            'kpi' => $this->buildKpiSection($context),
            'trends' => $this->buildTrendsSection($context),
            'gsc' => $this->buildGscSection($context),
            'anomalies' => $this->buildAnomaliesSection($context),
        ];

        return array_filter($sections);
    }

    private function buildKpiSection(array $context): string
    {
        $totals = is_array($context['kpi']['totals'] ?? null) ? $context['kpi']['totals'] : [];
        $metrics = [
            'users' => 'Utenti',
            'sessions' => 'Sessioni',
            'pageviews' => 'Pagine viste',
            'clicks' => 'Clic',
            'impressions' => 'Impressioni',
            'cost' => 'Costo',
            'conversions' => 'Conversioni',
            'revenue' => 'Fatturato',
        ];

        $cards = '';
        foreach ($metrics as $key => $label) {
            $value = Wp::numberFormatI18n(
                (float) Arr::get($totals, $key, 0.0),
                in_array($key, ['cost', 'revenue'], true) ? 2 : 0
            );
            if (in_array($key, ['cost', 'revenue'], true)) {
                $value = '€ ' . $value;
            }
            $cards .= '<div class="kpi"><span>' . Wp::escHtml($label) . '</span><strong>' . Wp::escHtml($value) . '</strong></div>';
        }

        return '<div class="section"><h2>Indicatori chiave di performance</h2><div class="kpi-grid">' . $cards . '</div></div>';
    }

    private function buildTrendsSection(array $context): string
    {
        $trends = is_array($context['trends'] ?? null) ? $context['trends'] : [];
        $labels = [
            'users' => 'Utenti',
            'sessions' => 'Sessioni',
            'pageviews' => 'Pagine viste',
            'clicks' => 'Clic',
            'impressions' => 'Impressioni',
            'conversions' => 'Conversioni',
            'cost' => 'Costo',
            'revenue' => 'Fatturato',
        ];

        $tables = '';
        $windows = [
            'wow' => 'Confronto settimanale',
            'mom' => 'Confronto mensile',
        ];

        foreach ($windows as $key => $heading) {
            $data = is_array($trends[$key] ?? null) ? $trends[$key] : [];
            if (empty($data)) {
                continue;
            }

            $tables .= $this->renderTrendTable($data, $heading, $labels);
        }

        if ($tables === '') {
            return '';
        }

        return '<div class="section"><h2>Andamento temporale</h2><div class="tables-wrap trend-tables">' . $tables . '</div></div>';
    }

    /**
     * @param array<string,array<string,float|null>> $data
     * @param array<string,string> $labels
     */
    private function renderTrendTable(array $data, string $heading, array $labels): string
    {
        $rows = '';
        foreach ($labels as $metric => $label) {
            $row = is_array($data[$metric] ?? null) ? $data[$metric] : [];
            $current = Wp::numberFormatI18n(
                (float) Arr::get($row, 'current', 0.0),
                in_array($metric, ['cost', 'revenue'], true) ? 2 : 0
            );
            $previous = Wp::numberFormatI18n(
                (float) Arr::get($row, 'previous', 0.0),
                in_array($metric, ['cost', 'revenue'], true) ? 2 : 0
            );
            $delta = (float) Arr::get($row, 'delta', 0.0);
            $pct = Arr::get($row, 'delta_pct');
            $class = $delta >= 0 ? 'trend-up' : 'trend-down';
            $arrow = $delta >= 0 ? '↑' : '↓';
            $pctString = $pct === null ? '—' : sprintf('%s%.1f%%', $arrow, abs((float) $pct));
            $rows .= '<tr><td>' . Wp::escHtml($label) . '</td><td>' . Wp::escHtml($current) . '</td><td>' . Wp::escHtml($previous) . '</td><td class="' . Wp::escAttr($class) . '">' . Wp::escHtml($pctString) . '</td></tr>';
        }

        return '<div><h3>' . Wp::escHtml($heading) . '</h3><table class="trend-table"><thead><tr><th>Metrica</th><th>Attuale</th><th>Precedente</th><th>Δ %</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    private function buildGscSection(array $context): string
    {
        $queries = $context['tables']['gsc']['queries'] ?? [];
        $pages = $context['tables']['gsc']['pages'] ?? [];
        if (empty($queries) && empty($pages)) {
            return '';
        }

        $queryTable = $this->renderSimpleTable($queries, 'Query più performanti');
        $pagesTable = $this->renderSimpleTable($pages, 'Pagine più visitate');

        return '<div class="section"><h2>Dati Search Console</h2><div class="tables-wrap">' . $queryTable . $pagesTable . '</div></div>';
    }

    private function buildAnomaliesSection(array $context): string
    {
        $anomalies = $context['anomalies']['items'] ?? [];
        if (empty($anomalies)) {
            return '';
        }

        $items = '';
        foreach ($anomalies as $anomaly) {
            if (! is_array($anomaly)) {
                continue;
            }
            $metric = (string) ($anomaly['metric'] ?? 'Metrica');
            $severity = strtolower((string) ($anomaly['severity'] ?? 'warn'));
            $delta = isset($anomaly['delta_percent'])
                ? Wp::numberFormatI18n((float) $anomaly['delta_percent'], 1) . '%'
                : '';
            $label = $delta !== '' ? sprintf('%s (%s)', $metric, $delta) : $metric;
            $items .= '<li class="' . Wp::escAttr('severity-' . $severity) . '">' . Wp::escHtml($label) . '</li>';
        }

        return '<div class="section"><h2>Anomalie rilevate</h2><ul class="anomalies">' . $items . '</ul></div>';
    }

    /**
     * @param array<int, mixed> $items
     */
    private function renderSimpleTable(array $items, string $heading): string
    {
        if (empty($items)) {
            return '';
        }

        $rows = '';
        foreach ($items as $item) {
            if (is_array($item)) {
                $label = isset($item['name']) ? (string) $item['name'] : (string) ($item['label'] ?? reset($item) ?? '');
                $value = isset($item['value']) ? (float) $item['value'] : (float) ($item['clicks'] ?? $item['sessions'] ?? 0);
                $rows .= '<tr><td>' . Wp::escHtml($label) . '</td><td>' . Wp::escHtml(Wp::numberFormatI18n($value, 0)) . '</td></tr>';
            } else {
                $rows .= '<tr><td colspan="2">' . Wp::escHtml((string) $item) . '</td></tr>';
            }
        }

        return '<div><h3>' . Wp::escHtml($heading) . '</h3><table><tbody>' . $rows . '</tbody></table></div>';
    }
}

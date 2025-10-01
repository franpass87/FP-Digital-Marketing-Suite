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
        $logo = Wp::escUrl($context['branding']['logo_url'] ?? '');
        $footer = Wp::ksesPost($context['branding']['footer_text'] ?? '');

        $logoHtml = $logo !== '' ? '<img src="' . $logo . '" alt="logo" style="max-width:200px;">' : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' .
            'body{font-family:sans-serif;color:#111;margin:0;padding:40px;background:#f3f4f6;}' .
            'h1,h2,h3{color:' . Wp::escAttr($color) . ';margin:0;}' .
            '.section{background:#fff;padding:24px;border-radius:12px;margin-bottom:24px;box-shadow:0 1px 2px rgba(15,23,42,0.08);}' .
            '.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-top:16px;}' .
            '.kpi{padding:16px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;}' .
            '.kpi span{display:block;font-size:13px;color:#64748b;margin-bottom:4px;}' .
            '.trend-table{width:100%;border-collapse:collapse;margin-top:16px;}' .
            '.trend-table th,.trend-table td{padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:13px;}' .
            '.trend-up{color:#16a34a;font-weight:600;}' .
            '.trend-down{color:#dc2626;font-weight:600;}' .
            '.tables-wrap{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:16px;}' .
            '.tables-wrap table{width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;}' .
            '.tables-wrap th,.tables-wrap td{padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:left;}' .
            '.anomalies li{margin-bottom:8px;}' .
            '.severity-critical{color:#b91c1c;font-weight:600;}' .
            '.severity-warn{color:#d97706;font-weight:600;}' .
            '.fpdms-empty{margin-top:16px;padding:20px;border:1px dashed #94a3b8;border-radius:10px;background:#fff;text-align:center;font-style:italic;color:#475569;}' .
            '.footer{margin-top:24px;font-size:12px;color:#64748b;text-align:center;}' .
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
            'users' => I18n::__('Users'),
            'sessions' => I18n::__('Sessions'),
            'clicks' => I18n::__('Clicks'),
            'impressions' => I18n::__('Impressions'),
            'cost' => I18n::__('Cost'),
            'conversions' => I18n::__('Conversions'),
            'revenue' => I18n::__('Revenue'),
        ];

        $cards = '';
        foreach ($metrics as $key => $label) {
            $value = Wp::numberFormatI18n(
                (float) Arr::get($totals, $key, 0.0),
                in_array($key, ['cost', 'revenue'], true) ? 2 : 0
            );
            $cards .= '<div class="kpi"><span>' . Wp::escHtml($label) . '</span><strong style="font-size:20px;">' . Wp::escHtml($value) . '</strong></div>';
        }

        return '<div class="section"><h2>' . Wp::escHtml(I18n::__('Key performance indicators')) . '</h2><div class="kpi-grid">' . $cards . '</div></div>';
    }

    private function buildTrendsSection(array $context): string
    {
        $trends = is_array($context['trends'] ?? null) ? $context['trends'] : [];
        $labels = [
            'users' => I18n::__('Users'),
            'sessions' => I18n::__('Sessions'),
            'clicks' => I18n::__('Clicks'),
            'impressions' => I18n::__('Impressions'),
            'conversions' => I18n::__('Conversions'),
            'cost' => I18n::__('Cost'),
            'revenue' => I18n::__('Revenue'),
        ];

        $tables = '';
        $windows = [
            'wow' => I18n::__('Week over week'),
            'mom' => I18n::__('Month over month'),
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

        return '<div class="section"><h2>' . Wp::escHtml(I18n::__('Trend comparison')) . '</h2><div class="tables-wrap trend-tables">' . $tables . '</div></div>';
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

        return '<div><h3>' . Wp::escHtml($heading) . '</h3><table class="trend-table"><thead><tr><th>' . Wp::escHtml(I18n::__('Metric')) . '</th><th>' . Wp::escHtml(I18n::__('Current')) . '</th><th>' . Wp::escHtml(I18n::__('Previous')) . '</th><th>' . Wp::escHtml(I18n::__('Δ %')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    private function buildGscSection(array $context): string
    {
        $queries = $context['tables']['gsc']['queries'] ?? [];
        $pages = $context['tables']['gsc']['pages'] ?? [];
        if (empty($queries) && empty($pages)) {
            return '';
        }

        $queryTable = $this->renderSimpleTable($queries, I18n::__('Top queries'));
        $pagesTable = $this->renderSimpleTable($pages, I18n::__('Top pages'));

        return '<div class="section"><h2>' . Wp::escHtml(I18n::__('Search Console insights')) . '</h2><div class="tables-wrap">' . $queryTable . $pagesTable . '</div></div>';
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
            $metric = (string) ($anomaly['metric'] ?? I18n::__('Metric'));
            $severity = strtolower((string) ($anomaly['severity'] ?? 'warn'));
            $delta = isset($anomaly['delta_percent'])
                ? Wp::numberFormatI18n((float) $anomaly['delta_percent'], 1) . '%'
                : '';
            $label = $delta !== '' ? sprintf('%s (%s)', $metric, $delta) : $metric;
            $items .= '<li class="' . Wp::escAttr('severity-' . $severity) . '">' . Wp::escHtml($label) . '</li>';
        }

        return '<div class="section"><h2>' . Wp::escHtml(I18n::__('Anomalies detected')) . '</h2><ul class="anomalies">' . $items . '</ul></div>';
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

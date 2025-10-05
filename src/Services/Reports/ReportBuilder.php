<?php

declare(strict_types=1);

namespace FP\DMS\Services\Reports;

use Exception;
use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\ReportJob;
use FP\DMS\Domain\Entities\Template;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Infra\Options;
use FP\DMS\Infra\PdfRenderer;
use FP\DMS\Services\Connectors\DataSourceProviderInterface;
use FP\DMS\Services\Connectors\Normalizer;
use FP\DMS\Support\I18n;
use FP\DMS\Support\Wp;
use FP\DMS\Support\Period;
use RuntimeException;
use function is_array;
use function is_string;
use function wp_get_attachment_image_url;
use function wp_get_attachment_url;

class ReportBuilder
{
    public function __construct(
        private ReportsRepo $reports,
        private HtmlRenderer $html,
        private PdfRenderer $pdf,
    ) {
    }

    /**
     * @param DataSourceProviderInterface[] $providers
     */
    public function generate(ReportJob $job, Client $client, array $providers, Period $period, Template $template, array $previousMetrics = []): ?ReportJob
    {
        $collected = $this->collectData($providers, $period, $previousMetrics);
        $timestamp = Wp::currentTime('mysql');
        $meta = array_merge(
            $job->meta,
            $collected,
            [
                'generated_at' => $timestamp,
                'period' => $period->toArray(),
            ]
        );

        try {
            $context = $this->buildContext($client, $period, $meta);
            $html = $this->html->render($template, $context);
            [$absolute, $relative] = $this->determinePath($client, $period);
            $this->pdf->render($html, $absolute);

            $this->reports->update($job->id ?? 0, [
                'status' => 'success',
                'storage_path' => $relative,
                'meta' => array_merge($meta, ['completed_at' => $timestamp]),
            ]);
        } catch (Exception $e) {
            $this->reports->update($job->id ?? 0, [
                'status' => 'failed',
                'meta' => array_merge($meta, [
                    'error' => $e->getMessage(),
                    'failed_at' => $timestamp,
                ]),
            ]);

            return $this->reports->find($job->id ?? 0);
        }

        return $this->reports->find($job->id ?? 0);
    }

    /**
     * @param DataSourceProviderInterface[] $providers
     * @return array<string,mixed>
     */
    private function collectData(array $providers, Period $period, array $previousMetrics): array
    {
        $metrics = [];
        $dimensions = [];
        $sources = [];
        $hasMetricRows = false;

        foreach ($providers as $provider) {
            $definition = $provider->describe();
            $defaultSource = is_string($definition['name'] ?? null) ? (string) $definition['name'] : strtolower((new \ReflectionClass($provider))->getShortName());
            if (! empty($definition['label']) && is_string($definition['label'])) {
                $sources[$defaultSource] = (string) $definition['label'];
            }

            $rows = [];
            foreach ($provider->fetchMetrics($period) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $row['source'] = $row['source'] ?? $defaultSource;
                $row['date'] = $row['date'] ?? $period->end->format('Y-m-d');
                $normalized = Normalizer::ensureKeys($row);
                if ($this->hasValues($normalized)) {
                    $hasMetricRows = true;
                }
                $rows[] = $normalized;
            }

            if (! empty($rows)) {
                $metrics[$defaultSource] = $rows;
            }

            $dimensionRows = $provider->fetchDimensions($period);
            if (! empty($dimensionRows)) {
                $dimensions[$defaultSource] = $dimensionRows;
            }
        }

        $daily = [];
        if (! empty($metrics)) {
            $daily = Normalizer::mergeDaily(...array_values($metrics));
            $daily = array_map(static function (array $row): array {
                $row['source'] = 'aggregate';

                return $row;
            }, $daily);
        }

        $kpiBySource = [];
        foreach ($metrics as $source => $rows) {
            $kpiBySource[$source] = $this->aggregateRows($rows);
        }

        $overallTotals = $this->aggregateRows($daily);
        $previousTotals = $this->aggregatePrevious($previousMetrics);

        return [
            'kpi' => $kpiBySource,
            'kpi_total' => $overallTotals,
            'dimensions' => $dimensions,
            'sources' => $sources,
            'metrics_daily' => $daily,
            'previous_totals' => $previousTotals,
            'empty' => ! $hasMetricRows && empty($dimensions),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildContext(Client $client, Period $period, array $meta): array
    {
        $settings = Options::getGlobalSettings();
        $branding = is_array($settings['pdf_branding'] ?? null) ? $settings['pdf_branding'] : [];
        $clientLogoUrl = $this->resolveClientLogoUrl($client);
        if ($clientLogoUrl !== '') {
            $branding['logo_url'] = $clientLogoUrl;
        } else {
            $branding['logo_url'] = (string) ($branding['logo_url'] ?? '');
        }
        $daily = is_array($meta['metrics_daily'] ?? null) ? $meta['metrics_daily'] : [];
        $totals = is_array($meta['kpi_total'] ?? null) ? $meta['kpi_total'] : [];
        $previousTotals = is_array($meta['previous_totals'] ?? null) ? $meta['previous_totals'] : [];
        $trends = $this->buildTrendComparisons($daily, $totals, $previousTotals);
        $gsc = $meta['dimensions']['gsc'] ?? [];
        $gscQueries = is_array($gsc['queries'] ?? null) ? array_slice($gsc['queries'], 0, 10) : [];
        $gscPages = is_array($gsc['pages'] ?? null) ? array_slice($gsc['pages'], 0, 10) : [];
        $anomalies = is_array($meta['anomalies'] ?? null) ? $meta['anomalies'] : [];

        $bySource = is_array($meta['kpi'] ?? null) ? $meta['kpi'] : [];
        return [
            'client' => [
                'name' => $client->name,
                'timezone' => $client->timezone,
                'logo_url' => $clientLogoUrl,
            ],
            'period' => [
                'start' => $period->start->format('Y-m-d'),
                'end' => $period->end->format('Y-m-d'),
                'label' => $period->format('Y-m-d'),
            ],
            'branding' => $branding,
            'kpi' => array_merge($bySource, [
                'by_source' => $bySource,
                'totals' => $totals,
            ]),
            'dimensions' => $meta['dimensions'] ?? [],
            'metrics_daily' => $daily,
            'sources' => $meta['sources'] ?? [],
            'anomalies' => [
                'items' => $anomalies,
                'count' => count($anomalies),
            ],
            'tables' => [
                'gsc' => [
                    'queries' => $gscQueries,
                    'pages' => $gscPages,
                ],
            ],
            'trends' => $trends,
            'report' => [
                'empty' => ! empty($meta['empty']),
                'empty_message' => I18n::__('No data available for this period.'),
            ],
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function determinePath(Client $client, Period $period): array
    {
        $upload = Wp::uploadDir();
        if (! empty($upload['error']) || empty($upload['basedir'])) {
            throw new RuntimeException(I18n::__('Uploads directory is not available.'));
        }
        $subdir = 'fpdms/' . $period->start->format('Y') . '/' . $period->start->format('m');
        $slug = Wp::sanitizeTitle($client->name ?: 'client');
        $filename = $slug . '-' . $period->start->format('Ymd') . '-' . $period->end->format('Ymd') . '.pdf';
        $relative = Wp::trailingSlashIt($subdir) . $filename;
        $absolute = Wp::trailingSlashIt($upload['basedir']) . $relative;

        return [$absolute, $relative];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, float>
     */
    private function aggregateRows(array $rows): array
    {
        $totals = array_fill_keys(['users', 'sessions', 'clicks', 'impressions', 'cost', 'conversions', 'revenue'], 0.0);
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = Normalizer::ensureKeys($row);
            foreach ($normalized as $key => $value) {
                if ($key === 'date' || $key === 'source') {
                    continue;
                }
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $value;
            }
        }

        return $totals;
    }

    /**
     * @param array<string, array<string, float|int>> $previous
     * @return array<string, float>
     */
    private function aggregatePrevious(array $previous): array
    {
        $totals = array_fill_keys(['users', 'sessions', 'clicks', 'impressions', 'cost', 'conversions', 'revenue'], 0.0);
        foreach ($previous as $metrics) {
            if (! is_array($metrics)) {
                continue;
            }
            foreach ($metrics as $key => $value) {
                if (! is_numeric($value)) {
                    continue;
                }
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $value;
            }
        }

        return $totals;
    }

    /**
     * @param array<int, array<string, mixed>> $daily
     * @param array<string, float> $totals
     * @param array<string, float> $previous
     * @return array<string, array<string, array<string, float|null>>>
     */
    private function buildTrendComparisons(array $daily, array $totals, array $previous): array
    {
        $wow = $this->computeWindowTrend($daily, 7);
        if ($wow === null) {
            $wow = $this->computeDelta($totals, $previous);
        }

        return [
            'wow' => $wow,
            'mom' => $this->computeDelta($totals, $previous),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $daily
     * @return array<string, array<string, float|null>>|null
     */
    private function computeWindowTrend(array $daily, int $window): ?array
    {
        if (count($daily) < $window * 2) {
            return null;
        }

        usort($daily, static fn(array $a, array $b): int => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));
        $recent = array_slice($daily, -$window);
        $previous = array_slice($daily, -$window * 2, $window);

        $currentTotals = $this->aggregateRows($recent);
        $previousTotals = $this->aggregateRows($previous);

        return $this->computeDelta($currentTotals, $previousTotals);
    }

    /**
     * @param array<string, float> $current
     * @param array<string, float> $previous
     * @return array<string, array<string, float|null>>
     */
    private function computeDelta(array $current, array $previous): array
    {
        $metrics = ['users', 'sessions', 'clicks', 'impressions', 'conversions', 'cost', 'revenue'];
        $results = [];
        foreach ($metrics as $metric) {
            $curr = (float) ($current[$metric] ?? 0.0);
            $prev = (float) ($previous[$metric] ?? 0.0);
            $delta = $curr - $prev;
            $pct = $prev > 0.0 ? ($delta / $prev) * 100 : null;
            $results[$metric] = [
                'current' => $curr,
                'previous' => $prev,
                'delta' => $delta,
                'delta_pct' => $pct,
            ];
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hasValues(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key === 'date' || $key === 'source') {
                continue;
            }

            if (is_numeric($value)) {
                return true;
            }
        }

        return false;
    }

    private function resolveClientLogoUrl(Client $client): string
    {
        if ($client->logoId === null) {
            return '';
        }

        $url = wp_get_attachment_image_url($client->logoId, 'full');
        if (is_string($url) && $url !== '') {
            return $url;
        }

        $fallback = wp_get_attachment_url($client->logoId);
        if (is_string($fallback)) {
            return $fallback;
        }

        return '';
    }
}

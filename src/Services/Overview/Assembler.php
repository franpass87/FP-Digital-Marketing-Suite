<?php

declare(strict_types=1);

namespace FP\DMS\Services\Overview;

use DateInterval;
use DateTimeImmutable;
use Exception;
use FP\DMS\Domain\Entities\DataSource;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Services\Connectors\Normalizer;
use FP\DMS\Services\Connectors\ProviderFactory;
use FP\DMS\Support\Dates;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use Throwable;

use function __;
use function array_filter;
use function array_fill_keys;
use function array_map;
use function count;
use function is_array;
use function is_numeric;

class Assembler
{
    /** @var array<string, array{label: string, precision: int}> */
    private const KPI_MAP = [
        'users' => ['label' => 'Users', 'precision' => 0],
        'sessions' => ['label' => 'Sessions', 'precision' => 0],
        'clicks' => ['label' => 'Clicks', 'precision' => 0],
        'impressions' => ['label' => 'Impressions', 'precision' => 0],
        'cost' => ['label' => 'Cost', 'precision' => 2],
        'conversions' => ['label' => 'Conversions', 'precision' => 2],
        'revenue' => ['label' => 'Revenue', 'precision' => 2],
        'gsc_clicks' => ['label' => 'GSC Clicks', 'precision' => 0],
        'gsc_impressions' => ['label' => 'GSC Impressions', 'precision' => 0],
    ];

    /** @var array<string, array<string, string>> */
    private const SOURCE_METRICS = [
        'ga4' => [
            'users' => 'users',
            'sessions' => 'sessions',
            'revenue' => 'revenue',
        ],
        'google_ads' => [
            'clicks' => 'clicks',
            'impressions' => 'impressions',
            'cost' => 'cost',
            'conversions' => 'conversions',
        ],
        'meta_ads' => [
            'clicks' => 'clicks',
            'impressions' => 'impressions',
            'cost' => 'cost',
            'conversions' => 'conversions',
        ],
        'gsc' => [
            'clicks' => 'gsc_clicks',
            'impressions' => 'gsc_impressions',
        ],
    ];

    /** @var array<string, array{label: string}> */
    private const STATUS_TYPES = [
        'ga4' => ['label' => 'GA4'],
        'gsc' => ['label' => 'GSC'],
        'google_ads' => ['label' => 'Google Ads'],
        'meta_ads' => ['label' => 'Meta Ads'],
        'clarity' => ['label' => 'Clarity'],
        'csv_generic' => ['label' => 'Generic'],
    ];

    /** @var array<string, array{auth?: string[], config?: string[]}> */
    private const SOURCE_REQUIREMENTS = [
        'ga4' => ['auth' => ['service_account'], 'config' => ['property_id']],
        'gsc' => ['auth' => ['service_account'], 'config' => ['site_url']],
    ];

    public function __construct(private ?DataSourcesRepo $dataSourcesRepo = null)
    {
        $this->dataSourcesRepo = $dataSourcesRepo ?: new DataSourcesRepo();
    }

    /**
     * @param array<string, string> $period
     * @return array<string, mixed>
     */
    public function summary(int $clientId, array $period): array
    {
        $range = $this->resolvePeriod($period);
        $previous = Dates::prevComparable($range);

        $currentSeries = $this->collectSeries($clientId, $range);
        $previousSeries = $this->collectSeries($clientId, $previous);

        $kpis = [];
        foreach (self::KPI_MAP as $metric => $config) {
            $currentValue = $currentSeries['totals'][$metric] ?? 0.0;
            $previousValue = $previousSeries['totals'][$metric] ?? 0.0;
            $delta = Presenter::formatDelta($currentValue, $previousValue);
            $sparklineValues = array_map(
                static fn(array $metrics): float => (float) ($metrics[$metric] ?? 0.0),
                $currentSeries['daily']
            );

            $kpis[] = [
                'metric' => $metric,
                'label' => __($config['label'], 'fp-dms'),
                'value' => $currentValue,
                'formatted_value' => Presenter::formatNumber($currentValue, $config['precision']),
                'previous_value' => $previousValue,
                'formatted_previous' => Presenter::formatNumber($previousValue, $config['precision']),
                'delta' => $delta,
                'sparkline' => Sparkline::normalize($sparklineValues),
            ];
        }

        return [
            'period' => [
                'from' => $range->start->format('Y-m-d'),
                'to' => $range->end->format('Y-m-d'),
                'days' => count($currentSeries['daily']),
            ],
            'comparison' => [
                'from' => $previous->start->format('Y-m-d'),
                'to' => $previous->end->format('Y-m-d'),
            ],
            'kpis' => $kpis,
            'series' => $currentSeries['daily'],
        ];
    }

    /**
     * @param array<string, string> $period
     * @return array<string, mixed>
     */
    public function trend(int $clientId, array $period, string $metric): array
    {
        $range = $this->resolvePeriod($period);
        $series = $this->collectSeries($clientId, $range);

        $points = [];
        foreach ($series['daily'] as $date => $metrics) {
            $points[] = [
                'date' => $date,
                'value' => (float) ($metrics[$metric] ?? 0.0),
            ];
        }

        return [
            'metric' => $metric,
            'period' => [
                'from' => $range->start->format('Y-m-d'),
                'to' => $range->end->format('Y-m-d'),
            ],
            'series' => $points,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function status(int $clientId): array
    {
        $sources = $this->dataSourcesRepo->forClient($clientId);
        $status = [];

        foreach (self::STATUS_TYPES as $type => $meta) {
            $entry = [
                'type' => $type,
                'label' => __($meta['label'], 'fp-dms'),
            ];

            $dataSource = $this->findSourceByType($sources, $type);
            if (! $dataSource) {
                $status[] = $entry + [
                    'state' => 'missing',
                    'message' => __('Connector not configured.', 'fp-dms'),
                    'last_updated' => null,
                ];
                continue;
            }

            $status[] = $entry + $this->buildStatusPayload($dataSource);
        }

        return $status;
    }

    /**
     * @param array<int, DataSource> $sources
     */
    private function findSourceByType(array $sources, string $type): ?DataSource
    {
        foreach ($sources as $source) {
            if ($source->type === $type) {
                return $source;
            }
        }

        return null;
    }

    private function buildStatusPayload(DataSource $source): array
    {
        if (! $source->active) {
            return [
                'state' => 'inactive',
                'message' => __('Connector is disabled.', 'fp-dms'),
                'last_updated' => null,
            ];
        }

        if (! $this->meetsRequirements($source)) {
            return [
                'state' => 'misconfigured',
                'message' => __('Connector misconfigured. Review credentials and settings.', 'fp-dms'),
                'last_updated' => null,
            ];
        }

        $summary = $this->extractSummary($source);
        $lastUpdated = $this->extractLastUpdated($summary);
        $hasMetrics = $this->summaryHasMetrics($summary);

        if (! $hasMetrics) {
            return [
                'state' => 'no_data',
                'message' => __('No recent data ingested.', 'fp-dms'),
                'last_updated' => $lastUpdated,
            ];
        }

        return [
            'state' => 'ok',
            'message' => __('Data available.', 'fp-dms'),
            'last_updated' => $lastUpdated,
        ];
    }

    private function meetsRequirements(DataSource $source): bool
    {
        $requirements = self::SOURCE_REQUIREMENTS[$source->type] ?? null;
        if ($requirements === null) {
            return true;
        }

        if (isset($requirements['auth'])) {
            foreach ($requirements['auth'] as $key) {
                if (empty($source->auth[$key])) {
                    return false;
                }
            }
        }

        if (isset($requirements['config'])) {
            foreach ($requirements['config'] as $key) {
                if (empty($source->config[$key])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function extractSummary(DataSource $source): ?array
    {
        $summary = $source->config['summary'] ?? null;

        return is_array($summary) ? $summary : null;
    }

    private function extractLastUpdated(?array $summary): ?string
    {
        if (! $summary) {
            return null;
        }

        foreach (['last_ingested_at', 'updated_at', 'generated_at'] as $key) {
            if (! empty($summary[$key]) && is_string($summary[$key])) {
                return $summary[$key];
            }
        }

        return null;
    }

    private function summaryHasMetrics(?array $summary): bool
    {
        if (! $summary) {
            return false;
        }

        if (! empty($summary['metrics']) && is_array($summary['metrics'])) {
            foreach ($summary['metrics'] as $value) {
                if (is_numeric($value)) {
                    return true;
                }
            }
        }

        if (! empty($summary['daily']) && is_array($summary['daily'])) {
            foreach ($summary['daily'] as $metrics) {
                if (! is_array($metrics)) {
                    continue;
                }

                foreach ($metrics as $key => $value) {
                    if ($key === 'date') {
                        continue;
                    }

                    if (is_numeric($value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $period
     */
    private function resolvePeriod(array $period): Period
    {
        $now = new DateTimeImmutable('now');
        $start = $this->parseDate($period['from'] ?? '') ?? $now->sub(new DateInterval('P6D'));
        $end = $this->parseDate($period['to'] ?? '') ?? $now;

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return new Period($start, $end);
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return null;
        }
    }

    private function safeDate(string $candidate, DateTimeImmutable $fallback): ?string
    {
        if ($candidate === '') {
            return $fallback->format('Y-m-d');
        }

        try {
            $date = new DateTimeImmutable($candidate);
        } catch (Exception $exception) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * @return array{daily: array<string, array<string, float>>, totals: array<string, float>}
     */
    private function collectSeries(int $clientId, Period $period): array
    {
        $sources = $this->dataSourcesRepo->forClient($clientId);
        $daily = [];
        $totals = array_fill_keys(array_keys(self::KPI_MAP), 0.0);

        foreach ($sources as $dataSource) {
            if (! $dataSource->active) {
                continue;
            }

            $provider = ProviderFactory::create($dataSource->type, $dataSource->auth, $dataSource->config);
            if (! $provider) {
                continue;
            }

            try {
                $rows = $provider->fetchMetrics($period);
            } catch (Throwable $throwable) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $sourceKey = isset($row['source']) ? (string) $row['source'] : $dataSource->type;
                $metrics = $this->mapMetrics($sourceKey, $row);
                if ($metrics === []) {
                    continue;
                }

                $date = isset($row['date']) ? (string) $row['date'] : '';
                $normalizedDate = $this->safeDate($date, $period->end);
                if (! $normalizedDate) {
                    continue;
                }

                if (! Normalizer::isWithinPeriod($period, $normalizedDate)) {
                    continue;
                }

                if (! isset($daily[$normalizedDate])) {
                    $daily[$normalizedDate] = array_fill_keys(array_keys(self::KPI_MAP), 0.0);
                }

                foreach ($metrics as $metric => $value) {
                    $daily[$normalizedDate][$metric] = ($daily[$normalizedDate][$metric] ?? 0.0) + (float) $value;
                    $totals[$metric] = ($totals[$metric] ?? 0.0) + (float) $value;
                }
            }
        }

        $filled = [];
        foreach (Dates::rangeDays($period->start, $period->end) as $date) {
            $filled[$date] = array_merge(
                array_fill_keys(array_keys(self::KPI_MAP), 0.0),
                $daily[$date] ?? []
            );
        }

        return [
            'daily' => $filled,
            'totals' => array_map(
                static fn(float $value): float => round($value, 2),
                $totals
            ),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, float>
     */
    private function mapMetrics(string $source, array $row): array
    {
        $source = Wp::sanitizeKey($source);
        $map = self::SOURCE_METRICS[$source] ?? null;
        if ($map === null) {
            return [];
        }

        $metrics = [];
        foreach ($map as $original => $target) {
            if (! isset($row[$original]) || ! is_numeric($row[$original])) {
                continue;
            }

            $metrics[$target] = ($metrics[$target] ?? 0.0) + (float) $row[$original];
        }

        return $metrics;
    }
}

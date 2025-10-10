<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

use function __;

class MetaAdsProvider implements DataSourceProviderInterface
{
    private const SOURCE = 'meta_ads';

    /** @var array<string, list<string>> */
    private const METRIC_ALIASES = [
        'clicks' => ['clicks', 'linkclicks'],
        'impressions' => ['impressions'],
        'conversions' => ['conversions', 'results', 'purchases', 'purchase', 'websitepurchases', 'leads'],
        'cost' => ['cost', 'costusd', 'amountspent*', 'spend*'],
        'revenue' => ['revenue', 'purchaseconversionvalue*', 'purchasesconversionvalue*'],
    ];

    public function __construct(private array $auth, private array $config)
    {
    }

    public function testConnection(): ConnectionResult
    {
        $token = trim((string) ($this->auth['access_token'] ?? ''));
        $accountId = trim((string) ($this->config['account_id'] ?? ''));

        if ($token === '') {
            return ConnectionResult::failure(__('Provide a Meta Ads access token with the required permissions.', 'fp-dms'));
        }

        if ($accountId === '' || ! preg_match('/^act_[0-9]+$/', $accountId)) {
            return ConnectionResult::failure(__('Enter the ad account ID using the act_1234567890 format.', 'fp-dms'));
        }

        return ConnectionResult::success(__('Credentials saved. Meta Ads data will refresh on the next sync run.', 'fp-dms'));
    }

    public function fetchMetrics(Period $period): array
    {
        $summary = is_array($this->config['summary'] ?? null) ? $this->config['summary'] : [];
        $rows = [];

        $dailyRows = [];
        $daily = $summary['daily'] ?? [];

        if (is_array($daily)) {
            foreach ($daily as $date => $metrics) {
                if (! is_array($metrics) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                    continue;
                }

                $normalized = self::sanitizeMetricMap($metrics);
                $dailyRows[(string) $date] = $this->decorateRow((string) $date, $normalized, false);
            }
        }

        if ($dailyRows !== []) {
            ksort($dailyRows);
            $rows = array_values($dailyRows);
        }

        $summaryMetrics = self::sanitizeMetricMap($summary['metrics'] ?? []);
        $aggregateRow = $this->decorateRow($period->end->format('Y-m-d'), $summaryMetrics, true);

        if ($this->metricsAreEmpty($summaryMetrics) && $dailyRows !== []) {
            $aggregateRow = $this->aggregateFromDaily($period, $dailyRows);
        }

        $rows[] = $aggregateRow;

        return $rows;
    }

    public function fetchDimensions(Period $period): array
    {
        return [];
    }

    public function describe(): array
    {
        return [
            'name' => self::SOURCE,
            'label' => __('Meta Ads', 'fp-dms'),
            'credentials' => ['access_token'],
            'config' => ['account_id', 'pixel_id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ingestCsvSummary(string $csv): array
    {
        $csv = trim($csv);

        if ($csv === '') {
            return [
                'rows' => 0,
                'metrics' => self::emptyMetrics(),
                'daily' => [],
            ];
        }

        $lines = preg_split("/(?:\r\n|\r|\n)/", $csv) ?: [];

        if ($lines === []) {
            return [
                'rows' => 0,
                'metrics' => self::emptyMetrics(),
                'daily' => [],
            ];
        }

        $headerLine = array_shift($lines);
        $headers = $headerLine !== null ? str_getcsv($headerLine) : [];
        $headerMap = [];

        foreach ($headers as $index => $column) {
            $headerMap[$index] = self::sanitizeKey($column);
        }

        $totals = self::emptyMetrics();
        $dailySummary = [];
        $rowCount = 0;

        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }

            $values = str_getcsv((string) $line);

            $normalized = [];

            foreach ($values as $index => $value) {
                $key = $headerMap[$index] ?? 'col' . $index;
                $normalized[$key] = $value;
            }

            $date = trim((string) ($normalized['date'] ?? ''));

            if ($date === '') {
                continue;
            }

            $dateKey = substr($date, 0, 10);

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
                continue;
            }

            $rowCount++;

            $metrics = self::sanitizeMetricMap($normalized);

            $rowMetrics = [
                'clicks' => self::pickMetric($metrics, self::METRIC_ALIASES['clicks']) ?? 0.0,
                'impressions' => self::pickMetric($metrics, self::METRIC_ALIASES['impressions']) ?? 0.0,
                'conversions' => self::pickMetric($metrics, self::METRIC_ALIASES['conversions']) ?? 0.0,
            ];

            $cost = self::pickMetric($metrics, self::METRIC_ALIASES['cost']);
            if ($cost !== null && $cost >= 0.0) {
                $rowMetrics['cost'] = $cost;
                $totals['cost'] = self::addCurrency($totals['cost'], $cost);
            }

            $revenue = self::pickMetric($metrics, self::METRIC_ALIASES['revenue']);
            if ($revenue !== null) {
                $rowMetrics['revenue'] = $revenue;
                $totals['revenue'] = self::addCurrency($totals['revenue'], $revenue);
            }

            $totals['clicks'] += $rowMetrics['clicks'];
            $totals['impressions'] += $rowMetrics['impressions'];
            $totals['conversions'] += $rowMetrics['conversions'];

            $dailySummary[$dateKey] = $rowMetrics;
        }

        return [
            'rows' => $rowCount,
            'metrics' => $totals,
            'daily' => $dailySummary,
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function decorateRow(string $date, array $metrics, bool $includeEmptyCosts): array
    {
        $row = [
            'source' => self::SOURCE,
            'date' => $date,
            'clicks' => self::pickMetric($metrics, self::METRIC_ALIASES['clicks']) ?? 0.0,
            'impressions' => self::pickMetric($metrics, self::METRIC_ALIASES['impressions']) ?? 0.0,
            'conversions' => self::pickMetric($metrics, self::METRIC_ALIASES['conversions']) ?? 0.0,
        ];

        $cost = self::pickMetric($metrics, self::METRIC_ALIASES['cost']);
        if ($cost !== null) {
            $row['cost'] = $cost;
        } elseif ($includeEmptyCosts) {
            $row['cost'] = 0.0;
        }

        $revenue = self::pickMetric($metrics, self::METRIC_ALIASES['revenue']);
        if ($revenue !== null) {
            $row['revenue'] = $revenue;
        } elseif ($includeEmptyCosts) {
            $row['revenue'] = 0.0;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $dailyRows
     */
    private function aggregateFromDaily(Period $period, array $dailyRows): array
    {
        $totals = [
            'source' => self::SOURCE,
            'date' => $period->end->format('Y-m-d'),
            'clicks' => 0.0,
            'impressions' => 0.0,
            'conversions' => 0.0,
            'cost' => 0.0,
            'revenue' => 0.0,
        ];

        foreach ($dailyRows as $row) {
            $totals['clicks'] += (float) ($row['clicks'] ?? 0.0);
            $totals['impressions'] += (float) ($row['impressions'] ?? 0.0);
            $totals['conversions'] += (float) ($row['conversions'] ?? 0.0);

            if (isset($row['cost'])) {
                $totals['cost'] = self::addCurrency($totals['cost'], (float) $row['cost']);
            }

            if (isset($row['revenue'])) {
                $totals['revenue'] = self::addCurrency($totals['revenue'], (float) $row['revenue']);
            }
        }

        return $totals;
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function metricsAreEmpty(array $metrics): bool
    {
        foreach ($metrics as $value) {
            if (self::parseNumber($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private static function sanitizeMetricMap(array $metrics): array
    {
        $normalized = [];

        foreach ($metrics as $key => $value) {
            $normalized[self::sanitizeKey((string) $key)] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $metrics
     * @param list<string> $aliases
     */
    private static function pickMetric(array $metrics, array $aliases): ?float
    {
        foreach ($aliases as $alias) {
            $isPrefix = str_ends_with($alias, '*');
            $pattern = $isPrefix ? substr($alias, 0, -1) : $alias;

            foreach ($metrics as $key => $value) {
                if ($key === '') {
                    continue;
                }

                if ($isPrefix) {
                    if (! str_starts_with($key, $pattern)) {
                        continue;
                    }
                } elseif ($key !== $pattern) {
                    continue;
                }

                $parsed = self::parseNumber($value);

                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    private static function sanitizeKey(string $key): string
    {
        $key = mb_strtolower($key);
        $key = preg_replace('/[^a-z0-9]+/', '', $key) ?? '';

        return $key;
    }

    private static function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        $isNegative = false;

        if ($raw === '' || $raw === '-') {
            return null;
        }

        if ($raw[0] === '(' && str_ends_with($raw, ')')) {
            $raw = substr($raw, 1, -1);
            $isNegative = true;
        }

        $stripped = preg_replace('/[^0-9,\.\-]/', '', $raw) ?? '';

        if ($stripped === '' || $stripped === '-' || $stripped === '--') {
            return null;
        }

        $hasComma = str_contains($stripped, ',');
        $hasDot = str_contains($stripped, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($stripped, ',');
            $lastDot = strrpos($stripped, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $stripped = str_replace('.', '', $stripped);
                $stripped = str_replace(',', '.', $stripped);
            } else {
                $stripped = str_replace(',', '', $stripped);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $stripped);
            $last = end($parts) ?: '';

            if (strlen($last) === 3 && count($parts) > 1) {
                $stripped = str_replace(',', '', $stripped);
            } else {
                $stripped = str_replace(',', '.', $stripped);
            }
        } elseif ($hasDot) {
            $parts = explode('.', $stripped);
            $last = end($parts) ?: '';

            if (strlen($last) === 3 && count($parts) > 1) {
                $stripped = str_replace('.', '', $stripped);
            }
        }

        if (! is_numeric($stripped)) {
            return null;
        }

        $number = (float) $stripped;

        return $isNegative ? $number * -1 : $number;
    }

    /**
     * @return array<string, float>
     */
    private static function emptyMetrics(): array
    {
        return [
            'clicks' => 0.0,
            'impressions' => 0.0,
            'conversions' => 0.0,
            'cost' => 0.0,
            'revenue' => 0.0,
        ];
    }

    private static function addCurrency(float $current, float $value): float
    {
        $currentCents = (int) round($current * 100);
        $valueCents = (int) round($value * 100);

        return ($currentCents + $valueCents) / 100.0;
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Dates;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use function __;

class MetaAdsProvider implements DataSourceProviderInterface
{
    public function __construct(private array $auth, private array $config)
    {
    }

    public function testConnection(): ConnectionResult
    {
        $summary = $this->config['summary'] ?? null;
        if (is_array($summary) && ! empty($summary)) {
            return ConnectionResult::success(__('CSV summary loaded.', 'fp-dms'));
        }

        return ConnectionResult::failure(__('No CSV data available for Meta Ads.', 'fp-dms'));
    }

    public function fetchMetrics(Period $period): array
    {
        $summary = is_array($this->config['summary'] ?? null) ? $this->config['summary'] : [];
        $rows = [];

        if (isset($summary['daily']) && is_array($summary['daily'])) {
            foreach ($summary['daily'] as $date => $metrics) {
                if (! is_array($metrics)) {
                    continue;
                }
                $dateString = (string) $date;
                if ($dateString === 'total') {
                    $dateString = $period->end->format('Y-m-d');
                }
                if (! Normalizer::isWithinPeriod($period, $dateString)) {
                    continue;
                }

                $rows[] = Normalizer::ensureKeys(array_merge(
                    ['source' => 'meta_ads', 'date' => $dateString],
                    self::mapMetrics($metrics)
                ));
            }
        } elseif (isset($summary['metrics']) && is_array($summary['metrics'])) {
            $rows[] = Normalizer::ensureKeys(array_merge(
                ['source' => 'meta_ads', 'date' => $period->end->format('Y-m-d')],
                self::mapMetrics($summary['metrics'])
            ));
        } else {
            foreach (Dates::rangeDays($period->start, $period->end) as $date) {
                $rows[] = Normalizer::ensureKeys(['source' => 'meta_ads', 'date' => $date]);
            }
        }

        return $rows;
    }

    public function fetchDimensions(Period $period): array
    {
        return [];
    }

    public function describe(): array
    {
        return [
            'name' => 'meta_ads',
            'label' => __('Meta Ads (CSV)', 'fp-dms'),
            'credentials' => [],
            'config' => ['summary'],
        ];
    }

    /** @var array<string, string[]> */
    private const METRIC_ALIASES = [
        'clicks' => ['clicks', 'link_clicks'],
        'impressions' => ['impressions'],
        'conversions' => ['conversions', 'purchases', 'leads', 'website_purchases'],
        'cost' => ['cost', 'spend', 'amount_spent'],
        'revenue' => [
            'revenue',
            'purchase_conversion_value',
            'purchases_conversion_value',
            'website_purchase_conversion_value',
            'total_conversion_value',
        ],
    ];

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, float>
     */
    private static function mapMetrics(array $metrics): array
    {
        $normalized = [];
        foreach (self::METRIC_ALIASES as $target => $candidates) {
            $value = self::findMetricValue($metrics, $candidates);
            if ($value === null) {
                continue;
            }

            $normalized[$target] = $value;
        }

        return $normalized;
    }

    public static function ingestCsvSummary(string $csv): array
    {
        $rows = self::parseCsv($csv);
        if ($rows === []) {
            return [];
        }

        $daily = [];
        $totals = ['clicks' => 0.0, 'impressions' => 0.0, 'conversions' => 0.0, 'cost' => 0.0, 'revenue' => 0.0];

        foreach ($rows as $row) {
            $date = self::normalizeDate($row['date'] ?? '');
            if (! $date) {
                continue;
            }

            $metrics = [];
            foreach (self::METRIC_ALIASES as $key => $aliases) {
                $metrics[$key] = self::extractMetric($row, $aliases);
            }

            foreach ($metrics as $key => $value) {
                if ($value < 0) {
                    continue;
                }
                if (! isset($daily[$date])) {
                    $daily[$date] = [];
                }
                $daily[$date][$key] = round(($daily[$date][$key] ?? 0.0) + $value, 2);
                $totals[$key] = ($totals[$key] ?? 0.0) + $value;
            }
        }

        if ($daily === []) {
            return [];
        }

        ksort($daily);
        $totals = array_map(static fn(float $value): float => round($value, 2), $totals);

        return [
            'qa' => true,
            'metrics' => $totals,
            'daily' => $daily,
            'rows' => count($daily),
            'last_ingested_at' => Wp::currentTime('mysql'),
        ];
    }

    /**
     * @param array<string, string> $row
     * @param string[] $aliases
     */
    private static function extractMetric(array $row, array $aliases): float
    {
        $value = self::findMetricValue($row, $aliases);

        return $value ?? 0.0;
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $aliases
     */
    private static function findMetricValue(array $row, array $aliases): ?float
    {
        foreach ($aliases as $alias) {
            foreach (self::aliasCandidates($row, $alias) as $candidate) {
                $value = self::parseMetricValue($row[$candidate]);
                if ($value === null) {
                    continue;
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return string[]
     */
    private static function aliasCandidates(array $row, string $alias): array
    {
        $candidates = [];
        $bases = array_filter(array_unique([$alias, str_replace('_', '', $alias)]));
        $normalizedKeys = [];

        foreach ($row as $key => $_value) {
            if (! is_string($key)) {
                continue;
            }

            $sanitized = Wp::sanitizeKey($key);
            if ($sanitized === '') {
                continue;
            }

            if (! isset($normalizedKeys[$sanitized])) {
                $normalizedKeys[$sanitized] = $key;
            }
        }

        foreach ($bases as $base) {
            if (array_key_exists($base, $row)) {
                $candidates[] = $base;
            }

            if (isset($normalizedKeys[$base])) {
                $candidates[] = $normalizedKeys[$base];
            }

            foreach ($normalizedKeys as $sanitized => $original) {
                if ($sanitized === $base) {
                    continue;
                }

                if (self::aliasMatchesWithSuffix($sanitized, $base)) {
                    $candidates[] = $original;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function aliasMatchesWithSuffix(string $sanitized, string $base): bool
    {
        if ($sanitized === '' || $base === '') {
            return false;
        }

        if (str_starts_with($sanitized, $base . '_')) {
            $suffix = substr($sanitized, strlen($base) + 1);
        } elseif (str_starts_with($sanitized, $base)) {
            $suffix = substr($sanitized, strlen($base));
        } else {
            return false;
        }

        if ($suffix === '') {
            return false;
        }

        if (preg_match('/^[a-z]{3}$/', $suffix) === 1) {
            return true;
        }

        if (preg_match('/^[0-9][a-z0-9_-]*$/', $suffix) === 1) {
            return true;
        }

        if (in_array($suffix, ['default', 'standard'], true)) {
            return true;
        }

        if (preg_match('/^[a-z]{3}([a-z0-9_-]+)$/', $suffix, $matches) === 1) {
            $rest = $matches[1];

            if ($rest === '') {
                return true;
            }

            if (strpbrk($rest, '0123456789') !== false) {
                return true;
            }

            foreach (['click', 'view', 'day', 'week', 'month', 'hour', 'year'] as $keyword) {
                if (str_contains($rest, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param mixed $value Raw metric value from a CSV cell or cached summary.
     */
    private static function parseMetricValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $number = self::toNumber($trimmed);
        if ($number === 0.0 && preg_match('/[0-9]/', $trimmed) !== 1) {
            return null;
        }

        return $number;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function parseCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        if (! $lines) {
            return [];
        }

        $header = str_getcsv(array_shift($lines) ?: '', ',', '"', '\\');
        if (! $header) {
            return [];
        }

        $keys = array_map(static fn($value) => Wp::sanitizeKey($value), $header);
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $data = str_getcsv($line, ',', '"', '\\');
            if (! $data) {
                continue;
            }
            $assoc = [];
            foreach ($keys as $index => $key) {
                $assoc[$key] = $data[$index] ?? '';
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    private static function normalizeDate(string $date): ?string
    {
        $timestamp = strtotime(trim($date));
        if (! $timestamp) {
            return null;
        }

        return Wp::date('Y-m-d', $timestamp);
    }

    private static function toNumber(string $value): float
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0.0;
        }

        $negative = false;
        if (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            $negative = true;
            $trimmed = substr($trimmed, 1, -1);
        }

        $clean = preg_replace('/[^0-9\.,-]/', '', $trimmed) ?? '';
        if ($clean === '') {
            return 0.0;
        }

        if (str_contains($clean, '-')) {
            $negative = true;
            $clean = str_replace('-', '', $clean);
        }

        if ($clean === '' || preg_match('/[0-9]/', $clean) !== 1) {
            return 0.0;
        }

        $commaCount = substr_count($clean, ',');
        $dotCount = substr_count($clean, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($commaCount > 0) {
            $lastComma = strrchr($clean, ',');
            $decimals = $lastComma === false ? 0 : strlen($lastComma) - 1;
            if ($commaCount === 1 && $decimals > 0 && $decimals <= 2) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($dotCount > 0) {
            $lastDot = strrchr($clean, '.');
            $decimals = $lastDot === false ? 0 : strlen($lastDot) - 1;
            if ($dotCount > 1 || $decimals > 2) {
                $clean = str_replace('.', '', $clean);
            }
        }

        $number = (float) $clean;

        return $negative ? -$number : $number;
    }
}

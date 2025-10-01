<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Dates;
use FP\DMS\Support\Period;
use function __;

class GoogleAdsProvider implements DataSourceProviderInterface
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

        return ConnectionResult::failure(__('No CSV data available for Google Ads.', 'fp-dms'));
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
                $rows[] = Normalizer::ensureKeys(array_merge(
                    ['source' => 'google_ads', 'date' => $dateString],
                    self::mapMetrics($metrics)
                ));
            }
        } elseif (isset($summary['metrics']) && is_array($summary['metrics'])) {
            $rows[] = Normalizer::ensureKeys(array_merge(
                ['source' => 'google_ads', 'date' => $period->end->format('Y-m-d')],
                self::mapMetrics($summary['metrics'])
            ));
        } else {
            foreach (Dates::rangeDays($period->start, $period->end) as $date) {
                $rows[] = Normalizer::ensureKeys(['source' => 'google_ads', 'date' => $date]);
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
            'name' => 'google_ads',
            'label' => __('Google Ads (CSV)', 'fp-dms'),
            'credentials' => [],
            'config' => ['summary'],
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, float>
     */
    private static function mapMetrics(array $metrics): array
    {
        $map = [
            'clicks' => 'clicks',
            'impressions' => 'impressions',
            'conversions' => 'conversions',
            'cost' => 'cost',
            'spend' => 'cost',
            'revenue' => 'revenue',
        ];

        $normalized = [];
        foreach ($map as $sourceKey => $target) {
            if (! isset($metrics[$sourceKey]) || ! is_numeric($metrics[$sourceKey])) {
                continue;
            }
            $normalized[$target] = ($normalized[$target] ?? 0.0) + (float) $metrics[$sourceKey];
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

            $metrics = [
                'clicks' => self::toNumber($row['clicks'] ?? $row['click'] ?? ''),
                'impressions' => self::toNumber($row['impressions'] ?? ''),
                'conversions' => self::toNumber($row['conversions'] ?? $row['purchases'] ?? $row['leads'] ?? ''),
                'cost' => self::toNumber($row['cost'] ?? $row['spend'] ?? ''),
                'revenue' => self::toNumber($row['revenue'] ?? ''),
            ];

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
            'last_ingested_at' => current_time('mysql'),
        ];
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

        $header = str_getcsv(array_shift($lines) ?: '');
        if (! $header) {
            return [];
        }

        $keys = array_map(static fn($value) => sanitize_key((string) $value), $header);
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $data = str_getcsv($line);
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

        return wp_date('Y-m-d', $timestamp);
    }

    private static function toNumber(string $value): float
    {
        $clean = preg_replace('/[^0-9\.,-]/', '', $value);
        if ($clean === null || $clean === '') {
            return 0.0;
        }

        $clean = str_replace(',', '', $clean);

        return (float) $clean;
    }
}

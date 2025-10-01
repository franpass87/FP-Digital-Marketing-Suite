<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Dates;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use function __;

class CsvGenericProvider implements DataSourceProviderInterface
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

        return ConnectionResult::failure(__('No CSV data available for this connector.', 'fp-dms'));
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
                    ['source' => 'csv_generic', 'date' => $dateString],
                    self::mapMetrics($metrics)
                ));
            }
        } elseif (isset($summary['metrics']) && is_array($summary['metrics'])) {
            $rows[] = Normalizer::ensureKeys(array_merge(
                ['source' => 'csv_generic', 'date' => $period->end->format('Y-m-d')],
                self::mapMetrics($summary['metrics'])
            ));
        } else {
            foreach (Dates::rangeDays($period->start, $period->end) as $date) {
                $rows[] = Normalizer::ensureKeys(['source' => 'csv_generic', 'date' => $date]);
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
            'name' => 'csv_generic',
            'label' => __('Generic CSV Data Source', 'fp-dms'),
            'credentials' => [],
            'config' => ['source_label'],
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, float>
     */
    private static function mapMetrics(array $metrics): array
    {
        $map = [
            'users' => 'users',
            'sessions' => 'sessions',
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
        $totals = ['users' => 0.0, 'sessions' => 0.0, 'clicks' => 0.0, 'impressions' => 0.0, 'conversions' => 0.0, 'cost' => 0.0, 'revenue' => 0.0];

        foreach ($rows as $row) {
            $date = self::normalizeDate($row['date'] ?? '');
            if (! $date) {
                continue;
            }

            $metrics = [];
            foreach (['users', 'sessions', 'clicks', 'impressions', 'conversions', 'cost', 'spend', 'revenue'] as $key) {
                if (! isset($row[$key])) {
                    continue;
                }
                $value = self::toNumber($row[$key]);
                if ($value < 0) {
                    continue;
                }
                $target = $key === 'spend' ? 'cost' : $key;
                $metrics[$target] = ($metrics[$target] ?? 0.0) + $value;
            }

            if ($metrics === []) {
                continue;
            }

            foreach ($metrics as $metric => $value) {
                if (! isset($daily[$date])) {
                    $daily[$date] = [];
                }
                $daily[$date][$metric] = round(($daily[$date][$metric] ?? 0.0) + $value, 2);
                $totals[$metric] = ($totals[$metric] ?? 0.0) + $value;
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
        $clean = preg_replace('/[^0-9\.,-]/', '', $value);
        if ($clean === null || $clean === '') {
            return 0.0;
        }

        $clean = str_replace(',', '', $clean);

        return (float) $clean;
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Dates;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use function __;

class GSCProvider implements DataSourceProviderInterface
{
    public function __construct(private array $auth, private array $config)
    {
    }

    public function testConnection(): ConnectionResult
    {
        $json = $this->auth['service_account'] ?? '';
        $siteUrl = $this->config['site_url'] ?? '';

        if (! $json || ! $siteUrl) {
            return ConnectionResult::failure(__('Missing service account or site URL.', 'fp-dms'));
        }

        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            return ConnectionResult::failure(__('Invalid service account JSON.', 'fp-dms'));
        }

        return ConnectionResult::success(__('Credentials look valid. Run a report to confirm data.', 'fp-dms'), [
            'site_url' => $siteUrl,
            'client_email' => $decoded['client_email'],
        ]);
    }

    public function fetchMetrics(Period $period): array
    {
        $rows = [];
        $summary = $this->config['summary'] ?? [];
        if (is_array($summary) && isset($summary['daily']) && is_array($summary['daily'])) {
            foreach ($summary['daily'] as $date => $metrics) {
                if (! is_array($metrics)) {
                    continue;
                }
                $dateString = (string) $date;
                if ($dateString === 'total') {
                    $dateString = $period->end->format('Y-m-d');
                }
                $rows[] = Normalizer::ensureKeys(array_merge(
                    ['source' => 'gsc', 'date' => $dateString],
                    self::mapMetrics($metrics)
                ));
            }
        } elseif (is_array($summary) && isset($summary['metrics']) && is_array($summary['metrics'])) {
            $rows[] = Normalizer::ensureKeys(array_merge(
                ['source' => 'gsc', 'date' => $period->end->format('Y-m-d')],
                self::mapMetrics($summary['metrics'])
            ));
        } elseif (! empty($this->config['emit_empty'])) {
            foreach (Dates::rangeDays($period->start, $period->end) as $date) {
                $rows[] = Normalizer::ensureKeys(['source' => 'gsc', 'date' => $date]);
            }
        }

        return $rows;
    }

    public function fetchDimensions(Period $period): array
    {
        $summary = $this->config['summary'] ?? [];
        if (! is_array($summary)) {
            return [];
        }

        $dimensions = [];
        foreach (['queries', 'pages'] as $key) {
            if (isset($summary[$key]) && is_array($summary[$key])) {
                $dimensions[$key] = array_slice($summary[$key], 0, 10);
            }
        }

        return $dimensions;
    }

    public function describe(): array
    {
        return [
            'name' => 'gsc',
            'label' => __('Google Search Console', 'fp-dms'),
            'credentials' => ['service_account'],
            'config' => ['site_url'],
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
        ];

        $normalized = [];
        foreach ($map as $sourceKey => $target) {
            if (! isset($metrics[$sourceKey]) || ! is_numeric($metrics[$sourceKey])) {
                continue;
            }
            $normalized[$target] = (float) $metrics[$sourceKey];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public static function ingestCsvSummary(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $daily = [];
        $totals = ['clicks' => 0.0, 'impressions' => 0.0, 'conversions' => 0.0];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $date = isset($row['date']) ? self::normalizeDate((string) $row['date']) : null;
            if (! $date) {
                continue;
            }

            $metrics = self::mapMetrics($row);
            if ($metrics === []) {
                continue;
            }

            foreach ($metrics as $metric => $value) {
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

    private static function normalizeDate(string $value): ?string
    {
        $timestamp = strtotime(trim($value));
        if (! $timestamp) {
            return null;
        }

        return Wp::date('Y-m-d', $timestamp);
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Dates;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use function __;
use function apply_filters;

class GA4Provider implements DataSourceProviderInterface
{
    public function __construct(private array $auth, private array $config)
    {
    }

    public function testConnection(): ConnectionResult
    {
        $json = $this->resolveServiceAccount();
        $propertyId = $this->config['property_id'] ?? '';

        if (! $json || ! $propertyId) {
            return ConnectionResult::failure(__('Missing service account or property ID.', 'fp-dms'));
        }

        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            return ConnectionResult::failure(__('Invalid service account JSON.', 'fp-dms'));
        }

        return ConnectionResult::success(__('Credentials look valid. Run a report to confirm data.', 'fp-dms'), [
            'property_id' => $propertyId,
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
                if (! Normalizer::isWithinPeriod($period, $dateString)) {
                    continue;
                }

                $rows[] = Normalizer::ensureKeys(array_merge(
                    ['source' => 'ga4', 'date' => $dateString],
                    self::mapMetrics($metrics)
                ));
            }
        } elseif (is_array($summary) && isset($summary['metrics']) && is_array($summary['metrics'])) {
            $rows[] = Normalizer::ensureKeys(array_merge(
                ['source' => 'ga4', 'date' => $period->end->format('Y-m-d')],
                self::mapMetrics($summary['metrics'])
            ));
        } elseif (! empty($this->config['emit_empty'])) {
            foreach (Dates::rangeDays($period->start, $period->end) as $date) {
                $rows[] = Normalizer::ensureKeys(['source' => 'ga4', 'date' => $date]);
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

        $top = [];
        foreach (['top_pages', 'top_events'] as $key) {
            if (isset($summary[$key]) && is_array($summary[$key])) {
                $top[$key] = array_slice($summary[$key], 0, 10);
            }
        }

        return $top;
    }

    public function describe(): array
    {
        return [
            'name' => 'ga4',
            'label' => __('Google Analytics 4', 'fp-dms'),
            'credentials' => ['service_account'],
            'config' => ['property_id'],
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
            'total_revenue' => 'revenue',
        ];

        $normalized = [];
        foreach ($map as $sourceKey => $target) {
            if (! isset($metrics[$sourceKey])) {
                continue;
            }
            $value = $metrics[$sourceKey];
            if (! is_numeric($value)) {
                continue;
            }
            $normalized[$target] = ($normalized[$target] ?? 0.0) + (float) $value;
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
        $totals = ['users' => 0.0, 'sessions' => 0.0, 'revenue' => 0.0];

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

    private function resolveServiceAccount(): string
    {
        $source = $this->auth['credential_source'] ?? 'manual';
        if ($source === 'constant') {
            $constant = $this->auth['service_account_constant'] ?? '';
            if (! is_string($constant) || $constant === '' || ! defined($constant)) {
                return '';
            }
            $value = constant($constant);
            return is_string($value) ? $value : '';
        }

        $serviceAccount = (string) ($this->auth['service_account'] ?? '');

        /**
         * Allow developers to load the GA4 service account JSON from custom locations.
         *
         * @param string $serviceAccount JSON payload used to authenticate with GA4.
         * @param array<string, mixed> $auth Raw authentication data saved with the data source.
         * @param array<string, mixed> $config Connector configuration for the data source.
         */
        return (string) apply_filters('fpdms/connector/ga4/service_account', $serviceAccount, $this->auth, $this->config);
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Services\Anomalies;

use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Infra\Options;
use FP\DMS\Services\Anomalies\Engine;
use FP\DMS\Support\Period;

class Detector
{
    private Engine $engine;

    public function __construct(private AnomaliesRepo $repo)
    {
        $this->engine = new Engine($repo);
    }

    /**
     * Legacy compatibility wrapper that maps the historical evaluate signature
     * to the new engine. Metrics are assumed to represent the latest day.
     *
     * @param array<string,array<string,float|int>> $current
     * @param array<string,array<string,float|int>> $previous
     * @param array<int,array<string,float>> $history
     * @return array<int,array<string,mixed>>
     */
    public function evaluate(int $clientId, array $current, array $previous = [], array $history = [], bool $qaTag = false): array
    {
        // Get client timezone instead of hardcoding UTC
        $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        $client = $clientsRepo->find($clientId);
        $timezone = $client?->timezone ?? 'UTC';
        
        $period = Period::fromStrings(
            gmdate('Y-m-d', strtotime('-6 days')),
            gmdate('Y-m-d'),
            $timezone
        );

        $meta = [
            'metrics_daily' => $this->legacyRows($current),
            'previous_totals' => $this->aggregateTotals($previous),
        ];

        return $this->evaluatePeriod($clientId, $period, $meta, $history, $qaTag);
    }

    /**
     * Evaluates anomalies given a rich report metadata payload.
     *
     * @param array<int,array<string,mixed>> $history
     * @return array<int,array<string,mixed>>
     */
    public function evaluatePeriod(int $clientId, Period $period, array $meta, array $history = [], bool $qaTag = false): array
    {
        $policy = Options::getAnomalyPolicy($clientId);
        $policy['_context'] = [
            'daily' => is_array($meta['metrics_daily'] ?? null) ? $meta['metrics_daily'] : [],
            'previous_totals' => is_array($meta['previous_totals'] ?? null) ? $meta['previous_totals'] : [],
            'history' => $history,
            'qa' => $qaTag,
        ];

        $anomalies = $this->engine->evaluateClientPeriod($clientId, $period, $policy);

        if ($qaTag) {
            foreach ($anomalies as &$anomaly) {
                $anomaly['qa'] = true;
            }
            unset($anomaly); // CRITICAL: Unset reference to prevent memory corruption
        }

        return $anomalies;
    }

    /**
     * @param array<string,array<string,float|int>> $buckets
     * @return array<string,float>
     */
    private function aggregateTotals(array $buckets): array
    {
        $totals = [];
        foreach ($buckets as $metrics) {
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
     * @param array<string,array<string,float|int>> $current
     * @return array<int,array<string,mixed>>
     */
    private function legacyRows(array $current): array
    {
        $rows = [];
        foreach ($current as $source => $metrics) {
            if (! is_array($metrics)) {
                continue;
            }
            $rows[] = array_merge(
                ['source' => is_string($source) ? $source : 'aggregate', 'date' => gmdate('Y-m-d')],
                array_map(static fn($value) => is_numeric($value) ? (float) $value : 0.0, $metrics)
            );
        }

        return $rows;
    }
}

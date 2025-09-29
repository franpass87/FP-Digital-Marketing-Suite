<?php

declare(strict_types=1);

namespace FP\DMS\Services\Anomalies;

use FP\DMS\Domain\Repos\AnomaliesRepo;

class Detector
{
    public function __construct(private AnomaliesRepo $repo)
    {
    }

    /**
     * @param array<string,array<string,float|int>> $current
     * @param array<string,array<string,float|int>> $previous
     * @param array<int,array<string,float>> $history
     * @return array<int,array<string,mixed>>
     */
    public function evaluate(int $clientId, array $current, array $previous = [], array $history = [], bool $qaTag = false): array
    {
        $anomalies = [];
        $currentTotals = $this->aggregateTotals($current);
        $previousTotals = $this->aggregateTotals($previous);

        foreach ($currentTotals as $metric => $value) {
            $prev = $previousTotals[$metric] ?? null;
            $series = $this->extractSeries($history, $metric);
            $deltaPercent = $prev && $prev > 0.0 ? (($value - $prev) / $prev) * 100 : null;
            $zScore = $this->computeZScore($series, $value);

            $isDeltaAnomaly = $deltaPercent !== null && abs($deltaPercent) >= 30.0;
            $isZScoreAnomaly = $zScore !== null && abs($zScore) >= 2.0;

            if (! $isDeltaAnomaly && ! $isZScoreAnomaly) {
                continue;
            }

            $severity = (abs((float) ($deltaPercent ?? 0)) >= 50.0 || ($zScore !== null && abs($zScore) >= 3.0)) ? 'critical' : 'warn';

            $payload = [
                'metric' => $metric,
                'current' => round($value, 2),
                'previous' => $prev !== null ? round((float) $prev, 2) : null,
                'delta_percent' => $deltaPercent !== null ? round($deltaPercent, 2) : null,
                'z_score' => $zScore !== null ? round($zScore, 2) : null,
                'resolved' => false,
                'note' => '',
            ];

            if ($qaTag) {
                $payload['qa'] = true;
            }

            $this->repo->create([
                'client_id' => $clientId,
                'type' => $metric,
                'severity' => $severity,
                'payload' => $payload,
                'detected_at' => current_time('mysql'),
            ]);

            $anomalies[] = $payload + ['severity' => $severity];
        }

        return $anomalies;
    }

    /**
     * @param array<string,array<string,float|int>> $buckets
     * @return array<string,float>
     */
    private function aggregateTotals(array $buckets): array
    {
        $totals = array_fill_keys(['users', 'sessions', 'clicks', 'impressions', 'conversions', 'cost', 'revenue'], 0.0);
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
     * @param array<int,array<string,float>> $history
     * @return array<int,float>
     */
    private function extractSeries(array $history, string $metric): array
    {
        $series = [];
        foreach ($history as $periodTotals) {
            if (! is_array($periodTotals) || ! isset($periodTotals[$metric])) {
                continue;
            }
            $series[] = (float) $periodTotals[$metric];
        }

        return array_slice($series, 0, 8);
    }

    /**
     * @param array<int,float> $series
     */
    private function computeZScore(array $series, float $current): ?float
    {
        if (count($series) < 3) {
            return null;
        }

        $mean = array_sum($series) / count($series);
        $variance = 0.0;
        foreach ($series as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $variance /= count($series);
        $stdDev = sqrt($variance);
        if ($stdDev <= 0.0) {
            return null;
        }

        return ($current - $mean) / $stdDev;
    }
}

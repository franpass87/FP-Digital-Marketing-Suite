<?php

declare(strict_types=1);

namespace FP\DMS\Services\Anomalies;

use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Infra\Logger;
use FP\DMS\Support\Period;

class Engine
{
    public function __construct(
        private AnomaliesRepo $repo,
        private Baseline $baseline = new Baseline(),
        private Detectors $detectors = new Detectors(),
    ) {
    }

    /**
     * Evaluates the configured metrics for the provided client/period.
     *
     * The policy array can include a special `_context` key with:
     *  - daily: array<int,array<string,mixed>>
     *  - previous_totals: array<string,float>
     *  - history: array<int,array<string,float>>
     *
     * @return array<int,array<string,mixed>>
     */
    public function evaluateClientPeriod(int $clientId, Period $period, array $policy): array
    {
        $metricsPolicy = is_array($policy['metrics'] ?? null) ? $policy['metrics'] : [];
        $context = is_array($policy['_context'] ?? null) ? $policy['_context'] : [];
        $dailyRows = is_array($context['daily'] ?? null) ? $context['daily'] : [];
        $previousTotals = is_array($context['previous_totals'] ?? null) ? $context['previous_totals'] : [];
        $historyTotals = is_array($context['history'] ?? null) ? $context['history'] : [];
        $qaMode = ! empty($context['qa']);

        $timeSeries = new TimeSeries($dailyRows);
        $grouped = $timeSeries->groupByDate();
        if (empty($grouped)) {
            return [];
        }

        $dates = $timeSeries->dates();
        $seasonality = isset($policy['baseline']['seasonality']) ? (string) $policy['baseline']['seasonality'] : 'dow';
        $ewmaAlpha = isset($policy['baseline']['ewma_alpha']) ? (float) $policy['baseline']['ewma_alpha'] : 0.3;
        $cusumK = isset($policy['baseline']['cusum_k']) ? (float) $policy['baseline']['cusum_k'] : 0.5;
        $cusumH = isset($policy['baseline']['cusum_h']) ? (float) $policy['baseline']['cusum_h'] : 5.0;
        $windowDays = isset($policy['baseline']['window_days']) ? (int) $policy['baseline']['window_days'] : 28;

        $results = [];
        foreach ($metricsPolicy as $metric => $config) {
            $series = $timeSeries->metricSeries($metric);
            if (empty($series) && $metric === 'spend') {
                $series = $timeSeries->metricSeries('cost');
            }
            if (empty($series)) {
                continue;
            }

            $seriesByDate = [];
            foreach ($dates as $index => $date) {
                $seriesByDate[$date] = $series[$index] ?? 0.0;
            }

            $historySeries = [];
            foreach ($historyTotals as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $value = $row[$metric] ?? null;
                if ($value === null && $metric === 'spend' && isset($row['cost'])) {
                    $value = $row['cost'];
                }
                $historySeries[] = (float) ($value ?? 0.0);
            }

            $combinedSeries = array_merge($historySeries, $series);
            if (count($combinedSeries) < 2) {
                continue;
            }

            $zResult = $this->detectors->zScore($combinedSeries);
            $ewmaResult = $this->detectors->ewmaDeviation($combinedSeries, $ewmaAlpha);
            $cusumResult = $this->detectors->cusum($combinedSeries, $cusumK, $cusumH);

            $seasonal = $this->baseline->seasonalBaseline($seriesByDate, $seasonality);
            $rolling = $this->baseline->rollingMean($series, min($windowDays, max(count($series) - 1, 1)));

            $actual = (float) end($series);
            $expected = (float) end($seasonal) ?: ($zResult['expected'] ?? 0.0);
            $baselineValue = (float) end($rolling);

            $prevKey = $metric;
            if ($prevKey === 'spend' && ! isset($previousTotals[$prevKey]) && isset($previousTotals['cost'])) {
                $prevKey = 'cost';
            }
            $prevValue = isset($previousTotals[$prevKey]) ? (float) $previousTotals[$prevKey] : null;
            $deltaPercent = null;
            if ($prevValue !== null && abs($prevValue) > 0.0001) {
                $deltaPercent = (($actual - $prevValue) / abs($prevValue)) * 100;
            }

            $metricPolicy = $this->normaliseMetricPolicy($config);
            $severity = $this->decideSeverity($deltaPercent, $zResult['z'], $ewmaResult['score'], $cusumResult['detected'], $metricPolicy);
            if ($severity === null) {
                continue;
            }

            [$algo, $score] = $this->decideAlgorithm($deltaPercent, $zResult, $ewmaResult, $cusumResult, $metricPolicy);
            $pValue = $zResult['z'] !== null ? $this->twoTailedPValue((float) $zResult['z']) : null;

            $payload = [
                'metric' => $metric,
                'severity' => $severity,
                'delta_percent' => $deltaPercent !== null ? round($deltaPercent, 2) : null,
                'z_score' => $zResult['z'] !== null ? round((float) $zResult['z'], 2) : null,
                'ewma_score' => round($ewmaResult['score'], 2),
                'cusum_score' => round($cusumResult['score'], 2),
                'expected' => round($expected, 2),
                'actual' => round($actual, 2),
                'baseline' => round($baselineValue, 2),
                'period' => [
                    'start' => $period->start->format('Y-m-d'),
                    'end' => $period->end->format('Y-m-d'),
                ],
                'resolved' => false,
                'note' => '',
            ];

            if ($qaMode) {
                $payload['qa'] = true;
            }

            $this->repo->create([
                'client_id' => $clientId,
                'type' => $metric,
                'severity' => $severity,
                'payload' => $payload,
                'algo' => $algo,
                'score' => $score,
                'expected' => $expected,
                'actual' => $actual,
                'baseline' => $baselineValue,
                'z' => $zResult['z'],
                'p_value' => $pValue,
                'window' => count($series),
                'detected_at' => current_time('mysql'),
            ]);

            Logger::logAnomaly($clientId, $metric, $severity, [
                'algo' => $algo,
                'delta' => $deltaPercent,
                'z' => $zResult['z'],
                'ewma' => $ewmaResult['score'],
                'cusum' => $cusumResult['score'],
                'qa' => $qaMode,
            ]);

            $results[] = $payload;
        }

        return $results;
    }

    private function decideSeverity(?float $delta, ?float $z, float $ewmaScore, bool $cusumDetected, array $policy): ?string
    {
        $absDelta = $delta !== null ? abs($delta) : null;
        $absZ = $z !== null ? abs($z) : null;

        $isCritical = (
            ($absDelta !== null && $absDelta >= $policy['crit_pct']) ||
            ($absZ !== null && $absZ >= $policy['z_crit']) ||
            $cusumDetected
        );

        if ($isCritical) {
            return 'critical';
        }

        $isWarn = (
            ($absDelta !== null && $absDelta >= $policy['warn_pct']) ||
            ($absZ !== null && $absZ >= $policy['z_warn']) ||
            ($ewmaScore >= $policy['warn_pct'])
        );

        return $isWarn ? 'warn' : null;
    }

    /**
     * @return array{0:string,1:float}
     */
    private function decideAlgorithm(?float $delta, array $zResult, array $ewmaResult, array $cusumResult, array $policy): array
    {
        $absDelta = $delta !== null ? abs($delta) : 0.0;
        $absZ = $zResult['z'] !== null ? abs((float) $zResult['z']) : 0.0;
        $ewmaScore = (float) $ewmaResult['score'];
        $cusumScore = (float) $cusumResult['score'];

        if ($cusumResult['detected']) {
            return ['cusum', $cusumScore];
        }

        if ($absZ >= $policy['z_warn']) {
            return ['zscore', $absZ];
        }

        if ($absDelta >= $policy['warn_pct']) {
            return ['delta', $absDelta];
        }

        if ($ewmaScore >= $policy['warn_pct']) {
            return ['ewma', $ewmaScore];
        }

        return ['hybrid', max($absDelta, $absZ, $ewmaScore, $cusumScore)];
    }

    /**
     * @param array<string,float|int> $config
     * @return array{warn_pct:float,crit_pct:float,z_warn:float,z_crit:float}
     */
    private function normaliseMetricPolicy(array $config): array
    {
        return [
            'warn_pct' => isset($config['warn_pct']) ? (float) $config['warn_pct'] : 20.0,
            'crit_pct' => isset($config['crit_pct']) ? (float) $config['crit_pct'] : 40.0,
            'z_warn' => isset($config['z_warn']) ? (float) $config['z_warn'] : 1.5,
            'z_crit' => isset($config['z_crit']) ? (float) $config['z_crit'] : 3.0,
        ];
    }

    private function twoTailedPValue(float $z): float
    {
        $cdf = $this->gaussianCdf($z);
        $p = 2 * (1 - $cdf);

        return max(min($p, 1.0), 0.0);
    }

    private function gaussianCdf(float $z): float
    {
        $abs = abs($z);
        $t = 1.0 / (1.0 + 0.2316419 * $abs);
        $coeffs = [0.319381530, -0.356563782, 1.781477937, -1.821255978, 1.330274429];
        $poly = 0.0;
        $tPow = $t;
        foreach ($coeffs as $coeff) {
            $poly += $coeff * $tPow;
            $tPow *= $t;
        }
        $phi = (1 / sqrt(2 * pi())) * exp(-0.5 * $abs * $abs);
        $cdf = 1 - $phi * $poly;
        if ($z < 0) {
            $cdf = 1 - $cdf;
        }

        return $cdf;
    }
}

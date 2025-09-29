<?php

declare(strict_types=1);

namespace FP\DMS\Services\Anomalies;

use RuntimeException;

/**
 * Implements anomaly scoring algorithms.
 */
class Detectors
{
    /**
     * @param float[] $series
     * @return array{z:float|null,expected:float,actual:float,score:float}
     */
    public function zScore(array $series): array
    {
        $count = count($series);
        if ($count < 3) {
            return ['z' => null, 'expected' => $series[$count - 1] ?? 0.0, 'actual' => $series[$count - 1] ?? 0.0, 'score' => 0.0];
        }

        $actual = (float) $series[$count - 1];
        $history = array_slice($series, 0, $count - 1);
        $mean = array_sum($history) / max(count($history), 1);
        $variance = 0.0;
        foreach ($history as $value) {
            $variance += ((float) $value - $mean) ** 2;
        }
        $variance /= max(count($history) - 1, 1);
        $stdDev = sqrt(max($variance, 0.0));
        if ($stdDev <= 0.0) {
            return ['z' => null, 'expected' => $mean, 'actual' => $actual, 'score' => abs($actual - $mean)];
        }

        $z = ($actual - $mean) / $stdDev;

        return ['z' => $z, 'expected' => $mean, 'actual' => $actual, 'score' => abs($z)];
    }

    /**
     * @param float[] $series
     * @return array{score:float,expected:float,actual:float}
     */
    public function ewmaDeviation(array $series, float $alpha): array
    {
        $count = count($series);
        if ($count === 0) {
            return ['score' => 0.0, 'expected' => 0.0, 'actual' => 0.0];
        }

        $actual = (float) $series[$count - 1];
        if ($count === 1) {
            return ['score' => 0.0, 'expected' => $actual, 'actual' => $actual];
        }

        $baseline = (new Baseline())->ewma(array_slice($series, 0, $count - 1), $alpha);
        $expected = end($baseline) ?: 0.0;
        $expected = is_float($expected) ? $expected : 0.0;
        $delta = $expected !== 0.0 ? (($actual - $expected) / max(abs($expected), 1e-6)) * 100 : ($actual !== 0.0 ? 100.0 : 0.0);

        return ['score' => abs($delta), 'expected' => $expected, 'actual' => $actual];
    }

    /**
     * @param float[] $series
     * @return array{score:float,detected:bool}
     */
    public function cusum(array $series, float $k, float $h): array
    {
        $count = count($series);
        if ($count < 2) {
            return ['score' => 0.0, 'detected' => false];
        }

        $mean = array_sum($series) / $count;
        $pos = 0.0;
        $neg = 0.0;
        $maxScore = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $value = (float) $series[$i];
            $pos = max(0.0, $pos + $value - $mean - $k);
            $neg = min(0.0, $neg + $value - $mean + $k);
            $maxScore = max($maxScore, abs($pos), abs($neg));
            if ($pos > $h || abs($neg) > $h) {
                return ['score' => $maxScore, 'detected' => true];
            }
        }

        return ['score' => $maxScore, 'detected' => false];
    }
}

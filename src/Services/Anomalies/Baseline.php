<?php

declare(strict_types=1);

namespace FP\DMS\Services\Anomalies;

use function array_slice;

/**
 * Baseline helpers for anomaly detection.
 */
class Baseline
{
    /**
     * @param float[] $series
     * @return float[]
     */
    public function rollingMean(array $series, int $window): array
    {
        $window = max(1, $window);
        $result = [];
        $count = count($series);
        for ($i = 0; $i < $count; $i++) {
            $start = max(0, $i - $window);
            $subset = array_slice($series, $start, $window, true);
            if (empty($subset)) {
                $result[$i] = $series[$i] ?? 0.0;
                continue;
            }
            $result[$i] = array_sum($subset) / count($subset);
        }

        return $result;
    }

    /**
     * @param array<string,float> $series Map of date => value
     * @return array<string,float>
     */
    public function seasonalBaseline(array $series, string $seasonality = 'dow'): array
    {
        if (empty($series)) {
            return [];
        }

        $seasonGroups = [];
        foreach ($series as $date => $value) {
            $key = $seasonality === 'dow' ? TimeSeries::dayOfWeek($date) : $seasonality;
            $seasonGroups[$key][] = (float) $value;
        }

        $seasonAverages = [];
        foreach ($seasonGroups as $key => $values) {
            $seasonAverages[$key] = array_sum($values) / max(count($values), 1);
        }

        $expected = [];
        foreach ($series as $date => $value) {
            $key = $seasonality === 'dow' ? TimeSeries::dayOfWeek($date) : $seasonality;
            $expected[$date] = $seasonAverages[$key] ?? (float) $value;
        }

        return $expected;
    }

    /**
     * @param float[] $series
     * @return float[]
     */
    public function ewma(array $series, float $alpha): array
    {
        $alpha = max(0.01, min($alpha, 1.0));
        $result = [];
        $prev = null;
        foreach ($series as $index => $value) {
            $value = (float) $value;
            if ($prev === null) {
                $prev = $value;
            } else {
                $prev = $alpha * $value + (1 - $alpha) * $prev;
            }
            $result[$index] = $prev;
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Services\Anomalies;

use DateTimeImmutable;
use DateTimeZone;
use FP\DMS\Services\Connectors\Normalizer;

/**
 * Helper to normalise and inspect time series built from aggregated daily metrics.
 */
class TimeSeries
{
    /** @var array<int,array<string,mixed>> */
    private array $rows;

    /** @var array<string,array<string,float>> */
    private array $grouped = [];

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = self::normalize($rows);
    }

    /**
     * Normalises raw connector rows to the canonical KPI format.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function normalize(array $rows): array
    {
        $normalised = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalised[] = Normalizer::ensureKeys($row);
        }

        return $normalised;
    }

    /**
     * Groups rows by day (Y-m-d) and sums numeric metrics.
     *
     * @return array<string,array<string,float>> keyed by date
     */
    public function groupByDate(): array
    {
        if (! empty($this->grouped)) {
            return $this->grouped;
        }

        $bucket = [];
        foreach ($this->rows as $row) {
            $date = isset($row['date']) ? (string) $row['date'] : '';
            if ($date === '') {
                continue;
            }
            if (! isset($bucket[$date])) {
                $bucket[$date] = [];
            }
            foreach ($row as $key => $value) {
                if ($key === 'date' || $key === 'source') {
                    continue;
                }
                if (! is_numeric($value)) {
                    continue;
                }
                $bucket[$date][$key] = ($bucket[$date][$key] ?? 0.0) + (float) $value;
            }
        }

        ksort($bucket);
        $this->grouped = $bucket;

        return $this->grouped;
    }

    /**
     * Returns the ordered series for the provided metric.
     *
     * @return float[] values sorted by date ascending
     */
    public function metricSeries(string $metric): array
    {
        $grouped = $this->groupByDate();
        $series = [];
        foreach ($grouped as $row) {
            $series[] = isset($row[$metric]) ? (float) $row[$metric] : 0.0;
        }

        return $series;
    }

    /**
     * @return string[]
     */
    public function dates(): array
    {
        $grouped = $this->groupByDate();

        return array_keys($grouped);
    }

    /**
     * @return array<string,float>
     */
    public function totals(): array
    {
        $totals = [];
        $grouped = $this->groupByDate();
        foreach ($grouped as $row) {
            foreach ($row as $key => $value) {
                if (! is_numeric($value)) {
                    continue;
                }
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $value;
            }
        }

        return $totals;
    }

    public static function dayOfWeek(string $date, string $timezone = 'UTC'): string
    {
        try {
            $dt = new DateTimeImmutable($date, new DateTimeZone($timezone));
        } catch (\Exception) {
            return '0';
        }

        return (string) $dt->format('N');
    }
}

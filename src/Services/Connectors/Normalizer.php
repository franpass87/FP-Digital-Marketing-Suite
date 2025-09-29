<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

class Normalizer
{
    /** @var string[] */
    private const NUMERIC_KEYS = ['users', 'sessions', 'clicks', 'impressions', 'cost', 'conversions', 'revenue'];

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function ensureKeys(array $row): array
    {
        $normalized = [];
        $normalized['source'] = isset($row['source']) ? (string) $row['source'] : '';
        $normalized['date'] = isset($row['date']) ? (string) $row['date'] : '';

        foreach (self::NUMERIC_KEYS as $key) {
            $value = $row[$key] ?? 0;
            $normalized[$key] = is_numeric($value) ? (float) $value : 0.0;
        }

        foreach ($row as $key => $value) {
            if (isset($normalized[$key]) || $key === 'source' || $key === 'date') {
                continue;
            }
            if (is_numeric($value)) {
                $normalized[$key] = (float) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> ...$collections
     * @return array<int, array<string, mixed>>
     */
    public static function mergeDaily(array ...$collections): array
    {
        $bucket = [];
        foreach ($collections as $rows) {
            foreach ($rows as $row) {
                $normalized = self::ensureKeys($row);
                $date = $normalized['date'] ?: 'total';
                if (! isset($bucket[$date])) {
                    $bucket[$date] = array_merge(
                        array_fill_keys(self::NUMERIC_KEYS, 0.0),
                        ['date' => $date],
                        $normalized,
                    );
                }
                foreach ($normalized as $key => $value) {
                    if ($key === 'date' || $key === 'source') {
                        continue;
                    }
                    $bucket[$date][$key] = ($bucket[$date][$key] ?? 0.0) + (float) $value;
                }
            }
        }

        return array_values($bucket);
    }
}

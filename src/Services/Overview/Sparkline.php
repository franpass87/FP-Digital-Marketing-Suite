<?php

declare(strict_types=1);

namespace FP\DMS\Services\Overview;

class Sparkline
{
    /**
     * @param array<int, float> $values
     * @return array<int, array{y: float, x: int}>
     */
    public static function normalize(array $values, int $maxPoints = 20): array
    {
        $values = array_values(array_map('floatval', $values));
        if ($values === []) {
            return [];
        }

        $sampled = self::downSample($values, $maxPoints);
        $max = max($sampled);
        $min = min($sampled);
        $range = $max - $min;
        if ($range <= 0) {
            $range = 1;
        }

        $points = [];
        foreach ($sampled as $index => $value) {
            $points[] = [
                'x' => $index,
                'y' => ($value - $min) / $range,
            ];
        }

        return $points;
    }

    /**
     * @param array<int, float> $values
     * @return array<int, float>
     */
    private static function downSample(array $values, int $limit): array
    {
        $total = count($values);
        if ($total <= $limit) {
            return $values;
        }

        // Prevent division by zero
        $limit = max(1, $limit);
        $ratio = $total / $limit;

        $sampled = [];
        for ($i = 0; $i < $total; $i++) {
            $bucket = (int) floor($i / $ratio);
            if (! isset($sampled[$bucket])) {
                $sampled[$bucket] = [];
            }
            $sampled[$bucket][] = $values[$i];
        }

        return array_map(
            static fn(array $bucket): float => array_sum($bucket) / max(count($bucket), 1),
            $sampled
        );
    }
}

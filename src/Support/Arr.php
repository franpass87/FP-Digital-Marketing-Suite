<?php

declare(strict_types=1);

namespace FP\DMS\Support;

class Arr
{
    /**
     * @template T
     * @param array<string|int, T> $array
     * @param string|int $key
     * @param T|null $default
     * @return T|null
     */
    public static function get(array $array, string|int $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $default;
    }

    /**
     * @param array<string|int, mixed> $array
     * @param string $prefix
     * @return array<string, mixed>
     */
    public static function flattenKeys(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $compound = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flattenKeys($value, $compound);
                continue;
            }
            $result[$compound] = $value;
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $array
     * @param callable $callback
     * @return array<int|string, mixed>
     */
    public static function mapKeys(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[$callback($key, $value)] = $value;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, float|int>> $rows
     */
    public static function sumBy(array $rows, string $key): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $value = $row[$key] ?? 0;
            if (is_numeric($value)) {
                $total += (float) $value;
            }
        }

        return $total;
    }

    /**
     * @template T
     * @param array<int, T> $rows
     * @param callable(T):string|int $callback
     * @return array<string|int, array<int, T>>
     */
    public static function groupBy(array $rows, callable $callback): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = $callback($row);
            $grouped[$key] ??= [];
            $grouped[$key][] = $row;
        }

        return $grouped;
    }
}

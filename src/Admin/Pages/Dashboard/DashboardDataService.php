<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Dashboard;

use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Infra\DB;

use function __;
use function is_array;
use function sprintf;

use const ARRAY_A;

/**
 * Handles data retrieval and transformation for the dashboard
 */
class DashboardDataService
{
    /**
     * Get client directory (id => name mapping)
     *
     * @return array<int, string>
     */
    public static function getClientDirectory(): array
    {
        $repo = new ClientsRepo();
        $clients = $repo->all();
        $map = [];

        foreach ($clients as $client) {
            if ($client->id !== null) {
                $map[(int) $client->id] = $client->name;
            }
        }

        return $map;
    }

    /**
     * Get dashboard statistics
     *
     * @return array<string, int>
     */
    public static function getStats(): array
    {
        return [
            'clients' => self::countRows('clients'),
            'datasources' => self::countRows('datasources'),
            'active_schedules' => self::countRows('schedules', 'active = %d', [1]),
            'templates' => self::countRows('templates'),
        ];
    }

    /**
     * Get recent reports with formatted data
     *
     * @param array<int, string> $clientNames
     * @return array<int, array{client: string, status: string, period: string, created: string}>
     */
    public static function getRecentReports(array $clientNames, int $limit = 5): array
    {
        global $wpdb;

        $table = DB::table('reports');
        $sql = $wpdb->prepare(
            "SELECT client_id, status, period_start, period_end, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $clientId = isset($row['client_id']) ? (int) $row['client_id'] : 0;
            $clientName = $clientNames[$clientId] ?? sprintf(__('Client #%d', 'fp-dms'), $clientId);
            $period = DateFormatter::dateRange($row['period_start'] ?? null, $row['period_end'] ?? null);
            $created = DateFormatter::dateTime($row['created_at'] ?? null);
            $status = isset($row['status']) ? (string) $row['status'] : 'queued';

            $items[] = [
                'client' => $clientName,
                'status' => $status,
                'period' => $period,
                'created' => $created,
            ];
        }

        return $items;
    }

    /**
     * Get recent anomalies with formatted data
     *
     * @param array<int, string> $clientNames
     * @return array<int, array{client: string, type: string, severity: string, detected: string}>
     */
    public static function getRecentAnomalies(array $clientNames, int $limit = 5): array
    {
        $repo = new AnomaliesRepo();
        $records = $repo->recent($limit);
        $items = [];

        foreach ($records as $anomaly) {
            $clientId = $anomaly->clientId;
            $clientName = $clientNames[$clientId] ?? sprintf(__('Client #%d', 'fp-dms'), $clientId);
            $type = DateFormatter::humanizeType($anomaly->type);
            $detected = DateFormatter::dateTime($anomaly->detectedAt);

            $items[] = [
                'client' => $clientName,
                'type' => $type,
                'severity' => $anomaly->severity,
                'detected' => $detected,
            ];
        }

        return $items;
    }

    /**
     * Count rows in a table with optional WHERE clause
     */
    private static function countRows(string $table, string $where = '', array $params = []): int
    {
        global $wpdb;

        $sql = 'SELECT COUNT(*) FROM ' . DB::table($table);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $prepared = $params !== [] ? $wpdb->prepare($sql, $params) : $sql;
        $result = $wpdb->get_var($prepared);

        return $result !== null ? (int) $result : 0;
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use FP\DMS\Domain\Entities\ReportJob;
use FP\DMS\Infra\DB;
use FP\DMS\Support\Wp;
use wpdb;

class ReportsRepo
{
    private string $table;

    public function __construct()
    {
        $this->table = DB::table('reports');
    }

    public function find(int $id): ?ReportJob
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? ReportJob::fromRow($row) : null;
    }

    public function nextQueued(): ?ReportJob
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table} WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1";
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? ReportJob::fromRow($row) : null;
    }

    /**
     * @return ReportJob[]
     */
    public function forClient(int $clientId): array
    {
        return $this->search(['client_id' => $clientId]);
    }

    /**
     * @param array<string,mixed> $criteria
     * @return ReportJob[]
     */
    public function search(array $criteria = []): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if (isset($criteria['client_id'])) {
            $where[] = 'client_id = %d';
            $params[] = (int) $criteria['client_id'];
        }

        if (isset($criteria['status'])) {
            $where[] = 'status = %s';
            $params[] = (string) $criteria['status'];
        }

        if (! empty($criteria['status_in']) && is_array($criteria['status_in'])) {
            $statuses = array_map(static fn($status): string => (string) $status, $criteria['status_in']);
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $where[] = 'status IN (' . $placeholders . ')';
            array_push($params, ...$statuses);
        }

        if (isset($criteria['created_before'])) {
            $where[] = 'created_at < %s';
            $params[] = (string) $criteria['created_before'];
        }

        $sql = 'SELECT * FROM ' . $this->table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
        $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): ReportJob => ReportJob::fromRow($row), $rows);
    }

    public function findByClientAndPeriod(int $clientId, string $start, string $end, ?array $statuses = null): ?ReportJob
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table} WHERE client_id = %d AND period_start = %s AND period_end = %s";
        $params = [$clientId, $start, $end];

        if ($statuses && $statuses !== []) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $sql .= ' AND status IN (' . $placeholders . ')';
            foreach ($statuses as $status) {
                $params[] = (string) $status;
            }
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 1';
        $prepared = $wpdb->prepare($sql, $params);
        $row = $wpdb->get_row($prepared, ARRAY_A);

        return is_array($row) ? ReportJob::fromRow($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): ?ReportJob
    {
        global $wpdb;
        $now = Wp::currentTime('mysql');
        $payload = [
            'client_id' => (int) ($data['client_id'] ?? 0),
            'period_start' => (string) ($data['period_start'] ?? ''),
            'period_end' => (string) ($data['period_end'] ?? ''),
            'status' => (string) ($data['status'] ?? 'queued'),
            'storage_path' => $data['storage_path'] ?? null,
            'meta' => Wp::jsonEncode($data['meta'] ?? []) ?: '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert($this->table, $payload, ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        if ($result === false) {
            return null;
        }

        return $this->find((int) $wpdb->insert_id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;
        $payload = [];
        $formats = [];

        foreach (['status', 'storage_path', 'period_start', 'period_end'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
                $formats[] = '%s';
            }
        }

        if (array_key_exists('meta', $data)) {
            $payload['meta'] = Wp::jsonEncode($data['meta']) ?: '[]';
            $formats[] = '%s';
        }

        if ($payload === []) {
            return true;
        }

        $payload['updated_at'] = Wp::currentTime('mysql');
        $formats[] = '%s';

        $result = $wpdb->update($this->table, $payload, ['id' => $id], $formats, ['%d']);

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $result !== false;
    }
}

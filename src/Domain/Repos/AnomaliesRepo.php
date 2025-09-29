<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use FP\DMS\Domain\Entities\Anomaly;
use FP\DMS\Infra\DB;
use wpdb;

class AnomaliesRepo
{
    private string $table;

    public function __construct()
    {
        $this->table = DB::table('anomalies');
    }

    /**
     * @return Anomaly[]
     */
    public function recent(int $limit = 50): array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} ORDER BY detected_at DESC LIMIT %d", $limit);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): Anomaly => Anomaly::fromRow($row), $rows);
    }

    /**
     * @return Anomaly[]
     */
    public function recentForClient(int $clientId, int $limit = 20): array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE client_id = %d ORDER BY detected_at DESC LIMIT %d", $clientId, $limit);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): Anomaly => Anomaly::fromRow($row), $rows);
    }

    public function create(array $data): ?Anomaly
    {
        global $wpdb;
        $payloadData = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $payloadData += ['resolved' => false, 'note' => ''];

        $payload = [
            'client_id' => (int) ($data['client_id'] ?? 0),
            'type' => (string) ($data['type'] ?? ''),
            'severity' => (string) ($data['severity'] ?? 'info'),
            'payload' => wp_json_encode($payloadData),
            'detected_at' => (string) ($data['detected_at'] ?? current_time('mysql')),
            'notified' => empty($data['notified']) ? 0 : 1,
        ];

        $result = $wpdb->insert($this->table, $payload, ['%d', '%s', '%s', '%s', '%s', '%d']);
        if ($result === false) {
            return null;
        }

        return $this->find((int) $wpdb->insert_id);
    }

    public function find(int $id): ?Anomaly
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? Anomaly::fromRow($row) : null;
    }

    public function markNotified(int $id, bool $notified): bool
    {
        global $wpdb;
        $result = $wpdb->update($this->table, ['notified' => $notified ? 1 : 0], ['id' => $id], ['%d'], ['%d']);

        return $result !== false;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function updatePayload(int $id, array $payload): bool
    {
        global $wpdb;
        $payload += ['resolved' => false, 'note' => ''];

        $result = $wpdb->update(
            $this->table,
            ['payload' => wp_json_encode($payload)],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function countForClient(int $clientId): int
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE client_id = %d", $clientId);
        $count = $wpdb->get_var($sql);

        return $count ? (int) $count : 0;
    }

    public function deleteByClient(int $clientId): int
    {
        global $wpdb;
        $result = $wpdb->delete($this->table, ['client_id' => $clientId], ['%d']);

        return $result !== false ? (int) $result : 0;
    }
}

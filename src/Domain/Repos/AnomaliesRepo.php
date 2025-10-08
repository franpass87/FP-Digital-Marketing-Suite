<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use FP\DMS\Domain\Entities\Anomaly;
use FP\DMS\Infra\DB;
use FP\DMS\Support\Wp;
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
        
        if ($sql === false) {
            return [];
        }
        
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
        
        if ($sql === false) {
            return [];
        }
        
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

        $payloadJson = Wp::jsonEncode($payloadData);
        if ($payloadJson === false) {
            error_log('[FPDMS] JSON encode failed for anomaly payload');
            $payloadJson = '[]';
        }

        // Build payload with proper NULL handling
        $payload = [
            'client_id' => (int) ($data['client_id'] ?? 0),
            'type' => (string) ($data['type'] ?? ''),
            'severity' => (string) ($data['severity'] ?? 'info'),
            'payload' => $payloadJson,
            'detected_at' => (string) ($data['detected_at'] ?? Wp::currentTime('mysql')),
            'notified' => empty($data['notified']) ? 0 : 1,
        ];
        
        $formats = ['%d', '%s', '%s', '%s', '%s', '%d'];
        
        // Add nullable fields only if they have values
        if (isset($data['algo'])) {
            $payload['algo'] = (string) $data['algo'];
            $formats[] = '%s';
        }
        if (isset($data['score'])) {
            $payload['score'] = (float) $data['score'];
            $formats[] = '%f';
        }
        if (isset($data['expected'])) {
            $payload['expected'] = (float) $data['expected'];
            $formats[] = '%f';
        }
        if (isset($data['actual'])) {
            $payload['actual'] = (float) $data['actual'];
            $formats[] = '%f';
        }
        if (isset($data['baseline'])) {
            $payload['baseline'] = (float) $data['baseline'];
            $formats[] = '%f';
        }
        if (isset($data['z'])) {
            $payload['z'] = (float) $data['z'];
            $formats[] = '%f';
        }
        if (isset($data['p_value'])) {
            $payload['p_value'] = (float) $data['p_value'];
            $formats[] = '%f';
        }
        if (isset($data['window'])) {
            $payload['window'] = (int) $data['window'];
            $formats[] = '%d';
        }

        $result = $wpdb->insert($this->table, $payload, $formats);
        if ($result === false) {
            return null;
        }

        return $this->find((int) $wpdb->insert_id);
    }

    public function find(int $id): ?Anomaly
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        
        if ($sql === false) {
            return null;
        }
        
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

        $payloadJson = Wp::jsonEncode($payload);
        if ($payloadJson === false) {
            error_log('[FPDMS] JSON encode failed for anomaly payload in update');
            return false;
        }

        $result = $wpdb->update(
            $this->table,
            ['payload' => $payloadJson],
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
        
        if ($sql === false) {
            return 0;
        }
        
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

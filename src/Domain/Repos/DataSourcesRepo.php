<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use FP\DMS\Domain\Entities\DataSource;
use FP\DMS\Infra\DB;
use FP\DMS\Support\Security;
use wpdb;

class DataSourcesRepo
{
    private string $table;

    public function __construct()
    {
        $this->table = DB::table('datasources');
    }

    /**
     * @return DataSource[]
     */
    public function forClient(int $clientId): array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE client_id = %d ORDER BY id DESC", $clientId);
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): DataSource => DataSource::fromRow($row), $rows);
    }

    public function find(int $id): ?DataSource
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? DataSource::fromRow($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): ?DataSource
    {
        global $wpdb;

        $now = current_time('mysql');
        $auth = wp_json_encode($data['auth'] ?? []);
        if (! is_string($auth)) {
            $auth = '[]';
        }

        $config = wp_json_encode($data['config'] ?? []);
        if (! is_string($config)) {
            $config = '[]';
        }

        $payload = [
            'client_id' => (int) ($data['client_id'] ?? 0),
            'type' => (string) ($data['type'] ?? ''),
            'auth' => Security::encrypt($auth),
            'config' => $config,
            'active' => empty($data['active']) ? 0 : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert($this->table, $payload, ['%d', '%s', '%s', '%s', '%d', '%s', '%s']);
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

        $auth = wp_json_encode($data['auth'] ?? []);
        if (! is_string($auth)) {
            $auth = '[]';
        }

        $config = wp_json_encode($data['config'] ?? []);
        if (! is_string($config)) {
            $config = '[]';
        }

        $payload = [
            'type' => (string) ($data['type'] ?? ''),
            'auth' => Security::encrypt($auth),
            'config' => $config,
            'active' => empty($data['active']) ? 0 : 1,
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->update($this->table, $payload, ['id' => $id], ['%s', '%s', '%s', '%d', '%s'], ['%d']);

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    public function deleteByClient(int $clientId): int
    {
        $deleted = 0;
        foreach ($this->forClient($clientId) as $dataSource) {
            if ($this->delete($dataSource->id ?? 0)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

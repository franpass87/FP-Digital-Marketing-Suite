<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Infra\DB;
use wpdb;

class ClientsRepo
{
    private string $table;

    public function __construct()
    {
        $this->table = DB::table('clients');
    }

    /**
     * @return Client[]
     */
    public function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY name ASC", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): Client => Client::fromRow($row), $rows);
    }

    public function find(int $id): ?Client
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? Client::fromRow($row) : null;
    }

    public function findByName(string $name): ?Client
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE name = %s LIMIT 1", $name);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? Client::fromRow($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): ?Client
    {
        global $wpdb;

        $now = current_time('mysql');
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'email_to' => wp_json_encode(array_values($data['email_to'] ?? [])),
            'email_cc' => wp_json_encode(array_values($data['email_cc'] ?? [])),
            'timezone' => (string) ($data['timezone'] ?? 'UTC'),
            'notes' => (string) ($data['notes'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $inserted = $wpdb->insert($this->table, $payload, ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        if ($inserted === false) {
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

        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'email_to' => wp_json_encode(array_values($data['email_to'] ?? [])),
            'email_cc' => wp_json_encode(array_values($data['email_cc'] ?? [])),
            'timezone' => (string) ($data['timezone'] ?? 'UTC'),
            'notes' => (string) ($data['notes'] ?? ''),
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->update($this->table, $payload, ['id' => $id], ['%s', '%s', '%s', '%s', '%s', '%s'], ['%d']);

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $result !== false;
    }
}

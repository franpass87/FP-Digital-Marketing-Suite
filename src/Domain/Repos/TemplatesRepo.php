<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use FP\DMS\Domain\Entities\Template;
use FP\DMS\Infra\DB;
use FP\DMS\Support\Wp;
use wpdb;

class TemplatesRepo
{
    private string $table;

    public function __construct()
    {
        $this->table = DB::table('templates');
    }

    /**
     * @return Template[]
     */
    public function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY is_default DESC, name ASC", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): Template => Template::fromRow($row), $rows);
    }

    public function find(int $id): ?Template
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? Template::fromRow($row) : null;
    }

    public function findDefault(): ?Template
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE is_default = 1 ORDER BY id ASC LIMIT %d", 1);
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? Template::fromRow($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): ?Template
    {
        global $wpdb;
        $now = Wp::currentTime('mysql');
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'is_default' => empty($data['is_default']) ? 0 : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert($this->table, $payload, ['%s', '%s', '%s', '%d', '%s', '%s']);
        if ($result === false) {
            return null;
        }

        if (! empty($payload['is_default'])) {
            $this->clearOtherDefaults((int) $wpdb->insert_id);
        }

        return $this->find((int) $wpdb->insert_id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;
        $current = $this->find($id);
        if (! $current) {
            return false;
        }

        $name = array_key_exists('name', $data) ? (string) $data['name'] : $current->name;
        $description = array_key_exists('description', $data) ? (string) $data['description'] : $current->description;
        $content = array_key_exists('content', $data) ? (string) $data['content'] : $current->content;
        $isDefault = array_key_exists('is_default', $data)
            ? (! empty($data['is_default']) ? 1 : 0)
            : ($current->isDefault ? 1 : 0);

        $payload = [
            'name' => $name,
            'description' => $description,
            'content' => $content,
            'is_default' => $isDefault,
            'updated_at' => Wp::currentTime('mysql'),
        ];

        $result = $wpdb->update($this->table, $payload, ['id' => $id], ['%s', '%s', '%s', '%d', '%s'], ['%d']);

        if ($result !== false && ! empty($payload['is_default'])) {
            $this->clearOtherDefaults($id);
        }

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    private function clearOtherDefaults(int $keepId): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE {$this->table} SET is_default = 0 WHERE id != %d", $keepId));
    }
}

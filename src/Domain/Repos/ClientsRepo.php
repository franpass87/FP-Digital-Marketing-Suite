<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use DateTimeZone;
use Exception;
use FP\DMS\Domain\Entities\Client;
use FP\DMS\Infra\DB;
use FP\DMS\Support\Wp;
use wpdb;

use function explode;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

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

        $now = Wp::currentTime('mysql');
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'email_to' => Wp::jsonEncode($this->sanitizeEmailList($data['email_to'] ?? [])) ?: '[]',
            'email_cc' => Wp::jsonEncode($this->sanitizeEmailList($data['email_cc'] ?? [])) ?: '[]',
            'timezone' => $this->normalizeTimezone((string) ($data['timezone'] ?? ''), 'UTC'),
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

        $current = $this->find($id);
        if (! $current) {
            return false;
        }

        $name = array_key_exists('name', $data) ? (string) $data['name'] : $current->name;
        $timezoneInput = array_key_exists('timezone', $data) ? (string) $data['timezone'] : $current->timezone;
        $timezone = $this->normalizeTimezone($timezoneInput, $current->timezone);
        $notes = array_key_exists('notes', $data) ? (string) $data['notes'] : $current->notes;

        $emailToInput = array_key_exists('email_to', $data) ? $data['email_to'] : $current->emailTo;
        $emailCcInput = array_key_exists('email_cc', $data) ? $data['email_cc'] : $current->emailCc;

        $payload = [
            'name' => $name,
            'email_to' => Wp::jsonEncode($this->sanitizeEmailList($emailToInput)) ?: '[]',
            'email_cc' => Wp::jsonEncode($this->sanitizeEmailList($emailCcInput)) ?: '[]',
            'timezone' => $timezone,
            'notes' => $notes,
            'updated_at' => Wp::currentTime('mysql'),
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

    /**
     * @param mixed $input
     *
     * @return string[]
     */
    private function sanitizeEmailList(mixed $input): array
    {
        $items = [];

        if (is_string($input)) {
            $items = explode(',', $input);
        } elseif (is_array($input)) {
            $items = $input;
        }

        $sanitized = [];

        foreach ($items as $email) {
            if (! is_string($email)) {
                continue;
            }

            $normalized = Wp::sanitizeEmail(trim($email));
            if ($normalized === '' || ! Wp::isEmail($normalized)) {
                continue;
            }

            $normalized = strtolower($normalized);
            $sanitized[$normalized] = $normalized;
        }

        return array_values($sanitized);
    }

    private function normalizeTimezone(string $timezone, string $fallback): string
    {
        $candidate = trim($timezone);
        if ($candidate === '') {
            return $fallback;
        }

        try {
            new DateTimeZone($candidate);

            return $candidate;
        } catch (Exception $exception) {
            return $fallback;
        }
    }
}

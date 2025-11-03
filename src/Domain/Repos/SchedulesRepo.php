<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Repos;

use FP\DMS\Domain\Entities\Schedule;
use FP\DMS\Infra\DB;
use FP\DMS\Support\Wp;
use wpdb;

class SchedulesRepo
{
    private string $table;

    public function __construct()
    {
        $this->table = DB::table('schedules');
    }

    /**
     * @return Schedule[]
     */
    public function dueSchedules(string $now): array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE active = 1 AND next_run_at IS NOT NULL AND next_run_at <= %s ORDER BY next_run_at ASC", $now);

        if ($sql === false) {
            return [];
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): Schedule => Schedule::fromRow($row), $rows);
    }

    /**
     * @return Schedule[]
     */
    public function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY created_at DESC", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): Schedule => Schedule::fromRow($row), $rows);
    }

    /**
     * @return Schedule[]
     */
    public function forClient(int $clientId): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE client_id = %d ORDER BY created_at DESC", $clientId);

        if ($sql === false) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row): Schedule => Schedule::fromRow($row), $rows);
    }

    public function find(int $id): ?Schedule
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);

        if ($sql === false) {
            return null;
        }

        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? Schedule::fromRow($row) : null;
    }

    public function nextScheduledRun(): ?Schedule
    {
        global $wpdb;
        
        try {
            $sql = "SELECT * FROM {$this->table} WHERE active = 1 AND next_run_at IS NOT NULL ORDER BY next_run_at ASC LIMIT 1";
            $row = $wpdb->get_row($sql, ARRAY_A);

            return is_array($row) ? Schedule::fromRow($row) : null;
        } catch (\Throwable $e) {
            \error_log('[FPDMS SchedulesRepo] Failed to fetch next scheduled run: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): ?Schedule
    {
        global $wpdb;
        $now = Wp::currentTime('mysql');
        // Use cryptographically secure random for cron_key to prevent collisions
        $cronKey = isset($data['cron_key']) && is_string($data['cron_key']) && $data['cron_key'] !== ''
            ? (string) $data['cron_key']
            : 'cron_' . bin2hex(random_bytes(16));

        $payload = [
            'client_id' => (int) ($data['client_id'] ?? 0),
            'cron_key' => $cronKey,
            'frequency' => (string) ($data['frequency'] ?? 'monthly'),
            'next_run_at' => $data['next_run_at'] ?? null,
            'last_run_at' => $data['last_run_at'] ?? null,
            'active' => empty($data['active']) ? 0 : 1,
            'template_id' => isset($data['template_id']) ? (int) $data['template_id'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert($this->table, $payload, ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);
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

        $current = $this->find($id);
        if (! $current) {
            return false;
        }

        $frequency = array_key_exists('frequency', $data) ? (string) $data['frequency'] : $current->frequency;
        $nextRunAt = array_key_exists('next_run_at', $data) ? $data['next_run_at'] : $current->nextRunAt;
        $lastRunAt = array_key_exists('last_run_at', $data) ? $data['last_run_at'] : $current->lastRunAt;
        $active = array_key_exists('active', $data) ? ! empty($data['active']) : $current->active;
        $templateId = array_key_exists('template_id', $data)
            ? (isset($data['template_id']) ? (int) $data['template_id'] : null)
            : $current->templateId;

        $payload = [
            'frequency' => $frequency,
            'next_run_at' => $nextRunAt,
            'last_run_at' => $lastRunAt,
            'active' => $active ? 1 : 0,
            'template_id' => $templateId,
            'updated_at' => Wp::currentTime('mysql'),
        ];

        $result = $wpdb->update($this->table, $payload, ['id' => $id], ['%s', '%s', '%s', '%d', '%d', '%s'], ['%d']);

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

        // Note: Reports are handled separately by client cleanup
        // Schedule IDs are stored in report meta, not as direct foreign keys
        foreach ($this->forClient($clientId) as $schedule) {
            $scheduleId = $schedule->id ?? 0;

            if ($scheduleId > 0 && $this->delete($scheduleId)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

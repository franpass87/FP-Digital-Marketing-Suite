<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

class Schedule
{
    public function __construct(
        public ?int $id,
        public int $clientId,
        public string $cronKey,
        public string $frequency,
        public ?string $nextRunAt,
        public ?string $lastRunAt,
        public bool $active,
        public ?int $templateId,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['client_id'] ?? 0),
            (string) ($row['cron_key'] ?? ''),
            (string) ($row['frequency'] ?? ''),
            isset($row['next_run_at']) ? (string) $row['next_run_at'] : null,
            isset($row['last_run_at']) ? (string) $row['last_run_at'] : null,
            (bool) ($row['active'] ?? false),
            isset($row['template_id']) ? (int) $row['template_id'] : null,
            (string) ($row['created_at'] ?? ''),
            (string) ($row['updated_at'] ?? ''),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'cron_key' => $this->cronKey,
            'frequency' => $this->frequency,
            'next_run_at' => $this->nextRunAt,
            'last_run_at' => $this->lastRunAt,
            'active' => $this->active ? 1 : 0,
            'template_id' => $this->templateId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

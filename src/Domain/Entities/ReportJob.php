<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

class ReportJob
{
    public function __construct(
        public ?int $id,
        public int $clientId,
        public string $periodStart,
        public string $periodEnd,
        public string $status,
        public ?string $storagePath,
        public array $meta,
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
            (string) ($row['period_start'] ?? ''),
            (string) ($row['period_end'] ?? ''),
            (string) ($row['status'] ?? ''),
            isset($row['storage_path']) ? (string) $row['storage_path'] : null,
            self::decodeMeta($row['meta'] ?? '[]'),
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
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'status' => $this->status,
            'storage_path' => $this->storagePath,
            'meta' => wp_json_encode($this->meta),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodeMeta(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

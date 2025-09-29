<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

class Anomaly
{
    public function __construct(
        public ?int $id,
        public int $clientId,
        public string $type,
        public string $severity,
        public array $payload,
        public string $detectedAt,
        public bool $notified,
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
            (string) ($row['type'] ?? ''),
            (string) ($row['severity'] ?? 'info'),
            self::decodePayload($row['payload'] ?? '[]'),
            (string) ($row['detected_at'] ?? ''),
            (bool) ($row['notified'] ?? false),
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
            'type' => $this->type,
            'severity' => $this->severity,
            'payload' => wp_json_encode($this->payload),
            'detected_at' => $this->detectedAt,
            'notified' => $this->notified ? 1 : 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodePayload(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

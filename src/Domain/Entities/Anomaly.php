<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

use FP\DMS\Support\Wp;

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
        public ?string $algo = null,
        public ?float $score = null,
        public ?float $expected = null,
        public ?float $actual = null,
        public ?float $baseline = null,
        public ?float $z = null,
        public ?float $pValue = null,
        public ?int $window = null,
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
            isset($row['algo']) ? (string) $row['algo'] : null,
            isset($row['score']) ? (float) $row['score'] : null,
            isset($row['expected']) ? (float) $row['expected'] : null,
            isset($row['actual']) ? (float) $row['actual'] : null,
            isset($row['baseline']) ? (float) $row['baseline'] : null,
            isset($row['z']) ? (float) $row['z'] : null,
            isset($row['p_value']) ? (float) $row['p_value'] : null,
            isset($row['window']) ? (int) $row['window'] : null,
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
            'payload' => Wp::jsonEncode($this->payload) ?: '[]',
            'detected_at' => $this->detectedAt,
            'notified' => $this->notified ? 1 : 0,
            'algo' => $this->algo,
            'score' => $this->score,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'baseline' => $this->baseline,
            'z' => $this->z,
            'p_value' => $this->pValue,
            'window' => $this->window,
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

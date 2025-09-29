<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

use FP\DMS\Support\Security;

class DataSource
{
    public function __construct(
        public ?int $id,
        public int $clientId,
        public string $type,
        public array $auth,
        public array $config,
        public bool $active,
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
            (string) ($row['type'] ?? ''),
            self::decodeJson(Security::decrypt(is_string($row['auth'] ?? null) ? (string) $row['auth'] : '[]')),
            self::decodeJson($row['config'] ?? '[]'),
            (bool) ($row['active'] ?? false),
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
            'type' => $this->type,
            'auth' => wp_json_encode($this->auth),
            'config' => wp_json_encode($this->config),
            'active' => $this->active ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

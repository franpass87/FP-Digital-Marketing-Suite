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
        public ?string $authCipher,
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
        $cipher = is_string($row['auth'] ?? null) ? (string) $row['auth'] : '[]';
        $failed = false;
        $decoded = Security::decrypt($cipher, $failed);
        $auth = $failed ? [] : self::decodeJson($decoded);

        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['client_id'] ?? 0),
            (string) ($row['type'] ?? ''),
            $auth,
            $failed ? $cipher : null,
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
        $authJson = wp_json_encode($this->auth);
        if (! is_string($authJson)) {
            $authJson = '[]';
        }

        $authValue = $this->authCipher !== null && $this->authCipher !== ''
            ? $this->authCipher
            : Security::encrypt($authJson);

        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'type' => $this->type,
            'auth' => $authValue,
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

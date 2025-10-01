<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

use FP\DMS\Support\Wp;

class Client
{
    public function __construct(
        public ?int $id,
        public string $name,
        public array $emailTo,
        public array $emailCc,
        public string $timezone,
        public string $notes,
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
            (string) ($row['name'] ?? ''),
            self::decodeEmails($row['email_to'] ?? '[]'),
            self::decodeEmails($row['email_cc'] ?? '[]'),
            (string) ($row['timezone'] ?? 'UTC'),
            (string) ($row['notes'] ?? ''),
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
            'name' => $this->name,
            'email_to' => Wp::jsonEncode($this->emailTo) ?: '[]',
            'email_cc' => Wp::jsonEncode($this->emailCc) ?: '[]',
            'timezone' => $this->timezone,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @return string[]
     */
    private static function decodeEmails(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map('strval', $decoded));
    }
}

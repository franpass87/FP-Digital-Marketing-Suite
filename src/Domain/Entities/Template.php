<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Entities;

class Template
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $description,
        public string $content,
        public bool $isDefault,
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
            (string) ($row['description'] ?? ''),
            (string) ($row['content'] ?? ''),
            (bool) ($row['is_default'] ?? false),
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
            'description' => $this->description,
            'content' => $this->content,
            'is_default' => $this->isDefault ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

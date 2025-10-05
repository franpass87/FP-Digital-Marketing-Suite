<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Templates;

use FP\DMS\Support\Wp;

final class TemplateDraft
{
    public function __construct(
        public string $name,
        public string $description,
        public string $content,
        public bool $isDefault
    ) {
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            Wp::sanitizeTextField($input['name'] ?? ''),
            Wp::sanitizeTextField($input['description'] ?? ''),
            Wp::ksesPost($input['content'] ?? ''),
            ! empty($input['is_default'])
        );
    }

    public static function fromValues(string $name, string $description, string $content, bool $isDefault): self
    {
        return new self(
            Wp::sanitizeTextField($name),
            Wp::sanitizeTextField($description),
            Wp::ksesPost($content),
            $isDefault
        );
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Templates;

final class TemplateBlueprint
{
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public string $content
    ) {
    }

    /**
     * @return array{key:string,name:string,description:string,content:string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
        ];
    }
}

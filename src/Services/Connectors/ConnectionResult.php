<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

class ConnectionResult
{
    private function __construct(
        private bool $success,
        private string $message,
        private array $details = [],
    ) {
    }

    public static function success(string $message, array $details = []): self
    {
        return new self(true, $message, $details);
    }

    public static function failure(string $message, array $details = []): self
    {
        return new self(false, $message, $details);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string,mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}

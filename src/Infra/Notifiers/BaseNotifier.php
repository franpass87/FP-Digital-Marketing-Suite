<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

interface BaseNotifier
{
    /**
     * @param array<string,mixed> $payload
     */
    public function send(array $payload): bool;
}

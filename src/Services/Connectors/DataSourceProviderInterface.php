<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

interface DataSourceProviderInterface
{
    public function testConnection(): ConnectionResult;

    /**
     * @return array<int, array<string,mixed>>
     */
    public function fetchMetrics(Period $period): array;

    /**
     * @return array<int, array<string,mixed>>
     */
    public function fetchDimensions(Period $period): array;

    /**
     * @return array<string,mixed>
     */
    public function describe(): array;
}

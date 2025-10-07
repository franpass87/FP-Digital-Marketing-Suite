<?php

declare(strict_types=1);

use FP\DMS\Services\Connectors\DataSourceProviderInterface;
use FP\DMS\Services\Connectors\ProviderFactory;
use PHPUnit\Framework\TestCase;

final class ProviderFactoryTest extends TestCase
{
    public function testRegistryOverridesBuiltIn(): void
    {
        ProviderFactory::register('ga4', static function (array $auth, array $config): DataSourceProviderInterface {
            return new class($auth, $config) implements DataSourceProviderInterface {
                public function __construct(private array $auth, private array $config) {}
                public function testConnection(): \FP\DMS\Services\Connectors\ConnectionResult { return \FP\DMS\Services\Connectors\ConnectionResult::success('ok'); }
                public function fetchMetrics(\FP\DMS\Support\Period $period): array { return []; }
                public function fetchDimensions(\FP\DMS\Support\Period $period): array { return []; }
                public function describe(): array { return ['name' => 'ga4']; }
            };
        });

        $instance = ProviderFactory::create('ga4', [], []);
        $this->assertNotNull($instance);
        $this->assertSame('ga4', $instance->describe()['name']);
    }
}



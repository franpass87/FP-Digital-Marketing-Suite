<?php

declare(strict_types=1);

use FP\DMS\Services\Connectors\GA4Provider;
use FP\DMS\Services\Connectors\GSCProvider;
use FP\DMS\Support\Period;
use PHPUnit\Framework\TestCase;

final class GoogleProvidersTest extends TestCase
{
    public function testResolveServiceAccountManual(): void
    {
        $ga4 = new GA4Provider(['credential_source' => 'manual', 'service_account' => '{"k":"v"}'], ['property_id' => '1']);
        $res = $ga4->testConnection();
        $this->assertTrue($res->ok);

        $gsc = new GSCProvider(['credential_source' => 'manual', 'service_account' => '{"k":"v"}'], ['site_url' => 'https://ex.com']);
        $res2 = $gsc->testConnection();
        $this->assertTrue($res2->ok);
    }

    public function testNormalizeDateViaCsvIngest(): void
    {
        $summary = GA4Provider::ingestCsvSummary([
            ['date' => '2024-01-01', 'users' => 1, 'sessions' => 2],
            ['date' => '2024-01-02', 'users' => 3, 'sessions' => 4],
        ]);
        $this->assertIsArray($summary);
        $this->assertSame(2, $summary['rows']);
    }
}



<?php

declare(strict_types=1);

namespace FP\DMS\Tests\Unit;

use FP\DMS\Services\Connectors\MetaAdsProvider;
use FP\DMS\Support\Period;
use PHPUnit\Framework\TestCase;

final class MetaAdsProviderTest extends TestCase
{
    public function testFetchMetricsDoesNotDoubleCountSynonyms(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'clicks' => 100,
                    'impressions' => 200,
                    'conversions' => 10,
                    'purchases' => 5,
                    'leads' => 3,
                    'cost' => 50,
                    'spend' => 60,
                    'revenue' => 500,
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-01-01', '2024-01-31', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame('meta_ads', $row['source']);
        $this->assertSame('2024-01-31', $row['date']);
        $this->assertSame(100.0, $row['clicks']);
        $this->assertSame(200.0, $row['impressions']);
        $this->assertSame(10.0, $row['conversions']);
        $this->assertSame(50.0, $row['cost']);
        $this->assertSame(500.0, $row['revenue']);
    }

    public function testIngestCsvSummaryFallsBackToAlternativeAliases(): void
    {
        $csv = <<<CSV
            date,clicks,impressions,conversions,purchases,leads,cost,spend,revenue
            2024-01-01,10,100,,5,,,$12.34,50
            2024-01-02,15,200,7,,,4.56,,75
            2024-01-03,20,300,,,3,,9.10,125
            CSV;

        $summary = MetaAdsProvider::ingestCsvSummary($csv);

        $this->assertSame(3, $summary['rows']);
        $this->assertSame(
            [
                'clicks' => 45.0,
                'impressions' => 600.0,
                'conversions' => 15.0,
                'cost' => 26.0,
                'revenue' => 250.0,
            ],
            $summary['metrics']
        );

        $this->assertSame(5.0, $summary['daily']['2024-01-01']['conversions']);
        $this->assertSame(12.34, $summary['daily']['2024-01-01']['cost']);
        $this->assertSame(7.0, $summary['daily']['2024-01-02']['conversions']);
        $this->assertSame(4.56, $summary['daily']['2024-01-02']['cost']);
        $this->assertSame(3.0, $summary['daily']['2024-01-03']['conversions']);
        $this->assertSame(9.1, $summary['daily']['2024-01-03']['cost']);
    }

    public function testFetchMetricsParsesFormattedNumbersFromSummary(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'clicks' => '1,234',
                    'impressions' => '5,678',
                    'conversions' => '',
                    'purchases' => '12',
                    'cost' => '$1,234.56',
                    'revenue' => '€9,876.54',
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-02-01', '2024-02-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $this->assertSame(1234.0, $rows[0]['clicks']);
        $this->assertSame(5678.0, $rows[0]['impressions']);
        $this->assertSame(12.0, $rows[0]['conversions']);
        $this->assertSame(1234.56, $rows[0]['cost']);
        $this->assertSame(9876.54, $rows[0]['revenue']);
    }

    public function testFetchMetricsRecognizesPluralPurchaseConversionValue(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'Purchases conversion value' => '€1.234,56',
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-02-01', '2024-02-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $this->assertSame(1234.56, $rows[0]['revenue']);
    }

    public function testFetchMetricsHandlesAttributionWindowSuffixes(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'Purchase conversion value (1-day click)' => '$1,234.56',
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-06-01', '2024-06-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $this->assertSame(1234.56, $rows[0]['revenue']);
    }

    public function testFetchMetricsHandlesCurrencyAndAttributionSuffixes(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'Purchase conversion value (USD) (Last click)' => '$1,234.56',
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-06-01', '2024-06-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $this->assertSame(1234.56, $rows[0]['revenue']);
    }

    public function testFetchMetricsDoesNotTreatCostPerResultAsCost(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'Cost per result' => '$12.34',
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-07-01', '2024-07-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $this->assertSame(0.0, $rows[0]['cost']);
    }

    public function testFetchMetricsParsesEuropeanFormattedNumbers(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'clicks' => '1.234',
                    'impressions' => '5.678',
                    'conversions' => '3,5',
                    'cost' => '(€1.234,56)',
                    'revenue' => '€7.890,12',
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-03-01', '2024-03-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $this->assertSame(1234.0, $rows[0]['clicks']);
        $this->assertSame(5678.0, $rows[0]['impressions']);
        $this->assertSame(3.5, $rows[0]['conversions']);
        $this->assertSame(-1234.56, $rows[0]['cost']);
        $this->assertSame(7890.12, $rows[0]['revenue']);
    }

    public function testFetchMetricsUsesSanitizedMetaColumnNames(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'link_clicks' => '1,234',
                    'website_purchases' => '12',
                    'amount_spent_usd' => '$1,234.56',
                    'purchase_conversion_value_eur' => '€7.890,12',
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-04-01', '2024-04-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(1, $rows);
        $this->assertSame(1234.0, $rows[0]['clicks']);
        $this->assertSame(12.0, $rows[0]['conversions']);
        $this->assertSame(1234.56, $rows[0]['cost']);
        $this->assertSame(7890.12, $rows[0]['revenue']);
    }

    public function testIngestCsvSummaryNormalizesEuropeanFormats(): void
    {
        $csv = <<<CSV
            date,clicks,conversions,cost,revenue
            2024-03-01,"1.234","3,5","€1.234,56","€2.345,67"
            2024-03-02,"2.000","4","(€200,00)","€3.456,78"
            CSV;

        $summary = MetaAdsProvider::ingestCsvSummary($csv);

        $this->assertSame(2, $summary['rows']);
        $this->assertSame(
            [
                'clicks' => 3234.0,
                'impressions' => 0.0,
                'conversions' => 7.5,
                'cost' => 1234.56,
                'revenue' => 5802.45,
            ],
            $summary['metrics']
        );

        $this->assertSame(
            [
                'clicks' => 1234.0,
                'impressions' => 0.0,
                'conversions' => 3.5,
                'cost' => 1234.56,
                'revenue' => 2345.67,
            ],
            $summary['daily']['2024-03-01']
        );
        $this->assertSame(
            [
                'clicks' => 2000.0,
                'impressions' => 0.0,
                'conversions' => 4.0,
                'revenue' => 3456.78,
            ],
            $summary['daily']['2024-03-02']
        );
        $this->assertArrayNotHasKey('cost', $summary['daily']['2024-03-02']);
    }

    public function testIngestCsvSummaryHandlesSanitizedMetaColumns(): void
    {
        $csv = <<<CSV
            date,"Link clicks","Website purchases","Amount spent (USD)","Purchase conversion value (EUR)"
            2024-04-01,"1,234","7","$123.45","€456,78"
            2024-04-02,"2,000","5","$200.00","€321,00"
            CSV;

        $summary = MetaAdsProvider::ingestCsvSummary($csv);

        $this->assertSame(2, $summary['rows']);
        $this->assertSame(
            [
                'clicks' => 3234.0,
                'impressions' => 0.0,
                'conversions' => 12.0,
                'cost' => 323.45,
                'revenue' => 777.78,
            ],
            $summary['metrics']
        );

        $this->assertSame(1234.0, $summary['daily']['2024-04-01']['clicks']);
        $this->assertSame(7.0, $summary['daily']['2024-04-01']['conversions']);
        $this->assertSame(123.45, $summary['daily']['2024-04-01']['cost']);
        $this->assertSame(456.78, $summary['daily']['2024-04-01']['revenue']);

        $this->assertSame(2000.0, $summary['daily']['2024-04-02']['clicks']);
        $this->assertSame(5.0, $summary['daily']['2024-04-02']['conversions']);
        $this->assertSame(200.0, $summary['daily']['2024-04-02']['cost']);
        $this->assertSame(321.0, $summary['daily']['2024-04-02']['revenue']);
    }

    public function testIngestCsvSummaryHandlesAttributionWindowSuffixes(): void
    {
        $csv = <<<CSV
            date,clicks,"Purchases conversion value (1-day click)"
            2024-06-01,10,"€1.234,56"
            2024-06-02,5,"€2.000,00"
            CSV;

        $summary = MetaAdsProvider::ingestCsvSummary($csv);

        $this->assertSame(2, $summary['rows']);
        $this->assertSame(3234.56, $summary['metrics']['revenue']);
        $this->assertSame(1234.56, $summary['daily']['2024-06-01']['revenue']);
        $this->assertSame(2000.0, $summary['daily']['2024-06-02']['revenue']);
    }

    public function testIngestCsvSummaryHandlesCurrencyAndAttributionSuffixes(): void
    {
        $csv = <<<CSV
            date,clicks,"Purchase conversion value (USD) (Last click)"
            2024-07-01,8,"$1,234.56"
            2024-07-02,4,"$500.00"
            CSV;

        $summary = MetaAdsProvider::ingestCsvSummary($csv);

        $this->assertSame(2, $summary['rows']);
        $this->assertSame(1734.56, $summary['metrics']['revenue']);
        $this->assertSame(1234.56, $summary['daily']['2024-07-01']['revenue']);
        $this->assertSame(500.0, $summary['daily']['2024-07-02']['revenue']);
    }

    public function testFetchMetricsHandlesHumanReadableSummaryKeys(): void
    {
        $provider = new MetaAdsProvider([], [
            'summary' => [
                'metrics' => [
                    'Clicks' => '1,234',
                    'Impressions' => '5,678',
                    'Purchases' => '12',
                    'Cost (USD)' => '$1,234.56',
                    'Purchase Conversion Value (EUR)' => '€7.890,12',
                ],
                'daily' => [
                    '2024-05-01' => [
                        'Clicks' => '123',
                        'Impressions' => '456',
                        'Purchases' => '1',
                        'Cost (USD)' => '$12.34',
                        'Purchase Conversion Value (EUR)' => '€45,67',
                    ],
                    'total' => [
                        'Clicks' => '1,234',
                        'Impressions' => '5,678',
                        'Purchases' => '12',
                        'Cost (USD)' => '$1,234.56',
                        'Purchase Conversion Value (EUR)' => '€7.890,12',
                    ],
                ],
            ],
        ]);

        $period = Period::fromStrings('2024-05-01', '2024-05-07', 'UTC');

        $rows = $provider->fetchMetrics($period);

        $this->assertCount(2, $rows);

        $this->assertSame('2024-05-01', $rows[0]['date']);
        $this->assertSame(123.0, $rows[0]['clicks']);
        $this->assertSame(456.0, $rows[0]['impressions']);
        $this->assertSame(1.0, $rows[0]['conversions']);
        $this->assertSame(12.34, $rows[0]['cost']);
        $this->assertSame(45.67, $rows[0]['revenue']);

        $this->assertSame('2024-05-07', $rows[1]['date']);
        $this->assertSame(1234.0, $rows[1]['clicks']);
        $this->assertSame(5678.0, $rows[1]['impressions']);
        $this->assertSame(12.0, $rows[1]['conversions']);
        $this->assertSame(1234.56, $rows[1]['cost']);
        $this->assertSame(7890.12, $rows[1]['revenue']);
    }
}

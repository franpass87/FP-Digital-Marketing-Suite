<?php

namespace FP\DigitalMarketing;

/**
 * Provides mock data for demo reports
 */
class MockDataProvider
{
    /**
     * Generate mock analytics data for reports
     */
    public static function getAnalyticsData()
    {
        return [
            'period' => [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-d'),
                'display' => date('F Y')
            ],
            'kpis' => [
                'sessions' => [
                    'value' => rand(15000, 25000),
                    'change' => rand(-10, 25),
                    'label' => 'Sessions'
                ],
                'users' => [
                    'value' => rand(8000, 15000),
                    'change' => rand(-5, 20),
                    'label' => 'Users'
                ],
                'conversion_rate' => [
                    'value' => round(rand(200, 450) / 100, 2),
                    'change' => round(rand(-50, 80) / 100, 2),
                    'label' => 'Conversion Rate (%)'
                ],
                'revenue' => [
                    'value' => '€' . number_format(rand(50000, 150000), 0, ',', '.'),
                    'change' => rand(-15, 30),
                    'label' => 'Revenue'
                ]
            ],
            'charts' => [
                'traffic_trend' => self::generateTrafficTrend(),
                'conversion_funnel' => self::generateConversionFunnel(),
                'revenue_by_source' => self::generateRevenueBySource()
            ]
        ];
    }

    private static function generateTrafficTrend()
    {
        $data = [];
        for ($i = 30; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data[] = [
                'date' => $date,
                'sessions' => rand(400, 1200),
                'users' => rand(250, 800)
            ];
        }
        return $data;
    }

    private static function generateConversionFunnel()
    {
        return [
            ['step' => 'Visitors', 'count' => 10000, 'percentage' => 100],
            ['step' => 'Product Views', 'count' => 4500, 'percentage' => 45],
            ['step' => 'Add to Cart', 'count' => 1200, 'percentage' => 12],
            ['step' => 'Checkout', 'count' => 650, 'percentage' => 6.5],
            ['step' => 'Purchase', 'count' => 420, 'percentage' => 4.2]
        ];
    }

    private static function generateRevenueBySource()
    {
        return [
            ['source' => 'Organic Search', 'revenue' => 45000, 'percentage' => 35],
            ['source' => 'Paid Ads', 'revenue' => 38000, 'percentage' => 30],
            ['source' => 'Social Media', 'revenue' => 25000, 'percentage' => 20],
            ['source' => 'Email', 'revenue' => 12000, 'percentage' => 10],
            ['source' => 'Direct', 'revenue' => 6000, 'percentage' => 5]
        ];
    }
}
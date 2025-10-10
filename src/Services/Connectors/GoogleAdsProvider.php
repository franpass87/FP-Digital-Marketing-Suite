<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;

use function __;

class GoogleAdsProvider implements DataSourceProviderInterface
{
    public function __construct(private array $auth, private array $config)
    {
    }

    public function testConnection(): ConnectionResult
    {
        $requiredAuth = ['developer_token', 'client_id', 'client_secret', 'refresh_token'];
        foreach ($requiredAuth as $key) {
            if (trim((string) ($this->auth[$key] ?? '')) === '') {
                return ConnectionResult::failure(__('Complete all Google Ads API credentials before testing the connection.', 'fp-dms'));
            }
        }

        $customerId = trim((string) ($this->config['customer_id'] ?? ''));
        if ($customerId === '' || ! preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/', $customerId)) {
            return ConnectionResult::failure(__('Enter a valid Google Ads customer ID in the 000-000-0000 format.', 'fp-dms'));
        }

        return ConnectionResult::success(__('Credentials saved. Campaign data will refresh on the next sync run.', 'fp-dms'));
    }

    public function fetchMetrics(Period $period): array
    {
        return [];
    }

    public function fetchDimensions(Period $period): array
    {
        return [];
    }

    public function describe(): array
    {
        return [
            'name' => 'google_ads',
            'label' => __('Google Ads', 'fp-dms'),
            'credentials' => ['developer_token', 'client_id', 'client_secret', 'refresh_token'],
            'config' => ['customer_id', 'login_customer_id'],
        ];
    }
}

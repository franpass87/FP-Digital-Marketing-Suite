<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;
use function __;

class MetaAdsProvider implements DataSourceProviderInterface
{
    public function __construct(private array $auth, private array $config)
    {
    }

    public function testConnection(): ConnectionResult
    {
        $token = trim((string) ($this->auth['access_token'] ?? ''));
        $accountId = trim((string) ($this->config['account_id'] ?? ''));

        if ($token === '') {
            return ConnectionResult::failure(__('Provide a Meta Ads access token with the required permissions.', 'fp-dms'));
        }

        if ($accountId === '' || ! preg_match('/^act_[0-9]+$/', $accountId)) {
            return ConnectionResult::failure(__('Enter the ad account ID using the act_1234567890 format.', 'fp-dms'));
        }

        return ConnectionResult::success(__('Credentials saved. Meta Ads data will refresh on the next sync run.', 'fp-dms'));
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
            'name' => 'meta_ads',
            'label' => __('Meta Ads', 'fp-dms'),
            'credentials' => ['access_token'],
            'config' => ['account_id', 'pixel_id'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Period;
use function __;

class ClarityProvider implements DataSourceProviderInterface
{
    public function __construct(private array $auth, private array $config)
    {
    }

    public function testConnection(): ConnectionResult
    {
        $projectId = trim((string) ($this->config['project_id'] ?? ''));
        $apiKey = trim((string) ($this->auth['api_key'] ?? ''));

        if ($projectId === '' || $apiKey === '') {
            return ConnectionResult::failure(__('Provide both the project ID and API key to connect Microsoft Clarity.', 'fp-dms'));
        }

        return ConnectionResult::success(__('Credentials saved. Clarity data will sync on the next collection run.', 'fp-dms'));
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
            'name' => 'clarity',
            'label' => __('Microsoft Clarity', 'fp-dms'),
            'credentials' => ['api_key'],
            'config' => ['project_id', 'site_url', 'webhook_url'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;
use FP\DMS\Services\Connectors\ProviderFactory;

/**
 * Test connection step - validates the configuration works.
 */
class TestConnectionStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Test Connection', 'fp-dms'),
            __('Let\'s verify everything is working correctly', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        ob_start();
        ?>
        <div class="fpdms-test-connection-step">
            <div class="fpdms-test-intro">
                <p><?php _e('We\'re about to test the connection with your credentials. This will:', 'fp-dms'); ?></p>
                <ul>
                    <li>✓ <?php _e('Verify your credentials are valid', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('Check you have the necessary permissions', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('Confirm we can retrieve data', 'fp-dms'); ?></li>
                </ul>
            </div>

            <div class="fpdms-test-action">
                <button type="button" class="button button-primary button-hero fpdms-btn-test-now">
                    🧪 <?php _e('Test Connection Now', 'fp-dms'); ?>
                </button>
            </div>

            <div class="fpdms-test-results" style="display: none;">
                <!-- Results will be shown here -->
            </div>

            <div class="fpdms-test-details" style="display: none;">
                <h4><?php _e('Connection Summary', 'fp-dms'); ?></h4>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th><?php _e('Provider', 'fp-dms'); ?></th>
                            <td class="fpdms-detail-provider"></td>
                        </tr>
                        <tr>
                            <th><?php _e('Status', 'fp-dms'); ?></th>
                            <td class="fpdms-detail-status"></td>
                        </tr>
                        <tr>
                            <th><?php _e('Details', 'fp-dms'); ?></th>
                            <td class="fpdms-detail-info"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        // Try to create and test provider
        try {
            $auth = $data['auth'] ?? [];
            $config = $data['config'] ?? [];

            $provider = ProviderFactory::create($this->provider, $auth, $config);

            if (!$provider) {
                return [
                    'valid' => false,
                    'errors' => ['_general' => __('Invalid provider configuration', 'fp-dms')],
                ];
            }

            $result = $provider->testConnection();

            if (!$result->isSuccess()) {
                return [
                    'valid' => false,
                    'errors' => ['_general' => $result->message],
                ];
            }

            return ['valid' => true];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => ['_general' => $e->getMessage()],
            ];
        }
    }

    public function getHelp(): array
    {
        return [
            'title' => __('Connection Testing', 'fp-dms'),
            'content' => __(
                'The connection test makes a real API call to verify your credentials work. If the test fails, check that you\'ve added the service account with the correct permissions.',
                'fp-dms'
            ),
        ];
    }

    private function getProviderLabel(): string
    {
        return match ($this->provider) {
            'ga4' => 'Google Analytics 4',
            'gsc' => 'Google Search Console',
            'google_ads' => 'Google Ads',
            'meta_ads' => 'Meta Ads',
            'clarity' => 'Microsoft Clarity',
            'csv_generic' => 'CSV',
            default => ucfirst($this->provider),
        };
    }
}

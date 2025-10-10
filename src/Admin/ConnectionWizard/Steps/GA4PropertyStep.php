<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;
use FP\DMS\Services\Connectors\AutoDiscovery;
use FP\DMS\Services\Connectors\ConnectorException;

/**
 * GA4 Property selection step with auto-discovery.
 */
class GA4PropertyStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Select GA4 Property', 'fp-dms'),
            __('Choose which property to connect', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $serviceAccount = $data['auth']['service_account'] ?? '';
        $selectedPropertyId = $data['config']['property_id'] ?? '';

        ob_start();
        ?>
        <div class="fpdms-ga4-property-step">
            <?php if (!empty($serviceAccount)) : ?>
                <div class="fpdms-autodiscovery-section">
                    <button type="button" class="button button-secondary fpdms-btn-discover" data-provider="ga4">
                        🔍 <?php _e('Auto-discover my properties', 'fp-dms'); ?>
                    </button>
                    <p class="description">
                        <?php _e('We\'ll automatically find all GA4 properties accessible by your service account', 'fp-dms'); ?>
                    </p>
                </div>

                <div class="fpdms-resource-list" id="fpdms-discovered-properties" style="display: none;">
                    <!-- Properties will be loaded here via AJAX -->
                </div>

                <div class="fpdms-or-divider">
                    <span><?php _e('or enter manually', 'fp-dms'); ?></span>
                </div>
            <?php endif; ?>

            <div class="fpdms-manual-entry">
                <?php echo $this->renderTextField(
                    'property_id',
                    __('GA4 Property ID', 'fp-dms'),
                    $selectedPropertyId,
                    [
                        'required' => true,
                        'placeholder' => '123456789',
                        'description' => __('Find this in Google Analytics under Admin → Property Settings', 'fp-dms'),
                        'data-validate' => 'data-validate="ga4-property"',
                    ]
                ); ?>
            </div>

            <?php echo $this->renderHelpPanel(
                __('📍 Where to find your Property ID', 'fp-dms'),
                $this->getPropertyIdHelp(),
                [
                    [
                        'label' => __('Open Google Analytics', 'fp-dms'),
                        'url' => 'https://analytics.google.com/',
                    ],
                ]
            ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $propertyId = $data['config']['property_id'] ?? '';

        if (empty($propertyId)) {
            return [
                'valid' => false,
                'errors' => [
                    'property_id' => __('Property ID is required', 'fp-dms'),
                ],
            ];
        }

        if (!ctype_digit($propertyId)) {
            return [
                'valid' => false,
                'errors' => [
                    'property_id' => __('Property ID must be numeric', 'fp-dms'),
                ],
            ];
        }

        return ['valid' => true];
    }

    public function getHelp(): array
    {
        return [
            'title' => __('About GA4 Properties', 'fp-dms'),
            'content' => __(
                'A GA4 Property represents a website or app in Google Analytics. You can find the Property ID in your GA4 admin settings.',
                'fp-dms'
            ),
            'links' => [
                [
                    'label' => __('How to find your Property ID', 'fp-dms'),
                    'url' => 'https://support.google.com/analytics/answer/9539598',
                ],
            ],
        ];
    }

    private function getPropertyIdHelp(): string
    {
        return '
            <ol>
                <li>' . __('Open <strong>Google Analytics</strong>', 'fp-dms') . '</li>
                <li>' . __('Click <strong>Admin</strong> (gear icon in bottom left)', 'fp-dms') . '</li>
                <li>' . __('Select your <strong>Property</strong> from the dropdown', 'fp-dms') . '</li>
                <li>' . __('Click <strong>Property Settings</strong>', 'fp-dms') . '</li>
                <li>' . __('Find the <strong>Property ID</strong> at the top (it\'s a number like 123456789)', 'fp-dms') . '</li>
            </ol>
            <p><strong>' . __('Important:', 'fp-dms') . '</strong> ' .
            __('Make sure to add the service account email as a user in GA4 with "Viewer" permissions.', 'fp-dms') .
            '</p>
        ';
    }
}

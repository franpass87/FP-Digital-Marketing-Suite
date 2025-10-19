<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * Google Ads Customer ID selection step.
 */
class GoogleAdsCustomerStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Google Ads Customer ID', 'fp-dms'),
            __('Enter your Google Ads customer ID', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $serviceAccount = $data['auth']['service_account'] ?? '';
        $selectedCustomerId = $data['config']['customer_id'] ?? '';

        ob_start();
        ?>
        <div class="fpdms-google-ads-customer-step">
            <?php if (!empty($serviceAccount)) : ?>
                <div class="fpdms-autodiscovery-section">
                    <button type="button" class="button button-secondary fpdms-btn-discover" data-provider="google_ads">
                        🔍 <?php _e('Auto-discover my accounts', 'fp-dms'); ?>
                    </button>
                    <p class="description">
                        <?php _e('We\'ll automatically find all Google Ads accounts accessible by your service account', 'fp-dms'); ?>
                    </p>
                </div>

                <div class="fpdms-resource-list" id="fpdms-discovered-accounts" style="display: none;">
                    <!-- Accounts will be loaded here via AJAX -->
                </div>

                <div class="fpdms-or-divider">
                    <span><?php _e('or enter manually', 'fp-dms'); ?></span>
                </div>
            <?php endif; ?>

            <div class="fpdms-manual-entry">
                <?php echo $this->renderTextField(
                    'config[customer_id]',
                    __('Customer ID', 'fp-dms'),
                    $selectedCustomerId,
                    [
                        'required' => true,
                        'placeholder' => '123-456-7890',
                        'description' => __('Format: 000-000-0000 (without "CID-" prefix)', 'fp-dms'),
                        'data-validate' => 'data-validate="google-ads-customer"',
                    ]
                ); ?>

                <div class="fpdms-format-help">
                    <p><strong><?php _e('Format examples:', 'fp-dms'); ?></strong></p>
                    <ul>
                        <li>✅ <code>123-456-7890</code></li>
                        <li>❌ <code>CID-123-456-7890</code> (remove "CID-")</li>
                        <li>❌ <code>1234567890</code> (add hyphens)</li>
                    </ul>
                    <p class="description">
                        <?php _e('Don\'t worry, we\'ll auto-format it for you!', 'fp-dms'); ?>
                    </p>
                </div>
            </div>

            <?php echo $this->renderHelpPanel(
                __('📍 Where to find your Customer ID', 'fp-dms'),
                $this->getCustomerIdHelp(),
                [
                    [
                        'label' => __('Open Google Ads', 'fp-dms'),
                        'url' => 'https://ads.google.com/',
                    ],
                ]
            ); ?>

            <div class="fpdms-important-note">
                <h4>⚠️ <?php _e('Important: Add Service Account Access', 'fp-dms'); ?></h4>
                <p><?php _e('After entering your Customer ID, you must add the service account email to your Google Ads account:', 'fp-dms'); ?></p>
                <ol>
                    <li><?php _e('Go to Google Ads → Admin → Access and Security', 'fp-dms'); ?></li>
                    <li><?php _e('Click the plus button (+)', 'fp-dms'); ?></li>
                    <li><?php _e('Add this email with "Standard" access:', 'fp-dms'); ?>
                        <div class="fpdms-sa-email-display">
                            <code class="fpdms-sa-email"></code>
                            <button type="button" class="button button-small fpdms-copy-email">
                                📋 <?php _e('Copy', 'fp-dms'); ?>
                            </button>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $customerId = $data['config']['customer_id'] ?? '';

        if (empty($customerId)) {
            return [
                'valid' => false,
                'errors' => [
                    'customer_id' => __('Customer ID is required', 'fp-dms'),
                ],
            ];
        }

        // Remove any non-numeric characters for validation
        $cleaned = preg_replace('/[^0-9]/', '', $customerId);

        if (strlen($cleaned) !== 10) {
            return [
                'valid' => false,
                'errors' => [
                    'customer_id' => __('Customer ID must be 10 digits', 'fp-dms'),
                ],
            ];
        }

        // Validate format (should be XXX-XXX-XXXX)
        if (!preg_match('/^\d{3}-\d{3}-\d{4}$/', $customerId)) {
            // Auto-format
            $formatted = substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6);

            return [
                'valid' => true,
                'formatted' => $formatted,
                'message' => __('Auto-formatted to correct format', 'fp-dms'),
            ];
        }

        return ['valid' => true];
    }

    public function process(array $data): array
    {
        // Auto-format customer ID if needed
        $customerId = $data['config']['customer_id'] ?? '';
        $cleaned = preg_replace('/[^0-9]/', '', $customerId);

        if (strlen($cleaned) === 10) {
            $data['config']['customer_id'] = substr($cleaned, 0, 3) . '-' .
                                              substr($cleaned, 3, 3) . '-' .
                                              substr($cleaned, 6);
        }

        return $data;
    }

    public function getHelp(): array
    {
        return [
            'title' => __('About Google Ads Customer ID', 'fp-dms'),
            'content' => __(
                'The Customer ID is a unique 10-digit number that identifies your Google Ads account. You can find it in the top-right corner of your Google Ads interface.',
                'fp-dms'
            ),
            'links' => [
                [
                    'label' => __('How to find your Customer ID', 'fp-dms'),
                    'url' => 'https://support.google.com/google-ads/answer/1704344',
                ],
            ],
        ];
    }

    private function getCustomerIdHelp(): string
    {
        return '
            <ol>
                <li>' . __('Log in to <strong>Google Ads</strong>', 'fp-dms') . '</li>
                <li>' . __('Look at the <strong>top-right corner</strong> of the page', 'fp-dms') . '</li>
                <li>' . __('You\'ll see a number like "123-456-7890" next to your account name', 'fp-dms') . '</li>
                <li>' . __('Copy that number (without "CID-" prefix if present)', 'fp-dms') . '</li>
            </ol>
            <p><strong>' . __('Tip:', 'fp-dms') . '</strong> ' .
            __('If you manage multiple accounts, make sure you select the correct one from the account selector.', 'fp-dms') .
            '</p>
        ';
    }
}

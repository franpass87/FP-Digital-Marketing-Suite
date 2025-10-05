<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * GSC Site selection step with auto-discovery.
 */
class GSCSiteStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;
        
        parent::__construct(
            $id,
            __('Select Search Console Site', 'fp-dms'),
            __('Choose which site to connect', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $serviceAccount = $data['auth']['service_account'] ?? '';
        $selectedSiteUrl = $data['config']['site_url'] ?? '';
        
        ob_start();
        ?>
        <div class="fpdms-gsc-site-step">
            <?php if (!empty($serviceAccount)): ?>
                <div class="fpdms-autodiscovery-section">
                    <button type="button" class="button button-secondary fpdms-btn-discover" data-provider="gsc">
                        🔍 <?php _e('Auto-discover my sites', 'fp-dms'); ?>
                    </button>
                    <p class="description">
                        <?php _e('We\'ll automatically find all verified sites accessible by your service account', 'fp-dms'); ?>
                    </p>
                </div>

                <div class="fpdms-resource-list" id="fpdms-discovered-sites" style="display: none;">
                    <!-- Sites will be loaded here via AJAX -->
                </div>

                <div class="fpdms-or-divider">
                    <span><?php _e('or enter manually', 'fp-dms'); ?></span>
                </div>
            <?php endif; ?>

            <div class="fpdms-manual-entry">
                <?php echo $this->renderTextField(
                    'site_url',
                    __('Site URL', 'fp-dms'),
                    $selectedSiteUrl,
                    [
                        'required' => true,
                        'placeholder' => 'https://www.example.com',
                        'description' => __('The exact URL as shown in Search Console (with https:// or sc-domain:)', 'fp-dms'),
                        'data-validate' => 'data-validate="gsc-site"',
                    ]
                ); ?>

                <div class="fpdms-site-format-help">
                    <p><strong><?php _e('Accepted formats:', 'fp-dms'); ?></strong></p>
                    <ul>
                        <li><code>https://www.example.com</code> - <?php _e('URL prefix property', 'fp-dms'); ?></li>
                        <li><code>http://example.com</code> - <?php _e('URL prefix property', 'fp-dms'); ?></li>
                        <li><code>sc-domain:example.com</code> - <?php _e('Domain property', 'fp-dms'); ?></li>
                    </ul>
                </div>
            </div>

            <?php echo $this->renderHelpPanel(
                __('📍 How to find your site URL', 'fp-dms'),
                $this->getSiteUrlHelp(),
                [
                    [
                        'label' => __('Open Search Console', 'fp-dms'),
                        'url' => 'https://search.google.com/search-console',
                    ],
                ]
            ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $siteUrl = $data['config']['site_url'] ?? '';
        
        if (empty($siteUrl)) {
            return [
                'valid' => false,
                'errors' => [
                    'site_url' => __('Site URL is required', 'fp-dms'),
                ],
            ];
        }

        // Validate format
        if (!str_starts_with($siteUrl, 'http') && !str_starts_with($siteUrl, 'sc-domain:')) {
            return [
                'valid' => false,
                'errors' => [
                    'site_url' => __('Site URL must start with http://, https://, or sc-domain:', 'fp-dms'),
                ],
            ];
        }

        return ['valid' => true];
    }

    public function getHelp(): array
    {
        return [
            'title' => __('About Search Console Sites', 'fp-dms'),
            'content' => __(
                'A site in Search Console represents a verified website. You must add the service account as a user before connecting.',
                'fp-dms'
            ),
            'links' => [
                [
                    'label' => __('Verify your site', 'fp-dms'),
                    'url' => 'https://support.google.com/webmasters/answer/9008080',
                ],
            ],
        ];
    }

    private function getSiteUrlHelp(): string
    {
        return '
            <ol>
                <li>' . __('Open <strong>Google Search Console</strong>', 'fp-dms') . '</li>
                <li>' . __('Look at the property selector in the top left', 'fp-dms') . '</li>
                <li>' . __('Copy the exact URL shown (including https:// or sc-domain: prefix)', 'fp-dms') . '</li>
            </ol>
            <p><strong>' . __('Important:', 'fp-dms') . '</strong> ' . 
            __('Add the service account email as a user in Search Console with "Full" or "Restricted" permissions.', 'fp-dms') . 
            '</p>
        ';
    }
}

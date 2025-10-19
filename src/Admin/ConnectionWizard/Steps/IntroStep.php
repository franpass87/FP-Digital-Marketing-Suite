<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * Introduction step - explains what the wizard will do.
 */
class IntroStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            $this->getProviderTitle(),
            __('Let\'s connect your data source in a few simple steps', 'fp-dms')
        );

        $this->skippable = true;
    }

    public function render(array $data): string
    {
        $providerLabel = $this->getProviderLabel();

        ob_start();
        ?>
        <div class="fpdms-intro-step">
            <div class="fpdms-intro-icon">
                <?php echo $this->getProviderIcon(); ?>
            </div>
            
            <div class="fpdms-intro-content">
                <h3><?php printf(__('Connect %s to your marketing suite', 'fp-dms'), $providerLabel); ?></h3>
                
                <p><?php echo $this->getIntroText(); ?></p>
                
                <div class="fpdms-intro-steps">
                    <h4><?php _e('What you\'ll need:', 'fp-dms'); ?></h4>
                    <ol>
                        <?php foreach ($this->getRequirements() as $requirement) : ?>
                            <li><?php echo esc_html($requirement); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                
                <div class="fpdms-intro-time">
                    <span class="dashicons dashicons-clock"></span>
                    <?php printf(__('Estimated time: %s', 'fp-dms'), $this->getEstimatedTime()); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        // No validation needed for intro step
        return ['valid' => true];
    }

    public function getHelp(): array
    {
        return [
            'title' => __('Getting Started', 'fp-dms'),
            'content' => $this->getHelpContent(),
            'links' => $this->getHelpLinks(),
        ];
    }

    private function getProviderTitle(): string
    {
        return match ($this->provider) {
            'ga4' => __('Setup Google Analytics 4', 'fp-dms'),
            'gsc' => __('Setup Google Search Console', 'fp-dms'),
            'google_ads' => __('Setup Google Ads', 'fp-dms'),
            'meta_ads' => __('Setup Meta Ads', 'fp-dms'),
            'clarity' => __('Setup Microsoft Clarity', 'fp-dms'),
            'csv_generic' => __('Setup CSV Import', 'fp-dms'),
            default => __('Setup Data Source', 'fp-dms'),
        };
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

    private function getProviderIcon(): string
    {
        return match ($this->provider) {
            'ga4' => '📊',
            'gsc' => '🔍',
            'google_ads' => '🎯',
            'meta_ads' => '📱',
            'clarity' => '📈',
            'csv_generic' => '📄',
            default => '🔌',
        };
    }

    private function getIntroText(): string
    {
        return match ($this->provider) {
            'ga4' => __('Connect your Google Analytics 4 property to track user behavior, conversions, and engagement metrics.', 'fp-dms'),
            'gsc' => __('Connect Google Search Console to monitor your search performance, keywords, and organic traffic.', 'fp-dms'),
            'google_ads' => __('Connect Google Ads to track campaign performance, costs, and conversions.', 'fp-dms'),
            'meta_ads' => __('Connect your Meta (Facebook/Instagram) Ads to monitor campaign metrics and ROI.', 'fp-dms'),
            'clarity' => __('Connect Microsoft Clarity to analyze user behavior with heatmaps and session recordings.', 'fp-dms'),
            'csv_generic' => __('Import data from CSV files to consolidate metrics from any source.', 'fp-dms'),
            default => __('Follow the wizard to connect your data source.', 'fp-dms'),
        };
    }

    private function getRequirements(): array
    {
        return match ($this->provider) {
            'ga4', 'gsc' => [
                __('A Google Cloud service account with API access', 'fp-dms'),
                __('Your property/customer ID', 'fp-dms'),
                __('Viewer permissions on the resource', 'fp-dms'),
            ],
            'google_ads' => [
                __('Google Ads API access (Developer Token)', 'fp-dms'),
                __('OAuth 2.0 client credentials', 'fp-dms'),
                __('Your Google Ads Customer ID', 'fp-dms'),
                __('Refresh token from OAuth flow', 'fp-dms'),
            ],
            'meta_ads' => [
                __('A Meta Business account', 'fp-dms'),
                __('Access token with ads_read permission', 'fp-dms'),
                __('Your Ad Account ID', 'fp-dms'),
            ],
            'clarity' => [
                __('Microsoft Clarity account', 'fp-dms'),
                __('API key', 'fp-dms'),
                __('Project ID', 'fp-dms'),
            ],
            'csv_generic' => [
                __('CSV files with your data', 'fp-dms'),
                __('Column mappings configuration', 'fp-dms'),
            ],
            default => [__('Required credentials', 'fp-dms')],
        };
    }

    private function getEstimatedTime(): string
    {
        return match ($this->provider) {
            'ga4', 'gsc', 'google_ads' => __('3-5 minutes', 'fp-dms'),
            'meta_ads' => __('2-3 minutes', 'fp-dms'),
            'clarity' => __('2 minutes', 'fp-dms'),
            'csv_generic' => __('1 minute', 'fp-dms'),
            default => __('5 minutes', 'fp-dms'),
        };
    }

    private function getHelpContent(): string
    {
        return match ($this->provider) {
            'ga4' => __('This wizard will guide you through connecting your GA4 property. You\'ll need to create a service account in Google Cloud Console if you don\'t have one yet.', 'fp-dms'),
            'gsc' => __('To connect Search Console, you\'ll need a service account with access to your verified site. The wizard will help you set this up step by step.', 'fp-dms'),
            'google_ads' => __('To connect Google Ads, you\'ll need to set up OAuth 2.0 credentials in Google Cloud Console and obtain a developer token from Google Ads API Center.', 'fp-dms'),
            default => __('Follow the wizard steps to complete the setup.', 'fp-dms'),
        };
    }

    private function getHelpLinks(): array
    {
        return match ($this->provider) {
            'ga4' => [
                [
                    'label' => __('GA4 Setup Guide', 'fp-dms'),
                    'url' => 'https://support.google.com/analytics/answer/9305587',
                ],
            ],
            'gsc' => [
                [
                    'label' => __('GSC Setup Guide', 'fp-dms'),
                    'url' => 'https://support.google.com/webmasters/answer/7687615',
                ],
            ],
            'google_ads' => [
                [
                    'label' => __('Google Ads API Setup', 'fp-dms'),
                    'url' => 'https://developers.google.com/google-ads/api/docs/start',
                ],
                [
                    'label' => __('OAuth 2.0 Setup Guide', 'fp-dms'),
                    'url' => 'https://developers.google.com/google-ads/api/docs/oauth/overview',
                ],
            ],
            default => [],
        };
    }
}

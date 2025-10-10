<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * Final step - shows success and next steps.
 */
class FinishStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Setup Complete!', 'fp-dms'),
            __('Your data source is now connected', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $providerLabel = $this->getProviderLabel();

        ob_start();
        ?>
        <div class="fpdms-finish-step">
            <div class="fpdms-success-icon">
                🎉
            </div>

            <div class="fpdms-success-message">
                <h3><?php printf(__('Successfully connected %s!', 'fp-dms'), $providerLabel); ?></h3>
                <p><?php _e('Your data source is now configured and ready to use.', 'fp-dms'); ?></p>
            </div>

            <div class="fpdms-next-steps">
                <h4><?php _e('What happens next?', 'fp-dms'); ?></h4>
                <ul>
                    <li>✓ <?php _e('Data will be synced automatically based on your schedule', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('You can view the data in your reports', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('Configure alerts and notifications in settings', 'fp-dms'); ?></li>
                </ul>
            </div>

            <div class="fpdms-quick-actions">
                <h4><?php _e('Quick Actions', 'fp-dms'); ?></h4>
                <div class="fpdms-action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=fpdms-data-sources'); ?>" class="button">
                        📊 <?php _e('View All Data Sources', 'fp-dms'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=fpdms-schedules'); ?>" class="button">
                        ⏰ <?php _e('Configure Schedules', 'fp-dms'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=fpdms-reports'); ?>" class="button">
                        📈 <?php _e('View Reports', 'fp-dms'); ?>
                    </a>
                </div>
            </div>

            <?php if ($this->hasTemplate($data)) : ?>
                <div class="fpdms-template-applied">
                    <h4><?php _e('Template Applied', 'fp-dms'); ?></h4>
                    <p>
                        <?php printf(
                            __('We\'ve configured %d metrics based on the "%s" template.', 'fp-dms'),
                            count($data['config']['metrics'] ?? []),
                            $this->getTemplateName($data)
                        ); ?>
                    </p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=fpdms-data-sources&action=edit&id=' . ($data['id'] ?? '')); ?>">
                            <?php _e('Customize metrics →', 'fp-dms'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="fpdms-help-section">
                <h4><?php _e('Need Help?', 'fp-dms'); ?></h4>
                <p>
                    <?php _e('Check out our documentation or contact support if you have any questions.', 'fp-dms'); ?>
                </p>
                <div class="fpdms-help-links">
                    <a href="<?php echo admin_url('admin.php?page=fpdms-help'); ?>" target="_blank">
                        📚 <?php _e('Documentation', 'fp-dms'); ?>
                    </a>
                    <a href="https://support.example.com" target="_blank">
                        💬 <?php _e('Contact Support', 'fp-dms'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        // No validation needed for finish step
        return ['valid' => true];
    }

    public function getHelp(): array
    {
        return [];
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

    private function hasTemplate(array $data): bool
    {
        return !empty($data['config']['template_used']);
    }

    private function getTemplateName(array $data): string
    {
        $templateId = $data['config']['template_used'] ?? '';

        // Get template name from ConnectionTemplate class
        $template = \FP\DMS\Services\Connectors\ConnectionTemplate::getTemplate($templateId);

        return $template['name'] ?? $templateId;
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard;

/**
 * Main connection wizard orchestrator.
 *
 * Manages the multi-step process for configuring data source connections.
 */
class ConnectionWizard
{
    private string $provider;

    /** @var WizardStep[] */
    private array $steps = [];

    private int $currentStep = 0;

    private array $data = [];

    public function __construct(string $provider)
    {
        $this->provider = $provider;
        $this->loadSteps();
    }

    /**
     * Load steps for the current provider.
     */
    private function loadSteps(): void
    {
        $this->steps = match ($this->provider) {
            'ga4' => $this->getGA4Steps(),
            'gsc' => $this->getGSCSteps(),
            'google_ads' => $this->getGoogleAdsSteps(),
            'meta_ads' => $this->getMetaAdsSteps(),
            'clarity' => $this->getClaritySteps(),
            'csv_generic' => $this->getCSVSteps(),
            default => [],
        };

        // Allow filtering steps
        $this->steps = apply_filters(
            'fpdms_connection_wizard_steps',
            $this->steps,
            $this->provider
        );
    }

    /**
     * Get GA4 wizard steps.
     *
     * @return WizardStep[]
     */
    private function getGA4Steps(): array
    {
        return [
            new Steps\IntroStep('intro', $this->provider),
            new Steps\TemplateSelectionStep('template', $this->provider),
            new Steps\ServiceAccountStep('service_account', $this->provider),
            new Steps\GA4PropertyStep('property', $this->provider),
            new Steps\TestConnectionStep('test', $this->provider),
            new Steps\FinishStep('finish', $this->provider),
        ];
    }

    /**
     * Get GSC wizard steps.
     *
     * @return WizardStep[]
     */
    private function getGSCSteps(): array
    {
        return [
            new Steps\IntroStep('intro', $this->provider),
            new Steps\TemplateSelectionStep('template', $this->provider),
            new Steps\ServiceAccountStep('service_account', $this->provider),
            new Steps\GSCSiteStep('site', $this->provider),
            new Steps\TestConnectionStep('test', $this->provider),
            new Steps\FinishStep('finish', $this->provider),
        ];
    }

    /**
     * Get Google Ads wizard steps.
     *
     * @return WizardStep[]
     */
    private function getGoogleAdsSteps(): array
    {
        return [
            new Steps\IntroStep('intro', $this->provider),
            new Steps\ServiceAccountStep('service_account', $this->provider),
            new Steps\GoogleAdsCustomerStep('customer', $this->provider),
            new Steps\TestConnectionStep('test', $this->provider),
            new Steps\FinishStep('finish', $this->provider),
        ];
    }

    /**
     * Get Meta Ads wizard steps.
     *
     * @return WizardStep[]
     */
    private function getMetaAdsSteps(): array
    {
        return [
            new Steps\IntroStep('intro', $this->provider),
            new Steps\MetaAdsAuthStep('auth', $this->provider),
            new Steps\TestConnectionStep('test', $this->provider),
            new Steps\FinishStep('finish', $this->provider),
        ];
    }

    /**
     * Get Clarity wizard steps.
     *
     * @return WizardStep[]
     */
    private function getClaritySteps(): array
    {
        return [
            new Steps\IntroStep('intro', $this->provider),
            new Steps\ClarityProjectStep('project', $this->provider),
            new Steps\TestConnectionStep('test', $this->provider),
            new Steps\FinishStep('finish', $this->provider),
        ];
    }

    /**
     * Get CSV wizard steps.
     *
     * @return WizardStep[]
     */
    private function getCSVSteps(): array
    {
        return [
            new Steps\IntroStep('intro', $this->provider),
            new Steps\CSVConfigStep('config', $this->provider),
            new Steps\FinishStep('finish', $this->provider),
        ];
    }

    /**
     * Render the wizard UI.
     */
    public function render(): string
    {
        if (empty($this->steps)) {
            return '<div class="notice notice-error"><p>' .
                   __('Invalid provider type', 'fp-dms') .
                   '</p></div>';
        }

        $currentStepObj = $this->steps[$this->currentStep];

        ob_start();
        ?>
        <div class="fpdms-wizard" data-provider="<?php echo esc_attr($this->provider); ?>" data-step="<?php echo esc_attr((string)$this->currentStep); ?>">
            <div class="fpdms-wizard-header">
                <h2 class="fpdms-wizard-title">
                    <?php echo esc_html($currentStepObj->getTitle()); ?>
                </h2>
                <?php if ($currentStepObj->getDescription()) : ?>
                    <p class="fpdms-wizard-description">
                        <?php echo esc_html($currentStepObj->getDescription()); ?>
                    </p>
                <?php endif; ?>
                
                <?php $this->renderProgress(); ?>
            </div>

            <div class="fpdms-wizard-body">
                <?php echo $currentStepObj->render($this->data); ?>
            </div>

            <div class="fpdms-wizard-footer">
                <div class="fpdms-wizard-help">
                    <?php $this->renderStepHelp($currentStepObj); ?>
                </div>
                <div class="fpdms-wizard-nav">
                    <?php $this->renderNavigation(); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render progress indicator.
     */
    private function renderProgress(): void
    {
        $total = count($this->steps);
        ?>
        <div class="fpdms-wizard-progress">
            <div class="fpdms-wizard-progress-text">
                <?php
                printf(
                    __('Step %d of %d', 'fp-dms'),
                    $this->currentStep + 1,
                    $total
                );
                ?>
            </div>
            <div class="fpdms-wizard-progress-bar">
                <?php for ($i = 0; $i < $total; $i++) : ?>
                    <div class="fpdms-wizard-step-indicator <?php
                        echo $i < $this->currentStep ? 'completed' : '';
                        echo $i === $this->currentStep ? 'active' : '';
                    ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render navigation buttons.
     */
    private function renderNavigation(): void
    {
        $isFirst = $this->currentStep === 0;
        $isLast = $this->currentStep === count($this->steps) - 1;
        $currentStepObj = $this->steps[$this->currentStep];

        ?>
        <?php if (!$isFirst) : ?>
            <button type="button" class="button fpdms-wizard-btn-prev">
                ❮ <?php esc_html_e('Back', 'fp-dms'); ?>
            </button>
        <?php endif; ?>

        <?php if ($currentStepObj->isSkippable()) : ?>
            <button type="button" class="button fpdms-wizard-btn-skip">
                <?php esc_html_e('Skip', 'fp-dms'); ?>
            </button>
        <?php endif; ?>

        <?php if (!$isLast) : ?>
            <button type="button" class="button button-primary fpdms-wizard-btn-next">
                <?php esc_html_e('Continue', 'fp-dms'); ?> ❯
            </button>
        <?php else : ?>
            <button type="button" class="button button-primary fpdms-wizard-btn-finish">
                <?php esc_html_e('Finish Setup', 'fp-dms'); ?> ✓
            </button>
        <?php endif; ?>
        <?php
    }

    /**
     * Render help content for current step.
     */
    private function renderStepHelp(WizardStep $step): void
    {
        $help = $step->getHelp();

        if (empty($help)) {
            return;
        }

        if (isset($help['content'])) {
            echo '<button type="button" class="button fpdms-btn-help" data-step="' .
                 esc_attr($step->getId()) . '">';
            echo '❓ ' . esc_html__('Need help?', 'fp-dms');
            echo '</button>';
        }
    }

    /**
     * Set current step.
     */
    public function setCurrentStep(int $step): void
    {
        if ($step >= 0 && $step < count($this->steps)) {
            $this->currentStep = $step;
        }
    }

    /**
     * Set wizard data.
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Get current step object.
     */
    public function getCurrentStep(): ?WizardStep
    {
        return $this->steps[$this->currentStep] ?? null;
    }

    /**
     * Get all steps.
     *
     * @return WizardStep[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get provider type.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }
}

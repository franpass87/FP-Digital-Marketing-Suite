<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * Microsoft Clarity project configuration step.
 */
class ClarityProjectStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Microsoft Clarity Configuration', 'fp-dms'),
            __('Configure your Microsoft Clarity project', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $apiKey = $data['auth']['api_key'] ?? '';
        $projectId = $data['config']['project_id'] ?? '';

        ob_start();
        ?>
        <div class="fpdms-clarity-project-step">
            <?php echo $this->renderHelpPanel(
                __('🔑 Getting Your Clarity API Credentials', 'fp-dms'),
                $this->getCredentialsHelp(),
                [
                    [
                        'label' => __('Open Microsoft Clarity', 'fp-dms'),
                        'url' => 'https://clarity.microsoft.com/',
                    ],
                    [
                        'label' => __('Clarity API Documentation', 'fp-dms'),
                        'url' => 'https://docs.microsoft.com/en-us/clarity/',
                    ],
                ]
            ); ?>

            <div class="fpdms-field-group">
                <h3><?php _e('Step 1: API Key', 'fp-dms'); ?></h3>
                
                <?php echo $this->renderTextField(
                    'auth[api_key]',
                    __('API Key', 'fp-dms'),
                    $apiKey,
                    [
                        'required' => true,
                        'placeholder' => 'your-api-key-here',
                        'description' => __('Your Microsoft Clarity API key', 'fp-dms'),
                    ]
                ); ?>
            </div>

            <div class="fpdms-field-group">
                <h3><?php _e('Step 2: Project ID', 'fp-dms'); ?></h3>

                <?php echo $this->renderTextField(
                    'config[project_id]',
                    __('Project ID', 'fp-dms'),
                    $projectId,
                    [
                        'required' => true,
                        'placeholder' => 'abc123def456',
                        'description' => __('Your Clarity project identifier', 'fp-dms'),
                    ]
                ); ?>

                <div class="fpdms-format-help">
                    <p><strong><?php _e('Where to find:', 'fp-dms'); ?></strong></p>
                    <ul>
                        <li><?php _e('Go to your Clarity dashboard', 'fp-dms'); ?></li>
                        <li><?php _e('Select your project', 'fp-dms'); ?></li>
                        <li><?php _e('Look at the URL: clarity.microsoft.com/projects/view/<strong>[PROJECT_ID]</strong>/...', 'fp-dms'); ?></li>
                        <li><?php _e('Or check Settings → Project Details', 'fp-dms'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="fpdms-clarity-features">
                <h4><?php _e('📊 What you\'ll get:', 'fp-dms'); ?></h4>
                <ul>
                    <li>✓ <?php _e('Session recordings', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('Heatmaps', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('User behavior metrics', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('Click tracking', 'fp-dms'); ?></li>
                    <li>✓ <?php _e('Scroll depth', 'fp-dms'); ?></li>
                </ul>
            </div>

            <div class="fpdms-important-note">
                <h4>📝 <?php _e('Note', 'fp-dms'); ?></h4>
                <p>
                    <?php _e('Microsoft Clarity is free and has no traffic limits. Make sure the Clarity tracking code is installed on your website.', 'fp-dms'); ?>
                </p>
                <p>
                    <a href="https://clarity.microsoft.com/projects" target="_blank">
                        <?php _e('Check if tracking code is installed →', 'fp-dms'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $errors = [];

        // Validate API key
        $apiKey = $data['auth']['api_key'] ?? '';
        if (empty($apiKey)) {
            $errors['api_key'] = __('API key is required', 'fp-dms');
        } elseif (strlen($apiKey) < 10) {
            $errors['api_key'] = __('API key seems too short', 'fp-dms');
        }

        // Validate project ID
        $projectId = $data['config']['project_id'] ?? '';
        if (empty($projectId)) {
            $errors['project_id'] = __('Project ID is required', 'fp-dms');
        } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $projectId)) {
            $errors['project_id'] = __('Project ID should contain only letters and numbers', 'fp-dms');
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true];
    }

    public function getHelp(): array
    {
        return [
            'title' => __('About Microsoft Clarity', 'fp-dms'),
            'content' => __(
                'Microsoft Clarity is a free user behavior analytics tool that helps you understand how users interact with your website through session recordings and heatmaps.',
                'fp-dms'
            ),
            'links' => [
                [
                    'label' => __('Clarity Dashboard', 'fp-dms'),
                    'url' => 'https://clarity.microsoft.com/',
                ],
                [
                    'label' => __('Getting Started Guide', 'fp-dms'),
                    'url' => 'https://docs.microsoft.com/en-us/clarity/',
                ],
            ],
        ];
    }

    private function getCredentialsHelp(): string
    {
        return '
            <h4>' . __('Getting Your API Key:', 'fp-dms') . '</h4>
            <ol>
                <li>' . __('Go to <a href="https://clarity.microsoft.com/" target="_blank">Microsoft Clarity</a>', 'fp-dms') . '</li>
                <li>' . __('Sign in with your Microsoft account', 'fp-dms') . '</li>
                <li>' . __('Click on your profile icon → <strong>Settings</strong>', 'fp-dms') . '</li>
                <li>' . __('Navigate to <strong>API Keys</strong> section', 'fp-dms') . '</li>
                <li>' . __('Click <strong>Generate New Key</strong>', 'fp-dms') . '</li>
                <li>' . __('Give it a name (e.g., "FP Marketing Suite")', 'fp-dms') . '</li>
                <li>' . __('Copy the API key', 'fp-dms') . '</li>
            </ol>
            
            <h4>' . __('Getting Your Project ID:', 'fp-dms') . '</h4>
            <ol>
                <li>' . __('From your Clarity dashboard, select your project', 'fp-dms') . '</li>
                <li>' . __('Look at the URL in your browser', 'fp-dms') . '</li>
                <li>' . __('The project ID is the alphanumeric code after <code>/projects/view/</code>', 'fp-dms') . '</li>
                <li>' . __('Example: <code>clarity.microsoft.com/projects/view/<strong>abc123def456</strong>/...</code>', 'fp-dms') . '</li>
            </ol>
            
            <p><strong>' . __('Note:', 'fp-dms') . '</strong> ' .
            __('If you don\'t have a Clarity project yet, you can create one for free at clarity.microsoft.com', 'fp-dms') .
            '</p>
        ';
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * Service Account configuration step for Google services.
 */
class ServiceAccountStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Service Account Credentials', 'fp-dms'),
            __('Provide your Google Cloud service account JSON', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $currentValue = $data['auth']['service_account'] ?? '';

        ob_start();
        ?>
        <div class="fpdms-service-account-step">
            <?php echo $this->renderHelpPanel(
                __('📚 How to get a Service Account', 'fp-dms'),
                $this->getSetupInstructions(),
                [
                    [
                        'label' => __('Open Google Cloud Console', 'fp-dms'),
                        'url' => 'https://console.cloud.google.com/apis/credentials',
                    ],
                    [
                        'label' => __('Watch Video Tutorial', 'fp-dms'),
                        'url' => '#', // Link to video
                    ],
                ]
            ); ?>

            <div class="fpdms-field-group">
                <?php echo $this->renderTextareaField(
                    'service_account',
                    __('Service Account JSON', 'fp-dms'),
                    $currentValue,
                    [
                        'required' => true,
                        'rows' => 10,
                        'placeholder' => '{
  "type": "service_account",
  "project_id": "your-project",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...",
  "client_email": "your-sa@project.iam.gserviceaccount.com"
}',
                        'description' => __('Paste the entire JSON file content from Google Cloud Console', 'fp-dms'),
                        'data-validate' => 'data-validate="service-account"',
                    ]
                ); ?>

                <div class="fpdms-or-divider">
                    <span><?php _e('or', 'fp-dms'); ?></span>
                </div>

                <div class="fpdms-file-upload">
                    <label for="fpdms_sa_file" class="button button-secondary">
                        📎 <?php _e('Upload JSON File', 'fp-dms'); ?>
                    </label>
                    <input type="file" 
                           id="fpdms_sa_file" 
                           accept=".json,application/json" 
                           style="display: none;" />
                    <span class="fpdms-file-name"></span>
                </div>
            </div>

            <div class="fpdms-sa-validation" style="display: none;">
                <div class="fpdms-sa-info-box">
                    <h4><?php _e('✅ Service Account Validated', 'fp-dms'); ?></h4>
                    <p class="fpdms-sa-email"></p>
                    <p class="fpdms-sa-project"></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $json = $data['auth']['service_account'] ?? '';

        if (empty($json)) {
            return [
                'valid' => false,
                'errors' => [
                    'service_account' => __('Service account JSON is required', 'fp-dms'),
                ],
            ];
        }

        // Validate JSON structure
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [
                'valid' => false,
                'errors' => [
                    'service_account' => __('Invalid JSON format', 'fp-dms'),
                ],
            ];
        }

        // Check required fields
        $required = ['type', 'project_id', 'private_key', 'client_email'];
        $missing = array_diff($required, array_keys($decoded));

        if (!empty($missing)) {
            return [
                'valid' => false,
                'errors' => [
                    'service_account' => sprintf(
                        __('Missing required fields: %s', 'fp-dms'),
                        implode(', ', $missing)
                    ),
                ],
            ];
        }

        if ($decoded['type'] !== 'service_account') {
            return [
                'valid' => false,
                'errors' => [
                    'service_account' => __('This is not a service account JSON file', 'fp-dms'),
                ],
            ];
        }

        return ['valid' => true];
    }

    public function getHelp(): array
    {
        return [
            'title' => __('About Service Accounts', 'fp-dms'),
            'content' => __(
                'A service account is a special type of Google account that belongs to your application. It allows secure, automated access to Google APIs without user interaction.',
                'fp-dms'
            ),
            'links' => [
                [
                    'label' => __('Service Account Documentation', 'fp-dms'),
                    'url' => 'https://cloud.google.com/iam/docs/service-accounts',
                ],
            ],
        ];
    }

    private function getSetupInstructions(): string
    {
        return '
            <p>' . __('Follow these steps to create a service account:', 'fp-dms') . '</p>
            <ol>
                <li>' . __('Go to <strong>Google Cloud Console</strong> → IAM & Admin → Service Accounts', 'fp-dms') . '</li>
                <li>' . __('Click <strong>"Create Service Account"</strong>', 'fp-dms') . '</li>
                <li>' . __('Give it a name (e.g., "FP Marketing Suite")', 'fp-dms') . '</li>
                <li>' . __('Skip optional permissions (we\'ll add them to specific resources)', 'fp-dms') . '</li>
                <li>' . __('Click <strong>"Create and Continue"</strong>', 'fp-dms') . '</li>
                <li>' . __('Click <strong>"Keys"</strong> → Add Key → Create New Key', 'fp-dms') . '</li>
                <li>' . __('Select <strong>JSON</strong> format and click Create', 'fp-dms') . '</li>
                <li>' . __('The JSON file will download automatically', 'fp-dms') . '</li>
            </ol>
            <p><strong>' . __('Important:', 'fp-dms') . '</strong> ' .
            __('After creating the service account, you\'ll need to grant it access to your GA4 property or GSC site.', 'fp-dms') .
            '</p>
        ';
    }
}

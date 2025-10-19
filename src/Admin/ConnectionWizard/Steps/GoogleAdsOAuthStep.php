<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * Google Ads OAuth credentials step.
 */
class GoogleAdsOAuthStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Google Ads API Credentials', 'fp-dms'),
            __('Configure your Google Ads API OAuth credentials', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $developerToken = $data['auth']['developer_token'] ?? '';
        $clientId = $data['auth']['client_id'] ?? '';
        $clientSecret = $data['auth']['client_secret'] ?? '';
        $refreshToken = $data['auth']['refresh_token'] ?? '';

        ob_start();
        ?>
        <div class="fpdms-google-ads-oauth-step">
            <?php echo $this->renderHelpPanel(
                __('🔑 Google Ads API Setup', 'fp-dms'),
                $this->getSetupInstructions(),
                [
                    [
                        'label' => __('Google Ads API Center', 'fp-dms'),
                        'url' => 'https://ads.google.com/aw/apicenter',
                    ],
                    [
                        'label' => __('OAuth 2.0 Setup Guide', 'fp-dms'),
                        'url' => 'https://developers.google.com/google-ads/api/docs/oauth/overview',
                    ],
                ]
            ); ?>

            <div class="fpdms-field-group">
                <?php echo $this->renderTextField(
                    'auth[developer_token]',
                    __('Developer Token', 'fp-dms'),
                    $developerToken,
                    [
                        'required' => true,
                        'placeholder' => 'Enter your Google Ads developer token',
                        'description' => __('Get this from Google Ads API Center → Developer Token', 'fp-dms'),
                    ]
                ); ?>

                <?php echo $this->renderTextField(
                    'auth[client_id]',
                    __('OAuth Client ID', 'fp-dms'),
                    $clientId,
                    [
                        'required' => true,
                        'placeholder' => 'your-client-id.apps.googleusercontent.com',
                        'description' => __('From Google Cloud Console → APIs & Services → Credentials', 'fp-dms'),
                    ]
                ); ?>

                <?php echo $this->renderTextField(
                    'auth[client_secret]',
                    __('OAuth Client Secret', 'fp-dms'),
                    $clientSecret,
                    [
                        'required' => true,
                        'type' => 'password',
                        'placeholder' => 'Enter your OAuth client secret',
                        'description' => __('From the same OAuth 2.0 client in Google Cloud Console', 'fp-dms'),
                    ]
                ); ?>

                <?php echo $this->renderTextareaField(
                    'auth[refresh_token]',
                    __('Refresh Token', 'fp-dms'),
                    $refreshToken,
                    [
                        'required' => true,
                        'rows' => 3,
                        'placeholder' => 'Enter your OAuth refresh token',
                        'description' => __('Get this by running the OAuth flow with your client credentials', 'fp-dms'),
                    ]
                ); ?>
            </div>

            <div class="fpdms-important-note">
                <h4>⚠️ <?php _e('Important: OAuth Setup Required', 'fp-dms'); ?></h4>
                <p><?php _e('Before using these credentials, you must:', 'fp-dms'); ?></p>
                <ol>
                    <li><?php _e('Create an OAuth 2.0 client in Google Cloud Console', 'fp-dms'); ?></li>
                    <li><?php _e('Run the OAuth flow to get a refresh token', 'fp-dms'); ?></li>
                    <li><?php _e('Ensure your Google Ads account has API access enabled', 'fp-dms'); ?></li>
                </ol>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $errors = [];
        
        $developerToken = trim($data['auth']['developer_token'] ?? '');
        if (empty($developerToken)) {
            $errors['developer_token'] = __('Developer token is required', 'fp-dms');
        }

        $clientId = trim($data['auth']['client_id'] ?? '');
        if (empty($clientId)) {
            $errors['client_id'] = __('OAuth client ID is required', 'fp-dms');
        } elseif (!preg_match('/^[a-zA-Z0-9-]+\.apps\.googleusercontent\.com$/', $clientId)) {
            $errors['client_id'] = __('Invalid OAuth client ID format', 'fp-dms');
        }

        $clientSecret = trim($data['auth']['client_secret'] ?? '');
        if (empty($clientSecret)) {
            $errors['client_secret'] = __('OAuth client secret is required', 'fp-dms');
        }

        $refreshToken = trim($data['auth']['refresh_token'] ?? '');
        if (empty($refreshToken)) {
            $errors['refresh_token'] = __('Refresh token is required', 'fp-dms');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function getHelp(): array
    {
        return [
            'title' => __('Google Ads API Credentials', 'fp-dms'),
            'content' => __(
                'Google Ads API requires OAuth 2.0 credentials for authentication. You need to create an OAuth client in Google Cloud Console and obtain a refresh token through the OAuth flow.',
                'fp-dms'
            ),
            'links' => [
                [
                    'label' => __('Google Ads API Documentation', 'fp-dms'),
                    'url' => 'https://developers.google.com/google-ads/api/docs/start',
                ],
                [
                    'label' => __('OAuth 2.0 Setup Guide', 'fp-dms'),
                    'url' => 'https://developers.google.com/google-ads/api/docs/oauth/overview',
                ],
            ],
        ];
    }

    private function getSetupInstructions(): string
    {
        return '
            <p>' . __('Follow these steps to set up Google Ads API credentials:', 'fp-dms') . '</p>
            <ol>
                <li>' . __('Go to <strong>Google Cloud Console</strong> → APIs & Services → Credentials', 'fp-dms') . '</li>
                <li>' . __('Click <strong>"Create Credentials"</strong> → OAuth 2.0 Client ID', 'fp-dms') . '</li>
                <li>' . __('Select <strong>"Desktop application"</strong> as application type', 'fp-dms') . '</li>
                <li>' . __('Give it a name (e.g., "FP Marketing Suite Google Ads")', 'fp-dms') . '</li>
                <li>' . __('Click <strong>"Create"</strong> and copy the Client ID and Client Secret', 'fp-dms') . '</li>
                <li>' . __('Go to <strong>Google Ads API Center</strong> to get your Developer Token', 'fp-dms') . '</li>
                <li>' . __('Run the OAuth flow to get a refresh token using your client credentials', 'fp-dms') . '</li>
            </ol>
            <p><strong>' . __('Note:', 'fp-dms') . '</strong> ' .
            __('Make sure to enable the Google Ads API in your Google Cloud project and request access in Google Ads API Center.', 'fp-dms') .
            '</p>
        ';
    }
}

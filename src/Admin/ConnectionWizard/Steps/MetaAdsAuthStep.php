<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * Meta Ads authentication and account selection step.
 */
class MetaAdsAuthStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Meta Ads Configuration', 'fp-dms'),
            __('Configure your Meta (Facebook/Instagram) Ads connection', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $accessToken = $data['auth']['access_token'] ?? '';
        $accountId = $data['config']['account_id'] ?? '';

        ob_start();
        ?>
        <div class="fpdms-meta-ads-auth-step">
            <?php echo $this->renderHelpPanel(
                __('🔑 Getting Your Access Token', 'fp-dms'),
                $this->getAccessTokenHelp(),
                [
                    [
                        'label' => __('Open Meta Business Suite', 'fp-dms'),
                        'url' => 'https://business.facebook.com/',
                    ],
                    [
                        'label' => __('Meta for Developers', 'fp-dms'),
                        'url' => 'https://developers.facebook.com/',
                    ],
                ]
            ); ?>

            <div class="fpdms-field-group">
                <h3><?php _e('Step 1: Access Token', 'fp-dms'); ?></h3>
                
                <?php echo $this->renderTextareaField(
                    'auth[access_token]',
                    __('Access Token', 'fp-dms'),
                    $accessToken,
                    [
                        'required' => true,
                        'rows' => 4,
                        'placeholder' => 'EAAG...',
                        'description' => __('Your Meta Business access token with ads_read permission', 'fp-dms'),
                    ]
                ); ?>

                <div class="fpdms-token-info">
                    <p><strong><?php _e('Required permissions:', 'fp-dms'); ?></strong></p>
                    <ul>
                        <li>✅ <code>ads_read</code></li>
                        <li>✅ <code>read_insights</code></li>
                    </ul>
                </div>
            </div>

            <div class="fpdms-field-group">
                <h3><?php _e('Step 2: Ad Account ID', 'fp-dms'); ?></h3>

                <?php if (!empty($accessToken)) : ?>
                    <div class="fpdms-autodiscovery-section">
                        <button type="button" class="button button-secondary fpdms-btn-discover" data-provider="meta_ads">
                            🔍 <?php _e('Auto-discover my ad accounts', 'fp-dms'); ?>
                        </button>
                        <p class="description">
                            <?php _e('We\'ll automatically find all ad accounts accessible with your token', 'fp-dms'); ?>
                        </p>
                    </div>

                    <div class="fpdms-resource-list" id="fpdms-discovered-accounts" style="display: none;">
                        <!-- Accounts will be loaded here via AJAX -->
                    </div>

                    <div class="fpdms-or-divider">
                        <span><?php _e('or enter manually', 'fp-dms'); ?></span>
                    </div>
                <?php endif; ?>

                <?php echo $this->renderTextField(
                    'config[account_id]',
                    __('Ad Account ID', 'fp-dms'),
                    $accountId,
                    [
                        'required' => true,
                        'placeholder' => 'act_1234567890',
                        'description' => __('Must start with "act_" followed by numbers', 'fp-dms'),
                        'data-validate' => 'data-validate="meta-ads-account"',
                    ]
                ); ?>

                <div class="fpdms-format-help">
                    <p><strong><?php _e('Format examples:', 'fp-dms'); ?></strong></p>
                    <ul>
                        <li>✅ <code>act_1234567890</code></li>
                        <li>❌ <code>1234567890</code> (missing "act_" prefix)</li>
                    </ul>
                </div>
            </div>

            <?php echo $this->renderHelpPanel(
                __('📍 Where to find your Ad Account ID', 'fp-dms'),
                $this->getAccountIdHelp(),
                []
            ); ?>

            <div class="fpdms-security-note">
                <h4>🔒 <?php _e('Security Note', 'fp-dms'); ?></h4>
                <p>
                    <?php _e('Your access token will be stored securely and encrypted. Never share your token publicly.', 'fp-dms'); ?>
                </p>
                <p>
                    <strong><?php _e('Best practice:', 'fp-dms'); ?></strong>
                    <?php _e('Create a dedicated System User in Meta Business Manager for this integration.', 'fp-dms'); ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $errors = [];

        // Validate access token
        $accessToken = $data['auth']['access_token'] ?? '';
        if (empty($accessToken)) {
            $errors['access_token'] = __('Access token is required', 'fp-dms');
        } elseif (!str_starts_with($accessToken, 'EAA')) {
            $errors['access_token'] = __('Access token should start with "EAA"', 'fp-dms');
        }

        // Validate account ID
        $accountId = $data['config']['account_id'] ?? '';
        if (empty($accountId)) {
            $errors['account_id'] = __('Account ID is required', 'fp-dms');
        } elseif (!preg_match('/^act_[0-9]+$/', $accountId)) {
            $errors['account_id'] = __('Account ID must be in format: act_1234567890', 'fp-dms');
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true];
    }

    public function process(array $data): array
    {
        // Auto-add "act_" prefix if missing
        $accountId = $data['config']['account_id'] ?? '';
        if (!empty($accountId) && !str_starts_with($accountId, 'act_')) {
            $cleaned = preg_replace('/[^0-9]/', '', $accountId);
            if (!empty($cleaned)) {
                $data['config']['account_id'] = 'act_' . $cleaned;
            }
        }

        return $data;
    }

    public function getHelp(): array
    {
        return [
            'title' => __('About Meta Business Access', 'fp-dms'),
            'content' => __(
                'To connect Meta Ads, you need an access token from Meta Business Manager. We recommend creating a System User with ads_read permission for this integration.',
                'fp-dms'
            ),
            'links' => [
                [
                    'label' => __('Meta Business Settings', 'fp-dms'),
                    'url' => 'https://business.facebook.com/settings',
                ],
                [
                    'label' => __('System Users Guide', 'fp-dms'),
                    'url' => 'https://www.facebook.com/business/help/503306463479099',
                ],
            ],
        ];
    }

    private function getAccessTokenHelp(): string
    {
        return '
            <p>' . __('There are two ways to get an access token:', 'fp-dms') . '</p>
            
            <h4>' . __('Option 1: Graph API Explorer (Quick, for testing)', 'fp-dms') . '</h4>
            <ol>
                <li>' . __('Go to <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a>', 'fp-dms') . '</li>
                <li>' . __('Select your app or create a new one', 'fp-dms') . '</li>
                <li>' . __('Click "Generate Access Token"', 'fp-dms') . '</li>
                <li>' . __('Select permissions: <code>ads_read</code> and <code>read_insights</code>', 'fp-dms') . '</li>
                <li>' . __('Copy the token', 'fp-dms') . '</li>
            </ol>
            
            <h4>' . __('Option 2: System User (Recommended for production)', 'fp-dms') . '</h4>
            <ol>
                <li>' . __('Go to <strong>Meta Business Settings</strong> → Users → System Users', 'fp-dms') . '</li>
                <li>' . __('Click <strong>Add</strong> to create a new System User', 'fp-dms') . '</li>
                <li>' . __('Give it Admin or Employee role', 'fp-dms') . '</li>
                <li>' . __('Click <strong>Generate New Token</strong>', 'fp-dms') . '</li>
                <li>' . __('Select your ad account and required permissions', 'fp-dms') . '</li>
                <li>' . __('Set token to <strong>never expire</strong> (or set expiration)', 'fp-dms') . '</li>
                <li>' . __('Copy the token', 'fp-dms') . '</li>
            </ol>
        ';
    }

    private function getAccountIdHelp(): string
    {
        return '
            <ol>
                <li>' . __('Go to <strong>Meta Ads Manager</strong>', 'fp-dms') . '</li>
                <li>' . __('Click the account dropdown in the top-left', 'fp-dms') . '</li>
                <li>' . __('Hover over an account name to see its ID', 'fp-dms') . '</li>
                <li>' . __('The ID will be in format <code>act_1234567890</code>', 'fp-dms') . '</li>
                <li>' . __('Or go to <strong>Business Settings → Accounts → Ad Accounts</strong>', 'fp-dms') . '</li>
                <li>' . __('The ID is shown next to each ad account name', 'fp-dms') . '</li>
            </ol>
        ';
    }
}

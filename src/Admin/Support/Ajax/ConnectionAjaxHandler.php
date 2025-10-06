<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Support\Ajax;

use FP\DMS\Services\Connectors\ConnectorException;
use FP\DMS\Services\Connectors\ErrorTranslator;
use FP\DMS\Services\Connectors\ProviderFactory;
use FP\DMS\Support\Period;
use FP\DMS\Support\Security;

/**
 * Handles AJAX requests for connection testing and validation.
 */
class ConnectionAjaxHandler
{
    /**
     * Register AJAX hooks.
     */
    public static function register(): void
    {
        add_action('wp_ajax_fpdms_test_connection_live', [self::class, 'handleTestConnection']);
        add_action('wp_ajax_fpdms_discover_resources', [self::class, 'handleDiscoverResources']);
        add_action('wp_ajax_fpdms_validate_field', [self::class, 'handleValidateField']);
        add_action('wp_ajax_fpdms_wizard_load_step', [self::class, 'handleLoadWizardStep']);
        add_action('wp_ajax_fpdms_save_connection', [self::class, 'handleSaveConnection']);
    }

    /**
     * Handle live connection test.
     */
    public static function handleTestConnection(): void
    {
        // Verify nonce
        if (!Security::verifyNonce($_POST['nonce'] ?? '', 'fpdms_connection_wizard')) {
            wp_send_json_error([
                'message' => __('Invalid security token', 'fp-dms'),
            ], 403);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'fp-dms'),
            ], 403);
            return;
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $dataJson = wp_unslash($_POST['data'] ?? '{}');
        $data = json_decode($dataJson, true);

        if (!$provider || !is_array($data)) {
            wp_send_json_error([
                'message' => __('Invalid request data', 'fp-dms'),
            ], 400);
            return;
        }

        try {
            // Create provider instance
            $auth = $data['auth'] ?? [];
            $config = $data['config'] ?? [];

            $providerInstance = ProviderFactory::create($provider, $auth, $config);

            if (!$providerInstance) {
                throw new \RuntimeException("Provider not found: {$provider}");
            }

            // Test connection
            $result = $providerInstance->testConnection();

            if ($result->isSuccess()) {
                wp_send_json_success([
                    'title' => '✅ ' . __('Connection Successful', 'fp-dms'),
                    'message' => $result->message,
                    'details' => $result->details,
                ]);
            } else {
                wp_send_json_error([
                    'title' => '❌ ' . __('Connection Failed', 'fp-dms'),
                    'message' => $result->message,
                ]);
            }
        } catch (ConnectorException $e) {
            // Translate to user-friendly error
            $translated = ErrorTranslator::translate($e);
            wp_send_json_error($translated);
        } catch (\Exception $e) {
            wp_send_json_error([
                'title' => '❌ ' . __('Unexpected Error', 'fp-dms'),
                'message' => __('An unexpected error occurred', 'fp-dms'),
                'technical_details' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle auto-discovery of resources (properties, accounts, etc.).
     */
    public static function handleDiscoverResources(): void
    {
        // Verify nonce
        if (!Security::verifyNonce($_POST['nonce'] ?? '', 'fpdms_connection_wizard')) {
            wp_send_json_error([
                'message' => __('Invalid security token', 'fp-dms'),
            ], 403);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'fp-dms'),
            ], 403);
            return;
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $authJson = wp_unslash($_POST['auth'] ?? '{}');
        $auth = json_decode($authJson, true);

        if (!$provider || !is_array($auth)) {
            wp_send_json_error([
                'message' => __('Invalid request data', 'fp-dms'),
            ], 400);
            return;
        }

        try {
            // Use AutoDiscovery class (to be implemented in Phase 2)
            // For now, return mock data
            $resources = self::discoverResourcesForProvider($provider, $auth);

            wp_send_json_success([
                'resources' => $resources,
                'count' => count($resources),
            ]);
        } catch (ConnectorException $e) {
            $translated = ErrorTranslator::translate($e);
            wp_send_json_error($translated);
        } catch (\Exception $e) {
            wp_send_json_error([
                'title' => '❌ ' . __('Discovery Failed', 'fp-dms'),
                'message' => __('Could not discover resources', 'fp-dms'),
                'technical_details' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle field validation.
     */
    public static function handleValidateField(): void
    {
        // Verify nonce
        if (!Security::verifyNonce($_POST['nonce'] ?? '', 'fpdms_connection_wizard')) {
            wp_send_json_error([
                'message' => __('Invalid security token', 'fp-dms'),
            ], 403);
            return;
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = wp_unslash($_POST['value'] ?? '');

        if (!$provider || !$field) {
            wp_send_json_error([
                'message' => __('Invalid request data', 'fp-dms'),
            ], 400);
            return;
        }

        // Server-side validation
        $validation = self::validateField($provider, $field, $value);

        if ($validation['valid']) {
            wp_send_json_success($validation);
        } else {
            wp_send_json_error($validation);
        }
    }

    /**
     * Discover resources for a specific provider.
     * This is a placeholder - full implementation in Phase 2.
     */
    private static function discoverResourcesForProvider(string $provider, array $auth): array
    {
        // This will be implemented with AutoDiscovery class in Phase 2
        // For now, return empty array
        return [];
    }

    /**
     * Validate a specific field server-side.
     */
    private static function validateField(string $provider, string $field, string $value): array
    {
        switch ($provider) {
            case 'ga4':
                if ($field === 'property_id') {
                    return self::validateGA4PropertyId($value);
                }
                break;

            case 'google_ads':
                if ($field === 'customer_id') {
                    return self::validateGoogleAdsCustomerId($value);
                }
                break;

            case 'meta_ads':
                if ($field === 'account_id') {
                    return self::validateMetaAdsAccountId($value);
                }
                break;

            case 'gsc':
                if ($field === 'site_url') {
                    return self::validateGSCSiteUrl($value);
                }
                break;
        }

        return [
            'valid' => true,
            'message' => __('Field validated', 'fp-dms'),
        ];
    }

    /**
     * Validate GA4 Property ID.
     */
    private static function validateGA4PropertyId(string $value): array
    {
        $value = trim($value);

        if (empty($value)) {
            return [
                'valid' => false,
                'error' => __('Property ID is required', 'fp-dms'),
            ];
        }

        if (!ctype_digit($value)) {
            return [
                'valid' => false,
                'error' => __('Property ID must contain only numbers', 'fp-dms'),
                'suggestion' => __('Example: 123456789', 'fp-dms'),
            ];
        }

        if (strlen($value) < 6 || strlen($value) > 15) {
            return [
                'valid' => false,
                'error' => __('Property ID length seems incorrect', 'fp-dms'),
            ];
        }

        return [
            'valid' => true,
            'message' => __('Valid Property ID format', 'fp-dms'),
        ];
    }

    /**
     * Validate Google Ads Customer ID.
     */
    private static function validateGoogleAdsCustomerId(string $value): array
    {
        $value = trim($value);

        if (empty($value)) {
            return [
                'valid' => false,
                'error' => __('Customer ID is required', 'fp-dms'),
            ];
        }

        // Auto-format
        $formatted = preg_replace('/[^0-9]/', '', $value);
        if (strlen($formatted) === 10) {
            $formatted = substr($formatted, 0, 3) . '-' . substr($formatted, 3, 3) . '-' . substr($formatted, 6);
        }

        if (!preg_match('/^\d{3}-\d{3}-\d{4}$/', $formatted)) {
            return [
                'valid' => false,
                'error' => __('Invalid Customer ID format', 'fp-dms'),
                'suggestion' => __('Use format: 123-456-7890', 'fp-dms'),
            ];
        }

        return [
            'valid' => true,
            'formatted' => $formatted,
            'message' => __('Valid Customer ID format', 'fp-dms'),
        ];
    }

    /**
     * Validate Meta Ads Account ID.
     */
    private static function validateMetaAdsAccountId(string $value): array
    {
        $value = trim($value);

        if (empty($value)) {
            return [
                'valid' => false,
                'error' => __('Account ID is required', 'fp-dms'),
            ];
        }

        if (!preg_match('/^act_[0-9]+$/', $value)) {
            return [
                'valid' => false,
                'error' => __('Account ID must start with "act_"', 'fp-dms'),
                'suggestion' => __('Example: act_1234567890', 'fp-dms'),
            ];
        }

        return [
            'valid' => true,
            'message' => __('Valid Account ID format', 'fp-dms'),
        ];
    }

    /**
     * Validate GSC Site URL.
     */
    private static function validateGSCSiteUrl(string $value): array
    {
        $value = trim($value);

        if (empty($value)) {
            return [
                'valid' => false,
                'error' => __('Site URL is required', 'fp-dms'),
            ];
        }

        // Allow sc-domain: prefix
        if (strpos($value, 'sc-domain:') === 0) {
            return [
                'valid' => true,
                'message' => __('Valid domain property format', 'fp-dms'),
            ];
        }

        // Validate URL
        $urlToTest = strpos($value, 'http') === 0 ? $value : 'https://' . $value;
        if (!filter_var($urlToTest, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'error' => __('Invalid URL format', 'fp-dms'),
                'suggestion' => __('Example: https://www.example.com', 'fp-dms'),
            ];
        }

        return [
            'valid' => true,
            'message' => __('Valid URL format', 'fp-dms'),
        ];
    }

    /**
     * Handle loading a specific wizard step.
     */
    public static function handleLoadWizardStep(): void
    {
        // Verify nonce
        if (!Security::verifyNonce($_POST['nonce'] ?? '', 'fpdms_connection_wizard')) {
            wp_send_json_error([
                'message' => __('Invalid security token', 'fp-dms'),
            ], 403);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'fp-dms'),
            ], 403);
            return;
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        $dataJson = wp_unslash($_POST['data'] ?? '{}');
        $data = json_decode($dataJson, true);

        if (!$provider || !is_array($data)) {
            wp_send_json_error([
                'message' => __('Invalid request data', 'fp-dms'),
            ], 400);
            return;
        }

        try {
            $wizard = new \FP\DMS\Admin\ConnectionWizard\ConnectionWizard($provider);
            $wizard->setCurrentStep($step);
            $wizard->setData($data);

            $html = $wizard->render();

            wp_send_json_success([
                'html' => $html,
                'step' => $step,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to load wizard step', 'fp-dms'),
                'technical_details' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle saving a connection from the wizard.
     */
    public static function handleSaveConnection(): void
    {
        // Verify nonce
        if (!Security::verifyNonce($_POST['nonce'] ?? '', 'fpdms_connection_wizard')) {
            wp_send_json_error([
                'message' => __('Invalid security token', 'fp-dms'),
            ], 403);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'fp-dms'),
            ], 403);
            return;
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $dataJson = wp_unslash($_POST['data'] ?? '{}');
        $data = json_decode($dataJson, true);

        if (!$provider || !is_array($data)) {
            wp_send_json_error([
                'message' => __('Invalid request data', 'fp-dms'),
            ], 400);
            return;
        }

        try {
            // Extract client ID from data
            $clientId = isset($data['client_id']) ? intval($data['client_id']) : 0;
            
            if ($clientId <= 0) {
                throw new \RuntimeException(__('Client ID is required', 'fp-dms'));
            }

            // Prepare the payload for saving
            $repo = new \FP\DMS\Domain\Repos\DataSourcesRepo();
            
            $payload = [
                'type' => $provider,
                'client_id' => $clientId,
                'auth' => $data['auth'] ?? [],
                'config' => $data['config'] ?? [],
                'active' => true,
            ];

            $created = $repo->create($payload);

            if ($created === null) {
                throw new \RuntimeException(__('Failed to save connection', 'fp-dms'));
            }

            wp_send_json_success([
                'message' => __('Connection saved successfully', 'fp-dms'),
                'data_source_id' => $created->id,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to save connection', 'fp-dms'),
                'technical_details' => $e->getMessage(),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace FP\DMS;

use FP\DMS\Admin\Support\Ajax\ConnectionAjaxHandler;

/**
 * Main plugin integration for connection improvements.
 *
 * This class integrates the connection wizard and validation features
 * into the existing plugin.
 */
class ConnectionWizardIntegration
{
    /**
     * Initialize the integration.
     */
    public static function init(): void
    {
        // Register AJAX handlers
        ConnectionAjaxHandler::register();

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);

        // Add module type to scripts
        add_filter('script_loader_tag', [self::class, 'addModuleType'], 10, 3);

        // Add wizard page
        add_action('admin_menu', [self::class, 'addWizardPage'], 20);
    }

    /**
     * Enqueue scripts and styles for connection wizard.
     */
    public static function enqueueAssets(string $hook): void
    {
        // Debug logging solo se necessario
        if (defined('WP_DEBUG') && WP_DEBUG && strpos($hook, 'fpdms') !== false) {
            error_log('FPDMS Hook: ' . $hook);
        }

        // Only load on data sources and wizard pages
        $allowed_hooks = [
            'toplevel_page_fp-dms-dashboard',
            'fp-suite_page_fp-dms-datasources',
            'admin_page_fpdms-connection-wizard'
        ];

        // Carica anche se la query string contiene page=fpdms-connection-wizard
        $is_wizard_page = isset($_GET['page']) && $_GET['page'] === 'fpdms-connection-wizard';

        if (!in_array($hook, $allowed_hooks, true) && !$is_wizard_page) {
            return;
        }

        $version = defined('FP_DMS_VERSION') ? FP_DMS_VERSION : '1.0.0';
        $pluginDir = defined('FP_DMS_PLUGIN_DIR') ? FP_DMS_PLUGIN_DIR : dirname(__DIR__);

        // Debug script (caricato per primo, senza module)
        wp_enqueue_script(
            'fpdms-wizard-debug',
            plugins_url('assets/js/wizard-debug.js', $pluginDir . '/fp-digital-marketing-suite.php'),
            ['jquery'],
            $version,
            true
        );

        // Enqueue validator
        wp_enqueue_script(
            'fpdms-connection-validator',
            plugins_url('assets/js/connection-validator.js', $pluginDir . '/fp-digital-marketing-suite.php'),
            ['jquery'],
            $version,
            true
        );

        // Enqueue wizard
        wp_enqueue_script(
            'fpdms-connection-wizard',
            plugins_url('assets/js/connection-wizard.js', $pluginDir . '/fp-digital-marketing-suite.php'),
            ['jquery', 'fpdms-connection-validator'],
            $version,
            true
        );

        // Enqueue styles
        wp_enqueue_style(
            'fpdms-main',
            plugins_url('assets/css/main.css', $pluginDir . '/fp-digital-marketing-suite.php'),
            [],
            $version
        );

        wp_enqueue_style(
            'fpdms-connection-validator',
            plugins_url('assets/css/connection-validator.css', $pluginDir . '/fp-digital-marketing-suite.php'),
            ['fpdms-main'],
            $version
        );

        // Localize scripts
        wp_localize_script('fpdms-connection-validator', 'fpdmsI18n', [
            'propertyIdRequired' => __('Property ID is required', 'fp-dms'),
            'propertyIdNumeric' => __('Property ID must contain only numbers', 'fp-dms'),
            'propertyIdExample' => __('Example: 123456789', 'fp-dms'),
            'propertyIdLength' => __('Property ID seems too short or too long', 'fp-dms'),
            'propertyIdCheck' => __('Please verify you copied the correct ID', 'fp-dms'),
            'customerIdRequired' => __('Customer ID is required', 'fp-dms'),
            'customerIdFormat' => __('Invalid Customer ID format', 'fp-dms'),
            'customerIdExample' => __('Use format: 123-456-7890', 'fp-dms'),
            'accountIdRequired' => __('Account ID is required', 'fp-dms'),
            'accountIdFormat' => __('Account ID must start with "act_"', 'fp-dms'),
            'accountIdExample' => __('Example: act_1234567890', 'fp-dms'),
            'accountIdInvalid' => __('Invalid Account ID', 'fp-dms'),
            'siteUrlRequired' => __('Site URL is required', 'fp-dms'),
            'siteUrlInvalid' => __('Invalid URL format', 'fp-dms'),
            'siteUrlExample' => __('Example: https://www.example.com', 'fp-dms'),
            'serviceAccountRequired' => __('Service account JSON is required', 'fp-dms'),
            'serviceAccountMissing' => __('Missing fields in JSON', 'fp-dms'),
            'missingFields' => __('Missing fields', 'fp-dms'),
            'serviceAccountWrongType' => __('This is not a service account JSON', 'fp-dms'),
            'downloadCorrectFile' => __('Download the correct file from Google Cloud Console', 'fp-dms'),
            'invalidJson' => __('Invalid JSON format', 'fp-dms'),
            'copyEntireFile' => __('Copy the entire file content without modifications', 'fp-dms'),
            'validServiceAccount' => __('Valid service account', 'fp-dms'),
            'validatedInfo' => __('Validated', 'fp-dms'),
            'suggestedFormat' => __('Suggested format', 'fp-dms'),
            'apply' => __('Apply', 'fp-dms'),
            'testing' => __('Testing connection...', 'fp-dms'),
            'autoFormatted' => __('Auto-formatted', 'fp-dms'),
            'fieldRequired' => __('This field is required', 'fp-dms'),
            'validationFailed' => __('Please fix the errors above', 'fp-dms'),
            'loading' => __('Loading...', 'fp-dms'),
            'connectionError' => __('Connection Error', 'fp-dms'),
            'unknownError' => __('Unknown error occurred', 'fp-dms'),
            'testingConnection' => __('Testing connection...', 'fp-dms'),
            'connectionSuccess' => __('Connection successful!', 'fp-dms'),
            'connectionFailed' => __('Connection failed', 'fp-dms'),
            'canonicalUrl' => __('Suggested canonical format', 'fp-dms'),
        ]);

        // Get client ID from URL parameters if on wizard page
        $clientId = 0;
        if (isset($_GET['page']) && $_GET['page'] === 'fpdms-connection-wizard') {
            $clientId = isset($_GET['client']) ? intval($_GET['client']) : 0;
        }

        wp_localize_script('fpdms-connection-wizard', 'fpdmsWizard', [
            'nonce' => wp_create_nonce('fpdms_connection_wizard'),
            'redirectUrl' => admin_url('admin.php?page=fpdms-data-sources'),
            'clientId' => $clientId,
        ]);
    }

    /**
     * Add type="module" to scripts that use ES6 imports.
     */
    public static function addModuleType(string $tag, string $handle, string $src): string
    {
        // Scripts that need module type (all scripts using ES6 import/export)
        $moduleScripts = [
            'fpdms-connection-validator',
            'fpdms-connection-wizard',
            'fpdms-overview',
        ];

        if (in_array($handle, $moduleScripts, true)) {
            $tag = str_replace(' src=', ' type="module" src=', $tag);
        }

        return $tag;
    }

    /**
     * Add wizard page to admin menu.
     */
    public static function addWizardPage(): void
    {
        add_submenu_page(
            null, // Parent slug (null = hidden from menu)
            __('Connection Wizard', 'fp-dms'),
            __('Connection Wizard', 'fp-dms'),
            'manage_options',
            'fpdms-connection-wizard',
            [self::class, 'renderWizardPage']
        );
    }

    /**
     * Render the wizard page.
     */
    public static function renderWizardPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fp-dms'));
        }

        // Sanitize inputs to prevent XSS and injection attacks
        $provider = sanitize_key($_GET['provider'] ?? '');
        $clientId = isset($_GET['client']) ? intval($_GET['client']) : 0;

        if (empty($provider)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>';
            echo esc_html__('Invalid provider specified. Please provide a valid provider parameter.', 'fp-dms');
            echo '</p><p><strong>Debug info:</strong> URL completo necessario: <code>?page=fpdms-connection-wizard&provider=ga4&client=1</code></p></div></div>';
            return;
        }

        if ($clientId <= 0) {
            echo '<div class="wrap"><div class="notice notice-error"><p>';
            echo esc_html__('Invalid client specified. Please select a client first.', 'fp-dms');
            echo '</p><p><strong>Debug info:</strong> Client ID ricevuto: <code>' . esc_html($clientId) . '</code></p>';
            echo '<p>URL completo necessario: <code>?page=fpdms-connection-wizard&provider=' . esc_html($provider) . '&client=1</code></p></div></div>';
            return;
        }

        $wizard = new \FP\DMS\Admin\ConnectionWizard\ConnectionWizard($provider);

        // Get current step from query string (sanitized)
        $step = isset($_GET['step']) ? intval($_GET['step']) : 0;
        $wizard->setCurrentStep($step);

        // Get saved data from session or query string (sanitized)
        $data = ['client_id' => $clientId];
        if (isset($_GET['data'])) {
            // Use wp_unslash instead of stripslashes for proper sanitization
            $savedData = json_decode(wp_unslash($_GET['data']), true);
            if (is_array($savedData)) {
                $data = array_merge($data, $savedData);
            }
        }
        $wizard->setData($data);

        ?>
        <div class="wrap">
            <h1><?php
                printf(
                    esc_html__('%s Connection Wizard', 'fp-dms'),
                    esc_html(ucfirst($provider))
                );
                ?></h1>
            <?php echo $wizard->render(); ?>
        </div>
        <?php
    }
}

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

        // Add wizard page
        add_action('admin_menu', [self::class, 'addWizardPage'], 20);
    }

    /**
     * Enqueue scripts and styles for connection wizard.
     */
    public static function enqueueAssets(string $hook): void
    {
        // Only load on data sources and wizard pages
        $allowed_hooks = [
            'toplevel_page_fp-dms-dashboard',
            'fp-suite_page_fp-dms-datasources',
            'admin_page_fpdms-connection-wizard'
        ];
        
        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        $version = defined('FP_DMS_VERSION') ? FP_DMS_VERSION : '1.0.0';
        $pluginDir = defined('FP_DMS_PLUGIN_DIR') ? FP_DMS_PLUGIN_DIR : dirname(__DIR__);

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

        wp_localize_script('fpdms-connection-wizard', 'fpdmsWizard', [
            'nonce' => wp_create_nonce('fpdms_connection_wizard'),
            'redirectUrl' => admin_url('admin.php?page=fpdms-data-sources'),
        ]);
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

        $provider = $_GET['provider'] ?? '';
        $clientId = isset($_GET['client']) ? intval($_GET['client']) : 0;

        if (empty($provider)) {
            wp_die(__('Invalid provider specified.', 'fp-dms'));
        }

        if ($clientId <= 0) {
            wp_die(__('Invalid client specified. Please select a client first.', 'fp-dms'));
        }

        $wizard = new \FP\DMS\Admin\ConnectionWizard\ConnectionWizard($provider);
        
        // Get current step from query string
        $step = isset($_GET['step']) ? intval($_GET['step']) : 0;
        $wizard->setCurrentStep($step);

        // Get saved data from session or query string
        $data = ['client_id' => $clientId];
        if (isset($_GET['data'])) {
            $savedData = json_decode(stripslashes($_GET['data']), true) ?: [];
            $data = array_merge($data, $savedData);
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

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\DataSources;

use FP\DMS\Domain\Entities\DataSource;
use FP\DMS\Support\Wp;
use WP_Error;

/**
 * Validates and builds payload for Data Source creation/update.
 */
class PayloadValidator
{
    /**
     * Build and validate payload from POST data.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function buildPayload(string $type, ?DataSource $existing = null): array|WP_Error
    {
        $label = Wp::sanitizeTextField($_POST['label'] ?? '');

        if ($label === '') {
            return new WP_Error(
                'fpdms_datasources_label',
                __('Provide a name for this data source.', 'fp-dms')
            );
        }

        $auth = $this->extractAuthData($type);
        $config = $this->extractConfigData($type);

        // Type-specific validation
        $validation = match ($type) {
            'ga4' => $this->validateGA4($auth, $config),
            'gsc' => $this->validateGSC($auth, $config),
            'google_ads' => $this->validateGoogleAds($auth, $config),
            'meta_ads' => $this->validateMetaAds($auth, $config),
            'clarity' => $this->validateClarity($auth, $config),
            'csv_generic' => $this->validateCSV($config),
            default => null,
        };

        if ($validation instanceof WP_Error) {
            return $validation;
        }

        return [
            'type' => $type,
            'label' => $label,
            'auth' => $auth,
            'config' => $config,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function extractAuthData(string $type): array
    {
        $auth = [];

        // Service account (common for Google services)
        if (in_array($type, ['ga4', 'gsc', 'google_ads'], true)) {
            $saKey = Wp::sanitizeTextField($_POST['auth']['service_account'] ?? '');
            if ($saKey !== '') {
                $auth['service_account'] = $saKey;
            }
        }

        // Meta Ads specific
        if ($type === 'meta_ads') {
            $auth['access_token'] = Wp::sanitizeTextField($_POST['auth']['access_token'] ?? '');
            $auth['account_id'] = Wp::sanitizeTextField($_POST['auth']['account_id'] ?? '');
        }

        // Clarity specific
        if ($type === 'clarity') {
            $auth['api_key'] = Wp::sanitizeTextField($_POST['auth']['api_key'] ?? '');
        }

        return $auth;
    }

    /**
     * @return array<string,mixed>
     */
    private function extractConfigData(string $type): array
    {
        $config = [];

        // GA4
        if ($type === 'ga4') {
            $config['property_id'] = Wp::sanitizeTextField($_POST['config']['property_id'] ?? '');
        }

        // GSC
        if ($type === 'gsc') {
            $config['site_url'] = Wp::sanitizeTextField($_POST['config']['site_url'] ?? '');
        }

        // Google Ads
        if ($type === 'google_ads') {
            $config['customer_id'] = Wp::sanitizeTextField($_POST['config']['customer_id'] ?? '');
        }

        // Meta Ads
        if ($type === 'meta_ads') {
            $config['account_id'] = Wp::sanitizeTextField($_POST['config']['account_id'] ?? '');
        }

        // Clarity
        if ($type === 'clarity') {
            $config['project_id'] = Wp::sanitizeTextField($_POST['config']['project_id'] ?? '');
        }

        // CSV
        if ($type === 'csv_generic') {
            $config['file_path'] = Wp::sanitizeTextField($_POST['config']['file_path'] ?? '');
            $config['delimiter'] = Wp::sanitizeTextField($_POST['config']['delimiter'] ?? ',');
        }

        return $config;
    }

    /**
     * @param array<string,mixed> $auth
     * @param array<string,mixed> $config
     */
    private function validateGA4(array $auth, array $config): ?WP_Error
    {
        if (empty($auth['service_account'])) {
            return new WP_Error(
                'fpdms_datasources_sa',
                __('Service account JSON is required for GA4.', 'fp-dms')
            );
        }

        if (empty($config['property_id'])) {
            return new WP_Error(
                'fpdms_datasources_property',
                __('GA4 Property ID is required.', 'fp-dms')
            );
        }

        return null;
    }

    /**
     * @param array<string,mixed> $auth
     * @param array<string,mixed> $config
     */
    private function validateGSC(array $auth, array $config): ?WP_Error
    {
        if (empty($auth['service_account'])) {
            return new WP_Error(
                'fpdms_datasources_sa',
                __('Service account JSON is required for GSC.', 'fp-dms')
            );
        }

        if (empty($config['site_url'])) {
            return new WP_Error(
                'fpdms_datasources_site',
                __('Site URL is required for GSC.', 'fp-dms')
            );
        }

        return null;
    }

    /**
     * @param array<string,mixed> $auth
     * @param array<string,mixed> $config
     */
    private function validateGoogleAds(array $auth, array $config): ?WP_Error
    {
        if (empty($auth['service_account'])) {
            return new WP_Error(
                'fpdms_datasources_sa',
                __('Service account JSON is required for Google Ads.', 'fp-dms')
            );
        }

        if (empty($config['customer_id'])) {
            return new WP_Error(
                'fpdms_datasources_customer',
                __('Customer ID is required for Google Ads.', 'fp-dms')
            );
        }

        return null;
    }

    /**
     * @param array<string,mixed> $auth
     * @param array<string,mixed> $config
     */
    private function validateMetaAds(array $auth, array $config): ?WP_Error
    {
        if (empty($auth['access_token'])) {
            return new WP_Error(
                'fpdms_datasources_token',
                __('Access token is required for Meta Ads.', 'fp-dms')
            );
        }

        if (empty($config['account_id'])) {
            return new WP_Error(
                'fpdms_datasources_account',
                __('Account ID is required for Meta Ads.', 'fp-dms')
            );
        }

        return null;
    }

    /**
     * @param array<string,mixed> $auth
     * @param array<string,mixed> $config
     */
    private function validateClarity(array $auth, array $config): ?WP_Error
    {
        if (empty($auth['api_key'])) {
            return new WP_Error(
                'fpdms_datasources_apikey',
                __('API key is required for Clarity.', 'fp-dms')
            );
        }

        if (empty($config['project_id'])) {
            return new WP_Error(
                'fpdms_datasources_project',
                __('Project ID is required for Clarity.', 'fp-dms')
            );
        }

        return null;
    }

    /**
     * @param array<string,mixed> $config
     */
    private function validateCSV(array $config): ?WP_Error
    {
        if (empty($config['file_path'])) {
            return new WP_Error(
                'fpdms_datasources_file',
                __('File path is required for CSV import.', 'fp-dms')
            );
        }

        return null;
    }
}

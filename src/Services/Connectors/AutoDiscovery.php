<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use FP\DMS\Support\Wp;

/**
 * Auto-discovers available resources (properties, accounts, sites) from provider APIs.
 * 
 * This eliminates manual ID entry by querying provider APIs for accessible resources.
 */
class AutoDiscovery
{
    /**
     * Discover GA4 properties accessible by the service account.
     *
     * @param string $serviceAccountJson Service account credentials JSON
     * @return array{id: string, display_name: string, account: string, website?: string}[]
     * @throws ConnectorException
     */
    public static function discoverGA4Properties(string $serviceAccountJson): array
    {
        $client = ServiceAccountHttpClient::fromJson($serviceAccountJson);

        if (!$client) {
            throw ConnectorException::authenticationFailed(
                'ga4',
                'Failed to initialize HTTP client',
                ['has_json' => !empty($serviceAccountJson)]
            );
        }

        // Use GA4 Admin API to list properties
        $url = 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries';
        $scopes = ['https://www.googleapis.com/auth/analytics.readonly'];

        $response = $client->get($url, $scopes);

        if (!$response['ok']) {
            throw ConnectorException::apiCallFailed(
                'ga4_autodiscovery',
                $url,
                $response['status'],
                $response['message'] ?? '',
                ['response_body' => $response['body'] ?? '']
            );
        }

        $properties = [];
        $data = $response['json'] ?? [];

        foreach ($data['accountSummaries'] ?? [] as $account) {
            $accountName = $account['displayName'] ?? 'Unknown Account';

            foreach ($account['propertySummaries'] ?? [] as $property) {
                // Extract numeric ID from property resource name (e.g., "properties/123456789")
                $propertyResource = $property['property'] ?? '';
                $propertyId = str_replace('properties/', '', $propertyResource);

                $properties[] = [
                    'id' => $propertyId,
                    'display_name' => $property['displayName'] ?? 'Unknown Property',
                    'account' => $accountName,
                    'resource_name' => $propertyResource,
                ];
            }
        }

        return $properties;
    }

    /**
     * Discover GSC sites accessible by the service account.
     *
     * @param string $serviceAccountJson Service account credentials JSON
     * @return array{url: string, permission_level: string}[]
     * @throws ConnectorException
     */
    public static function discoverGSCSites(string $serviceAccountJson): array
    {
        $client = ServiceAccountHttpClient::fromJson($serviceAccountJson);

        if (!$client) {
            throw ConnectorException::authenticationFailed(
                'gsc',
                'Failed to initialize HTTP client',
                ['has_json' => !empty($serviceAccountJson)]
            );
        }

        // Use Search Console API to list sites
        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites';
        $scopes = ['https://www.googleapis.com/auth/webmasters.readonly'];

        $response = $client->get($url, $scopes);

        if (!$response['ok']) {
            throw ConnectorException::apiCallFailed(
                'gsc_autodiscovery',
                $url,
                $response['status'],
                $response['message'] ?? '',
                ['response_body' => $response['body'] ?? '']
            );
        }

        $sites = [];
        $data = $response['json'] ?? [];

        foreach ($data['siteEntry'] ?? [] as $site) {
            $sites[] = [
                'url' => $site['siteUrl'] ?? '',
                'permission_level' => $site['permissionLevel'] ?? 'unknown',
            ];
        }

        return $sites;
    }

    /**
     * Test and enrich GA4 connection with property information.
     *
     * @param string $serviceAccountJson Service account credentials JSON
     * @param string $propertyId GA4 Property ID
     * @return array{success: bool, property_name?: string, last_data?: string, sample_data?: array}
     * @throws ConnectorException
     */
    public static function testAndEnrichGA4Connection(
        string $serviceAccountJson,
        string $propertyId
    ): array {
        $client = ServiceAccountHttpClient::fromJson($serviceAccountJson);

        if (!$client) {
            throw ConnectorException::authenticationFailed(
                'ga4',
                'Failed to initialize HTTP client'
            );
        }

        // Test with a simple query
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";
        $body = [
            'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'yesterday']],
            'metrics' => [['name' => 'activeUsers']],
            'limit' => 1,
        ];
        $scopes = ['https://www.googleapis.com/auth/analytics.readonly'];

        $response = $client->postJson($url, $body, $scopes);

        if (!$response['ok']) {
            return [
                'success' => false,
                'error' => $response['message'] ?? 'Connection test failed',
                'status_code' => $response['status'],
            ];
        }

        $data = $response['json'] ?? [];

        return [
            'success' => true,
            'property_name' => $data['propertyQuota']['tokensPerDay']['quota'] ?? null,
            'last_data' => $data['metadata']['dataLastRefreshed'] ?? null,
            'sample_data' => $data['rows'][0]['metricValues'] ?? [],
            'row_count' => $data['rowCount'] ?? 0,
        ];
    }

    /**
     * Test and enrich GSC connection with site information.
     *
     * @param string $serviceAccountJson Service account credentials JSON
     * @param string $siteUrl GSC Site URL
     * @return array{success: bool, clicks?: int, impressions?: int}
     * @throws ConnectorException
     */
    public static function testAndEnrichGSCConnection(
        string $serviceAccountJson,
        string $siteUrl
    ): array {
        $client = ServiceAccountHttpClient::fromJson($serviceAccountJson);

        if (!$client) {
            throw ConnectorException::authenticationFailed(
                'gsc',
                'Failed to initialize HTTP client'
            );
        }

        // Test with a simple query
        $encodedUrl = rawurlencode($siteUrl);
        $url = "https://searchconsole.googleapis.com/webmasters/v3/sites/{$encodedUrl}/searchAnalytics/query";
        $body = [
            'startDate' => Wp::date('Y-m-d', strtotime('-7 days')),
            'endDate' => Wp::date('Y-m-d', strtotime('-1 day')),
            'dimensions' => ['date'],
            'rowLimit' => 1,
        ];
        $scopes = ['https://www.googleapis.com/auth/webmasters.readonly'];

        $response = $client->postJson($url, $body, $scopes);

        if (!$response['ok']) {
            return [
                'success' => false,
                'error' => $response['message'] ?? 'Connection test failed',
                'status_code' => $response['status'],
            ];
        }

        $data = $response['json'] ?? [];
        $firstRow = $data['rows'][0] ?? null;

        return [
            'success' => true,
            'clicks' => $firstRow['clicks'] ?? 0,
            'impressions' => $firstRow['impressions'] ?? 0,
            'has_data' => !empty($data['rows']),
        ];
    }

    /**
     * Get metadata about a GA4 property without querying data.
     *
     * @param string $serviceAccountJson Service account credentials JSON
     * @param string $propertyId GA4 Property ID
     * @return array{display_name?: string, create_time?: string, time_zone?: string}
     * @throws ConnectorException
     */
    public static function getGA4PropertyMetadata(
        string $serviceAccountJson,
        string $propertyId
    ): array {
        $client = ServiceAccountHttpClient::fromJson($serviceAccountJson);

        if (!$client) {
            throw ConnectorException::authenticationFailed(
                'ga4',
                'Failed to initialize HTTP client'
            );
        }

        // Use Admin API to get property details
        $url = "https://analyticsadmin.googleapis.com/v1beta/properties/{$propertyId}";
        $scopes = ['https://www.googleapis.com/auth/analytics.readonly'];

        $response = $client->get($url, $scopes);

        if (!$response['ok']) {
            throw ConnectorException::apiCallFailed(
                'ga4_metadata',
                $url,
                $response['status'],
                $response['message'] ?? ''
            );
        }

        $data = $response['json'] ?? [];

        return [
            'display_name' => $data['displayName'] ?? null,
            'create_time' => $data['createTime'] ?? null,
            'time_zone' => $data['timeZone'] ?? null,
            'currency_code' => $data['currencyCode'] ?? null,
            'industry_category' => $data['industryCategory'] ?? null,
        ];
    }

    /**
     * Validate service account has necessary permissions.
     *
     * @param string $serviceAccountJson Service account credentials JSON
     * @param string $provider Provider type (ga4, gsc, etc.)
     * @return array{valid: bool, permissions?: array, error?: string}
     */
    public static function validateServiceAccountPermissions(
        string $serviceAccountJson,
        string $provider
    ): array {
        try {
            $decoded = json_decode($serviceAccountJson, true);

            if (!is_array($decoded)) {
                return [
                    'valid' => false,
                    'error' => 'Invalid JSON format',
                ];
            }

            // Check required fields
            $required = ['type', 'project_id', 'private_key', 'client_email'];
            $missing = array_diff($required, array_keys($decoded));

            if (!empty($missing)) {
                return [
                    'valid' => false,
                    'error' => 'Missing required fields: ' . implode(', ', $missing),
                ];
            }

            // Check type
            if ($decoded['type'] !== 'service_account') {
                return [
                    'valid' => false,
                    'error' => 'Not a service account',
                ];
            }

            return [
                'valid' => true,
                'email' => $decoded['client_email'],
                'project_id' => $decoded['project_id'],
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

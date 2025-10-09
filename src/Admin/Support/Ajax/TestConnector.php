<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Support\Ajax;

use FP\DMS\Services\Connectors\CentralServiceAccount;
use FP\DMS\Services\Connectors\ClientConnectorValidator;
use FP\DMS\Services\Connectors\ServiceAccountHttpClient;
use FP\DMS\Support\Wp;

use function __;
use function add_action;
use function current_user_can;
use function gmdate;
use function in_array;
use function is_array;
use function rawurlencode;
use function sprintf;
use function strtotime;
use function trim;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_verify_nonce;

class TestConnector
{
    public static function register(): void
    {
        add_action('wp_ajax_fpdms_test_connector', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error([
                'ok' => false,
                'status' => 403,
                'message' => __('You are not allowed to test connectors.', 'fp-dms'),
            ], 403);
        }

        $nonce = Wp::sanitizeTextField($_POST['_ajax_nonce'] ?? '');
        if (! wp_verify_nonce($nonce, 'fpdms_test_connector')) {
            wp_send_json_error([
                'ok' => false,
                'status' => 403,
                'message' => __('Security check failed. Refresh the page and try again.', 'fp-dms'),
            ], 403);
        }

        $connectorType = Wp::sanitizeKey($_POST['connector_type'] ?? '');
        if (! in_array($connectorType, ['ga4', 'gsc'], true)) {
            wp_send_json_error([
                'ok' => false,
                'status' => 400,
                'message' => __('Unknown connector type.', 'fp-dms'),
            ], 400);
        }

        $serviceAccountJson = CentralServiceAccount::getJson($connectorType);
        if ($serviceAccountJson === '') {
            wp_send_json_error([
                'ok' => false,
                'status' => 412,
                'message' => __('Configure the central service account before running tests.', 'fp-dms'),
            ]);
        }

        $client = ServiceAccountHttpClient::fromJson($serviceAccountJson);
        if (! $client) {
            wp_send_json_error([
                'ok' => false,
                'status' => 400,
                'message' => __('The central service account JSON is not valid.', 'fp-dms'),
            ]);
        }

        if ($connectorType === 'ga4') {
            self::handleGa4($client);

            return;
        }

        self::handleGsc($client);
    }

    private static function handleGa4(ServiceAccountHttpClient $client): void
    {
        $propertyId = ClientConnectorValidator::sanitizeGa4PropertyId($_POST['property_id'] ?? '');
        if ($propertyId === '') {
            wp_send_json_error([
                'ok' => false,
                'status' => 422,
                'message' => __('Provide a valid GA4 property ID before testing.', 'fp-dms'),
            ]);
        }

        $start = gmdate('Y-m-d', strtotime('-3 days'));
        $end = gmdate('Y-m-d');

        $response = $client->postJson(
            sprintf('https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport', rawurlencode($propertyId)),
            [
                'dateRanges' => [
                    ['startDate' => $start, 'endDate' => $end],
                ],
                'dimensions' => [
                    ['name' => 'date'],
                ],
                'metrics' => [
                    ['name' => 'activeUsers'],
                ],
                'limit' => 3,
            ],
            ['https://www.googleapis.com/auth/analytics.readonly']
        );

        self::dispatchResponse($response, 'ga4');
    }

    private static function handleGsc(ServiceAccountHttpClient $client): void
    {
        $siteProperty = ClientConnectorValidator::sanitizeGscSiteProperty($_POST['site_property'] ?? '');
        if ($siteProperty === '') {
            wp_send_json_error([
                'ok' => false,
                'status' => 422,
                'message' => __('Provide a valid Search Console property before testing.', 'fp-dms'),
            ]);
        }

        $start = gmdate('Y-m-d', strtotime('-3 days'));
        $end = gmdate('Y-m-d');

        $response = $client->postJson(
            sprintf('https://searchconsole.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query', rawurlencode($siteProperty)),
            [
                'startDate' => $start,
                'endDate' => $end,
                'dimensions' => ['page'],
                'rowLimit' => 1,
            ],
            ['https://www.googleapis.com/auth/webmasters.readonly']
        );

        self::dispatchResponse($response, 'gsc');
    }

    /**
     * @param array{ok:bool,status:int,body:string,json:array<string,mixed>|null,message:string} $response
     */
    private static function dispatchResponse(array $response, string $connectorType): void
    {
        if ($response['ok']) {
            $rows = 0;
            if (is_array($response['json'])) {
                if ($connectorType === 'ga4') {
                    $rows = isset($response['json']['rowCount'])
                        ? (int) $response['json']['rowCount']
                        : (is_array($response['json']['rows'] ?? null) ? count($response['json']['rows']) : 0);
                } else {
                    $rows = isset($response['json']['rows']) && is_array($response['json']['rows'])
                        ? count($response['json']['rows'])
                        : 0;
                }
            }

            wp_send_json_success([
                'ok' => true,
                'status' => $response['status'],
                'message' => sprintf(
                    __('Connection verified. Received %d rows in the last 3 days.', 'fp-dms'),
                    $rows
                ),
            ]);
        }

        $message = trim($response['message']);
        if (in_array($response['status'], [403, 404], true)) {
            if ($connectorType === 'ga4') {
                $message .= ' ' . __('Ensure the service account email has at least Reader access to the GA4 property.', 'fp-dms');
            } else {
                $message .= ' ' . __('Ensure the service account email is a verified owner for this Search Console property.', 'fp-dms');
            }
        }

        wp_send_json_error([
            'ok' => false,
            'status' => $response['status'],
            'message' => $message !== '' ? $message : __('Connection failed. Check the property and permissions.', 'fp-dms'),
            'debug' => [
                'status' => $response['status'],
                'body' => $response['body'],
            ],
        ]);
    }
}

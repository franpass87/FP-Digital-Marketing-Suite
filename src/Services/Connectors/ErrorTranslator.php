<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

/**
 * Translates technical connector errors into user-friendly messages with actionable steps.
 */
class ErrorTranslator
{
    /**
     * Translate a ConnectorException into a user-friendly error message with actions.
     *
     * @param ConnectorException $e The exception to translate
     * @return array{title: string, message: string, actions: array, help?: string, technical_details?: string}
     */
    public static function translate(ConnectorException $e): array
    {
        $context = $e->getContext();
        $provider = $context['provider'] ?? 'unknown';
        $code = $e->getCode();

        // Authentication failures (401)
        if ($code === 401) {
            if (str_contains($e->getMessage(), 'service account')) {
                return [
                    'title' => '🔑 ' . __('Invalid Credentials', 'fp-dms'),
                    'message' => __('The service account does not have access to this resource.', 'fp-dms'),
                    'actions' => [
                        [
                            'label' => __('Check Permissions Guide', 'fp-dms'),
                            'type' => 'link',
                            'url' => self::getPermissionsGuideUrl($provider),
                            'target' => '_blank',
                        ],
                        [
                            'label' => __('Generate New Service Account', 'fp-dms'),
                            'type' => 'wizard',
                            'step' => 'service_account',
                        ],
                    ],
                    'help' => self::getAuthenticationHelp($provider),
                    'technical_details' => $e->getMessage(),
                ];
            }
        }

        // Forbidden - Insufficient permissions (403)
        if ($code === 403) {
            return [
                'title' => '⛔ ' . __('Insufficient Permissions', 'fp-dms'),
                'message' => sprintf(
                    __('The service account does not have the necessary permissions to access %s.', 'fp-dms'),
                    self::getProviderLabel($provider)
                ),
                'actions' => [
                    [
                        'label' => __('Permissions Setup Guide', 'fp-dms'),
                        'type' => 'link',
                        'url' => self::getPermissionsGuideUrl($provider),
                        'target' => '_blank',
                    ],
                ],
                'help' => sprintf(
                    __('You need to add the service account as a "Viewer" in the %s console.', 'fp-dms'),
                    self::getProviderLabel($provider)
                ),
                'technical_details' => $e->getMessage(),
            ];
        }

        // Not Found (404)
        if ($code === 404) {
            return [
                'title' => '🔍 ' . __('Resource Not Found', 'fp-dms'),
                'message' => self::getNotFoundMessage($provider, $context),
                'actions' => [
                    [
                        'label' => __('Verify ID', 'fp-dms'),
                        'type' => 'edit_field',
                        'field' => self::getRelevantField($provider),
                    ],
                    [
                        'label' => __('Use Auto-Discovery', 'fp-dms'),
                        'type' => 'wizard',
                        'step' => 'autodiscovery',
                    ],
                ],
                'help' => __('Double-check that you copied the correct ID from the platform.', 'fp-dms'),
                'technical_details' => $e->getMessage(),
            ];
        }

        // Rate Limit Exceeded (429)
        if ($code === 429) {
            $retryAfter = $context['retry_after'] ?? 60;
            return [
                'title' => '⏱️ ' . __('Too Many Requests', 'fp-dms'),
                'message' => __('You have exceeded the API request limit.', 'fp-dms'),
                'actions' => [
                    [
                        'label' => sprintf(__('Retry in %d seconds', 'fp-dms'), $retryAfter),
                        'type' => 'retry',
                        'delay' => $retryAfter,
                    ],
                ],
                'help' => __('The service limits the number of requests per minute. Please wait before retrying.', 'fp-dms'),
            ];
        }

        // Validation Failed (422)
        if ($code === 422) {
            $field = $context['field'] ?? 'unknown';
            return [
                'title' => '⚠️ ' . __('Validation Error', 'fp-dms'),
                'message' => sprintf(
                    __('The field "%s" contains an invalid value.', 'fp-dms'),
                    self::getFieldLabel($provider, $field)
                ),
                'actions' => [
                    [
                        'label' => __('See Format Example', 'fp-dms'),
                        'type' => 'show_example',
                        'field' => $field,
                        'example' => self::getFieldExample($provider, $field),
                    ],
                ],
                'help' => $context['reason'] ?? __('Please check the field format.', 'fp-dms'),
                'technical_details' => $e->getMessage(),
            ];
        }

        // Invalid Configuration (400)
        if ($code === 400) {
            return [
                'title' => '⚙️ ' . __('Invalid Configuration', 'fp-dms'),
                'message' => $context['reason'] ?? __('The configuration is incomplete or invalid.', 'fp-dms'),
                'actions' => [
                    [
                        'label' => __('View Setup Guide', 'fp-dms'),
                        'type' => 'link',
                        'url' => self::getSetupGuideUrl($provider),
                        'target' => '_blank',
                    ],
                    [
                        'label' => __('Use Setup Wizard', 'fp-dms'),
                        'type' => 'wizard',
                        'step' => 'intro',
                    ],
                ],
                'technical_details' => $e->getMessage(),
            ];
        }

        // Generic/Unknown error
        return [
            'title' => '❌ ' . __('Connection Error', 'fp-dms'),
            'message' => __('An error occurred while connecting to the service.', 'fp-dms'),
            'actions' => [
                [
                    'label' => __('Retry', 'fp-dms'),
                    'type' => 'retry',
                ],
                [
                    'label' => __('Contact Support', 'fp-dms'),
                    'type' => 'support',
                    'context' => $context,
                ],
            ],
            'technical_details' => $e->getMessage(),
        ];
    }

    /**
     * Get user-friendly provider label.
     */
    private static function getProviderLabel(string $provider): string
    {
        return match ($provider) {
            'ga4' => 'Google Analytics 4',
            'gsc' => 'Google Search Console',
            'google_ads' => 'Google Ads',
            'meta_ads' => 'Meta Ads',
            'clarity' => 'Microsoft Clarity',
            'csv_generic' => 'CSV',
            default => ucfirst($provider),
        };
    }

    /**
     * Get not found message specific to provider.
     */
    private static function getNotFoundMessage(string $provider, array $context): string
    {
        return match ($provider) {
            'ga4' => sprintf(
                __('Property ID "%s" not found. Check the ID or use automatic discovery.', 'fp-dms'),
                $context['property_id'] ?? 'unknown'
            ),
            'gsc' => sprintf(
                __('Property "%s" not found. Make sure it is verified in Search Console.', 'fp-dms'),
                $context['site_url'] ?? 'unknown'
            ),
            'google_ads' => sprintf(
                __('Customer ID "%s" not found. Verify the format (000-000-0000).', 'fp-dms'),
                $context['customer_id'] ?? 'unknown'
            ),
            'meta_ads' => sprintf(
                __('Account ID "%s" not found. Must start with "act_".', 'fp-dms'),
                $context['account_id'] ?? 'unknown'
            ),
            default => __('Resource not found. Please check your credentials.', 'fp-dms'),
        };
    }

    /**
     * Get the relevant field name for a provider.
     */
    private static function getRelevantField(string $provider): string
    {
        return match ($provider) {
            'ga4' => 'property_id',
            'gsc' => 'site_url',
            'google_ads' => 'customer_id',
            'meta_ads' => 'account_id',
            'clarity' => 'project_id',
            default => 'config',
        };
    }

    /**
     * Get field label for display.
     */
    private static function getFieldLabel(string $provider, string $field): string
    {
        $labels = [
            'property_id' => __('Property ID', 'fp-dms'),
            'site_url' => __('Site URL', 'fp-dms'),
            'customer_id' => __('Customer ID', 'fp-dms'),
            'account_id' => __('Account ID', 'fp-dms'),
            'project_id' => __('Project ID', 'fp-dms'),
            'service_account' => __('Service Account', 'fp-dms'),
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Get format example for a field.
     */
    private static function getFieldExample(string $provider, string $field): string
    {
        $examples = [
            'ga4' => [
                'property_id' => '123456789',
            ],
            'google_ads' => [
                'customer_id' => '123-456-7890',
            ],
            'meta_ads' => [
                'account_id' => 'act_1234567890',
            ],
        ];

        return $examples[$provider][$field] ?? '';
    }

    /**
     * Get authentication help text for provider.
     */
    private static function getAuthenticationHelp(string $provider): string
    {
        return match ($provider) {
            'ga4' => __(
                'Make sure the service account has been added as a user in Google Analytics with at least "Viewer" permissions.',
                'fp-dms'
            ),
            'gsc' => __(
                'Add the service account email as a user in Google Search Console with "Full" or "Restricted" permissions.',
                'fp-dms'
            ),
            'google_ads' => __(
                'The service account must be added to your Google Ads account with "Standard" access.',
                'fp-dms'
            ),
            'meta_ads' => __(
                'Verify that your Meta Business access token has the required permissions.',
                'fp-dms'
            ),
            default => __('Verify your credentials and permissions.', 'fp-dms'),
        };
    }

    /**
     * Get URL for permissions guide.
     */
    private static function getPermissionsGuideUrl(string $provider): string
    {
        // These would be real URLs in production
        return match ($provider) {
            'ga4' => 'https://support.google.com/analytics/answer/9305587',
            'gsc' => 'https://support.google.com/webmasters/answer/7687615',
            'google_ads' => 'https://support.google.com/google-ads/answer/7476552',
            'meta_ads' => 'https://developers.facebook.com/docs/marketing-api/overview',
            default => '#',
        };
    }

    /**
     * Get URL for setup guide.
     */
    private static function getSetupGuideUrl(string $provider): string
    {
        // These would link to plugin documentation
        return admin_url('admin.php?page=fpdms-help&section=' . $provider);
    }
}

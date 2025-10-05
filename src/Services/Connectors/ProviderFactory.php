<?php

declare(strict_types=1);

namespace FP\DMS\Services\Connectors;

use function __;

class ProviderFactory
{
    public static function create(string $type, array $auth, array $config): ?DataSourceProviderInterface
    {
        return match ($type) {
            'ga4' => new GA4Provider($auth, $config),
            'gsc' => new GSCProvider($auth, $config),
            'google_ads' => new GoogleAdsProvider($auth, $config),
            'meta_ads' => new MetaAdsProvider($auth, $config),
            'clarity' => new ClarityProvider($auth, $config),
            'csv_generic' => new CsvGenericProvider($auth, $config),
            default => null,
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'ga4' => [
                'label' => __('Google Analytics 4', 'fp-dms'),
                'summary' => __('Sync engagement metrics directly from GA4.', 'fp-dms'),
                'description' => __('Use a service account JSON with access to the GA4 property or load it from wp-config.', 'fp-dms'),
                'steps' => [
                    __('Create or reuse a Google Cloud service account with access to the GA4 property.', 'fp-dms'),
                    __('Share the service account email with the property (Admin → Property Access Management).', 'fp-dms'),
                    __('Optionally define a wp-config constant that contains the JSON if you prefer not to paste it here.', 'fp-dms'),
                    __('Copy the numeric Property ID from GA4 Admin → Property Settings.', 'fp-dms'),
                ],
                'fields' => [
                    'auth' => [
                        'credential_source' => [
                            'type' => 'select',
                            'label' => __('Credential Source', 'fp-dms'),
                            'description' => __('Decide whether to paste the JSON or load it from a wp-config constant.', 'fp-dms'),
                            'options' => [
                                'manual' => __('Paste JSON manually', 'fp-dms'),
                                'constant' => __('Use wp-config constant', 'fp-dms'),
                            ],
                            'default' => 'manual',
                        ],
                        'service_account' => [
                            'type' => 'textarea',
                            'label' => __('Service Account JSON', 'fp-dms'),
                            'description' => __('Paste the entire JSON key for the service account.', 'fp-dms'),
                        ],
                        'service_account_constant' => [
                            'type' => 'text',
                            'label' => __('wp-config Constant', 'fp-dms'),
                            'description' => __('Name of the constant (e.g. FPDMS_GA4_JSON) that returns the JSON string.', 'fp-dms'),
                        ],
                    ],
                    'config' => [
                        'property_id' => [
                            'type' => 'text',
                            'label' => __('Property ID', 'fp-dms'),
                            'description' => __('GA4 property identifier (numbers only).', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'gsc' => [
                'label' => __('Google Search Console', 'fp-dms'),
                'summary' => __('Bring in organic search queries and clicks.', 'fp-dms'),
                'description' => __('Provide a service account JSON (or load it from wp-config) and the verified site URL.', 'fp-dms'),
                'steps' => [
                    __('Generate a service account JSON and add the client email as an owner in Search Console.', 'fp-dms'),
                    __('Optionally define a wp-config constant that contains the JSON if you prefer not to paste it here.', 'fp-dms'),
                    __('Confirm the property you want to track is verified in the same Search Console account.', 'fp-dms'),
                    __('Copy the exact site URL (including protocol) from the property settings.', 'fp-dms'),
                ],
                'fields' => [
                    'auth' => [
                        'credential_source' => [
                            'type' => 'select',
                            'label' => __('Credential Source', 'fp-dms'),
                            'description' => __('Decide whether to paste the JSON or load it from a wp-config constant.', 'fp-dms'),
                            'options' => [
                                'manual' => __('Paste JSON manually', 'fp-dms'),
                                'constant' => __('Use wp-config constant', 'fp-dms'),
                            ],
                            'default' => 'manual',
                        ],
                        'service_account' => [
                            'type' => 'textarea',
                            'label' => __('Service Account JSON', 'fp-dms'),
                            'description' => __('Paste the JSON key for the Search Console service account.', 'fp-dms'),
                        ],
                        'service_account_constant' => [
                            'type' => 'text',
                            'label' => __('wp-config Constant', 'fp-dms'),
                            'description' => __('Name of the constant (e.g. FPDMS_GSC_JSON) that returns the JSON string.', 'fp-dms'),
                        ],
                    ],
                    'config' => [
                        'site_url' => [
                            'type' => 'url',
                            'label' => __('Site URL', 'fp-dms'),
                            'description' => __('Exact site URL as registered in Google Search Console.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'google_ads' => [
                'label' => __('Google Ads', 'fp-dms'),
                'summary' => __('Automate ad spend, clicks, and conversions.', 'fp-dms'),
                'description' => __('Connect using your Google Ads API credentials to keep campaigns in sync automatically.', 'fp-dms'),
                'steps' => [
                    __('Ensure your Google Ads developer token is approved and ready for production use.', 'fp-dms'),
                    __('Create an OAuth client (Desktop app) and generate a refresh token for the Google Ads account.', 'fp-dms'),
                    __('Copy the customer ID (and manager ID if used) exactly as it appears in Google Ads.', 'fp-dms'),
                ],
                'fields' => [
                    'auth' => [
                        'developer_token' => [
                            'type' => 'text',
                            'label' => __('Developer Token', 'fp-dms'),
                            'description' => __('Token from the Google Ads API center.', 'fp-dms'),
                        ],
                        'client_id' => [
                            'type' => 'text',
                            'label' => __('OAuth Client ID', 'fp-dms'),
                            'description' => __('Installed application client ID.', 'fp-dms'),
                        ],
                        'client_secret' => [
                            'type' => 'text',
                            'label' => __('OAuth Client Secret', 'fp-dms'),
                            'description' => __('Secret paired with the client ID.', 'fp-dms'),
                        ],
                        'refresh_token' => [
                            'type' => 'text',
                            'label' => __('Refresh Token', 'fp-dms'),
                            'description' => __('Generated after authorising the Google Ads account.', 'fp-dms'),
                        ],
                    ],
                    'config' => [
                        'customer_id' => [
                            'type' => 'text',
                            'label' => __('Customer ID', 'fp-dms'),
                            'description' => __('Google Ads account in 000-000-0000 format.', 'fp-dms'),
                        ],
                        'login_customer_id' => [
                            'type' => 'text',
                            'label' => __('Manager Account ID (optional)', 'fp-dms'),
                            'description' => __('Required when authenticating through a manager account.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'meta_ads' => [
                'label' => __('Meta Ads', 'fp-dms'),
                'summary' => __('Sync Facebook and Instagram campaign performance.', 'fp-dms'),
                'description' => __('Use a long-lived access token to connect your Meta Ads account.', 'fp-dms'),
                'steps' => [
                    __('Create a system user or long-lived access token with ads_read permission.', 'fp-dms'),
                    __('Note the ad account ID (act_123...) you plan to report on.', 'fp-dms'),
                    __('Optional: copy the Pixel ID if you want to include behaviour signals.', 'fp-dms'),
                ],
                'fields' => [
                    'auth' => [
                        'access_token' => [
                            'type' => 'textarea',
                            'label' => __('Access Token', 'fp-dms'),
                            'description' => __('System user or long-lived access token with ads_read permission.', 'fp-dms'),
                        ],
                    ],
                    'config' => [
                        'account_id' => [
                            'type' => 'text',
                            'label' => __('Ad Account ID', 'fp-dms'),
                            'description' => __('ID in the format act_1234567890.', 'fp-dms'),
                        ],
                        'pixel_id' => [
                            'type' => 'text',
                            'label' => __('Pixel ID (optional)', 'fp-dms'),
                            'description' => __('Used for conversion and behaviour insights.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'clarity' => [
                'label' => __('Microsoft Clarity', 'fp-dms'),
                'summary' => __('Capture behaviour analytics and UX anomalies.', 'fp-dms'),
                'description' => __('Connect with the Clarity API to pull behaviour analytics automatically.', 'fp-dms'),
                'steps' => [
                    __('Create or open your Clarity project and generate an API key.', 'fp-dms'),
                    __('Copy the project ID from the Clarity setup or settings page.', 'fp-dms'),
                    __('Optional: add the site URL or webhook to receive anomaly alerts.', 'fp-dms'),
                ],
                'fields' => [
                    'auth' => [
                        'api_key' => [
                            'type' => 'text',
                            'label' => __('API Key', 'fp-dms'),
                            'description' => __('Generated from the Clarity project settings.', 'fp-dms'),
                        ],
                    ],
                    'config' => [
                        'project_id' => [
                            'type' => 'text',
                            'label' => __('Project ID', 'fp-dms'),
                            'description' => __('Unique identifier of the Clarity project.', 'fp-dms'),
                        ],
                        'site_url' => [
                            'type' => 'url',
                            'label' => __('Site URL (optional)', 'fp-dms'),
                            'description' => __('Used for reference within reports.', 'fp-dms'),
                        ],
                        'webhook_url' => [
                            'type' => 'url',
                            'label' => __('Webhook URL (optional)', 'fp-dms'),
                            'description' => __('Notifications are sent to this webhook when anomalies are detected.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'csv_generic' => [
                'label' => __('Generic CSV Data Source', 'fp-dms'),
                'summary' => __('Upload any normalized CSV for custom metrics.', 'fp-dms'),
                'description' => __('Upload any normalized CSV to include custom metrics in the report.', 'fp-dms'),
                'steps' => [
                    __('Name the data source so it can be identified in dashboards and reports.', 'fp-dms'),
                    __('Export a CSV with a date column plus any metrics you want to track.', 'fp-dms'),
                    __('Upload the CSV file; the suite will summarise the metrics automatically.', 'fp-dms'),
                ],
                'fields' => [
                    'config' => [
                        'source_label' => [
                            'type' => 'text',
                            'label' => __('Source Label', 'fp-dms'),
                            'description' => __('Name used when presenting the metrics in the report.', 'fp-dms'),
                        ],
                    ],
                    'uploads' => [
                        'csv_file' => [
                            'label' => __('CSV Upload', 'fp-dms'),
                            'description' => __('Upload a CSV with date and metric columns; the data is summarized automatically.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
        ];
    }
}

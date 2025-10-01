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
            'clarity' => new ClarityCsvProvider($auth, $config),
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
                'description' => __('Use a service account JSON with access to the GA4 property.', 'fp-dms'),
                'fields' => [
                    'auth' => [
                        'service_account' => [
                            'type' => 'textarea',
                            'label' => __('Service Account JSON', 'fp-dms'),
                            'description' => __('Paste the entire JSON key for the service account.', 'fp-dms'),
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
                'description' => __('Provide a service account JSON and the verified site URL.', 'fp-dms'),
                'fields' => [
                    'auth' => [
                        'service_account' => [
                            'type' => 'textarea',
                            'label' => __('Service Account JSON', 'fp-dms'),
                            'description' => __('Paste the JSON key for the Search Console service account.', 'fp-dms'),
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
                'label' => __('Google Ads (CSV)', 'fp-dms'),
                'description' => __('Upload the CSV export produced from the Google Ads reports UI.', 'fp-dms'),
                'fields' => [
                    'config' => [
                        'account_name' => [
                            'type' => 'text',
                            'label' => __('Account Label', 'fp-dms'),
                            'description' => __('Friendly name for the ads account shown in reports.', 'fp-dms'),
                        ],
                    ],
                    'uploads' => [
                        'csv_file' => [
                            'label' => __('CSV Upload', 'fp-dms'),
                            'description' => __('Provide a recent CSV export; data is summarized and stored securely.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'meta_ads' => [
                'label' => __('Meta Ads (CSV)', 'fp-dms'),
                'description' => __('Upload the CSV export from Meta Ads reporting.', 'fp-dms'),
                'fields' => [
                    'config' => [
                        'account_name' => [
                            'type' => 'text',
                            'label' => __('Account Label', 'fp-dms'),
                            'description' => __('Friendly name for the Meta Ads account shown in reports.', 'fp-dms'),
                        ],
                    ],
                    'uploads' => [
                        'csv_file' => [
                            'label' => __('CSV Upload', 'fp-dms'),
                            'description' => __('Provide a recent CSV export; metrics will be aggregated and stored.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'clarity' => [
                'label' => __('Microsoft Clarity (CSV)', 'fp-dms'),
                'description' => __('Upload the CSV summary exported from Clarity. Optional webhook URL is used for live alerts.', 'fp-dms'),
                'fields' => [
                    'config' => [
                        'site_url' => [
                            'type' => 'url',
                            'label' => __('Site URL', 'fp-dms'),
                            'description' => __('Tracked site URL.', 'fp-dms'),
                        ],
                        'webhook_url' => [
                            'type' => 'url',
                            'label' => __('Webhook URL (optional)', 'fp-dms'),
                            'description' => __('Notifications are sent to this webhook when anomalies are detected.', 'fp-dms'),
                        ],
                    ],
                    'uploads' => [
                        'csv_file' => [
                            'label' => __('CSV Upload', 'fp-dms'),
                            'description' => __('Provide the exported CSV; only aggregated metrics are stored.', 'fp-dms'),
                        ],
                    ],
                ],
            ],
            'csv_generic' => [
                'label' => __('Generic CSV Data Source', 'fp-dms'),
                'description' => __('Upload any normalized CSV to include custom metrics in the report.', 'fp-dms'),
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

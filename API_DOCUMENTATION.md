# FP Digital Marketing Suite - API Documentation

This document provides comprehensive documentation for developers who want to extend or integrate with the FP Digital Marketing Suite plugin.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Core APIs](#core-apis)
- [Analytics API](#analytics-api)
- [Client Management API](#client-management-api)
- [SEO Tools API](#seo-tools-api)
- [Marketing Automation API](#marketing-automation-api)
- [Hooks and Filters](#hooks-and-filters)
- [Database Schema](#database-schema)
- [Examples](#examples)
- [Best Practices](#best-practices)

## Overview

FP Digital Marketing Suite provides a comprehensive API for digital marketing functionality. The plugin is built with extensibility in mind, offering multiple integration points for developers.

### Key Features

- **Analytics Integration**: Google Analytics 4, Google Ads, Search Console, Microsoft Clarity
- **Client Management**: Custom post types and metadata management
- **SEO Tools**: XML sitemaps, schema markup, meta management
- **Marketing Automation**: UTM tracking, conversion events, audience segmentation
- **Performance Monitoring**: Core Web Vitals, caching, optimization
- **Security**: GDPR compliance, secure data handling

## Architecture

### Namespace Structure

```php
FP\DigitalMarketing\
├── Analytics\          # Analytics integrations
├── Client\            # Client management
├── SEO\               # SEO tools
├── Marketing\         # Marketing automation
├── Performance\       # Performance optimization
├── Security\          # Security features
├── Admin\             # Admin interfaces
├── API\               # REST API endpoints
├── Helpers\           # Utility classes
└── Setup\             # Installation and setup
```

### Core Classes

- **`Plugin`**: Main plugin class and initialization
- **`Settings`**: Configuration management
- **`Database`**: Database operations and schema
- **`Cache`**: Caching system
- **`Security`**: Security and authentication

## Core APIs

### Plugin Initialization

```php
// Get main plugin instance
$plugin = FP\DigitalMarketing\Plugin::get_instance();

// Check if plugin is loaded
if (class_exists('FP\DigitalMarketing\Plugin')) {
    // Plugin is available
}
```

### Settings API

```php
use FP\DigitalMarketing\Settings;

// Get setting value
$value = Settings::get_option('analytics_ga4_measurement_id');

// Set setting value
Settings::update_option('analytics_ga4_measurement_id', 'G-XXXXXXXXXX');

// Get all settings
$all_settings = Settings::get_all_options();

// Delete setting
Settings::delete_option('old_setting_key');
```

### Database API

```php
use FP\DigitalMarketing\Helpers\Database;

// Get database instance
$db = Database::get_instance();

// Execute custom query
$results = $db->query("SELECT * FROM {$db->get_table_name('metrics')} WHERE client_id = %d", $client_id);

// Get table name with prefix
$table_name = $db->get_table_name('analytics_data');
```

## Analytics API

### Metrics Aggregator

```php
use FP\DigitalMarketing\Helpers\MetricsAggregator;

// Get aggregated metrics for a client
$metrics = MetricsAggregator::get_aggregated_metrics(
    123,                        // client_id
    '2024-01-01 00:00:00',     // period_start
    '2024-01-31 23:59:59'      // period_end
);

// Advanced metrics query
$result = MetricsAggregator::query_metrics([
    'client_id' => 123,
    'period_start' => '2024-01-01 00:00:00',
    'period_end' => '2024-01-31 23:59:59',
    'kpis' => ['sessions', 'users', 'revenue'],
    'source_types' => ['analytics', 'advertising'],
    'include_trends' => true,
    'limit' => 10
]);

// Get trending metrics
$trends = MetricsAggregator::get_trending_metrics(
    123,                        // client_id
    '2024-01-01 00:00:00',     // period_start
    '2024-06-30 23:59:59',     // period_end
    6                          // number of periods
);
```

### Google Analytics 4 Integration

```php
use FP\DigitalMarketing\Analytics\GoogleAnalytics4;

// Initialize GA4 client
$ga4 = new GoogleAnalytics4();

// Get real-time data
$realtime_data = $ga4->get_realtime_data($client_id);

// Get analytics report
$report = $ga4->get_analytics_report($client_id, [
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'metrics' => ['sessions', 'users', 'bounceRate'],
    'dimensions' => ['date', 'source']
]);

// Get conversion data
$conversions = $ga4->get_conversion_data($client_id, $start_date, $end_date);
```

### Google Ads Integration

```php
use FP\DigitalMarketing\Analytics\GoogleAds;

// Initialize Google Ads client
$ads = new GoogleAds();

// Get campaign performance
$campaigns = $ads->get_campaign_performance($client_id, $start_date, $end_date);

// Get keyword performance
$keywords = $ads->get_keyword_performance($client_id, $campaign_id);

// Get cost and conversion data
$cost_data = $ads->get_cost_data($client_id, $start_date, $end_date);
```

### Search Console Integration

```php
use FP\DigitalMarketing\Analytics\SearchConsole;

// Initialize Search Console client
$gsc = new SearchConsole();

// Get search performance
$performance = $gsc->get_search_performance($client_id, [
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'dimensions' => ['query', 'page']
]);

// Get top queries
$top_queries = $gsc->get_top_queries($client_id, $start_date, $end_date, 10);

// Get indexing status
$indexing = $gsc->get_indexing_status($client_id);
```

## Client Management API

### Client Operations

```php
use FP\DigitalMarketing\Client\ClientManager;

// Create new client
$client_id = ClientManager::create_client([
    'name' => 'Example Company',
    'email' => 'contact@example.com',
    'website' => 'https://example.com',
    'industry' => 'Technology'
]);

// Get client data
$client = ClientManager::get_client($client_id);

// Update client
ClientManager::update_client($client_id, [
    'name' => 'Updated Company Name'
]);

// Delete client
ClientManager::delete_client($client_id);

// Get all clients
$clients = ClientManager::get_all_clients();

// Search clients
$results = ClientManager::search_clients('technology');
```

### Client Metadata

```php
// Set client metadata
ClientManager::set_client_meta($client_id, 'ga4_measurement_id', 'G-XXXXXXXXXX');

// Get client metadata
$ga4_id = ClientManager::get_client_meta($client_id, 'ga4_measurement_id');

// Delete client metadata
ClientManager::delete_client_meta($client_id, 'old_meta_key');
```

## SEO Tools API

### XML Sitemap Generation

```php
use FP\DigitalMarketing\SEO\XMLSitemap;

// Generate sitemap for client
$sitemap = new XMLSitemap();
$xml_content = $sitemap->generate_sitemap($client_id);

// Add custom URLs to sitemap
$sitemap->add_custom_url('https://example.com/custom-page', '2024-01-01', 'monthly', 0.8);

// Get sitemap URL
$sitemap_url = $sitemap->get_sitemap_url($client_id);
```

### Schema Markup

```php
use FP\DigitalMarketing\SEO\SchemaMarkup;

// Generate organization schema
$schema = new SchemaMarkup();
$org_schema = $schema->generate_organization_schema($client_id);

// Generate local business schema
$business_schema = $schema->generate_local_business_schema($client_id, [
    'address' => '123 Main St, City, State 12345',
    'phone' => '+1-555-123-4567',
    'hours' => 'Mo-Fr 09:00-17:00'
]);

// Add custom schema
$schema->add_custom_schema('Product', [
    'name' => 'Product Name',
    'description' => 'Product description',
    'price' => '99.99',
    'currency' => 'USD'
]);
```

### Meta Management

```php
use FP\DigitalMarketing\SEO\MetaManager;

// Set page meta tags
MetaManager::set_meta_tags($post_id, [
    'title' => 'Custom Page Title',
    'description' => 'Custom meta description',
    'keywords' => 'keyword1, keyword2, keyword3'
]);

// Get page meta tags
$meta = MetaManager::get_meta_tags($post_id);

// Generate Open Graph tags
$og_tags = MetaManager::generate_og_tags($post_id);
```

## Marketing Automation API

### UTM Campaign Manager

```php
use FP\DigitalMarketing\Marketing\UTMCampaignManager;

// Create UTM campaign
$campaign_id = UTMCampaignManager::create_campaign([
    'name' => 'Summer Sale 2024',
    'source' => 'email',
    'medium' => 'newsletter',
    'campaign' => 'summer_sale_2024',
    'client_id' => 123
]);

// Generate UTM URL
$utm_url = UTMCampaignManager::generate_utm_url(
    'https://example.com/product',
    'email',
    'newsletter',
    'summer_sale_2024'
);

// Track UTM performance
$performance = UTMCampaignManager::get_campaign_performance($campaign_id);
```

### Conversion Tracking

```php
use FP\DigitalMarketing\Marketing\ConversionTracker;

// Track conversion event
ConversionTracker::track_conversion([
    'client_id' => 123,
    'event_name' => 'purchase',
    'value' => 99.99,
    'currency' => 'USD',
    'user_id' => get_current_user_id(),
    'session_id' => session_id()
]);

// Get conversion data
$conversions = ConversionTracker::get_conversions($client_id, $start_date, $end_date);

// Get conversion funnel
$funnel = ConversionTracker::get_conversion_funnel($client_id);
```

## Hooks and Filters

### Action Hooks

```php
// Plugin initialization
do_action('fp_digital_marketing_init');

// Client created
do_action('fp_digital_marketing_client_created', $client_id, $client_data);

// Client updated
do_action('fp_digital_marketing_client_updated', $client_id, $updated_data);

// Client deleted
do_action('fp_digital_marketing_client_deleted', $client_id);

// Analytics data synced
do_action('fp_digital_marketing_analytics_synced', $client_id, $data_type, $sync_result);

// Conversion tracked
do_action('fp_digital_marketing_conversion_tracked', $conversion_data);

// Settings updated
do_action('fp_digital_marketing_settings_updated', $setting_key, $old_value, $new_value);
```

### Filter Hooks

```php
// Modify client data before save
$client_data = apply_filters('fp_digital_marketing_client_data', $client_data, $client_id);

// Modify analytics data before processing
$analytics_data = apply_filters('fp_digital_marketing_analytics_data', $analytics_data, $client_id);

// Modify sitemap URLs
$sitemap_urls = apply_filters('fp_digital_marketing_sitemap_urls', $sitemap_urls, $client_id);

// Modify schema markup
$schema_markup = apply_filters('fp_digital_marketing_schema_markup', $schema_markup, $schema_type, $client_id);

// Modify UTM parameters
$utm_params = apply_filters('fp_digital_marketing_utm_params', $utm_params, $campaign_id);

// Modify conversion data
$conversion_data = apply_filters('fp_digital_marketing_conversion_data', $conversion_data, $client_id);

// Modify dashboard widgets
$widgets = apply_filters('fp_digital_marketing_dashboard_widgets', $widgets, $client_id);
```

### Custom Hook Examples

```php
// Add custom client validation
add_filter('fp_digital_marketing_client_data', function($client_data, $client_id) {
    // Custom validation logic
    if (empty($client_data['website'])) {
        $client_data['website'] = 'https://example.com';
    }
    return $client_data;
}, 10, 2);

// Add custom analytics processing
add_action('fp_digital_marketing_analytics_synced', function($client_id, $data_type, $sync_result) {
    // Custom processing after analytics sync
    if ($data_type === 'ga4' && $sync_result['success']) {
        // Send notification, update cache, etc.
    }
}, 10, 3);
```

## Database Schema

### Table Structure

```sql
-- Client data table
CREATE TABLE wp_fp_dms_clients (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    email varchar(255) NOT NULL,
    website varchar(255),
    industry varchar(100),
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email (email),
    KEY idx_industry (industry)
);

-- Analytics data table
CREATE TABLE wp_fp_dms_analytics_data (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint(20) unsigned NOT NULL,
    source_type varchar(50) NOT NULL,
    metric_name varchar(100) NOT NULL,
    metric_value decimal(15,4) NOT NULL,
    date_recorded date NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client_date (client_id, date_recorded),
    KEY idx_source_metric (source_type, metric_name),
    FOREIGN KEY (client_id) REFERENCES wp_fp_dms_clients(id) ON DELETE CASCADE
);

-- Metrics table
CREATE TABLE wp_fp_dms_metrics (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint(20) unsigned NOT NULL,
    kpi varchar(100) NOT NULL,
    value decimal(15,4) NOT NULL,
    source varchar(50) NOT NULL,
    source_type varchar(50) NOT NULL,
    category varchar(50) NOT NULL,
    date_recorded datetime NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client_kpi_date (client_id, kpi, date_recorded),
    KEY idx_source_type (source_type),
    KEY idx_category (category),
    FOREIGN KEY (client_id) REFERENCES wp_fp_dms_clients(id) ON DELETE CASCADE
);
```

### Direct Database Access

```php
global $wpdb;

// Get custom table name
$table_name = $wpdb->prefix . 'fp_dms_clients';

// Query clients
$clients = $wpdb->get_results("SELECT * FROM {$table_name} WHERE industry = 'Technology'");

// Insert new client
$wpdb->insert(
    $table_name,
    [
        'name' => 'New Client',
        'email' => 'new@example.com',
        'website' => 'https://newclient.com'
    ],
    ['%s', '%s', '%s']
);
```

## Examples

### Custom Analytics Integration

```php
// Add custom analytics source
class CustomAnalytics {
    
    public function __construct() {
        add_action('fp_digital_marketing_sync_analytics', [$this, 'sync_custom_data']);
        add_filter('fp_digital_marketing_analytics_sources', [$this, 'add_custom_source']);
    }
    
    public function sync_custom_data($client_id) {
        // Fetch data from custom analytics API
        $data = $this->fetch_custom_analytics_data($client_id);
        
        // Store data using plugin's metrics system
        foreach ($data as $metric) {
            MetricsAggregator::store_metric([
                'client_id' => $client_id,
                'kpi' => $metric['name'],
                'value' => $metric['value'],
                'source' => 'custom_analytics',
                'source_type' => 'analytics',
                'category' => 'traffic'
            ]);
        }
    }
    
    public function add_custom_source($sources) {
        $sources['custom_analytics'] = 'Custom Analytics';
        return $sources;
    }
}

new CustomAnalytics();
```

### Custom Dashboard Widget

```php
// Add custom dashboard widget
add_filter('fp_digital_marketing_dashboard_widgets', function($widgets, $client_id) {
    $widgets['custom_widget'] = [
        'title' => 'Custom Metrics',
        'callback' => 'render_custom_widget',
        'priority' => 10
    ];
    return $widgets;
}, 10, 2);

function render_custom_widget($client_id) {
    // Get custom data
    $custom_data = get_custom_metrics($client_id);
    
    // Render widget HTML
    echo '<div class="custom-widget">';
    echo '<h3>Custom Metrics</h3>';
    foreach ($custom_data as $metric => $value) {
        echo "<p><strong>{$metric}:</strong> {$value}</p>";
    }
    echo '</div>';
}
```

### Custom Conversion Tracking

```php
// Track custom conversion events
add_action('woocommerce_order_completed', function($order_id) {
    $order = wc_get_order($order_id);
    
    // Track conversion using plugin's system
    ConversionTracker::track_conversion([
        'client_id' => get_option('fp_dms_default_client_id'),
        'event_name' => 'woocommerce_purchase',
        'value' => $order->get_total(),
        'currency' => $order->get_currency(),
        'order_id' => $order_id,
        'products' => $order->get_items()
    ]);
});
```

## Best Practices

### Performance

1. **Use caching** for expensive operations
2. **Batch database operations** when possible
3. **Implement pagination** for large data sets
4. **Use WordPress transients** for temporary data
5. **Optimize database queries** with proper indexes

### Security

1. **Sanitize all inputs** using WordPress functions
2. **Escape all outputs** using WordPress functions
3. **Use nonces** for form submissions
4. **Implement capability checks** for admin functions
5. **Validate API credentials** before storage

### Code Quality

1. **Follow WordPress coding standards**
2. **Use proper error handling**
3. **Write comprehensive docblocks**
4. **Implement unit tests**
5. **Use type hints** for PHP 7.4+ compatibility

### Integration

1. **Use plugin hooks** instead of modifying core files
2. **Check for plugin dependencies** before executing code
3. **Handle API failures gracefully**
4. **Implement proper logging** for debugging
5. **Follow WordPress plugin development guidelines**

## Support

For technical support and questions:

- **GitHub Issues**: [Report bugs and feature requests](https://github.com/franpass87/FP-Digital-Marketing-Suite/issues)
- **Email**: info@francescopasseri.com
- **Documentation**: Check the plugin's documentation files

## Version Compatibility

- **Minimum WordPress**: 5.0
- **Minimum PHP**: 7.4
- **Tested up to WordPress**: 6.4
- **Tested up to PHP**: 8.2

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on contributing to the plugin's development.

---

This API documentation is maintained alongside the plugin codebase. For the most up-to-date information, always refer to the latest version of this document.
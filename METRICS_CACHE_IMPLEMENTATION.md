# Task 5: Metrics Cache Table Implementation

## Overview

This implementation provides a structured caching system for normalized metrics data from various data sources. The solution includes a custom database table (`wp_fp_metrics_cache`) with comprehensive CRUD operations and PHPUnit tests.

## Implementation

### 1. Database Table Structure

**Table Name:** `wp_fp_metrics_cache`

**Schema:**
```sql
CREATE TABLE wp_fp_metrics_cache (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint(20) unsigned NOT NULL,
    source varchar(50) NOT NULL,
    metric varchar(100) NOT NULL,
    period_start datetime NOT NULL,
    period_end datetime NOT NULL,
    value text NOT NULL,
    meta longtext,
    fetched_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY client_id (client_id),
    KEY source (source),
    KEY metric (metric),
    KEY period_start (period_start),
    KEY period_end (period_end),
    KEY fetched_at (fetched_at)
);
```

**Key Features:**
- Optimized indexes for common query patterns
- Support for JSON metadata in the `meta` field
- Automatic timestamp tracking with `fetched_at`
- Flexible value storage as text to accommodate various metric types

### 2. File Structure

```
src/
├── Database/
│   └── MetricsCacheTable.php    # Table creation and management
├── Models/
│   └── MetricsCache.php         # CRUD operations
└── DigitalMarketingSuite.php    # Updated to initialize metrics cache

tests/
├── MetricsCacheTest.php             # Unit tests
├── MetricsCacheIntegrationTest.php  # Integration tests
└── bootstrap.php                    # Test environment setup
```

### 3. Database Management Class (`MetricsCacheTable`)

**Key Methods:**
- `create_table()` - Creates the metrics cache table
- `drop_table()` - Removes the table (for cleanup)
- `table_exists()` - Checks if table exists
- `get_table_name()` - Returns full table name with WordPress prefix

**Usage:**
```php
use FP\DigitalMarketing\Database\MetricsCacheTable;

// Create table (called automatically on plugin activation)
MetricsCacheTable::create_table();

// Check if table exists
if (MetricsCacheTable::table_exists()) {
    // Table is ready
}
```

### 4. CRUD Operations Class (`MetricsCache`)

**Core Methods:**

#### Save Metrics
```php
$id = MetricsCache::save(
    $client_id,      // int: Client ID from cliente post type
    $source,         // string: Data source (e.g., 'google_analytics_4')
    $metric,         // string: Metric name (e.g., 'sessions')
    $period_start,   // string: Start date (Y-m-d H:i:s)
    $period_end,     // string: End date (Y-m-d H:i:s)
    $value,          // mixed: Metric value
    $meta           // array: Optional metadata
);
```

#### Retrieve Metrics
```php
// Get single metric by ID
$metric = MetricsCache::get($id);

// Get multiple metrics with filters
$metrics = MetricsCache::get_metrics([
    'client_id' => 123,
    'source' => 'google_analytics_4',
    'metric' => 'sessions',
    'period_start' => '2024-01-01 00:00:00',
    'limit' => 50
]);
```

#### Update Metrics
```php
$success = MetricsCache::update($id, [
    'value' => '1800',
    'meta' => ['device' => 'mobile']
]);
```

#### Delete Metrics
```php
// Delete single metric
$success = MetricsCache::delete($id);

// Delete by criteria
$deleted_count = MetricsCache::delete_by_criteria([
    'client_id' => 123,
    'source' => 'google_analytics_4'
]);
```

#### Count Metrics
```php
$count = MetricsCache::count([
    'client_id' => 123,
    'source' => 'google_analytics_4'
]);
```

### 5. Plugin Integration

**Activation Hook:** Table is automatically created when the plugin is activated.

**Main Class Integration:** The `DigitalMarketingSuite` class includes a safety check to ensure the table exists on initialization.

```php
// In DigitalMarketingSuite::init()
$this->ensure_metrics_cache_table();
```

### 6. Usage Examples

#### Saving Google Analytics Data
```php
use FP\DigitalMarketing\Models\MetricsCache;

// Save session data
$id = MetricsCache::save(
    123,                           // client_id
    'google_analytics_4',          // source
    'sessions',                    // metric
    '2024-01-01 00:00:00',        // period_start
    '2024-01-31 23:59:59',        // period_end
    '1500',                       // value
    [                             // meta
        'device' => 'desktop',
        'region' => 'Italy',
        'page_category' => 'product'
    ]
);
```

#### Retrieving Campaign Performance
```php
// Get all metrics for a client from Facebook Ads
$facebook_metrics = MetricsCache::get_metrics([
    'client_id' => 456,
    'source' => 'facebook_ads',
    'period_start' => '2024-02-01 00:00:00',
    'period_end' => '2024-02-29 23:59:59'
]);

foreach ($facebook_metrics as $metric) {
    echo "Metric: {$metric->metric}, Value: {$metric->value}\n";
}
```

#### Bulk Data Management
```php
// Clean old cache data for a client
MetricsCache::delete_by_criteria([
    'client_id' => 789,
    'source' => 'google_search_console'
]);

// Check current cache size
$total_metrics = MetricsCache::count();
echo "Total cached metrics: $total_metrics\n";
```

### 7. Testing

**Test Coverage:**
- Unit tests for all CRUD operations
- Integration tests demonstrating real-world workflows
- Mock WordPress environment for testing without full WP setup

**Running Tests:**
```bash
# Run specific test
composer test tests/MetricsCacheTest.php

# Run all tests
composer test

# Run with coverage
composer test-coverage
```

### 8. Security Features

- **Input Sanitization:** All inputs are sanitized using WordPress functions
- **SQL Injection Prevention:** Uses WordPress `$wpdb->prepare()` for all queries
- **Data Validation:** Proper type checking and validation
- **Limited Update Fields:** Only specific fields can be updated to prevent data corruption

### 9. Performance Considerations

- **Indexes:** Strategic indexes on commonly queried fields
- **Pagination:** Built-in limit/offset support for large datasets
- **Optimized Queries:** Efficient SQL generation with proper WHERE clauses
- **JSON Metadata:** Flexible metadata storage without additional tables

## Future Enhancements

1. **Automatic Cache Expiration:** Add TTL functionality
2. **Data Aggregation:** Built-in methods for metric aggregation
3. **Cache Warming:** Background processes to pre-populate cache
4. **API Integration:** Direct connection to data source APIs
5. **Real-time Updates:** WebSocket support for live metric updates

## Acceptance Criteria Compliance

✅ **Table created on activation**
- Custom table `wp_fp_metrics_cache` created via activation hook
- Safety check in main class initialization

✅ **CRUD functionality working**
- Complete Create, Read, Update, Delete operations
- Advanced filtering and pagination support
- Bulk operations for data management

✅ **PHPUnit tests for saving and retrieval**
- Comprehensive unit tests covering all CRUD operations
- Integration tests demonstrating practical usage scenarios
- Mock environment for testing without WordPress dependency

**Output:** Metrics cache table ready for storing normalized data from various data sources, with full CRUD functionality and comprehensive test coverage.
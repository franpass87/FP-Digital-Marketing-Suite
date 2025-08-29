# Metrics Query API Documentation

## Overview

The Metrics Query API provides a comprehensive set of methods for querying, filtering, and analyzing marketing metrics across multiple data sources. This API is designed for dashboard creation, reporting systems, and data analysis workflows.

## Table of Contents

1. [Core Query Methods](#core-query-methods)
2. [Advanced Filtering](#advanced-filtering)
3. [Query Parameters](#query-parameters)
4. [Response Formats](#response-formats)
5. [Examples](#examples)
6. [Dashboard Integration](#dashboard-integration)
7. [Reporting Workflows](#reporting-workflows)

## Core Query Methods

### 1. `query_metrics()` - Advanced Query API

The primary method for advanced metric queries with comprehensive filtering capabilities.

```php
$result = MetricsAggregator::query_metrics([
    'client_id' => 123,
    'period_start' => '2024-01-01 00:00:00',
    'period_end' => '2024-01-31 23:59:59',
    'kpis' => ['sessions', 'users', 'revenue'],
    'sources' => ['google_analytics_4', 'facebook_ads'],
    'source_types' => ['analytics', 'advertising'],
    'categories' => ['traffic', 'conversions'],
    'aggregation' => 'sum',
    'include_trends' => true,
    'limit' => 50,
    'offset' => 0,
    'sort_by' => 'value',
    'sort_order' => 'desc'
]);
```

**Response Structure:**
```php
[
    'query_params' => [...],  // Original query parameters
    'results' => [...],       // Filtered and sorted metrics
    'metadata' => [
        'total_results' => 15,
        'query_time' => '2024-01-15 10:30:00',
        'has_pagination' => true,
        'offset' => 0,
        'limit' => 50
    ]
]
```

### 2. `get_aggregated_metrics()` - Basic Aggregation

Get aggregated metrics for a client across all sources.

```php
$metrics = MetricsAggregator::get_aggregated_metrics(
    $client_id, 
    '2024-01-01 00:00:00', 
    '2024-01-31 23:59:59',
    $kpis = [],      // Optional: specific KPIs
    $sources = []    // Optional: specific sources
);
```

### 3. `get_kpi_summary()` - Category-Based Summary

Get KPI summary with optional category filtering.

```php
$summary = MetricsAggregator::get_kpi_summary(
    $client_id, 
    $period_start, 
    $period_end,
    MetricsSchema::CATEGORY_TRAFFIC  // Optional category filter
);
```

### 4. `get_period_comparison()` - Period-to-Period Analysis

Compare metrics between two time periods.

```php
$comparison = MetricsAggregator::get_period_comparison(
    $client_id,
    $current_start, $current_end,
    $previous_start, $previous_end,
    $kpis = []  // Optional: specific KPIs to compare
);
```

### 5. `get_metrics_by_type()` - Type-Based Filtering

Get metrics filtered by metric types (traffic, conversion, engagement, etc.).

```php
$metrics = MetricsAggregator::get_metrics_by_type(
    $client_id,
    $period_start,
    $period_end,
    ['traffic', 'conversion', 'engagement']
);
```

### 6. `get_metrics_by_source_type()` - Source Type Filtering

Get metrics from specific source types (analytics, advertising, social, etc.).

```php
$metrics = MetricsAggregator::get_metrics_by_source_type(
    $client_id,
    $period_start,
    $period_end,
    ['analytics', 'advertising']
);
```

### 7. `get_trending_metrics()` - Trend Analysis

Get metrics with trend analysis and growth patterns.

```php
$trending = MetricsAggregator::get_trending_metrics(
    $client_id,
    $period_start,
    $period_end,
    $trend_periods = 4  // Number of periods to analyze
);
```

### 8. `search_metrics()` - Text-Based Search

Search metrics by name or description.

```php
$results = MetricsAggregator::search_metrics(
    $client_id,
    $period_start,
    $period_end,
    'conversion'  // Search term
);
```

### 9. `get_data_quality_report()` - Data Quality Assessment

Get comprehensive data quality and coverage report.

```php
$quality = MetricsAggregator::get_data_quality_report(
    $client_id, 
    $period_start, 
    $period_end
);
```

## Advanced Filtering

### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `client_id` | int | Client identifier | `123` |
| `period_start` | string | Start date (Y-m-d H:i:s) | `'2024-01-01 00:00:00'` |
| `period_end` | string | End date (Y-m-d H:i:s) | `'2024-01-31 23:59:59'` |
| `kpis` | array | Specific KPIs to retrieve | `['sessions', 'users']` |
| `sources` | array | Specific data sources | `['google_analytics_4']` |
| `source_types` | array | Filter by source types | `['analytics', 'advertising']` |
| `categories` | array | Filter by KPI categories | `['traffic', 'conversions']` |
| `metric_types` | array | Filter by metric types | `['traffic', 'engagement']` |
| `aggregation` | string | Aggregation method | `'sum', 'average', 'max', 'min'` |
| `include_trends` | bool | Include trend analysis | `true` |
| `limit` | int | Result limit for pagination | `50` |
| `offset` | int | Result offset for pagination | `0` |
| `sort_by` | string | Sort field | `'value', 'name', 'change'` |
| `sort_order` | string | Sort order | `'asc', 'desc'` |

### Source Types

- `'analytics'` - Web analytics (Google Analytics, etc.)
- `'advertising'` - Paid advertising (Google Ads, Facebook Ads)
- `'social'` - Social media platforms
- `'search'` - SEO and search data (Search Console)
- `'email'` - Email marketing platforms

### Metric Types / Categories

- `'traffic'` - Website traffic metrics
- `'engagement'` - User engagement metrics
- `'conversion'` - Conversion and revenue metrics
- `'advertising'` - Advertising performance metrics
- `'search'` - Organic search metrics
- `'email'` - Email marketing metrics

### Aggregation Methods

- `'sum'` - Sum all values (default)
- `'average'` - Calculate average
- `'max'` - Maximum value
- `'min'` - Minimum value

## Response Formats

### Standard Metric Response

```php
[
    'kpi' => 'sessions',
    'values' => [1500, 1200, 1800],
    'sources' => ['google_analytics_4'],
    'total_value' => 4500,
    'count' => 3
]
```

### KPI Summary Response

```php
[
    'sessions' => [
        'name' => 'Sessioni',
        'description' => 'Numero di sessioni utente',
        'category' => 'traffic',
        'value' => 4500,
        'formatted_value' => '4,500',
        'sources' => ['google_analytics_4'],
        'source_count' => 1,
        'has_data' => true
    ]
]
```

### Period Comparison Response

```php
[
    'sessions' => [
        'kpi' => 'sessions',
        'current_value' => 4500,
        'previous_value' => 3800,
        'change' => 700,
        'change_percentage' => 18.42,
        'trend' => 'up',
        'has_data' => true
    ]
]
```

### Trending Metrics Response

```php
[
    'sessions' => [
        'kpi' => 'sessions',
        'total_value' => 4500,
        'trend' => [
            'direction' => 'up',
            'velocity' => 125.5,
            'historical_values' => [3000, 3500, 4000, 4500],
            'periods_analyzed' => 4
        ]
    ]
]
```

## Examples

### Dashboard Overview Query

```php
// Get key metrics for dashboard overview
$overview = MetricsAggregator::query_metrics([
    'client_id' => 123,
    'period_start' => '2024-01-01 00:00:00',
    'period_end' => '2024-01-31 23:59:59',
    'kpis' => [
        MetricsSchema::KPI_SESSIONS,
        MetricsSchema::KPI_USERS,
        MetricsSchema::KPI_REVENUE,
        MetricsSchema::KPI_CONVERSIONS
    ],
    'include_trends' => true,
    'sort_by' => 'value',
    'sort_order' => 'desc'
]);

echo "Sessions: " . $overview['results']['sessions']['total_value'];
echo "Trend: " . $overview['results']['sessions']['trend_analysis']['trend'];
```

### Traffic Analysis Report

```php
// Get comprehensive traffic metrics
$traffic_report = MetricsAggregator::get_metrics_by_type(
    123,
    '2024-01-01 00:00:00',
    '2024-01-31 23:59:59',
    ['traffic', 'engagement']
);

foreach ($traffic_report as $kpi => $data) {
    echo "{$kpi}: {$data['total_value']} from " . count($data['sources']) . " sources\n";
}
```

### Advertising Performance Comparison

```php
// Compare current vs previous month for advertising metrics
$ad_comparison = MetricsAggregator::get_period_comparison(
    123,
    '2024-02-01 00:00:00', '2024-02-29 23:59:59',  // Current month
    '2024-01-01 00:00:00', '2024-01-31 23:59:59',  // Previous month
    [
        MetricsSchema::KPI_IMPRESSIONS,
        MetricsSchema::KPI_CLICKS,
        MetricsSchema::KPI_COST,
        MetricsSchema::KPI_CONVERSIONS
    ]
);

foreach ($ad_comparison as $kpi => $comparison) {
    echo "{$kpi}: {$comparison['change_percentage']}% change\n";
}
```

### Multi-Source Analysis

```php
// Analyze metrics from specific sources
$multi_source = MetricsAggregator::query_metrics([
    'client_id' => 123,
    'period_start' => '2024-01-01 00:00:00',
    'period_end' => '2024-01-31 23:59:59',
    'sources' => ['google_analytics_4', 'facebook_ads', 'google_ads'],
    'aggregation' => 'sum',
    'sort_by' => 'value'
]);

$by_source = MetricsAggregator::get_metrics_by_source(
    123, 
    '2024-01-01 00:00:00', 
    '2024-01-31 23:59:59'
);
```

### Search and Filter Workflow

```php
// Search for conversion-related metrics
$conversion_metrics = MetricsAggregator::search_metrics(
    123,
    '2024-01-01 00:00:00',
    '2024-01-31 23:59:59',
    'conversion'
);

// Get detailed conversion analysis
$conversion_analysis = MetricsAggregator::get_metrics_by_type(
    123,
    '2024-01-01 00:00:00',
    '2024-01-31 23:59:59',
    ['conversion']
);
```

### Trending Analysis for Strategy

```php
// Get 6-month trending data for strategic analysis
$strategic_trends = MetricsAggregator::get_trending_metrics(
    123,
    '2024-01-01 00:00:00',
    '2024-06-30 23:59:59',
    6  // Analyze 6 periods
);

foreach ($strategic_trends as $kpi => $data) {
    $trend = $data['trend'];
    echo "{$kpi}: {$trend['direction']} trend with velocity {$trend['velocity']}\n";
}
```

## Dashboard Integration

### Real-Time Dashboard Query

```php
// Optimized query for real-time dashboard
function getDashboardMetrics($client_id) {
    $current_month_start = date('Y-m-01 00:00:00');
    $current_month_end = date('Y-m-t 23:59:59');
    
    return MetricsAggregator::query_metrics([
        'client_id' => $client_id,
        'period_start' => $current_month_start,
        'period_end' => $current_month_end,
        'kpis' => [
            MetricsSchema::KPI_SESSIONS,
            MetricsSchema::KPI_USERS,
            MetricsSchema::KPI_REVENUE,
            MetricsSchema::KPI_CONVERSIONS,
            MetricsSchema::KPI_CTR
        ],
        'include_trends' => true,
        'sort_by' => 'value',
        'limit' => 10
    ]);
}
```

### Widget-Specific Queries

```php
// Traffic widget
function getTrafficWidget($client_id, $period_days = 30) {
    $period_start = date('Y-m-d H:i:s', strtotime("-{$period_days} days"));
    $period_end = date('Y-m-d H:i:s');
    
    return MetricsAggregator::get_metrics_by_type(
        $client_id,
        $period_start,
        $period_end,
        ['traffic']
    );
}

// Conversion funnel widget
function getConversionFunnel($client_id) {
    return MetricsAggregator::query_metrics([
        'client_id' => $client_id,
        'period_start' => date('Y-m-01 00:00:00'),
        'period_end' => date('Y-m-t 23:59:59'),
        'categories' => [MetricsSchema::CATEGORY_CONVERSIONS],
        'sort_by' => 'value',
        'sort_order' => 'desc'
    ]);
}
```

## Reporting Workflows

### Monthly Report Generation

```php
function generateMonthlyReport($client_id, $year, $month) {
    $period_start = sprintf('%d-%02d-01 00:00:00', $year, $month);
    $period_end = date('Y-m-t 23:59:59', strtotime($period_start));
    
    // Get all metrics for the month
    $monthly_metrics = MetricsAggregator::get_aggregated_metrics(
        $client_id,
        $period_start,
        $period_end
    );
    
    // Get comparison with previous month
    $prev_month = date('Y-m-d H:i:s', strtotime($period_start . ' -1 month'));
    $prev_month_end = date('Y-m-t 23:59:59', strtotime($prev_month));
    
    $comparison = MetricsAggregator::get_period_comparison(
        $client_id,
        $period_start, $period_end,
        $prev_month, $prev_month_end
    );
    
    // Get data quality report
    $quality_report = MetricsAggregator::get_data_quality_report(
        $client_id,
        $period_start,
        $period_end
    );
    
    return [
        'period' => ['start' => $period_start, 'end' => $period_end],
        'metrics' => $monthly_metrics,
        'comparison' => $comparison,
        'quality' => $quality_report
    ];
}
```

### Performance Analysis Report

```php
function generatePerformanceAnalysis($client_id, $months = 6) {
    $period_end = date('Y-m-d H:i:s');
    $period_start = date('Y-m-d H:i:s', strtotime("-{$months} months"));
    
    // Get trending analysis
    $trends = MetricsAggregator::get_trending_metrics(
        $client_id,
        $period_start,
        $period_end,
        $months
    );
    
    // Get source performance
    $source_performance = MetricsAggregator::get_source_availability(
        $client_id,
        $period_start,
        $period_end
    );
    
    // Get top performing metrics
    $top_metrics = MetricsAggregator::query_metrics([
        'client_id' => $client_id,
        'period_start' => $period_start,
        'period_end' => $period_end,
        'sort_by' => 'value',
        'sort_order' => 'desc',
        'limit' => 20
    ]);
    
    return [
        'trends' => $trends,
        'sources' => $source_performance,
        'top_metrics' => $top_metrics
    ];
}
```

## Error Handling

### Query Validation

```php
function validateQueryParams($params) {
    $required = ['client_id', 'period_start', 'period_end'];
    
    foreach ($required as $field) {
        if (!isset($params[$field]) || empty($params[$field])) {
            throw new InvalidArgumentException("Required field '$field' is missing");
        }
    }
    
    // Validate date format
    $start = DateTime::createFromFormat('Y-m-d H:i:s', $params['period_start']);
    $end = DateTime::createFromFormat('Y-m-d H:i:s', $params['period_end']);
    
    if (!$start || !$end) {
        throw new InvalidArgumentException("Invalid date format. Use 'Y-m-d H:i:s'");
    }
    
    if ($start >= $end) {
        throw new InvalidArgumentException("Start date must be before end date");
    }
    
    return true;
}
```

### Graceful Fallbacks

```php
function safeQueryMetrics($params) {
    try {
        validateQueryParams($params);
        return MetricsAggregator::query_metrics($params);
    } catch (Exception $e) {
        error_log("Metrics query error: " . $e->getMessage());
        
        // Return empty result with error info
        return [
            'query_params' => $params,
            'results' => [],
            'metadata' => [
                'total_results' => 0,
                'error' => $e->getMessage(),
                'query_time' => date('Y-m-d H:i:s')
            ]
        ];
    }
}
```

## Performance Optimization

### Caching Strategy

```php
function getCachedMetrics($cache_key, $query_params) {
    // Check cache first
    $cached = wp_cache_get($cache_key, 'metrics_query');
    if ($cached !== false) {
        return $cached;
    }
    
    // Execute query
    $result = MetricsAggregator::query_metrics($query_params);
    
    // Cache for 15 minutes
    wp_cache_set($cache_key, $result, 'metrics_query', 900);
    
    return $result;
}
```

### Batch Queries

```php
function batchMetricsQuery($client_ids, $period_start, $period_end) {
    $results = [];
    
    foreach ($client_ids as $client_id) {
        $results[$client_id] = MetricsAggregator::query_metrics([
            'client_id' => $client_id,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'limit' => 50  // Limit to prevent memory issues
        ]);
    }
    
    return $results;
}
```

This comprehensive API provides powerful querying capabilities for building sophisticated marketing dashboards and reporting systems while maintaining flexibility and performance.
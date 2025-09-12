# Database Schema Documentation

## Overview

The FP Digital Marketing Suite uses 7 custom database tables to store analytics data, client information, SEO metrics, performance data, and campaign tracking. All tables follow WordPress naming conventions with the `wp_` prefix (or custom prefix if configured).

## Table Structure

### 1. fp_clients
**Purpose:** Store client information for agency management

```sql
CREATE TABLE wp_fp_clients (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    company VARCHAR(255),
    website VARCHAR(255),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_email (email),
    KEY idx_status (status),
    KEY idx_created (created_at)
);
```

**Key Fields:**
- `id`: Unique client identifier
- `name`: Client full name
- `email`: Primary contact email (unique)
- `status`: Client account status
- `website`: Client's website URL

**Relationships:**
- Referenced by analytics data, SEO data, and performance metrics
- One-to-many relationship with campaign data

### 2. fp_analytics_data
**Purpose:** Store Google Analytics, Google Ads, and other analytics metrics

```sql
CREATE TABLE wp_fp_analytics_data (
    id INT(11) NOT NULL AUTO_INCREMENT,
    client_id INT(11),
    source ENUM('ga4', 'google_ads', 'search_console', 'clarity') NOT NULL,
    metric_type VARCHAR(100) NOT NULL,
    metric_value TEXT,
    dimensions JSON,
    date_range_start DATE,
    date_range_end DATE,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    KEY idx_client_source (client_id, source),
    KEY idx_metric_type (metric_type),
    KEY idx_date_range (date_range_start, date_range_end),
    KEY idx_recorded (recorded_at)
);
```

**Key Fields:**
- `source`: Data source (GA4, Google Ads, etc.)
- `metric_type`: Type of metric (sessions, pageviews, conversions, etc.)
- `metric_value`: Serialized metric data
- `dimensions`: JSON array of metric dimensions
- `date_range_start/end`: Time period for the data

**Indexes:**
- Composite index on client_id and source for fast filtering
- Date range index for time-based queries
- Metric type index for aggregation queries

### 3. fp_seo_data
**Purpose:** Store SEO metrics, keyword rankings, and Search Console data

```sql
CREATE TABLE wp_fp_seo_data (
    id INT(11) NOT NULL AUTO_INCREMENT,
    client_id INT(11),
    page_url VARCHAR(512) NOT NULL,
    keyword VARCHAR(255),
    position INT(11),
    clicks INT(11) DEFAULT 0,
    impressions INT(11) DEFAULT 0,
    ctr DECIMAL(5,2) DEFAULT 0.00,
    meta_title VARCHAR(512),
    meta_description TEXT,
    h1_tags JSON,
    schema_markup JSON,
    recorded_date DATE,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    KEY idx_client_page (client_id, page_url(255)),
    KEY idx_keyword (keyword),
    KEY idx_position (position),
    KEY idx_recorded_date (recorded_date),
    KEY idx_ctr (ctr)
);
```

**Key Fields:**
- `page_url`: Full URL of the page
- `keyword`: Target keyword for ranking
- `position`: Search engine ranking position
- `clicks/impressions/ctr`: Search Console metrics
- `schema_markup`: JSON-encoded schema data

**Indexes:**
- URL-based indexing for page performance analysis
- Keyword indexing for ranking tracking
- CTR indexing for performance optimization

### 4. fp_alerts
**Purpose:** Store system alerts and notifications

```sql
CREATE TABLE wp_fp_alerts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    client_id INT(11),
    alert_type ENUM('performance', 'seo', 'analytics', 'security', 'system') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,
    status ENUM('active', 'acknowledged', 'resolved') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    KEY idx_client_type (client_id, alert_type),
    KEY idx_severity (severity),
    KEY idx_status (status),
    KEY idx_created (created_at)
);
```

**Key Fields:**
- `alert_type`: Category of alert
- `severity`: Alert importance level
- `data`: JSON-encoded alert details
- `status`: Current alert status

**Indexes:**
- Client and type composite index for filtering
- Severity index for priority handling
- Status index for active alert queries

### 5. fp_performance_metrics
**Purpose:** Store Core Web Vitals and performance data

```sql
CREATE TABLE wp_fp_performance_metrics (
    id INT(11) NOT NULL AUTO_INCREMENT,
    client_id INT(11),
    page_url VARCHAR(512) NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,3),
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    connection_type VARCHAR(50),
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    KEY idx_client_page (client_id, page_url(255)),
    KEY idx_metric (metric_name),
    KEY idx_device (device_type),
    KEY idx_timestamp (timestamp)
);
```

**Key Fields:**
- `metric_name`: Performance metric (LCP, FID, CLS, etc.)
- `metric_value`: Numeric metric value
- `device_type`: Device category
- `connection_type`: Network connection info

**Indexes:**
- Page-based indexing for URL performance analysis
- Metric name indexing for specific metric queries
- Device type indexing for device-specific analysis

### 6. fp_utm_campaigns
**Purpose:** Store UTM campaign tracking data

```sql
CREATE TABLE wp_fp_utm_campaigns (
    id INT(11) NOT NULL AUTO_INCREMENT,
    client_id INT(11),
    campaign_name VARCHAR(255) NOT NULL,
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    utm_term VARCHAR(100),
    utm_content VARCHAR(100),
    target_url VARCHAR(512),
    clicks INT(11) DEFAULT 0,
    conversions INT(11) DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    KEY idx_client_campaign (client_id, campaign_name),
    KEY idx_utm_source (utm_source),
    KEY idx_utm_medium (utm_medium),
    KEY idx_status (status),
    KEY idx_created (created_at)
);
```

**Key Fields:**
- `campaign_name`: Human-readable campaign name
- `utm_*`: UTM parameter values
- `clicks/conversions/revenue`: Campaign performance metrics

**Indexes:**
- UTM parameter indexing for campaign analysis
- Status indexing for active campaign queries

### 7. fp_conversion_events
**Purpose:** Store conversion tracking events

```sql
CREATE TABLE wp_fp_conversion_events (
    id INT(11) NOT NULL AUTO_INCREMENT,
    client_id INT(11),
    campaign_id INT(11),
    event_type VARCHAR(100) NOT NULL,
    event_value DECIMAL(10,2),
    user_id VARCHAR(255),
    session_id VARCHAR(255),
    page_url VARCHAR(512),
    referrer VARCHAR(512),
    user_agent TEXT,
    ip_address VARCHAR(45),
    event_data JSON,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES wp_fp_utm_campaigns(id) ON DELETE SET NULL,
    KEY idx_client_event (client_id, event_type),
    KEY idx_campaign (campaign_id),
    KEY idx_user (user_id),
    KEY idx_session (session_id),
    KEY idx_timestamp (timestamp)
);
```

**Key Fields:**
- `event_type`: Type of conversion event
- `event_value`: Monetary value of conversion
- `event_data`: JSON-encoded event details
- `user_id/session_id`: User tracking identifiers

**Indexes:**
- User and session indexing for behavioral analysis
- Campaign indexing for conversion attribution

## Data Relationships

### Entity Relationship Diagram

```
fp_clients (1) -----> (N) fp_analytics_data
    |
    |-----> (N) fp_seo_data
    |
    |-----> (N) fp_alerts
    |
    |-----> (N) fp_performance_metrics
    |
    |-----> (N) fp_utm_campaigns (1) -----> (N) fp_conversion_events
```

### Key Relationships

1. **Client-Centric Design:** All data tables reference `fp_clients` for multi-client support
2. **Campaign Attribution:** Conversion events link to UTM campaigns for attribution tracking
3. **Cascading Deletes:** Client deletion removes all associated data
4. **Nullable Foreign Keys:** Some relationships allow NULL for system-wide data

## Performance Considerations

### Indexing Strategy

1. **Composite Indexes:** Combine frequently queried columns (client_id + date, client_id + source)
2. **Selective Indexes:** Index high-cardinality columns (email, URLs, timestamps)
3. **Covering Indexes:** Include additional columns to avoid table lookups

### Query Optimization

1. **Date Range Queries:** Use DATE indexes for time-based analytics
2. **Aggregation Queries:** Leverage metric_type indexes for sum/avg operations
3. **Client Isolation:** Always filter by client_id first in multi-tenant queries

### Storage Optimization

1. **JSON Fields:** Store complex data structures efficiently
2. **ENUM Types:** Reduce storage for fixed value sets
3. **VARCHAR Sizing:** Right-size string fields based on expected data

## Migration Scripts

### Creating Tables

```php
// Example table creation in WordPress
function fp_create_analytics_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fp_analytics_data';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        client_id INT(11),
        source ENUM('ga4', 'google_ads', 'search_console', 'clarity') NOT NULL,
        metric_type VARCHAR(100) NOT NULL,
        metric_value TEXT,
        dimensions JSON,
        date_range_start DATE,
        date_range_end DATE,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_client_source (client_id, source),
        KEY idx_metric_type (metric_type),
        KEY idx_date_range (date_range_start, date_range_end)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
```

### Data Migration

```php
// Example data migration
function fp_migrate_legacy_data() {
    global $wpdb;
    
    // Migrate from old analytics table
    $old_data = $wpdb->get_results("SELECT * FROM old_analytics_table");
    
    foreach ($old_data as $row) {
        $wpdb->insert(
            $wpdb->prefix . 'fp_analytics_data',
            array(
                'client_id' => $row->client_id,
                'source' => 'ga4',
                'metric_type' => $row->type,
                'metric_value' => $row->value,
                'recorded_at' => $row->created_at
            )
        );
    }
}
```

## Backup and Recovery

### Full Backup

```sql
-- Backup all FP tables
mysqldump -u username -p database_name \
  wp_fp_clients \
  wp_fp_analytics_data \
  wp_fp_seo_data \
  wp_fp_alerts \
  wp_fp_performance_metrics \
  wp_fp_utm_campaigns \
  wp_fp_conversion_events \
  > fp_digital_marketing_backup.sql
```

### Selective Backup

```sql
-- Backup specific client data
SELECT * FROM wp_fp_analytics_data WHERE client_id = 123;
SELECT * FROM wp_fp_seo_data WHERE client_id = 123;
-- etc.
```

## Security Considerations

### Data Protection

1. **PII Handling:** Store minimal personal information
2. **Data Encryption:** Encrypt sensitive fields at application level
3. **Access Control:** Implement proper user permissions
4. **Audit Logging:** Track data access and modifications

### SQL Injection Prevention

1. **Prepared Statements:** Always use $wpdb->prepare()
2. **Input Validation:** Sanitize all user inputs
3. **Parameterized Queries:** Never concatenate user data into SQL

### GDPR Compliance

1. **Data Retention:** Implement automatic data cleanup
2. **Right to Deletion:** Provide data removal capabilities
3. **Data Portability:** Support data export functionality
4. **Consent Tracking:** Log user consent for data processing
# Task 19: Alerting & Notifications - Implementation Documentation

## Overview

This document describes the implementation of the alerting and notification system for the FP Digital Marketing Suite. The system provides threshold-based monitoring of metrics with configurable rules and multi-channel notifications.

## Features Implemented

### 1. Alert Rules Management
- **Rule Definition**: Create rules with metric, condition (>, <, >=, <=, =, !=), and threshold values
- **Client-specific Rules**: Rules are associated with specific clients for targeted monitoring
- **Flexible Conditions**: Support for all common comparison operators
- **Active/Inactive States**: Rules can be enabled or disabled without deletion
- **Notification Settings**: Configurable email and admin notice notifications per rule

### 2. Alert Engine
- **Automatic Evaluation**: Rules are checked after each data sync operation
- **Metric Integration**: Uses MetricsAggregator to get current metric values from last 24 hours
- **Condition Evaluation**: Robust condition checking with proper float comparison handling
- **Trigger Tracking**: Records when rules are triggered and how many times
- **Error Handling**: Graceful error handling with detailed logging

### 3. Notification System
- **Admin Notices**: WordPress admin notices displayed in the dashboard
- **Email Notifications**: Automated email alerts with detailed information
- **Notice Management**: Admin notices can be dismissed and are automatically cleaned up
- **Multi-language Support**: Full Italian localization for all notifications

### 4. Admin Interface
- **Rule Creation**: User-friendly form for creating and editing alert rules
- **Rule Management**: List view with edit, delete, and toggle functionality
- **Enhanced UX**: Auto-suggestions, live previews, and validation
- **Logs Viewer**: Historical view of alert check results and performance
- **Integration**: Seamlessly integrated into existing admin menu structure

### 5. Database Integration
- **Alert Rules Table**: Dedicated table for storing rule definitions
- **Audit Trail**: Tracks rule creation, updates, and trigger history
- **Performance Optimized**: Indexed fields for efficient querying
- **Data Integrity**: Proper foreign key relationships and constraints

## Technical Implementation

### Database Schema

```sql
CREATE TABLE wp_fp_alert_rules (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint(20) unsigned NOT NULL,
    name varchar(255) NOT NULL,
    description text,
    metric varchar(100) NOT NULL,
    condition varchar(10) NOT NULL,
    threshold_value decimal(15,4) NOT NULL,
    notification_email varchar(255),
    notification_admin_notice tinyint(1) NOT NULL DEFAULT 1,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    last_triggered datetime,
    triggered_count int unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY client_id (client_id),
    KEY metric (metric),
    KEY is_active (is_active),
    KEY last_triggered (last_triggered)
);
```

### Core Classes

#### AlertRulesTable
- Database table management (create, drop, exists)
- Follows existing patterns from MetricsCacheTable
- Proper indexing for performance

#### AlertRule (Model)
- CRUD operations for alert rules
- Data validation and sanitization
- Condition operator definitions and validation
- Static methods for retrieving rules by client or status

#### AlertEngine (Helper)
- Rule evaluation logic
- Notification sending (email and admin notices)
- Integration with MetricsAggregator for data retrieval
- Logging and audit trail management
- Admin notice management via WordPress transients

#### AlertingAdmin (Admin)
- WordPress admin interface
- Form handling and validation
- AJAX-enabled notice dismissal
- Tab-based interface (Rules, Logs)
- Enhanced UX with JavaScript interactions

### Integration Points

#### SyncEngine Integration
```php
// Added to SyncEngine::run_sync() after successful sync
if ( $results['errors_count'] === 0 ) {
    try {
        $alert_results = AlertEngine::check_all_rules();
        // Log alert results...
    } catch ( \Exception $e ) {
        error_log( 'Alert Check Error: ' . $e->getMessage() );
    }
}
```

#### MetricsAggregator Integration
- Uses existing MetricsAggregator::get_metrics() method
- Leverages normalized KPI definitions from MetricsSchema
- Supports all standard metrics (sessions, users, revenue, etc.)

#### Admin Menu Integration
- Added as submenu under "Cliente" post type
- Follows WordPress admin standards
- Consistent with existing admin interface styling

### Security Features

#### Data Validation
- Input sanitization using WordPress functions
- Nonce verification for all form submissions
- Capability checks (manage_options required)
- SQL injection prevention with prepared statements

#### Access Control
- Admin-only access to alert management
- AJAX endpoints secured with nonces
- Email validation for notification addresses

#### Error Handling
- Graceful degradation when metrics are unavailable
- Detailed error logging for debugging
- Try-catch blocks around all critical operations

## Usage Examples

### Creating an Alert Rule

```php
// Create a rule to alert when sessions drop below 100
$rule_id = AlertRule::save(
    123,                                    // client_id
    'Low Sessions Alert',                   // name
    'sessions',                             // metric
    AlertRule::CONDITION_LESS_THAN,        // condition
    100,                                    // threshold_value
    'Alert when daily sessions drop below 100', // description
    'admin@example.com',                    // notification_email
    true,                                   // notification_admin_notice
    true                                    // is_active
);
```

### Checking All Rules

```php
$results = AlertEngine::check_all_rules();
// Returns:
// [
//     'checked' => 5,
//     'triggered' => 2,
//     'errors' => 0,
//     'notifications_sent' => 2
// ]
```

### Getting Alert Logs

```php
$logs = AlertEngine::get_alert_logs(20);
foreach ($logs as $log) {
    echo "Checked: {$log['results']['checked']}, Triggered: {$log['results']['triggered']}";
}
```

## Admin Interface

### Rule Management
- **Create Rule**: Form with client selection, metric selection, condition, and threshold
- **Edit Rule**: Inline editing with pre-populated values
- **Delete Rule**: Confirmation dialog for safe deletion
- **Toggle Active**: Quick enable/disable with visual switch
- **Auto-suggestions**: Rule names auto-generated based on selections

### Enhanced UX Features
- **Live Preview**: Shows rule logic as user types
- **Field Validation**: Real-time validation with error highlighting
- **Help Text**: Contextual help for metrics and conditions
- **Keyboard Shortcuts**: Ctrl+S to save, ESC to cancel
- **Auto-refresh**: Logs tab refreshes every 30 seconds

## Notification Examples

### Admin Notice
```
Alert attivato: Low Sessions Alert
Cliente: Acme Corp | Metrica: Sessioni | Valore attuale: 85 < 100
```

### Email Notification
```
Subject: [Your Site] Alert: Low Sessions Alert

Alert attivato per il cliente: Acme Corp

Regola: Low Sessions Alert
Metrica: Sessioni
Valore attuale: 85
Condizione: < 100
Soglia: 100

Data/ora: 2024-01-15 14:30:00

Per maggiori dettagli, accedi al pannello di amministrazione.
```

## Performance Considerations

### Optimization Features
- **Indexed Database Queries**: All queries use proper indexes
- **Batch Processing**: Rules checked in batches during sync
- **Transient Caching**: Admin notices cached for 24 hours
- **Log Rotation**: Automatic cleanup of old logs (keep last 50 entries)
- **Conditional Execution**: Alerts only checked after successful sync

### Resource Usage
- **Memory Efficient**: Minimal memory footprint during rule evaluation
- **Database Optimized**: Efficient queries with proper WHERE clauses
- **Async-Ready**: Can be easily extended for background processing

## Extensibility

### Hooks for Extensions
```php
// Hook for custom notification channels
do_action( 'fp_dms_alert_triggered', $rule, $result );

// Hook for custom rule evaluation
$custom_result = apply_filters( 'fp_dms_evaluate_custom_rule', $result, $rule );

// Hook for notification formatting
$formatted_message = apply_filters( 'fp_dms_format_alert_message', $message, $rule, $result );
```

### Adding Custom Metrics
- Rules work with any metric in MetricsSchema
- New metrics automatically available in rule creation
- Custom formatting supported via MetricsSchema definitions

## Testing

### Unit Tests
- Condition evaluation testing
- Rule validation testing  
- Notification formatting testing
- Admin interface functionality testing

### Integration Tests
- End-to-end rule creation and evaluation
- Email notification delivery testing
- Admin notice display and dismissal
- Database integrity testing

## Future Enhancements

### Potential Improvements
1. **Advanced Conditions**: Support for percentage changes and trend analysis
2. **Rule Templates**: Pre-defined rule templates for common scenarios
3. **Bulk Operations**: Batch creation/editing of rules
4. **Escalation Rules**: Multi-tier alerting with escalation paths
5. **Custom Notification Channels**: SMS, Slack, webhook integrations
6. **Alert Scheduling**: Time-based alert windows and scheduling
7. **Dashboard Widget**: Summary widget for admin dashboard
8. **Alert Grouping**: Group related alerts to reduce notification noise

### Integration Opportunities
1. **Mobile Notifications**: Push notifications via mobile app
2. **Calendar Integration**: Alert scheduling based on business hours
3. **Client Portal**: Client-facing alert dashboard
4. **API Endpoints**: REST API for external alert management
5. **Machine Learning**: Predictive alerting based on trends

## Compliance with Task Requirements

### Requirements Met
✅ **Threshold-based System**: Supports >, <, >=, <=, =, != conditions  
✅ **Rule Definition**: Complete rule management with metric, condition, threshold  
✅ **Variation Monitoring**: Monitors actual metric values vs thresholds  
✅ **Notification System**: Email and admin notice notifications  
✅ **Admin Interface**: Full-featured management interface  
✅ **Integration**: Seamless integration with existing sync engine  
✅ **Italian Localization**: Complete translation support  
✅ **Logging**: Comprehensive audit trail and logging  

### Architecture Benefits
- **Maintainable**: Clean separation of concerns
- **Extensible**: Hook-based architecture for extensions
- **Performant**: Optimized database queries and caching
- **Secure**: Proper validation and access controls
- **User-friendly**: Intuitive admin interface with enhanced UX

This implementation provides a solid foundation for metric-based alerting that can scale with the application's growth while maintaining excellent performance and user experience.
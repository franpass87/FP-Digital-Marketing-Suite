# FP Digital Marketing Suite - Roles & Capabilities Documentation

## Overview

The FP Digital Marketing Suite implements a granular capability management system that provides fine-grained access control to different plugin features. This system enhances security by moving away from the generic `manage_options` capability to specific, purpose-built capabilities.

## Custom Capabilities

The plugin defines 5 custom capabilities:

### 1. `fp_dms_view_dashboard`
- **Purpose**: Access to view dashboard and metrics overview
- **Admin Sections**: Main Dashboard page
- **Default Roles**: Administrator, Editor
- **Description**: Allows users to view analytics data, KPIs, and chart visualizations

### 2. `fp_dms_manage_data_sources`
- **Purpose**: Configure and manage data source connections
- **Admin Sections**: Settings page (API keys, OAuth configurations)
- **Default Roles**: Administrator only
- **Description**: Manage connections to Google Analytics 4, Google Search Console, and other data sources

### 3. `fp_dms_export_reports`
- **Purpose**: Export reports and data in various formats
- **Admin Sections**: Reports page
- **Default Roles**: Administrator, Editor
- **Description**: Generate and download PDF, Excel, and CSV reports

### 4. `fp_dms_manage_alerts`
- **Purpose**: Create, modify and manage alert rules and notifications
- **Admin Sections**: Alerts & Notifications page
- **Default Roles**: Administrator only
- **Description**: Configure automated alerts based on metrics thresholds

### 5. `fp_dms_manage_settings`
- **Purpose**: Access plugin settings and configuration options
- **Admin Sections**: Settings page, Security page, Cache Performance page
- **Default Roles**: Administrator only
- **Description**: Configure global plugin settings, security options, and performance settings

## Default Role Assignments

### Administrator Role
Receives **all** capabilities:
- `fp_dms_view_dashboard`
- `fp_dms_manage_data_sources`
- `fp_dms_export_reports`
- `fp_dms_manage_alerts`
- `fp_dms_manage_settings`

### Editor Role
Receives **limited** capabilities:
- `fp_dms_view_dashboard`
- `fp_dms_export_reports`

### Other Roles
By default, other WordPress roles (Author, Contributor, Subscriber) receive **no** plugin capabilities.

## Admin Page Capability Mapping

| Admin Page | Required Capability | Menu Location |
|------------|-------------------|---------------|
| **Dashboard** | `fp_dms_view_dashboard` | Main Menu → FP DMS |
| **Reports** | `fp_dms_export_reports` | Main Menu → DM Reports |
| **Settings** | `fp_dms_manage_settings` | Settings → FP Digital Marketing |
| **Security** | `fp_dms_manage_settings` | Settings → FP DMS Security |
| **Cache Performance** | `fp_dms_manage_settings` | Submenu under Reports |
| **Alerts & Notifications** | `fp_dms_manage_alerts` | Submenu under Clients |

## API Usage

### Check Current User Capability
```php
use FP\DigitalMarketing\Helpers\Capabilities;

// Check if current user can view dashboard
if ( Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
    // User has permission
}

// Check if current user can manage data sources
if ( Capabilities::current_user_can( Capabilities::MANAGE_DATA_SOURCES ) ) {
    // User can configure API connections
}
```

### Check Specific User Capability
```php
// Check capability for specific user
$user_id = 123;
if ( Capabilities::user_can( Capabilities::EXPORT_REPORTS, 0, $user_id ) ) {
    // User can export reports
}
```

### Get Capability Information
```php
// Get all custom capabilities
$capabilities = Capabilities::get_custom_capabilities();

// Get human-readable capability label
$label = Capabilities::get_capability_label( Capabilities::VIEW_DASHBOARD );
// Returns: "View Dashboard"

// Get capability description
$description = Capabilities::get_capability_description( Capabilities::MANAGE_ALERTS );
// Returns: "Create, modify and manage alert rules and notifications"
```

## Role Management

### Add Capability to Role
```php
// Add view dashboard capability to Author role
Capabilities::add_role_capability( 'author', Capabilities::VIEW_DASHBOARD );
```

### Remove Capability from Role
```php
// Remove export capability from Editor role
Capabilities::remove_role_capability( 'editor', Capabilities::EXPORT_REPORTS );
```

### Get Role Capabilities
```php
// Get all custom capabilities for a role
$editor_caps = Capabilities::get_role_capabilities( 'editor' );
```

## Security Features

### Capability Logging
All capability checks are logged for security monitoring:
- Failed capability attempts are logged with user ID and IP address
- Role capability modifications are logged
- Security events are stored for audit purposes

### Automatic Registration
- Capabilities are automatically registered during plugin activation
- Default role assignments are applied on first activation
- Capabilities are removed during plugin deactivation

### Nonce Verification
All admin actions combine capability checks with nonce verification:
```php
if ( Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS ) && 
     Security::verify_nonce_with_logging( 'settings_action' ) ) {
    // Process secure action
}
```

## Migration from manage_options

The plugin has been updated to replace generic `manage_options` checks with specific capabilities:

| Old Check | New Check | Reason |
|-----------|-----------|---------|
| `current_user_can( 'manage_options' )` in Dashboard | `Capabilities::current_user_can( Capabilities::VIEW_DASHBOARD )` | Allows editors to view dashboard |
| `current_user_can( 'manage_options' )` in Reports | `Capabilities::current_user_can( Capabilities::EXPORT_REPORTS )` | Allows editors to export reports |
| `current_user_can( 'manage_options' )` in Settings | `Capabilities::current_user_can( Capabilities::MANAGE_SETTINGS )` | Restricts settings to admin only |

## Best Practices

### For Developers
1. Always use the capability constants instead of strings
2. Combine capability checks with nonce verification
3. Use the logging-enabled capability check methods
4. Check capabilities at both menu registration and page render

### For Site Administrators
1. Review capability assignments regularly
2. Use the principle of least privilege
3. Monitor security logs for failed capability attempts
4. Test capability changes with non-admin users

### For Plugin Extensions
1. Use the existing capability system for consistency
2. Add new capabilities through the Capabilities helper class
3. Follow the naming convention: `fp_dms_[action]_[resource]`
4. Document new capabilities in this file

## Troubleshooting

### User Cannot Access Feature
1. Check if user's role has required capability
2. Verify capability was registered correctly
3. Check security logs for capability denial events
4. Ensure capability constants are used correctly

### Capability Not Working
1. Verify plugin activation completed successfully
2. Check if `fp_dms_capabilities_registered` option is set to true
3. Manually trigger capability registration if needed
4. Review error logs for any registration failures

### Security Concerns
1. Monitor security logs regularly
2. Investigate repeated capability denial attempts
3. Ensure nonce verification is always used
4. Audit role capability assignments periodically

## Future Enhancements

Potential improvements to the capability system:

1. **UI for Role Management**: WordPress admin interface for capability assignment
2. **Capability Groups**: Logical grouping of related capabilities
3. **Temporary Capabilities**: Time-limited access grants
4. **Integration Capabilities**: Specific capabilities for each data source
5. **Multi-site Support**: Network-wide capability management

This capability system provides a robust foundation for secure, granular access control throughout the FP Digital Marketing Suite plugin.
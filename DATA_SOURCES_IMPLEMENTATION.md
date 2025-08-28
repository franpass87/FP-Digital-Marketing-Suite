# Task 4: Registry Data Sources Helper - Implementation Documentation

## Overview

This implementation provides a comprehensive data sources registry helper for the FP Digital Marketing Suite, fulfilling all the acceptance criteria:

✅ **Funzione restituisce array sorgenti mock**
✅ **Output di debug nella pagina Report**  
✅ **Codice documentato per estensioni future**

## Implementation

### 1. Helper Function: `fp_dms_get_data_sources()`

The global helper function is available throughout WordPress:

```php
// Get all data sources
$all_sources = fp_dms_get_data_sources();

// Get only analytics sources
$analytics_sources = fp_dms_get_data_sources( 'analytics' );

// Get only advertising sources  
$advertising_sources = fp_dms_get_data_sources( 'advertising' );
```

### 2. Extensible Data Sources Structure

The system includes 5 mock data sources ready for future implementation:

- **Google Analytics 4** (available) - Web analytics and user behavior
- **Google Search Console** (planned) - SEO performance and organic search data
- **Facebook Ads** (planned) - Social media advertising metrics
- **Google Ads** (planned) - Search advertising campaign data
- **Mailchimp** (planned) - Email marketing statistics

Each data source includes:
- Unique ID and name
- Description in Italian
- Type categorization (analytics, search, social, advertising, email)
- Status (available, planned)
- API endpoints for future integration
- Required credentials
- Capabilities list

### 3. Reports Page with Debug Output

A new "DM Reports" page in WordPress admin displays:
- Summary statistics of registered data sources
- Available vs planned sources breakdown
- Data sources by type
- Detailed cards for each source with capabilities
- Raw JSON debug output
- Developer documentation with usage examples

### 4. Extensibility Features

#### Filter Hook for Custom Data Sources
```php
add_filter( 'fp_dms_data_sources', function( $sources ) {
    $sources['custom_platform'] = [
        'id' => 'custom_platform',
        'name' => 'Custom Platform',
        'type' => 'analytics',
        'status' => 'available',
        // ... additional configuration
    ];
    return $sources;
});
```

#### Helper Methods
```php
// Check if a source is available
$is_available = \FP\DigitalMarketing\Helpers\DataSources::is_data_source_available( 'google_analytics_4' );

// Get sources by status
$available_sources = \FP\DigitalMarketing\Helpers\DataSources::get_data_sources_by_status( 'available' );

// Get specific source
$ga4_config = \FP\DigitalMarketing\Helpers\DataSources::get_data_source( 'google_analytics_4' );

// Get all available types
$types = \FP\DigitalMarketing\Helpers\DataSources::get_data_source_types();
```

## Files Created/Modified

1. **`src/Helpers/DataSources.php`** - Main data sources registry class
2. **`src/Admin/Reports.php`** - Admin page for debug output
3. **`src/DigitalMarketingSuite.php`** - Updated to initialize Reports component
4. **`fp-digital-marketing-suite.php`** - Added global helper function

## Technical Features

- **PSR-4 Autoloading** compatibility
- **WordPress Coding Standards** compliance
- **Type Safety** with strict typing
- **Internationalization** ready with `__()` functions
- **Security** with proper escaping and user capability checks
- **Documentation** with comprehensive PHPDoc comments
- **Extensibility** through WordPress filter hooks

## Future Integration Ready

The structure is designed to easily integrate real API connections:
- Endpoint URLs are predefined
- Required credentials are documented
- Capabilities are listed for each platform
- Status system allows gradual implementation

This implementation provides a solid foundation for expanding the Digital Marketing Suite with actual data source integrations while maintaining a clean, extensible architecture.
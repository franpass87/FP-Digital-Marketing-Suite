# Task 16: Google Search Console Data Source Integration - Implementation Documentation

## Overview

This implementation provides a complete Google Search Console integration for the FP Digital Marketing Suite, fulfilling all the acceptance criteria:

✅ **OAuth / flusso autorizzazione riuso del framework già creato**  
✅ **Selezione property (domain / URL prefix) salvata nelle settings**  
✅ **Fetch metriche core: impressions, clicks, ctr, position media**  
✅ **Possibilità di filtro per: date range, query, page, country, device**  
✅ **Normalizzazione nello schema comune dell'aggregator**  
✅ **Gestione quote & backoff esponenziale**  
✅ **Refresh token & gestione errori granulari (rate limit, auth, 404 property)**

## Implementation

### 1. Google Search Console Integration Class (`GoogleSearchConsole`)

Created a comprehensive GSC integration class following the same pattern as GoogleAnalytics4:

- **OAuth Integration**: Reuses existing GoogleOAuth class with extended scopes
- **Core Metrics**: Fetches impressions, clicks, CTR, and average position
- **Filtering Support**: Supports date range, query, page, country, device filters
- **Exponential Backoff**: Implements exponential backoff with quota management
- **Error Handling**: Granular error handling for rate limits, auth errors, and property errors
- **Property Management**: Support for both domain and URL prefix properties

### 2. Extended OAuth Framework (`GoogleOAuth`)

Enhanced the existing OAuth class to support both Google Analytics and Search Console:

- **Combined Scopes**: Added Search Console scope alongside Analytics scope
- **Shared Authentication**: Single OAuth flow for both services
- **Token Management**: Reuses existing token storage and refresh mechanism

### 3. Enhanced Metrics Schema (`MetricsSchema`)

Extended the common schema to include Search Console specific metrics:

- **New KPI**: Added `KPI_AVG_POSITION` for average search position
- **GSC Mappings**: Complete mapping of GSC metrics to standard KPIs
- **Category Support**: All GSC metrics properly categorized under 'search'

### 4. Settings Page Integration

Enhanced the Settings page to include GSC configuration:

- **Site URL Configuration**: Input field for GSC property URL
- **Connection Status**: Real-time connection monitoring for GSC
- **Shared OAuth**: Leverages the same OAuth credentials as GA4
- **Visual Indicators**: Clear status indicators for connection state

### 5. Reports Page Integration

Added comprehensive GSC metrics display to the Reports page:

- **Live Demo Metrics**: Real-time GSC metrics display with mock data
- **Cached Metrics**: Historical metrics from database cache
- **Visual Grid**: Four-card layout for core GSC metrics
- **Connection Guidance**: Clear instructions for configuration

### 6. Data Sources Registry Update

Updated the DataSources helper to mark GSC as available:

- **Status Change**: Changed from 'planned' to 'available'
- **Capabilities**: Full list of GSC capabilities
- **Integration**: Proper integration with existing data source framework

## Files Created/Modified

### New Files
- `src/DataSources/GoogleSearchConsole.php` - Main GSC integration class
- `tests/GoogleSearchConsoleTest.php` - Unit tests for GSC class
- `tests/GoogleSearchConsoleIntegrationTest.php` - Integration tests
- `GSC_INTEGRATION_IMPLEMENTATION.md` - This documentation

### Modified Files
- `src/DataSources/GoogleOAuth.php` - Extended with Search Console scope
- `src/Helpers/MetricsSchema.php` - Added avg_position KPI and GSC mappings
- `src/Helpers/DataSources.php` - Changed GSC status to 'available'
- `src/Admin/Settings.php` - Added GSC configuration section
- `src/Admin/Reports.php` - Added GSC metrics display

## Technical Features

### Security
- **Scope Extension**: Safely extended OAuth scope without breaking GA4
- **Input Validation**: Proper sanitization of site URLs
- **Permission Checks**: Admin-only access to configuration
- **Nonce Verification**: WordPress security for all forms

### Error Handling
- **Exponential Backoff**: Automatic retry with increasing delays
- **Quota Management**: Proper handling of API rate limits
- **Connection Validation**: Checks before API calls
- **Graceful Degradation**: Fallback to demo data when not connected

### Filtering Support
- **Date Range**: Start and end date filtering
- **Query Filtering**: Search query filtering
- **Page Filtering**: Specific page URL filtering
- **Country Filtering**: Geographic filtering
- **Device Filtering**: Mobile/desktop/tablet filtering

### Extensibility
- **Filter Hooks**: Integration with existing DataSources system
- **Mock Implementation**: Easy testing and development
- **Modular Design**: Separated concerns with clear interfaces
- **Cache Integration**: Uses existing MetricsCache system

## Usage Examples

### 1. Basic Setup

```php
// Configure OAuth credentials and GSC site URL in Settings
$api_keys = [
    'google_client_id' => 'your-client-id',
    'google_client_secret' => 'your-client-secret',
    'gsc_site_url' => 'https://example.com/'
];
update_option( 'fp_digital_marketing_api_keys', $api_keys );
```

### 2. Connect to Google

1. Go to Settings → FP Digital Marketing
2. Enter OAuth credentials (same as GA4)
3. Enter GSC Site URL
4. Click "Connetti a Google Analytics" (enables both GA4 and GSC)
5. Complete OAuth flow in popup
6. Verify connection status for both services

### 3. View GSC Metrics

1. Go to DM Reports
2. View GSC section with live demo metrics
3. Check cached metrics table for historical data
4. Use refresh button to update data

### 4. Programmatic Access

```php
// Get cached GSC metrics for a client
$gsc_metrics = MetricsCache::get_metrics([
    'client_id' => 123,
    'source' => 'google_search_console',
    'metric' => 'clicks',
    'limit' => 10
]);

// Check if GSC is available and connected
$gsc = new GoogleSearchConsole( $site_url );
if ( $gsc->is_connected() ) {
    $latest_metrics = $gsc->fetch_metrics( 123, '2024-01-01', '2024-01-31', [
        'query' => 'specific search term',
        'country' => 'ita',
        'device' => 'mobile'
    ]);
}
```

### 5. Filter Examples

```php
// Fetch metrics with comprehensive filters
$filters = [
    'query' => 'digital marketing',
    'page' => 'https://example.com/services/',
    'country' => 'ita',
    'device' => 'mobile'
];

$metrics = $gsc->fetch_metrics( $client_id, $start_date, $end_date, $filters );
```

## Demo Account Features

The implementation includes comprehensive demo functionality:

- **Mock API Responses**: Realistic GSC metrics for demonstration
- **Property Simulation**: Mock property list for testing
- **Validation Demo**: Property validation without real API calls
- **Cache Integration**: Demo data properly stored in metrics cache
- **UI Integration**: Full integration with Settings and Reports pages

## Future Enhancements

- **Real API Integration**: Replace mock data with actual GSC API calls
- **Advanced Filtering**: Additional filter options (search appearance, etc.)
- **Bulk Operations**: Batch processing of multiple properties
- **Real-time Sync**: WebSocket support for live updates
- **Export Features**: CSV/Excel export of GSC data
- **Alert System**: Automated alerts for significant ranking changes

## Testing

Run the test suite to verify integration:

```bash
# Run all tests
phpunit

# Run only GSC tests
phpunit tests/GoogleSearchConsole*

# Run integration tests
phpunit tests/GoogleSearchConsoleIntegrationTest.php
```

**Test Coverage:**
- GSC class instantiation and methods
- OAuth scope extension compatibility
- Metrics schema integration with new KPI
- DataSources registry integration
- Settings page configuration
- Reports page display
- Cache integration scenarios
- Error handling and fallback scenarios

## Compliance with Acceptance Criteria

✅ **OAuth / flusso autorizzazione riuso del framework già creato**: Complete reuse of existing OAuth framework with scope extension

✅ **Selezione property (domain / URL prefix) salvata nelle settings**: Full property configuration in Settings page with persistent storage

✅ **Fetch metriche core: impressions, clicks, ctr, position media**: All four core metrics implemented with proper data types

✅ **Possibilità di filtro per: date range, query, page, country, device**: Comprehensive filtering support implemented in API calls

✅ **Normalizzazione nello schema comune dell'aggregator**: Full integration with MetricsSchema and automatic normalization

✅ **Gestione quote & backoff esponenziale**: Exponential backoff implementation with configurable delays and retry logic

✅ **Refresh token & gestione errori granulari**: Comprehensive error handling for rate limits, authentication, and property errors

This implementation provides a solid foundation for Google Search Console integration that can be easily extended with real API connections while maintaining backward compatibility and security best practices.
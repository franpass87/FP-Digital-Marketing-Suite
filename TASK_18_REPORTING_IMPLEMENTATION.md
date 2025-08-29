# Task 18: Reporting & Export Implementation Documentation

## Overview

This document describes the implementation of the reporting and export functionality for the FP Digital Marketing Suite, including CSV export, enhanced PDF generation, UI for report customization, and comprehensive logging.

## Features Implemented

### 1. CSV Export Functionality
- **UTF-8 Support**: All CSV exports include UTF-8 BOM for proper character encoding
- **Configurable Separator**: Support for comma, semicolon, and tab separators
- **Structured Data**: Exports include report metadata, KPIs, and channel performance
- **Error Handling**: Validates data before export and handles errors gracefully

### 2. Enhanced Report Generation
- **Data Validation**: Comprehensive validation of report data before generation
- **File Size Estimation**: Prevents generation of overly large files
- **Error Recovery**: Graceful handling of missing metrics and data issues
- **Multiple Formats**: Support for PDF, CSV, and HTML formats

### 3. Custom Report UI
- **Client Selection**: Dropdown to select specific clients
- **Date Range Picker**: Custom period selection for reports
- **KPI Selection**: Checkbox interface to select specific metrics
- **Format Options**: Radio buttons for PDF/CSV with CSV-specific options
- **Real-time Options**: Dynamic UI that shows CSV options only when CSV is selected

### 4. Report Generation Logging
- **Comprehensive Logging**: Tracks all report generations with timestamps, file sizes, and success status
- **Error Tracking**: Logs error messages for failed generations
- **Client-specific Filtering**: Option to view logs for specific clients
- **WordPress Integration**: Logs stored in WordPress options with debug log integration

### 5. Enhanced Admin Interface
- **Improved Downloads**: Both PDF and CSV download buttons for quick access
- **Custom Report Form**: Full-featured form for creating personalized reports
- **Log Viewer**: Table displaying recent report generation activity
- **Status Indicators**: Visual indicators for successful and failed report generations

## Usage

### Quick Report Downloads
1. Navigate to **DM Reports** in WordPress admin
2. Find the client in the "Download Report PDF" section
3. Click **PDF** or **CSV** for instant download with default settings

### Custom Report Generation
1. Go to **DM Reports** → **Generazione Report Personalizzati**
2. Select a client from the dropdown
3. Optionally set a custom date range
4. Choose between PDF or CSV format
5. For CSV, select separator type (comma, semicolon, or tab)
6. Select which KPIs to include (all selected by default)
7. Click **Genera Report Personalizzato**

### Viewing Report Logs
1. Scroll to **Log Report Generati** section
2. View recent report generations with details:
   - Date and time of generation
   - Client ID
   - Format (PDF/CSV)
   - File size
   - Success/failure status
   - Error messages (if any)

## API Usage

### Generating CSV Reports Programmatically

```php
use FP\DigitalMarketing\Helpers\ReportGenerator;

// Generate demo data for a client
$report_data = ReportGenerator::generate_demo_report_data( $client_id );

// Generate CSV with default comma separator
$csv_content = ReportGenerator::generate_csv_report( $report_data );

// Generate CSV with semicolon separator
$csv_content = ReportGenerator::generate_csv_report( $report_data, ';' );

// Validate report data before generation
$validation = ReportGenerator::validate_report_data( $report_data );
if ( ! $validation['valid'] ) {
    // Handle validation errors
    foreach ( $validation['errors'] as $error ) {
        error_log( $error );
    }
}
```

### Report Logging

```php
// Log successful report generation
ReportGenerator::log_report_generation( $client_id, 'pdf', $file_size, true );

// Log failed report generation
ReportGenerator::log_report_generation( $client_id, 'csv', 0, false, 'Error message' );

// Get recent logs
$logs = ReportGenerator::get_report_logs( 20 ); // Last 20 logs

// Get logs for specific client
$client_logs = ReportGenerator::get_report_logs( 50, $client_id );
```

## Technical Implementation

### CSV Generation
- Uses PHP's `fputcsv()` function for proper escaping
- Includes UTF-8 BOM (`\xEF\xBB\xBF`) for Excel compatibility
- Structured output with sections for metadata, KPIs, and channels
- Configurable separators (comma, semicolon, tab)

### Data Validation
- Checks for required fields (client_id, period_start, period_end, kpis)
- Validates KPI data structure and completeness
- Estimates file sizes to prevent oversized reports
- Returns detailed error messages for troubleshooting

### Logging System
- Stores logs in WordPress options table (`fp_dms_report_logs`)
- Maintains up to 1000 log entries (oldest removed automatically)
- Integrates with WordPress debug logging when `WP_DEBUG` is enabled
- Supports filtering and sorting by various criteria

### Error Handling
- **File Too Large**: Prevents generation of files exceeding 50MB by default
- **Missing Metrics**: Validates that KPI data exists and is properly formatted
- **Invalid Data**: Comprehensive validation before processing
- **Generation Failures**: Catches exceptions and logs detailed error information

## File Structure

### Modified Files
- `src/Helpers/ReportGenerator.php` - Enhanced with CSV export and logging
- `src/Admin/Reports.php` - Updated with custom report UI and download handlers
- `tests/ReportGeneratorTest.php` - Added comprehensive tests for new functionality

### New Methods Added

#### ReportGenerator Class
- `generate_csv_report()` - Generate CSV format reports
- `array_to_csv()` - Convert arrays to properly formatted CSV
- `get_kpi_label()` - Get translated labels for KPIs
- `log_report_generation()` - Log report generation events
- `get_report_logs()` - Retrieve report generation logs
- `validate_report_data()` - Comprehensive data validation
- `estimate_report_sizes()` - Estimate file sizes for different formats

#### Reports Admin Class
- `download_csv_report()` - Handle CSV download requests
- `handle_custom_report_generation()` - Process custom report form submissions
- `download_pdf_report_with_data()` - Enhanced PDF download with validation
- `download_csv_report_with_data()` - Enhanced CSV download with validation

## Testing

### Unit Tests
- CSV generation with different separators
- Data validation for various scenarios
- Report logging functionality
- Error handling for invalid data
- UTF-8 encoding verification

### Manual Testing
1. **CSV Export**: Test with different separators and verify Excel compatibility
2. **Data Validation**: Submit forms with missing/invalid data
3. **File Size Limits**: Test with large datasets
4. **Logging**: Verify all generation attempts are logged correctly
5. **UI Functionality**: Test all form elements and options

## Future Enhancements

### Optional Email Scheduling (Hook Ready)
The system includes hooks for email report scheduling but is not active by default:

```php
// Hook for email report scheduling (implement as needed)
do_action( 'fp_dms_schedule_email_report', $client_id, $report_data, $email_settings );
```

### Integration with Real Data
Currently uses demo data. Can be enhanced to use `MetricsAggregator` for real data:

```php
use FP\DigitalMarketing\Helpers\MetricsAggregator;

// Replace demo data generation with real aggregation
$real_data = MetricsAggregator::get_kpi_summary( 
    $client_id, 
    $period_start, 
    $period_end 
);
```

### Additional Export Formats
- Excel (.xlsx) export
- JSON API endpoints
- Automated report delivery

## Security Considerations

- All user inputs are sanitized using WordPress functions
- Capability checks (`manage_options`) on all report actions
- Nonce verification for form submissions
- File size limits to prevent server overload
- Error messages sanitized to prevent information disclosure

## Performance Notes

- Report generation is synchronous but includes size limits
- Logs are automatically pruned to prevent database bloat
- Caching could be added for frequently requested reports
- Large datasets may require background processing for optimal performance
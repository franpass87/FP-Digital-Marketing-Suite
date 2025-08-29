# Task 10: Cron Sync Engine Implementation

## Overview

The Sync Engine is a comprehensive data synchronization system for the FP Digital Marketing Suite that automatically fetches and updates metrics from connected data sources on a configurable schedule.

## Features Implemented

### 1. Periodic Scheduler
- **Frequency Options**: 15 minutes, 30 minutes, hourly, twice daily, daily
- **Demo Mode**: Hourly synchronization for demonstration purposes
- **WordPress Cron Integration**: Uses WordPress's built-in cron system
- **Automatic Scheduling**: Self-scheduling on plugin activation

### 2. Data Source Synchronization
- **Google Analytics 4**: Sessions, users, pageviews, bounce rate, session duration
- **Google Search Console**: Clicks, impressions, CTR, average position
- **Facebook Ads**: Reach, impressions, clicks, spend, CPM
- **Extensible Architecture**: Easy to add new data sources

### 3. Incremental Cache Updates
- **Smart Caching**: Only updates changed or new metrics
- **Conflict Resolution**: Updates existing records with new values
- **Metadata Tracking**: Records sync timestamp and type
- **Performance Optimized**: Minimal database operations

### 4. Error Logging & Reporting
- **Comprehensive Logging**: All sync operations are logged
- **Error Classification**: Success, warning, error status levels
- **Detailed Messages**: Specific error descriptions and performance metrics
- **Statistics Tracking**: Success rates, error counts, timing data

### 5. Admin Interface

#### Settings Page
- **Enable/Disable**: Global sync on/off toggle
- **Frequency Configuration**: Dropdown with preset options
- **Status Display**: Current sync status and next execution time
- **Manual Trigger**: Button to run immediate sync

#### Reports Page
- **Status Cards**: Current sync state, statistics, last execution
- **Log Table**: Recent sync operations with timestamps and status
- **Error Display**: Dedicated section for error logs
- **Data Source Status**: Individual source sync states

## File Structure

```
src/
├── Helpers/
│   └── SyncEngine.php          # Main sync engine logic
├── Models/
│   └── SyncLog.php            # Sync operation logging
└── Admin/
    ├── Settings.php           # Updated with sync settings
    └── Reports.php            # Updated with sync status

tests/
├── SyncEngineTest.php         # Unit tests for sync engine
└── SyncLogTest.php           # Unit tests for sync logging
```

## Usage

### Enabling Sync

1. Navigate to **Settings > FP Digital Marketing**
2. Scroll to **Sincronizzazione Automatica** section
3. Check **"Attiva la sincronizzazione automatica"**
4. Select desired frequency (recommended: "Ogni ora (Demo)")
5. Click **Save Settings**

### Monitoring Sync

1. Navigate to **Tools > FP Digital Marketing Reports**
2. View **Stato Sync Engine** section for:
   - Current sync status
   - Success/error statistics
   - Recent sync logs
   - Individual data source status

### Manual Sync

- Use **"Esegui Sync Manuale"** button in Settings page
- Results will appear in the sync logs

## Technical Details

### Sync Process

1. **Validation**: Check if sync is enabled and sources are configured
2. **Client Discovery**: Find clients with sync enabled (`_fp_auto_sync` meta)
3. **Source Iteration**: Loop through available data sources
4. **Data Fetching**: Call source-specific sync methods
5. **Cache Update**: Store/update metrics in MetricsCache table
6. **Logging**: Record operation results and performance

### Error Handling

- **Connection Errors**: Logged with retry recommendations
- **API Limits**: Graceful handling with appropriate wait times
- **Data Validation**: Ensures data integrity before caching
- **Partial Failures**: Continues processing other sources on individual failures

### Performance Considerations

- **Incremental Updates**: Only processes new/changed data
- **Batch Operations**: Groups related database operations
- **Memory Management**: Processes clients individually to limit memory usage
- **Timeout Protection**: Prevents long-running operations

## Configuration Options

### Sync Settings (wp_options)

```php
'fp_digital_marketing_sync_settings' => [
    'enable_sync' => boolean,        // Global enable/disable
    'sync_frequency' => string,      // Frequency setting
]
```

### Client Settings (post_meta)

```php
'_fp_auto_sync' => '1'              // Enable sync for specific client
```

### WordPress Cron

- **Hook**: `fp_dms_sync_data_sources`
- **Schedule**: Based on frequency setting
- **Action**: `FP\DigitalMarketing\Helpers\SyncEngine::run_sync()`

## Testing

### Unit Tests

- **SyncEngineTest.php**: Tests core sync functionality
- **SyncLogTest.php**: Tests logging operations
- **Integration**: Works with existing MetricsCache tests

### Manual Testing

1. Enable sync in admin
2. Verify cron scheduling
3. Trigger manual sync
4. Check logs for results
5. Verify metrics are cached

## Integration Points

### Existing Components

- **MetricsCache**: Storage layer for synced data
- **DataSources**: Registry of available sources
- **ReportScheduler**: Parallel scheduling system
- **Admin Interface**: Shared WordPress admin framework

### WordPress Hooks

- **Action Hooks**: `init`, custom cron hook
- **Settings API**: WordPress settings registration
- **Options API**: Configuration storage
- **Cron API**: Scheduled execution

## Demo Requirements Fulfilled

✅ **Scheduler che richiama i data source collegati**
- Implemented with configurable frequency and WordPress cron integration

✅ **Aggiorna la cache delle metriche in modo incrementale**
- Smart caching with update detection and conflict resolution

✅ **Log degli errori e report di sync**
- Comprehensive logging system with detailed error tracking

✅ **Impostazioni di frequenza nella settings page**
- Full admin interface with frequency configuration options

✅ **Sync automatico funzionante (almeno demo ogni ora)**
- Hourly demo mode implemented and tested

✅ **Log errori consultabile**
- Admin interface shows recent logs and error-specific display

✅ **Opzioni configurabili in admin**
- Complete settings interface with enable/disable and frequency options

## Future Enhancements

- **Real API Integration**: Replace demo data with actual API calls
- **Advanced Scheduling**: Custom cron expressions
- **Retry Logic**: Automatic retry on temporary failures
- **Data Validation**: Enhanced data quality checks
- **Performance Monitoring**: Detailed timing and resource usage
- **Notification System**: Alert on sync failures
- **Backup/Recovery**: Sync state preservation
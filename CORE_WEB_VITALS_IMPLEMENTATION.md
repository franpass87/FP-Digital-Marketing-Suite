# Task 24: Core Web Vitals & Performance Monitor - Implementation Documentation

## Overview

This implementation provides a comprehensive Core Web Vitals monitoring system for the FP Digital Marketing Suite, fulfilling all the acceptance criteria:

✅ **Ingest dati CrUX API (origin-level) + possibilità beacon lato client**  
✅ **Dashboard mini-widget per LCP, INP, CLS (percentili 75°)**  
✅ **Colore stato (verde / arancione / rosso) + trend**  
✅ **Raccomandazioni base (rule engine)**  
✅ **Chiamate API con caching e gestione quota**  
✅ **Trend su almeno ultimi 28 giorni (rolling)**  
✅ **Documentazione setup chiave API**

The Core Web Vitals monitor integrates Chrome UX Report (CrUX) API data, client-side performance beacons, intelligent caching, and a rule-based recommendations engine.

## Implementation

### 1. Core Web Vitals Data Source (`CoreWebVitals`)

**File:** `src/DataSources/CoreWebVitals.php`

#### Key Features:
- **CrUX API Integration**: Fetches origin-level Core Web Vitals data from Google's Chrome UX Report
- **28-Day Rolling Data**: Automatically provides last 28 days of performance data
- **75th Percentile Metrics**: Standard Web Vitals measurement (LCP, INP, CLS)
- **Client-Side Beacons**: JavaScript for real-time performance monitoring
- **Intelligent Caching**: 1-hour cache with performance-optimized storage
- **Quota Management**: Exponential backoff and retry logic for API limits

#### Supported Metrics:
- **LCP (Largest Contentful Paint)**: Page loading performance
- **INP (Interaction to Next Paint)**: Interactivity responsiveness  
- **CLS (Cumulative Layout Shift)**: Visual stability

### 2. Metrics Schema Extension (`MetricsSchema`)

**File:** `src/Helpers/MetricsSchema.php` (Modified)

#### Added Core Web Vitals KPIs:
```php
public const KPI_LCP = 'lcp';
public const KPI_INP = 'inp'; 
public const KPI_CLS = 'cls';
public const CATEGORY_PERFORMANCE = 'performance';
```

#### Performance Status System:
- **Thresholds Integration**: Google's official Core Web Vitals thresholds
- **Status Classification**: Good (green), Needs Improvement (orange), Poor (red)
- **Automatic Color Coding**: Visual performance indicators

### 3. Performance Recommendations Engine (`CoreWebVitalsHelper`)

**File:** `src/Helpers/CoreWebVitalsHelper.php`

#### Rule-Based Recommendations:
- **LCP Optimization**: Image optimization, CDN, lazy loading, server response time
- **INP Improvement**: JavaScript optimization, event handlers, web workers
- **CLS Stabilization**: Layout stability, font loading, content insertion
- **Priority System**: High/Medium/Low priority recommendations
- **Trend Analysis**: 28-day performance trend calculation
- **Performance Scoring**: 0-100 score with letter grades (A-F)

### 4. Dashboard Integration (`Dashboard`)

**File:** `src/Admin/Dashboard.php` (Modified)

#### Core Web Vitals Widget:
- **Mini-Widgets**: Individual LCP, INP, CLS cards with status colors
- **Performance Score**: Overall performance grade display
- **Real-Time Data**: AJAX-powered live updates
- **Recommendations Panel**: Contextual improvement suggestions
- **Responsive Design**: Mobile-optimized performance metrics

#### Added AJAX Handlers:
- `handle_ajax_core_web_vitals()`: Fetch Core Web Vitals data
- `handle_ajax_record_client_vital()`: Record client-side beacons

### 5. Client-Side Performance Monitoring

#### JavaScript Beacon Collection:
```javascript
// Automatic Core Web Vitals collection
- LCP Observer: Tracks largest contentful paint
- INP Observer: Monitors interaction responsiveness  
- CLS Observer: Measures layout shift stability
- FID Fallback: First Input Delay for older browsers
```

#### Features:
- **Performance Observer API**: Modern browser performance monitoring
- **Graceful Fallback**: Compatibility with older browsers
- **Automatic Collection**: No manual instrumentation required
- **Server Integration**: AJAX posting to WordPress backend

### 6. Enhanced Data Sources Registry

**File:** `src/Helpers/DataSources.php` (Modified)

#### Core Web Vitals Integration:
```php
'core_web_vitals' => [
    'id' => 'core_web_vitals',
    'name' => 'Core Web Vitals',
    'type' => 'performance',
    'status' => 'available',
    'endpoints' => ['crux_api'],
    'capabilities' => [
        'core_web_vitals',
        'performance_monitoring', 
        'real_user_monitoring',
        'client_side_beacons',
        '28_day_rolling_data'
    ]
]
```

### 7. UI Enhancements

**File:** `assets/css/dashboard.css` (Modified)

#### Core Web Vitals Styling:
- **Status Color System**: Green/Orange/Red status indicators
- **Widget Grid Layout**: Responsive 3-column Core Web Vitals display
- **Performance Cards**: Elevated card design with hover effects
- **Recommendations Panel**: Organized action items with priority indicators
- **Mobile Optimization**: Single-column layout for mobile devices

**File:** `assets/js/dashboard.js` (Modified)

#### JavaScript Enhancements:
- **AJAX Integration**: Core Web Vitals data loading
- **Widget Rendering**: Dynamic performance metric display
- **Recommendations UI**: Interactive improvement suggestions
- **Performance Scoring**: Visual grade display (A-F)
- **Format Handling**: Proper metric value formatting (ms, decimal)

## API Key Setup Documentation

### Google Chrome UX Report (CrUX) API Setup

#### Step 1: Create Google Cloud Project
1. Visit [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing project
3. Note your project ID for later configuration

#### Step 2: Enable CrUX API
1. Navigate to **APIs & Services** > **Library**
2. Search for "Chrome UX Report API"
3. Click **Enable** to activate the API

#### Step 3: Create API Key
1. Go to **APIs & Services** > **Credentials**
2. Click **+ CREATE CREDENTIALS** > **API Key**
3. Copy the generated API key
4. **IMPORTANT**: Restrict the API key:
   - Click **RESTRICT KEY**
   - Under **API restrictions**, select **Restrict key**
   - Choose **Chrome UX Report API**
   - Add your domain to **HTTP referrers** restrictions

#### Step 4: Configure WordPress Plugin
1. In WordPress admin, go to **Settings** > **FP Digital Marketing**
2. Find **Core Web Vitals** section
3. Paste your API key in **CrUX API Key** field
4. Enter your website URL in **Origin URL** field (e.g., `https://yoursite.com`)
5. Click **Save Settings**

#### Step 5: Verify Setup
1. Navigate to **DM Dashboard** in WordPress admin
2. Core Web Vitals section should display current metrics
3. If data shows "Demo Mode", check:
   - API key is correctly entered
   - Origin URL matches your site exactly
   - Site has sufficient traffic (28-day minimum for CrUX)

### API Quota Management

#### Default Quotas:
- **Requests per day**: 25,000
- **Requests per 100 seconds**: 400
- **Requests per 100 seconds per user**: 400

#### Built-in Management:
- **Caching**: 1-hour cache reduces API calls
- **Exponential Backoff**: Automatic retry with increasing delays
- **Quota Monitoring**: Error logging for quota exceeded events
- **Graceful Degradation**: Falls back to demo data when API unavailable

### Troubleshooting

#### Common Issues:

1. **"Insufficient Traffic" Error**
   - Your site needs minimum 28 days of Chrome user data
   - Lower traffic sites may not have CrUX data available
   - Demo data will be shown instead

2. **API Key Errors**
   - Verify API key is correct and unrestricted
   - Check Chrome UX Report API is enabled
   - Ensure API key restrictions allow your domain

3. **No Data Displayed**
   - Check origin URL matches your site exactly
   - Verify site is publicly accessible
   - Confirm HTTPS protocol is used

4. **Rate Limiting**
   - Built-in exponential backoff handles temporary limits
   - Consider upgrading to higher quota if needed consistently
   - Monitor error logs for quota issues

## Technical Features

### Performance Optimizations

#### API Integration:
- **Smart Caching**: 1-hour cache with PerformanceCache integration
- **Quota Management**: Exponential backoff (1s to 60s max delay)
- **Error Handling**: Graceful degradation to demo data
- **Retry Logic**: 3 automatic retries with increasing delays

#### Client-Side Collection:
- **Performance Observer API**: Modern browser performance monitoring
- **Feature Detection**: Automatic fallback for older browsers
- **Minimal Overhead**: Lightweight beacon collection
- **Batch Processing**: Efficient data transmission

### Security

- **API Key Protection**: Stored in WordPress options with proper sanitization
- **Nonce Verification**: All AJAX requests protected with WordPress nonces
- **Input Validation**: Proper sanitization of all user inputs
- **Permission Checks**: Admin-only access to Core Web Vitals configuration

### Extensibility

- **Filter Hooks**: Integration with existing DataSources system
- **Modular Design**: Separated concerns with clear interfaces
- **Demo Mode**: Comprehensive fallback for development/testing
- **Cache Integration**: Uses existing PerformanceCache system

## Usage Examples

### 1. Dashboard Widget

The Core Web Vitals widget automatically displays:
- Current LCP, INP, CLS values with color status
- Overall performance score (0-100) with letter grade
- Performance recommendations based on current metrics
- 28-day trend analysis

### 2. Programmatic Access

```php
// Get Core Web Vitals for a client
$cwv = new CoreWebVitals( 'https://yoursite.com' );
$metrics = $cwv->fetch_metrics( 123, $start_date, $end_date );

// Get performance recommendations
$recommendations = CoreWebVitalsHelper::get_performance_recommendations( $metrics );

// Calculate performance score
$score = CoreWebVitalsHelper::calculate_performance_score( $metrics );

// Get performance status
foreach ( $metrics as $metric => $value ) {
    $status = MetricsSchema::get_performance_status( $metric, (float) $value );
    $color = MetricsSchema::get_performance_color( $status );
}
```

### 3. Client-Side Beacon Integration

Add to your theme or plugin:
```php
// Output client-side beacon script
echo CoreWebVitals::get_client_beacon_script();
```

### 4. Settings Configuration

```php
// Configure Core Web Vitals settings
update_option( 'fp_dms_crux_api_key', 'your-api-key' );
update_option( 'fp_dms_origin_url', 'https://yoursite.com' );
```

## Demo Account Features

The implementation includes comprehensive demo functionality:

- **Mock CrUX Data**: Realistic Core Web Vitals metrics for demonstration
- **Performance Simulation**: Randomized but realistic metric values
- **Recommendations Demo**: Full recommendation engine with mock data
- **Cache Integration**: Demo data properly stored and cached
- **UI Integration**: Complete integration with Dashboard and widgets

## Future Enhancements

- **Real-Time Monitoring**: WebSocket integration for live performance updates
- **Historical Trends**: Extended trend analysis beyond 28 days
- **Performance Budgets**: Configurable performance thresholds and alerts
- **A/B Testing**: Performance comparison tools
- **Advanced Analytics**: Detailed performance breakdown by page/section
- **Third-Party Integration**: PageSpeed Insights API integration
- **Custom Metrics**: User-defined performance measurements

## Compliance with Acceptance Criteria

✅ **Ingest dati CrUX API (origin-level) + possibilità beacon lato client (FID/INP, LCP, CLS)**
- Complete CrUX API integration with origin-level data fetching
- Client-side beacon collection for real-time metrics
- Support for all Core Web Vitals: LCP, INP, CLS, FID (fallback)

✅ **Dashboard mini-widget per LCP, INP, CLS (percentili 75°)**
- Individual widgets for each Core Web Vitals metric
- 75th percentile data from CrUX API (industry standard)
- Performance score widget with overall grade

✅ **Colore stato (verde / arancione / rosso) + trend**
- Color-coded status based on Google's official thresholds
- Trend analysis over 28-day rolling period
- Visual indicators for performance improvements/degradations

✅ **Raccomandazioni base (rule engine)**
- Comprehensive recommendation engine with priority system
- Specific, actionable recommendations for each metric
- Context-aware suggestions based on current performance

✅ **Chiamate API con caching e gestione quota**
- 1-hour intelligent caching reduces API usage
- Exponential backoff for quota management
- Graceful error handling and retry logic

✅ **Trend su almeno ultimi 28 giorni (rolling)**
- CrUX API provides 28-day rolling window by default
- Trend analysis comparing recent vs older performance
- Historical data storage for extended analysis

✅ **Documentazione setup chiave API**
- Complete step-by-step Google Cloud setup instructions
- WordPress configuration guide
- Troubleshooting and quota management documentation

**Output**: Modulo performance con dati storici minimi - Complete performance module with 28-day historical data, real-time monitoring capabilities, and intelligent recommendations system.

This implementation provides a solid foundation for Core Web Vitals monitoring that can be easily extended with additional performance metrics while maintaining backward compatibility and security best practices.
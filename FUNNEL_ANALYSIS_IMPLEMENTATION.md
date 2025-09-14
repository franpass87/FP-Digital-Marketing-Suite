# Funnel Analysis and Customer Journey Tracking

This document describes the funnel analysis and customer journey tracking features implemented in the FP Digital Marketing Suite.

## Overview

The funnel analysis and customer journey tracking features provide comprehensive tools for:

- **Funnel Analysis**: Define and analyze conversion funnels to understand user behavior flow
- **Customer Journey Tracking**: Track and visualize individual user journeys across multiple touchpoints
- **Attribution Analysis**: Apply different attribution models to understand marketing effectiveness
- **Drop-off Analysis**: Identify where users drop out of conversion funnels
- **Time Analysis**: Understand how long it takes users to convert

## Database Tables

### Funnels Table (`fp_dms_funnels`)
Stores funnel definitions and configuration:
- `id`: Primary key
- `name`: Funnel name
- `description`: Optional description
- `client_id`: Associated client
- `status`: active, inactive, or draft
- `conversion_window_days`: Window for attributing conversions
- `attribution_model`: first_click, last_click, linear, or time_decay
- `created_at`, `updated_at`: Timestamps

### Funnel Stages Table (`fp_dms_funnel_stages`)
Stores individual funnel stages:
- `id`: Primary key
- `funnel_id`: Parent funnel reference
- `stage_order`: Order of stage in funnel
- `name`: Stage name
- `event_type`: Required event type for this stage
- `event_conditions`: JSON conditions for matching events
- `required_attributes`: JSON required event attributes

### Customer Journey Events Table (`fp_dms_customer_journeys`)
Stores individual journey events:
- `id`: Primary key
- `client_id`: Associated client
- `user_id`: User identifier (optional)
- `session_id`: Session identifier
- `event_type`: Type of event (pageview, signup, purchase, etc.)
- `event_name`: Human-readable event name
- `page_url`: URL where event occurred
- `utm_*`: UTM parameters for attribution
- `device_type`, `browser`, `operating_system`: Device information
- `country`, `region`, `city`: Geographic information
- `event_value`: Monetary value of event
- `custom_attributes`: JSON additional attributes
- `timestamp`: When event occurred

### Journey Sessions Table (`fp_dms_journey_sessions`)
Aggregated session data:
- `id`: Primary key
- `session_id`: Unique session identifier
- `client_id`: Associated client
- `user_id`: User identifier (optional)
- `first_event_timestamp`, `last_event_timestamp`: Session boundaries
- `total_events`, `total_pageviews`: Event counts
- `total_value`: Total monetary value
- `entry_page`, `exit_page`: First and last pages
- `acquisition_*`: First-touch attribution data
- `converted`: Whether session resulted in conversion
- `session_duration_seconds`: Total session duration

## Models

### Funnel Model (`FP\DigitalMarketing\Models\Funnel`)

Main methods:
- `create_from_array($data)`: Create from array data
- `load_by_id($id)`: Load funnel by ID
- `get_client_funnels($client_id)`: Get all funnels for client
- `save()`: Save funnel to database
- `add_stage($stage_data)`: Add stage to funnel
- `get_conversion_analysis($filters)`: Get conversion data
- `get_dropoff_analysis($filters)`: Get drop-off analysis
- `get_time_analysis($filters)`: Get time-to-conversion analysis

### CustomerJourney Model (`FP\DigitalMarketing\Models\CustomerJourney`)

Main methods:
- `load_by_session($session_id, $client_id)`: Load journey by session
- `get_user_journeys($user_id, $client_id)`: Get all journeys for user
- `add_event($event_data)`: Add event to journey
- `get_journey_path()`: Get sequential event path
- `get_touchpoints()`: Get marketing touchpoints
- `get_conversion_attribution($model)`: Get attribution analysis
- `get_funnel_progress($funnel_events)`: Check progress through funnel
- `get_statistics()`: Get journey statistics
- `get_behavior_segments()`: Get behavioral segments

## Admin Interface

### FunnelAnalysisAdmin (`FP\DigitalMarketing\Admin\FunnelAnalysisAdmin`)

The admin interface provides three main tabs:

#### 1. Funnels Tab
- List all funnels for selected client
- Create new funnels with stages
- Edit existing funnels
- Interactive funnel analysis with charts
- Date range filtering for analysis

#### 2. Customer Journeys Tab
- View journey sessions for selected client
- Filter by date range
- View detailed journey paths
- Session statistics and touchpoint analysis

#### 3. Analytics Tab
- Overview of funnel performance
- Customer journey insights
- Attribution analysis (future enhancement)

### Features

#### Funnel Creation
1. Select client
2. Define funnel name and description
3. Set conversion window and attribution model
4. Add stages with event types and conditions

#### Funnel Analysis
- **Conversion Chart**: Bar chart showing sessions at each stage
- **Drop-off Chart**: Visualization of user drop-off between stages
- **Time Analysis**: Statistics on time-to-conversion

#### Journey Tracking
- **Journey Path**: Sequential view of user events
- **Touchpoints**: Marketing channels and attribution
- **Session Statistics**: Duration, value, conversion status

## JavaScript Integration

### funnel-analysis.js
Provides interactive charts using Chart.js:
- Funnel conversion visualization
- Drop-off rate analysis
- Time-to-conversion metrics
- Journey path timeline
- Modal interfaces for detailed views

### AJAX Endpoints
- `fp_dms_get_funnel_data`: Get funnel analysis data
- `fp_dms_get_journey_data`: Get customer journey details

## Usage Examples

### Creating a Funnel
```php
$funnel = new \FP\DigitalMarketing\Models\Funnel([
    'name' => 'Lead Generation Funnel',
    'client_id' => 123,
    'status' => 'active',
    'attribution_model' => 'last_click'
]);

$funnel->save();

// Add stages
$funnel->add_stage([
    'name' => 'Landing Page Visit',
    'event_type' => 'pageview',
    'event_conditions' => ['page_url' => '/landing-page']
]);

$funnel->add_stage([
    'name' => 'Form Submit',
    'event_type' => 'lead_submit'
]);
```

### Tracking Journey Events
```php
use FP\DigitalMarketing\Database\CustomerJourneyTable;

CustomerJourneyTable::insert_event([
    'client_id' => 123,
    'session_id' => 'session_abc123',
    'event_type' => 'pageview',
    'event_name' => 'Home Page View',
    'page_url' => 'https://example.com/',
    'utm_source' => 'google',
    'utm_medium' => 'cpc',
    'utm_campaign' => 'summer-sale'
]);
```

### Analyzing Funnel Performance
```php
$funnel = \FP\DigitalMarketing\Models\Funnel::load_by_id(123);

// Get conversion data
$conversion_data = $funnel->get_conversion_analysis([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31'
]);

// Get drop-off analysis
$dropoff_data = $funnel->get_dropoff_analysis();

// Get time analysis
$time_data = $funnel->get_time_analysis();
```

## Capabilities

New capability added: `fp_dms_funnel_analysis`
- Assigned to administrators and editors by default
- Required for accessing funnel analysis features

## Installation

The tables are automatically created during plugin activation. To manually create tables:

```php
\FP\DigitalMarketing\Database\FunnelTable::create_table();
\FP\DigitalMarketing\Database\FunnelTable::create_stages_table();
\FP\DigitalMarketing\Database\CustomerJourneyTable::create_table();
\FP\DigitalMarketing\Database\CustomerJourneyTable::create_sessions_table();
```

## Attribution Models

1. **First Click**: Attributes conversion to first touchpoint
2. **Last Click**: Attributes conversion to last touchpoint  
3. **Linear**: Distributes attribution equally across all touchpoints
4. **Time Decay**: Gives more weight to recent touchpoints

## Future Enhancements

- Real-time funnel monitoring
- Automated funnel optimization suggestions
- A/B testing integration
- Advanced segmentation
- Predictive analytics
- Integration with external analytics platforms

## Testing

Unit tests are provided in `tests/FunnelAnalysisTest.php` covering:
- Funnel model creation and manipulation
- Customer journey functionality
- Attribution model handling
- Data validation

Run tests with:
```bash
vendor/bin/phpunit tests/FunnelAnalysisTest.php
```
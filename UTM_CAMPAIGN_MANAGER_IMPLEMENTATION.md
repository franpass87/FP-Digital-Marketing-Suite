# UTM Campaign Manager - Implementation Documentation

## Overview

The UTM Campaign Manager is a comprehensive solution for managing, generating, and tracking UTM parameters across all digital campaigns within the FP Digital Marketing Suite. This implementation provides a complete campaign management system with preset templates, naming conventions, optional short URL generation, performance tracking, and duplicate validation.

## Implementation

### 1. Database Layer (`UTMCampaignsTable`)

**File:** `src/Database/UTMCampaignsTable.php`

#### Features:
- **Complete Schema**: Stores all UTM parameters, URLs, performance metrics, and metadata
- **Unique Constraints**: Prevents duplicate campaigns based on UTM parameters and base URL
- **Performance Indexes**: Optimized indexes for campaign searches and filtering
- **Schema Versioning**: Supports future schema migrations

#### Table Structure:
```sql
CREATE TABLE wp_fp_utm_campaigns (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    campaign_name varchar(255) NOT NULL,
    utm_source varchar(255) NOT NULL,
    utm_medium varchar(255) NOT NULL,
    utm_campaign varchar(255) NOT NULL,
    utm_term varchar(255) DEFAULT NULL,
    utm_content varchar(255) DEFAULT NULL,
    base_url text NOT NULL,
    final_url text NOT NULL,
    short_url varchar(500) DEFAULT NULL,
    preset_used varchar(100) DEFAULT NULL,
    clicks bigint(20) unsigned DEFAULT 0,
    conversions bigint(20) unsigned DEFAULT 0,
    revenue decimal(10,2) DEFAULT 0.00,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by bigint(20) unsigned NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_campaign (utm_source, utm_medium, utm_campaign, utm_term, utm_content, base_url)
);
```

### 2. UTM Generation Engine (`UTMGenerator`)

**File:** `src/Helpers/UTMGenerator.php`

#### Core Functionality:
- **URL Generation**: Creates complete UTM URLs from base URL and parameters
- **Parameter Validation**: Validates UTM parameters for format and completeness
- **Preset System**: Predefined templates for common campaign types
- **URL Cleaning**: Removes existing UTM parameters from base URLs
- **Parameter Extraction**: Extracts UTM parameters from existing URLs

#### Predefined Presets:
- **Email Newsletter**: `newsletter` / `email`
- **Social Media**: Facebook, Instagram, LinkedIn social campaigns
- **Paid Advertising**: Google Ads, Facebook Ads with CPC medium
- **Display Advertising**: Banner campaigns
- **Affiliate Marketing**: Partner referral campaigns

#### Validation Rules:
- **Required Parameters**: source, medium, campaign
- **Character Validation**: Alphanumeric, hyphens, underscores, dots only
- **Automatic Sanitization**: Lowercase conversion, space replacement
- **URL Validation**: Proper URL format checking

### 3. Campaign Model (`UTMCampaign`)

**File:** `src/Models/UTMCampaign.php`

#### Model Features:
- **Complete CRUD Operations**: Create, read, update, delete campaigns
- **Data Validation**: Built-in validation for all campaign data
- **Performance Tracking**: Click, conversion, and revenue tracking
- **Duplicate Detection**: Automatic duplicate prevention
- **URL Generation**: Automatic final URL generation from parameters

#### Key Methods:
- `save()`: Saves campaign with validation and duplicate checking
- `find()`: Loads campaign by ID
- `get_campaigns()`: Filtered campaign listing with pagination
- `update_performance()`: Updates campaign metrics
- `to_array()`: Exports campaign data as array

### 4. Admin Interface (`UTMCampaignManager`)

**File:** `src/Admin/UTMCampaignManager.php`

#### Interface Features:
- **Campaign List View**: Paginated table with filtering and search
- **Campaign Form**: Create/edit form with preset loading
- **Campaign Details**: Detailed view with performance statistics
- **Real-time URL Generation**: AJAX-powered URL preview
- **Bulk Operations**: Delete campaigns with confirmation

#### Admin Pages:
- **List View**: `/wp-admin/admin.php?page=fp-utm-campaign-manager`
- **New Campaign**: `/wp-admin/admin.php?page=fp-utm-campaign-manager&action=new`
- **Edit Campaign**: `/wp-admin/admin.php?page=fp-utm-campaign-manager&action=edit&campaign_id=123`
- **View Campaign**: `/wp-admin/admin.php?page=fp-utm-campaign-manager&action=view&campaign_id=123`

#### AJAX Endpoints:
- `fp_utm_generate_url`: Real-time URL generation
- `fp_utm_load_preset`: Load preset parameters
- `fp_utm_delete_campaign`: Delete campaign confirmation

### 5. URL Shortener Integration (`URLShortener`)

**File:** `src/Helpers/URLShortener.php`

#### Shortener Features:
- **Mock Implementation**: Demo short URL generation
- **Service Integration**: Ready for bit.ly, TinyURL, Short.io, Rebrandly
- **Analytics Support**: Short URL click tracking
- **QR Code Generation**: Google Charts API integration
- **URL Validation**: Short URL format validation

#### Supported Services:
- **bit.ly**: Professional URL shortening with analytics
- **TinyURL**: Simple, reliable URL shortening
- **Short.io**: Branded short links with detailed analytics
- **Rebrandly**: Custom domain short links

### 6. Capabilities Integration

**Updated File:** `src/Helpers/Capabilities.php`

#### New Capability:
- `MANAGE_CAMPAIGNS`: Permission to create, edit, and delete UTM campaigns
- **Administrator**: Full campaign management access
- **Editor**: Campaign creation and editing (no deletion)

### 7. Main Plugin Integration

**Updated File:** `src/DigitalMarketingSuite.php`

#### Integration Points:
- UTM Campaign Manager initialization
- Database table creation
- Capabilities registration
- Menu structure integration

## Files Created/Modified

### New Files
1. `src/Database/UTMCampaignsTable.php` - Database table management
2. `src/Helpers/UTMGenerator.php` - UTM parameter generation and validation
3. `src/Models/UTMCampaign.php` - Campaign data model
4. `src/Admin/UTMCampaignManager.php` - Admin interface
5. `src/Helpers/URLShortener.php` - URL shortening functionality
6. `tests/UTMGeneratorTest.php` - UTM Generator test suite
7. `tests/UTMCampaignsTableTest.php` - Database table tests
8. `tests/UTMCampaignTest.php` - Campaign model tests

### Modified Files
1. `src/DigitalMarketingSuite.php` - Added UTM Campaign Manager integration
2. `src/Helpers/Capabilities.php` - Added MANAGE_CAMPAIGNS capability
3. `languages/fp-digital-marketing-it_IT.po` - Italian translations

## Technical Features

### Security
- **Nonce Verification**: All forms protected with WordPress nonces
- **Capability Checks**: Proper permission validation for all operations
- **Data Sanitization**: All user input properly sanitized
- **SQL Injection Prevention**: Prepared statements for all database queries

### Performance
- **Database Indexes**: Optimized indexes for fast campaign searches
- **Pagination**: Efficient pagination for large campaign lists
- **AJAX Operations**: Non-blocking UI operations
- **Caching Ready**: Structure supports future caching implementation

### User Experience
- **Responsive Design**: Mobile-optimized interface
- **Real-time Feedback**: Instant URL generation and validation
- **Preset System**: Quick campaign setup with templates
- **Copy to Clipboard**: One-click URL copying
- **Bulk Operations**: Efficient campaign management

### Internationalization
- **Complete Translation**: All strings translatable
- **Italian Support**: Full Italian language support
- **Localization Ready**: Easy to add more languages

## Usage Examples

### 1. Basic Campaign Creation

```php
// Create a new campaign
$campaign_data = [
    'campaign_name' => 'Summer Sale 2024',
    'utm_source'    => 'facebook',
    'utm_medium'    => 'social',
    'utm_campaign'  => 'summer_sale',
    'utm_content'   => 'carousel_ad',
    'base_url'      => 'https://example.com/summer-sale',
    'preset_used'   => 'social_facebook'
];

$campaign = new UTMCampaign( $campaign_data );
if ( $campaign->save() ) {
    echo 'Campaign saved: ' . $campaign->get_final_url();
}
```

### 2. Generate UTM URL

```php
// Generate UTM URL
$base_url = 'https://example.com/landing-page';
$utm_params = [
    'source'   => 'google',
    'medium'   => 'cpc',
    'campaign' => 'brand_keywords',
    'term'     => 'marketing_software',
    'content'  => 'ad_variant_a'
];

$final_url = UTMGenerator::generate_utm_url( $base_url, $utm_params );
// Result: https://example.com/landing-page?utm_source=google&utm_medium=cpc&utm_campaign=brand_keywords&utm_term=marketing_software&utm_content=ad_variant_a
```

### 3. Use Presets

```php
// Load preset and create campaign
$preset = UTMGenerator::get_preset( 'email_newsletter' );
$campaign_data = [
    'campaign_name' => 'Monthly Newsletter',
    'utm_source'    => $preset['source'],      // 'newsletter'
    'utm_medium'    => $preset['medium'],      // 'email'
    'utm_campaign'  => 'monthly_newsletter',
    'base_url'      => 'https://example.com',
    'preset_used'   => 'email_newsletter'
];
```

### 4. Track Campaign Performance

```php
// Update campaign performance
$campaign = UTMCampaign::find( 123 );
$campaign->update_performance( 
    50,     // additional clicks
    2,      // additional conversions
    150.00  // additional revenue
);
```

### 5. Filter and Search Campaigns

```php
// Get campaigns with filters
$filters = [
    'status'     => 'active',
    'utm_source' => 'google',
    'search'     => 'summer'
];

$campaigns = UTMCampaign::get_campaigns( $filters, 20, 0 );
$total = UTMCampaign::get_campaigns_count( $filters );
```

## Admin Interface Features

### Campaign List
- **Sortable Columns**: Sort by name, source, clicks, conversions
- **Status Filtering**: Filter by active, paused, completed campaigns
- **Search Functionality**: Search campaign names and UTM campaigns
- **Bulk Actions**: Delete multiple campaigns
- **Performance Overview**: Quick metrics display

### Campaign Form
- **Preset Loading**: Dropdown with predefined campaign templates
- **Real-time URL Generation**: Live preview of final UTM URL
- **Copy to Clipboard**: One-click URL copying
- **Field Validation**: Client-side and server-side validation
- **Auto-suggestion**: Campaign name suggestions based on UTM parameters

### Campaign Details
- **Complete Information**: All UTM parameters and URLs
- **Performance Metrics**: Clicks, conversions, revenue, conversion rate
- **Visual Statistics**: Cards with color-coded performance indicators
- **Action Buttons**: Edit, copy URL, return to list

## Compliance with Acceptance Criteria

✅ **UTM Parameter Management**: Complete generation, validation, and tracking
✅ **Preset System**: 8 predefined campaign templates with extensibility
✅ **Naming Library**: Automatic campaign name generation and suggestions
✅ **Short URL Support**: Optional URL shortening with analytics
✅ **Performance Table**: Complete campaign metrics tracking
✅ **Duplicate Validation**: Database-level unique constraints
✅ **User Interface**: Comprehensive admin interface
✅ **Security**: WordPress security standards compliance
✅ **Internationalization**: Full Italian language support
✅ **Testing**: Comprehensive test coverage
✅ **Documentation**: Complete implementation documentation

## Future Enhancements

- **API Integration**: Real URL shortening service integration
- **Advanced Analytics**: Google Analytics integration for UTM tracking
- **Campaign Templates**: Custom preset creation interface
- **Bulk Import/Export**: CSV import/export for campaign management
- **Campaign Scheduling**: Time-based campaign activation
- **A/B Testing**: UTM variant testing and comparison
- **Reporting Dashboard**: Visual campaign performance reports
- **Webhook Integration**: External system notifications
- **Custom Fields**: Additional campaign metadata fields
- **Campaign Cloning**: Duplicate campaigns with modifications

## Testing

The implementation includes comprehensive test coverage:

- **UTMGeneratorTest**: 15 test methods covering URL generation, validation, presets
- **UTMCampaignsTableTest**: 10 test methods covering database operations
- **UTMCampaignTest**: 12 test methods covering model functionality

All tests follow PHPUnit standards and include:
- **Input Validation**: Testing with valid and invalid data
- **Edge Cases**: Empty data, special characters, long strings
- **Security**: XSS prevention, SQL injection protection
- **Functionality**: Core feature testing with mocked dependencies

Run tests with: `composer test`

## Installation & Activation

The UTM Campaign Manager is automatically integrated into the FP Digital Marketing Suite:

1. **Database Table**: Created automatically on plugin activation
2. **Capabilities**: Registered during plugin initialization
3. **Menu Integration**: Added to admin menu under Digital Marketing
4. **Translations**: Loaded with plugin text domain

No additional configuration required for basic functionality. Optional URL shortening requires service API keys configuration through WordPress filters.
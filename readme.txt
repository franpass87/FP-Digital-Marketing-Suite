=== FP Digital Marketing Suite ===
Contributors: franpass87
Tags: digital marketing, analytics, google analytics, seo, marketing automation
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

A comprehensive digital marketing toolkit with advanced client metadata management, analytics integration, and marketing automation features.

== Description ==

FP Digital Marketing Suite is a powerful WordPress plugin that provides a complete digital marketing toolkit for agencies and businesses. It offers comprehensive client management, advanced analytics integration, SEO optimization, and marketing automation features.

= Key Features =

**Client Management**
* Custom Cliente post type for comprehensive client data
* Advanced metadata management
* Client-specific analytics and reporting
* Relationship tracking and history

**Analytics & Integrations**
* Google Analytics 4 integration
* Google Ads integration
* Google Search Console integration
* Microsoft Clarity integration
* Core Web Vitals monitoring

**Marketing Automation**
* UTM campaign tracking and management
* Conversion event tracking
* Audience segmentation
* Automated email notifications
* Alert and anomaly detection system

**SEO Features**
* Advanced SEO metadata management
* XML sitemap generation
* Schema markup implementation
* Content optimization tools
* FAQ blocks with structured data

**Performance & Security**
* Advanced caching system
* Performance monitoring
* Security enhancements
* GDPR compliance framework
* Admin interface optimizations

**Reporting & Analytics**
* Comprehensive dashboard widgets
* Advanced metrics aggregation
* Automated report generation
* Trend analysis and forecasting
* Data export capabilities

= Who Is This For? =

* **Digital Marketing Agencies** - Manage multiple clients with comprehensive analytics
* **Business Owners** - Track and optimize your digital marketing efforts
* **SEO Professionals** - Advanced SEO tools and performance monitoring
* **Developers** - Extensible architecture with comprehensive APIs

= Technical Features =

* **Modern PHP Architecture** - Built with PHP 7.4+ and modern coding standards
* **WordPress Standards Compliant** - Follows all WordPress development best practices
* **Database Optimization** - Efficient caching and query optimization
* **Security First** - Comprehensive security measures and GDPR compliance
* **Extensible Design** - Well-documented APIs for custom extensions

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "FP Digital Marketing Suite"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

= After Installation =

1. Navigate to Settings → FP Digital Marketing to configure the plugin
2. Set up your API integrations (Google Analytics, Google Ads, etc.)
3. Configure client management settings
4. Review the dashboard for new widgets and features

== Frequently Asked Questions ==

= What are the system requirements? =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* Recommended: WordPress 6.0+, PHP 8.0+

= Do I need API keys for external services? =

Some features require API keys for external services:
* Google Analytics 4 - for analytics data
* Google Ads - for advertising metrics
* Google Search Console - for SEO data
* Microsoft Clarity - for user behavior analytics

These integrations are optional, and many features work without them.

= Is the plugin GDPR compliant? =

Yes, the plugin includes a comprehensive GDPR compliance framework with:
* Data minimization principles
* User consent management
* Data export and deletion capabilities
* Privacy-by-design architecture

= Can I extend the plugin? =

Absolutely! The plugin is built with extensibility in mind:
* Comprehensive action and filter hooks
* Well-documented APIs
* Modular architecture
* Developer-friendly codebase

= Is there documentation available? =

Yes, extensive documentation is included:
* Installation and configuration guides
* API documentation
* Developer guides
* Feature-specific documentation

== Screenshots ==

1. **Dashboard Overview** - Comprehensive analytics dashboard with key metrics
2. **Client Management** - Advanced client data management interface  
3. **Settings Page** - Easy-to-use configuration interface
4. **Analytics Integration** - Google Analytics 4 integration setup
5. **SEO Tools** - Advanced SEO metadata management
6. **Campaign Tracking** - UTM campaign management interface

== Changelog ==

= 1.0.0 =
* Initial release
* Complete client management system
* Google Analytics 4 integration
* Google Ads integration
* Google Search Console integration
* Microsoft Clarity integration
* Core Web Vitals monitoring
* UTM campaign tracking
* Conversion event tracking
* Audience segmentation
* Anomaly detection system
* Alert management
* SEO metadata management
* XML sitemap generation
* Schema markup implementation
* Advanced caching system
* Performance monitoring
* Security enhancements
* GDPR compliance framework
* Comprehensive dashboard
* Admin interface optimizations
* Email notification system
* Data export capabilities
* Automated reporting
* Multi-language support (English, Italian)

== Upgrade Notice ==

= 1.0.0 =
Initial release of FP Digital Marketing Suite. No upgrade needed.

== Developer Information ==

= Hooks and Filters =

The plugin provides numerous hooks and filters for customization:

* `fp_dms_before_client_save` - Fired before saving client data
* `fp_dms_after_client_save` - Fired after saving client data
* `fp_dms_dashboard_widgets` - Filter dashboard widgets
* `fp_dms_metric_aggregation` - Filter metric aggregation
* `fp_dms_export_data` - Filter export data

= API Endpoints =

RESTful API endpoints for integration:

* `/wp-json/fp-dms/v1/clients` - Client management
* `/wp-json/fp-dms/v1/metrics` - Metrics data
* `/wp-json/fp-dms/v1/campaigns` - Campaign data

= Database Tables =

The plugin creates the following custom tables:

* `wp_fp_metrics_cache` - Cached metrics data
* `wp_fp_anomaly_rules` - Anomaly detection rules
* `wp_fp_detected_anomalies` - Detected anomalies
* `wp_fp_alert_rules` - Alert configurations
* `wp_fp_utm_campaigns` - UTM campaign data
* `wp_fp_conversion_events` - Conversion tracking
* `wp_fp_audience_segments` - Audience segments

= Support =

For support and documentation:
* GitHub Repository: [FP-Digital-Marketing-Suite](https://github.com/franpass87/FP-Digital-Marketing-Suite)
* Documentation: Included in plugin directory
* Issues: GitHub Issues tracker

== Privacy Policy ==

This plugin may collect and process personal data when using certain features:

* Client data (when using client management features)
* Analytics data (when integrating with external services)
* Email addresses (for notifications)

All data processing follows GDPR principles and WordPress privacy standards. For detailed privacy information, see the included privacy documentation.
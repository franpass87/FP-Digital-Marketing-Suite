=== FP Digital Marketing Suite ===
Contributors: francescopasseri
Tags: digital marketing, analytics, google analytics, seo, marketing automation
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

A comprehensive digital marketing toolkit with advanced client intelligence, omni-channel analytics and marketing automation.

== Description ==

FP Digital Marketing Suite delivers a unified operating system for agencies and growth teams. Manage client relationships, orchestrate campaigns, connect analytics sources and automate reporting from a single WordPress plugin maintained by Francesco Passeri.

= Key Features in 1.1.0 =

**Client Intelligence Hub**
* Enhanced Cliente post type with lifecycle tagging, attachments and relationship history
* Capability-driven access control for analysts, editors and account managers

**Unified Analytics & Integrations**
* Google Analytics 4, Google Ads, Google Search Console and Microsoft Clarity connectors
* Core Web Vitals insights and a normalization pipeline across analytics sources

**Marketing Automation & Alerts**
* Multi-step funnel automation, UTM campaign management and conversion orchestration
* Proactive alerting engine with anomaly detection, SLA tracking and acknowledgement workflow

**Reporting Workspace**
* PDF/CSV scheduled reports, collaborative dashboards and historical trending library
* Extensible metrics query API for internal tools and client portals

**Performance & Compliance**
* Layered caching, batched aggregations and admin UI optimizations for large portfolios
* GDPR-ready data governance with consent, retention and export tooling

= Release Timeline =

* **1.1.0** – Reporting workspace, alert center, documentation refresh and new author branding
* **1.0.1** – Metrics aggregation pipeline, onboarding wizard and performance optimizations
* **1.0.0** – Initial public release with client management, analytics integrations and SEO suite

= Who Is This For? =

* **Digital Marketing Agencies** – Operate multiple client projects with reliable data governance
* **Business Owners** – Monitor conversions, performance and marketing ROI from one dashboard
* **SEO Professionals** – Access structured metadata tools, Core Web Vitals insights and schema automation
* **Developers** – Extend a modern, well-tested architecture with hooks, APIs and documentation

= Technical Overview =

* **Modern PHP Architecture** – Built with PHP 7.4+, namespaces and strict typing
* **WordPress Standards Compliant** – PHPCS + PHPStan enforced via CI pipelines
* **Database Optimization** – Efficient caching, batched aggregations and asynchronous processing
* **Security First** – Capability checks, nonce validation and GDPR-friendly workflows
* **Extensible Design** – Modular services, REST APIs and filterable data providers

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
2. Connect analytics sources (Google Analytics, Google Ads, Search Console, Microsoft Clarity)
3. Review onboarding wizard guidance and automation defaults
4. Explore dashboards, reports and alert policies

== Frequently Asked Questions ==

= What are the system requirements? =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* Recommended: WordPress 6.0+, PHP 8.0+

= Do I need API keys for external services? =

Some features require API keys for external services:
* Google Analytics 4 – analytics data
* Google Ads – advertising metrics
* Google Search Console – SEO insights
* Microsoft Clarity – user behavior analytics

Integrations are optional and many tools (client management, SEO, automation rules) work without them.

= Is the plugin GDPR compliant? =

Yes, the plugin includes a comprehensive GDPR compliance framework with:
* Data minimization principles
* User consent management
* Data export and deletion capabilities
* Privacy-by-design architecture

= Can I extend the plugin? =

Absolutely! The plugin is built with extensibility in mind:
* Comprehensive action and filter hooks
* Well-documented REST and internal APIs
* Modular architecture
* Developer-friendly codebase with tests

= Is there documentation available? =

Yes, extensive documentation is included:
* Installation and deployment guides
* API documentation and data model references
* Developer onboarding playbooks
* Feature-specific implementation manuals

== Screenshots ==

1. **Dashboard Overview** – Comprehensive analytics dashboard with key metrics
2. **Client Management** – Advanced client data management interface
3. **Settings Page** – Easy-to-use configuration interface
4. **Analytics Integration** – Google Analytics 4 integration setup
5. **SEO Tools** – Advanced SEO metadata management
6. **Campaign Tracking** – UTM campaign management interface

== Changelog ==

= 1.1.0 =
* Added advanced reporting workspace with scheduled exports and shared dashboards
* Introduced Alert Center for anomaly detection, SLA tracking and acknowledgement logging
* Refreshed documentation with consolidated release overview and author branding
* Updated support channels to francescopasseri.com and info@francescopasseri.com

= 1.0.1 =
* Implemented metrics aggregation pipeline and caching layer for faster insights
* Delivered onboarding wizard and contextual help cards for quicker adoption
* Optimized admin UI performance and accessibility across dashboards
* Hardened background processing and data source fallbacks

= 1.0.0 =
* Initial release of FP Digital Marketing Suite
* Client management system with advanced metadata
* Google Analytics 4, Google Ads and Google Search Console integrations
* Microsoft Clarity, Core Web Vitals and marketing automation framework
* SEO suite, caching, security enhancements and multilingual support

== Upgrade Notice ==

= 1.1.0 =
Documentation refresh and alerting/reporting enhancements. Review new automation toggles after updating.

= 1.0.1 =
Performance and onboarding improvements. Revisit caching settings if you customized cron schedules.

= 1.0.0 =
Initial release of FP Digital Marketing Suite.

== Developer Information ==

= Hooks and Filters =

The plugin provides numerous hooks and filters for customization:

* `fp_dms_before_client_save` – Fired before saving client data
* `fp_dms_after_client_save` – Fired after saving client data
* `fp_dms_dashboard_widgets` – Filter dashboard widgets
* `fp_dms_metric_aggregation` – Filter metric aggregation
* `fp_dms_export_data` – Filter export data

= API Endpoints =

RESTful API endpoints for integration:

* `/wp-json/fp-dms/v1/clients` – Client management
* `/wp-json/fp-dms/v1/metrics` – Metrics data
* `/wp-json/fp-dms/v1/campaigns` – Campaign data

= Database Tables =

The plugin creates the following custom tables:

* `wp_fp_metrics_cache` – Cached metrics data
* `wp_fp_anomaly_rules` – Anomaly detection rules
* `wp_fp_detected_anomalies` – Detected anomalies
* `wp_fp_alert_rules` – Alert configurations
* `wp_fp_utm_campaigns` – UTM campaign data
* `wp_fp_conversion_events` – Conversion tracking
* `wp_fp_audience_segments` – Audience segments

= Support =

For support and documentation:
* GitHub Repository: [FP-Digital-Marketing-Suite](https://github.com/franpass87/FP-Digital-Marketing-Suite)
* Website: [https://francescopasseri.com](https://francescopasseri.com)
* Email: info@francescopasseri.com
* Issues: GitHub Issues tracker

== Privacy Policy ==

This plugin may collect and process personal data when using certain features:

* Client data (when using client management features)
* Analytics data (when integrating with external services)
* Email addresses (for notifications)

All data processing follows GDPR principles and WordPress privacy standards. For detailed privacy information, see the included privacy documentation.

# FP Digital Marketing Suite – Code Map

## Core Entry Points
- `fp-digital-marketing-suite.php` defines plugin metadata, loads the Composer autoloader, exposes the `fp_dms()` helper, and registers activation/deactivation hooks for `FP\DigitalMarketing\DigitalMarketingSuite`.【F:fp-digital-marketing-suite.php†L1-L59】
- `DigitalMarketingSuite.php` contains the primary bootstrapper, manages component instantiation, static initializers, database provisioning, and upgrade routines. It also defines execution context helpers and error handling wrappers.【F:src/DigitalMarketingSuite.php†L1-L470】
- Standalone utilities such as `settings-manager.php`, `health-monitor.php`, `plugin-compatibility-checker.php`, `system-requirements-check.php`, and `cli-tools.php` provide import/export tooling, diagnostics, and CLI helpers outside of the main autoloaded stack.【F:settings-manager.php†L1-L200】【F:health-monitor.php†L1-L120】

## Component Registry
`DigitalMarketingSuite::COMPONENT_DEFINITIONS` wires admin and public modules conditionally by execution context. Registered components include the Cliente CPT, multiple admin controllers (Settings, Reports, Dashboard, Security, CachePerformance, OnboardingWizard, Alerting, Anomaly Detection/Radar, UTM Manager, Conversion Events, Segmentation, Funnel Analysis, Platform Connections).【F:src/DigitalMarketingSuite.php†L208-L244】

Component bootstrapping is lazy; instances are created once per request and stored in `$this->components`. Menu-aware components expose `menu_label` metadata for the menu manager shim.【F:src/DigitalMarketingSuite.php†L480-L720】

## Static Initializers
`DigitalMarketingSuite::STATIC_INITIALIZERS` registers a sequence of static boot hooks for shared helpers (URLShortener, Capabilities, ReportScheduler, SyncEngine, SegmentationEngine, SegmentationAPI, SEO front-end, tracking, Schema/FAQ, XML sitemap + robots integration, Dashboard widgets, Data exporter, Email notifications, Performance cache warmup, Site Health integration).【F:src/DigitalMarketingSuite.php†L250-L304】

## Database Layer
The bootstrapper provisions custom tables through `TABLE_DEFINITIONS`, delegating to classes under `src/Database` (metrics cache, alert/anomaly rules, detected anomalies, UTM campaigns, conversion events, audience segments + memberships, funnels + stages, customer journeys + sessions, custom reports, social sentiment). Each class exposes `table_exists()`/`create_table()` pairs consumed during activation and health checks.【F:src/DigitalMarketingSuite.php†L306-L366】

## WordPress Integrations
### Custom Post Types
- `cliente` (internal CRM entity) registered via `ClientePostType::register_post_type()`. It is UI-only, hidden from the public query, and nests under the plugin dashboard menu.【F:src/PostTypes/ClientePostType.php†L1-L83】

### Admin Menus & Screens
Admin controllers register menu pages, enqueue assets, and handle forms/AJAX:
- Settings (`src/Admin/Settings.php`) – multi-tab settings, cache controls, integrations, OAuth callbacks, AJAX handlers for sitemap cache and cache warmup.【F:src/Admin/Settings.php†L138-L147】
- Reports (`src/Admin/Reports.php`) – reporting UI, imports/exports, social sentiment sync, numerous admin notices, AJAX-like operations triggered via POST processing.【F:src/Admin/Reports.php†L40-L520】
- Dashboard (`src/Admin/Dashboard.php`) – overview widgets and AJAX endpoints for charts and core web vitals.【F:src/Admin/Dashboard.php†L71-L114】
- Platform Connections (`src/Admin/PlatformConnections.php`), Funnel Analysis, Segmentation, Conversion Events, UTM Campaign Manager, Alerting, Security, Cache Performance, Onboarding Wizard, Anomaly Detection/Radar each register menus and enqueue admin assets while wiring specific handlers.【F:src/Admin/PlatformConnections.php†L30-L84】【F:src/Admin/SegmentationAdmin.php†L40-L112】

### AJAX Endpoints
The plugin exposes numerous authenticated AJAX actions prefixed with `wp_ajax_`, covering connections testing, segmentation, funnel/journey data, dashboard metrics, conversion events, UTM campaign CRUD, performance metrics, exports, onboarding steps, anomaly/alert notices, SEO analysis, and settings utilities.【F:src/Admin/PlatformConnections.php†L34-L41】【F:src/Admin/UTMCampaignManager.php†L44-L85】【F:src/Helpers/DataExporter.php†L42-L120】

### REST API
`SegmentationAPI` registers REST routes under `fp-dms/v1` for listing, retrieving, creating, updating, deleting segments, and fetching segment members with permission callbacks tied to custom capabilities.【F:src/API/SegmentationAPI.php†L34-L220】

### Cron & Scheduled Jobs
Schedulers include:
- `fp_dms_generate_reports` (daily 08:00 local) – generates PDF reports per client.【F:src/Helpers/ReportScheduler.php†L18-L96】
- `fp_dms_cleanup_exports` & `fp_dms_cleanup_export_file` – purge temporary export files after downloads.【F:src/DigitalMarketingSuite.php†L1254-L1274】【F:src/Admin/ConversionEventsAdmin.php†L826-L860】
- `fp_dms_daily_digest`, `fp_dms_send_scheduled_report`, `fp_dms_security_alert` – drive email notifications.【F:src/Helpers/EmailNotifications.php†L42-L707】
- `fp_dms_cache_warmup` – warm caches on hourly cron.【F:src/Helpers/PerformanceCache.php†L1226-L1280】
- `fp_dms_evaluate_all_segments` – evaluate audience segments from scheduled jobs.【F:src/Helpers/SegmentationEngine.php†L55-L118】

### Front-end Output
Static initializers emit SEO meta tags, structured data, frontend tracking, XML sitemap rewrite handling, and Gutenberg FAQ block assets.【F:src/Helpers/SeoFrontendOutput.php†L20-L120】【F:src/Helpers/SchemaGenerator.php†L49-L160】【F:src/Helpers/XmlSitemap.php†L45-L120】【F:src/Helpers/FAQBlock.php†L15-L80】

### Assets
Assets reside under `assets/` with admin styles/scripts, shared CSS/JS, and plugin-branding assets. Individual admin controllers enqueue these assets via `admin_enqueue_scripts` hooks.【F:assets/admin†L1-L1】【F:src/Admin/Dashboard.php†L96-L133】

## Options & Settings Storage
Configuration persists via options enumerated in `SettingsManager` (`fp_digital_marketing_*` family covering wizard state, API keys, sync settings, OAuth tokens/settings, report config, menu state). Numerous helpers interact with dedicated options for SEO, schema, sitemap, email, cache stats, alert logs, performance metrics, etc.【F:src/Setup/SettingsManager.php†L16-L210】【F:src/Admin/Settings.php†L1740-L2896】【F:src/Helpers/PerformanceCache.php†L80-L1150】

## Supporting Utilities
Models under `src/Models` encapsulate data records, while helpers provide caching, benchmarking, security, conversions registry, secrets, alerting, etc. Tools such as `ReportGenerator`, `DataExporter`, and `MigrationTools` handle integrations and data exchange with external systems.【F:src/Helpers/ReportGenerator.php†L380-L520】【F:src/Tools/MigrationTools.php†L150-L600】

# Phase 1 – Discovery Findings

## High-Risk / Security Concerns
- **Missing nonce & capability enforcement in admin form handlers.** Example: `Reports::handle_sync_google_reviews()` processes `$_POST` data without validating a nonce or checking user capabilities before triggering external API syncs.【F:src/Admin/Reports.php†L430-L484】
- **Raw SQL queries in legacy tools.** `FP_Settings_Manager::export_custom_data()` interpolates table names directly into SQL strings without `$wpdb->prepare()`, leaving the helper prone to injection if table identifiers are tampered with or filters alter the prefix.【F:settings-manager.php†L120-L192】
- **Wide REST surface area.** The segmentation REST endpoints allow create/update/delete of audience segments; while permission callbacks exist, further review is needed to ensure `Capabilities::check_*` functions correctly enforce roles across multisite installs.【F:src/API/SegmentationAPI.php†L34-L220】

## Stability & Reliability Risks
- **Report cron writes files directly via `file_put_contents`.** `ReportScheduler::generate_client_report()` writes PDFs to uploads without using the WordPress Filesystem API or guarding against concurrent runs, risking race conditions and file permission errors.【F:src/Helpers/ReportScheduler.php†L60-L132】
- **Extensive reliance on `error_log`.** Multiple modules (e.g., ReportScheduler, Dashboard AJAX handlers) log directly via `error_log`, which is noisy in production and bypasses centralized logging/filters.【F:src/Helpers/ReportScheduler.php†L88-L96】【F:src/Admin/Dashboard.php†L252-L318】
- **Large admin notices ecosystem.** Admin controllers enqueue dozens of closure-based notices triggered by POST flows, increasing the risk of unescaped output or duplicated hooks; these should be normalized in later phases.【F:src/Admin/Reports.php†L430-L520】【F:src/Admin/FunnelAnalysisAdmin.php†L120-L270】

## Performance Observations
- **Cron tasks iterate every client synchronously.** Daily report generation loops all `cliente` posts and renders PDFs per request, which may time out on large datasets; batching or queueing will be required.【F:src/Helpers/ReportScheduler.php†L66-L112】
- **Synchronous external fetches in admin requests.** Report and platform connection handlers hit third-party APIs during admin form submissions, impacting latency; consider async jobs or caching strategies in later phases.【F:src/Admin/Reports.php†L430-L484】【F:src/Admin/PlatformConnections.php†L120-L220】

## Compatibility & Maintainability Notes
- **Standalone legacy scripts** (`settings-manager.php`, `health-monitor.php`) bypass the autoloader and modern coding standards; they will need refactoring or wrappers to align with namespaces and Composer autoloading.【F:settings-manager.php†L1-L200】【F:health-monitor.php†L1-L120】
- **Extensive option surface.** Numerous option keys (wizard states, API keys, cache stats) require schema documentation and migration safeguards before upgrades are introduced.【F:src/Setup/SettingsManager.php†L16-L210】【F:src/Admin/Settings.php†L1740-L2896】

## Next Steps
1. Configure PHPCS/PHPStan and baseline linting (Phase 2).
2. Audit AJAX/REST handlers for nonce/capability coverage and sanitization.
3. Plan refactors for cron workloads, logging abstraction, and legacy utility scripts.
4. Document option schemas and prepare migration strategies for future upgrades.

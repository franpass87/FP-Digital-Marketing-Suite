# FP Digital Marketing Suite

## Overview
FP Digital Marketing Suite is a private WordPress plugin to automate marketing performance reporting, anomaly detection, and enterprise-grade alerting across multiple channels.

## Features
- Clients & datasources (GA4, GSC, Google Ads/Meta Ads CSV, Clarity CSV, Generic CSV)
- Report builder with HTML-to-PDF rendering (logo, brand colors, templates)
- Scheduling & reliable cron (system cron + fallback REST tick)
- Email delivery with retry/backoff logic
- Advanced anomaly detection engine:
  - Baseline comparisons, z-score, EWMA, CUSUM, seasonal decomposition
  - Policies per client including thresholds and mute windows
- Multi-channel notifications:
  - Email (digest + retry)
  - Slack / Teams webhooks
  - Telegram bot messaging
  - Generic Webhook with HMAC signing
  - Twilio SMS stub integration
- Comprehensive admin UI: Clients, Data Sources, Schedules, Templates, Settings, Logs, Health, QA Automation
- REST API + WP-CLI command coverage
- QA Automation suite (seed/run/anomalies/status/cleanup via Admin or REST)
- GitHub Actions workflow for source/release ZIPs, checksums, and tagged releases
- Client Overview dashboard for real-time KPI snapshots, anomalies, connector health, and quick remediation actions

## Client Overview Dashboard
The **FP Suite → Overview** page surfaces real-time KPI cards, inline sparklines, anomaly snapshots, datasource health, and job controls per client without waiting for PDF reports. Filters support date presets and custom ranges, auto-refresh can be toggled per user, and responses are cached briefly to stay responsive. REST endpoints under `/wp-json/fpdms/v1/overview/*` power the UI so data can also be consumed programmatically.

## Installation
1. Place the plugin folder under `wp-content/plugins/`.
2. Run `composer install --no-dev` inside the plugin directory (installs mPDF dependencies).
3. Add `define('DISABLE_WP_CRON', true);` to `wp-config.php`.
4. Configure a system cron job every 5 minutes:
   ```bash
   */5 * * * * curl -sS https://YOUR_SITE/wp-cron.php?doing_wp_cron=1 >/dev/null 2>&1
   ```
   Or with WP-CLI:
   ```bash
   */5 * * * * wp --path=/var/www/html cron event run --due-now --quiet
   ```

## QA Automation
Admin interface: **FP Suite → QA Automation**
- Controls: Seed, Run Report, Trigger Anomalies, Show Status, Cleanup, Run All
- Inline JSON output for each action

REST endpoints (requires `X-FPDMS-QA-KEY` header):
- `POST /wp-json/fpdms/v1/qa/seed`
- `POST /wp-json/fpdms/v1/qa/run`
- `POST /wp-json/fpdms/v1/qa/anomalies`
- `POST /wp-json/fpdms/v1/qa/all`
- `GET /wp-json/fpdms/v1/qa/status`
- `POST /wp-json/fpdms/v1/qa/cleanup`

## Advanced Anomalies
- Algorithms: z-score, EWMA, CUSUM, seasonal baseline
- Per-client thresholds with warning and critical levels
- Supports mute windows, rolling baselines, and daily/weekly seasonality
- Logs detailed anomaly context including score and expected vs. actual metrics

## Notifications
- Email with retry/backoff and digest batching
- Slack / Teams webhook delivery
- Telegram bot messaging
- Generic Webhook (JSON payload + HMAC signature)
- Optional Twilio SMS stub (logs for audit only)
- Routing, cooldowns, and deduplication to avoid alert fatigue

## Cron/Build
- System cron integration is preferred for reliability
- Fallback REST tick endpoint protected by QA key
- Locking via transient plus DB row to avoid overlapping runs
- Health page reports last tick, next run, and supports manual “Force Tick”

## CI/CD
- GitHub Actions workflow `.github/workflows/build-zip.yml`:
  - Runs PHP linting
  - Builds source and release ZIP packages plus checksums
  - Uploads build artifacts
  - Publishes a GitHub Release when tags are pushed

## Release process
- Bump the plugin version and build the distributable ZIP locally:
  ```bash
  bash build.sh --bump=patch
  ```
- Optionally set an explicit semantic version:
  ```bash
  bash build.sh --set-version=1.2.3
  ```
- Grab the generated archive under `build/` and upload it to WordPress.
- Tag the release to trigger the GitHub Action that publishes the packaged ZIP (`git tag v1.2.3 && git push origin v1.2.3`).

## Changelog
### v0.1.0
- Initial release with enterprise features
- Added QA automation, anomaly detection engine, multi-channel notifications, and CI/CD build pipeline

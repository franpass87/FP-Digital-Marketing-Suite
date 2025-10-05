# Overview

FP Digital Marketing Suite automates marketing performance reporting, anomaly detection, and multi-channel alerts for private WordPress operations. It centralises client configuration, connectors, scheduling, PDF rendering, and delivery workflows inside the WordPress admin.

## Key Capabilities

- **Data connectors**: GA4, Google Search Console, Google Ads, Meta Ads CSV, Microsoft Clarity CSV, and generic CSV imports per client, with credential sources configurable via JSON uploads or constants.
- **Report automation**: HTML templates with reusable presets, token replacement, and PDF rendering dispatched according to schedules.
- **Queue reliability**: Dedicated `fpdms_5min` cron interval, queue locking, retention cleanup, and REST fallback tick endpoint.
- **Anomaly detection**: Combined z-score, EWMA, CUSUM, and seasonal baselines with per-client thresholds and mute windows.
- **Notifications**: Email with retry/backoff, Slack, Microsoft Teams, Telegram, generic webhooks (with HMAC), and Twilio SMS audit logging.
- **QA tooling**: REST namespace and admin pages to seed fixtures, run end-to-end flows, inspect status, and clean up generated data.

## Admin Experience

- Configure clients (logo, contact, and retention metadata), data sources, and retention under the FP Suite menu.
- Manage schedules, templates, and anomaly policies with inline validation and nonce-protected forms.
- Monitor queue status from the Health page, trigger emergency ticks, and review last run metadata.
- Use the Overview dashboard for live KPIs, sparklines, connector health, and anomaly highlights.

## Automation Interfaces

- **REST**: `/wp-json/fpdms/v1/tick`, `/wp-json/fpdms/v1/qa/*`, `/wp-json/fpdms/v1/anomalies/*`.
- **WP-CLI**: `wp fpdms run`, `queue:list`, `anomalies:*`, `repair:db`.
- **Cron events**: `fpdms_cron_tick` and `fpdms_retention_cleanup` orchestrate scheduled work.

Refer to `docs/architecture.md` for component-level detail and `docs/faq.md` for operational guidance.

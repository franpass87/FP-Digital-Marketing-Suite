# FP Digital Marketing Suite

> Automates marketing performance reporting, anomaly detection, and multi-channel alerts for private WordPress operations.

| Field | Value |
| ----- | ----- |
| Name | FP Digital Marketing Suite |
| Version | 0.1.1 |
| Author | [Francesco Passeri](https://francescopasseri.com) |
| Author Email | info@francescopasseri.com |
| Requires WordPress | 6.4 |
| Tested up to | 6.4 |
| Requires PHP | 8.1 |
| License | [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) |

## About

FP Digital Marketing Suite centralises scheduled marketing reports, connector management, PDF rendering, anomaly detection, and alert routing inside a private WordPress installation. It is built for agencies that need reproducible client deliverables without exposing dashboards publicly.

## Features

- Client-centric data sources for Google Analytics 4, Google Search Console, Google Ads, Meta Ads CSV, Clarity CSV, and generic CSV exports.
- Drag-and-drop HTML templates with token replacement and PDF rendering for branded deliverables.
- Hardened scheduling engine with a custom five-minute cron interval, background queue, and fallback REST tick endpoint.
- Advanced anomaly detection stack combining z-score, EWMA, CUSUM, and seasonal baselines with per-client thresholds and mute windows.
- Multi-channel notifications (email with retry, Slack, Microsoft Teams, Telegram, generic webhooks, Twilio SMS audit log).
- Extensive admin UI covering Clients, Data Sources, Schedules, Templates, Settings, Logs, Health, Overview, QA Automation, and Anomalies.
- REST API namespace and WP-CLI commands for automation and QA workflows.

## Installation

1. Upload the plugin to `wp-content/plugins/` or install it from the WordPress admin.
2. Run `composer install --no-dev` in the plugin directory to pull in the PDF renderer.
3. Activate the plugin and visit **FP Suite → Settings** to configure branding assets, SMTP credentials, retention, and alert routing.
4. Disable WP-Cron and schedule a system cron every five minutes:
   ```bash
   */5 * * * * curl -sS https://YOUR-SITE/wp-cron.php?doing_wp_cron=1 >/dev/null 2>&1
   ```
   Or, using WP-CLI:
   ```bash
   */5 * * * * wp --path=/path/to/wp cron event run --due-now --quiet
   ```

## Usage

### Admin workflow

- Configure clients, connectors, and scheduling policies from the FP Suite menu.
- Build and preview HTML report templates before enabling automated PDF delivery.
- Monitor queue health, cron status, and anomaly summaries in **FP Suite → Overview** and **FP Suite → Health**.
- Trigger QA automation to seed fixtures, run end-to-end tests, simulate anomalies, and clean up data safely.

### REST endpoints

- `POST /wp-json/fpdms/v1/tick?key=YOUR_SECRET` — force the queue when cron is paused (rate limited to one call every 120 seconds).
- QA automation namespace (`/wp-json/fpdms/v1/qa/*`) supports seeding, running, anomaly simulation, status checks, and cleanup with the `X-FPDMS-QA-KEY` header or `qa_key` parameter.
- Anomaly engine endpoints:
  - `POST /wp-json/fpdms/v1/anomalies/evaluate?client_id=ID&from=YYYY-MM-DD&to=YYYY-MM-DD`
  - `POST /wp-json/fpdms/v1/anomalies/notify?client_id=ID`

### WP-CLI commands

- `wp fpdms run --client=ID --from=YYYY-MM-DD --to=YYYY-MM-DD`
- `wp fpdms queue:list`
- `wp fpdms anomalies:scan --client=ID`
- `wp fpdms anomalies:evaluate --client=ID [--from=YYYY-MM-DD --to=YYYY-MM-DD]`
- `wp fpdms anomalies:notify --client=ID`
- `wp fpdms repair:db`

## Hooks & Filters

| Hook | Type | Description |
| ---- | ---- | ----------- |
| `fpdms_cron_tick` | Action | Triggered every queue cycle to process scheduled marketing jobs. |
| `fpdms/health/force_tick` | Action | Invoked when administrators request an immediate queue tick from the Health page. |
| `fpdms_retention_cleanup` | Action | Daily cron event that purges stale reports and queue artifacts. |
| `cron_schedules` | Filter | Extended with the custom `fpdms_5min` interval (300 seconds). |
| `phpmailer_init` | Action | Used to configure the mailer before outbound notifications are sent. |

The plugin text domain is `fp-dms` and translations live under `languages/`.

## Support

- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- Issues: [GitHub Issues](https://github.com/francescopasseri/FP-Digital-Marketing-Suite/issues)
- Documentation: see the `docs/` directory for architecture, overview, and FAQ guides.

## Development

Install dependencies and keep metadata in sync with:

```bash
composer sync:author
npm run sync:docs
npm run changelog:from-git
```

## Changelog

Refer to [CHANGELOG.md](./CHANGELOG.md) for release notes (Keep a Changelog, SemVer).

=== FP Digital Marketing Suite ===
Contributors: francescopasseri
Donate link: https://francescopasseri.com
Tags: marketing, analytics, reports, automation, alerts
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automates marketing performance reporting, anomaly detection, and multi-channel alerts for private WordPress operations.
Automates marketing performance reporting, anomaly detection, and multi-channel alerts for private WordPress operations.

== Description ==

FP Digital Marketing Suite centralises scheduled marketing reports, data source connectors, PDF rendering, queue reliability, and anomaly detection inside a private WordPress instance. It was designed to support agency workflows that need reproducible client deliverables without exposing dashboards publicly.

= Highlights =

* Connect Google Analytics 4, Google Search Console, Google Ads, Meta Ads CSV, Clarity CSV, and generic CSV data sources per client.
* Supply GA4 and GSC credentials via JSON uploads or secure `wp-config.php` constants instead of pasting secrets in the UI.
* Build HTML templates with reusable presets, branding tokens, and render them to PDF for automated delivery.
* Schedule recurring jobs with a hardened cron runner, background queue, and REST-based fallback tick endpoint.
* Detect anomalies with z-score, EWMA, CUSUM, and seasonal baselines; route alerts per client with thresholds and mute windows.
* Deliver notifications by email (with retry), Slack, Microsoft Teams, Telegram, generic webhooks, and a Twilio SMS audit stub.
* Maintain dedicated admin pages for Clients, Data Sources, Schedules, Templates, Settings, Logs, Health, Overview, QA Automation, and Anomalies.

= Reliability & Security =

* Email delivery retries automatically with exponential backoff and audit logging.
* Queue locking and retention policies prevent overlapping runs and manage storage growth.
* Force ticks securely via the REST namespace using a shared secret to recover from cron interruptions.
* Admin-only tools seed QA fixtures, run synthetic tests, and clean up generated data in one click.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin through the "Plugins" menu.
3. Run `composer install --no-dev` inside the plugin directory to install the PDF renderer.
4. Visit **FP Suite → Settings** to configure branding assets, SMTP credentials, retention windows, and notification routing.

== Usage ==

* Configure clients (logo included), connectors, and scheduling rules from the FP Suite admin menu.
* Build branded report templates and preview HTML output before enabling automatic PDF delivery.
* Monitor queue health, cron status, and anomaly summaries from **FP Suite → Overview** and **FP Suite → Health**.
* Trigger QA automation from the dedicated admin page to seed fixtures, execute end-to-end runs, and validate anomaly routing.

== Cron configuration ==

Disable the default WP-Cron runner and trigger the marketing queue with a real cron job for reliability.

1. Edit `wp-config.php` and add:
```
define( 'DISABLE_WP_CRON', true );
```
2. Configure a system cron to run every 5 minutes:
```
*/5 * * * * curl -sS https://YOUR-SITE/wp-cron.php?doing_wp_cron=1 >/dev/null 2>&1
```
Alternatively, use WP-CLI:
```
*/5 * * * * wp --path=/path/to/wp cron event run --due-now --quiet
```

== REST endpoints ==

Retrieve the secret tick key from **FP Suite → Settings** and call:
```
POST https://YOUR-SITE/wp-json/fpdms/v1/tick?key=YOUR_SECRET
```
Calls are rate-limited to once every 120 seconds.

QA automation endpoints (require the `X-FPDMS-QA-KEY` header or `qa_key` body parameter):
* `POST /wp-json/fpdms/v1/qa/seed`
* `POST /wp-json/fpdms/v1/qa/run`
* `POST /wp-json/fpdms/v1/qa/anomalies`
* `POST /wp-json/fpdms/v1/qa/all`
* `GET  /wp-json/fpdms/v1/qa/status`
* `POST /wp-json/fpdms/v1/qa/cleanup`

Anomaly evaluation endpoints (requires `manage_options` and a nonce):
* `POST /wp-json/fpdms/v1/anomalies/evaluate?client_id=ID&from=YYYY-MM-DD&to=YYYY-MM-DD`
* `POST /wp-json/fpdms/v1/anomalies/notify?client_id=ID`

== CLI commands ==

After installing WP-CLI, control the suite via:

* `wp fpdms run --client=ID --from=YYYY-MM-DD --to=YYYY-MM-DD`
* `wp fpdms queue:list` — lists queued jobs and their status.
* `wp fpdms anomalies:scan --client=ID`
* `wp fpdms anomalies:evaluate --client=ID [--from=YYYY-MM-DD --to=YYYY-MM-DD]`
* `wp fpdms anomalies:notify --client=ID`
* `wp fpdms repair:db`

== FAQ ==

= Does the plugin expose client data publicly? =
No. All dashboards live inside the WordPress admin and the REST namespace requires authenticated requests and capability checks.

= Can I add custom connectors? =
Yes. Implement `FP\\DMS\\Services\\Connectors\\DataSourceProviderInterface` and register it via `ProviderFactory` to expose new data sources.

= How do I customise email delivery? =
Configure SMTP credentials under **FP Suite → Settings** or hook `phpmailer_init` to adjust headers before messages are dispatched.

= What happens if a cron run fails? =
The queue locks failed jobs, retries with exponential backoff, and exposes the last tick status on the Health page. Use the REST fallback tick to recover quickly.

= Is localisation supported? =
Yes. Load translations from the `/languages` directory. The text domain is `fp-dms`.

== Support ==

For assistance open an issue on GitHub or email info@francescopasseri.com.

== Changelog ==

See [CHANGELOG.md](https://github.com/francescopasseri/FP-Digital-Marketing-Suite/blob/main/CHANGELOG.md) for the full history.

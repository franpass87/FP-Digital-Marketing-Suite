=== FP Digital Marketing Suite ===
Contributors: francescopasseri
Donate link: https://francescopasseri.com
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated marketing reports for a private WordPress installation.

== Description ==

FP Digital Marketing Suite centralises report scheduling, connector management, PDF rendering, email delivery, anomalies detection, and fallback cron orchestration for a private WordPress site.

== Reliability ==

* Report emails use up to three delivery attempts with exponential backoff and are logged for auditing. Configure SMTP credentials in **FP Suite → Settings** if you prefer a dedicated mail relay.
* Detected anomalies trigger owner email alerts (and optional webhooks) with the impacted metrics, ensuring issues are surfaced even when the queue runs unattended.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin through the "Plugins" menu.
3. Visit **FP Suite → Settings** to configure branding, SMTP, and retention.

== Cron configuration ==

It is strongly recommended to disable the default WP-Cron runner and trigger the marketing queue with a real cron job.

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

== Fallback tick endpoint ==

A REST endpoint is available when you need to force the queue externally. Retrieve the secret tick key from **FP Suite → Settings** and invoke:
```
POST https://YOUR-SITE/wp-json/fpdms/v1/tick?key=YOUR_SECRET
```
Calls are rate-limited to once every 120 seconds.

== QA automation ==

Administrators can drive an end-to-end QA run from **FP Suite → QA Automation**. The page exposes buttons for seeding fixtures, executing the monthly report, triggering synthetic anomalies, inspecting status, and cleaning up the QA client. All actions require the shared QA key displayed on the page.

For scripted runs call the REST namespace (include the `X-FPDMS-QA-KEY` header or `qa_key` body parameter):

* `POST /wp-json/fpdms/v1/qa/seed`
* `POST /wp-json/fpdms/v1/qa/run`
* `POST /wp-json/fpdms/v1/qa/anomalies`
* `POST /wp-json/fpdms/v1/qa/all`
* `GET  /wp-json/fpdms/v1/qa/status`
* `POST /wp-json/fpdms/v1/qa/cleanup`

Each endpoint returns machine-readable JSON detailing the outcome, warnings, and any generated report metadata.

== CLI commands ==

After installing WP-CLI you can control the suite via:

* `wp fpdms run --client=ID --from=YYYY-MM-DD --to=YYYY-MM-DD`
* `wp fpdms queue:list`
* `wp fpdms anomalies:scan --client=ID`
* `wp fpdms repair:db`

== Support ==

For assistance contact info@francescopasseri.com.

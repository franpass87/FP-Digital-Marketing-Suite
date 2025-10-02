# Frequently Asked Questions

## Do I need to expose the Overview dashboard publicly?
No. The plugin is designed for private WordPress installs. All dashboards are WordPress admin pages behind capability checks, and the REST namespace requires authenticated requests or shared QA keys.

## How do I configure the queue to run reliably?
Disable WP-Cron (`define('DISABLE_WP_CRON', true);`) and create a system cron that hits `wp-cron.php` or runs `wp cron event run --due-now` every five minutes. The plugin registers a custom `fpdms_5min` interval and processes jobs on the `fpdms_cron_tick` event.

## Where are reports and templates stored?
Templates live in the WordPress database while generated PDFs are written to the uploads directory by default. Retention policies configured in **FP Suite → Settings** control how long artifacts are kept before the `fpdms_retention_cleanup` event purges them.

## Can I add a custom data connector?
Yes. Implement `FP\\DMS\\Services\\Connectors\\DataSourceProviderInterface`, provide the necessary credential form fields, and register the provider via `ProviderFactory`. The admin UI will then expose it when creating or editing data sources.

## How do anomaly notifications avoid alert fatigue?
Policies can define thresholds, mute windows, and cooldowns per channel. The notification router deduplicates alerts using transient locks and digest batching so clients only receive actionable updates.

## How can I trigger the QA automation from scripts?
Use the REST namespace with the `X-FPDMS-QA-KEY` header (or `qa_key` body parameter). For example: `POST /wp-json/fpdms/v1/qa/all` seeds data, runs reports, simulates anomalies, and returns status JSON in one call.

## Is localisation supported?
Yes. The text domain is `fp-dms` and translations are loaded from the `languages/` directory during `plugins_loaded`.

## What happens if email delivery fails?
Emails are dispatched through PHPMailer with retry and exponential backoff. Failures are logged and surfaced on the Logs page; you can configure SMTP credentials in **FP Suite → Settings** or hook `phpmailer_init` for custom mailers.

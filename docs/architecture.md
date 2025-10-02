# Architecture

## Core Modules

- **Entry point**: `fp-digital-marketing-suite.php` bootstraps autoloading, registers REST routes, queues, mailer, and admin menu.
- **Domain entities** (`src/Domain/Entities/*`): value objects for clients, schedules, data sources, templates, anomalies, and report jobs.
- **Repositories** (`src/Domain/Repos/*`): encapsulate persistence and hydration of domain entities from custom tables.
- **Services** (`src/Services/*`):
  - `Connectors` — providers for GA4, Google Search Console, Google Ads, Meta Ads CSV, Clarity CSV, and generic CSV imports.
  - `Reports` — builders and HTML/PDF renderers powering scheduled deliverables.
  - `Overview` — cache, assembler, and presenter powering the admin dashboard tiles and sparklines.
  - `Anomalies` — baseline, detector, and engine classes combining statistical methods for alerting.
  - `Qa` — automation runner for seeding fixtures and executing synthetic flows.
- **Infrastructure** (`src/Infra/*`): queue, cron, database helpers, locking, logging, mailer bootstrap, retention policies, and notification router.
- **Admin UI** (`src/Admin/*`): menu registration, page controllers, and shared notices for WordPress admin screens.
- **HTTP layer** (`src/Http/*`): REST controllers for overview data, QA automation, anomalies, and queue tick endpoints.
- **CLI** (`src/Cli/Commands.php`): registers WP-CLI commands for reports, queue inspection, anomaly evaluation, and repairs.

## Data Flow

1. **Scheduling** — `Cron::bootstrap()` registers a five-minute interval and the `fpdms_cron_tick` event. `Activator` schedules both the queue tick and daily retention cleanup.
2. **Queue processing** — `Infra\Queue` locks jobs, invokes the report builder, and stores PDF output. Failures are retried with exponential backoff.
3. **Reporting** — `Services\Reports\ReportBuilder` composes metrics from connectors, applies templates, and hands rendering to `HtmlRenderer` and `PdfRenderer`.
4. **Delivery** — `Infra\Mailer` configures PHPMailer, while `Infra\NotificationRouter` orchestrates email, Slack, Teams, Telegram, webhook, and Twilio routes.
5. **Anomaly detection** — `Services\Anomalies\Engine` consumes baselines, detectors, and policies before dispatching notifications through the router.
6. **Overview dashboard** — `Services\Overview\Assembler` collects KPI aggregates, caches them with `Services\Overview\Cache`, and exposes REST responses for the React admin UI.

## Storage & Configuration

- Custom tables and options are created by `Infra\DB` and `Infra\Options` using the `fpdms_` prefix.
- Global settings, QA keys, and retention windows are stored in WordPress options while per-user preferences live under `_fpdms_prefs`.
- Templates, reports, and logs are stored in the plugin directory and the WordPress uploads folder depending on configuration.

## Extensibility Points

- **Cron events**: `fpdms_cron_tick` and `fpdms_retention_cleanup` can be hooked for custom automation.
- **REST**: extend the `/wp-json/fpdms/v1/*` namespace or consume existing endpoints for integrations.
- **Mail**: hook `phpmailer_init` to customise SMTP or add BCC recipients; override `NotificationRouter` for additional channels.
- **Data sources**: implement `Services\Connectors\DataSourceProviderInterface` and register it with `ProviderFactory` to add bespoke connectors.

## Security Considerations

- All admin actions use nonces and capability checks (typically `manage_options`).
- REST endpoints validate the QA key or require authenticated requests with proper capabilities.
- Queue locks and retention policies protect against concurrent processing and unbounded storage growth.
- Sensitive credentials (SMTP, webhooks, tokens) are sanitised via `Support\Validation` and stored using WordPress options APIs.

## Internationalisation

- Text domain: `fp-dms`
- Language files: `languages/`
- `fp_dms_load_textdomain()` loads translations on `plugins_loaded`.

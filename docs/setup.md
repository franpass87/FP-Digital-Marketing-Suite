# Development Environment Setup

This guide describes how to prepare a local environment for FP Digital Marketing Suite and mirrors the checks performed by [`system-requirements-check.php`](../system-requirements-check.php).

## Platform Requirements

| Component | Minimum Version | Notes |
| --- | --- | --- |
| PHP | 7.4.0 | Match the requirement enforced by the checker. PHP 8.x is recommended for better performance. |
| WordPress | 5.0.0 | Tested against the minimum defined in the checker; newer LTS releases are encouraged. |
| MySQL | 5.6.0 | MariaDB 10.1+ is an acceptable drop-in replacement. |
| Memory limit | 128 MB | Increase to 256 MB for large datasets. |
| Max execution time | 30 seconds | Set higher for long-running imports if necessary. |
| Upload max filesize | 2 MB | Increase to support larger CSV/JSON imports. |

## PHP Extensions

The following extensions are required for the plugin to function (mirrors `FP_DMS_SystemChecker::REQUIRED_EXTENSIONS`):

- `curl`
- `json`
- `mbstring`
- `mysqli`
- `openssl`
- `zip`

Additionally, the checker flags these as recommended for improved performance and features:

- `gd`
- `imagick`
- `redis`
- `memcached`

## Dependency Installation

1. Install PHP dependencies:
   ```bash
   composer install
   ```

2. Install JavaScript tooling (only if/when a `package.json` exists for asset builds):
   ```bash
   npm install
   ```

## Docker-Based WordPress Environment

1. Start the stack:
   ```bash
   docker-compose up -d
   ```

2. Complete the WordPress installation at <http://localhost:8080> and activate **FP Digital Marketing Suite** from the Plugins screen.

3. Enable WP-CLI inside the container:
   ```bash
   docker-compose exec wordpress wp cli info
   ```

4. (Optional) Disable the built-in cron and trigger it manually via WP-CLI for deterministic runs:
   ```bash
   docker-compose exec wordpress wp config set DISABLE_WP_CRON true --raw
   docker-compose exec wordpress wp cron event run --due-now
   ```

## Seeding Fake OAuth Credentials

`src/DataSources/GoogleOAuth.php` reads client details from the `fp_digital_marketing_api_keys` option. Seed development-safe credentials via WP-CLI:

```bash
docker-compose exec wordpress wp fp-dms setup ga4 \
  --property-id=GA4-DEMO-PROPERTY \
  --client-id=demo-client-id.apps.googleusercontent.com \
  --client-secret=demo-client-secret
```

This populates the option with encrypted values so that OAuth-dependent features treat the environment as configured while still using non-production credentials.

## Running the Requirements Checker

After the stack is up, run the bundled checker to verify the environment:

```bash
docker-compose exec wordpress php wp-content/plugins/fp-digital-marketing-suite/system-requirements-check.php
```

The output surfaces any missing extensions or configuration mismatches before you proceed with development.

# Upgrade Guide

This document outlines the recommended process for upgrading the FP Digital Marketing Suite to version 1.2.0 and beyond.

## Before You Upgrade

1. **Back Up** your database and `wp-content/uploads/` directory.
2. **Review Requirements** in [`docs/setup.md`](docs/setup.md) and confirm the server still meets the minimum PHP 7.4 and WordPress 5.0 baselines.
3. **Check Customizations** such as mu-plugins or overrides that hook into FP DMS actions and filters. Note any compatibility changes documented in [`docs/audit/compatibility.md`](docs/audit/compatibility.md).
4. **Schedule Downtime** during a maintenance window so cache purges and background migrations can complete without user load.

## Upgrade Paths

### Updating from 1.1.x

1. Download `dist/fp-digital-marketing-suite-1.2.0.zip`.
2. Deactivate the existing plugin in WordPress.
3. Upload and activate the new version.
4. The upgrade registry will automatically migrate wizard menu state, cache schemas and stored TTL overrides. Review cache TTLs under **Settings → Performance** if you previously customized them.
5. Confirm that scheduled reports, alerts and sync tasks are still active. If you use object caching, manually flush it to clear any external caches.

### Updating from 1.0.x

1. Follow the same steps as above, upgrading sequentially if possible (1.0.x → 1.1.0 → 1.2.0).
2. Read the change notes for 1.1.0 in [`CHANGELOG.md`](CHANGELOG.md) to prepare for new features such as Reporting Workspace and Alert Center.
3. Validate role capabilities via **Settings → Permissions** after the upgrade to ensure new defaults match your organization.

### Fresh Installations

Install the plugin normally from the packaged ZIP and walk through the onboarding wizard. All migrations will detect a fresh install and skip data conversions.

## Post-Upgrade Checklist

- Run automated tests if you maintain a fork: `php phpunit.phar --configuration phpunit.xml`.
- Inspect `docs/audit/release.md` for manual QA steps executed during the 1.2.0 release.
- Execute `verify-deployment.php` and review `docs/audit/security.md` if you customize access controls.
- Monitor the **Runtime Logs** (see `docs/audit/runtime-issues.log`) for unexpected notices after the first cron cycle.

## Troubleshooting

If an upgrade stalls or you notice stale analytics data:

- Trigger the cache purge under **Tools → FP DMS Diagnostics**.
- Clear any persistent object cache (Redis/Memcached) and regenerate the dashboard.
- Review the `PerformanceCache` entries via WP-CLI using the commands outlined in `docs/audit/perf.md`.
- For multisite installs, switch to each site dashboard and ensure the plugin remains active and migrations reported success.

For further assistance open a GitHub issue or contact [info@francescopasseri.com](mailto:info@francescopasseri.com).

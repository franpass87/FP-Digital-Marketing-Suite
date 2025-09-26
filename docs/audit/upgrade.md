# Phase 10 – Upgrade & Migrations

## Summary
- Bumped the plugin release metadata to **1.3.0** across the bootstrap, readme files, changelog and PHPStan harness.
- Confirmed that menu registry redirects and list-table refits preserve slug compatibility so new IA changes remain back compatible.
- Documented how reusable admin components interact with existing hooks and filters to minimize upgrade friction.

## Migration Details
- `SettingsManager::upgrade_menu_state_schema()` normalizes legacy wizard menu payloads, sanitizes registered slugs, enforces allowed statuses and stamps schema metadata.
- `PerformanceCache::upgrade_cache_schema()` sanitizes cache settings, enforces TTL minimums, upgrades schema markers and removes stale cache index entries.
- `DigitalMarketingSuite::purge_runtime_cache_layers()` invalidates plugin caches, flushes the WordPress object cache/runtime store and resets OPcache (when enabled) after migrations run.

## Operational Notes
- Upgrade routines are network-aware and will execute on each site when the plugin is network activated.
- Cache purges run automatically, so schedule production upgrades during low-traffic windows to absorb the cache warm-up.
- Schema versions and `last_migrated_at` timestamps are stored with the menu state and cache settings to aid support investigations.

# Phase 6 – Settings Hardening & Validation

## Overview
Phase 6 focuses on reinforcing the security and resilience of the plugin settings screens. The refitted UI from the previous phase now includes consistent validation, capability checks, and meaningful feedback so that administrators can safely manage configuration across single and multisite installs.

## Key Enhancements
- **Nonce verification** – Every privileged action (saving options, triggering cache tasks, disconnecting OAuth, running manual sync) now verifies dedicated nonces via the shared `Security::verify_nonce_with_logging()` helper or WordPress core utilities.
- **Capability gating** – Cache controls, API key updates, and manual synchronisation all enforce `Capabilities::MANAGE_SETTINGS` or `Capabilities::MANAGE_DATA_SOURCES`, preventing lower-privileged roles from mutating critical configuration.
- **Sanitized inputs** – Request parameters retrieved from `$_GET`/`$_POST` are normalised with `sanitize_key()`/`sanitize_text_field()`/`wp_unslash()` before use. Numbers are cast with `absint()` or `(float)` to avoid type juggling and provide safe translations in notices.
- **Safe redirects** – All admin redirects now run through `wp_safe_redirect()` to avoid open redirect vectors when cleaning callback URLs or returning after manual actions.
- **Escaped output** – Dynamic CSS class names and notice content printed in the settings screen now pass through `esc_attr()`/`esc_html()` to ensure A11y-friendly markup without risking HTML injection.
- **Robust feedback** – Success/error notices leverage `add_settings_error()` with translated, sanitised copy so administrators receive actionable guidance when remote APIs fail, tokens expire, or cache operations encounter issues.

## Operational Guidance
1. **After saving settings**, confirm that `settings_errors()` shows any validation messages immediately beneath the page header.
2. **For manual syncs or OAuth disconnects**, ensure WP-CLI automation includes the appropriate nonce when simulating POST requests.
3. **In multisite**, leverage the same capabilities—network administrators retain access while site-level editors remain blocked by `Capabilities::current_user_can()` checks.
4. **Logging & auditing** – Security-sensitive workflows (cache invalidation, sitemap clearance, OAuth failures) continue to write to the security log for review during QA.

## Next Steps
With the settings layer hardened, Phase 7 will extend the same rigour to list tables, ensuring bulk operations, Screen Options, and Help Tabs follow identical security and UX standards.

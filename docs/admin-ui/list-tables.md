# Admin List Tables – Phase 7

## UTM Campaign Manager
- Replaced the bespoke table markup with a dedicated `UTMCampaignsListTable` that extends `WP_List_Table`, preserving slugs and AJAX entry points while aligning actions with WordPress conventions.
- Added row actions for viewing, editing, and deleting campaigns, all guarded by nonces and capability checks, and wired the delete links into the existing nonce-protected controller.
- Surfaced status pills that map to the design token palette (`fp-dms-status-pill--active|--paused|--completed`) so campaign state is colour coded without inline CSS.

## Bulk Actions & Filters
- Introduced bulk actions for delete, activate, pause, and mark completed; they reuse the campaign model to persist status updates and display admin notices summarising the result.
- Added view links (`Tutte`, `Attive`, `In pausa`, `Completate`) alongside a status filter dropdown inside the tablenav so operators can segment large datasets quickly.
- Upgraded the search field to the native list-table search box, keeping keyboard focus ordering and Screen Options compatibility intact.

## Screen Options & Help
- Registered a `Campagne per pagina` screen option (default 20) so power users can raise or lower pagination without editing code.
- Added a contextual help tab that documents how to filter, customise visible columns, and apply bulk actions, with a sidebar of quick tips for tracking hygiene.
- Hooked into the admin notice pipeline so both successful and failed bulk operations surface inline feedback with dismissible notices.

## Implementation Notes
- The list table pulls filters from `$_REQUEST['status']` and `$_REQUEST['s']`, sanitising via `sanitize_key()`/`sanitize_text_field()` to maintain hardening introduced during the settings phase.
- Nonce validation is handled for both row and bulk requests (`utm-campaign-row-action-{$id}` and `bulk-utm_campaigns`) before any destructive operation runs.
- Screen registration relies on the current screen ID containing the page slug, keeping compatibility with both legacy `add_submenu_page` hooks and the centralised menu registry.

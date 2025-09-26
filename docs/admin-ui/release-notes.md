# Admin UI Release Notes – Version 1.3.0

## Overview
Version 1.3.0 completes the Admin UI/UX revamp by rolling the iterative accessibility, design system and information architecture work into a shippable package. The release focuses on delivering a cohesive WordPress-native experience while preserving back-compatibility with historical menu slugs and workflows.

## Highlights
- **Accessible Navigation** – Skip links, landmarked regions and improved focus outlines across the UTM campaign flows.
- **Reusable Components** – Shared headers, cards, forms, notices, tabs and toolbars powering the refreshed admin screens.
- **Menu Registry & Redirects** – Centralized menu registration with sanitized legacy slug redirects and capability enforcement.
- **List Table Enhancements** – Search, sorting, views, bulk actions and contextual help integrated into the UTM campaigns manager.

## Packaging
- Distribution: generated via `./build.sh` (outputs `dist/fp-digital-marketing-suite-1.3.0.zip`)
- Checksum: produced alongside the ZIP by the build script (`dist/fp-digital-marketing-suite-1.3.0.zip.sha256`)
- Build Command: `./build.sh`

## Documentation Updates
- `README.md`, `readme.txt` and `UPGRADE.md` now reference the 1.3.0 admin experience and new upgrade guidance.
- `CHANGELOG.md` documents the admin UI overhaul, packaging details and upgrade notes.
- `docs/audit/release.md` and `docs/audit/upgrade.md` capture verification steps for publishing 1.3.0.

## QA Summary
Manual smoke tests confirmed:
- Menu redirects load the new IA without capability regressions.
- UTM campaign list table actions (view, edit, delete, bulk status change) respect nonce and capability checks.
- Settings forms continue to sanitize, validate and render notices via the shared components helper.

See `docs/admin-ui/qa-results.md` for the detailed execution log from the QA phase.

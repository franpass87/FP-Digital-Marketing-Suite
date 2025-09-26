# Admin Design Tokens & Base Styles

Phase **[3] DESIGN TOKENS & STYLE** establishes the shared language for
spacing, typography, and component primitives used across the refreshed
admin experience. The files are intentionally split between authoring
sources in `assets/src/admin/` and distributable assets in
`assets/dist/admin/`.

> **Nota:** i file sotto `assets/dist/admin/` vengono generati con
> `composer run build:admin-styles` e non sono versionati nel repository.

## Token Overview

| Category | Tokens | Notes |
| --- | --- | --- |
| Typography | `--fp-dms-font-family-*`, `--fp-dms-font-size-*`, `--fp-dms-line-height-*` | Align to WordPress admin scale while adopting Inter as the base stack. |
| Spacing | `--fp-dms-space-0` → `--fp-dms-space-8` | Even 4px increments enable predictable rhythm across components. |
| Radius | `--fp-dms-radius-*` | Rounded corners default to `4px`, with pill utility reserved for badges. |
| Elevation | `--fp-dms-shadow-*` | Soft shadows mirror WP defaults; `--fp-dms-shadow-focus` improves focus rings. |
| Color | `--fp-dms-color-*` | Includes light and dark mode variants, plus semantic palettes for stateful UI. |
| Motion | `--fp-dms-transition-*`, `--fp-dms-ease-*` | Short transitions with accessible easing; global reduced motion guard rails included. |
| Data Viz | `--fp-dms-chart-*` | Palette for charts, matching analytics color scheme in Dashboard widgets. |

Utility classes such as `.fp-dms-u-grid`, `.fp-dms-u-flex`, and
`.fp-dms-u-sr-only` are available for rapid layout adjustments and
accessibility helpers without resorting to bespoke CSS.

## Base Style Application

`assets/src/admin/base.css` scopes structural rules to the `body` element
when it contains the `fp-dms-admin` class. The helper automatically adds
this class on FP Digital Marketing Suite screens to avoid leaking styles
into unrelated admin pages.

Key primitives:

- `.fp-dms-page-header`: Shared header pattern with actions alignment and
  consistent spacing.
- `.fp-dms-card` and modifiers: Card container with spacing and subtle
  elevation.
- `.fp-dms-toolbar`: Flexible toolbar with keyboard-focus enhancements.
- `.fp-dms-form-row`: Form layout that groups label, description, and
  validation messaging while respecting WCAG contrast.
- `.fp-dms-tab-nav`: Tab navigation that mirrors WordPress nav tabs but
  adopts tokenized spacing and state colors.

## Build Workflow

1. Edit the source files under `assets/src/admin/`.
2. Run `composer run build:admin-styles` to copy sources into
   `assets/dist/admin/` with a generated banner.
3. Include the source files in version control; the generated dist files
   remain local artifacts that ship with the packaged build.

The build script is also executed by `build.sh` so packaging always ships
fresh CSS.

## Enqueue Strategy

`FP\DigitalMarketing\Helpers\AdminOptimizations` now enqueues the
`fp-dms-admin-tokens` and `fp-dms-admin-base` styles before existing
optimization assets. The helper registers an `admin_body_class` filter to
append `fp-dms-admin`, keeping styles isolated and ensuring the tokens
are available for subsequent phases (component refits, page updates,
etc.).

## Accessibility Polish (Phase [10])

Phase **[10] A11Y & POLISH FINALE** extends the tokens with
skip-navigation and contrast refinements:

- `--fp-dms-color-focus-ring` now pairs with
  `--fp-dms-color-focus-ring-contrast` and an updated
  `--fp-dms-shadow-focus` to deliver a thicker, high-contrast outline that
  remains visible on light and dark themes.
- `--fp-dms-color-skip-link-*` centralizes the skip-link palette used by
  `.fp-dms-skip-link` so themes can override it with a single variable.
- Status pills use `--fp-dms-status-*` variables and pseudo-element dots
  to meet 4.5:1 text contrast while still conveying state through color.
- Utility class `.fp-dms-admin-screen__main` provides a focusable main
  region that works with the skip link and supports `tabindex="-1"` for
  programmatic focus.

# Phase [10] A11Y & Polish Finale

This phase closes out the iterative admin revamp with targeted
accessibility fixes and UI refinements that sit on top of the refitted
screens delivered earlier in the playbook.

## Keyboard & Landmark Improvements

- Added a persistent `.fp-dms-skip-link` entry point on campaign list,
  edit, and detail screens so keyboard users can jump directly to the
  main content.
- Wrapped primary content in `<main>` regions with `role="main"`,
  `tabindex="-1"`, and contextual `aria-labelledby` / `aria-describedby`
  attributes for assistive technology announcements.
- Documented `.fp-dms-admin-screen__main` as the focus target paired with
  skip links, ensuring consistent spacing and scroll positioning.

## Contrast & Focus Adjustments

- Refined design tokens so focus rings use
  `--fp-dms-color-focus-ring-contrast` and an enlarged
  `--fp-dms-shadow-focus`, making outlines visible above both light and
  dark admin themes.
- Promoted skip-link colors and status pill palettes to tokens to keep
  branding configurable while guaranteeing 4.5:1 contrast.
- Reworked status pills to rely on a neutral background with semantic
  indicator dots instead of low-opacity text colors, maintaining visual
  hierarchy without sacrificing readability.

## Copy & Assistive Text

- Added screen-reader helper text that explains the purpose of the UTM
  campaign creation form and the detail view statistics.
- Marked UTM campaign filters as a search region so assistive technology
  announces the available controls.

## Verification Checklist

- Activate WP_DEBUG and navigate the campaign list, edit, and detail
  pages using only the keyboard. Confirm the skip link receives focus and
  the main region highlights when activated.
- Tab through buttons, tabs, and toolbar actions on any refitted screen
  to confirm the enhanced focus outline is visible against adjacent
  surfaces.
- With a screen reader running, trigger the skip link and verify the
  campaign form and detail view announce their new summaries.
- Test in light and dark admin color schemes to ensure the updated tokens
  keep focus rings and status pills legible.

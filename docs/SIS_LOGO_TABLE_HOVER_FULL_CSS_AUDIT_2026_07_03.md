# SIS Logo + Table Hover Full CSS Audit Fix — 2026-07-03

## Scope
Audited and patched the remaining UI/theme issues reported after the full page theme audit, focusing on:

- Table hover states in light and dark mode
- Old hardcoded table/card CSS conflicts
- Sidebar/logo visibility
- Login logo visibility
- Footer logo visibility
- Icon-only theme toggle

## Changes

- Replaced the old generated `assets/images/logo.svg` with the uploaded logo.
- Replaced `assets/img/logo-rd.svg` with the uploaded logo for older pages that still reference it.
- Replaced favicon source with the uploaded logo SVG.
- Removed the extra visible `COMIUM` text beside the logo in sidebar and login.
- Replaced footer text branding with the uploaded logo.
- Added a final CSS override layer at the bottom of `assets/css/sis-theme.css` so it wins over old page styles.
- Fixed table hover for all standard and custom table classes:
  - `.table`
  - `.table-hover`
  - `.table-custom`
  - `.region-table`
  - `.compact-table`
  - `.sis-searchable-table`
  - `.sis-tt-table`
  - `.dataTable`
- Added dark-mode safe table variables so cells do not stay light and text stays visible.
- Removed visible Light/Dark wording from all theme buttons; the toggle is now icon-only.

## Validation

- PHP syntax check passed.
- JavaScript syntax check passed for theme JS.
- ZIP integrity check passed.

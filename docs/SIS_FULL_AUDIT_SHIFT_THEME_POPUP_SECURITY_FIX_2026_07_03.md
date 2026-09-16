# SIS Full Audit: Shift, Theme, Popup, Owner Visibility Fixes

Date: 2026-07-03

## Main fixes

- Removed automatic conversion of every Bootstrap alert into a center popup. Alerts now stay inline unless a page explicitly calls the branded confirmation modal.
- Kept logout confirmation but stopped unnecessary automatic popups from normal page messages.
- Hid user-facing references to the internal owner role from normal Admin, Supervisor, and Agent views.
- Reworded owner-only UI labels from "Super Admin" to "System Owner" or "Owner" where the owner account itself needs to see the page.
- Repaired shift edit workflow on Admin and Supervisor Shift Allocation pages:
  - Edit now uses safe data attributes instead of fragile inline JavaScript.
  - Edit mode updates the selected shift row by ID.
  - Cancel Edit resets the form.
  - Saving the same shift still updates existing agent/date assignment.
- Added Admin/System Owner shift rule for late attendance:
  - Admin/System Owner can set how many minutes after shift start counts as late.
  - Default is 15 minutes.
  - Reports and attendance badge logic use the configured value.
- Improved theme behavior:
  - Early theme initialization before layout rendering.
  - More complete dark/light styling for legacy cards, tables, forms, dropdowns, modals, pagination and list items.
  - Theme toggle shows visible Light/Dark label.

## Files changed

- `config.php`
- `include/shift_helpers.php`
- `include/side_header.php`
- `include/navbar.php`
- `include/footer.php`
- `assets/js/sis-theme.js`
- `assets/css/sis-theme.css`
- `admin/shift_allocation.php`
- `supervisor/shift_allocation.php`
- `admin/shift_report.php`
- `supervisor/shift_report.php`
- `admin/shift_requests.php`
- `admin/users.php`
- `admin/database_console.php`
- `admin/super_audit_logs.php`
- `admin/run_upgrade.php`
- `help.php`

## Validation

- PHP syntax check passed for all non-doc PHP files.
- Main theme JavaScript syntax check passed.
- ZIP integrity check passed.

## After replacing project

Login as the owner account and run:

`Admin > Owner Tools & System > Run Upgrade`

Then open:

`Admin > Shift Allocation`

Set **Mark agent late after** to the number of minutes you want.

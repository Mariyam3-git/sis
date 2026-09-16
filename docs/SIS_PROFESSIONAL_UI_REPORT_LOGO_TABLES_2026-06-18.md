# SIS Professional UI, Logo, Reports, Forms, and Table Filter Update

## Summary
This update uses the uploaded SIS project as the base and improves the visual consistency across Admin, Agent, and Supervisor pages without rebuilding the PHP/MySQL architecture.

## Main Changes

### Shared Layout and Logo
- Added the uploaded logo to `assets/img/logo-rd.svg`.
- Updated `include/side_header.php` so Admin, Agent, and Supervisor use the same branded sidebar layout.
- Updated `include/navbar.php` with a consistent top header, role label, user chip, theme toggle, and logout action.
- Updated `include/footer.php` to remove duplicate sidebar JavaScript and keep the footer clean.

### Unified Design System
- Rebuilt `assets/css/sis-page-components.css` with one shared professional component system.
- Rebuilt `assets/css/sis-theme.css` with final UI overrides and dark/light theme fixes.
- Standardized cards, forms, inputs, dropdowns, buttons, tables, modals, alerts, reports, dashboards, KPI cards, and support pages.

### Table Filtering
- Rebuilt `assets/js/sis-theme.js`.
- Added native table controls for normal PHP tables:
  - Show 10
  - Show 25
  - Show 50
  - Show 75
  - Show 100
  - Show 150
  - Show All
  - Search
  - Previous / Next
- Updated DataTables pages to use the same record-size options.

### Complaint / Support Form Cleanup
- Removed the visible text `Admin-created Form Fields` from Agent/Supervisor complaint forms.
- Removed the visible warning `No active support form fields found. Please ask Admin to create fields first.`
- If no dynamic fields are active, the form now simply shows the normal standard complaint/support fields.
- Improved Agent and Supervisor complaint/support page styling.

### Handle Timer UI
- Redesigned the `Live Customer Time` widget.
- The timer now has a consistent professional design across Agent and Supervisor customer/sale forms.
- Start time, duration, and completed time are still preserved.

### Reports UI
- Improved report page styling through the shared report, table, filter, and KPI component rules.
- Fixed the Daily Showroom Report layout typo that could print an extra `">` after the shared layout call.
- Kept Complaint / Support Reports under the Admin Reports section.

## Validation
- PHP syntax check passed for all PHP files.
- JavaScript syntax check passed for `assets/js/sis-theme.js`.
- No PHP `<style>` blocks remain.
- No PHP inline `style="..."` attributes remain.
- The uploaded logo file is included in the project.

## Files Changed
- `include/side_header.php`
- `include/navbar.php`
- `include/footer.php`
- `assets/css/sis-page-components.css`
- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`
- `assets/img/logo-rd.svg`
- `support_helpers.php`
- `agent/support.php`
- `supervisor/support.php`
- `config.php`
- `admin/reports.php`
- `admin/daily_showroom_report.php`

## Testing Steps
1. Replace the project files with this ZIP.
2. Hard refresh the browser with `Ctrl + F5`.
3. Login as Admin and check dashboard, reports, support reports, and sales pages.
4. Login as Agent and check dashboard, Add Customer / Sale, complaint/support, profile, and stock pages.
5. Login as Supervisor and check dashboard, Start Customer / Add Sale, sales transactions, complaint/support, and reports.
6. Confirm tables show the record-size filter: `10, 25, 50, 75, 100, 150, All`.
7. Confirm Light/Dark theme works across all pages.
8. Confirm the logo appears in the sidebar.

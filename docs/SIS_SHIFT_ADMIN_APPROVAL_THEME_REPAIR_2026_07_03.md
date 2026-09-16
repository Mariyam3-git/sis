# SIS Shift Admin Approval + Theme Repair — 2026-07-03

## Main changes

- Added Admin/Super Admin shift request approval page: `admin/shift_requests.php`.
- Admin and Super Admin can now approve/reject agent:
  - Early shift-end requests
  - Login access requests
  - Overtime notifications/requests
- Kept Supervisor approval page working at `supervisor/shift_requests.php`.
- Added `Shift Requests` to the Admin/Super Admin sidebar.
- Added `admin/shift_requests.php` to the page access registry.
- Shift request notifications are now sent to:
  - Supervisor for the agent outlet
  - Admin
  - Super Admin
- Agent login-request message now says Supervisor/Admin approval instead of Supervisor only.

## Theme repair

- Restored real light/dark mode switching.
- Removed the effect of the old forced-light override by adding final theme variables.
- Re-enabled the floating and navbar theme toggle buttons.
- Dark mode now applies to cards, tables, forms, dropdowns, modals, sidebar, topbar, notification menus, and common Bootstrap surfaces.
- Theme is applied immediately from localStorage before DOM ready to avoid theme flash/stuck state.

## Validation

- PHP syntax check passed for all PHP files.
- Main theme JavaScript syntax checked with Node.
- ZIP integrity should be tested after packaging.

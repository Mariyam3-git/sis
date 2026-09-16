# Hotfix: Notifications, Sales UI/Search, General Report

Date: 2026-06-30

## Fixed fatal error
- Added missing `sisEnsureNotificationTables()` helper in `include/shift_helpers.php`.
- Added missing notification helpers:
  - `sisCreateNotification()`
  - `sisNotificationCountForCurrentUser()`
  - `sisGetNotificationsForCurrentUser()`
  - `sisMarkNotificationRead()`
- Added missing shift login approval helper:
  - `sisMaybeRequireSupervisorLoginApproval()`
- The Admin `run_upgrade.php` page no longer crashes when `sisEnsureAdvancedShiftTables()` creates notification tables.

## Shift/login approval workflow
- If an agent already ended the current shift and tries to log in again, the system now creates a Supervisor login-access request.
- If the agent logs in outside the allowed shift access window, the system creates a Supervisor login-access request.
- Supervisor approval is required before the agent can continue.
- Supervisor receives navbar notification and popup/toast notice.

## Sales Report
- Rebuilt `admin/sales.php` with a cleaner UI.
- Added database search filter.
- Added instant table search for visible tabs.
- Added tabs for Transactions, By Agent, By Showroom, and By Shift.
- All money displays use Dalasis: `D`.

## General Report
- Added `admin/general_report.php`.
- Added menu and navbar title support.
- Includes overall visits, sales, collections, pending/unpaid, support tickets, showroom performance, agent performance, and recent sales.

## Files changed
- `include/shift_helpers.php`
- `include/navbar.php`
- `include/side_header.php`
- `admin/sales.php`
- `admin/partials_sales_summary_table.php`
- `admin/general_report.php`
- `assets/css/sis-theme.css`
- `docs/php_lint_hotfix_2026_06_30.txt`

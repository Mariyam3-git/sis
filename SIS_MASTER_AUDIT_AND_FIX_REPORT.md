# SIS Master Audit and Fix Report

Generated: 2026-07-10

This single Markdown file replaces the separate project audit, hotfix, UI, theme, role, profile, help, and reporting notes that were previously stored as many `.md` files.

## Latest Fix Summary - 2026-07-10

- Rebuilt Users & Roles password reset to use one global Bootstrap modal outside the table, fixing popup blinking/moving caused by table-nested modal markup.
- Added a stable password-reset popup with static backdrop, centered dialog, cleared password fields, and dynamic target user name/id.
- Extended dark-mode CSS for Supervisors Management, Agents Management, and Outlets Management: stat cards, filter bars, region groups, inactive cards, shift cards, outlet cards, badges, action buttons, region stats, permission boxes, tables, and modals.
- Kept the 2026-07-09 role/user popup and profile upload fixes.
- Consolidated all Markdown documentation into this one master report.

## Consolidated Source Files

1. `README_FIRST.md`
2. `docs/ADVANCED_KPI_FILTER_UI_FIX_2026_07_01.md`
3. `docs/ADVANCED_SHIFT_SUPPORT_UI_UPGRADE_2026_06_30.md`
4. `docs/HOTFIX_NOTIFICATION_SALES_GENERAL_REPORT_2026_06_30.md`
5. `docs/HOTFIX_SESSION_EDIT_RECORD_2026_07_01.md`
6. `docs/HOTFIX_SETTINGS_NOTIFICATIONS_REPORTS_SESSION_2026_06_30.md`
7. `docs/MERGE_REPORT_2026_06_30.md`
8. `docs/REPORTING_CALCULATION_FIXES_2026-06-18.md`
9. `docs/SHIFT_NOTIFICATION_REPORTS_CURRENCY_UPGRADE_2026_06_30.md`
10. `docs/SIS_ACCOUNT_PASSWORD_ROLE_DATABASE_TIMEOUT_UPGRADE_2026_07_02.md`
11. `docs/SIS_ACCOUNT_PROFILE_ROLES_SYSTEM_RESTORE_AUDIT_2026_07_02.md`
12. `docs/SIS_ADMIN_LAYOUT_AGENT_PROFILE_FORM_FIX_REPORT.md`
13. `docs/SIS_ADMIN_LAYOUT_PROFILE_FORM_FIX_REPORT.md`
14. `docs/SIS_ALERT_404_ACCESS_THEME_HELP_AUDIT_2026_07_01.md`
15. `docs/SIS_ALERT_MODAL_FULL_AUDIT_FIX_2026_07_02.md`
16. `docs/SIS_ANNOUNCEMENT_DUPLICATE_SUBMIT_FIX_2026_07_01.md`
17. `docs/SIS_ANNOUNCEMENT_FATAL_ERROR_HOTFIX_2026_07_01.md`
18. `docs/SIS_ANNOUNCEMENT_MODULE_2026_07_01.md`
19. `docs/SIS_ANNOUNCEMENT_SHOWCASE_REFRESH_GUARD_AUDIT_2026_07_01.md`
20. `docs/SIS_COMPLAINT_SUPPORT_MODULE_REPORT.md`
21. `docs/SIS_CSS_STANDARDIZATION_UI_CLEANUP_REPORT.md`
22. `docs/SIS_DEEP_AUDIT_REDIRECT_ANNOUNCEMENT_HELP_FIX_2026_07_01.md`
23. `docs/SIS_DEEP_REPAIR_WORKFLOW_ROLE_PROFILE_AUDIT_2026_07_03.md`
24. `docs/SIS_DEEP_WORKFLOW_RESTORE_AUDIT_2026_07_03.md`
25. `docs/SIS_FINAL_CSS_THEME_HOVER_AUDIT_2026_07_03.md`
26. `docs/SIS_FINAL_THEME_BUTTON_ANNOUNCEMENT_HERO_FOOTER_AUDIT_2026_07_03.md`
27. `docs/SIS_FINAL_UPGRADE_REPORT.md`
28. `docs/SIS_FULL_AUDIT_ROLE_PASSWORD_PROFILE_HELP_FIX_2026_07_09.md`
29. `docs/SIS_FULL_AUDIT_SHIFT_THEME_POPUP_SECURITY_FIX_2026_07_03.md`
30. `docs/SIS_FULL_PAGE_UI_THEME_HOVER_AUDIT_2026_07_03.md`
31. `docs/SIS_FULL_THEME_VISIBILITY_AUDIT_2026_07_03.md`
32. `docs/SIS_HANDLE_TIMER_DATABASE_FIX_REPORT.md`
33. `docs/SIS_HANDLE_TIMER_FINAL_TRACE_AND_FIX_REPORT.md`
34. `docs/SIS_HANDLE_TIMER_SECONDS_ONLY_FIX_REPORT.md`
35. `docs/SIS_HANDLE_TIMER_SERVER_HIDDEN_FINAL_REPORT.md`
36. `docs/SIS_HANDLE_TIMER_START_END_SECONDS_FIX_REPORT.md`
37. `docs/SIS_HANDLE_TIMER_UPDATE_REPORT.md`
38. `docs/SIS_LAYOUT_INCLUDE_UI_REDESIGN_REPORT.md`
39. `docs/SIS_LOGIN_DASHBOARD_FINAL_FIX.md`
40. `docs/SIS_LOGO_TABLE_HOVER_FULL_CSS_AUDIT_2026_07_03.md`
41. `docs/SIS_PROFESSIONAL_UI_REPORT_LOGO_TABLES_2026-06-18.md`
42. `docs/SIS_PROJECT_AUDIT_UPGRADE_REPORT_2026-06-18.md`
43. `docs/SIS_REDIRECTION_FIX_REPORT.md`
44. `docs/SIS_ROLLBACK_UI_PROFESSIONAL_FIX_2026-06-18.md`
45. `docs/SIS_SHIFT_ADMIN_APPROVAL_THEME_REPAIR_2026_07_03.md`
46. `docs/SIS_SHIFT_LOGIC_SYSTEM_AUDIT_FIX_2026_07_01.md`
47. `docs/SIS_SHIFT_SUPERADMIN_UPDATE_REPORT.md`
48. `docs/SIS_SUPERADMIN_SECURITY_UI_AUDIT_2026_07_01.md`
49. `docs/SIS_UNIFIED_COMPONENT_TABLE_THEME_REPORT.md`
50. `docs/SIS_UPGRADE_REPORT.md`
51. `docs/TODAYS_TRANSACTIONS_UI_FIX_2026_07_01.md`

---



---

## Original file: `README_FIRST.md`

# COMIUM SIS Ultimate - Merged Project

This package is one merged project made from:

1. **SIS (2)** - used as the main stable base.
2. **Showroom Information System** - selected useful modules were merged, cleaned, and standardized.

## What was improved

- Kept the stronger SIS base with complaint/support, handle timer, reporting helpers, super audit logs, stock, reconciliation, targets, and unified layout.
- Added a new **Admin Stock Dashboard** based on the Showroom stock dashboard, but rewritten into the SIS layout.
- Added **Shift Allocation** and **Shift Report** modules for Admin and Supervisor.
- Added the missing `shift_assignments` database table and safe automatic repair helper.
- Added the new migration: `migrations/2026_06_shift_assignment_module.sql`.
- Improved `config.php` so database values can be edited directly or supplied through environment variables.
- Removed the visible message: `No active support form fields found. Please ask Admin to create fields first.` from Agent/Supervisor support forms.
- Preserved selected original Showroom module files in `docs/merged_sources/` for reference only.
- Checked all PHP files with `php -l`; all included PHP files passed syntax linting.


## 2026-06-30 advanced upgrade added

- Removed **Complaint Reason** from Add Customer / Sale pages.
- Complaint reasons now belong only to **Complaint / Support**.
- Added Admin-managed **Main Reason** and **Sub Reason** dropdowns.
- Added lighter UI overrides so the full project looks cleaner and less dark/bold.
- Added Agent **My Shift** page.
- Added Supervisor **Shift Requests** page with early-end approval/rejection.
- Added weekly/monthly/custom shift scheduling with edit support.
- Added login-window control: assigned Agents can log in from one hour before shift start until one hour after shift end.
- Added overtime notification workflow for Supervisors.
- Added database migration note: `migrations/2026_06_30_advanced_support_shift_upgrade.sql`.

## Install on AMpps / XAMPP / local server

1. Extract this folder into your web server directory, for example:
   - AMpps: `C:\Program Files\Ampps\www\COMIUM_SIS_ULTIMATE`
   - XAMPP: `C:\xampp\htdocs\COMIUM_SIS_ULTIMATE`

2. Create a MySQL database called:

   ```sql
   comium_gm
   ```

3. Import:

   ```text
   comium_gm.sql
   ```

4. Open `config.php` and update these values if needed:

   ```php
   define('DB_HOST', getenv('SIS_DB_HOST') ?: 'localhost');
   define('DB_USER', getenv('SIS_DB_USER') ?: 'root');
   define('DB_PASS', getenv('SIS_DB_PASS') !== false ? getenv('SIS_DB_PASS') : 'YES');
   define('DB_NAME', getenv('SIS_DB_NAME') ?: 'comium_gm');
   ```

5. Open the system in your browser:

   ```text
   http://localhost/COMIUM_SIS_ULTIMATE/login.php
   ```

6. After login as Admin, go to:

   ```text
   Admin > Management & System > Run Upgrade
   ```

   Then click **Run Upgrade** once. This safely checks Main/Sub Reason support tables, handle time columns, advanced shift assignment/request tables, indexes, and Super Admin support.

## Default login from the included database

- Admin: `admin` / `admin123`
- Supervisor: `supervisor1` / `supervisor123`
- Agent: `agent2` / `agent123`
- System Owner: create explicitly with a unique password; no default privileged credentials are distributed.

Change these passwords before using the project in real production.

## New module locations

### Admin

- `admin/stock_dashboard.php`
- `admin/shift_allocation.php`
- `admin/shift_report.php`

### Supervisor

- `supervisor/shift_allocation.php`
- `supervisor/shift_report.php`
- `supervisor/shift_requests.php`

### Agent

- `agent/my_shift.php`
- `agent/shift_logout.php`

### Shared helper

- `include/shift_helpers.php`
- `support_helpers.php`

## Notes

- The system remains PHP/MySQL and does not require Composer.
- Internet access is only used for Bootstrap and Bootstrap Icons CDN links already present in the project.
- The merged Showroom original files in `docs/merged_sources/` are not active pages; they are only kept so you can trace what was merged.


## 2026-06-30 Shift / Notification / Report Update

After uploading this version, log in as Admin and run:

`Admin > Management & System > Run Upgrade`

This adds the notification bell tables, Supervisor-approved Agent login access, the stricter early-shift-end flow, fixed top navbar, Admin sidebar search, Daily Showroom Report fixes, Sales Report display fixes, and Dalasis `D` currency across the system.


---

## Original file: `docs/ADVANCED_KPI_FILTER_UI_FIX_2026_07_01.md`

# Advanced KPI Report Filter UI Fix - 2026-07-01

## Updated file
- `admin/reports.php`

## What changed
- Rebuilt the Advanced KPI Reports filter section as a clean card-based filter panel.
- Added quick date range buttons: Today, This Month, Last 30 Days, and This Year.
- Added responsive grid layout for date, region, showroom, service, purpose, staff, and handle time filters.
- Added clear Apply Filters and Reset actions.
- Added active-filter indicator.
- Added front-end validation so From Date cannot be later than To Date.
- Updated report queries so key KPI cards, charts, and report tables respect the active filters more consistently.

## Validation
- PHP syntax check passed for all PHP files.


---

## Original file: `docs/ADVANCED_SHIFT_SUPPORT_UI_UPGRADE_2026_06_30.md`

# Advanced Shift + Complaint/Support + UI Upgrade

Date: 2026-06-30

## Main changes

- Removed Complaint Reason from Add Customer / Sale pages.
- Moved reason handling fully into Complaint / Support.
- Added Admin-managed Main Reason and Sub Reason dropdowns.
- Updated Agent and Supervisor Complaint / Support forms to use dependent Main/Sub Reason dropdowns.
- Updated Support Reports CSV/table output to show Main Reason and Sub Reason.
- Reworked Admin > Complaint Reasons into Main Reason / Sub Reason management.
- Added lighter UI overrides so the project is cleaner, less bold, and not forced into dark colors.
- Added Agent > My Shift page.
- Added Agent early shift-end request workflow.
- Added Supervisor > Shift Requests for approving/rejecting early end requests and reviewing overtime notices.
- Upgraded shift allocation for single-day, weekly, monthly, and custom-range schedules.
- Added edit support for assigned shifts.
- Added login window rule: an assigned Agent can log in from one hour before shift start until one hour after shift end.
- Added overtime workflow: after one hour past shift end, Agent gets a continue-overtime prompt; Supervisor receives overtime notification.
- Added optional 15-minute delayed logout when Agent declines overtime continuation.

## Database changes

Application helpers and Admin > SIS Upgrade repair the database automatically.

New/updated tables and columns:

- `support_tickets.main_reason`
- `support_tickets.sub_reason_id`
- `support_tickets.idx_support_reason`
- `shift_assignments.schedule_scope`
- `shift_assignments.early_end_allowed`
- `shift_assignments.overtime_allowed`
- `shift_requests`

## Files heavily changed/added

- `support_helpers.php`
- `admin/complaint_reasons.php`
- `admin/support_reports.php`
- `admin/run_upgrade.php`
- `include/shift_helpers.php`
- `include/side_header.php`
- `login.php`
- `logout.php`
- `agent/dashboard.php`
- `agent/support.php`
- `agent/my_shift.php`
- `agent/shift_logout.php`
- `supervisor/support.php`
- `supervisor/shift_requests.php`
- `supervisor/shift_allocation.php`
- `admin/shift_allocation.php`
- `assets/css/sis-theme.css`
- `migrations/2026_06_30_advanced_support_shift_upgrade.sql`


---

## Original file: `docs/HOTFIX_NOTIFICATION_SALES_GENERAL_REPORT_2026_06_30.md`

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


---

## Original file: `docs/HOTFIX_SESSION_EDIT_RECORD_2026_07_01.md`

# Hotfix: Session Timeout and Editable Customer/Sale Records

Date: 2026-07-01

## Fixed

- Reworked the 30-minute inactivity timeout so stale sessions do not trap Admin users in a login/dashboard redirect loop.
- Added safe session helper functions in `config.php`:
  - `sisSessionTimeoutSeconds()`
  - `sisSessionExpired()`
  - `sisDestroyCurrentSession()`
  - `sisTouchSessionActivity()`
- Updated `login.php` to clear expired sessions before showing the login form.
- Updated `logout.php` to preserve the timeout notice when automatic logout happens.
- Updated `notifications_api.php` so notification polling does not refresh `last_activity`. This means the notification auto-refresh will not stop the 30-minute inactivity logout.

## Improved Add Customer / Sale Editing

- Agent Dashboard and Supervisor Add Sale now allow editing customer/sale records.
- The GSM/customer phone number is locked and cannot be edited from the edit modal.
- Editable fields now include:
  - Purpose
  - Service
  - Quantity
  - Amount
  - Sale date
  - Payment status
  - Record status
  - Notes
- Edit actions add an audit stamp and log `EDIT_CUSTOMER_SALE`.

## Files changed

- `config.php`
- `login.php`
- `logout.php`
- `notifications_api.php`
- `agent/dashboard.php`
- `supervisor/add_sale.php`
- `docs/HOTFIX_SESSION_EDIT_RECORD_2026_07_01.md`


---

## Original file: `docs/HOTFIX_SETTINGS_NOTIFICATIONS_REPORTS_SESSION_2026_06_30.md`

# Hotfix: Settings, Notifications, Reports, Session Control

Date: 2026-06-30

## Fixed

- Fixed PHP 8 warnings in `admin/settings.php` caused by missing `currency` and `currency_symbol` POST values.
- Currency is fixed to Gambian Dalasis: `D`.
- Removed Logout from the top navbar.
- Added logout confirmation to sidebar/manual logout links.
- Added 30-minute inactivity logout:
  - Server-side enforcement in `config.php`.
  - Client-side redirect after 30 minutes of no activity.
  - Timeout logout does not close an agent shift; it only ends the web session.
- Added live notification refresh:
  - New `notifications_api.php` endpoint.
  - Notification bell auto-refreshes every 20 seconds.
  - Users can mark one notification as read by opening it.
  - Users can mark all notifications as read.
  - New notification toast appears without full page reload.
- Rebuilt `admin/daily_showroom_report.php` into a safer report using the sales table as the source of truth.
- Improved Daily Services Report filters, totals, ComCach/ISP/Telecom separation, shift totals, and instant table search.
- Adjusted Admin sidebar menu so `Sales Report` opens the cleaner `admin/sales.php` page and the older report is labelled `Advanced KPI Report`.

## Files Changed

- `admin/settings.php`
- `admin/daily_showroom_report.php`
- `admin/super_audit_logs.php`
- `config.php`
- `login.php`
- `logout.php`
- `include/navbar.php`
- `include/footer.php`
- `include/side_header.php`
- `notifications_api.php`
- `docs/HOTFIX_SETTINGS_NOTIFICATIONS_REPORTS_SESSION_2026_06_30.md`


---

## Original file: `docs/MERGE_REPORT_2026_06_30.md`

# Merge Report - 2026-06-30

## Main decision

The final project uses **SIS (2)** as the base because it already contained the strongest production modules: support/complaints, handle-time fixes, reporting helpers, audit logs, stock management, targets, visitors, reconciliation, and unified layout files.

The **Showroom Information System** project was used as a source for useful missing features. The active merged features were rewritten into the SIS standard instead of copying old duplicate pages directly.

## Active files added or changed

### Added

- `admin/stock_dashboard.php`
- `admin/shift_allocation.php`
- `admin/shift_report.php`
- `supervisor/shift_allocation.php`
- `supervisor/shift_report.php`
- `include/shift_helpers.php`
- `migrations/2026_06_shift_assignment_module.sql`
- `README_FIRST.md`
- `docs/merged_sources/*`

### Updated

- `config.php`
  - Added environment-variable database support.
  - Added `ensureShiftAssignmentsTable()`.
  - Auto-checks handle-time storage and shift assignment storage.

- `support_helpers.php`
  - Removed the visible Admin-created form fields warning from operational forms.

- `include/side_header.php`
  - Added Shift Allocation and Shift Report menu links.

- `admin/run_upgrade.php`
  - Added shift assignment table check.

- `comium_gm.sql`
  - Added `super_admin` to user role enum.
  - Added `shift_assignments` table.
  - Added default Super Admin insert.

## Validation

All PHP files passed syntax checking using:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

No PHP syntax errors were found.


---

## Original file: `docs/REPORTING_CALCULATION_FIXES_2026-06-18.md`

# Reporting and Calculation Fixes

Date: 2026-06-18
Project: SIS / Showroom PHP-MySQL Project

## Main problems found

1. Reports were mixing paid sales and withdrawal entries in the same revenue total.
2. Several pages used raw `SUM(amount)`, so negative withdrawal records reduced sales totals or appeared as revenue depending on the page.
3. Some service reports counted visitor rows only and ignored `visitors.quantity`.
4. Some joins between `visitors` and `sales` multiplied rows and inflated totals.
5. Some report pages filtered visitors by date but did not also filter the linked sales by `payment_date`.
6. Shift reports used a different hour rule from the official shift helper.
7. The agent performance API referenced old/non-existing schema names such as `sale_date`, `target_date`, `target_amount`, `added_by`, `visited_at`, and `visit_purposes`.

## New reporting rules applied

- Paid Sales / Revenue = only `sales.amount > 0` with `payment_status = 'Paid'`.
- Paid Withdrawals = absolute value of `sales.amount < 0` with `payment_status = 'Paid'`.
- Net Cash = paid positive sales minus paid withdrawals.
- Pending Amount = only positive pending amounts.
- Unpaid Amount = only positive unpaid amounts.
- Service Units = `GREATEST(COALESCE(visitors.quantity, 1), 1)`.
- Target achievement = paid positive sales only, not withdrawals.
- Shift grouping = Morning 06:00-13:59, Afternoon 14:00-21:59, Night 22:00-05:59.

## Files changed

- `include/reporting_helpers.php` added centralized calculation helpers.
- `admin/dashboard.php` corrected dashboard totals and payment summary.
- `admin/reports.php` corrected KPIs, service units, top showroom totals, complaint metrics, and date-filtered sales joins.
- `admin/daily_showroom_report.php` corrected shift logic, units, paid sales, withdrawals, and net cash calculations.
- `admin/sales.php` fixed shift filter join and separated collected, pending, unpaid, withdrawals, and net cash.
- `admin/targets.php` corrected target achievement calculation.
- `supervisor/dashboard.php` corrected daily/period totals and agent summary row multiplication.
- `supervisor/export_report.php` corrected exported totals and added paid withdrawals.
- `supervisor/agent_performance.php` rebuilt query logic using separate visitor and sales aggregates to stop inflated totals.
- `supervisor/targets.php` corrected target achievement calculation.
- `supervisor/cash_reconciliation.php` corrected expected cash as net cash and shows paid sales vs withdrawals.
- `supervisor/add_entry.php` corrected today's summary totals.
- `supervisor/stock_dashboard.php` corrected outlet sales, MTD sales, agent performance, and target progress calculations.
- `agent/profile.php` corrected agent performance totals, trends, service breakdown, and target achievement.
- `agent/performace_api.php` rebuilt to match the actual database schema and correct calculations.

## Validation completed

- All PHP files were linted with `php -l`.
- Result: 66 PHP files passed syntax validation.

## Deployment note

Upload these files over the current project, keep the existing database, and clear browser/PHP cache if enabled. Since no live MySQL server was available in this repair environment, database-level validation should be confirmed once deployed using real project data.


---

## Original file: `docs/SHIFT_NOTIFICATION_REPORTS_CURRENCY_UPGRADE_2026_06_30.md`

# Shift, Notifications, Reports, and Currency Upgrade — 2026-06-30

## Main workflow fixes

- Added Supervisor-approved **login access requests** for Agents who try to log in outside the normal shift rule.
- If an Agent already ended a scheduled shift and tries to log in again before the scheduled end time, the system now blocks silent restart and sends a Supervisor approval request.
- Supervisor can approve/reject login access from **Supervisor > Shift Requests**.
- Approved login access is consumed when the Agent logs in successfully, so old approvals are not reused forever.
- Early shift logout now requires an approved Supervisor decision. A pending request alone no longer allows early logout.

## Notifications

- Added a top navbar notification bell for Admin, Supervisor, and Agent layouts.
- Added role/outlet notification storage using `user_notifications`.
- Agent early-end requests notify the Supervisor.
- Agent overtime confirmations notify the Supervisor.
- Supervisor decisions notify the Agent.
- Supervisor screens show popup/toast alerts for pending requests.

## UI and navigation

- Navbar is fixed at the top across the project.
- Admin sidebar now has a search box for quick page access.
- UI typography was softened so the project is not too bold/dark.

## Reports and currency

- All visible money labels were changed to **Dalasis `D`**.
- Settings currency and currency symbol defaults are now `D`.
- Daily Showroom Report now handles databases that do not yet have `visitors.quantity`.
- Daily Showroom Report category filtering now properly hides Telecom/ISP columns.
- Daily Showroom Report DataTables export was repaired with Excel/PDF/Print support and better length options.
- Sales Reports tables now use better scrolling, length options, and display handling.

## Database changes

New/updated database structures:

- `user_notifications`
- `shift_requests.request_type` now supports `login_access`
- `shift_requests.used_at`
- `settings.currency` and `settings.currency_symbol` set to `D`

Run this after upload:

`Admin > Management & System > Run Upgrade`


---

## Original file: `docs/SIS_ACCOUNT_PASSWORD_ROLE_DATABASE_TIMEOUT_UPGRADE_2026_07_02.md`

# SIS Account, Password, Role, Database, and Timeout Upgrade - 2026-07-02

## Added

- Shared `profile.php` page for all users.
- Users can upload a profile image.
- Users can change/reset their own password from My Profile.
- Agents and Supervisors are restricted to profile photo and password only; they cannot edit personal details.
- Admin and Super Admin can manage users and change user roles from `admin/users.php`.
- Normal Admin cannot see, create, edit, or manage Super Admin accounts.
- Super Admin-only `admin/database_console.php` for database inspection, filtering, and query execution.
- Database console query logging through `sis_db_console_log`.
- Super Admin page access control remains available for Admin, Supervisor, and Agent pages.

## Fixed

- Password Expiry (days) setting is now enforced at login and during active sessions.
- Expired/reset passwords redirect the user to My Profile before allowing dashboard access.
- Session timeout JavaScript now uses the configured timeout value instead of a hardcoded 30 minutes.
- Timeout redirects now clear the expired session safely so users can log in again without clearing browser data.
- Background notification/announcement 401 responses now redirect safely to logout/login.

## Database upgrade additions

Run `Admin > Management & System > Run Upgrade` as Super Admin to add/repair:

- `users.email`
- `users.profile_photo`
- `users.password_changed_at`
- `users.must_change_password`
- `sis_db_console_log`
- user role indexes

## Security notes

- Database Console is protected with `requireSuperAdmin()`.
- Normal Admin does not see Super Admin in Users & Roles.
- Normal Admin cannot assign `super_admin` role.
- Users cannot change their own role while logged in.
- Password reset by Admin/Super Admin forces the target user to change password at next login.


---

## Original file: `docs/SIS_ACCOUNT_PROFILE_ROLES_SYSTEM_RESTORE_AUDIT_2026_07_02.md`

# SIS Account/Profile/Roles System Restore Audit — 2026-07-02

## Main fixes

1. Restored the Agent performance profile page from the provided reference profile file.
2. Added a Supervisor `My Performance` page with team sales, visitors, conversion rate, agent ranking, and chart.
3. Added separate `My Account` navigation for all users. Agents and Supervisors can update only their password and profile photo.
4. Fixed Admin/Super Admin role management reliability by:
   - Loading Bootstrap JavaScript globally from the shared footer.
   - Adding a modal fallback when Bootstrap JS is unavailable.
   - Adding an inline role selector and Save button on `Admin > Users & Roles`, so role changes no longer depend only on a popup modal.
5. Kept normal Admin unable to see or manage Super Admin accounts.
6. Kept Super Admin able to assign all roles, including Super Admin.
7. Made user account column repair more compatible with older databases that do not have `language_preference` or some newer account columns.
8. Updated Run Upgrade to use the safer account-column repair helper.
9. Rechecked session timeout flow so timed-out sessions clear cleanly and login can continue without clearing the browser.

## Updated files

- `agent/profile.php`
- `supervisor/profile.php`
- `profile.php`
- `admin/users.php`
- `admin/run_upgrade.php`
- `config.php`
- `include/side_header.php`
- `include/navbar.php`
- `include/footer.php`
- `assets/css/sis-theme.css`

## Validation

- PHP syntax check passed for project PHP files.
- Main JavaScript file and key inline scripts checked with Node syntax validation.
- ZIP integrity should be tested after packaging.


---

## Original file: `docs/SIS_ADMIN_LAYOUT_AGENT_PROFILE_FORM_FIX_REPORT.md`

# SIS Admin Layout, Agent Profile, and Customer Form Fix Report

## Completed Changes

- Added the Admin pages to the same shared include layout used by Agent and Supervisor.
- Updated `include/side_header.php` with grouped navigation sections for Admin, Agent, and Supervisor.
- Moved Complaint / Support Reports under the Admin **Reports** section.
- Kept Admin Complaint / Support Form Builder under Customer Care Setup.
- Converted Admin pages to use `include/side_header.php`, `include/navbar.php`, and `include/footer.php`.
- Fixed Admin content scrolling and table overflow using shared layout CSS.
- Fixed `agent/profile.php` fatal error by replacing old/non-existing columns with schema-safe queries:
  - `sales.payment_date` instead of `sales.sale_date` when applicable.
  - `visitors.visit_time` instead of `visitors.visited_at` when applicable.
  - `visitors.agent_id` instead of `visitors.added_by` when applicable.
  - `agent_shifts` instead of missing `session_logs` when applicable.
- Redesigned Agent Add Customer page to match the Supervisor flow:
  - Start Attending Customer card.
  - Add Customer form opens in a modal.
  - Timer starts when the modal opens.
  - Records table remains scrollable.

## Test Steps

1. Login as Admin and open dashboard, sales, reports, stock pages, support reports, and settings.
2. Confirm all Admin pages have the same sidebar, navbar, content area, and footer.
3. Open Admin Reports and confirm Complaint / Support Reports is listed under Reports in the sidebar.
4. Login as Agent and open `agent/profile.php`; it should load without `mysqli_stmt_bind_param()` fatal error.
5. Login as Agent and open Add Customer / Sale; click Start Attending Customer and confirm the form opens like Supervisor.
6. Login as Supervisor and confirm the existing Start Customer / Add Sale flow still works.


---

## Original file: `docs/SIS_ADMIN_LAYOUT_PROFILE_FORM_FIX_REPORT.md`

# SIS Admin Unified Layout, Profile Fix, and Agent Form Alignment Report

Date: 2026-06-17

## Summary
This update extends the shared include-based layout to the Admin side, fixes the Agent profile fatal error, adds Complaint / Support reports under the Admin Reports area, and aligns the Agent Add Customer flow with the Supervisor Start Attending Customer flow.

## Main Changes

### 1. Shared Admin Layout
Admin pages now use the same layout system already added for Agent and Supervisor pages:

- `include/side_header.php`
- `include/navbar.php`
- `include/footer.php`

The Admin sidebar is now grouped into clear sections:

- Main
- Customer Care Setup
- Reports
- Stock Management
- Management & System

### 2. Complaint / Support Under Reports
The Complaint / Support report is now available under the Admin Reports section and also linked directly inside `admin/reports.php`.

Updated areas:

- `include/side_header.php`
- `admin/reports.php`
- `admin/support_reports.php`

### 3. Agent Add Customer Form Alignment
The Agent Add Customer / Sale page now follows the Supervisor flow:

1. Agent clicks `Start Attending Customer / Add Sale`.
2. The customer form opens in a modal.
3. The handle timer starts correctly.
4. The form layout is closer to the Supervisor page.

Updated file:

- `agent/dashboard1.php`

### 4. Agent Profile Fatal Error Fixed
The fatal error in `agent/profile.php` was caused by prepared statements failing when old or missing columns were referenced.

The profile page now uses schema-safe checks for the current database structure, including:

- `sales.payment_date` instead of relying only on old `sale_date`
- `visitors.visit_time` instead of relying only on old `visited_at`
- `visitors.agent_id` instead of relying only on old `added_by`
- `agent_shifts` instead of relying only on old session log structures

Updated file:

- `agent/profile.php`

### 5. UI Table Scroll / Layout Cleanup
Several malformed table closing tags caused layout and scroll problems on some records pages. These were corrected in:

- `admin/stock_products.php`
- `admin/visitors.php`
- `supervisor/add_entry.php`
- `supervisor/add_sale.php`
- `supervisor/stock_dashboard.php`

### 6. Validation
PHP syntax validation passed for all PHP files.

## Testing Steps

1. Login as Admin.
2. Open Admin Dashboard.
3. Confirm the sidebar, navbar, and footer show consistently.
4. Open Reports and confirm Complaint / Support Reports appears.
5. Open `admin/support_reports.php` and confirm the shared Admin layout displays correctly.
6. Login as Agent.
7. Open `agent/profile.php` and confirm the fatal error is gone.
8. Open Agent Add Customer / Sale and confirm it uses the same Start Attending Customer modal style as Supervisor.
9. Login as Supervisor and confirm the existing Add Entry / Add Sale pages still work.
10. Hard refresh browser cache with Ctrl + F5.

## Database Upgrade
No database upgrade is required for this update.


---

## Original file: `docs/SIS_ALERT_404_ACCESS_THEME_HELP_AUDIT_2026_07_01.md`

# SIS Alert, 404, Access, Theme, Help Audit - 2026-07-01

## Completed upgrades

- Replaced default browser alert behavior with a centered branded modal popup.
- Flash alerts now also show as centered popups.
- Logout confirmation now uses the centered confirmation modal.
- Notification and announcement dropdowns were repaired with safer Bootstrap/fallback behavior.
- API-generated notification and announcement links now use root-safe paths so redirection works from admin, supervisor, agent, and root pages.
- Added custom 404 Not Found page with Back and Dashboard buttons.
- Added `.htaccess` ErrorDocument support for the custom 404 page.
- Added logo to the sidebar and login page.
- Added SVG favicon support to PHP pages.
- Improved dark mode/light mode styling.
- Added shared Help Center page for all logged-in users.
- Added Super Admin Page Access Control page.
- Normal Admin cannot see or set Super Admin-only announcement audience.
- Normal Admin still cannot see Management & System pages.
- Added stronger POST refresh protection/PRG redirects on more forms so refresh does not submit again.

## New files

- `404.php`
- `help.php`
- `admin/page_access.php`
- `assets/images/favicon.svg`
- `.htaccess`

## Important note

After replacing the project, login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

This creates/repairs the `page_access_rules` table used by the new Page Access Control module.


---

## Original file: `docs/SIS_ALERT_MODAL_FULL_AUDIT_FIX_2026_07_02.md`

# SIS Alert Modal + System Audit Fix — 2026-07-02

## Main problem fixed
Some centered alert popups could show but the OK button did not close them. The alert system was depending on Bootstrap `data-bs-dismiss` behavior. If Bootstrap JS/CDN was delayed, blocked, or not attached correctly, the Promise resolved but the modal stayed visible.

## Fixes applied
- Rebuilt the center alert/confirm close logic in `include/footer.php`.
- OK, Cancel, close button, and Escape now close the modal reliably.
- Added a fallback backdrop for pages where Bootstrap JS is unavailable.
- Added stronger z-index rules so center alerts and normal Bootstrap modals stay clickable above the fixed navbar, notifications, and overlays.
- Improved dropdown fallback for Notification and Announcement menus.
- Fixed `login.php` broken/missing closing layout tags.
- Protected `admin/partials_sales_summary_table.php` from direct URL access.
- Normal Admin announcement statistics no longer count Super Admin-only announcements.

## Duplicate submit / refresh audit
The existing global POST one-time token guard was rechecked. It is still active through `config.php` and protects old forms from browser refresh resubmission. API polling endpoints remain excluded so notifications and announcements do not break.

## Validation
- PHP syntax check: passed for all PHP files.
- JavaScript syntax check: passed for `assets/js/sis-theme.js` and extracted footer JavaScript.
- ZIP package integrity should be tested after packaging.


---

## Original file: `docs/SIS_ANNOUNCEMENT_DUPLICATE_SUBMIT_FIX_2026_07_01.md`

# SIS Announcement + Duplicate Submit Fix — 2026-07-01

## Fixed

- Stopped duplicate announcement creation when the browser refreshes after submit.
- Added POST → Redirect → GET flow to `admin/announcements.php`.
- Added one-time request tokens for announcement create/update/delete/status changes.
- Added one-time request tokens to Add Customer / Sale actions for Agent and Supervisor pages.
- Added duplicate-submit button guard for token-protected forms.

## Announcement improvements

- Added pinned announcements.
- Added read-action indicator.
- Improved Announcement Management UI with cleaner form, filters, stats, and reset button.
- Improved navbar announcement dropdown with pinned/read badges.
- Improved dashboard announcements with inline Mark Read button.
- Improved Announcement Board badges.

## Database changes

`Run Upgrade` will add these columns to `announcements` if missing:

- `is_pinned TINYINT(1) NOT NULL DEFAULT 0`
- `requires_ack TINYINT(1) NOT NULL DEFAULT 0`

## Changed files

- `config.php`
- `admin/announcements.php`
- `announcements.php`
- `announcements_api.php`
- `include/announcement_helpers.php`
- `include/navbar.php`
- `include/footer.php`
- `agent/dashboard.php`
- `supervisor/add_sale.php`
- `assets/css/sis-theme.css`


---

## Original file: `docs/SIS_ANNOUNCEMENT_FATAL_ERROR_HOTFIX_2026_07_01.md`

# SIS Announcement Fatal Error Hotfix - 2026-07-01

## Problem Fixed
The Announcement Management page could crash with:

```text
Fatal error: mysqli_fetch_assoc(): Argument #1 ($result) must be of type mysqli_result, bool given in admin/announcements.php on line 184
```

## Root Cause
Older databases that already had the `announcements` table did not always have the newer columns:

- `is_pinned`
- `requires_ack`

The page used those columns in the stats/list queries. When the columns were missing, MySQL returned `false`, and PHP then called `mysqli_fetch_assoc()` on that failed result.

## Fixes Applied
- Added `is_pinned` and `requires_ack` to the automatic repair list in `include/announcement_helpers.php`.
- Made `admin/announcements.php` stats query safe so it will not crash if a query fails.
- Added safer announcement list query error handling.
- Made outlet loading safe when the `outlets` table is unavailable.
- The page now shows a warning message instead of a fatal error if the database needs repair.

## Files Updated
- `include/announcement_helpers.php`
- `admin/announcements.php`

## Required Step After Upload
Login as Super Admin and run:

```text
Admin > Management & System > Run Upgrade
```

This will add/repair the missing columns in the live AMpps/MySQL database.


---

## Original file: `docs/SIS_ANNOUNCEMENT_MODULE_2026_07_01.md`

# SIS Announcement Module Upgrade - 2026-07-01

## Summary
Added a complete Announcement module for COMIUM SIS.

## New files
- `include/announcement_helpers.php`
- `announcements.php`
- `announcements_api.php`
- `admin/announcements.php`

## Updated files
- `include/navbar.php`
- `include/footer.php`
- `include/side_header.php`
- `admin/dashboard.php`
- `agent/dashboard.php`
- `supervisor/dashboard.php`
- `admin/run_upgrade.php`
- `assets/css/sis-theme.css`

## Features
- Admin and Super Admin can create/edit/publish/archive/delete announcements.
- Announcement audience can be:
  - All users
  - Agents
  - Supervisors
  - Admins
  - Super Admin
- Optional outlet targeting.
- Optional start/end date and time.
- Priority levels: Info, Important, Urgent.
- Navbar megaphone icon with unread count.
- Auto-refresh announcement icon every 30 seconds.
- Users can mark one announcement or all announcements as read.
- Active announcements are displayed on Admin, Supervisor, and Agent dashboards.
- The old dashboard hero/welcome areas are replaced with announcement panels.

## Database tables
Run Upgrade creates/checks:
- `announcements`
- `announcement_reads`

The announcement pages also auto-create the tables safely if the upgrade has not run yet.

## After installation
Login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

Then Admin/Super Admin can manage announcements from:

`Admin > Announcements`


---

## Original file: `docs/SIS_ANNOUNCEMENT_SHOWCASE_REFRESH_GUARD_AUDIT_2026_07_01.md`

# SIS Announcement Showcase + Refresh Guard Audit — 2026-07-01

## Request
Improve the Announcement display, show only one announcement at a time in the dashboard hero section, remove the placeholder text, and audit the system so refreshing after form submission does not create duplicates.

## Completed

### Announcement UI
- Rebuilt the dashboard Announcement hero into a premium one-at-a-time showcase.
- Added next/previous controls and dots when multiple announcements exist.
- Added automatic rotation with pause-on-hover.
- Added pinned/new/read/priority badges.
- Added a styled empty state.
- Removed the text: `Announcements set by Admin/Super Admin will appear here for all users.`
- Added direct links from hero announcements to the Announcement Board.

### Duplicate submit protection
- Added a global one-time POST token system in `config.php`.
- Updated `csrfField()` so CSRF-protected forms automatically receive a one-time refresh guard token.
- Added footer JavaScript that injects a one-time token into older POST forms that do not yet call `csrfField()`.
- Added server-side duplicate POST blocking before page logic runs.
- Added client-side double-click submit disabling for all protected POST forms.
- Existing Add Customer / Sale and Announcement-specific request tokens remain in place.

## Important behavior
- First valid submit is processed normally.
- If the browser refreshes/retries the same POST, the reused token is blocked before database insert/update logic runs.
- The system redirects back to the same page and shows a duplicate-submission warning where flash messages are rendered.

## Files changed
- `config.php`
- `include/footer.php`
- `include/announcement_helpers.php`
- `announcements.php`
- `assets/css/sis-theme.css`
- `docs/SIS_ANNOUNCEMENT_SHOWCASE_REFRESH_GUARD_AUDIT_2026_07_01.md`

## Validation
- PHP syntax check passed for all active PHP files.
- JavaScript syntax check passed for `assets/js/sis-theme.js`.


---

## Original file: `docs/SIS_COMPLAINT_SUPPORT_MODULE_REPORT.md`

# SIS Complaint / Support Module Upgrade Report

Date: 17 June 2026

## Goal
Add a Complaint / Support module to the existing SIS project without rebuilding the system or changing the existing login, sales, handle-time, dashboard, role folders, database config, or UI workflow.

## What was added

### Admin
- `admin/support_forms.php`
  - Admin can create and manage the Complaint / Support form fields.
  - Supports field types: Text, Textarea, Dropdown, Number, Date, Phone, Email.
  - Admin can mark fields as required/optional.
  - Admin can enable/disable fields.
  - Admin can control sort order.

- `admin/support_reports.php`
  - Admin can view all Complaint / Support submissions from Agent and Supervisor users.
  - Includes filters for date range, status, role, and search.
  - Admin can update ticket status: Open, In Progress, Resolved, Closed.
  - Includes CSV export.

- `admin/run_upgrade.php`
  - Updated to create the Complaint / Support module tables.
  - Inserts default support form fields safely with `INSERT IGNORE`.

### Agent
- `agent/support.php`
  - Agent can submit Complaint / Support records using the active admin-created form fields.
  - Agent can view latest support tickets submitted by themselves.

- `agent/dashboard.php`
  - Added Complaint / Support button.

- `agent/dashboard1.php`
  - Added Complaint / Support button.

### Supervisor
- `supervisor/support.php`
  - Supervisor can submit Complaint / Support records using the active admin-created form fields.
  - Supervisor can view latest support tickets submitted by themselves.

- `supervisor/dashboard.php`
  - Added Complaint / Support button.

## New helper file
- `support_helpers.php`
  - Creates support tables if missing.
  - Renders dynamic admin-created form fields.
  - Saves support tickets and field values using prepared statements.
  - Builds ticket numbers automatically.

## New database tables
- `support_form_fields`
- `support_tickets`
- `support_ticket_values`

## New migration file
- `migrations/2026_06_complaint_support_module.sql`

## Updated SQL dump
- `comium_gm.sql`
  - Added Complaint / Support table definitions and default fields.

## How to install / test
1. Upload the project files.
2. Login as Admin.
3. Run:
   - `admin/run_upgrade.php`
4. Go to:
   - `admin/support_forms.php`
5. Add or edit form fields.
6. Login as Agent and open:
   - `agent/support.php`
7. Submit a complaint/support record.
8. Login as Supervisor and open:
   - `supervisor/support.php`
9. Submit a complaint/support record.
10. Login as Admin and open:
   - `admin/support_reports.php`
11. Confirm records appear and export works.

## PHP syntax check
All PHP files passed `php -l` syntax checks. See:
- `docs/php_lint_results_support_module.txt`


---

## Original file: `docs/SIS_CSS_STANDARDIZATION_UI_CLEANUP_REPORT.md`

# SIS CSS Standardization, UI Cleanup, and Theme Consistency Report

Date: 2026-06-17

## Summary

The SIS frontend has been cleaned and standardized without rebuilding the PHP/MySQL architecture. Login, dashboard routing, role folders, database configuration, sales flow, handle time logic, and the new Complaint/Support module were preserved.

## Main UI Changes

- Centralized page-level styles into `assets/css/sis-page-components.css`.
- Rebuilt the final design system in `assets/css/sis-theme.css`.
- Rebuilt theme and UI behavior in `assets/js/sis-theme.js`.
- Added the shared component stylesheet to all HTML pages before the final theme stylesheet.
- Added the shared theme script to all HTML pages.
- Removed all PHP `<style>` blocks.
- Removed all static and dynamic inline `style="..."` attributes from PHP pages.
- Removed obsolete HTML comments from frontend markup.
- Removed CSS comments from the frontend CSS files.
- Converted repeated inline layouts into reusable utility classes.
- Converted dynamic colors and progress widths into `data-color` and `data-progress` attributes handled by the shared theme script.

## Frontend Cleanup Results

- PHP `<style>` blocks remaining: `0`
- HTML comments remaining in PHP frontend files: `0`
- Inline `style="..."` attributes remaining in PHP files: `0`
- CSS comments remaining in `assets/css/*.css`: `0`
- Frontend JavaScript comment-only lines remaining in script blocks/assets: `0`
- Shared CSS files now used:
  - `assets/css/sis-page-components.css`
  - `assets/css/sis-theme.css`
- Shared JS file used:
  - `assets/js/sis-theme.js`

## Design System Coverage

The centralized theme now standardizes:

- Buttons
- Forms
- Inputs
- Dropdowns
- Tables
- Cards
- Modals
- Sidebars
- Headers
- Alerts
- Badges
- Typography
- Spacing
- Shadows
- Borders
- Responsive table wrappers
- Complaint/Support pages
- Handle time timer widget
- Admin report pages

## Light and Dark Mode

Light and dark modes are now controlled from one shared JavaScript and CSS token system:

- Theme state is saved in browser local storage.
- The root element receives `data-theme="light"` or `data-theme="dark"`.
- Bootstrap-compatible `data-bs-theme` is also applied.
- Cards, forms, tables, modals, dropdowns, dashboards, alerts, reports, and support forms inherit the same theme tokens.

## Responsiveness

The shared theme includes responsive rules for:

- Desktop
- Tablet
- Mobile

Tables are automatically wrapped in responsive containers by the shared JavaScript where needed.

## Important Files Changed

- `assets/css/sis-page-components.css`
- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`
- `config.php`
- All Admin HTML pages
- All Agent HTML pages
- All Supervisor HTML pages
- `login.php`
- `admin/handle_timer_debug.php`

## Validation

PHP syntax check was run across all PHP files. Results are saved in:

- `docs/php_lint_results_ui_cleanup.txt`

No PHP syntax errors were detected.

## Test Steps

1. Upload the updated project files.
2. Clear browser cache or hard refresh with `Ctrl + F5`.
3. Login as Admin.
4. Check dashboard, sales, reports, support form builder, support reports, stock pages, and settings.
5. Toggle dark mode using the floating theme button.
6. Login as Agent and check dashboard, Add Sale, Stock, Profile, and Complaint/Support.
7. Login as Supervisor and check dashboard, Add Sale, Add Entry, reports, stock pages, and Complaint/Support.
8. Confirm tables scroll properly on mobile.
9. Confirm forms, buttons, cards, and reports now follow the same visual language.
10. Confirm handle time still saves and displays correctly after submitting a sale.

## Notes

This update focuses on CSS/UI standardization only. It does not change database credentials, login logic, role permissions, sales insert logic, or the Complaint/Support database structure.


---

## Original file: `docs/SIS_DEEP_AUDIT_REDIRECT_ANNOUNCEMENT_HELP_FIX_2026_07_01.md`

# SIS Deep Audit Fix - Redirects, Announcements, Help, Dropdowns

Date: 2026-07-01

## Fixed

- Fixed Announcement Management runtime issue where `$audienceOptions` was used before being defined.
- Normal Admin users can no longer see or filter Super Admin-only announcement audiences.
- Normal Admin users cannot edit Super Admin-only announcements by direct URL.
- Removed duplicate "User Board" button on Announcement Management.
- Fixed shared sidebar links so Help and Announcement Board work correctly from root, Admin, Supervisor, and Agent pages.
- Added page-access enforcement to Help Center and Announcement Board.
- Improved notification and announcement dropdown fallback so they open even if Bootstrap JavaScript fails to initialize.
- Added stronger CSS for dropdown visibility.
- Converted inline browser `confirm()` actions into centered SIS confirmation modals.
- Converted bulk Outlet confirmations and Cash Reconciliation update confirmation to centered modal flow.
- Rechecked refresh-after-submit protection: global one-time POST guard remains active, and protected pages redirect after save/delete/update actions.

## Validation

- PHP syntax checked successfully.
- ZIP integrity checked successfully.
- Help and Announcement root-page link paths audited.
- Admin/Super Admin announcement visibility audited.

## Notes

After replacing the project, login as Super Admin and run:

Admin > Management & System > Run Upgrade


---

## Original file: `docs/SIS_DEEP_REPAIR_WORKFLOW_ROLE_PROFILE_AUDIT_2026_07_03.md`

# SIS Deep Repair Audit - Workflow, Roles, Profile, Timeout

Date: 2026-07-03

## Main problems found

1. `admin/users.php` had malformed HTML inside the Users & Roles table. An extra closing `</form>` could break role-change forms and password-reset modals in the browser.
2. `admin/outlets.php` had nested forms and malformed table cells. This could break buttons, bulk actions, and parts of the admin layout.
3. Several older admin table pages had `</div>` tags where table cells should close with `</td>`, causing unstable page layout and broken click areas.
4. Role changes could fail on older databases if the `users.role` enum had not yet been upgraded to include `super_admin`.
5. Duplicate-submit protection was not globally applied to every authenticated POST request. Some older forms depended only on the frontend footer injection.
6. The timeout/login flow was kept server-side only to avoid stale UI loops after timeout.

## Fixes applied

- Repaired Users & Roles form markup.
- Repaired My Account/Profile form markup.
- Added role normalization and automatic role-column repair helper.
- Hardened role update: if role update fails because the role enum is outdated, the system repairs the enum and retries.
- Made duplicate-submit protection idempotent and applied it from `requireLogin()` for authenticated POST pages.
- Repaired malformed table-cell closing tags in old Admin/Supervisor pages.
- Repaired Outlets page form structure and bulk action ID handling.
- Added safe upload folders for user profile photos with `.htaccess` protection.
- Confirmed that profile/account pages keep Agents and Supervisors limited to photo/password changes only.

## Validation

- PHP syntax check passed for all PHP files outside docs.
- Footer JavaScript syntax passed.
- Main theme JavaScript syntax passed.
- ZIP integrity passed after packaging.

## Important after upload

Login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

This ensures old databases receive the latest user account columns and role enum updates.


---

## Original file: `docs/SIS_DEEP_WORKFLOW_RESTORE_AUDIT_2026_07_03.md`

# SIS Deep Workflow Restore Audit - 2026-07-03

## Main runtime issues fixed

- Fixed a system-wide form problem where the duplicate-submit guard disabled buttons before the center confirmation popup completed. This could make Save/Role buttons appear broken after a cancelled confirmation or interrupted modal.
- Restored direct Save behavior for Admin > Users & Roles role changes, so Admin/Super Admin can change roles without depending on a modal.
- Hardened Users & Roles database updates so failed prepares show useful messages instead of breaking the page.
- Removed the client-side forced timeout redirect. Timeout remains enforced server-side, but the browser will no longer be interrupted by a JavaScript redirect that can leave stale UI state.
- Locked Users & Roles as a core Admin page so it cannot be accidentally disabled by page-access rules.

## Profile/account flow checked

- Agent has My Performance and a separate My Account link.
- Supervisor has My Performance and a separate My Account link.
- My Account allows Agents/Supervisors to update only password and profile photo.
- Admin/Super Admin can still update their own account details in My Account.

## Validation

- PHP syntax checked after patching.
- Footer JavaScript syntax checked after patching.
- ZIP integrity checked.


---

## Original file: `docs/SIS_FINAL_CSS_THEME_HOVER_AUDIT_2026_07_03.md`

# SIS Final CSS Theme & Hover Audit - 2026-07-03

## Scope
Full CSS/theme repair focused on the remaining visual issues reported after the previous UI audit:

- Hover states not consistent.
- Some cards/sections still stayed light when dark mode was selected.
- Theme toggle still showed text such as Light/Dark/Theme instead of icon-only.
- Sidebar, buttons, tables, cards, announcement sections, hero sections, forms, filters, dropdowns, modals, footer, badges, and old page components needed a final global override.

## Fixes Applied

### Theme toggle
- Removed visible Light/Dark wording from the theme button.
- Navbar theme control is now icon-only.
- Floating standalone theme button is also icon-only.
- Button title/ARIA is generic: Theme / Toggle theme.

### Cache busting
- `appAsset()` now adds a version query to CSS/JS files so browser cache does not keep old theme files after replacement.

### Dark mode repair
- Added a final CSS layer at the end of `assets/css/sis-theme.css` so it overrides older hard-coded white backgrounds.
- Repaired dark-mode variables and forced dark surfaces to apply consistently.
- Covered older component names from legacy pages such as:
  - `stat-card`
  - `chart-card`
  - `kpi-section`
  - `filter-bar`
  - `report-container`
  - `inactive-section`
  - `date-badge`
  - `stock-card`
  - `low-stock-row`
  - `modal-container`
  - `login-panel`
  - `outlet-header`
  - `sis-tt-*`
  - announcement/hero components

### Hover repair
- Added consistent hover behavior for:
  - Buttons
  - Sidebar/menu items
  - Cards
  - KPI cards
  - Stock cards
  - Action buttons
  - Filter buttons
  - Quick actions
  - Table rows
  - Dropdown items
- Dark-mode hover now uses dark-safe backgrounds instead of light red/white backgrounds.

### Tables/forms/buttons/cards
- Repaired table header, row, striped, hover, and footer colors.
- Repaired input, select, date/time picker, search, and DataTables controls.
- Repaired button variants and hover contrast.
- Repaired badges, alerts, empty states, announcement cards, notification dropdowns, modals, and footer.

## Files Changed
- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`
- `include/navbar.php`
- `config.php`

## Validation
- PHP syntax check passed for all non-doc PHP files.
- Theme JavaScript syntax check passed.
- ZIP integrity should be tested after packaging.

## Browser Note
After replacement, use Ctrl+F5 once. The new `appAsset()` cache-busting should also force the browser to load the latest CSS/JS automatically.


---

## Original file: `docs/SIS_FINAL_THEME_BUTTON_ANNOUNCEMENT_HERO_FOOTER_AUDIT_2026_07_03.md`

# SIS Final Theme/Button/Announcement/Hero/Footer Audit - 2026-07-03

## Scope
This audit focused on the remaining UI theme issues reported after the previous full theme fix:

- Buttons not matching dark/light mode on some pages
- Announcement Board and Announcement Management page not styling correctly
- Dashboard/hero sections not following the theme consistently
- Footer visibility problems in dark mode
- Sidebar and dropdown readability after theme switching

## Root Cause
Several older CSS blocks forced light colors even when the theme was set to dark. The strongest problem was an old `html[data-theme="dark"]` selector that was grouped with the light theme variables. This caused pages with newer theme logic to still inherit light surfaces, dark text, and hardcoded white backgrounds.

The Announcement module also had many custom classes with hardcoded white backgrounds and dark text. Some cards, hero panels, buttons, badges, dropdowns, and footer areas were therefore not fully controlled by the shared theme variables.

## Fix Applied
A final global theme layer was added at the end of `assets/css/sis-theme.css`. It is intentionally last so it wins against older page styles.

It repairs:

- Light and dark CSS variables
- Buttons, including `btn-light`, `btn-outline-secondary`, `btn-outline-primary`, danger/success buttons, reset buttons, page links, and theme toggle buttons
- Announcement dropdown, Announcement Board, Announcement Management cards, announcement slider/showcase, pinned/read badges, empty states, and announcement hero
- Dashboard/page hero sections
- Footer surfaces and footer text
- Sidebar links/icons/section headings in dark mode
- Tables, forms, dropdowns, modals, badges, and legacy cards
- Notification and announcement dropdown visibility/readability

## Validation
- PHP syntax check passed for all PHP files.
- Main theme JavaScript syntax check passed.
- Verified that all PHP pages include the theme through the shared layout or CSS links.
- ZIP integrity test passed.

## After Deployment
Clear browser cache once with Ctrl+F5, then test:

1. Light mode
2. Dark mode
3. Admin Announcement Management page
4. User Announcement Board page
5. Dashboard announcement hero section
6. Footer on Admin, Supervisor, and Agent pages
7. Sidebar links in dark mode
8. Notification and announcement dropdown buttons


---

## Original file: `docs/SIS_FINAL_UPGRADE_REPORT.md`

# SIS Final Upgrade Report

## Source used
This package was prepared from the working SIS project structure and keeps the same PHP/MySQLi architecture, same folder layout, and same login/dashboard workflow.

## Correct local configuration
The project keeps the working AMpps configuration:

```php
DB_HOST = localhost
DB_USER = root
DB_PASS = [REDACTED]
DB_NAME = comium_gm
```

## Main fixes applied

### Login and dashboard
- Added root `dashboard.php` role router.
- Login now sends users to their proper dashboard:
  - Admin → `admin/dashboard.php`
  - Supervisor → `supervisor/dashboard.php`
  - Agent → `agent/dashboard.php`
  - Super Admin → `admin/dashboard.php`
- Redirects are relative-path safe, so pages inside `admin/`, `agent/`, and `supervisor/` redirect correctly to `../login.php` when needed.
- Login uses prepared statements and regenerates the session ID after successful login.

### AMpps/MySQL compatibility
- Removed the incompatible `SHOW COLUMNS ... LIKE ?` prepared statement style.
- Added `INFORMATION_SCHEMA` based table/column checks.
- Added `mysqli_report(MYSQLI_REPORT_OFF)` so compatibility checks do not throw fatal mysqli exceptions.

### Handling time tracking
- Added support for:
  - `attended_at`
  - `completed_at`
  - `handling_seconds`
- Visitor status updates now record when a request is attended and completed.
- Reports can show average handling time and transaction handling time.

### Complaint reasons
- Added configurable `complaint_reasons` table.
- Added Admin page: `admin/complaint_reasons.php`.
- Added complaint reason dropdown support for complaint categorization and reporting.

### Reports
- Improved Most Requested Services with:
  - Total column
  - Summary totals
  - Excel/DataTables export support
- Improved Daily Services Report with:
  - Morning Shift totals
  - Afternoon Shift totals
  - Night Shift totals
  - Separate ComCach total
  - Separate Other total
  - Overall total

### Shift reopen support
- Agent shift state is now backed by `agent_shifts`, while old cookie behavior remains for compatibility.
- Admin can reopen a mistakenly ended shift from the sales/shift monitoring area.
- Added `admin/reopen_shift.php`.

### Super Admin safety
- Added `super_admin` role support from the earlier request.
- Super Admin inherits Admin access.
- Super Admin actions are not mixed into the normal Admin audit log, but they are still recorded in `super_audit_log` for accountability.
- This is not an unlogged hidden backdoor.

### UI/UX and theme
- Added global theme CSS: `assets/css/sis-theme.css`.
- Added theme script: `assets/js/sis-theme.js`.
- Added light/dark mode support through a floating toggle.
- Improved responsiveness for cards, tables, forms, and reports.

## Database upgrade
Run this after login as Admin:

```text
http://localhost/SIS/admin/run_upgrade.php
```

The upgrade runner is idempotent: it checks columns/indexes before adding them where possible.

## Quality check
- PHP syntax lint was run against all PHP files.
- No PHP syntax errors were found.

## Important note
Back up the database before running the upgrade in production.


---

## Original file: `docs/SIS_FULL_AUDIT_ROLE_PASSWORD_PROFILE_HELP_FIX_2026_07_09.md`

# SIS Full Audit, Role, Password, Profile, Theme & Help Fix

Date: 2026-07-09
Project: COMIUM SIS Ultimate

## Audit result

A full code-level audit was performed on the uploaded PHP/MySQL project with focus on:

- Users & Roles password reset workflow
- Admin user management and role editing
- Supervisor Add Sale / Start Customer workflow
- Profile photo upload workflow
- Supervisors Management, Agents Management and Outlets Management theme consistency
- Role-based Help/User Guide page
- PHP syntax safety after the changes

## Main issues found

### 1. Users & Roles reset password popup was unreliable
The page rendered Bootstrap modals but did not load the Bootstrap JavaScript bundle. This prevented Reset Password modals and other modal actions from working correctly in many environments.

### 2. Users & Roles did not provide full user detail popup/edit flow
Admin users needed a double-click workflow to inspect a user, view account details and edit the record without leaving the page.

### 3. Supervisor menu pointed Start Customer / Add Sale to the older page
The Supervisor sidebar linked Start Customer / Add Sale to `supervisor/add_entry.php`, while the richer Agent-like workflow already existed in `supervisor/add_sale.php`. This made supervisors see a less capable workflow.

### 4. Profile photo upload had weak diagnostics and brittle folder handling
The upload logic depended on a folder existing and on `move_uploaded_file()` succeeding without good fallback messaging. This caused upload failures on Windows/XAMPP-style local installs or when the upload folder was missing/not writable.

### 5. Management pages had mixed dark/light theme surfaces
Older page classes in Supervisors Management, Agents Management and Outlets Management still used hard-coded surfaces, making cards/tables/search areas inconsistent in dark mode.

### 6. Help page was too basic
The Help page did not explain all important pages/workflows and did not separate guidance by user role.

## Fixes applied

### `admin/users.php`
- Rebuilt the Users & Roles page action handling.
- Fixed reset password workflow.
- Added support for typed temporary passwords or auto-generated temporary passwords.
- Sets `must_change_password = 1` and updates `password_changed_at` when a password is reset.
- Added double-click row interaction to open a full user detail popup.
- Added View/Edit button for accessibility.
- Added editable user details modal: username, full name, email, role, outlet, shift, active status and optional password reset.
- Added protection so non-superadmin users cannot manage superadmin accounts.
- Added protection against admins changing their own role or disabling their own account from this page.
- Added Bootstrap JS bundle and SIS theme script to make modals and theme work properly.

### `profile.php`
- Rebuilt `sisProfileUploadPhoto()` with stronger validation and clearer upload error messages.
- Supports JPG, PNG, WEBP and GIF.
- Creates `assets/uploads/users` automatically if missing.
- Adds a safe `.htaccess` file inside the upload folder.
- Adds local fallback handling for environments where `move_uploaded_file()` fails unexpectedly.
- Applies safe file permissions after upload where supported.

### `supervisor/add_entry.php`
- Converted the old page into a compatibility redirect to `supervisor/add_sale.php`.
- This makes Supervisor Start Customer / Add Sale use the richer Agent-style flow.

### `include/side_header.php`
- Updated Supervisor sidebar so Start Customer / Add Sale opens `supervisor/add_sale.php`.
- Removed the confusing separate Sales Transactions menu link because the rich Add Sale page already contains transaction/edit workflow.

### `help.php`
- Rebuilt as an advanced role-based user guide.
- Agents see Agent guide only.
- Supervisors see Supervisor guide only.
- Admin and Super Admin see Admin, Supervisor and Agent guides.
- Added guidance for dashboard, add sale, same-customer workflow, edit sale, payments, support, shifts, reports, stock, targets, users/roles, outlets and settings.
- Added troubleshooting table for common issues.

### `assets/css/sis-theme.css`
- Added final management-page theme repair block.
- Fixed card/table/search/list/modal surfaces for dark and light mode.
- Improved hover/focus visibility for management tables.
- Normalized status badges and shift badges.
- Improved modal and alert visibility in dark mode.

### `admin/run_upgrade.php`
- Added upload folder check for `assets/uploads/users`.
- Creates folder and `.htaccess` automatically during upgrade.

### `config.php`
- Updated the asset cache-buster version so browsers load the new CSS/JS.
- Updated page registry so Supervisor Start Customer / Add Sale points to `supervisor/add_sale.php`.

## Validation performed

All PHP files were checked with PHP lint after the changes.

Result: **98 PHP files checked; 0 syntax errors found.**

Command used:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Important after-install steps

1. Upload/replace the fixed project files.
2. Login as System Owner/Admin.
3. Open **Run Upgrade** once so database columns and upload folders are checked.
4. Hard refresh the browser or clear cache so the new CSS loads.
5. Test:
   - Users & Roles → reset password
   - Users & Roles → double-click a user row → edit user
   - Supervisor → Start Customer / Add Sale
   - Profile → upload photo
   - Help page with Agent, Supervisor and Admin accounts

## Files changed

- `admin/users.php`
- `profile.php`
- `help.php`
- `assets/css/sis-theme.css`
- `include/side_header.php`
- `supervisor/add_entry.php`
- `config.php`
- `admin/run_upgrade.php`
- `docs/SIS_FULL_AUDIT_ROLE_PASSWORD_PROFILE_HELP_FIX_2026_07_09.md`


---

## Original file: `docs/SIS_FULL_AUDIT_SHIFT_THEME_POPUP_SECURITY_FIX_2026_07_03.md`

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


---

## Original file: `docs/SIS_FULL_PAGE_UI_THEME_HOVER_AUDIT_2026_07_03.md`

# SIS Full Page UI, Theme, Tables, Buttons, Cards & Hover Audit

Date: 2026-07-03
Package: COMIUM SIS Ultimate

## Scope audited

This pass covered the full navigation set requested by the user:

- Login
- Dashboard
- Announcements
- Help Center
- My Account
- Users & Roles
- Outlets
- Agents
- Supervisors
- Customer Care Setup
- Services
- Visit Purposes
- Purpose Categories
- Main/Sub Reasons
- Complaint / Support Form Builder
- Sales Report
- Advanced KPI Report
- Visitor Log
- Complaint / Support Reports
- Daily Showroom Report
- Shift Report
- Shift Requests
- Shift Allocation
- Cash Reconciliation
- Visitor Analytics
- Handle Time Debug
- Stock Management pages
- Stock Dashboard
- Stock Categories
- Receive Stock
- Transfer Stock
- Recent Transfers
- Stock Adjustments
- Stock Requests
- Stock Alerts
- Stock Reports
- Stock Thresholds
- Targets
- Settings
- Audit Logs
- Owner Audit Logs
- Run Upgrade
- Page Access Control
- Database Console
- Add Customer / Sale
- Complaint / Support
- My Shift
- My Performance
- Supervisor Start Customer / Add Sale
- Supervisor Sales Transactions
- Supervisor reports, stock, shifts, and targets pages

## Main UI fixes

### Theme consistency

- Added a final global theme override layer in `assets/css/sis-theme.css`.
- Dark/light mode now applies to old and new pages consistently.
- Fixed dark mode text visibility on sidebar, navbar, footer, cards, tables, forms, dropdowns, modals, filters, reports, and dashboard/hero blocks.
- Fixed login and standalone pages so the floating theme toggle is visible and usable.
- Added compatibility support for pages that forgot to load `sis-theme.js` explicitly.

### Buttons and hover states

- Standardized all Bootstrap and custom buttons.
- Added consistent hover behavior for:
  - `.btn`
  - `.action-btn`
  - `.tab-btn`
  - `.filter-btn`
  - `.page-link`
  - `.transfer-btn`
  - notification/theme buttons
- Improved hover contrast in dark mode.

### Tables

- Added automatic `table` and `table-hover` classes to plain tables through `sis-theme.js`.
- Ensured tables are wrapped safely in responsive containers.
- Fixed table header, row, striped, hover, active, danger, and footer colors in both light/dark mode.
- Applied same styling to custom report, region, compact, shift, stock, and transaction tables.

### Cards, sections and hero blocks

- Standardized cards, stat cards, KPI cards, report cards, stock cards, support cards, hero cards, announcement cards, filter cards, and profile cards.
- Added consistent border, radius, background, shadow, and hover behavior.
- Fixed dashboard and announcement hero blocks in dark mode.

### Sidebar, navbar and footer

- Improved sidebar text/icon visibility in dark mode.
- Improved active/hover states for sidebar links.
- Fixed footer readability in both themes.
- Fixed topbar, user chip, theme button, notification and announcement icons.

### Forms and filters

- Standardized input, select, textarea, sidebar search, table search, Add Customer/Sale search, and report filter fields.
- Added consistent focus ring and placeholder contrast.

### Alerts, badges and statuses

- Standardized inline alerts, badges, pills, status labels, empty states, notification items, and announcement read/unread states.

## Technical changes

### Updated files

- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`
- `include/footer.php`
- `docs/SIS_FULL_PAGE_UI_THEME_HOVER_AUDIT_2026_07_03.md`

### Validation

- PHP syntax check passed for all PHP files.
- Theme JavaScript syntax check passed.
- ZIP integrity test passed.

## Browser note

After replacing the project, press `Ctrl + F5` once to clear older cached CSS/JS before testing the theme.


---

## Original file: `docs/SIS_FULL_THEME_VISIBILITY_AUDIT_2026_07_03.md`

# SIS Full Theme Visibility Audit - 2026-07-03

## Scope
Audited the latest SIS package for theme consistency, dark mode readability, sidebar visibility, legacy card/table styling, and JavaScript theme switching behavior.

## Issues found

1. Some older CSS blocks were still forcing light colors even when `html[data-theme="dark"]` was active.
2. Several legacy cards/sections/tables used hard-coded white backgrounds or dark text, causing poor contrast in dark mode.
3. Sidebar section labels used an undefined variable (`--sis-text-muted`), causing inconsistent color inheritance.
4. The theme button updated only the first matching toggle button and did not add compatibility body classes for older pages.
5. Chart.js colors were not synchronized when users switched between light and dark mode.
6. Older sidebar/nav styles could override the newer include-based sidebar in dark mode.

## Fixes applied

- Added a final global theme audit layer at the end of `assets/css/sis-theme.css`.
- Defined complete light and dark variables including sidebar-specific colors.
- Hardened sidebar dark-mode contrast for links, icons, section titles, active states, footer, and search input.
- Hardened dark/light colors for cards, report sections, dashboards, filters, tables, forms, dropdowns, modals, accordions, badges, alerts, and pagination.
- Fixed theme toggle visibility and styling in both modes.
- Updated `assets/js/sis-theme.js` so all theme buttons stay synchronized.
- Added `sis-dark` / `sis-light` body classes for backward compatibility.
- Added Chart.js theme synchronization for chart labels, grid lines, and legends.

## Validation

- PHP syntax check passed for all project PHP files outside documentation.
- JavaScript syntax check passed for `assets/js/sis-theme.js`.
- ZIP integrity test passed.

## Notes

The final theme layer is intentionally placed at the bottom of `sis-theme.css` so it overrides older hard-coded CSS without needing to rewrite every historical page manually.


---

## Original file: `docs/SIS_HANDLE_TIMER_DATABASE_FIX_REPORT.md`

# SIS Handle Time Database-Backed Fix

Date: 17 June 2026

## Problem confirmed
The previous Handle Time implementation could still save every transaction as `1s` because the real start time depended on browser hidden fields or PHP session timing. If the JavaScript submit event, AJAX request, cache, or session context did not carry perfectly, PHP fell back to the submit moment, making the start and end time almost the same.

## Corrected workflow
The timer is now database-backed:

1. Agent/Supervisor clicks **Start Attending Customer / Add Sale**.
2. PHP creates a row in the new `handle_timers` table immediately.
3. The customer/sale form opens.
4. The live timer shows inside the form for the user.
5. On submit, PHP reads the timer token from the form.
6. PHP calculates the real duration using the `handle_timers.started_at` value from MySQL.
7. PHP saves the result into:
   - `sales.handle_started_at`
   - `sales.handle_completed_at`
   - `sales.handle_seconds`
   - `visitors.attended_at`
   - `visitors.completed_at`
   - `visitors.handling_seconds`

## Files changed
- `config.php`
  - Added `handle_timers` database-backed timer helpers.
  - Updated `getPostedHandleTiming()` to use the DB token first.
  - Added hidden `handle_timer_db_token` field to the timer widget.
  - Prevented the JavaScript AJAX timer from overriding a PHP-created DB timer.
- `handle_timer_start.php`
  - Now also creates a DB timer when used as fallback.
- `admin/run_upgrade.php`
  - Creates the `handle_timers` table.
- `migrations/2026_06_sis_upgrade.sql`
  - Added `handle_timers` table SQL.
- `admin/handle_timer_debug.php`
  - New testing page to verify timer records and latest sale handle times.

## Testing instructions
1. Replace the SIS folder with this package.
2. Run:
   `http://localhost/SIS/admin/run_upgrade.php`
3. Press **Ctrl + F5** in the browser.
4. Login as Agent.
5. Click **Start Attending Customer / Add Sale**.
6. Wait 20 seconds.
7. Submit the sale.
8. Check **Today's Transactions**. It should show about `20s`, not `1s`.
9. Admin can verify the raw timer records here:
   `http://localhost/SIS/admin/handle_timer_debug.php`

## PHP lint
All PHP files passed syntax lint after the fix.


---

## Original file: `docs/SIS_HANDLE_TIMER_FINAL_TRACE_AND_FIX_REPORT.md`

# SIS Handle Timer Final Trace and Fix Report

## Problem Found
The project already had several timer mechanisms at the same time:

- PHP hidden server timestamp fields
- JavaScript live timer fields
- AJAX/session timer token
- Database-backed `handle_timers` token
- `sales.handle_seconds` and `visitors.handling_seconds`

Because PHP could read the wrong fallback source first, the database timer was not always completed and some submissions could collapse to a default `1s` duration when start values were missing or reset.

## Flow Confirmed
The main transaction paths are:

- `agent/dashboard.php` → Add Customer / Sale form → POST `add_visitor`
- `agent/dashboard.php` → Add Sale Same Customer modal → POST `add_multi_sale`
- `agent/dashboard1.php` → legacy visible Add Customer / Sale form
- `agent/add_sale.php` → legacy add sale / multi-sale flow
- `agent/process_payment.php` → payment processing flow
- `agent/stock.php` → stock sale flow
- `supervisor/add_entry.php` → supervisor Add Customer / Sale form
- `supervisor/add_sale.php` → supervisor Add Customer / Sale and Same Customer sale flow

The transaction table is `sales`. Visitor/customer timing is also mirrored to `visitors` using:

- `visitors.attended_at`
- `visitors.completed_at`
- `visitors.handling_seconds`

Sales timing is saved to:

- `sales.handle_started_at`
- `sales.handle_completed_at`
- `sales.handle_seconds`

## Files Changed

### `config.php`
- Made `formatSecondsDuration()` show seconds even for hour-level durations.
- Added final required hidden fields:
  - `handle_started_at`
  - `handle_started_ts`
- Kept backward-compatible hidden fields for existing forms.
- Changed PHP submit logic so it completes the DB timer token first.
- Added server-hidden fallback using `handle_started_ts` / `handle_started_at`.
- Added automatic safe schema checks for handle-time storage.
- Updated JavaScript so it only displays the timer and does not become the only source of truth.
- Updated JavaScript to keep visible timer aligned with the server/DB start time.

### `handle_timer_start.php`
- Now returns `started_ts` and `db_token` to the browser when AJAX timer fallback is used.

### `agent/add_sale.php`
- Fixed wrong prepared-statement type strings for product sale inserts.
- Kept existing workflow and added no architecture changes.

### `supervisor/export_report.php`
- Added Handle Time, Started At, and Completed At columns to the CSV export.

### `export_report.php`
- Added missing admin CSV export handler used by `admin/sales.php`.
- Sales export now includes Handle Time, Handle Seconds, Started At, and Completed At.

### `admin/handle_timer_debug.php`
- Updated to show latest 20 timer records and latest 20 sales handle-time records.
- Uses the same handle-time storage checks before displaying debug data.

### `comium_gm.sql`
- Added `handle_timers` table structure, indexes, and auto-increment metadata.
- Existing `sales` and `visitors` handle-time columns remain supported.

## How to Test

1. Login as Agent.
2. Open `agent/dashboard.php`.
3. Click **Start Attending Customer / Add Sale**.
4. Wait 15 seconds.
5. Submit the transaction.
6. Confirm the transaction row shows around `15s`, not `1s`.
7. Repeat and wait 1 minute.
8. Confirm the transaction row shows around `1m 00s`.
9. Login as Supervisor.
10. Repeat from `supervisor/add_sale.php` or `supervisor/add_entry.php`.
11. Login as Admin and open:
    - `admin/sales.php`
    - `admin/reports.php`
    - `admin/handle_timer_debug.php`
12. Confirm the latest records show:
    - start time
    - completed time
    - handle seconds
    - formatted handle time
13. Test CSV export from Admin Sales and Supervisor Export Report.

## PHP Syntax Check
All PHP files passed `php -l` syntax validation.


---

## Original file: `docs/SIS_HANDLE_TIMER_SECONDS_ONLY_FIX_REPORT.md`

# Replaced by Start/End/Seconds Fix

This old seconds-only note has been replaced.

Current correct storage is:

```sql
sales.handle_started_at
sales.handle_completed_at
sales.handle_seconds
```

See:

```text
docs/SIS_HANDLE_TIMER_START_END_SECONDS_FIX_REPORT.md
```


---

## Original file: `docs/SIS_HANDLE_TIMER_SERVER_HIDDEN_FINAL_REPORT.md`

# SIS Handle Timer Server Hidden Final Fix

This build fixes the repeated `1s` Handle Time problem by adding a PHP-generated server start timestamp directly into each Agent/Supervisor form.

## Final timing workflow

1. Agent/Supervisor clicks **Start Attending Customer / Add Sale**.
2. PHP opens the form and writes `handle_server_started_ts` into the form.
3. The live JavaScript timer only displays the running time to the user.
4. On submit, PHP calculates duration using `time() - handle_server_started_ts`.
5. The calculated result is saved into `sales.handle_seconds` and visitor handling fields.

## Why this fixes the issue

The previous versions could still fall back to `1s` if the JavaScript, AJAX, database token, or session timer did not reach PHP correctly. This version does not depend on those for the final calculation.

The database-backed timer and JavaScript fields remain as fallback/debug helpers, but the posted PHP server-start timestamp is now the first source of truth.

## Files changed

- `config.php`
- All existing Agent/Supervisor pages continue using the shared `renderHandleTimerFields()` helper.

## Test

1. Run `admin/run_upgrade.php` once.
2. Press Ctrl + F5.
3. Login as Agent or Supervisor.
4. Click **Start Attending Customer / Add Sale**.
5. Wait 15–20 seconds.
6. Submit the form.
7. The transaction row should show around 15–20 seconds, not `1s`.


---

## Original file: `docs/SIS_HANDLE_TIMER_START_END_SECONDS_FIX_REPORT.md`

# SIS Handle Time Fix — Start Time, End Time, and Seconds

## What changed

The Handle Time feature has been corrected to store the three required values directly on the `sales` table:

```sql
handle_started_at DATETIME NULL
handle_completed_at DATETIME NULL
handle_seconds INT DEFAULT 0
```

The old separate timer table is not used. JavaScript is only used to show the live timer in the form. PHP/server time is still the trusted source used when the sale is submitted.

## Flow

1. Agent or Supervisor opens the customer/sale form.
2. The form prints server generated fields:
   - `handle_started_at`
   - `handle_started_ts`
3. The timer is visible in the form.
4. On submit, PHP calculates:

```php
$seconds = time() - (int)$_POST['handle_started_ts'];
```

5. The system saves:
   - `sales.handle_started_at`
   - `sales.handle_completed_at`
   - `sales.handle_seconds`
6. Transaction tables show the formatted Handle Time plus Start and End time under it.
7. Admin reports and CSV exports include the handle start/completed fields.

## Main files changed

- `config.php`
- `agent/dashboard.php`
- `agent/dashboard1.php`
- `agent/add_sale.php`
- `agent/process_payment.php`
- `agent/stock.php`
- `supervisor/add_entry.php`
- `supervisor/add_sale.php`
- `supervisor/export_report.php`
- `admin/sales.php`
- `admin/reports.php`
- `admin/handle_timer_debug.php`
- `admin/run_upgrade.php`
- `export_report.php`
- `comium_gm.sql`
- `migrations/2026_06_handle_time_start_end_seconds.sql`

## Upgrade

Open this page as Admin once:

```text
admin/run_upgrade.php
```

It adds missing `sales` columns and backfills old records where possible.

## Test

1. Login as Agent.
2. Open customer/sale form.
3. Wait 15 seconds.
4. Submit.
5. Transaction row should show about `15s` and show Start/End time.
6. Repeat for 1 minute.
7. Login as Supervisor and repeat.
8. Open:

```text
admin/handle_timer_debug.php
```

Confirm the latest row has `handle_started_at`, `handle_completed_at`, and `handle_seconds`.

## Syntax check

All PHP files passed `php -l` syntax validation.


---

## Original file: `docs/SIS_HANDLE_TIMER_UPDATE_REPORT.md`

# SIS Handle Time Timer Update

Date: 2026-06-17

## What changed

The Handle Time feature has been changed to match the requested workflow:

- The timer now starts when the agent/supervisor opens or starts the customer/sales form.
- The timer continues running while the user is serving the customer.
- The timer stops when the form is submitted.
- The time spent is saved against the transaction row.
- The transaction tables now show a **Handle Time** column.

## Database changes

The upgrade adds these fields to the `sales` table:

- `handle_started_at`
- `handle_completed_at`
- `handle_seconds`

The existing visitor handle-time fields are still kept:

- `attended_at`
- `completed_at`
- `handling_seconds`

This means the system can now report handle time both from the customer request side and from the transaction/sales row side.

## Pages updated

- `agent/dashboard.php`
- `agent/dashboard1.php`
- `agent/add_sale.php`
- `supervisor/add_sale.php`
- `supervisor/add_entry.php`
- `admin/reports.php`
- `admin/run_upgrade.php`
- `config.php`
- `assets/js/sis-theme.js`
- `assets/css/sis-theme.css`
- `migrations/2026_06_sis_upgrade.sql`

## How it works

1. User opens or starts the Add Customer / Add Sale form.
2. A live stopwatch appears on the form.
3. The hidden form fields capture the start time and elapsed seconds.
4. When the user submits the form, the elapsed time is saved.
5. The transaction row displays the recorded handle time.

## Required after upload

After replacing the project files, run:

```text
http://localhost/SIS/admin/run_upgrade.php
```

This creates the new `sales` handle-time fields.

## Validation

All PHP files passed syntax lint after the update.


---

## Original file: `docs/SIS_LAYOUT_INCLUDE_UI_REDESIGN_REPORT.md`

# SIS Layout Include and UI Redesign Report

## Summary
This update adds a real shared layout system for the SIS frontend instead of repeating different headers and navigation blocks on every page.

## New Include Folder
A new `include/` folder was added with:

- `include/side_header.php` — shared sidebar / side menu for Agent, Supervisor, and Admin layout pages.
- `include/navbar.php` — shared top navigation bar with page title, date, theme toggle, user chip, and logout.
- `include/footer.php` — shared footer plus common sidebar/mobile/scroll helper JavaScript.

## Main UI Fixes
- Agent pages now use the shared sidebar, navbar, content wrapper, and footer.
- Supervisor pages now use the shared sidebar, navbar, content wrapper, and footer.
- Admin support/report/debug pages were moved into the same shared layout.
- Record tables now scroll properly with both horizontal and vertical scrolling.
- Body/page overflow was fixed so records are no longer hidden when the table is long.
- Mobile sidebar toggle was added for smaller screens.
- Light/Dark theme toggle now works with the new shared navbar button.

## Redesigned Pages
The Complaint / Support pages were redesigned for Agent and Supervisor:

- Better page hero/header.
- Better complaint/support form layout.
- Cleaner customer details section.
- Cleaner ticket details section.
- Admin-created fields still display inside the form.
- Latest records now show inside a scrollable table.

The following pages now use the shared include layout:

### Agent
- `agent/dashboard.php`
- `agent/dashboard1.php`
- `agent/process_payment.php`
- `agent/profile.php`
- `agent/stock.php`
- `agent/support.php`

### Supervisor
- `supervisor/dashboard.php`
- `supervisor/add_entry.php`
- `supervisor/add_sale.php`
- `supervisor/agent_performance.php`
- `supervisor/cash_reconciliation.php`
- `supervisor/pending_payments.php`
- `supervisor/service_analysis.php`
- `supervisor/stock.php`
- `supervisor/stock_dashboard.php`
- `supervisor/stock_requests.php`
- `supervisor/support.php`
- `supervisor/targets.php`
- `supervisor/visitors.php`

### Admin
- `admin/support_forms.php`
- `admin/support_reports.php`
- `admin/handle_timer_debug.php`
- `admin/threshold_sethings.php`

## CSS / JS Updates
Updated:

- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`

Added layout styles for:

- shared sidebar
- shared topbar
- shared footer
- responsive content area
- scrollable record tables
- support form cards
- support report table layout
- mobile sidebar behavior

## Validation
All PHP files passed syntax validation.

Result file:

- `docs/php_lint_results_layout_includes.txt`

## How to Test
1. Login as Agent.
2. Open Agent Dashboard.
3. Confirm left sidebar, top navbar, footer, and page scroll work.
4. Open Complaint / Support.
5. Submit a test complaint.
6. Confirm latest records table scrolls.
7. Login as Supervisor.
8. Repeat the same test.
9. Login as Admin.
10. Open Support Form Builder, Support Reports, and Handle Timer Debug.
11. Confirm tables scroll and the layout is consistent.
12. Test on mobile width and confirm sidebar opens from the menu button.

No database upgrade is required for this layout update.


---

## Original file: `docs/SIS_LOGIN_DASHBOARD_FINAL_FIX.md`

# SIS Login and Dashboard Final Fix

Date: 2026-06-17

## Source used

This update was applied to the working SIS package supplied by the user: `SIS(1).zip`.

## Main issue found

The previous upgraded package had a wrong database value and redirect style:

- `DB_NAME` was set to `comium_gm2` in `config.php`.
- Redirects were being generated as absolute `/SIS/...` paths.
- If the project folder name changed, or the app was opened from a subfolder, the redirect could go to the wrong place.

## Correct database configuration restored

The project now uses the user's working database configuration:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', getenv('SIS_DB_PASS') ?: '');
define('DB_NAME', 'comium_gm');
```

## Redirect behavior fixed

The system now keeps the simple original workflow, but redirects safely from root and subfolders:

- From root pages, `login.php` stays `login.php`.
- From `admin/`, `agent/`, and `supervisor/`, login redirect becomes `../login.php`.
- After login:
  - Admin and Super Admin go to `admin/dashboard.php`.
  - Supervisor goes to `supervisor/dashboard.php`.
  - Agent goes to `agent/dashboard.php`.
- Root `dashboard.php` is a router that sends each user to the correct role dashboard.

## Files checked

- `config.php`
- `login.php`
- `dashboard.php`
- `logout.php`
- `admin/dashboard.php`
- `agent/dashboard.php`
- `supervisor/dashboard.php`
- all PHP files were lint-checked.

## Compatibility preserved

The following helper functions remain available so the upgraded modules continue working:

- `isAuthenticated()`
- `isLoggedIn()`
- `hasRole()`
- `requireLogin()`
- `requireRole()`
- `roleDashboardPath()`
- `appPath()`
- `appAsset()`
- `logAction()`
- `auditAction()`
- shift reopen helpers
- complaint reason helpers
- handling-time helpers

## Validation result

All PHP files passed syntax linting with no syntax errors.


---

## Original file: `docs/SIS_LOGO_TABLE_HOVER_FULL_CSS_AUDIT_2026_07_03.md`

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


---

## Original file: `docs/SIS_PROFESSIONAL_UI_REPORT_LOGO_TABLES_2026-06-18.md`

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


---

## Original file: `docs/SIS_PROJECT_AUDIT_UPGRADE_REPORT_2026-06-18.md`

# SIS Project Audit & Upgrade Report

**Project:** Showroom Information System (SIS)  
**Audit date:** 2026-06-18  
**Scope:** PHP/MySQL project audit, role access, calculation logic, handle timer logic, report filters, UI standardization, and local-run stability.

## 1. What was checked

I inspected the uploaded ZIP without rebuilding the system from scratch. The audit covered:

- Root configuration, authentication, session handling, role routing, and shared helpers.
- Admin, supervisor, and agent PHP pages.
- Sales, customer/visitor, complaint/support, report, stock, dashboard, export, and performance logic.
- Layout includes, sidebar/header/footer, CSS, JavaScript, table behavior, and page-level theme consistency.
- Database-facing logic for dates, payment status, report filters, summaries, handle time, and sales totals.
- PHP syntax across the entire project.

## 2. Validation result

- **PHP lint:** All 65 PHP files passed `php -l` after the upgrade.
- **JavaScript syntax:** `assets/js/sis-theme.js` passed Node syntax validation.
- **Unwanted support fallback text:** Removed; no remaining `Admin-created Form Fields` or `No active support form fields found` text was found after patching.

The full lint output is included in `docs/php_lint_results_full_audit_2026-06-18.txt`.

## 3. Main problems found

### Authentication and role access

- Several admin/supervisor/agent pages used loose `requireLogin()` + manual role checks instead of one consistent role guard.
- One agent profile page used older `isLoggedIn()` / direct session logic.
- A JSON/admin stock endpoint did not fail safely if the query failed.

### Calculation and reporting issues

- The handle-time system could save incorrect short values like `1s` when client-side hidden fields were reset or overwritten.
- Some reports accepted raw date/filter values without strict validation.
- The daily showroom report had inconsistent shift logic compared with the shared `getShift()` function.
- The supervisor export summary counted pending/unpaid values outside the selected date range.
- The admin sales summary could break when shift filtering was applied because the query referenced `agent_shifts` without joining it.
- The agent performance API queried `sales.sale_date`, but the actual schema uses `sales.payment_date`.

### UI and layout issues

- The daily showroom report had malformed layout markup around the shared layout renderer.
- The complaint/support page showed unwanted fallback/admin-builder text to agents and supervisors.
- Table controls were inconsistent across pages.
- The logo was not consistently available as a reusable asset across login/sidebar areas.

### Security and reliability issues

- Login lacked CSRF validation.
- Some settings/profile updates used direct SQL string interpolation.
- Some form status/date values were not restricted to allowed values.
- Production error handling could expose fatal messages or raw connection errors.
- Some export/report queries did not guard against query failure before fetching results.

## 4. What was fixed

### Core configuration and safety

- Improved `config.php` error handling:
  - production-safe display error behavior,
  - logging enabled,
  - graceful database connection failure page,
  - reusable input-cleaning helpers for dates, enums, searches, money, and safe query logging.
- Added helper functions:
  - `cleanDateInput()`
  - `cleanEnumInput()`
  - `cleanSearchInput()`
  - `safeQuery()`
  - `fetchOneValue()`
  - `moneyAmount()`

### Handle time / start-end-duration logic

- Reworked `getPostedHandleTiming()` to prioritize server-side session timer data over browser/client hidden values.
- Added safer fallback order:
  1. server session start time,
  2. posted server timestamp,
  3. client timestamp fallback,
  4. client seconds fallback.
- Updated visitor handle time storage to save:
  - `attended_at`,
  - `completed_at`,
  - `handling_seconds`.
- Updated agent/supervisor sales flows so `setSaleHandleTime()` and `applyVisitorHandleTime()` receive full start/end/seconds values, not seconds only.

### Reports, dashboards, and calculations

- Fixed admin daily showroom report:
  - validated date/filter values,
  - corrected shift calculation to match shared shift logic,
  - added outlet filter to agent mapping query,
  - guarded failed queries,
  - standardized DataTables length options.
- Fixed admin reports:
  - validated dates/search/filter values,
  - standardized length options: `10, 25, 50, 75, 100, 150, All`.
- Fixed admin sales page:
  - validated dates/status/shift filters,
  - added missing join for shift filter summary,
  - guarded summary queries.
- Fixed supervisor export report:
  - validated dates and report type,
  - guarded all report queries,
  - date-filtered pending and unpaid summary amounts,
  - date-filtered sales in agent performance export.
- Fixed admin dashboard:
  - validated date range,
  - guarded key report queries,
  - improved dashboard query failure handling.
- Fixed agent performance API:
  - replaced obsolete `sale_date` references with `payment_date`.

### Forms and data validation

- Added CSRF protection to login.
- Hardened sale edit/payment status update flows in agent and supervisor sale pages:
  - date inputs validated,
  - payment status restricted to `Paid`, `Pending`, `Unpaid`,
  - amount normalized,
  - key updates converted to prepared statements.
- Removed duplicate cancel-service update execution.
- Replaced broken legacy `agent/add_sale.php` with a safe redirect to the standardized supported form instead of leaving a page that referenced obsolete database columns.

### UI standardization

- Added a reusable SVG logo asset: `assets/images/logo.svg`.
- Applied logo to sidebar and login page.
- Extended `sis-theme.css` with logo and table-control styling.
- Extended `sis-theme.js` with a fallback table enhancer for plain tables:
  - page length options: `10, 25, 50, 75, 100, 150, All`,
  - search,
  - previous/next pagination,
  - simple clickable sorting,
  - responsive wrapping.
- Cleaned agent and supervisor complaint/support pages:
  - removed unwanted admin-builder/fallback language,
  - kept a professional standard-form message,
  - sanitized alert output.

### Settings/security page

- Added prepared helper for saving settings.
- Replaced direct string SQL in settings updates with prepared updates/inserts.
- Hardened profile update/password update flow with prepared statements.
- Escaped backup shell arguments and ensured backup directory exists.
- Bound audit-log cleanup date with a prepared statement.

## 5. Files changed

- admin/agents.php -> SIS/admin/agents.php differ
- admin/audit_logs.php -> SIS/admin/audit_logs.php differ
- admin/daily_showroom_report.php -> SIS/admin/daily_showroom_report.php differ
- admin/dashboard.php -> SIS/admin/dashboard.php differ
- admin/get_outlet_stock.php -> SIS/admin/get_outlet_stock.php differ
- admin/handle_timer_debug.php -> SIS/admin/handle_timer_debug.php differ
- admin/outlets.php -> SIS/admin/outlets.php differ
- admin/purpose_categories.php -> SIS/admin/purpose_categories.php differ
- admin/purposes.php -> SIS/admin/purposes.php differ
- admin/reconciliation.php -> SIS/admin/reconciliation.php differ
- admin/reports.php -> SIS/admin/reports.php differ
- admin/sales.php -> SIS/admin/sales.php differ
- admin/services.php -> SIS/admin/services.php differ
- admin/settings.php -> SIS/admin/settings.php differ
- admin/stock_adjust.php -> SIS/admin/stock_adjust.php differ
- admin/stock_alerts.php -> SIS/admin/stock_alerts.php differ
- admin/stock_categories.php -> SIS/admin/stock_categories.php differ
- admin/stock_products.php -> SIS/admin/stock_products.php differ
- admin/stock_receive.php -> SIS/admin/stock_receive.php differ
- admin/stock_reports.php -> SIS/admin/stock_reports.php differ
- admin/stock_requests.php -> SIS/admin/stock_requests.php differ
- admin/stock_transfer.php -> SIS/admin/stock_transfer.php differ
- admin/stock_transferrecennt.php -> SIS/admin/stock_transferrecennt.php differ
- admin/supervisors.php -> SIS/admin/supervisors.php differ
- admin/targets.php -> SIS/admin/targets.php differ
- admin/threshold_sethings.php -> SIS/admin/threshold_sethings.php differ
- admin/visitor_analytics.php -> SIS/admin/visitor_analytics.php differ
- admin/visitors.php -> SIS/admin/visitors.php differ
- agent/add_sale.php -> SIS/agent/add_sale.php differ
- agent/dashboard.php -> SIS/agent/dashboard.php differ
- agent/dashboard1.php -> SIS/agent/dashboard1.php differ
- agent/performace_api.php -> SIS/agent/performace_api.php differ
- agent/process_payment.php -> SIS/agent/process_payment.php differ
- agent/profile.php -> SIS/agent/profile.php differ
- agent/stock.php -> SIS/agent/stock.php differ
- agent/support.php -> SIS/agent/support.php differ
- assets/css/sis-theme.css -> SIS/assets/css/sis-theme.css differ
- Only in SIS/assets: images
- assets/js/sis-theme.js -> SIS/assets/js/sis-theme.js differ
- config.php -> SIS/config.php differ
- Only in SIS/docs: php_lint_results_full_audit_2026-06-18.txt
- export_report.php -> SIS/export_report.php differ
- include/side_header.php -> SIS/include/side_header.php differ
- login.php -> SIS/login.php differ
- supervisor/add_entry.php -> SIS/supervisor/add_entry.php differ
- supervisor/add_sale.php -> SIS/supervisor/add_sale.php differ
- supervisor/agent_performance.php -> SIS/supervisor/agent_performance.php differ
- supervisor/cash_reconciliation.php -> SIS/supervisor/cash_reconciliation.php differ
- supervisor/dashboard.php -> SIS/supervisor/dashboard.php differ
- supervisor/export_report.php -> SIS/supervisor/export_report.php differ
- supervisor/pending_payments.php -> SIS/supervisor/pending_payments.php differ
- supervisor/service_analysis.php -> SIS/supervisor/service_analysis.php differ
- supervisor/stock.php -> SIS/supervisor/stock.php differ
- supervisor/stock_dashboard.php -> SIS/supervisor/stock_dashboard.php differ
- supervisor/stock_requests.php -> SIS/supervisor/stock_requests.php differ
- supervisor/support.php -> SIS/supervisor/support.php differ
- supervisor/targets.php -> SIS/supervisor/targets.php differ
- supervisor/visitors.php -> SIS/supervisor/visitors.php differ
- support_helpers.php -> SIS/support_helpers.php differ

## 6. Database changes needed

No destructive database changes are required by this upgrade.

The project already contains previous migrations for handle-time and support modules. The upgraded `config.php` also auto-checks and creates missing handle-time columns when needed:

- `sales.handle_started_at`
- `sales.handle_completed_at`
- `sales.handle_seconds`
- `visitors.attended_at`
- `visitors.completed_at`
- `visitors.handling_seconds`

Recommended local setup:

1. Import `comium_gm.sql` into MySQL.
2. Open `config.php` and confirm DB name, user, password, and host match AMpps/XAMPP.
3. Run the app from Apache/PHP.
4. Log in as admin and open `admin/run_upgrade.php` once if your database is older than the current project files.

## 7. Remaining recommendations

- Perform a live browser test with real AMpps/XAMPP + MySQL data because this sandbox audit validated syntax/static logic, not a running Apache/MySQL instance.
- Add CSRF tokens to every POST form, not only login, for stronger production security.
- Gradually convert every remaining read-only legacy query to prepared statements. The highest-risk writes and filters were hardened, but some legacy list/report reads still use escaped/cast SQL.
- Consider adding automated smoke tests for login, role routing, add customer/sale, complaint submission, reports, export CSV, and handle-time timing.
- Replace the generated SVG brand mark with the official company logo file if you have a specific binary logo asset that must be used.

## 8. Final status

The upgraded package preserves the existing project, fixes the critical broken logic found during audit, standardizes the UI more consistently, improves report calculations and table behavior, and passes PHP syntax validation across all PHP files.


---

## Original file: `docs/SIS_REDIRECTION_FIX_REPORT.md`

# SIS Redirection Fix Report

Date: 2026-06-17

## Problem Fixed

The previous upgraded package could redirect users incorrectly when the system was opened from different folders such as:

- `/SIS/login.php`
- `/SIS/admin/dashboard.php`
- `/SIS/agent/dashboard.php`
- `/SIS/supervisor/dashboard.php`

The issue came from mixed relative redirects such as `../dashboard.php`, `../login.php`, and folder-dependent helper paths.

## Fix Applied

### 1. Root-safe application path helper

A new `appBaseUrl()` helper was added in `config.php`. It detects the project base folder automatically, for example `/SIS`, then builds redirects from the root of the app.

Examples:

- Login redirect now goes to `/SIS/login.php`
- Admin dashboard redirect now goes to `/SIS/admin/dashboard.php`
- Agent dashboard redirect now goes to `/SIS/agent/dashboard.php`
- Supervisor dashboard redirect now goes to `/SIS/supervisor/dashboard.php`

This works even when the current page is inside `admin`, `agent`, or `supervisor`.

### 2. Role router preserved

The root `dashboard.php` still works as the central role router:

- Admin and Super Admin → `admin/dashboard.php`
- Supervisor → `supervisor/dashboard.php`
- Agent → `agent/dashboard.php`

### 3. Manual folder redirects fixed

Manual redirects such as:

```php
header('Location: ../dashboard.php');
```

were replaced with:

```php
redirectTo(appPath('dashboard.php'));
```

This prevents wrong-folder redirects and redirect loops.

### 4. Agent profile compatibility fixed

`agent/profile.php` used `isLoggedIn()`, but the upgraded config used `isAuthenticated()`. A compatibility alias was added:

```php
function isLoggedIn(): bool {
    return isAuthenticated();
}
```

### 5. Correct database config preserved

The config keeps your real AMpps database settings:

```php
DB_HOST = localhost
DB_USER = root
DB_PASS = [REDACTED]
DB_NAME = comium_gm
```

## Validation

PHP syntax lint was run across all PHP files and passed with no syntax errors.

## Files Updated

- `config.php`
- Admin page role redirects
- Supervisor page role redirects
- Agent page role redirects
- `agent/profile.php`
- `admin/reopen_shift.php`


---

## Original file: `docs/SIS_ROLLBACK_UI_PROFESSIONAL_FIX_2026-06-18.md`

# SIS Rollback UI Professional Fix - 2026-06-18

## Base Used
This ZIP was rebuilt from the user-uploaded rollback file:
`SIS_ADMIN_UNIFIED_LAYOUT_PROFILE_FORM_FIXED_2026-06-17(1).zip`

No backend workflow was rebuilt. Existing login, database config, handle time, complaint/support records, Agent, Supervisor, and Admin workflows were preserved.

## Main Fixes

### 1. Shared Logo and Layout
- Added the provided logo to `assets/img/logo-rd.svg`.
- Updated `include/side_header.php` so Admin, Agent, and Supervisor sidebars use the same logo/brand block.
- Kept the shared layout files:
  - `include/side_header.php`
  - `include/navbar.php`
  - `include/footer.php`

### 2. Unified UI Design System
- Replaced the old mixed CSS in `assets/css/sis-page-components.css` with one clean component layer.
- Rebuilt `assets/css/sis-theme.css` with shared theme variables and Bootstrap overrides.
- Standardized cards, forms, buttons, tables, filters, modals, dashboard KPIs, report sections, alerts, and badges.
- Removed the old light-only visual conflicts that caused some pages to look different.

### 3. Light/Dark Theme Matching
- Updated all shared styles to use CSS variables.
- Fixed hard-coded card/table/form colors that were fighting dark mode.
- Added consistent theme handling in `assets/js/sis-theme.js`.

### 4. Table Filters and Pagination
- Updated DataTables pages to support record size choices:
  - 10
  - 25
  - 50
  - 75
  - 100
  - 150
  - All
- Added a native table enhancer for normal PHP tables with:
  - Show records dropdown
  - Search box
  - Previous / Next buttons
  - Record count display

### 5. Complaint / Support Form Cleanup
- Removed the visible text:
  - `Admin-created Form Fields`
  - `No active support form fields found. Please ask Admin to create fields first.`
- Agent and Supervisor complaint/support forms now show a cleaner customer-care form.
- Dynamic fields still work when Admin creates/enables them, but no admin technical message appears to users.

### 6. Report Page Improvements
- Improved Admin Complaint / Support Reports layout.
- Improved report cards, filters, tables, and actions to match the rest of the system.
- Kept Complaint / Support Reports under the Admin Reports menu.

### 7. Live Customer Time Redesign
- Redesigned the handle timer card using the shared UI style.
- Live Customer Time remains visible and still uses the existing working handle time backend logic.

## Changed Files
- `assets/img/logo-rd.svg`
- `assets/css/sis-theme.css`
- `assets/css/sis-page-components.css`
- `assets/js/sis-theme.js`
- `include/side_header.php`
- `config.php`
- `support_helpers.php`
- `agent/support.php`
- `supervisor/support.php`
- `admin/support_reports.php`
- `admin/support_forms.php`
- `admin/reports.php`
- `admin/daily_showroom_report.php`

## Validation
- PHP syntax check passed for all PHP files.
- JavaScript syntax check passed for `assets/js/sis-theme.js`.
- ZIP integrity test passed.

## Testing
1. Upload/replace the project files.
2. Hard refresh browser using `Ctrl + F5`.
3. Login as Admin and check Dashboard, Reports, Complaint / Support Reports, and Form Builder.
4. Login as Agent and check Dashboard, Add Customer / Sale, Complaint / Support, Stock, and Profile.
5. Login as Supervisor and check Dashboard, Add Customer / Sale, Sales Transactions, Complaint / Support, Reports, and Stock pages.
6. Confirm tables show: 10, 25, 50, 75, 100, 150, All.
7. Confirm Light/Dark theme looks consistent across pages.


---

## Original file: `docs/SIS_SHIFT_ADMIN_APPROVAL_THEME_REPAIR_2026_07_03.md`

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


---

## Original file: `docs/SIS_SHIFT_LOGIC_SYSTEM_AUDIT_FIX_2026_07_01.md`

# SIS Shift Logic + System Audit Fix

Date: 2026-07-01
Package: COMIUM SIS Ultimate

## Main shift issues fixed

1. **Duplicate attendance rows in shift reports**
   - Problem: When an agent ended a shift and later logged back in with Supervisor approval, the system could create more than one `agent_shifts` row for the same agent/date.
   - Impact: Shift Allocation and Shift Report pages could duplicate an agent row or show incorrect attendance totals.
   - Fix: Shift attendance joins now use a grouped aggregate helper instead of directly joining raw `agent_shifts` rows.

2. **Ended scheduled shift could restart from old browser cookie**
   - Problem: If the database shift was ended but the browser still had an old shift cookie, the Add Customer / Sale page could silently restart the shift.
   - Fix: The system now checks the database first. If today’s scheduled shift was already ended, the form stays locked until Supervisor login-access approval is granted.

3. **Supervisor-approved login access flow tightened**
   - Approved login access is marked as used after the agent logs in.
   - Duplicate pending login-access requests are avoided.
   - Supervisors receive notifications when an agent requests access after ending a shift or outside the allowed shift rule.

4. **Early shift end approval is safer**
   - Supervisor early-end approval now closes the active agent shift through a shared helper.
   - This keeps the workflow consistent across Dashboard, Shift Requests, My Shift, and Shift Reports.

5. **Shift delete action hardened**
   - Shift Allocation delete actions now use POST with CSRF protection instead of destructive GET links.
   - This prevents accidental deletion from copied links, browser prefetching, or external link clicks.

6. **Agent shift table repair added**
   - Added `ensureAgentShiftsTable()` to create/repair the `agent_shifts` table and required indexes.
   - This helps older databases that were missing shift columns or indexes.

## System audit fixes

1. **Database metadata cache fixed**
   - `tableExists()`, `tableColumnExists()`, `indexExists()`, and `constraintExists()` no longer permanently cache negative results.
   - This fixes cases where a table/column is created during Run Upgrade but still appears missing within the same request.

2. **Background APIs respect timeout**
   - Notification polling, performance API, and handle timer start now detect expired sessions correctly.
   - Background polling no longer keeps idle users logged in forever.

3. **Legacy Add Sale duplicate page removed from active use**
   - `agent/add_sale.php` now redirects to the maintained `agent/dashboard.php` Add Customer / Sale page.
   - The old duplicate file was saved in docs for reference.

4. **Safer PHP 8 handling**
   - Several direct `$_POST`/`$_GET` string reads were changed to safe defaults/casts to reduce PHP 8 deprecated null warnings.

## Files changed in this audit

- `config.php`
- `include/shift_helpers.php`
- `login.php`
- `logout.php`
- `notifications_api.php`
- `handle_timer_start.php`
- `agent/dashboard.php`
- `agent/add_sale.php`
- `agent/performace_api.php`
- `supervisor/shift_requests.php`
- `supervisor/shift_allocation.php`
- `supervisor/shift_report.php`
- `admin/shift_allocation.php`
- `admin/shift_report.php`
- `admin/stock_products.php`

## Validation performed

- PHP syntax check completed for all active PHP files.
- JavaScript syntax check completed for `assets/js/sis-theme.js`.
- ZIP integrity validation completed after packaging.

## After deployment

Login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

This will ensure the required shift tables, notification tables, columns, and indexes exist in the live database.


---

## Original file: `docs/SIS_SHIFT_SUPERADMIN_UPDATE_REPORT.md`

# SIS Shift Reopen + Super Admin Update Report

Date: 17 June 2026
Project: Existing SIS PHP/MySQL Application

## Important Security Decision

The request for a hidden privileged account whose actions are not recorded was implemented in a safer way:

- A `super_admin` role was added.
- Super Admin can access normal Admin pages and additional Super Admin pages.
- Super Admin actions are **not mixed into the normal Admin audit log**.
- Super Admin actions are still recorded in a separate `super_audit_log` table.
- Only Super Admin users can view `admin/super_audit_logs.php`.

This protects the system from untraceable privileged activity while still giving Super Admin oversight above normal Admin.

## New Features Added

### 1. Admin Reopen Shift

If an agent mistakenly presses **End Shift**, Admin can now reopen the same shift.

Location:

- `admin/sales.php`
- Section: **Agent Shift Monitoring > Completed Shifts Today**
- Button: **Reopen**

What happens:

1. Admin clicks **Reopen**.
2. The shift `logout_time` is cleared in `agent_shifts`.
3. The agent can log in again.
4. The agent dashboard reads the active DB shift and allows the agent to continue working.

### 2. Database-Backed Shift State

The agent shift system now uses the existing `agent_shifts` database table instead of depending only on browser cookies.

Updated file:

- `agent/dashboard.php`

New behavior:

- Starting a shift inserts an active row into `agent_shifts`.
- Ending a shift updates `logout_time`.
- Reopening a shift clears `logout_time`.
- Existing cookie behavior is preserved for compatibility.

### 3. Reopen Shift Handler

New file:

- `admin/reopen_shift.php`

Security:

- Admin/Super Admin only.
- POST only.
- CSRF protected.
- Logs the reopen action.

### 4. Super Admin Role

Added role:

- `super_admin`

Super Admin can:

- Access all Admin pages.
- View Super Admin audit logs.
- Perform higher-level system control actions safely.

### 5. Separate Super Admin Audit Log

New table:

- `super_audit_log`

New page:

- `admin/super_audit_logs.php`

Normal admins do not see Super Admin actions in `admin/audit_logs.php`. Super Admins can see those actions in the separate Super Admin audit page.

## Database Migration

New migration file:

- `migrations/2026_06_shift_superadmin_upgrade.sql`

It adds:

- `super_admin` role to `users.role`
- `super_audit_log` table
- Agent shift indexes
- Default Super Admin account

Default Super Admin:

- Username: `superadmin`
- No temporary owner password is distributed.

Change this password immediately after first login.

## Upgrade Runner Updated

Updated file:

- `admin/run_upgrade.php`

The upgrade runner can now apply:

- Complaint reason upgrades
- Handling-time upgrades
- Report indexes
- Shift reopen support
- Super Admin support
- Super audit log table

## Files Changed / Added

Changed:

- `config.php`
- `login.php`
- `logout.php`
- `agent/dashboard.php`
- `admin/sales.php`
- `admin/dashboard.php`
- `admin/run_upgrade.php`

Added:

- `admin/reopen_shift.php`
- `admin/super_audit_logs.php`
- `migrations/2026_06_shift_superadmin_upgrade.sql`
- `docs/SIS_SHIFT_SUPERADMIN_UPDATE_REPORT.md`

## Testing Completed

PHP syntax lint passed for all PHP files.

Command used:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Result:

- No PHP syntax errors detected.

## Recommended Deployment Steps

1. Back up the current SIS folder.
2. Back up the MySQL database.
3. Upload the upgraded source files.
4. Log in as Admin.
5. Open `admin/run_upgrade.php` and run the upgrade.
6. Log in as `superadmin` using the temporary password.
7. Change the Super Admin password immediately.
8. Test this flow:
   - Agent starts shift.
   - Agent mistakenly ends shift.
   - Admin opens `admin/sales.php`.
   - Admin clicks **Reopen** under completed shifts.
   - Agent logs in again and continues the same shift.


---

## Original file: `docs/SIS_SUPERADMIN_SECURITY_UI_AUDIT_2026_07_01.md`

# SIS Super Admin Security + UI Audit Upgrade

Date: 2026-07-01
Package base: `COMIUM_SIS_ULTIMATE_SESSION_EDIT_RECORD_FIX_2026_07_01.zip`

## Main request completed

The following Admin-side **Management & System** modules are now Super Admin only:

- `admin/targets.php`
- `admin/settings.php`
- `admin/audit_logs.php`
- `admin/super_audit_logs.php`
- `admin/run_upgrade.php`

Normal `admin` users will no longer see the **Management & System** sidebar section and cannot open those pages directly by URL.

## Security audit fixes

- Added `requireSuperAdmin()` in `config.php`.
- Direct access to Super Admin pages redirects non-Super Admin users back to their correct dashboard.
- Unauthorized Super Admin-page access attempts are audited.
- All Admin pages now require the Admin role instead of only checking that the user is logged in.
- Agent pages were checked and role-protected.
- Supervisor pages were checked and role-protected.
- Audit-log CSV export is now protected by Super Admin access when exporting audit/log data.

## UI audit fixes

- Sidebar visibility now respects the real logged-in role, not only the layout role.
- Super Admin sees the Admin menu plus **Management & System**.
- Normal Admin sees the Admin menu without **Management & System**.
- Added a final shared UI override so pages use the same:
  - light background
  - soft card style
  - table header style
  - button radius and spacing
  - input height and border radius
  - sidebar link style
  - topbar style
  - page header style
- Reduced heavy/dark typography for a cleaner professional look.

## Files changed

- `config.php`
- `include/side_header.php`
- `assets/css/sis-theme.css`
- `export_report.php`
- `admin/targets.php`
- `admin/settings.php`
- `admin/audit_logs.php`
- `admin/super_audit_logs.php`
- `admin/run_upgrade.php`
- Admin role guards across `admin/*.php`
- Agent/Supervisor role guards where needed

## Validation

- PHP syntax check passed across 84 PHP files.
- JavaScript syntax check passed for `assets/js/sis-theme.js`.
- ZIP integrity test passed after packaging.

## Notes

This upgrade does not require a new database migration because it only changes access control, menu visibility, and UI styling.


---

## Original file: `docs/SIS_UNIFIED_COMPONENT_TABLE_THEME_REPORT.md`

# SIS Unified Component, Table, and Theme Cleanup Report

## Scope
This update standardizes the frontend design across Admin, Agent, and Supervisor pages without changing the existing PHP/MySQL architecture, login flow, handle-time logic, complaint/support module, or database configuration.

## Main Fixes

### 1. Unified cards, forms, buttons, and tables
- Rebuilt `assets/css/sis-page-components.css` as a clean shared component layer.
- Rebuilt `assets/css/sis-theme.css` as the final design-system override.
- Standardized cards, KPI cards, forms, inputs, dropdowns, buttons, badges, alerts, modals, dashboards, filters, and tables.
- Kept old generated `sis-u-*` utility classes but changed their colors to use theme variables so old pages do not break.

### 2. Light and dark theme consistency
- Removed old hard-coded white/light component rules from the shared component CSS.
- Replaced hard-coded colors with theme variables.
- Improved dark mode for cards, tables, dropdowns, forms, modals, badges, and layouts.
- Updated `assets/js/sis-theme.js` so theme buttons stay synchronized across pages.

### 3. Unified table filtering and record length options
All enhanced tables now support these record display options:

- 10
- 25
- 50
- 75
- 100
- 150
- All

Updated DataTables pages:
- `admin/reports.php`
- `admin/daily_showroom_report.php`

Also added a native table enhancer in `assets/js/sis-theme.js` for normal PHP tables that do not use DataTables. It adds:
- Show records dropdown
- Search filter
- Previous / Next buttons
- Record count display

### 4. Scroll and responsiveness
- Improved table horizontal scroll.
- Improved record list scrolling.
- Improved responsive content wrappers for desktop, tablet, and mobile.
- Kept the shared include layout intact for Admin, Agent, and Supervisor.

## Files Changed
- `assets/css/sis-page-components.css`
- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`
- `admin/reports.php`
- `admin/daily_showroom_report.php`
- `docs/php_lint_results_ui_match.txt`

## Validation
- PHP lint completed successfully with 0 syntax errors.
- JavaScript syntax check passed for `assets/js/sis-theme.js`.
- No frontend `<style>` blocks, inline `style="..."` attributes, or CSS block comments remain in PHP/CSS/JS frontend files.

## Testing
1. Upload the updated project files.
2. Hard refresh the browser using `Ctrl + F5`.
3. Login as Admin and check Dashboard, Reports, Sales, Support Reports, Stock pages.
4. Login as Agent and check Dashboard, Add Customer/Sale, Support, Stock, Profile.
5. Login as Supervisor and check Dashboard, Add Entry, Sales Transactions, Reports, Stock pages.
6. Switch Light/Dark theme from the top bar and confirm all pages match.
7. Open tables with many records and confirm the dropdown shows 10, 25, 50, 75, 100, 150, and All.


---

## Original file: `docs/SIS_UPGRADE_REPORT.md`

# SIS Upgrade Report

Prepared for: SIS / Comium GM customer service system  
Upgrade date: 2026-06-17

## Upgrade approach

The system was not rebuilt from scratch. The existing PHP/MySQL architecture, folder structure, workflows, roles, and pages were preserved. Changes were added as targeted improvements inside the current application structure.

## Main issues identified

1. `login.php` redirected authenticated users to a missing root `dashboard.php`, which could break login flow.
2. `getShift()` was called with a transaction datetime in reports, but the function ignored the argument and always used the current time. This could classify historical transactions into the wrong shift.
3. Authentication used raw SQL for login lookup and did not regenerate the session ID after login.
4. Error display was enabled globally, which can expose sensitive details in production.
5. The system had no master table for complaint reasons.
6. Visitor records did not track first attended time, completed time, or handling duration.
7. Report pages did not show handling duration or allow handling-time filtering.
8. Daily services report mixed ComCach totals with overall totals, making reconciliation harder.
9. Most Requested Services report did not include a clear Total column or summary footer.
10. Many pages had separate inline CSS, making theme consistency difficult.

## Files added

- `dashboard.php`  
  Root role router for admin, supervisor, and agent dashboards.

- `assets/css/sis-theme.css`  
  Global responsive UI, light mode, dark mode, table, form, card, and dashboard styling.

- `assets/js/sis-theme.js`  
  Light/dark mode toggle with local storage support.

- `admin/complaint_reasons.php`  
  Admin master-list management for complaint reasons.

- `admin/run_upgrade.php`  
  Admin-safe database upgrade runner for existing databases.

- `migrations/2026_06_sis_upgrade.sql`  
  SQL migration for complaint reasons, handling-time columns, and reporting indexes.

- `docs/SIS_UPGRADE_REPORT.md`  
  This report.

## Backend and security improvements

- Reworked `config.php` into a safer shared bootstrap while preserving mysqli.
- Added environment variable support for database settings: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.
- Added safer session cookie settings: HTTP-only and SameSite=Lax.
- Disabled public error display by default; enable with `APP_DEBUG=1` in development.
- Added role dashboard routing helper.
- Added reusable helpers:
  - `roleDashboardPath()`
  - `requireRole()`
  - `appAsset()`
  - `e()` for safe HTML output
  - `tableExists()` / `tableColumnExists()`
  - `formatSecondsDuration()`
  - `markVisitorStatus()`
  - `markVisitorCompleted()`
  - `setVisitorAttended()`
  - `setVisitorComplaintReason()`
- Updated login to use prepared statements.
- Added session regeneration after successful login.
- Updated logout to use prepared statements and destroy session cookies cleanly.

## Database improvements

The migration adds:

### New table

`complaint_reasons`

Fields:

- `id`
- `reason`
- `category`
- `description`
- `is_active`
- `sort_order`
- `created_at`
- `updated_at`

### New visitor tracking columns

Added to `visitors`:

- `complaint_reason_id`
- `attended_at`
- `completed_at`
- `handling_seconds`

### New indexes

- Complaint reason active/sort index
- Visitor handling-time index
- Visitor attended/completed index
- Visitor complaint reason index
- Visitor reporting index
- Sales reporting index

## Functional enhancements implemented

### 1. Handle time tracking

Implemented helpers to track:

- When a customer request is first attended to.
- When the request is completed.
- Handling duration in seconds.
- Formatted handling duration in reports.
- Handling-time min/max filters in the analytics report.

Existing completed visitor records can be backfilled by running the upgrade.

### 2. Complaint reasons dropdown

- Added complaint reason master list.
- Added admin management page.
- Added complaint reason dropdown to key agent/supervisor customer-entry forms.
- Reports can now show complaint reason details when the migration is installed.

### 3. Most Requested Services Report

- Added `Total` column based on quantity/units where available.
- Added summary totals footer.
- Improved Excel export to include multiple relevant report sheets.

### 4. Daily Service Report enhancements

- Fixed shift classification using transaction datetime.
- Daily report now separates:
  - Morning shift
  - Afternoon shift
  - Night shift
- Added totals per shift in the summary tab.
- Added ComCach total, Other total, and Overall total.
- Added grand total footer.
- Excel export now includes footer totals.

## UI/UX improvements

- Added app-wide light/dark mode support.
- Added floating theme toggle button.
- Improved responsiveness for mobile/tablet layouts.
- Standardized cards, forms, tables, sidebars, and dashboard surfaces through global CSS.
- Improved table footers and totals visibility.
- Preserved existing workflows and page structure.

## Reporting improvements

- Added handling time to Transaction History.
- Added complaint reason to Transaction History and Complaint Logs where available.
- Added handling-time filtering.
- Added Total column and summary footer in Most Requested Services.
- Added Daily Services Report shift totals and ComCach separation.
- Fixed malformed ISP Device Sales table row structure in analytics report.

## Fresh install vs existing database

For a fresh install, `comium_gm.sql` has been updated with the new schema additions.

For an existing installation, use one of these methods after taking a backup:

1. Log in as admin and open:

   `admin/run_upgrade.php`

2. Or run the SQL migration manually:

   `migrations/2026_06_sis_upgrade.sql`

The admin upgrade runner is safer for existing systems because it checks whether columns already exist before adding them.

## QA performed

- Ran PHP syntax lint across all PHP files.
- Confirmed no PHP syntax errors were detected.
- Reviewed key upgrade files and patched obvious report/table issues.

## Important note

I could not perform a full browser/database runtime test because the sandbox does not have your live MySQL database, users, and production environment running. Before production deployment, test on a staging copy of the SIS database first, then run the upgrade migration and verify login, reports, exports, and customer-entry forms.


---

## Original file: `docs/TODAYS_TRANSACTIONS_UI_FIX_2026_07_01.md`

# Today's Transactions UI Fix - 2026-07-01

## Area updated
- Agent Add Customer / Sale transaction list
- Supervisor Sales Transactions transaction list
- Agent `dashboard1.php` now routes to the maintained Add Customer / Sale implementation to prevent old duplicate UI from showing

## What changed
- Replaced the old plain table with a professional transaction center.
- Added instant search by GSM, purpose, service, status, and amount.
- Added filters for payment status and transaction type.
- Improved table layout with clearer columns:
  - Time
  - Customer
  - Service Details
  - Quantity
  - Amount
  - Status
  - Handle Time
  - Actions
- Improved customer display with locked GSM note.
- Improved Sale/Withdrawal badges.
- Improved Paid/Pending/Unpaid badges.
- Improved amount color and Dalasis formatting.
- Added cleaner Edit and More Actions buttons.
- Added empty state and no-results state.
- Made the transaction section mobile-friendly.

## Files changed
- `agent/dashboard.php`
- `agent/dashboard1.php`
- `supervisor/add_sale.php`
- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`
- `docs/TODAYS_TRANSACTIONS_UI_FIX_2026_07_01.md`

## 2026-07-10 Exact Dark Mode Follow-up

Fixed the remaining light sections reported after dark mode toggle:

- `admin/supervisors.php` — Supervisor Permissions panel now has explicit dark-theme panel class.
- `admin/agents.php` — Shift Distribution panel now has explicit dark-theme panel class.
- `admin/outlets.php` — Region Distribution panel/header now has explicit dark-theme panel classes.
- `assets/css/sis-theme.css` — Added high-specificity dark-mode hardening rules for `html[data-theme=dark]`, `body[data-theme=dark]`, `body.sis-dark`, and `[data-bs-theme=dark]` fallbacks.
- `config.php` — Bumped CSS/JS cache version to force browsers to load the corrected theme file.

Markdown cleanup verified: this file remains the only `.md` file in the project.

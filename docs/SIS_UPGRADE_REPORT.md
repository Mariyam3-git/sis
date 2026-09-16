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

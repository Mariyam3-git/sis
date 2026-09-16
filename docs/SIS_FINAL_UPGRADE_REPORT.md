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

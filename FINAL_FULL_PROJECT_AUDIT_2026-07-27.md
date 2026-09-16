# Comium SIS Final Full-Project Audit

Date: 27 July 2026

## 28 July report and upload hotfix

- Fixed the global CSS rule that hid the Bootstrap tab containers—and therefore all tables—on Sales Report and Daily Services Report.
- Added safe report-query handling and compatibility for live databases where `agent_shifts` has not yet been created.
- Updated sales exports to use the same shift-table compatibility logic.
- Improved profile upload permission recovery and the System Upgrade upload-folder check.
- Marked `assets/uploads/users` writable in the deployment package; its `.htaccess` continues to block executable uploads.

## 28 July final full-project audit

The final deployment source was reviewed again across authentication, role
authorization, CSRF, duplicate-submit protection, sales, visitors, shifts,
notifications, reports, exports, uploads, database upgrades, local links,
JavaScript, CSS, and deployment packaging.

Additional confirmed corrections:

- Separated the Agent workspace to match the Supervisor structure:
  `agent/dashboard.php` now provides the overview and `agent/sales.php`
  provides Add Customer / Sale and today’s transaction management.
- Kept one shared Agent controller for shift enforcement, sales validation and
  transaction calculations, preventing the two pages from drifting apart.
- Updated the Agent menu and page-access registry, and redirected the legacy
  `dashboard1.php` and `add_sale.php` routes to `agent/sales.php`.
- Corrected the Agent “Add Customer / Sale” menu route from the legacy
  `dashboard1.php` URL to the dedicated `sales.php` workflow.
- Changed `agent/dashboard1.php` into a real authenticated redirect so old
  bookmarks immediately resolve to `agent/sales.php` instead of running
  that page under the wrong URL.
- Changed the CSS/JavaScript cache version so browsers cannot keep serving the
  older stylesheet that hid the Sales Report and Daily Services Report tables.
- Made new visitor-and-sale creation atomic for both Agent and Supervisor
  workflows. If the sale insert fails, the transaction rolls back and does not
  leave an incomplete visitor record.
- Added explicit sale-insert failure handling instead of showing a success
  message after a failed insert.
- Added Agent-side service validation before accessing service configuration.
- Added a safe server-side quantity range and replaced fragile `SHOW COLUMNS`
  result handling with the shared schema-compatibility helper.
- Added CSRF enforcement to the authenticated handle-timer POST endpoint.
- Confirmed the legacy delete/approve/reject/restore links are converted into
  CSRF-protected POST submissions, while direct mutation GET requests receive
  HTTP 405.

Final automated checks:

- 94 active PHP application files parsed successfully with zero syntax errors.
- Every project JavaScript file passed syntax checking.
- Static local `href` and `src` validation found zero missing files.
- No executable-code primitives such as `eval`, shell execution, unsafe
  deserialization, or encoded payload loaders were found in active application
  code.
- Database credentials remain environment-configurable and production error
  display is disabled unless `SIS_DEBUG` is explicitly enabled.
- ZIP integrity and upload-directory permission metadata are verified before
  delivery.

Deployment requirement:

- Preserve the live database and existing `assets/uploads/users` photos.
- Do not import `comium_gm.sql` over the live database.
- Run `admin/run_upgrade.php` once after replacing the files.

## Scope

The active PHP application, JavaScript, database upgrade paths, authentication,
roles, forms, sales workflows, reports, exports, maintenance tools, uploads,
navigation, assets, and production web-root protections were reviewed.

## Confirmed corrections

- Removed the false record-count-dependent duplicate submission failure.
- Kept CSRF and duplicate-submit protection separate and applied protection to
  authenticated Admin, System Owner, Supervisor, and Agent POST requests.
- Converted legacy delete, restore, approve, reject, receive, and similar
  data-changing links to CSRF-protected POST submissions. Direct mutation GET
  requests now return HTTP 405.
- Removed automatic creation/reactivation of a predictable System Owner account
  from the upgrade page, SQL dump, and migration.
- Removed historical privileged credentials and database passwords from bundled
  documentation.
- Added configurable login-attempt throttling using `max_login_attempts`.
- Changed database backup and log cleanup to confirmed POST operations.
- Database backups now run without placing the password in the command line and
  are stored outside the public application directory with restricted file
  permissions.
- Added server-side password confirmation and minimum-length validation to the
  Settings profile workflow.
- Escaped stored Settings and account values before HTML output.
- Fixed reporting date ranges, same-day averages, filters, shift attribution,
  duplicate shift joins, optional handling-time columns, and CSV exports.
- Standardized responsive report table styling.
- Fixed Night shifts across midnight so the assignment, active session, early
  logout, overtime, and reopen actions retain the shift's original work date.
- Added database advisory locking when starting shifts to prevent duplicate
  active sessions from simultaneous requests.
- Added strict real-time validation for shift start/end values.
- Managed-schedule agents without a current allocation now require Supervisor
  login approval instead of silently bypassing the schedule.
- Overtime-decline logout deadlines are stored in the database and enforced
  after refresh, navigation, or reopening the browser.
- Fixed System Owner notification read scope and added an on/off notification
  chime using the browser audio system.

## Validation results

- 92 active PHP files parsed successfully.
- Main JavaScript and footer inline scripts passed syntax validation.
- No missing static local assets were found.
- No merge-conflict markers were found.
- No active `system`, `shell_exec`, `passthru`, or `exec` calls remain.
- No known bundled temporary privileged password or database password remains.
- ZIP integrity validation must pass before deployment.

## Live deployment

Replace the PHP application files, but keep the live database and environment
credentials. Do not import `comium_gm.sql` over a live database. Back up the
database first, preserve `.env`/server environment values and uploaded profile
photos, then open the System Owner upgrade page once if the live database is
missing newer optional columns or tables.

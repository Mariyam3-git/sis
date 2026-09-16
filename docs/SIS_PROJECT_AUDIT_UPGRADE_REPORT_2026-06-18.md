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

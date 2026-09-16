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

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

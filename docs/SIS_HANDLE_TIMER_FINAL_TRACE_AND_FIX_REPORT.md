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

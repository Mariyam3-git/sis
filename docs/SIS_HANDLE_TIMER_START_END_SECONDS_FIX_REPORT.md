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

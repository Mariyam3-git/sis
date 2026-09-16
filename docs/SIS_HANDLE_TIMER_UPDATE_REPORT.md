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

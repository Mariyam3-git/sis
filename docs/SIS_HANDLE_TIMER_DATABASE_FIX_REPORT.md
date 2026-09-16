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

# SIS Shift Logic + System Audit Fix

Date: 2026-07-01
Package: COMIUM SIS Ultimate

## Main shift issues fixed

1. **Duplicate attendance rows in shift reports**
   - Problem: When an agent ended a shift and later logged back in with Supervisor approval, the system could create more than one `agent_shifts` row for the same agent/date.
   - Impact: Shift Allocation and Shift Report pages could duplicate an agent row or show incorrect attendance totals.
   - Fix: Shift attendance joins now use a grouped aggregate helper instead of directly joining raw `agent_shifts` rows.

2. **Ended scheduled shift could restart from old browser cookie**
   - Problem: If the database shift was ended but the browser still had an old shift cookie, the Add Customer / Sale page could silently restart the shift.
   - Fix: The system now checks the database first. If today’s scheduled shift was already ended, the form stays locked until Supervisor login-access approval is granted.

3. **Supervisor-approved login access flow tightened**
   - Approved login access is marked as used after the agent logs in.
   - Duplicate pending login-access requests are avoided.
   - Supervisors receive notifications when an agent requests access after ending a shift or outside the allowed shift rule.

4. **Early shift end approval is safer**
   - Supervisor early-end approval now closes the active agent shift through a shared helper.
   - This keeps the workflow consistent across Dashboard, Shift Requests, My Shift, and Shift Reports.

5. **Shift delete action hardened**
   - Shift Allocation delete actions now use POST with CSRF protection instead of destructive GET links.
   - This prevents accidental deletion from copied links, browser prefetching, or external link clicks.

6. **Agent shift table repair added**
   - Added `ensureAgentShiftsTable()` to create/repair the `agent_shifts` table and required indexes.
   - This helps older databases that were missing shift columns or indexes.

## System audit fixes

1. **Database metadata cache fixed**
   - `tableExists()`, `tableColumnExists()`, `indexExists()`, and `constraintExists()` no longer permanently cache negative results.
   - This fixes cases where a table/column is created during Run Upgrade but still appears missing within the same request.

2. **Background APIs respect timeout**
   - Notification polling, performance API, and handle timer start now detect expired sessions correctly.
   - Background polling no longer keeps idle users logged in forever.

3. **Legacy Add Sale duplicate page removed from active use**
   - `agent/add_sale.php` now redirects to the maintained `agent/dashboard.php` Add Customer / Sale page.
   - The old duplicate file was saved in docs for reference.

4. **Safer PHP 8 handling**
   - Several direct `$_POST`/`$_GET` string reads were changed to safe defaults/casts to reduce PHP 8 deprecated null warnings.

## Files changed in this audit

- `config.php`
- `include/shift_helpers.php`
- `login.php`
- `logout.php`
- `notifications_api.php`
- `handle_timer_start.php`
- `agent/dashboard.php`
- `agent/add_sale.php`
- `agent/performace_api.php`
- `supervisor/shift_requests.php`
- `supervisor/shift_allocation.php`
- `supervisor/shift_report.php`
- `admin/shift_allocation.php`
- `admin/shift_report.php`
- `admin/stock_products.php`

## Validation performed

- PHP syntax check completed for all active PHP files.
- JavaScript syntax check completed for `assets/js/sis-theme.js`.
- ZIP integrity validation completed after packaging.

## After deployment

Login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

This will ensure the required shift tables, notification tables, columns, and indexes exist in the live database.

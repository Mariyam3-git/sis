# SIS Handle Timer Server Hidden Final Fix

This build fixes the repeated `1s` Handle Time problem by adding a PHP-generated server start timestamp directly into each Agent/Supervisor form.

## Final timing workflow

1. Agent/Supervisor clicks **Start Attending Customer / Add Sale**.
2. PHP opens the form and writes `handle_server_started_ts` into the form.
3. The live JavaScript timer only displays the running time to the user.
4. On submit, PHP calculates duration using `time() - handle_server_started_ts`.
5. The calculated result is saved into `sales.handle_seconds` and visitor handling fields.

## Why this fixes the issue

The previous versions could still fall back to `1s` if the JavaScript, AJAX, database token, or session timer did not reach PHP correctly. This version does not depend on those for the final calculation.

The database-backed timer and JavaScript fields remain as fallback/debug helpers, but the posted PHP server-start timestamp is now the first source of truth.

## Files changed

- `config.php`
- All existing Agent/Supervisor pages continue using the shared `renderHandleTimerFields()` helper.

## Test

1. Run `admin/run_upgrade.php` once.
2. Press Ctrl + F5.
3. Login as Agent or Supervisor.
4. Click **Start Attending Customer / Add Sale**.
5. Wait 15–20 seconds.
6. Submit the form.
7. The transaction row should show around 15–20 seconds, not `1s`.

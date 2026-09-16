# Hotfix: Session Timeout and Editable Customer/Sale Records

Date: 2026-07-01

## Fixed

- Reworked the 30-minute inactivity timeout so stale sessions do not trap Admin users in a login/dashboard redirect loop.
- Added safe session helper functions in `config.php`:
  - `sisSessionTimeoutSeconds()`
  - `sisSessionExpired()`
  - `sisDestroyCurrentSession()`
  - `sisTouchSessionActivity()`
- Updated `login.php` to clear expired sessions before showing the login form.
- Updated `logout.php` to preserve the timeout notice when automatic logout happens.
- Updated `notifications_api.php` so notification polling does not refresh `last_activity`. This means the notification auto-refresh will not stop the 30-minute inactivity logout.

## Improved Add Customer / Sale Editing

- Agent Dashboard and Supervisor Add Sale now allow editing customer/sale records.
- The GSM/customer phone number is locked and cannot be edited from the edit modal.
- Editable fields now include:
  - Purpose
  - Service
  - Quantity
  - Amount
  - Sale date
  - Payment status
  - Record status
  - Notes
- Edit actions add an audit stamp and log `EDIT_CUSTOMER_SALE`.

## Files changed

- `config.php`
- `login.php`
- `logout.php`
- `notifications_api.php`
- `agent/dashboard.php`
- `supervisor/add_sale.php`
- `docs/HOTFIX_SESSION_EDIT_RECORD_2026_07_01.md`

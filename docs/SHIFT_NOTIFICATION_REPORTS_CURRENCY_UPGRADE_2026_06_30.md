# Shift, Notifications, Reports, and Currency Upgrade — 2026-06-30

## Main workflow fixes

- Added Supervisor-approved **login access requests** for Agents who try to log in outside the normal shift rule.
- If an Agent already ended a scheduled shift and tries to log in again before the scheduled end time, the system now blocks silent restart and sends a Supervisor approval request.
- Supervisor can approve/reject login access from **Supervisor > Shift Requests**.
- Approved login access is consumed when the Agent logs in successfully, so old approvals are not reused forever.
- Early shift logout now requires an approved Supervisor decision. A pending request alone no longer allows early logout.

## Notifications

- Added a top navbar notification bell for Admin, Supervisor, and Agent layouts.
- Added role/outlet notification storage using `user_notifications`.
- Agent early-end requests notify the Supervisor.
- Agent overtime confirmations notify the Supervisor.
- Supervisor decisions notify the Agent.
- Supervisor screens show popup/toast alerts for pending requests.

## UI and navigation

- Navbar is fixed at the top across the project.
- Admin sidebar now has a search box for quick page access.
- UI typography was softened so the project is not too bold/dark.

## Reports and currency

- All visible money labels were changed to **Dalasis `D`**.
- Settings currency and currency symbol defaults are now `D`.
- Daily Showroom Report now handles databases that do not yet have `visitors.quantity`.
- Daily Showroom Report category filtering now properly hides Telecom/ISP columns.
- Daily Showroom Report DataTables export was repaired with Excel/PDF/Print support and better length options.
- Sales Reports tables now use better scrolling, length options, and display handling.

## Database changes

New/updated database structures:

- `user_notifications`
- `shift_requests.request_type` now supports `login_access`
- `shift_requests.used_at`
- `settings.currency` and `settings.currency_symbol` set to `D`

Run this after upload:

`Admin > Management & System > Run Upgrade`

# Hotfix: Settings, Notifications, Reports, Session Control

Date: 2026-06-30

## Fixed

- Fixed PHP 8 warnings in `admin/settings.php` caused by missing `currency` and `currency_symbol` POST values.
- Currency is fixed to Gambian Dalasis: `D`.
- Removed Logout from the top navbar.
- Added logout confirmation to sidebar/manual logout links.
- Added 30-minute inactivity logout:
  - Server-side enforcement in `config.php`.
  - Client-side redirect after 30 minutes of no activity.
  - Timeout logout does not close an agent shift; it only ends the web session.
- Added live notification refresh:
  - New `notifications_api.php` endpoint.
  - Notification bell auto-refreshes every 20 seconds.
  - Users can mark one notification as read by opening it.
  - Users can mark all notifications as read.
  - New notification toast appears without full page reload.
- Rebuilt `admin/daily_showroom_report.php` into a safer report using the sales table as the source of truth.
- Improved Daily Services Report filters, totals, ComCach/ISP/Telecom separation, shift totals, and instant table search.
- Adjusted Admin sidebar menu so `Sales Report` opens the cleaner `admin/sales.php` page and the older report is labelled `Advanced KPI Report`.

## Files Changed

- `admin/settings.php`
- `admin/daily_showroom_report.php`
- `admin/super_audit_logs.php`
- `config.php`
- `login.php`
- `logout.php`
- `include/navbar.php`
- `include/footer.php`
- `include/side_header.php`
- `notifications_api.php`
- `docs/HOTFIX_SETTINGS_NOTIFICATIONS_REPORTS_SESSION_2026_06_30.md`

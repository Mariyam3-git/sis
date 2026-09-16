# SIS Announcement Module Upgrade - 2026-07-01

## Summary
Added a complete Announcement module for COMIUM SIS.

## New files
- `include/announcement_helpers.php`
- `announcements.php`
- `announcements_api.php`
- `admin/announcements.php`

## Updated files
- `include/navbar.php`
- `include/footer.php`
- `include/side_header.php`
- `admin/dashboard.php`
- `agent/dashboard.php`
- `supervisor/dashboard.php`
- `admin/run_upgrade.php`
- `assets/css/sis-theme.css`

## Features
- Admin and Super Admin can create/edit/publish/archive/delete announcements.
- Announcement audience can be:
  - All users
  - Agents
  - Supervisors
  - Admins
  - Super Admin
- Optional outlet targeting.
- Optional start/end date and time.
- Priority levels: Info, Important, Urgent.
- Navbar megaphone icon with unread count.
- Auto-refresh announcement icon every 30 seconds.
- Users can mark one announcement or all announcements as read.
- Active announcements are displayed on Admin, Supervisor, and Agent dashboards.
- The old dashboard hero/welcome areas are replaced with announcement panels.

## Database tables
Run Upgrade creates/checks:
- `announcements`
- `announcement_reads`

The announcement pages also auto-create the tables safely if the upgrade has not run yet.

## After installation
Login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

Then Admin/Super Admin can manage announcements from:

`Admin > Announcements`

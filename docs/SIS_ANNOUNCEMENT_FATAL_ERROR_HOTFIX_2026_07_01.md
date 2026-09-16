# SIS Announcement Fatal Error Hotfix - 2026-07-01

## Problem Fixed
The Announcement Management page could crash with:

```text
Fatal error: mysqli_fetch_assoc(): Argument #1 ($result) must be of type mysqli_result, bool given in admin/announcements.php on line 184
```

## Root Cause
Older databases that already had the `announcements` table did not always have the newer columns:

- `is_pinned`
- `requires_ack`

The page used those columns in the stats/list queries. When the columns were missing, MySQL returned `false`, and PHP then called `mysqli_fetch_assoc()` on that failed result.

## Fixes Applied
- Added `is_pinned` and `requires_ack` to the automatic repair list in `include/announcement_helpers.php`.
- Made `admin/announcements.php` stats query safe so it will not crash if a query fails.
- Added safer announcement list query error handling.
- Made outlet loading safe when the `outlets` table is unavailable.
- The page now shows a warning message instead of a fatal error if the database needs repair.

## Files Updated
- `include/announcement_helpers.php`
- `admin/announcements.php`

## Required Step After Upload
Login as Super Admin and run:

```text
Admin > Management & System > Run Upgrade
```

This will add/repair the missing columns in the live AMpps/MySQL database.

# SIS Alert, 404, Access, Theme, Help Audit - 2026-07-01

## Completed upgrades

- Replaced default browser alert behavior with a centered branded modal popup.
- Flash alerts now also show as centered popups.
- Logout confirmation now uses the centered confirmation modal.
- Notification and announcement dropdowns were repaired with safer Bootstrap/fallback behavior.
- API-generated notification and announcement links now use root-safe paths so redirection works from admin, supervisor, agent, and root pages.
- Added custom 404 Not Found page with Back and Dashboard buttons.
- Added `.htaccess` ErrorDocument support for the custom 404 page.
- Added logo to the sidebar and login page.
- Added SVG favicon support to PHP pages.
- Improved dark mode/light mode styling.
- Added shared Help Center page for all logged-in users.
- Added Super Admin Page Access Control page.
- Normal Admin cannot see or set Super Admin-only announcement audience.
- Normal Admin still cannot see Management & System pages.
- Added stronger POST refresh protection/PRG redirects on more forms so refresh does not submit again.

## New files

- `404.php`
- `help.php`
- `admin/page_access.php`
- `assets/images/favicon.svg`
- `.htaccess`

## Important note

After replacing the project, login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

This creates/repairs the `page_access_rules` table used by the new Page Access Control module.

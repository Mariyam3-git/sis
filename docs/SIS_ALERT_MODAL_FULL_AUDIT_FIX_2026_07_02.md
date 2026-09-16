# SIS Alert Modal + System Audit Fix — 2026-07-02

## Main problem fixed
Some centered alert popups could show but the OK button did not close them. The alert system was depending on Bootstrap `data-bs-dismiss` behavior. If Bootstrap JS/CDN was delayed, blocked, or not attached correctly, the Promise resolved but the modal stayed visible.

## Fixes applied
- Rebuilt the center alert/confirm close logic in `include/footer.php`.
- OK, Cancel, close button, and Escape now close the modal reliably.
- Added a fallback backdrop for pages where Bootstrap JS is unavailable.
- Added stronger z-index rules so center alerts and normal Bootstrap modals stay clickable above the fixed navbar, notifications, and overlays.
- Improved dropdown fallback for Notification and Announcement menus.
- Fixed `login.php` broken/missing closing layout tags.
- Protected `admin/partials_sales_summary_table.php` from direct URL access.
- Normal Admin announcement statistics no longer count Super Admin-only announcements.

## Duplicate submit / refresh audit
The existing global POST one-time token guard was rechecked. It is still active through `config.php` and protects old forms from browser refresh resubmission. API polling endpoints remain excluded so notifications and announcements do not break.

## Validation
- PHP syntax check: passed for all PHP files.
- JavaScript syntax check: passed for `assets/js/sis-theme.js` and extracted footer JavaScript.
- ZIP package integrity should be tested after packaging.

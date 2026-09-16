# SIS Announcement Showcase + Refresh Guard Audit — 2026-07-01

## Request
Improve the Announcement display, show only one announcement at a time in the dashboard hero section, remove the placeholder text, and audit the system so refreshing after form submission does not create duplicates.

## Completed

### Announcement UI
- Rebuilt the dashboard Announcement hero into a premium one-at-a-time showcase.
- Added next/previous controls and dots when multiple announcements exist.
- Added automatic rotation with pause-on-hover.
- Added pinned/new/read/priority badges.
- Added a styled empty state.
- Removed the text: `Announcements set by Admin/Super Admin will appear here for all users.`
- Added direct links from hero announcements to the Announcement Board.

### Duplicate submit protection
- Added a global one-time POST token system in `config.php`.
- Updated `csrfField()` so CSRF-protected forms automatically receive a one-time refresh guard token.
- Added footer JavaScript that injects a one-time token into older POST forms that do not yet call `csrfField()`.
- Added server-side duplicate POST blocking before page logic runs.
- Added client-side double-click submit disabling for all protected POST forms.
- Existing Add Customer / Sale and Announcement-specific request tokens remain in place.

## Important behavior
- First valid submit is processed normally.
- If the browser refreshes/retries the same POST, the reused token is blocked before database insert/update logic runs.
- The system redirects back to the same page and shows a duplicate-submission warning where flash messages are rendered.

## Files changed
- `config.php`
- `include/footer.php`
- `include/announcement_helpers.php`
- `announcements.php`
- `assets/css/sis-theme.css`
- `docs/SIS_ANNOUNCEMENT_SHOWCASE_REFRESH_GUARD_AUDIT_2026_07_01.md`

## Validation
- PHP syntax check passed for all active PHP files.
- JavaScript syntax check passed for `assets/js/sis-theme.js`.

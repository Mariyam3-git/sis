# SIS Announcement + Duplicate Submit Fix — 2026-07-01

## Fixed

- Stopped duplicate announcement creation when the browser refreshes after submit.
- Added POST → Redirect → GET flow to `admin/announcements.php`.
- Added one-time request tokens for announcement create/update/delete/status changes.
- Added one-time request tokens to Add Customer / Sale actions for Agent and Supervisor pages.
- Added duplicate-submit button guard for token-protected forms.

## Announcement improvements

- Added pinned announcements.
- Added read-action indicator.
- Improved Announcement Management UI with cleaner form, filters, stats, and reset button.
- Improved navbar announcement dropdown with pinned/read badges.
- Improved dashboard announcements with inline Mark Read button.
- Improved Announcement Board badges.

## Database changes

`Run Upgrade` will add these columns to `announcements` if missing:

- `is_pinned TINYINT(1) NOT NULL DEFAULT 0`
- `requires_ack TINYINT(1) NOT NULL DEFAULT 0`

## Changed files

- `config.php`
- `admin/announcements.php`
- `announcements.php`
- `announcements_api.php`
- `include/announcement_helpers.php`
- `include/navbar.php`
- `include/footer.php`
- `agent/dashboard.php`
- `supervisor/add_sale.php`
- `assets/css/sis-theme.css`

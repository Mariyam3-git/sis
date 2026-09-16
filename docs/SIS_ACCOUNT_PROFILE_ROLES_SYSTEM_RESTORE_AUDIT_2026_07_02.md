# SIS Account/Profile/Roles System Restore Audit — 2026-07-02

## Main fixes

1. Restored the Agent performance profile page from the provided reference profile file.
2. Added a Supervisor `My Performance` page with team sales, visitors, conversion rate, agent ranking, and chart.
3. Added separate `My Account` navigation for all users. Agents and Supervisors can update only their password and profile photo.
4. Fixed Admin/Super Admin role management reliability by:
   - Loading Bootstrap JavaScript globally from the shared footer.
   - Adding a modal fallback when Bootstrap JS is unavailable.
   - Adding an inline role selector and Save button on `Admin > Users & Roles`, so role changes no longer depend only on a popup modal.
5. Kept normal Admin unable to see or manage Super Admin accounts.
6. Kept Super Admin able to assign all roles, including Super Admin.
7. Made user account column repair more compatible with older databases that do not have `language_preference` or some newer account columns.
8. Updated Run Upgrade to use the safer account-column repair helper.
9. Rechecked session timeout flow so timed-out sessions clear cleanly and login can continue without clearing the browser.

## Updated files

- `agent/profile.php`
- `supervisor/profile.php`
- `profile.php`
- `admin/users.php`
- `admin/run_upgrade.php`
- `config.php`
- `include/side_header.php`
- `include/navbar.php`
- `include/footer.php`
- `assets/css/sis-theme.css`

## Validation

- PHP syntax check passed for project PHP files.
- Main JavaScript file and key inline scripts checked with Node syntax validation.
- ZIP integrity should be tested after packaging.

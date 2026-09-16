# SIS Deep Repair Audit - Workflow, Roles, Profile, Timeout

Date: 2026-07-03

## Main problems found

1. `admin/users.php` had malformed HTML inside the Users & Roles table. An extra closing `</form>` could break role-change forms and password-reset modals in the browser.
2. `admin/outlets.php` had nested forms and malformed table cells. This could break buttons, bulk actions, and parts of the admin layout.
3. Several older admin table pages had `</div>` tags where table cells should close with `</td>`, causing unstable page layout and broken click areas.
4. Role changes could fail on older databases if the `users.role` enum had not yet been upgraded to include `super_admin`.
5. Duplicate-submit protection was not globally applied to every authenticated POST request. Some older forms depended only on the frontend footer injection.
6. The timeout/login flow was kept server-side only to avoid stale UI loops after timeout.

## Fixes applied

- Repaired Users & Roles form markup.
- Repaired My Account/Profile form markup.
- Added role normalization and automatic role-column repair helper.
- Hardened role update: if role update fails because the role enum is outdated, the system repairs the enum and retries.
- Made duplicate-submit protection idempotent and applied it from `requireLogin()` for authenticated POST pages.
- Repaired malformed table-cell closing tags in old Admin/Supervisor pages.
- Repaired Outlets page form structure and bulk action ID handling.
- Added safe upload folders for user profile photos with `.htaccess` protection.
- Confirmed that profile/account pages keep Agents and Supervisors limited to photo/password changes only.

## Validation

- PHP syntax check passed for all PHP files outside docs.
- Footer JavaScript syntax passed.
- Main theme JavaScript syntax passed.
- ZIP integrity passed after packaging.

## Important after upload

Login as Super Admin and run:

`Admin > Management & System > Run Upgrade`

This ensures old databases receive the latest user account columns and role enum updates.

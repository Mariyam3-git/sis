# SIS Account, Password, Role, Database, and Timeout Upgrade - 2026-07-02

## Added

- Shared `profile.php` page for all users.
- Users can upload a profile image.
- Users can change/reset their own password from My Profile.
- Agents and Supervisors are restricted to profile photo and password only; they cannot edit personal details.
- Admin and Super Admin can manage users and change user roles from `admin/users.php`.
- Normal Admin cannot see, create, edit, or manage Super Admin accounts.
- Super Admin-only `admin/database_console.php` for database inspection, filtering, and query execution.
- Database console query logging through `sis_db_console_log`.
- Super Admin page access control remains available for Admin, Supervisor, and Agent pages.

## Fixed

- Password Expiry (days) setting is now enforced at login and during active sessions.
- Expired/reset passwords redirect the user to My Profile before allowing dashboard access.
- Session timeout JavaScript now uses the configured timeout value instead of a hardcoded 30 minutes.
- Timeout redirects now clear the expired session safely so users can log in again without clearing browser data.
- Background notification/announcement 401 responses now redirect safely to logout/login.

## Database upgrade additions

Run `Admin > Management & System > Run Upgrade` as Super Admin to add/repair:

- `users.email`
- `users.profile_photo`
- `users.password_changed_at`
- `users.must_change_password`
- `sis_db_console_log`
- user role indexes

## Security notes

- Database Console is protected with `requireSuperAdmin()`.
- Normal Admin does not see Super Admin in Users & Roles.
- Normal Admin cannot assign `super_admin` role.
- Users cannot change their own role while logged in.
- Password reset by Admin/Super Admin forces the target user to change password at next login.

# SIS Full Audit, Role, Password, Profile, Theme & Help Fix

Date: 2026-07-09
Project: COMIUM SIS Ultimate

## Audit result

A full code-level audit was performed on the uploaded PHP/MySQL project with focus on:

- Users & Roles password reset workflow
- Admin user management and role editing
- Supervisor Add Sale / Start Customer workflow
- Profile photo upload workflow
- Supervisors Management, Agents Management and Outlets Management theme consistency
- Role-based Help/User Guide page
- PHP syntax safety after the changes

## Main issues found

### 1. Users & Roles reset password popup was unreliable
The page rendered Bootstrap modals but did not load the Bootstrap JavaScript bundle. This prevented Reset Password modals and other modal actions from working correctly in many environments.

### 2. Users & Roles did not provide full user detail popup/edit flow
Admin users needed a double-click workflow to inspect a user, view account details and edit the record without leaving the page.

### 3. Supervisor menu pointed Start Customer / Add Sale to the older page
The Supervisor sidebar linked Start Customer / Add Sale to `supervisor/add_entry.php`, while the richer Agent-like workflow already existed in `supervisor/add_sale.php`. This made supervisors see a less capable workflow.

### 4. Profile photo upload had weak diagnostics and brittle folder handling
The upload logic depended on a folder existing and on `move_uploaded_file()` succeeding without good fallback messaging. This caused upload failures on Windows/XAMPP-style local installs or when the upload folder was missing/not writable.

### 5. Management pages had mixed dark/light theme surfaces
Older page classes in Supervisors Management, Agents Management and Outlets Management still used hard-coded surfaces, making cards/tables/search areas inconsistent in dark mode.

### 6. Help page was too basic
The Help page did not explain all important pages/workflows and did not separate guidance by user role.

## Fixes applied

### `admin/users.php`
- Rebuilt the Users & Roles page action handling.
- Fixed reset password workflow.
- Added support for typed temporary passwords or auto-generated temporary passwords.
- Sets `must_change_password = 1` and updates `password_changed_at` when a password is reset.
- Added double-click row interaction to open a full user detail popup.
- Added View/Edit button for accessibility.
- Added editable user details modal: username, full name, email, role, outlet, shift, active status and optional password reset.
- Added protection so non-superadmin users cannot manage superadmin accounts.
- Added protection against admins changing their own role or disabling their own account from this page.
- Added Bootstrap JS bundle and SIS theme script to make modals and theme work properly.

### `profile.php`
- Rebuilt `sisProfileUploadPhoto()` with stronger validation and clearer upload error messages.
- Supports JPG, PNG, WEBP and GIF.
- Creates `assets/uploads/users` automatically if missing.
- Adds a safe `.htaccess` file inside the upload folder.
- Adds local fallback handling for environments where `move_uploaded_file()` fails unexpectedly.
- Applies safe file permissions after upload where supported.

### `supervisor/add_entry.php`
- Converted the old page into a compatibility redirect to `supervisor/add_sale.php`.
- This makes Supervisor Start Customer / Add Sale use the richer Agent-style flow.

### `include/side_header.php`
- Updated Supervisor sidebar so Start Customer / Add Sale opens `supervisor/add_sale.php`.
- Removed the confusing separate Sales Transactions menu link because the rich Add Sale page already contains transaction/edit workflow.

### `help.php`
- Rebuilt as an advanced role-based user guide.
- Agents see Agent guide only.
- Supervisors see Supervisor guide only.
- Admin and Super Admin see Admin, Supervisor and Agent guides.
- Added guidance for dashboard, add sale, same-customer workflow, edit sale, payments, support, shifts, reports, stock, targets, users/roles, outlets and settings.
- Added troubleshooting table for common issues.

### `assets/css/sis-theme.css`
- Added final management-page theme repair block.
- Fixed card/table/search/list/modal surfaces for dark and light mode.
- Improved hover/focus visibility for management tables.
- Normalized status badges and shift badges.
- Improved modal and alert visibility in dark mode.

### `admin/run_upgrade.php`
- Added upload folder check for `assets/uploads/users`.
- Creates folder and `.htaccess` automatically during upgrade.

### `config.php`
- Updated the asset cache-buster version so browsers load the new CSS/JS.
- Updated page registry so Supervisor Start Customer / Add Sale points to `supervisor/add_sale.php`.

## Validation performed

All PHP files were checked with PHP lint after the changes.

Result: **98 PHP files checked; 0 syntax errors found.**

Command used:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Important after-install steps

1. Upload/replace the fixed project files.
2. Login as System Owner/Admin.
3. Open **Run Upgrade** once so database columns and upload folders are checked.
4. Hard refresh the browser or clear cache so the new CSS loads.
5. Test:
   - Users & Roles → reset password
   - Users & Roles → double-click a user row → edit user
   - Supervisor → Start Customer / Add Sale
   - Profile → upload photo
   - Help page with Agent, Supervisor and Admin accounts

## Files changed

- `admin/users.php`
- `profile.php`
- `help.php`
- `assets/css/sis-theme.css`
- `include/side_header.php`
- `supervisor/add_entry.php`
- `config.php`
- `admin/run_upgrade.php`
- `docs/SIS_FULL_AUDIT_ROLE_PASSWORD_PROFILE_HELP_FIX_2026_07_09.md`

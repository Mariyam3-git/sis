# COMIUM SIS Ultimate - Merged Project

This package is one merged project made from:

1. **SIS (2)** - used as the main stable base.
2. **Showroom Information System** - selected useful modules were merged, cleaned, and standardized.

## What was improved

- Kept the stronger SIS base with complaint/support, handle timer, reporting helpers, super audit logs, stock, reconciliation, targets, and unified layout.
- Added a new **Admin Stock Dashboard** based on the Showroom stock dashboard, but rewritten into the SIS layout.
- Added **Shift Allocation** and **Shift Report** modules for Admin and Supervisor.
- Added the missing `shift_assignments` database table and safe automatic repair helper.
- Added the new migration: `migrations/2026_06_shift_assignment_module.sql`.
- Improved `config.php` so database values can be edited directly or supplied through environment variables.
- Removed the visible message: `No active support form fields found. Please ask Admin to create fields first.` from Agent/Supervisor support forms.
- Preserved selected original Showroom module files in `docs/merged_sources/` for reference only.
- Checked all PHP files with `php -l`; all included PHP files passed syntax linting.


## 2026-06-30 advanced upgrade added

- Removed **Complaint Reason** from Add Customer / Sale pages.
- Complaint reasons now belong only to **Complaint / Support**.
- Added Admin-managed **Main Reason** and **Sub Reason** dropdowns.
- Added lighter UI overrides so the full project looks cleaner and less dark/bold.
- Added Agent **My Shift** page.
- Added Supervisor **Shift Requests** page with early-end approval/rejection.
- Added weekly/monthly/custom shift scheduling with edit support.
- Added login-window control: assigned Agents can log in from one hour before shift start until one hour after shift end.
- Added overtime notification workflow for Supervisors.
- Added database migration note: `migrations/2026_06_30_advanced_support_shift_upgrade.sql`.

## Install on AMpps / XAMPP / local server

1. Extract this folder into your web server directory, for example:
   - AMpps: `C:\Program Files\Ampps\www\COMIUM_SIS_ULTIMATE`
   - XAMPP: `C:\xampp\htdocs\COMIUM_SIS_ULTIMATE`

2. Create a MySQL database called:

   ```sql
   comium_gm
   ```

3. Import:

   ```text
   comium_gm.sql
   ```

4. Open `config.php` and update these values if needed:

   ```php
   define('DB_HOST', getenv('SIS_DB_HOST') ?: 'localhost');
   define('DB_USER', getenv('SIS_DB_USER') ?: 'root');
   define('DB_PASS', getenv('SIS_DB_PASS') !== false ? getenv('SIS_DB_PASS') : '');
   define('DB_NAME', getenv('SIS_DB_NAME') ?: 'comium_gm');
   ```

5. Open the system in your browser:

   ```text
   http://localhost/COMIUM_SIS_ULTIMATE/login.php
   ```

6. After login as Admin, go to:

   ```text
   Admin > Management & System > Run Upgrade
   ```

   Then click **Run Upgrade** once. This safely checks Main/Sub Reason support tables, handle time columns, advanced shift assignment/request tables, indexes, and Super Admin support.

## Account security

The database contains sample accounts for local migration compatibility. Reset every account password before deployment, remove accounts that are not required, and set `SIS_DB_PASS` in the server environment. Do not publish default credentials.

## New module locations

### Admin

- `admin/stock_dashboard.php`
- `admin/shift_allocation.php`
- `admin/shift_report.php`

### Supervisor

- `supervisor/shift_allocation.php`
- `supervisor/shift_report.php`
- `supervisor/shift_requests.php`

### Agent

- `agent/my_shift.php`
- `agent/shift_logout.php`

### Shared helper

- `include/shift_helpers.php`
- `support_helpers.php`

## Notes

- The system remains PHP/MySQL and does not require Composer.
- Internet access is only used for Bootstrap and Bootstrap Icons CDN links already present in the project.
- The merged Showroom original files in `docs/merged_sources/` are not active pages; they are only kept so you can trace what was merged.


## 2026-06-30 Shift / Notification / Report Update

After uploading this version, log in as Admin and run:

`Admin > Management & System > Run Upgrade`

This adds the notification bell tables, Supervisor-approved Agent login access, the stricter early-shift-end flow, fixed top navbar, Admin sidebar search, Daily Showroom Report fixes, Sales Report display fixes, and Dalasis `D` currency across the system.

# Advanced Shift + Complaint/Support + UI Upgrade

Date: 2026-06-30

## Main changes

- Removed Complaint Reason from Add Customer / Sale pages.
- Moved reason handling fully into Complaint / Support.
- Added Admin-managed Main Reason and Sub Reason dropdowns.
- Updated Agent and Supervisor Complaint / Support forms to use dependent Main/Sub Reason dropdowns.
- Updated Support Reports CSV/table output to show Main Reason and Sub Reason.
- Reworked Admin > Complaint Reasons into Main Reason / Sub Reason management.
- Added lighter UI overrides so the project is cleaner, less bold, and not forced into dark colors.
- Added Agent > My Shift page.
- Added Agent early shift-end request workflow.
- Added Supervisor > Shift Requests for approving/rejecting early end requests and reviewing overtime notices.
- Upgraded shift allocation for single-day, weekly, monthly, and custom-range schedules.
- Added edit support for assigned shifts.
- Added login window rule: an assigned Agent can log in from one hour before shift start until one hour after shift end.
- Added overtime workflow: after one hour past shift end, Agent gets a continue-overtime prompt; Supervisor receives overtime notification.
- Added optional 15-minute delayed logout when Agent declines overtime continuation.

## Database changes

Application helpers and Admin > SIS Upgrade repair the database automatically.

New/updated tables and columns:

- `support_tickets.main_reason`
- `support_tickets.sub_reason_id`
- `support_tickets.idx_support_reason`
- `shift_assignments.schedule_scope`
- `shift_assignments.early_end_allowed`
- `shift_assignments.overtime_allowed`
- `shift_requests`

## Files heavily changed/added

- `support_helpers.php`
- `admin/complaint_reasons.php`
- `admin/support_reports.php`
- `admin/run_upgrade.php`
- `include/shift_helpers.php`
- `include/side_header.php`
- `login.php`
- `logout.php`
- `agent/dashboard.php`
- `agent/support.php`
- `agent/my_shift.php`
- `agent/shift_logout.php`
- `supervisor/support.php`
- `supervisor/shift_requests.php`
- `supervisor/shift_allocation.php`
- `admin/shift_allocation.php`
- `assets/css/sis-theme.css`
- `migrations/2026_06_30_advanced_support_shift_upgrade.sql`

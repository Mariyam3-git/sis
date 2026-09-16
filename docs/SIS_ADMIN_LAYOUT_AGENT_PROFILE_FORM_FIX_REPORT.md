# SIS Admin Layout, Agent Profile, and Customer Form Fix Report

## Completed Changes

- Added the Admin pages to the same shared include layout used by Agent and Supervisor.
- Updated `include/side_header.php` with grouped navigation sections for Admin, Agent, and Supervisor.
- Moved Complaint / Support Reports under the Admin **Reports** section.
- Kept Admin Complaint / Support Form Builder under Customer Care Setup.
- Converted Admin pages to use `include/side_header.php`, `include/navbar.php`, and `include/footer.php`.
- Fixed Admin content scrolling and table overflow using shared layout CSS.
- Fixed `agent/profile.php` fatal error by replacing old/non-existing columns with schema-safe queries:
  - `sales.payment_date` instead of `sales.sale_date` when applicable.
  - `visitors.visit_time` instead of `visitors.visited_at` when applicable.
  - `visitors.agent_id` instead of `visitors.added_by` when applicable.
  - `agent_shifts` instead of missing `session_logs` when applicable.
- Redesigned Agent Add Customer page to match the Supervisor flow:
  - Start Attending Customer card.
  - Add Customer form opens in a modal.
  - Timer starts when the modal opens.
  - Records table remains scrollable.

## Test Steps

1. Login as Admin and open dashboard, sales, reports, stock pages, support reports, and settings.
2. Confirm all Admin pages have the same sidebar, navbar, content area, and footer.
3. Open Admin Reports and confirm Complaint / Support Reports is listed under Reports in the sidebar.
4. Login as Agent and open `agent/profile.php`; it should load without `mysqli_stmt_bind_param()` fatal error.
5. Login as Agent and open Add Customer / Sale; click Start Attending Customer and confirm the form opens like Supervisor.
6. Login as Supervisor and confirm the existing Start Customer / Add Sale flow still works.

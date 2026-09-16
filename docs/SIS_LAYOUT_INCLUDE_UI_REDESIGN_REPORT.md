# SIS Layout Include and UI Redesign Report

## Summary
This update adds a real shared layout system for the SIS frontend instead of repeating different headers and navigation blocks on every page.

## New Include Folder
A new `include/` folder was added with:

- `include/side_header.php` — shared sidebar / side menu for Agent, Supervisor, and Admin layout pages.
- `include/navbar.php` — shared top navigation bar with page title, date, theme toggle, user chip, and logout.
- `include/footer.php` — shared footer plus common sidebar/mobile/scroll helper JavaScript.

## Main UI Fixes
- Agent pages now use the shared sidebar, navbar, content wrapper, and footer.
- Supervisor pages now use the shared sidebar, navbar, content wrapper, and footer.
- Admin support/report/debug pages were moved into the same shared layout.
- Record tables now scroll properly with both horizontal and vertical scrolling.
- Body/page overflow was fixed so records are no longer hidden when the table is long.
- Mobile sidebar toggle was added for smaller screens.
- Light/Dark theme toggle now works with the new shared navbar button.

## Redesigned Pages
The Complaint / Support pages were redesigned for Agent and Supervisor:

- Better page hero/header.
- Better complaint/support form layout.
- Cleaner customer details section.
- Cleaner ticket details section.
- Admin-created fields still display inside the form.
- Latest records now show inside a scrollable table.

The following pages now use the shared include layout:

### Agent
- `agent/dashboard.php`
- `agent/dashboard1.php`
- `agent/process_payment.php`
- `agent/profile.php`
- `agent/stock.php`
- `agent/support.php`

### Supervisor
- `supervisor/dashboard.php`
- `supervisor/add_entry.php`
- `supervisor/add_sale.php`
- `supervisor/agent_performance.php`
- `supervisor/cash_reconciliation.php`
- `supervisor/pending_payments.php`
- `supervisor/service_analysis.php`
- `supervisor/stock.php`
- `supervisor/stock_dashboard.php`
- `supervisor/stock_requests.php`
- `supervisor/support.php`
- `supervisor/targets.php`
- `supervisor/visitors.php`

### Admin
- `admin/support_forms.php`
- `admin/support_reports.php`
- `admin/handle_timer_debug.php`
- `admin/threshold_sethings.php`

## CSS / JS Updates
Updated:

- `assets/css/sis-theme.css`
- `assets/js/sis-theme.js`

Added layout styles for:

- shared sidebar
- shared topbar
- shared footer
- responsive content area
- scrollable record tables
- support form cards
- support report table layout
- mobile sidebar behavior

## Validation
All PHP files passed syntax validation.

Result file:

- `docs/php_lint_results_layout_includes.txt`

## How to Test
1. Login as Agent.
2. Open Agent Dashboard.
3. Confirm left sidebar, top navbar, footer, and page scroll work.
4. Open Complaint / Support.
5. Submit a test complaint.
6. Confirm latest records table scrolls.
7. Login as Supervisor.
8. Repeat the same test.
9. Login as Admin.
10. Open Support Form Builder, Support Reports, and Handle Timer Debug.
11. Confirm tables scroll and the layout is consistent.
12. Test on mobile width and confirm sidebar opens from the menu button.

No database upgrade is required for this layout update.

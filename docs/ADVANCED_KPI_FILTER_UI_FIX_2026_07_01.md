# Advanced KPI Report Filter UI Fix - 2026-07-01

## Updated file
- `admin/reports.php`

## What changed
- Rebuilt the Advanced KPI Reports filter section as a clean card-based filter panel.
- Added quick date range buttons: Today, This Month, Last 30 Days, and This Year.
- Added responsive grid layout for date, region, showroom, service, purpose, staff, and handle time filters.
- Added clear Apply Filters and Reset actions.
- Added active-filter indicator.
- Added front-end validation so From Date cannot be later than To Date.
- Updated report queries so key KPI cards, charts, and report tables respect the active filters more consistently.

## Validation
- PHP syntax check passed for all PHP files.

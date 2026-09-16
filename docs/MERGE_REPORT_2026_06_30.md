# Merge Report - 2026-06-30

## Main decision

The final project uses **SIS (2)** as the base because it already contained the strongest production modules: support/complaints, handle-time fixes, reporting helpers, audit logs, stock management, targets, visitors, reconciliation, and unified layout files.

The **Showroom Information System** project was used as a source for useful missing features. The active merged features were rewritten into the SIS standard instead of copying old duplicate pages directly.

## Active files added or changed

### Added

- `admin/stock_dashboard.php`
- `admin/shift_allocation.php`
- `admin/shift_report.php`
- `supervisor/shift_allocation.php`
- `supervisor/shift_report.php`
- `include/shift_helpers.php`
- `migrations/2026_06_shift_assignment_module.sql`
- `README_FIRST.md`
- `docs/merged_sources/*`

### Updated

- `config.php`
  - Added environment-variable database support.
  - Added `ensureShiftAssignmentsTable()`.
  - Auto-checks handle-time storage and shift assignment storage.

- `support_helpers.php`
  - Removed the visible Admin-created form fields warning from operational forms.

- `include/side_header.php`
  - Added Shift Allocation and Shift Report menu links.

- `admin/run_upgrade.php`
  - Added shift assignment table check.

- `comium_gm.sql`
  - Added `super_admin` to user role enum.
  - Added `shift_assignments` table.
  - Added default Super Admin insert.

## Validation

All PHP files passed syntax checking using:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

No PHP syntax errors were found.

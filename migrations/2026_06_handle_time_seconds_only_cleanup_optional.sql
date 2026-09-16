-- No longer used. The current Handle Time fix keeps these sales columns:
-- handle_started_at, handle_completed_at, handle_seconds.
-- Do not drop them. Use admin/run_upgrade.php or the migration below instead.

ALTER TABLE `sales`
  ADD COLUMN `handle_started_at` DATETIME NULL,
  ADD COLUMN `handle_completed_at` DATETIME NULL,
  ADD COLUMN `handle_seconds` INT DEFAULT 0;

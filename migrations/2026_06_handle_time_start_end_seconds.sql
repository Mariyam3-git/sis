-- Handle Time storage on sales: start time, completed time, and seconds.
-- If columns already exist, add them manually only where missing or run admin/run_upgrade.php.

ALTER TABLE `sales`
  ADD COLUMN `handle_started_at` DATETIME NULL,
  ADD COLUMN `handle_completed_at` DATETIME NULL,
  ADD COLUMN `handle_seconds` INT DEFAULT 0;

ALTER TABLE `sales`
  ADD KEY `idx_sales_handle_seconds` (`handle_seconds`),
  ADD KEY `idx_sales_handle_times` (`handle_started_at`, `handle_completed_at`);

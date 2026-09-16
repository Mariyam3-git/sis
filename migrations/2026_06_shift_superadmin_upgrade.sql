-- SIS Shift Reopen + Super Admin Upgrade - June 2026
-- Run after backing up the database. This does NOT create a hidden/unlogged account.
-- Super Admin actions are recorded separately in super_audit_log and visible only to Super Admins.

ALTER TABLE `users`
  MODIFY `role` ENUM('admin','super_admin','supervisor','agent') NOT NULL;

CREATE TABLE IF NOT EXISTS `super_audit_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `action_type` VARCHAR(120) NOT NULL,
  `record_id` INT NULL,
  `details` TEXT NULL,
  `timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_super_audit_user_time` (`user_id`, `timestamp`),
  KEY `idx_super_audit_action_time` (`action_type`, `timestamp`),
  CONSTRAINT `fk_super_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `agent_shifts`
  ADD KEY `idx_agent_shifts_active` (`agent_id`, `shift_date`, `logout_time`),
  ADD KEY `idx_agent_shifts_date_status` (`shift_date`, `logout_time`);

-- Deliberately does not create or reactivate a default privileged account.
-- Create the initial owner explicitly with a unique password.

-- Advanced Complaint/Support + Shift workflow upgrade (2026-06-30)
-- The PHP app also repairs these columns/tables automatically through support_helpers.php and include/shift_helpers.php.
-- For existing MySQL databases, run Admin > SIS Upgrade after uploading the files.

CREATE TABLE IF NOT EXISTS `shift_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `assignment_id` INT NULL,
  `agent_id` INT NOT NULL,
  `request_type` ENUM('early_end','overtime','login_access') NOT NULL DEFAULT 'early_end',
  `reason` TEXT NULL,
  `status` ENUM('pending','approved','rejected','notified') NOT NULL DEFAULT 'pending',
  `supervisor_id` INT NULL,
  `supervisor_note` TEXT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at` DATETIME NULL,
  `used_at` DATETIME NULL,
  `overtime_started_at` DATETIME NULL,
  `overtime_minutes` INT NOT NULL DEFAULT 0,
  `logout_deadline` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shift_requests_agent` (`agent_id`, `requested_at`),
  KEY `idx_shift_requests_assignment` (`assignment_id`, `request_type`, `status`),
  KEY `idx_shift_requests_status` (`status`, `request_type`),
  KEY `idx_shift_requests_used` (`request_type`, `status`, `used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Existing databases are upgraded safely by Admin > SIS Upgrade because portable MySQL
-- does not support IF NOT EXISTS on ADD COLUMN across all target versions.


CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `role_scope` VARCHAR(50) NULL,
  `outlet_id` INT NULL,
  `title` VARCHAR(180) NOT NULL,
  `message` TEXT NULL,
  `url` VARCHAR(255) NULL,
  `related_type` VARCHAR(60) NULL,
  `related_id` INT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_notifications_user` (`user_id`, `is_read`, `created_at`),
  KEY `idx_user_notifications_role` (`role_scope`, `outlet_id`, `is_read`, `created_at`),
  KEY `idx_user_notifications_related` (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE `settings` SET `setting_value` = 'D' WHERE `setting_key` IN ('currency', 'currency_symbol');

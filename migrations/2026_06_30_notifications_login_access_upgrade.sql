-- SIS notification bell + Supervisor-approved login access workflow upgrade (2026-06-30)

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

ALTER TABLE `shift_requests`
  MODIFY `request_type` ENUM('early_end','overtime','login_access') NOT NULL DEFAULT 'early_end';

ALTER TABLE `shift_requests`
  ADD COLUMN `used_at` DATETIME NULL AFTER `decided_at`;

ALTER TABLE `shift_requests`
  ADD KEY `idx_shift_requests_used` (`request_type`, `status`, `used_at`);

UPDATE `settings` SET `setting_value` = 'D' WHERE `setting_key` IN ('currency', 'currency_symbol');

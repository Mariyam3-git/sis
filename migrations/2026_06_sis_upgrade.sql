-- SIS Upgrade Migration - June 2026
-- Run once after backing up the database.
-- Adds complaint reason management, request handling-time tracking, and report indexes.

CREATE TABLE IF NOT EXISTS `complaint_reasons` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `reason` VARCHAR(150) NOT NULL,
  `category` VARCHAR(80) DEFAULT 'General',
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_complaint_reason` (`reason`),
  KEY `idx_complaint_reasons_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `complaint_reasons` (`reason`, `category`, `sort_order`) VALUES
('Network coverage issue', 'Network', 10),
('Slow data / internet issue', 'Network', 20),
('SIM registration issue', 'SIM Services', 30),
('SIM replacement issue', 'SIM Services', 40),
('ComCach deposit issue', 'ComCach', 50),
('ComCach withdrawal issue', 'ComCach', 60),
('Airtime or bundle not received', 'Billing', 70),
('Incorrect charge / billing dispute', 'Billing', 80),
('Customer service follow-up', 'Service Quality', 90),
('Other complaint', 'General', 100);

ALTER TABLE `visitors`
  ADD COLUMN `complaint_reason_id` INT NULL AFTER `service_id`,
  ADD COLUMN `attended_at` DATETIME NULL AFTER `visit_time`,
  ADD COLUMN `completed_at` DATETIME NULL AFTER `attended_at`,
  ADD COLUMN `handling_seconds` INT NULL AFTER `completed_at`;

UPDATE `visitors`
SET `attended_at` = COALESCE(`attended_at`, `visit_time`),
    `completed_at` = CASE WHEN `status` = 'Completed' THEN COALESCE(`completed_at`, `updated_at`) ELSE `completed_at` END,
    `handling_seconds` = CASE
      WHEN `status` = 'Completed' THEN TIMESTAMPDIFF(SECOND, COALESCE(`attended_at`, `visit_time`), COALESCE(`completed_at`, `updated_at`))
      ELSE `handling_seconds`
    END
WHERE `attended_at` IS NULL OR (`status` = 'Completed' AND (`completed_at` IS NULL OR `handling_seconds` IS NULL));

ALTER TABLE `visitors`
  ADD KEY `idx_visitors_handling_time` (`handling_seconds`),
  ADD KEY `idx_visitors_attended_completed` (`attended_at`, `completed_at`),
  ADD KEY `idx_visitors_complaint_reason` (`complaint_reason_id`),
  ADD CONSTRAINT `fk_visitors_complaint_reason` FOREIGN KEY (`complaint_reason_id`) REFERENCES `complaint_reasons` (`id`) ON DELETE SET NULL;

ALTER TABLE `sales`
  ADD COLUMN `handle_started_at` DATETIME NULL,
  ADD COLUMN `handle_completed_at` DATETIME NULL,
  ADD COLUMN `handle_seconds` INT DEFAULT 0,
  ADD KEY `idx_sales_handle_seconds` (`handle_seconds`),
  ADD KEY `idx_sales_handle_times` (`handle_started_at`, `handle_completed_at`),
  ADD KEY `idx_sales_report_date_status` (`payment_date`, `payment_status`, `outlet_id`, `agent_id`);

UPDATE `sales` s
LEFT JOIN `visitors` v ON s.`visitor_id` = v.`id`
SET s.`handle_completed_at` = COALESCE(s.`handle_completed_at`, s.`payment_date`),
    s.`handle_seconds` = COALESCE(NULLIF(s.`handle_seconds`, 0), v.`handling_seconds`, TIMESTAMPDIFF(SECOND, v.`visit_time`, s.`payment_date`), 1),
    s.`handle_started_at` = COALESCE(s.`handle_started_at`, DATE_SUB(COALESCE(s.`handle_completed_at`, s.`payment_date`), INTERVAL COALESCE(NULLIF(s.`handle_seconds`, 0), v.`handling_seconds`, TIMESTAMPDIFF(SECOND, v.`visit_time`, s.`payment_date`), 1) SECOND))
WHERE s.`handle_seconds` IS NULL OR s.`handle_seconds` = 0 OR s.`handle_started_at` IS NULL OR s.`handle_completed_at` IS NULL;

ALTER TABLE `visitors`
  ADD KEY `idx_visitors_report_date_status` (`visit_time`, `status`, `outlet_id`, `agent_id`, `service_id`, `purpose_id`);


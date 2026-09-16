-- Shift Allocation + Shift Report module merged from Showroom Information System into SIS.
-- Safe to run on existing databases. The application also creates/repairs this table automatically.

CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `agent_id` INT NOT NULL,
  `shift_date` DATE NOT NULL,
  `shift_type` ENUM('morning','afternoon','night') NOT NULL DEFAULT 'morning',
  `shift_start` TIME NOT NULL DEFAULT '08:00:00',
  `shift_end` TIME NOT NULL DEFAULT '16:00:00',
  `status` ENUM('scheduled','present','late','absent','cancelled') NOT NULL DEFAULT 'scheduled',
  `no_show_reason` VARCHAR(60) NULL,
  `notes` TEXT NULL,
  `schedule_scope` ENUM('single','weekly','monthly','custom') NOT NULL DEFAULT 'single',
  `early_end_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `overtime_allowed` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shift_agent_date` (`agent_id`, `shift_date`),
  KEY `idx_shift_date_status` (`shift_date`, `status`),
  KEY `idx_shift_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `shift_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `assignment_id` INT NULL,
  `agent_id` INT NOT NULL,
  `request_type` ENUM('early_end','overtime') NOT NULL DEFAULT 'early_end',
  `reason` TEXT NULL,
  `status` ENUM('pending','approved','rejected','notified') NOT NULL DEFAULT 'pending',
  `supervisor_id` INT NULL,
  `supervisor_note` TEXT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at` DATETIME NULL,
  `overtime_started_at` DATETIME NULL,
  `overtime_minutes` INT NOT NULL DEFAULT 0,
  `logout_deadline` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shift_requests_agent` (`agent_id`, `requested_at`),
  KEY `idx_shift_requests_assignment` (`assignment_id`, `request_type`, `status`),
  KEY `idx_shift_requests_status` (`status`, `request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

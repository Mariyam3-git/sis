-- Complaint / Support dynamic form module for SIS
-- Admin creates active form fields. Agents and Supervisors submit support tickets using those fields.

CREATE TABLE IF NOT EXISTS `support_form_fields` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `field_label` VARCHAR(150) NOT NULL,
  `field_key` VARCHAR(80) NOT NULL,
  `field_type` ENUM('text','textarea','select','number','date','phone','email') NOT NULL DEFAULT 'text',
  `field_options` TEXT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_support_field_key` (`field_key`),
  KEY `idx_support_fields_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ticket_no` VARCHAR(40) NOT NULL,
  `submitted_by` INT NULL,
  `submitted_role` VARCHAR(30) NULL,
  `outlet_id` INT NULL,
  `customer_name` VARCHAR(120) NULL,
  `customer_phone` VARCHAR(30) NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'Complaint',
  `priority` ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  `status` ENUM('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `main_reason` VARCHAR(100) NULL,
  `sub_reason_id` INT NULL,
  `subject` VARCHAR(180) NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_support_ticket_no` (`ticket_no`),
  KEY `idx_support_date_status` (`created_at`, `status`),
  KEY `idx_support_user` (`submitted_by`, `submitted_role`),
  KEY `idx_support_outlet` (`outlet_id`),
  KEY `idx_support_reason` (`main_reason`, `sub_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `support_ticket_values` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ticket_id` INT NOT NULL,
  `field_id` INT NOT NULL,
  `field_label` VARCHAR(150) NOT NULL,
  `field_value` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_values_ticket` (`ticket_id`),
  KEY `idx_support_values_field` (`field_id`),
  CONSTRAINT `fk_support_values_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_values_field` FOREIGN KEY (`field_id`) REFERENCES `support_form_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `support_form_fields` (`field_label`, `field_key`, `field_type`, `field_options`, `is_required`, `is_active`, `sort_order`) VALUES
('MSISDN / Customer Number', 'msisdn_customer_number', 'phone', '', 0, 1, 20),
('Problem Details', 'problem_details', 'textarea', '', 1, 1, 30),
('Action Taken', 'action_taken', 'textarea', '', 0, 1, 40),
('Follow-up Required', 'follow_up_required', 'select', 'No\nYes', 0, 1, 50);


-- Main Reason / Sub Reason defaults used by Complaint / Support dropdowns.
INSERT IGNORE INTO `complaint_reasons` (`category`, `reason`, `sort_order`, `is_active`) VALUES
('Network', 'No Service', 10, 1),
('Network', 'Poor Signal', 20, 1),
('Network', 'Data Not Working', 30, 1),
('Billing', 'Wrong Deduction', 40, 1),
('Billing', 'Bundle Not Received', 50, 1),
('SIM / Registration', 'SIM Swap Issue', 60, 1),
('SIM / Registration', 'Registration Update', 70, 1),
('ComCach', 'Cash-in Issue', 80, 1),
('ComCach', 'Withdrawal Issue', 90, 1),
('Customer Care', 'Follow-up Required', 100, 1),
('Customer Care', 'Other', 110, 1);

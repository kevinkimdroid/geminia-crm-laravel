-- Complaint Register - IRA compliance requirement
-- Run manually if migration fails: mysql -u root -p vtiger < database/sql/complaints.sql

CREATE TABLE IF NOT EXISTS `complaints` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `complaint_ref` VARCHAR(32) NOT NULL UNIQUE,
    `date_received` DATE NOT NULL,
    `complainant_name` VARCHAR(255) NOT NULL,
    `complainant_phone` VARCHAR(50) NULL,
    `complainant_email` VARCHAR(255) NULL,
    `contact_id` BIGINT UNSIGNED NULL,
    `policy_number` VARCHAR(64) NULL,
    `nature` VARCHAR(100) NULL,
    `description` TEXT NOT NULL,
    `source` VARCHAR(50) NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Received',
    `priority` VARCHAR(20) NOT NULL DEFAULT 'Medium',
    `assigned_to` VARCHAR(255) NULL,
    `date_resolved` DATE NULL,
    `resolution_notes` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `complaints_contact_id_index` (`contact_id`),
    INDEX `complaints_status_index` (`status`),
    INDEX `complaints_date_received_index` (`date_received`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

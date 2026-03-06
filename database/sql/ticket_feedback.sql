-- Run this in the vtiger database (your app uses vtiger as default)
-- Example: mysql -u root -p vtiger < database/sql/ticket_feedback.sql
-- Or in MySQL/phpMyAdmin: USE vtiger; then paste the CREATE below

CREATE TABLE IF NOT EXISTS `ticket_feedback` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` BIGINT UNSIGNED NOT NULL,
    `contact_id` BIGINT UNSIGNED NOT NULL,
    `rating` VARCHAR(20) NOT NULL,
    `comment` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `ticket_feedback_ticket_id_index` (`ticket_id`),
    UNIQUE KEY `ticket_feedback_ticket_id_contact_id_unique` (`ticket_id`, `contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

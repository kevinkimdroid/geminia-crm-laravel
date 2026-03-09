-- Link emails to complaints. Run manually if migration fails:
-- mysql -h HOST -u USER -p vtiger < database/sql/mail_manager_emails_add_complaint_id.sql

ALTER TABLE mail_manager_emails ADD COLUMN complaint_id BIGINT UNSIGNED NULL AFTER ticket_id;

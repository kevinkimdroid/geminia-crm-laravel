-- Optional: Add indexes to speed up tickets page (3k+ tickets).
-- Run on vtiger DB: mysql -h HOST -u USER -p vtiger < database/sql/tickets_performance_indexes.sql
-- If index exists, you may see "Duplicate key name" - safe to ignore or run DROP INDEX first.

-- vtiger_crmentity: for ticket list (deleted + setype + createdtime)
-- DROP INDEX idx_crmentity_deleted_setype_created ON vtiger_crmentity;
CREATE INDEX idx_crmentity_deleted_setype_created ON vtiger_crmentity(deleted, setype, createdtime);

-- vtiger_troubletickets: for filtered lists (Open, Closed, etc.)
-- DROP INDEX idx_troubletickets_status_contact ON vtiger_troubletickets;
CREATE INDEX idx_troubletickets_status_contact ON vtiger_troubletickets(status, contact_id);

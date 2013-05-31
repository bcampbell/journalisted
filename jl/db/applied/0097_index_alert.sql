-- add missing index!
BEGIN;
CREATE INDEX alert_journo_id_idx ON alert(journo_id);
COMMIT;


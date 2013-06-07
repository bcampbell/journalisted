-- add some missing indexes!
BEGIN;
CREATE INDEX journo_admired_journo_id_idx ON journo_admired(journo_id);
CREATE INDEX journo_admired_admired_id_idx ON journo_admired(admired_id);
COMMIT;


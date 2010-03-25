BEGIN;
ALTER TABLE journo_employment ADD COLUMN current boolean NOT NULL default false;

UPDATE journo_employment SET current=(year_to IS NULL);
COMMIT;


BEGIN;
ALTER TABLE journo_education ADD COLUMN kind character(1) NOT NULL DEFAULT 'u';
COMMIT;


BEGIN;

ALTER TABLE journo_employment ADD COLUMN kind char(1) NOT NULL DEFAULT 'e';

COMMIT;


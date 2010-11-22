BEGIN;
    ALTER TABLE journo_employment ADD COLUMN rank integer NOT NULL default 0;
COMMIT;


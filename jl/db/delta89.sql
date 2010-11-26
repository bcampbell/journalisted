BEGIN;
    ALTER TABLE journo_employment ADD COLUMN src integer REFERENCES link(id) ON DELETE SET NULL;
COMMIT;


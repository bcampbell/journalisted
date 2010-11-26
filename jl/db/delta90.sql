BEGIN;
    ALTER TABLE journo_education ADD COLUMN src integer REFERENCES link(id) ON DELETE SET NULL;
    ALTER TABLE journo_awards ADD COLUMN src integer REFERENCES link(id) ON DELETE SET NULL;
COMMIT;


BEGIN;
-- add a timestamp to user permissions
ALTER TABLE person_permission ADD COLUMN created timestamp NOT NULL DEFAULT NOW();
COMMIT;


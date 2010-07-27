BEGIN;
ALTER TABLE missing_articles ADD COLUMN reason text DEFAULT '' NOT NULL;
COMMIT;


-- tidy up journo_weblink table

DROP INDEX IF EXISTS journo_weblink_idx_url;
ALTER TABLE journo_weblink DROP COLUMN source;
ALTER TABLE journo_weblink DROP COLUMN type;
ALTER TABLE journo_weblink ADD COLUMN kind text NOT NULL DEFAULT '';

UPDATE journo_weblink SET kind='wikipedia-profile' WHERE url ilike '%wikipedia.%';
UPDATE journo_weblink SET kind='guardian-profile' WHERE url ILIKE '%guardian.co.uk%';




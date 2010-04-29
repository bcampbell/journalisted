-- tidy up journo_bio table
ALTER TABLE journo_bio DROP COLUMN context;
ALTER TABLE journo_bio DROP COLUMN type;
ALTER TABLE journo_bio ADD COLUMN kind text NOT NULL DEFAULT '';
CREATE INDEX journo_bio_idx_journo_id ON journo_bio (journo_id);

UPDATE journo_bio SET kind='wikipedia-profile' WHERE srcurl ilike '%wikipedia.%';
UPDATE journo_bio SET kind='guardian-profile' WHERE srcurl ILIKE '%guardian.co.uk%';


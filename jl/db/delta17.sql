-- add normalised name for fuzzy matching
ALTER TABLE journo ADD COLUMN normalisedname text NOT NULL DEFAULT '';


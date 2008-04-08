BEGIN;
---------------------------------------------------------------------

-- We would like to use a journo_id column instead of journo_ref
-- to identify the journo in the journo_email table.

ALTER TABLE journo_email  ADD COLUMN journo_id INTEGER REFERENCES journo(id);

-- Now populate it.

UPDATE journo_email SET journo_id=(SELECT id FROM journo WHERE ref=journo_ref);

-- Make journo_id NOT NULL.

ALTER TABLE journo_email  ALTER COLUMN journo_id SET NOT NULL;

-- ...which means we've established the mapping to journo
-- so we can safely remove the old journo_ref column.

ALTER TABLE journo_email  DROP COLUMN journo_ref;

---------------------------------------------------------------------
COMMIT;

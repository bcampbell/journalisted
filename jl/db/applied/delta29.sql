BEGIN;

-- They've only gone and turned off using OIDs by default in PostgreSQL 8.1.
-- Add an id column after all.
--
-- This still won't be the primary key!
-- It is unique, not-null and indexable though, so that's probably not
-- important. We may want to rebuild the table with id as primary key later.
--
ALTER TABLE journo_email ADD COLUMN id SERIAL NOT NULL;

-- Encourage the database to perform the usual optimisation when
-- searching on id.
--
CREATE UNIQUE INDEX journo_email_idkey ON journo_email (id);

COMMIT;

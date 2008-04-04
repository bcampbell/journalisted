-- Has a human approved publishing this bio on the journo's page?
--
ALTER TABLE journo_bio ADD COLUMN approved BOOLEAN DEFAULT false;


-- They've only gone and turned off using OIDs by default in PostgreSQL 8.1.
-- Add an id column after all.
--
-- This still won't be the primary key!
-- It is unique, not-null and indexable though, so that's probably not
-- important. We may want to rebuild the table with id as primary key later.
--
ALTER TABLE journo_bio ADD COLUMN id SERIAL;

-- Encourage the database to perform the usual optimisation when
-- searching on id.
--
CREATE UNIQUE INDEX journo_bio_idkey ON journo_bio (id);

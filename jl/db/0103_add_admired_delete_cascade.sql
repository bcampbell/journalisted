BEGIN;
-- journo_admired was missing delete cascade on admired_id foreign key
ALTER TABLE journo_admired DROP CONSTRAINT journo_admired_admired_id_fkey;
ALTER TABLE journo_admired ADD CONSTRAINT "journo_admired_admired_id_fkey" FOREIGN KEY (admired_id) REFERENCES journo(id) ON DELETE CASCADE;
COMMIT;


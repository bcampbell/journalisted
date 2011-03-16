BEGIN;
ALTER TABLE person_permission DROP CONSTRAINT person_permission_journo_id_fkey;
ALTER TABLE person_permission ADD CONSTRAINT "person_permission_journo_id_fkey" FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;
ALTER TABLE person_permission DROP CONSTRAINT person_permission_person_id_fkey;
ALTER TABLE person_permission ADD CONSTRAINT "person_permission_person_id_fkey" FOREIGN KEY (person_id) REFERENCES person(id) ON DELETE CASCADE;
COMMIT;


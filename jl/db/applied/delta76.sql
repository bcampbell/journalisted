BEGIN;


ALTER TABLE journo ALTER COLUMN firstname_metaphone TYPE text;
ALTER TABLE journo ALTER COLUMN firstname_metaphone SET DEFAULT '';

ALTER TABLE journo ALTER COLUMN lastname_metaphone TYPE text;
ALTER TABLE journo ALTER COLUMN lastname_metaphone SET DEFAULT '';

COMMIT;

BEGIN;
CREATE INDEX journo_firstname_metaphone_idx ON journo( firstname_metaphone );
CREATE INDEX journo_lastname_metaphone_idx ON journo( lastname_metaphone );
COMMIT;

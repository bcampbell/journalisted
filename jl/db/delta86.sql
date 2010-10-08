BEGIN;
ALTER TABLE organisation ADD COLUMN sortname text not null default '';


UPDATE organisation SET sortname=regexp_replace( lower(prettyname), '^the[[:space:]]+', '' );
--UPDATE organisation SET shortname=regexp_replace( lower(shortname), '^www[.]', '' );
COMMIT;


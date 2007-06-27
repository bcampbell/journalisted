-- add support for cascading deletes, and add references missing from primary keys 

ALTER TABLE article_dupe DROP CONSTRAINT article_dupe_dupeof_id_fkey;
ALTER TABLE article_dupe ADD FOREIGN KEY (dupeof_id) REFERENCES article(id) ON DELETE CASCADE;
ALTER TABLE article_dupe ADD FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


ALTER TABLE article_tag DROP CONSTRAINT "$1";
ALTER TABLE article_tag ADD FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;

ALTER TABLE journo_attr DROP CONSTRAINT "$1";
ALTER TABLE journo_attr DROP CONSTRAINT "$2";
ALTER TABLE journo_attr ADD FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;
ALTER TABLE journo_attr ADD FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;

ALTER TABLE journo_alias DROP CONSTRAINT "$1";
ALTER TABLE journo_alias ADD FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;

ALTER TABLE journo_jobtitle DROP CONSTRAINT "$1";
ALTER TABLE journo_jobtitle ADD FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;

ALTER TABLE journo_weblink DROP CONSTRAINT "$1";
ALTER TABLE journo_weblink ADD FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;



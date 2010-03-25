-- trigger fns are from delta51.sql

BEGIN;

DROP TABLE IF EXISTS journo_admired;
CREATE TABLE journo_admired (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    admired_name text,
    admired_id integer REFERENCES journo(id)
);

-- journo_admired triggers
DROP TRIGGER IF EXISTS journo_admired_insert ON journo_admired;
DROP TRIGGER IF EXISTS journo_admired_delete ON journo_admired;
DROP TRIGGER IF EXISTS journo_admired_update ON journo_admired;
CREATE TRIGGER journo_admired_insert AFTER INSERT ON journo_admired FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_admired_delete AFTER DELETE ON journo_admired FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_admired_update AFTER UPDATE ON journo_admired FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();



DROP TABLE IF EXISTS journo_education;
CREATE TABLE journo_education (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    school text NOT NULL default '',
    field text NOT NULL default '',
    qualification text NOT NULL default '',
    year_from smallint,
    year_to smallint
);

-- journo_education triggers
DROP TRIGGER IF EXISTS journo_education_insert ON journo_education;
DROP TRIGGER IF EXISTS journo_education_delete ON journo_education;
DROP TRIGGER IF EXISTS journo_education_update ON journo_education;
CREATE TRIGGER journo_education_insert AFTER INSERT ON journo_education FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_education_delete AFTER DELETE ON journo_education FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_education_update AFTER UPDATE ON journo_education FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();


DROP TABLE IF EXISTS journo_employment;
CREATE TABLE journo_employment (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    employer text NOT NULL default '',
    job_title text NOT NULL default '',
    year_from smallint,
    year_to smallint
);

-- journo_employment triggers
DROP TRIGGER IF EXISTS journo_employment_insert ON journo_employment;
DROP TRIGGER IF EXISTS journo_employment_delete ON journo_employment;
DROP TRIGGER IF EXISTS journo_employment_update ON journo_employment;
CREATE TRIGGER journo_employment_insert AFTER INSERT ON journo_employment FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_employment_delete AFTER DELETE ON journo_employment FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_employment_update AFTER UPDATE ON journo_employment FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();




DROP TABLE IF EXISTS journo_awards;
CREATE TABLE journo_awards (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    award text NOT NULL default '',
    year smallint
);

-- journo_awards triggers
DROP TRIGGER IF EXISTS journo_awards_insert ON journo_awards;
DROP TRIGGER IF EXISTS journo_awards_delete ON journo_awards;
DROP TRIGGER IF EXISTS journo_awards_update ON journo_awards;
CREATE TRIGGER journo_awards_insert AFTER INSERT ON journo_awards FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_awards_delete AFTER DELETE ON journo_awards FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_awards_update AFTER UPDATE ON journo_awards FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();



DROP TABLE IF EXISTS journo_books;
CREATE TABLE journo_books (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    title text NOT NULL default '',
    publisher text NOT NULL default '',
    year_published smallint
);

-- journo_books triggers
DROP TRIGGER IF EXISTS journo_books_insert ON journo_books;
DROP TRIGGER IF EXISTS journo_books_delete ON journo_books;
DROP TRIGGER IF EXISTS journo_books_update ON journo_books;
CREATE TRIGGER journo_books_insert AFTER INSERT ON journo_books FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_books_delete AFTER DELETE ON journo_books FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_books_update AFTER UPDATE ON journo_books FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();


COMMIT;

